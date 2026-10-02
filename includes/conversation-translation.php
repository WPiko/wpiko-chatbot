<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Translate conversation messages to the target language using OpenAI API
 * 
 * @param array $conversations Array of conversation database objects
 * @param string $target_language Language to translate to
 * @return array Translated conversations or original on error
 */
function wpiko_chatbot_translate_conversation($conversations, $target_language) {
    // Get and decrypt the API key
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    if (empty($encrypted_api_key)) {
        return $conversations; // Return original if no API key
    }
    
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        return $conversations; // Return original if decryption fails
    }
    
    // Prepare messages to translate
    $messages_to_translate = array();
    foreach ($conversations as $conversation) {
        if (!empty($conversation->message)) {
            $messages_to_translate[] = $conversation->message;
        }
    }
    
    if (empty($messages_to_translate)) {
        return $conversations; // No messages to translate
    }
    
    // Call OpenAI API to translate messages in batch
    $translated_messages = wpiko_chatbot_call_translation_api($messages_to_translate, $target_language, $api_key);
    
    if ($translated_messages === false) {
        return $conversations; // Return original on API error
    }
    
    // Replace messages with translations
    $index = 0;
    $translated_conversations = array();
    foreach ($conversations as $conversation) {
        $translated_conversation = clone $conversation;
        if (!empty($conversation->message) && isset($translated_messages[$index])) {
            $translated_conversation->message = $translated_messages[$index];
            $index++;
        }
        $translated_conversations[] = $translated_conversation;
    }
    
    return $translated_conversations;
}

/**
 * Call OpenAI Responses API to translate messages.
 * 
 * @param array $messages Array of messages to translate
 * @param string $target_language Target language
 * @param string $api_key OpenAI API key
 * @return array|false Array of translated messages or false on error
 */
function wpiko_chatbot_call_translation_api($messages, $target_language, $api_key) {
    // Create a system prompt for translation
    $system_prompt = sprintf(
        'You are a professional translator. Translate the following messages to %s. ' .
        'Maintain the original tone, formatting, and meaning. ' .
        'Preserve any HTML tags, markdown formatting, code blocks, and special characters exactly as they appear. ' .
        'Return one translation for every input message in the exact same order. ' .
        'Do not add explanations, comments, or additional text.',
        $target_language
    );
    
    // Format messages for translation. A strict schema keeps the output count and shape predictable.
    // JSON_UNESCAPED_SLASHES matters here: without it PHP encodes every "/" as "\/", so the model
    // sees "<\/strong>" and "https:\/\/example.com\/" in its input and — being told to preserve
    // characters exactly — copies those backslashes into the translation, breaking HTML and links.
    $messages_json = wp_json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $request_overrides = array(
        'text' => array(
            'format' => array(
                'type' => 'json_schema',
                'name' => 'conversation_translations',
                'strict' => true,
                'schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'translations' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                        ),
                    ),
                    'required' => array('translations'),
                    'additionalProperties' => false,
                ),
            ),
        ),
    );

    $response = wpiko_chatbot_openai_feature_text_request(
        $api_key,
        'translation',
        $system_prompt,
        $messages_json,
        $request_overrides
    );

    if (is_wp_error($response)) {
        wpiko_chatbot_log('Translation API error: ' . $response->get_error_message(), 'error');
        return false;
    }

    $translation_data = json_decode($response['wpiko_output_text'], true);
    $translated_messages = isset($translation_data['translations']) ? $translation_data['translations'] : null;

    if (!is_array($translated_messages)) {
        wpiko_chatbot_log('Translation API returned an invalid translations object', 'error');
        return false;
    }
    
    // Models occasionally emit escaped slashes anyway (they mimic JSON-ish input). Undo that so
    // closing tags and URLs stay valid; "\/" never appears legitimately in message content.
    $translated_messages = array_map(
        function ($translated_message) {
            return is_string($translated_message)
                ? str_replace('\\/', '/', $translated_message)
                : $translated_message;
        },
        $translated_messages
    );

    // Verify we got the same number of translations
    if (count($translated_messages) !== count($messages)) {
        wpiko_chatbot_log('Translation count mismatch: expected ' . count($messages) . ', got ' . count($translated_messages), 'error');
        return false;
    }
    
    return $translated_messages;
}

/**
 * AJAX handler for downloading translated conversation
 */
function wpiko_chatbot_download_translated_conversation() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    // Validate required parameters
    if (!isset($_POST['session_id']) || !isset($_POST['language'])) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
    $target_language = sanitize_text_field(wp_unslash($_POST['language']));
    
    global $wpdb;
    
    // Get conversation messages
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE session_id = %s ORDER BY id ASC",
        $session_id
    ));
    
    if (!$conversations) {
        wp_send_json_error('No conversation found');
        return;
    }
    
    // Check if translation is requested
    if ($target_language !== 'none' && !empty($target_language)) {
        // Translate the conversation
        $conversations = wpiko_chatbot_translate_conversation($conversations, $target_language);
    }
    
    // Get the chatbot name from options or use default
    $chatbot_name = get_option('wpiko_chatbot_name', 'Chatbot');
    
    // Generate HTML transcript
    $html_content = wpiko_chatbot_generate_html_transcript($conversations, $chatbot_name, $session_id);
    
    // Add language info to the transcript if translated
    if ($target_language !== 'none' && !empty($target_language)) {
        $language_note = '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 20px 0; border-radius: 5px; text-align: center; font-size: 12px;">' .
                        '<strong>Note:</strong> This conversation has been translated to ' . esc_html($target_language) . ' using AI.</div>';
        $html_content = str_replace('<div class="messages-container">', '<div class="messages-container">' . $language_note, $html_content);
    }
    
    wp_send_json_success($html_content);
}

add_action('wp_ajax_wpiko_chatbot_download_translated_conversation', 'wpiko_chatbot_download_translated_conversation');

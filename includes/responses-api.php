<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Responses API Integration

/**
 * Determine if a given Responses model supports tools/file_search attachments.
 */
function wpiko_chatbot_responses_model_supports_file_search($model)
{
    // Known to support tools/file_search
    $supports = array(
        'gpt-6-astra',
        'gpt-6.1-sol',
        'gpt-6-sol',
        'gpt-6-luna',
        'gpt-4.1',
        'gpt-4.1-mini',
        // GPT-5.2 family
        'gpt-5.2',
        // GPT-5.4 family
        'gpt-5.4',
        'gpt-5.4-mini',
        // GPT-5.5 family
        'gpt-5.5-2026-04-23',
        // GPT-5.6 family
        'gpt-5.6-sol',
        'gpt-5.6-terra',
        'gpt-5.6-luna'
    );
    if (in_array($model, $supports, true)) {
        return true;
    }
    // Heuristic: most modern 4.1/5-series variants support tools
    if (preg_match('/^(gpt-4\.1|gpt-5|gpt-5\.2|gpt-5\.4|gpt-5\.5|gpt-5\.6|gpt-6)/', $model)) {
        return true;
    }
    return false;
}

function wpiko_chatbot_get_responses_reasoning_effort_config()
{
    return array(
        'gpt-6-astra' => array('allowed' => array('low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-6.1-sol' => array('allowed' => array('low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-6-sol' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-6-luna' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-5.2' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh'), 'default' => 'none'),
        'gpt-5.4' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh'), 'default' => 'none'),
        'gpt-5.4-mini' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh'), 'default' => 'none'),
        'gpt-5.5-2026-04-23' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh'), 'default' => 'none'),
        'gpt-5.6-sol' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-5.6-terra' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium'),
        'gpt-5.6-luna' => array('allowed' => array('none', 'low', 'medium', 'high', 'xhigh', 'max'), 'default' => 'medium')
    );
}

function wpiko_chatbot_normalize_responses_reasoning_effort($model, $reasoning_effort)
{
    $effort_config = wpiko_chatbot_get_responses_reasoning_effort_config();
    if (!isset($effort_config[$model])) {
        return $reasoning_effort;
    }

    if ($reasoning_effort === 'minimal') {
        wpiko_chatbot_log("Reasoning effort 'minimal' is not compatible with Responses API file_search. Falling back to 'low' for model '$model'.", 'warning');
        return 'low';
    }

    $config = $effort_config[$model];
    if (!in_array($reasoning_effort, $config['allowed'], true)) {
        wpiko_chatbot_log("Invalid reasoning effort '$reasoning_effort' for model '$model'. Falling back to default '{$config['default']}'.", 'warning');
        return $config['default'];
    }

    return $reasoning_effort;
}

// Responses API Call
function wpiko_chatbot_responses_api_call($message, $conversation_id = null, $user_email = '', $frontend_previous_response_id = null)
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $user_id = get_current_user_id();

    // Use conversation_id or generate one
    $conversation_id = wpiko_chatbot_bind_chat_session($conversation_id ?: 'resp_' . wp_generate_password(12, false));

    if (empty($api_key)) {
        wpiko_chatbot_log('API key is missing', 'error');
        return array('error' => 'The chat is currently offline. Please contact the site administrator.');
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    // Get system instructions for Responses API
    $system_instructions = wpiko_chatbot_combine_responses_instructions();

    // Get the selected model for Responses API
    $model = get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');

    // Ensure model is one of the latest supported models; otherwise fall back
    $latest_models = wpiko_chatbot_get_responses_api_models();
    if (!isset($latest_models[$model])) {
        wpiko_chatbot_log('Selected Responses model is legacy or unsupported. Falling back to gpt-6-luna. Selected: ' . $model, 'warning');
        $model = 'gpt-6-luna';
    }

    $takeover_handoff_context = function_exists('wpiko_chatbot_build_takeover_handoff_context')
        ? wpiko_chatbot_build_takeover_handoff_context($conversation_id)
        : '';

    // Save user message to database
    wpiko_chatbot_save_message($user_id, $conversation_id, 'user', $message, $user_email);

    // Allow Pro plugin to skip AI response (e.g., during admin takeover)
    $should_skip_ai = apply_filters('wpiko_chatbot_skip_ai_response', false, $conversation_id);
    if ($should_skip_ai) {
        // Don't save or show a takeover message here — the notification is
        // already inserted once when the admin activates takeover via REST.
        // Just signal the frontend that takeover is active so polling continues.
        return array(
            'response' => '',
            'conversation_id' => $conversation_id,
            'response_id' => null,
            'human_takeover' => true,
        );
    }

    // Get the previous response ID for multi-turn conversation (stateful via OpenAI)
    // Priority: Use frontend-provided ID if available (prevents race conditions)
    // Fallback: Look up from database (for backward compatibility)
    $previous_response_id = null;
    if (!empty($frontend_previous_response_id)) {
        $previous_response_id = $frontend_previous_response_id;
        wpiko_chatbot_log('Using frontend-provided previous_response_id: ' . $previous_response_id, 'info');
    } elseif (function_exists('wpiko_chatbot_get_last_response_id')) {
        $previous_response_id = wpiko_chatbot_get_last_response_id($conversation_id);
        if (!empty($previous_response_id)) {
            wpiko_chatbot_log('Using database previous_response_id: ' . $previous_response_id, 'info');
        }
    }

    // Get Responses vector store id early (used in instructions and tools wiring)
    $responses_vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');

    $request_input = $message;
    if (!empty($takeover_handoff_context)) {
        $request_input = $takeover_handoff_context . "\n\nLatest user message:\n" . $message;
        wpiko_chatbot_log('Injecting live takeover handoff context before resuming AI for session ' . $conversation_id, 'info');
    }

    // Build request body - with previous_response_id, we only send the new message
    // OpenAI automatically includes the conversation history from the previous response chain
    $request_body = array(
        'model' => $model,
        'input' => $request_input
    );

    // For multi-turn conversations: use previous_response_id if available
    if (!empty($previous_response_id)) {
        $request_body['previous_response_id'] = $previous_response_id;
        wpiko_chatbot_log('Using previous_response_id for multi-turn: ' . $previous_response_id, 'info');
    } else {
        wpiko_chatbot_log('Starting new conversation (no previous_response_id)', 'info');
    }

    // Reasoning models use fixed sampling; omit temperature to avoid API errors.
    if (!wpiko_chatbot_responses_model_uses_fixed_sampling($model)) {
        $request_body['temperature'] = 0.7;
    } else {
        // Optional knob per latest guidance
        $reasoning_effort = get_option('wpiko_chatbot_responses_reasoning_effort', 'medium');

        $reasoning_effort = wpiko_chatbot_normalize_responses_reasoning_effort($model, $reasoning_effort);

        $verbosity = get_option('wpiko_chatbot_responses_verbosity', 'medium');

        $request_body['reasoning'] = array('effort' => $reasoning_effort);
        // API requires verbosity to be nested under 'text'.
        $request_body['text'] = array('verbosity' => $verbosity);
    }
    if (!empty($system_instructions)) {
        $request_body['instructions'] = $system_instructions;
    }

    // If a Responses vector store exists and the model supports tools, enable file search
    $using_file_search = false;
    if (!empty($responses_vector_store_id) && wpiko_chatbot_responses_model_supports_file_search($model)) {
        // Check if vector store has files before enabling file search
        if (wpiko_chatbot_vector_store_has_files($responses_vector_store_id)) {
            wpiko_chatbot_log('Vector store has files available for search', 'info');

            // Declare the tool and provide vector store configuration per latest Responses API shape
            $request_body['tools'] = array(
                array(
                    'type' => 'file_search',
                    'vector_store_ids' => array($responses_vector_store_id),
                    'max_num_results' => 20
                )
            );
            $using_file_search = true;
        } else {
            wpiko_chatbot_log('Vector store exists but has no files, file search disabled', 'warning');
        }
    }

    // Knowledge-base rules ("answer only from the uploaded files") only make
    // sense when there are files to search. Without them they make the bot
    // refuse almost every question, so leave them out.
    if (!$using_file_search) {
        $system_instructions = wpiko_chatbot_combine_responses_instructions(false);
        if (!empty($system_instructions)) {
            $request_body['instructions'] = $system_instructions;
        } else {
            unset($request_body['instructions']);
        }
    }

    $request_body = wpiko_chatbot_prepare_response_tools($request_body, $conversation_id);

    // If using tools/file_search, we don't need the assistants beta header for Responses API
    // The Responses API handles tools natively without beta headers

    // Log the API request for debugging
    wpiko_chatbot_log('Sending request to OpenAI Responses API with file search enabled: ' . ($using_file_search ? 'YES' : 'NO'), 'info');
    wpiko_chatbot_log('Vector Store ID: ' . $responses_vector_store_id, 'info');
    wpiko_chatbot_log('Model: ' . $model . ' (supports file search: ' . (wpiko_chatbot_responses_model_supports_file_search($model) ? 'YES' : 'NO') . ')', 'info');
    if ($using_file_search) {
        wpiko_chatbot_log('File search tool configuration: ' . json_encode($request_body['tools']), 'info');
    }
    if (!wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_log('Request body: ' . json_encode($request_body), 'info');
    }

    // Increase execution time to prevent timeouts - High reasoning effort can take a long time
    if (function_exists('set_time_limit')) {
        set_time_limit(200);
    }

    $response = wpiko_chatbot_api_call_with_retry('https://api.openai.com/v1/responses', array(
        'headers' => $headers,
        'body' => json_encode($request_body),
        'timeout' => 120 // Increased to 120s to accommodate High Reasoning Effort models
    ));

    $response = wpiko_chatbot_run_response_tools($response, $request_body, $headers);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wpiko_chatbot_log('Failed to call Responses API: ' . $error_message, 'error');
        $classification = wpiko_chatbot_classify_openai_error(0, null, $error_message, $model);
        $error_response = wpiko_chatbot_build_api_error_response($classification, array('wp_error' => $error_message));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        return array('success' => false, 'data' => $error_response['data']);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $response_body = json_decode($raw_body, true);

    wpiko_chatbot_log('Responses API HTTP status: ' . $status_code, 'info');
    if (!wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_log('Responses API payload: ' . $raw_body, 'info');
    }

    if ($status_code !== 200) {
        $api_error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : (is_string($raw_body) ? $raw_body : 'Unknown error');
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Responses API HTTP error (' . $status_code . '): ' . $api_error_message, 'error');
        }

        // Work out what went wrong so admins get an actionable explanation.
        $classification = wpiko_chatbot_classify_openai_error($status_code, $response_body, '', $model);

        // Fallback: if we were using file_search, retry without tools to keep chat working.
        // Skip it for account and rate-limit problems, where a second request cannot succeed.
        $has_function_tools = !empty(array_filter($request_body['tools'] ?? array(), function ($tool) { return ($tool['type'] ?? '') === 'function'; }));
        $can_retry_without_tools = !$has_function_tools && !$classification['account_problem'] && $classification['code'] !== 'rate_limited';
        if ($using_file_search && $can_retry_without_tools) {
            wpiko_chatbot_log('Retrying Responses API without tools/file_search as a fallback', 'warning');
            $fallback_headers = $headers;
            // No need to remove beta header since we're not using it for Responses API
            $fallback_body = $request_body;
            unset($fallback_body['tools']);
            unset($fallback_body['tool_config']);
            // Use shorter timeout for fallback - don't use retry mechanism to avoid further delays
            $fallback_response = wp_remote_post('https://api.openai.com/v1/responses', array(
                'headers' => $fallback_headers,
                'body' => json_encode($fallback_body),
                'timeout' => 30 // Shorter timeout for fallback attempt
            ));
            if (!is_wp_error($fallback_response)) {
                $fb_status = wp_remote_retrieve_response_code($fallback_response);
                $fb_raw = wp_remote_retrieve_body($fallback_response);
                $fb_json = json_decode($fb_raw, true);
                if ($fb_status === 200 && !isset($fb_json['error'])) {
                    $assistant_message = wpiko_chatbot_extract_responses_output_text($fb_json);
                    if ($assistant_message && is_string($assistant_message)) {
                        $assistant_message = wpiko_chatbot_format_responses_reply($assistant_message);
                        wpiko_chatbot_save_message($user_id, $conversation_id, 'assistant', $assistant_message, $user_email);
                        return array(
                            'response' => $assistant_message,
                            'conversation_id' => $conversation_id
                        );
                    }
                }
            }
        }

        $error_response = wpiko_chatbot_build_api_error_response($classification, array(
            'status' => $status_code,
            'openai_error' => $api_error_message,
            'request' => $request_body,
            'fallback_attempted' => $using_file_search && $can_retry_without_tools
        ));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        return array('success' => false, 'data' => $error_response['data']);
    }

    if (isset($response_body['error'])) {
        $api_error_message = is_array($response_body['error']) && isset($response_body['error']['message']) ? $response_body['error']['message'] : json_encode($response_body['error']);
        $status_code = wp_remote_retrieve_response_code($response);
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Responses API error (' . $status_code . '): ' . $api_error_message, 'error');
        }

        // Map OpenAI error types onto the HTTP status they normally come with.
        $error_type = isset($response_body['error']['type']) ? $response_body['error']['type'] : '';
        $type_status_map = array(
            'authentication_error' => 401,
            'permission_error' => 403,
            'insufficient_quota' => 429,
            'rate_limit_error' => 429,
            'server_error' => 500,
        );
        $effective_status = isset($type_status_map[$error_type]) ? $type_status_map[$error_type] : $status_code;
        if ($effective_status === 200) {
            $effective_status = strpos(strtolower($api_error_message), 'timeout') !== false ? 504 : 400;
        }

        $classification = wpiko_chatbot_classify_openai_error($effective_status, $response_body, '', $model);
        $error_response = wpiko_chatbot_build_api_error_response($classification, array(
            'status' => $status_code,
            'openai_error' => $api_error_message,
            'request' => $request_body
        ));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        return array('success' => false, 'data' => $error_response['data']);
    }

    // Extract assistant message from Responses API
    $assistant_message = wpiko_chatbot_extract_responses_output_text($response_body);
    if ($assistant_message === null || $assistant_message === '') {
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Unable to extract text from Responses API payload: ' . json_encode($response_body), 'error');
        }
        $user_friendly_error = wpiko_chatbot_get_user_friendly_error_message('Invalid response format', 'responses');
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $user_friendly_error, $user_email);
        return array('success' => false, 'data' => array('message' => $user_friendly_error, 'type' => 'responses_api_error'));
    }

    // Ensure we have a string, not an array
    if (is_array($assistant_message)) {
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Assistant message is an array, attempting to extract text: ' . json_encode($assistant_message), 'warning');
        }

        // Try to extract text from common array structures
        if (isset($assistant_message['text'])) {
            $assistant_message = $assistant_message['text'];
        } else if (isset($assistant_message['content'])) {
            $assistant_message = $assistant_message['content'];
        } else if (isset($assistant_message[0])) {
            // If it's a simple array, take the first element
            $first_element = $assistant_message[0];
            if (is_string($first_element)) {
                $assistant_message = $first_element;
            } else if (is_array($first_element) && isset($first_element['text'])) {
                $assistant_message = $first_element['text'];
            } else if (is_array($first_element) && isset($first_element['content'])) {
                $assistant_message = $first_element['content'];
            } else {
                // Convert first element to string
                $assistant_message = is_array($first_element) ? json_encode($first_element) : (string) $first_element;
            }
        } else {
            // Last resort: convert array to JSON string
            $assistant_message = json_encode($assistant_message);
        }
    }

    // Final check: ensure it's a string
    if (!is_string($assistant_message)) {
        $message_type = gettype($assistant_message);
        $message_value = is_array($assistant_message) ? wp_json_encode($assistant_message) : (string) $assistant_message;
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Converting assistant message to string from type: ' . $message_type . ' - Value: ' . $message_value, 'warning');
        }
        $assistant_message = is_array($assistant_message) ? json_encode($assistant_message) : (string) $assistant_message;
    }

    // Process the message similar to Assistant API
    if (!wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_log('Before processing message, type: ' . gettype($assistant_message), 'info');
    }
    $assistant_message = wpiko_chatbot_format_responses_reply($assistant_message);
    if (!wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_log('After processing message, type: ' . gettype($assistant_message), 'info');
    }

    // Ensure the message is a string
    if (!is_string($assistant_message)) {
        $message_type = gettype($assistant_message);
        $message_value = is_array($assistant_message) ? wp_json_encode($assistant_message) : (string) $assistant_message;
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Assistant message is not a string after processing: ' . $message_type . ' - Value: ' . $message_value, 'warning');
        }
        $assistant_message = is_array($assistant_message) ? json_encode($assistant_message) : (string) $assistant_message;
    }

    // Save assistant message to database
    wpiko_chatbot_save_message($user_id, $conversation_id, 'assistant', $assistant_message, $user_email);

    // A successful answer means any stored OpenAI account problem is resolved.
    wpiko_chatbot_clear_openai_health();

    // Store OpenAI response ID for multi-turn conversations
    // The response 'id' will be used as 'previous_response_id' in the next API call
    $new_response_id = null;
    if (isset($response_body['id']) && !empty($response_body['id'])) {
        $new_response_id = $response_body['id'];
        if (function_exists('wpiko_chatbot_save_response_id')) {
            wpiko_chatbot_save_response_id($conversation_id, $new_response_id);
            wpiko_chatbot_log('Stored response ID for next turn: ' . $new_response_id, 'info');
        }
    }

    wpiko_chatbot_record_private_response($conversation_id, $new_response_id);

    return array(
        'response' => $assistant_message,
        'conversation_id' => $conversation_id,
        'response_id' => $new_response_id
    );
}

/**
 * Extract plain text from OpenAI Responses API payload
 *
 * Expected shapes (non-streaming):
 * - {
 *     output_text: "..."
 *   }
 * - {
 *     output: [ { content: [ { type: 'output_text', text: '...' }, ... ] }, ... ]
 *   }
 * - Fallbacks: choices[0].message.content (chat completions), response (custom)
 *
 * @param array $body
 * @return string|null
 */
function wpiko_chatbot_extract_responses_output_text($body)
{
    if (!is_array($body)) {
        return null;
    }

    // 1) Simple and official field
    if (isset($body['output_text']) && is_string($body['output_text'])) {
        return trim($body['output_text']);
    }

    // 2) Newer object array with nested content parts
    if (isset($body['output']) && is_array($body['output'])) {
        $parts = array();
        foreach ($body['output'] as $item) {
            if (isset($item['content']) && is_array($item['content'])) {
                foreach ($item['content'] as $contentPart) {
                    if (isset($contentPart['text']) && is_string($contentPart['text'])) {
                        $parts[] = $contentPart['text'];
                    }
                }
            } elseif (isset($item['text']) && is_string($item['text'])) {
                // Very defensive fallback if text is directly on the item
                $parts[] = $item['text'];
            }
        }
        $joined = trim(implode("\n\n", array_filter($parts)));
        if ($joined !== '') {
            return $joined;
        }
    }

    // 3) Some experimental formats placed content at top-level
    if (isset($body['content']) && is_array($body['content'])) {
        $parts = array();
        foreach ($body['content'] as $contentPart) {
            if (isset($contentPart['text']) && is_string($contentPart['text'])) {
                $parts[] = $contentPart['text'];
            }
        }
        $joined = trim(implode("\n\n", array_filter($parts)));
        if ($joined !== '') {
            return $joined;
        }
    }

    // 4) Chat Completions compatibility
    if (isset($body['choices'][0]['message']['content']) && is_string($body['choices'][0]['message']['content'])) {
        return trim($body['choices'][0]['message']['content']);
    }

    // 5) Custom field fallback
    if (isset($body['response']) && is_string($body['response'])) {
        return trim($body['response']);
    }

    return null;
}

/**
 * Remove OpenAI's internal file citation tokens from text shown to visitors.
 */
function wpiko_chatbot_strip_file_citation_markers($text)
{
    $text = preg_replace('/\x{E200}filecite\x{E202}.*?\x{E201}/us', '', $text);
    return trim(preg_replace('/\x{E200}filecite\x{E202}.*$/us', '', $text));
}

/**
 * Format one completed reply for both the browser and conversation storage.
 */
function wpiko_chatbot_format_responses_reply($text)
{
    return wpiko_chatbot_process_message(wpiko_chatbot_strip_file_citation_markers($text));
}

/**
 * Hold incomplete citation tokens across SSE deltas so they never flash in chat.
 */
function wpiko_chatbot_filter_file_citation_stream_delta($delta, &$pending)
{
    $opener = "\xEE\x88\x80filecite\xEE\x88\x82";
    $closer = "\xEE\x88\x81";
    $pending .= $delta;
    $visible = '';

    while ($pending !== '') {
        $start = strpos($pending, $opener);
        if ($start !== false) {
            $visible .= substr($pending, 0, $start);
            $pending = substr($pending, $start);
            $end = strpos($pending, $closer, strlen($opener));
            if ($end === false) {
                break;
            }
            $pending = substr($pending, $end + strlen($closer));
            continue;
        }

        // Keep a possible opener prefix until the next delta arrives.
        $keep = 0;
        $max_prefix = min(strlen($pending), strlen($opener) - 1);
        for ($length = $max_prefix; $length > 0; $length--) {
            if (substr($pending, -$length) === substr($opener, 0, $length)) {
                $keep = $length;
                break;
            }
        }
        $visible .= $keep ? substr($pending, 0, -$keep) : $pending;
        $pending = $keep ? substr($pending, -$keep) : '';
        break;
    }

    return $visible;
}

/**
 * Build the common Responses API request context used by streaming requests.
 *
 * @param string $message
 * @param string|null $conversation_id
 * @param string|null $frontend_previous_response_id
 * @return array
 */
function wpiko_chatbot_build_responses_request_context($message, $conversation_id = null, $frontend_previous_response_id = null)
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $conversation_id = wpiko_chatbot_bind_chat_session($conversation_id ?: 'resp_' . wp_generate_password(12, false));

    if (empty($api_key)) {
        wpiko_chatbot_log('API key is missing', 'error');
        return array(
            'success' => false,
            'data' => array(
                'message' => 'The chat is currently offline. Please contact the site administrator.',
                'type' => 'config_error'
            )
        );
    }

    $headers = array(
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: text/event-stream'
    );

    $system_instructions = wpiko_chatbot_combine_responses_instructions();
    $model = get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');
    $latest_models = wpiko_chatbot_get_responses_api_models();

    if (!isset($latest_models[$model])) {
        wpiko_chatbot_log('Selected Responses model is legacy or unsupported. Falling back to gpt-6-luna. Selected: ' . $model, 'warning');
        $model = 'gpt-6-luna';
    }

    $takeover_handoff_context = function_exists('wpiko_chatbot_build_takeover_handoff_context')
        ? wpiko_chatbot_build_takeover_handoff_context($conversation_id)
        : '';

    $previous_response_id = null;
    if (!empty($frontend_previous_response_id)) {
        $previous_response_id = $frontend_previous_response_id;
        wpiko_chatbot_log('Using frontend-provided previous_response_id: ' . $previous_response_id, 'info');
    } elseif (function_exists('wpiko_chatbot_get_last_response_id')) {
        $previous_response_id = wpiko_chatbot_get_last_response_id($conversation_id);
        if (!empty($previous_response_id)) {
            wpiko_chatbot_log('Using database previous_response_id: ' . $previous_response_id, 'info');
        }
    }

    $responses_vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    $request_input = $message;
    if (!empty($takeover_handoff_context)) {
        $request_input = $takeover_handoff_context . "\n\nLatest user message:\n" . $message;
        wpiko_chatbot_log('Injecting live takeover handoff context before resuming AI for session ' . $conversation_id, 'info');
    }

    $request_body = array(
        'model' => $model,
        'input' => $request_input
    );

    if (!empty($previous_response_id)) {
        $request_body['previous_response_id'] = $previous_response_id;
        wpiko_chatbot_log('Using previous_response_id for multi-turn: ' . $previous_response_id, 'info');
    } else {
        wpiko_chatbot_log('Starting new conversation (no previous_response_id)', 'info');
    }

    if (!wpiko_chatbot_responses_model_uses_fixed_sampling($model)) {
        $request_body['temperature'] = 0.7;
    } else {
        $reasoning_effort = get_option('wpiko_chatbot_responses_reasoning_effort', 'medium');
        $reasoning_effort = wpiko_chatbot_normalize_responses_reasoning_effort($model, $reasoning_effort);

        $verbosity = get_option('wpiko_chatbot_responses_verbosity', 'medium');
        $request_body['reasoning'] = array('effort' => $reasoning_effort);
        $request_body['text'] = array('verbosity' => $verbosity);
    }

    if (!empty($system_instructions)) {
        $request_body['instructions'] = $system_instructions;
    }

    $using_file_search = false;
    if (!empty($responses_vector_store_id) && wpiko_chatbot_responses_model_supports_file_search($model)) {
        if (wpiko_chatbot_vector_store_has_files($responses_vector_store_id)) {
            wpiko_chatbot_log('Vector store has files available for search', 'info');
            $request_body['tools'] = array(
                array(
                    'type' => 'file_search',
                    'vector_store_ids' => array($responses_vector_store_id),
                    'max_num_results' => 20
                )
            );
            $using_file_search = true;
        } else {
            wpiko_chatbot_log('Vector store exists but has no files, file search disabled', 'warning');
        }
    }

    // Knowledge-base rules only make sense when there are files to search.
    if (!$using_file_search) {
        $system_instructions = wpiko_chatbot_combine_responses_instructions(false);
        if (!empty($system_instructions)) {
            $request_body['instructions'] = $system_instructions;
        } else {
            unset($request_body['instructions']);
        }
    }

    $request_body = wpiko_chatbot_prepare_response_tools($request_body, $conversation_id);

    return array(
        'success' => true,
        'conversation_id' => $conversation_id,
        'headers' => $headers,
        'request_body' => $request_body,
        'responses_vector_store_id' => $responses_vector_store_id,
        'model' => $model,
        'using_file_search' => $using_file_search
    );
}

/**
 * Stream a Responses API request and relay output deltas through callbacks.
 *
 * @param string $message
 * @param string|null $conversation_id
 * @param string $user_email
 * @param string|null $frontend_previous_response_id
 * @param array $callbacks start|delta|done|error callbacks
 * @return array
 */
function wpiko_chatbot_responses_api_stream($message, $conversation_id = null, $user_email = '', $frontend_previous_response_id = null, $callbacks = array())
{
    if (!function_exists('curl_init')) {
        wpiko_chatbot_log('cURL is unavailable; falling back to non-streaming Responses API call.', 'warning');
        $fallback_result = wpiko_chatbot_responses_api_call($message, $conversation_id, $user_email, $frontend_previous_response_id);
        if (isset($fallback_result['success']) && $fallback_result['success'] === false) {
            if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
                call_user_func($callbacks['error'], $fallback_result['data']);
            }
        } elseif (isset($callbacks['done']) && is_callable($callbacks['done'])) {
            call_user_func($callbacks['done'], $fallback_result);
        }
        return $fallback_result;
    }

    $context = wpiko_chatbot_build_responses_request_context($message, $conversation_id, $frontend_previous_response_id);
    if (isset($context['success']) && $context['success'] === false) {
        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            call_user_func($callbacks['error'], $context['data']);
        }
        return $context;
    }

    $user_id = get_current_user_id();
    $conversation_id = $context['conversation_id'];

    wpiko_chatbot_save_message($user_id, $conversation_id, 'user', $message, $user_email);

    if (isset($callbacks['start']) && is_callable($callbacks['start'])) {
        call_user_func($callbacks['start'], $conversation_id);
    }

    $should_skip_ai = apply_filters('wpiko_chatbot_skip_ai_response', false, $conversation_id);
    if ($should_skip_ai) {
        $result = array(
            'response' => '',
            'conversation_id' => $conversation_id,
            'response_id' => null,
            'human_takeover' => true,
        );
        if (isset($callbacks['done']) && is_callable($callbacks['done'])) {
            call_user_func($callbacks['done'], $result);
        }
        return $result;
    }

    $request_body = $context['request_body'];
    $request_body['stream'] = true;

    wpiko_chatbot_log('Streaming request to OpenAI Responses API with file search enabled: ' . ($context['using_file_search'] ? 'YES' : 'NO'), 'info');
    wpiko_chatbot_log('Vector Store ID: ' . $context['responses_vector_store_id'], 'info');
    wpiko_chatbot_log('Model: ' . $context['model'] . ' (supports file search: ' . (wpiko_chatbot_responses_model_supports_file_search($context['model']) ? 'YES' : 'NO') . ')', 'info');
    if (!wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_log('Streaming request body: ' . json_encode($request_body), 'info');
    }

    if (function_exists('set_time_limit')) {
        set_time_limit(200);
    }

    $stream_state = array(
        'assistant_message' => '',
        'citation_pending' => '',
        'response_id' => null,
        'completed_response' => null,
        'error' => null,
        'error_code' => '',
        'raw_body' => '',
        'event_name' => 'message',
        'data_lines' => array(),
        'buffer' => ''
    );

    // The WordPress HTTP API (wp_remote_post) does not support real-time, chunk-by-chunk
    // response streaming via a write callback, which is required to relay Server-Sent Events
    // from the OpenAI Responses API to the browser as they arrive. cURL with CURLOPT_WRITEFUNCTION
    // is the only viable option here; a wp_remote_post() fallback is used when cURL is unavailable.
    // phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_close
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $context['headers']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($request_body));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$stream_state, $callbacks) {
        if (connection_aborted()) {
            return 0;
        }

        if (strlen($stream_state['raw_body']) < 30000) {
            $stream_state['raw_body'] .= $chunk;
        }

        wpiko_chatbot_parse_openai_stream_chunk($chunk, $stream_state, $callbacks);
        return strlen($chunk);
    });

    $curl_result = curl_exec($ch);
    $curl_error = curl_error($ch);
    $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_close

    wpiko_chatbot_flush_openai_stream_parser($stream_state, $callbacks);

    if ($curl_result === false || !empty($curl_error)) {
        wpiko_chatbot_log('Streaming Responses API cURL error: ' . $curl_error, 'error');
        $classification = wpiko_chatbot_classify_openai_error(0, null, $curl_error !== '' ? $curl_error : 'connection failed', $context['model']);
        $error_response = wpiko_chatbot_build_api_error_response($classification, array('wp_error' => $curl_error, 'status' => $status_code));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        $data = $error_response['data'];
        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            call_user_func($callbacks['error'], $data);
        }
        return array('success' => false, 'data' => $data);
    }

    if (!empty($stream_state['error'])) {
        $api_error_message = $stream_state['error'];
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Streaming Responses API error: ' . $api_error_message, 'error');
        }
        $stream_error_body = array('error' => array('message' => $api_error_message));
        if (!empty($stream_state['error_code'])) {
            $stream_error_body['error']['code'] = $stream_state['error_code'];
        }
        $stream_error_status = ($status_code === 200 || $status_code === 0) ? 400 : $status_code;
        if (!empty($stream_state['error_code']) && $stream_state['error_code'] === 'insufficient_quota') {
            $stream_error_status = 429;
        } elseif (!empty($stream_state['error_code']) && $stream_state['error_code'] === 'rate_limit_exceeded') {
            $stream_error_status = 429;
        } elseif (!empty($stream_state['error_code']) && $stream_state['error_code'] === 'server_error') {
            $stream_error_status = 500;
        }
        $classification = wpiko_chatbot_classify_openai_error($stream_error_status, $stream_error_body, '', $context['model']);
        $error_response = wpiko_chatbot_build_api_error_response($classification, array('status' => $status_code, 'openai_error' => $api_error_message));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        $data = $error_response['data'];
        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            call_user_func($callbacks['error'], $data);
        }
        return array('success' => false, 'data' => $data);
    }

    if ($status_code !== 200) {
        $decoded_error = json_decode($stream_state['raw_body'], true);
        $api_error_message = isset($decoded_error['error']['message']) ? $decoded_error['error']['message'] : $stream_state['raw_body'];
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Streaming Responses API HTTP error (' . $status_code . '): ' . $api_error_message, 'error');
        }

        $classification = wpiko_chatbot_classify_openai_error($status_code, is_array($decoded_error) ? $decoded_error : $stream_state['raw_body'], '', $context['model']);
        $error_response = wpiko_chatbot_build_api_error_response($classification, array('status' => $status_code, 'openai_error' => $api_error_message, 'request' => $request_body));
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $error_response['visitor_message'], $user_email);
        $data = $error_response['data'];
        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            call_user_func($callbacks['error'], $data);
        }
        return array('success' => false, 'data' => $data);
    }

    // Function arguments are never relayed as text. Complete one bounded lookup round.
    if (is_array($stream_state['completed_response']) && wpiko_chatbot_response_has_function_calls($stream_state['completed_response'])) {
        $tool_headers = array('Content-Type' => 'application/json');
        foreach ($context['headers'] as $header) {
            if (strpos($header, 'Authorization: ') === 0) {
                $tool_headers['Authorization'] = substr($header, 15);
            }
        }
        $tool_response = wpiko_chatbot_run_response_tools(array(
            'response' => array('code' => 200),
            'body' => wp_json_encode($stream_state['completed_response']),
        ), $request_body, $tool_headers);
        if (is_wp_error($tool_response) || wp_remote_retrieve_response_code($tool_response) !== 200) {
            $data = array('message' => 'The order lookup could not be completed. Please try again later.', 'type' => 'responses_api_error');
            wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $data['message'], $user_email);
            if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
                call_user_func($callbacks['error'], $data);
            }
            return array('success' => false, 'data' => $data);
        }
        $stream_state['completed_response'] = json_decode(wp_remote_retrieve_body($tool_response), true);
        $stream_state['assistant_message'] = wpiko_chatbot_extract_responses_output_text($stream_state['completed_response']);
        $stream_state['response_id'] = $stream_state['completed_response']['id'] ?? null;
    }

    $assistant_message = $stream_state['assistant_message'];
    if (($assistant_message === null || $assistant_message === '') && is_array($stream_state['completed_response'])) {
        $assistant_message = wpiko_chatbot_extract_responses_output_text($stream_state['completed_response']);
    }

    if ($assistant_message === null || $assistant_message === '') {
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Unable to extract text from streaming Responses API payload: ' . $stream_state['raw_body'], 'error');
        }
        $user_friendly_error = wpiko_chatbot_get_user_friendly_error_message('Invalid response format', 'responses');
        wpiko_chatbot_save_message($user_id, $conversation_id, 'error', $user_friendly_error, $user_email);
        $data = array('message' => $user_friendly_error, 'type' => 'responses_api_error');
        if (isset($callbacks['error']) && is_callable($callbacks['error'])) {
            call_user_func($callbacks['error'], $data);
        }
        return array('success' => false, 'data' => $data);
    }

    if (!is_string($assistant_message)) {
        $assistant_message = is_array($assistant_message) ? wp_json_encode($assistant_message) : (string) $assistant_message;
    }

    $assistant_message = wpiko_chatbot_format_responses_reply($assistant_message);
    if (!is_string($assistant_message)) {
        $assistant_message = is_array($assistant_message) ? wp_json_encode($assistant_message) : (string) $assistant_message;
    }

    wpiko_chatbot_save_message($user_id, $conversation_id, 'assistant', $assistant_message, $user_email);

    // A successful answer means any stored OpenAI account problem is resolved.
    wpiko_chatbot_clear_openai_health();

    $new_response_id = $stream_state['response_id'];
    if (empty($new_response_id) && is_array($stream_state['completed_response']) && !empty($stream_state['completed_response']['id'])) {
        $new_response_id = $stream_state['completed_response']['id'];
    }

    if (!empty($new_response_id) && function_exists('wpiko_chatbot_save_response_id')) {
        wpiko_chatbot_save_response_id($conversation_id, $new_response_id);
        wpiko_chatbot_log('Stored streaming response ID for next turn: ' . $new_response_id, 'info');
    }

    wpiko_chatbot_record_private_response($conversation_id, $new_response_id);

    $result = array(
        'response' => $assistant_message,
        'conversation_id' => $conversation_id,
        'response_id' => $new_response_id
    );

    if (isset($callbacks['done']) && is_callable($callbacks['done'])) {
        call_user_func($callbacks['done'], $result);
    }

    return $result;
}

function wpiko_chatbot_parse_openai_stream_chunk($chunk, &$stream_state, $callbacks)
{
    $stream_state['buffer'] .= $chunk;
    $lines = preg_split("/\r\n|\n|\r/", $stream_state['buffer']);
    $stream_state['buffer'] = array_pop($lines);

    foreach ($lines as $line) {
        wpiko_chatbot_parse_openai_stream_line($line, $stream_state, $callbacks);
    }
}

function wpiko_chatbot_flush_openai_stream_parser(&$stream_state, $callbacks)
{
    if ($stream_state['buffer'] !== '') {
        wpiko_chatbot_parse_openai_stream_line($stream_state['buffer'], $stream_state, $callbacks);
        $stream_state['buffer'] = '';
    }

    if (!empty($stream_state['data_lines'])) {
        wpiko_chatbot_dispatch_openai_stream_event($stream_state, $callbacks);
    }
}

function wpiko_chatbot_parse_openai_stream_line($line, &$stream_state, $callbacks)
{
    $line = rtrim($line, "\r");

    if ($line === '') {
        wpiko_chatbot_dispatch_openai_stream_event($stream_state, $callbacks);
        return;
    }

    if (strpos($line, 'event:') === 0) {
        $stream_state['event_name'] = trim(substr($line, 6));
        return;
    }

    if (strpos($line, 'data:') === 0) {
        $stream_state['data_lines'][] = ltrim(substr($line, 5));
    }
}

function wpiko_chatbot_dispatch_openai_stream_event(&$stream_state, $callbacks)
{
    if (empty($stream_state['data_lines'])) {
        $stream_state['event_name'] = 'message';
        return;
    }

    $event_name = $stream_state['event_name'];
    $data = implode("\n", $stream_state['data_lines']);
    $stream_state['event_name'] = 'message';
    $stream_state['data_lines'] = array();

    if ($data === '[DONE]') {
        return;
    }

    $payload = json_decode($data, true);
    if (!is_array($payload)) {
        return;
    }

    if (!empty($payload['type']) && $event_name === 'message') {
        $event_name = $payload['type'];
    }

    $delta = null;
    if (isset($payload['delta']) && is_string($payload['delta'])) {
        $delta = $payload['delta'];
    } elseif (isset($payload['text']) && is_string($payload['text']) && strpos($event_name, '.delta') !== false) {
        $delta = $payload['text'];
    }

    if ($delta !== null && $event_name === 'response.output_text.delta') {
        $stream_state['assistant_message'] .= $delta;
        $visible_delta = wpiko_chatbot_filter_file_citation_stream_delta($delta, $stream_state['citation_pending']);
        if ($visible_delta !== '' && isset($callbacks['delta']) && is_callable($callbacks['delta'])) {
            call_user_func($callbacks['delta'], $visible_delta);
        }
        return;
    }

    if ($event_name === 'response.completed') {
        $completed_response = isset($payload['response']) && is_array($payload['response']) ? $payload['response'] : $payload;
        $stream_state['completed_response'] = $completed_response;
        if (!empty($completed_response['id'])) {
            $stream_state['response_id'] = $completed_response['id'];
        }
        return;
    }

    if (!empty($payload['response']['id']) && empty($stream_state['response_id'])) {
        $stream_state['response_id'] = $payload['response']['id'];
    } elseif (!empty($payload['id']) && empty($stream_state['response_id']) && strpos($payload['id'], 'resp_') === 0) {
        $stream_state['response_id'] = $payload['id'];
    }

    if (strpos($event_name, 'error') !== false || $event_name === 'response.failed' || $event_name === 'response.incomplete') {
        if (isset($payload['error']['code']) && is_string($payload['error']['code'])) {
            $stream_state['error_code'] = $payload['error']['code'];
        } elseif (isset($payload['response']['error']['code']) && is_string($payload['response']['error']['code'])) {
            $stream_state['error_code'] = $payload['response']['error']['code'];
        } elseif (isset($payload['code']) && is_string($payload['code'])) {
            $stream_state['error_code'] = $payload['code'];
        }

        if (isset($payload['error']['message'])) {
            $stream_state['error'] = $payload['error']['message'];
        } elseif (isset($payload['response']['error']['message'])) {
            $stream_state['error'] = $payload['response']['error']['message'];
        } elseif (isset($payload['message'])) {
            $stream_state['error'] = $payload['message'];
        } else {
            $stream_state['error'] = wp_json_encode($payload);
        }
    }
}

/**
 * Whether the vector store contains at least one file.
 *
 * The answer is cached for 10 minutes so each chat message does not need an
 * extra OpenAI request. Uploads and deletions refresh the cache.
 *
 * @param string $vector_store_id Vector store ID.
 * @return bool
 */
function wpiko_chatbot_vector_store_has_files($vector_store_id)
{
    if (empty($vector_store_id)) {
        return false;
    }

    $cache_key = 'wpiko_chatbot_vs_has_files_' . md5($vector_store_id);
    if (get_transient($cache_key) === '1') {
        return true;
    }

    $files_list = wpiko_chatbot_list_responses_files();
    $has_files = !empty($files_list['success']) && !empty($files_list['files']);

    // Only remember a positive answer: a store that just received its first
    // file must be used right away.
    if ($has_files) {
        set_transient($cache_key, '1', 10 * MINUTE_IN_SECONDS);
    }

    return $has_files;
}

/**
 * Forget the cached "vector store has files" answer.
 *
 * @return void
 */
function wpiko_chatbot_clear_vector_store_files_cache()
{
    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (!empty($vector_store_id)) {
        delete_transient('wpiko_chatbot_vs_has_files_' . md5($vector_store_id));
    }
}

// Function to get available models for Responses API
function wpiko_chatbot_get_responses_api_models()
{
    return array(
        'gpt-6-astra' => 'GPT-6 Astra',
        'gpt-6.1-sol' => 'GPT-6.1 Sol',
        'gpt-6-sol' => 'GPT-6 Sol',
        'gpt-6-luna' => 'GPT-6 Luna',
        'gpt-5.6-sol' => 'GPT-5.6 Sol',
        'gpt-5.6-terra' => 'GPT-5.6 Terra',
        'gpt-5.6-luna' => 'GPT-5.6 Luna',
        'gpt-5.5-2026-04-23' => 'GPT-5.5 (2026-04-23)',
        'gpt-5.4' => 'GPT-5.4',
        'gpt-5.4-mini' => 'GPT-5.4 Mini',
        'gpt-5.2' => 'GPT-5.2',
        'gpt-4.1' => 'GPT-4.1',
        'gpt-4.1-mini' => 'GPT-4.1 Mini'
    );
}

// Function to update Responses API configuration
function wpiko_chatbot_update_responses_config()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : 'gpt-6-luna';

    $supported_models = wpiko_chatbot_get_responses_api_models();
    if (!isset($supported_models[$model])) {
        wp_send_json_error(array('message' => 'Unsupported AI model.'));
        return;
    }

    // Update the model option
    update_option('wpiko_chatbot_responses_model', $model);

    // Handle structured instructions for Responses API
    if (isset($_POST['responses_assistant_type'])) {
        update_option('wpiko_chatbot_responses_assistant_type', sanitize_text_field(wp_unslash($_POST['responses_assistant_type'])));
    }

    if (isset($_POST['responses_website_specialization'])) {
        update_option('wpiko_chatbot_responses_website_specialization', sanitize_text_field(wp_unslash($_POST['responses_website_specialization'])));
    }

    if (isset($_POST['responses_assistant_tone'])) {
        update_option('wpiko_chatbot_responses_assistant_tone', sanitize_text_field(wp_unslash($_POST['responses_assistant_tone'])));
    }

    if (isset($_POST['responses_assistant_style'])) {
        update_option('wpiko_chatbot_responses_assistant_style', sanitize_text_field(wp_unslash($_POST['responses_assistant_style'])));
    }

    if (isset($_POST['responses_reasoning_effort'])) {
        update_option('wpiko_chatbot_responses_reasoning_effort', wpiko_chatbot_normalize_responses_reasoning_effort($model, sanitize_text_field(wp_unslash($_POST['responses_reasoning_effort']))));
    }

    if (isset($_POST['responses_verbosity'])) {
        update_option('wpiko_chatbot_responses_verbosity', sanitize_text_field(wp_unslash($_POST['responses_verbosity'])));
    }

    // Only main and specific instructions are editable. Ignore legacy managed fields.
    if (isset($_POST['main_system_instructions']) || isset($_POST['specific_system_instructions'])) {
        $instructions = wpiko_chatbot_get_system_instructions();
        $main_instructions = isset($_POST['main_system_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['main_system_instructions'])) : $instructions['main'];
        $specific_instructions = isset($_POST['specific_system_instructions']) ? sanitize_textarea_field(wp_unslash($_POST['specific_system_instructions'])) : $instructions['specific'];

        // Update system instructions in the database
        wpiko_chatbot_update_system_instructions(
            $main_instructions,
            $specific_instructions
        );
    }

    wp_send_json_success(array('message' => 'Responses API configuration updated successfully'));
}

add_action('wp_ajax_wpiko_chatbot_update_responses_config', 'wpiko_chatbot_update_responses_config');

/**
 * Helper: Determine whether a model requires fixed sampling parameters.
 */
function wpiko_chatbot_responses_model_uses_fixed_sampling($model)
{
    if (!$model || !is_string($model))
        return false;
    $fixed = array('gpt-6-astra', 'gpt-6.1-sol', 'gpt-6-sol', 'gpt-6-luna', 'gpt-5.2', 'gpt-5.4', 'gpt-5.4-mini', 'gpt-5.5-2026-04-23', 'gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna');
    if (in_array($model, $fixed, true))
        return true;
    return (bool) preg_match('/^gpt-(5|6)(\.\d+)?($|[-_])/', $model);
}

/**
 * Backward-compatible alias retained for integrations using the old helper name.
 */
function wpiko_chatbot_responses_model_is_gpt5($model)
{
    return wpiko_chatbot_responses_model_uses_fixed_sampling($model);
}

// (Removed) AJAX: wpiko_chatbot_check_gpt5_access endpoint

/**
 * Responses API - Vector store management and file operations
 */

/**
 * Get vector store details from OpenAI API
 */
function wpiko_chatbot_get_responses_vector_store_details()
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        return array('success' => false, 'message' => 'API key is not set');
    }

    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (empty($vector_store_id)) {
        return array('success' => false, 'message' => 'Vector store not created yet');
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    $resp = wp_remote_get("https://api.openai.com/v1/vector_stores/{$vector_store_id}", array(
        'headers' => $headers,
        'timeout' => 30
    ));

    if (is_wp_error($resp)) {
        return array('success' => false, 'message' => 'Failed to retrieve vector store: ' . $resp->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code !== 200) {
        $err = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';

        // Check if vector store was not found (deleted from OpenAI dashboard)
        if ($code === 404 || strpos($err, 'No vector store found') !== false) {
            // Clear the stored vector store ID since it no longer exists
            delete_option('wpiko_chatbot_responses_vector_store_id');
            wpiko_chatbot_log('Vector Store not found on OpenAI (deleted externally). Cleared local reference.', 'warning');
            return array(
                'success' => false,
                'message' => 'Vector Store not found',
                'not_found' => true,
                'details' => 'The Vector Store was deleted from OpenAI dashboard or no longer exists.'
            );
        }

        return array('success' => false, 'message' => 'Failed to retrieve vector store: ' . $err);
    }

    return array(
        'success' => true,
        'id' => $body['id'] ?? '',
        'name' => $body['name'] ?? '',
        'created_at' => $body['created_at'] ?? 0,
        'file_counts' => $body['file_counts'] ?? array(),
        'status' => $body['status'] ?? 'unknown'
    );
}

function wpiko_chatbot_get_responses_vector_store_id($create_if_missing = true)
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        return '';
    }

    $existing = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (!empty($existing)) {
        return $existing;
    }

    if (!$create_if_missing) {
        return '';
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    $site_name = get_bloginfo('name');
    $store_name = 'WPiko Responses KB - ' . $site_name;
    $resp = wp_remote_post('https://api.openai.com/v1/vector_stores', array(
        'headers' => $headers,
        'body' => json_encode(array('name' => $store_name)),
        'timeout' => 30
    ));

    if (is_wp_error($resp)) {
        wpiko_chatbot_log('Failed to create Responses vector store: ' . $resp->get_error_message(), 'error');
        return '';
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200 || empty($body['id'])) {
        $err = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        wpiko_chatbot_log('Failed to create Responses vector store: ' . $err, 'error');
        return '';
    }
    update_option('wpiko_chatbot_responses_vector_store_id', $body['id']);
    return $body['id'];
}

function wpiko_chatbot_upload_file_to_responses($file)
{
    if (wpiko_chatbot_private_orders_retired() && strtolower(basename($file['name'])) === 'woocommerce_orders.json') {
        return array('success' => false, 'message' => 'Order exports cannot be uploaded. Use controlled Order Assistance.');
    }

    // The file list is about to change.
    wpiko_chatbot_clear_vector_store_files_cache();

    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    if (empty($api_key)) {
        return array('success' => false, 'message' => 'API key is not set');
    }

    // Check if existing vector store is valid before attempting to use it
    $existing_vs_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (!empty($existing_vs_id)) {
        $vs_details = wpiko_chatbot_get_responses_vector_store_details();
        if (!$vs_details['success'] && isset($vs_details['not_found']) && $vs_details['not_found']) {
            // Vector store was deleted, clear it and create a new one
            wpiko_chatbot_log('Existing Vector Store ID is invalid, creating new one', 'info');
        }
    }

    $vector_store_id = wpiko_chatbot_get_responses_vector_store_id(true);
    if (empty($vector_store_id)) {
        return array('success' => false, 'message' => 'Failed to create or access Responses vector store');
    }

    // Prepare multipart upload to files endpoint
    $file_path = $file['tmp_name'];
    $file_name = basename($file['name']);
    $boundary = wp_generate_password(24, false);
    $body = '';
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="purpose"' . "\r\n\r\n";
    $body .= 'assistants' . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
    $body .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
    $body .= file_get_contents($file_path) . "\r\n";
    $body .= '--' . $boundary . '--';

    $upload_headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        'Content-Length' => strlen($body)
    );

    $upload_response = wp_remote_post('https://api.openai.com/v1/files', array(
        'headers' => $upload_headers,
        'body' => $body,
        'timeout' => 60
    ));

    if (is_wp_error($upload_response)) {
        return array('success' => false, 'message' => 'Failed to upload file: ' . $upload_response->get_error_message());
    }
    $http = wp_remote_retrieve_response_code($upload_response);
    $upload_data = json_decode(wp_remote_retrieve_body($upload_response), true);
    if ($http !== 200) {
        $err = isset($upload_data['error']['message']) ? $upload_data['error']['message'] : 'Unknown error occurred';
        return array('success' => false, 'message' => 'Failed to upload file: ' . $err);
    }
    $file_id = $upload_data['id'];

    // Add file to Responses vector store
    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );
    $add_resp = wp_remote_post("https://api.openai.com/v1/vector_stores/{$vector_store_id}/files", array(
        'headers' => $headers,
        'body' => wp_json_encode(wpiko_chatbot_vector_file_attachment($file_id, $file_name)),
        'timeout' => 30
    ));
    if (is_wp_error($add_resp)) {
        return array('success' => false, 'message' => 'Failed to add file to Vector Store: ' . $add_resp->get_error_message());
    }
    $add_code = wp_remote_retrieve_response_code($add_resp);
    $add_body = json_decode(wp_remote_retrieve_body($add_resp), true);
    if (isset($add_body['error'])) {
        $error_msg = $add_body['error']['message'];

        // Check if vector store was not found
        if ($add_code === 404 || strpos($error_msg, 'No vector store found') !== false) {
            // Clear the stored vector store ID since it no longer exists
            delete_option('wpiko_chatbot_responses_vector_store_id');
            wpiko_chatbot_log('Vector Store not found during file add (deleted externally). Cleared local reference.', 'warning');
            return array(
                'success' => false,
                'message' => 'Vector Store not found. It may have been deleted from OpenAI dashboard. Please refresh the page and try again.',
                'not_found' => true
            );
        }

        return array('success' => false, 'message' => 'Failed to add file to Vector Store: ' . $error_msg);
    }

    // Poll indexing status so knowledge is available immediately
    $status_headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );
    $index_status = 'unknown';
    $started = time();
    $timeout_sec = 60; // wait up to 60 seconds
    do {
        $status_resp = wp_remote_get("https://api.openai.com/v1/vector_stores/{$vector_store_id}/files/{$file_id}", array(
            'headers' => $status_headers,
            'timeout' => 15
        ));
        if (!is_wp_error($status_resp)) {
            $status_data = json_decode(wp_remote_retrieve_body($status_resp), true);
            if (isset($status_data['status'])) {
                $index_status = $status_data['status'];
                if ($index_status === 'completed') {
                    break;
                }
                if ($index_status === 'failed' || $index_status === 'cancelled') {
                    return array('success' => false, 'message' => 'File indexing failed in Vector Store.');
                }
            }
        }
        usleep(500000); // 0.5s backoff between polls
    } while ((time() - $started) < $timeout_sec);

    // Cache
    if (function_exists('wpiko_chatbot_cache_file_details')) {
        wpiko_chatbot_cache_file_details($file_id, array(
            'id' => $file_id,
            'filename' => $file_name,
            'bytes' => isset($file['size']) ? $file['size'] : (isset($upload_data['bytes']) ? $upload_data['bytes'] : 0),
            'created_at' => time(),
            'vector_store_id' => $vector_store_id
        ));
    }

    return array('success' => true, 'file_id' => $file_id, 'vector_store_id' => $vector_store_id, 'index_status' => $index_status);
}

function wpiko_chatbot_list_responses_files()
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (empty($api_key)) {
        return array('success' => false, 'message' => 'API key is missing');
    }
    if (empty($vector_store_id)) {
        return array('success' => true, 'files' => array(), 'performance' => array('total_files' => 0, 'cached_files' => 0, 'api_fetched' => 0));
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    $all_file_ids = array();
    $after = null;
    $has_more = true;
    $page_count = 0;
    $max_pages = 50;
    while ($has_more && $page_count < $max_pages) {
        $page_count++;
        $query_params = array('limit' => 100);
        if ($after) {
            $query_params['after'] = $after;
        }
        $url = "https://api.openai.com/v1/vector_stores/{$vector_store_id}/files?" . http_build_query($query_params);
        $resp = wp_remote_get($url, array('headers' => $headers, 'timeout' => 30));
        if (is_wp_error($resp)) {
            $cached = function_exists('wpiko_chatbot_get_cached_files') ? wpiko_chatbot_get_cached_files() : array();
            return array('success' => true, 'files' => array_values($cached), 'source' => 'cache_fallback');
        }
        $resp_code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($resp_code !== 200 || !isset($body['data']) || !is_array($body['data']) || isset($body['error'])) {
            // Check if vector store was not found
            if ($resp_code === 404 || strpos($body['error']['message'] ?? '', 'No vector store found') !== false) {
                delete_option('wpiko_chatbot_responses_vector_store_id');
                wpiko_chatbot_log('Vector Store not found during file list (deleted externally). Cleared local reference.', 'warning');
                return array(
                    'success' => false,
                    'message' => 'Vector Store not found',
                    'not_found' => true,
                    'files' => array()
                );
            }
            $cached = function_exists('wpiko_chatbot_get_cached_files') ? wpiko_chatbot_get_cached_files() : array();
            return array('success' => true, 'files' => array_values($cached), 'source' => 'cache_fallback');
        }
        if (!empty($body['data'])) {
            foreach ($body['data'] as $file) {
                $all_file_ids[] = $file['id'];
            }
        }
        $has_more = $body['has_more'] ?? false;
        if ($has_more && !empty($body['data'])) {
            $last = end($body['data']);
            $after = $last['id'];
        }
    }

    $files_with_details = array();
    $cached_files = function_exists('wpiko_chatbot_get_cached_files') ? wpiko_chatbot_get_cached_files() : array();
    $files_to_fetch = array();
    foreach ($all_file_ids as $fid) {
        if (isset($cached_files[$fid])) {
            $files_with_details[] = $cached_files[$fid];
        } else {
            $files_to_fetch[] = $fid;
        }
    }

    if (!empty($files_to_fetch)) {
        foreach ($files_to_fetch as $fid) {
            $file_resp = wp_remote_get("https://api.openai.com/v1/files/{$fid}", array('headers' => $headers, 'timeout' => 10));
            if (!is_wp_error($file_resp)) {
                $file_body = json_decode(wp_remote_retrieve_body($file_resp), true);
                if (!isset($file_body['error'])) {
                    $details = array(
                        'id' => $fid,
                        'filename' => $file_body['filename'] ?? $fid,
                        'bytes' => $file_body['bytes'] ?? 0,
                        'created_at' => $file_body['created_at'] ?? time(),
                    );
                    $files_with_details[] = $details;
                    if (function_exists('wpiko_chatbot_cache_file_details')) {
                        wpiko_chatbot_cache_file_details($fid, $details);
                    }
                }
            }
        }
    }

    usort($files_with_details, function ($a, $b) {
        return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
    });

    $uploaded_files = array_filter($files_with_details, function ($file) {
        return !preg_match('/^(page_|qa_data_|woocommerce_)/', $file['filename']);
    });
    $page_files = array_filter($files_with_details, function ($file) {
        return strpos($file['filename'], 'page_') === 0;
    });
    $qa_files = array_filter($files_with_details, function ($file) {
        return strpos($file['filename'], 'qa_data_') === 0;
    });
    $woo_files = array_filter($files_with_details, function ($file) {
        return strpos($file['filename'], 'woocommerce_') === 0;
    });

    return array(
        'success' => true,
        'files' => $files_with_details,
        // Migration callers must distinguish a full live listing from partial results.
        'complete' => !$has_more && count($files_with_details) === count($all_file_ids),
        'performance' => array(
            'total_files' => count($files_with_details),
            'cached_files' => count($all_file_ids) - count($files_to_fetch),
            'api_fetched' => count($files_to_fetch),
            'pages_fetched' => $page_count,
            'file_breakdown' => array(
                'uploaded_files' => count($uploaded_files),
                'page_files' => count($page_files),
                'qa_files' => count($qa_files),
                'woocommerce_files' => count($woo_files)
            )
        )
    );
}

function wpiko_chatbot_delete_responses_file($file_id)
{
    // The file list is about to change.
    wpiko_chatbot_clear_vector_store_files_cache();

    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if (empty($api_key) || empty($vector_store_id)) {
        return array('success' => false, 'message' => 'Missing API key or Vector Store');
    }
    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );
    // Remove from vector store
    $vs_resp = wp_remote_request("https://api.openai.com/v1/vector_stores/{$vector_store_id}/files/{$file_id}", array(
        'headers' => $headers,
        'method' => 'DELETE',
        'timeout' => 30
    ));
    if (is_wp_error($vs_resp)) {
        return array('success' => false, 'message' => 'Failed to delete file from Vector Store: ' . $vs_resp->get_error_message());
    }
    $vs_code = wp_remote_retrieve_response_code($vs_resp);
    if ($vs_code !== 204 && $vs_code !== 200) {
        $err_body = json_decode(wp_remote_retrieve_body($vs_resp), true);
        $err = isset($err_body['error']['message']) ? $err_body['error']['message'] : 'Unknown error occurred';
        return array('success' => false, 'message' => 'Failed to delete file from Vector Store: ' . $err);
    }
    // Delete from storage
    $del_resp = wp_remote_request("https://api.openai.com/v1/files/{$file_id}", array(
        'headers' => $headers,
        'method' => 'DELETE',
        'timeout' => 30
    ));
    if (is_wp_error($del_resp)) {
        return array('success' => false, 'message' => 'Failed to delete file from OpenAI storage: ' . $del_resp->get_error_message());
    }
    $del_code = wp_remote_retrieve_response_code($del_resp);
    if ($del_code !== 204 && $del_code !== 200) {
        $err_body = json_decode(wp_remote_retrieve_body($del_resp), true);
        $err = isset($err_body['error']['message']) ? $err_body['error']['message'] : 'Unknown error occurred';
        return array('success' => false, 'message' => 'Failed to delete file from OpenAI storage: ' . $err);
    }

    if (function_exists('wpiko_chatbot_remove_cached_file')) {
        wpiko_chatbot_remove_cached_file($file_id);
    }

    /**
     * Fires after a knowledge file was deleted from OpenAI.
     *
     * @param string $file_id Deleted file ID.
     */
    do_action('wpiko_chatbot_responses_file_deleted', $file_id);

    return array('success' => true);
}

/**
 * Delete the Responses vector store and all associated files
 */
function wpiko_chatbot_delete_responses_vector_store()
{
    // The file list is about to change.
    wpiko_chatbot_clear_vector_store_files_cache();

    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $vector_store_id = get_option('wpiko_chatbot_responses_vector_store_id', '');

    if (empty($api_key)) {
        return array('success' => false, 'message' => 'API key is not set');
    }

    if (empty($vector_store_id)) {
        return array('success' => false, 'message' => 'No vector store to delete');
    }

    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    );

    // Step 1: List all files in the vector store
    wpiko_chatbot_log('Starting deletion of vector store and all associated files', 'info');
    $all_file_ids = array();
    $after = null;
    $has_more = true;
    $page_count = 0;
    $max_pages = 50;

    while ($has_more && $page_count < $max_pages) {
        $page_count++;
        $query_params = array('limit' => 100);
        if ($after) {
            $query_params['after'] = $after;
        }
        $url = "https://api.openai.com/v1/vector_stores/{$vector_store_id}/files?" . http_build_query($query_params);
        $resp = wp_remote_get($url, array('headers' => $headers, 'timeout' => 30));

        if (is_wp_error($resp)) {
            wpiko_chatbot_log('Error listing files during vector store deletion: ' . $resp->get_error_message(), 'warning');
            break; // Continue with deletion even if listing fails
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (isset($body['error'])) {
            wpiko_chatbot_log('Error response when listing files: ' . $body['error']['message'], 'warning');
            break; // Continue with deletion even if listing fails
        }

        if (!empty($body['data'])) {
            foreach ($body['data'] as $file) {
                $all_file_ids[] = $file['id'];
            }
        }

        $has_more = $body['has_more'] ?? false;
        if ($has_more && !empty($body['data'])) {
            $last = end($body['data']);
            $after = $last['id'];
        }
    }

    // Step 2: Delete all files from storage
    $deleted_files = 0;
    $failed_files = 0;

    if (!empty($all_file_ids)) {
        wpiko_chatbot_log('Found ' . count($all_file_ids) . ' files to delete', 'info');

        foreach ($all_file_ids as $file_id) {
            // Delete file from storage
            $del_resp = wp_remote_request("https://api.openai.com/v1/files/{$file_id}", array(
                'headers' => $headers,
                'method' => 'DELETE',
                'timeout' => 30
            ));

            if (!is_wp_error($del_resp)) {
                $del_code = wp_remote_retrieve_response_code($del_resp);
                if ($del_code === 200 || $del_code === 204) {
                    $deleted_files++;
                } else {
                    $failed_files++;
                    wpiko_chatbot_log('Failed to delete file ' . $file_id . ' (HTTP ' . $del_code . ')', 'warning');
                }
            } else {
                $failed_files++;
                wpiko_chatbot_log('Failed to delete file ' . $file_id . ': ' . $del_resp->get_error_message(), 'warning');
            }
        }

        wpiko_chatbot_log("File deletion complete: {$deleted_files} deleted, {$failed_files} failed", 'info');
    }

    // Step 3: Delete the vector store from OpenAI
    $resp = wp_remote_request("https://api.openai.com/v1/vector_stores/{$vector_store_id}", array(
        'headers' => $headers,
        'method' => 'DELETE',
        'timeout' => 30
    ));

    if (is_wp_error($resp)) {
        wpiko_chatbot_log('Failed to delete Responses vector store: ' . $resp->get_error_message(), 'error');
        return array('success' => false, 'message' => 'Failed to delete vector store: ' . $resp->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200 && $code !== 204) {
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $err = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        wpiko_chatbot_log('Failed to delete Responses vector store: ' . $err, 'error');
        return array('success' => false, 'message' => 'Failed to delete vector store: ' . $err);
    }

    // Step 4: Remove the vector store ID from WordPress options
    delete_option('wpiko_chatbot_responses_vector_store_id');

    // Clear file cache if function exists
    if (function_exists('wpiko_chatbot_clear_file_cache')) {
        wpiko_chatbot_clear_file_cache();
    }

    $success_message = 'Vector store deleted successfully';
    if (!empty($all_file_ids)) {
        $success_message .= " along with {$deleted_files} file(s)";
        if ($failed_files > 0) {
            $success_message .= " ({$failed_files} file(s) could not be deleted)";
        }
    }

    wpiko_chatbot_log($success_message, 'info');

    /**
     * Fires after the whole knowledge store (and its files) was deleted.
     */
    do_action('wpiko_chatbot_responses_vector_store_deleted');

    return array(
        'success' => true,
        'message' => $success_message,
        'files_deleted' => $deleted_files,
        'files_failed' => $failed_files
    );
}

// AJAX handler: upload file for Responses API vector store
function wpiko_chatbot_upload_file_responses_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    if (!isset($_FILES['file'])) {
        wp_send_json_error(array('message' => 'No file was uploaded'));
    }
    // Sanitize the uploaded file array similarly to assistant flow
    $sanitized = array();
    if (isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
        $tmp = sanitize_text_field($_FILES['file']['tmp_name']);
        if (is_uploaded_file($tmp)) {
            $sanitized['tmp_name'] = $tmp;
            $sanitized['name'] = isset($_FILES['file']['name']) ? sanitize_file_name($_FILES['file']['name']) : '';
            $sanitized['type'] = isset($_FILES['file']['type']) ? sanitize_text_field($_FILES['file']['type']) : '';
            $sanitized['error'] = isset($_FILES['file']['error']) ? (int) $_FILES['file']['error'] : 0;
            $sanitized['size'] = isset($_FILES['file']['size']) ? (int) $_FILES['file']['size'] : 0;
        } else {
            wp_send_json_error(array('message' => 'Invalid file upload detected'));
            return;
        }
    } else {
        wp_send_json_error(array('message' => 'File upload failed or no file was provided'));
        return;
    }

    $result = wpiko_chatbot_upload_file_to_responses($sanitized);
    if (!empty($result['success'])) {
        wp_send_json_success(array('message' => 'File uploaded successfully', 'file_id' => $result['file_id']));
    } else {
        wp_send_json_error(array('message' => 'File upload failed: ' . ($result['message'] ?? 'Unknown error')));
    }
}
add_action('wp_ajax_wpiko_chatbot_upload_file_responses', 'wpiko_chatbot_upload_file_responses_ajax');

// AJAX handler: delete Responses vector store
function wpiko_chatbot_delete_responses_vector_store_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $result = wpiko_chatbot_delete_responses_vector_store();

    if ($result['success']) {
        wp_send_json_success(array('message' => $result['message']));
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}
add_action('wp_ajax_wpiko_chatbot_delete_responses_vector_store', 'wpiko_chatbot_delete_responses_vector_store_ajax');

// AJAX handler: get vector store details
function wpiko_chatbot_get_responses_vector_store_details_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $result = wpiko_chatbot_get_responses_vector_store_details();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_wpiko_chatbot_get_responses_vector_store_details', 'wpiko_chatbot_get_responses_vector_store_details_ajax');

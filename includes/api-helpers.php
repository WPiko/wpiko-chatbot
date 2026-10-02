<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// API Helper Functions

// Retry mechanism with exponential backoff
// Reduced from 3 retries to 2 for faster failure feedback (total max wait ~90s instead of 180s+)
define('WPIKO_CHATBOT_OPENAI_MAX_RETRIES', 2);
define('WPIKO_CHATBOT_OPENAI_RETRY_DELAY', 1000000); // 1 second in microseconds

/**
 * Get default error messages (centralized for DRY principle)
 * This is the single source of truth for all error message defaults
 * 
 * @return array Default error messages with keys and values
 */
function wpiko_chatbot_get_default_error_messages()
{
    return array(
        'general_error' => __("An unexpected error occurred. Please try again later.", 'wpiko-chatbot'),
        'connection_issue' => __("We are having trouble connecting to the server. Please check your internet connection.", 'wpiko-chatbot'),
        'server_busy' => __("The system is currently busy. Please try again in a few moments.", 'wpiko-chatbot'),
        'auth_failed' => __("Your session has expired. Please refresh the page and try again.", 'wpiko-chatbot'),
        'feature_restricted' => __("This feature is currently unavailable.", 'wpiko-chatbot')
    );
}

/**
 * Get error message labels for admin interface
 * 
 * @return array Error labels with keys and display names
 */
function wpiko_chatbot_get_error_message_labels()
{
    return array(
        'general_error' => __('General Error (Default)', 'wpiko-chatbot'),
        'connection_issue' => __('Connection Issues (Timeouts, Offline)', 'wpiko-chatbot'),
        'server_busy' => __('Server Busy (Rate Limits, Overloaded)', 'wpiko-chatbot'),
        'auth_failed' => __('Authentication Failed (Session Expired)', 'wpiko-chatbot'),
        'feature_restricted' => __('Feature Unavailable', 'wpiko-chatbot')
    );
}

/**
 * Get saved error messages merged with defaults
 * 
 * @return array Merged error messages
 */
function wpiko_chatbot_get_error_messages()
{
    $defaults = wpiko_chatbot_get_default_error_messages();
    $saved = get_option('wpiko_chatbot_error_messages_grouped', $defaults);
    return wp_parse_args($saved, $defaults);
}

function wpiko_chatbot_api_call_with_retry($url, $args, $max_retries = WPIKO_CHATBOT_OPENAI_MAX_RETRIES)
{
    $max_retries = max(1, absint($max_retries));
    $retry_count = 0;
    $response = null;

    while ($retry_count < $max_retries) {
        $response = wp_remote_post($url, $args);
        $response_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

        if (!is_wp_error($response) && $response_code === 200) {
            return $response;
        }

        // Retry network failures, rate limits, timeouts, and server errors only.
        $is_retryable = is_wp_error($response)
            || in_array($response_code, array(408, 409, 425, 429), true)
            || $response_code >= 500;

        if (!$is_retryable) {
            return $response;
        }

        $retry_count++;

        if ($retry_count < $max_retries && function_exists('wpiko_chatbot_log')) {
            $error_info = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . $response_code;
            wpiko_chatbot_log('API call attempt ' . $retry_count . ' failed: ' . $error_info . '. Retrying...', 'warning');
        }

        if ($retry_count < $max_retries) {
            usleep(WPIKO_CHATBOT_OPENAI_RETRY_DELAY * $retry_count); // Exponential backoff
        }
    }

    return $response; // Return the last response, even if it's an error
}

/**
 * Get the default OpenAI configuration for non-chatbot AI features.
 *
 * These defaults are intentionally independent from the public chatbot model so
 * background utilities retain predictable quality, latency, and cost.
 *
 * @param string $feature Feature identifier.
 * @return array<string, mixed> Feature configuration, or an empty array when unknown.
 */
function wpiko_chatbot_get_ai_feature_config($feature)
{
    $configs = array(
        'translation' => array(
            'model' => 'gpt-6-luna',
            'reasoning_effort' => 'none',
            'verbosity' => 'low',
            'max_output_tokens' => 5800,
            'timeout' => 60,
            'max_retries' => 2,
        ),
        'qa_generation' => array(
            'model' => 'gpt-6-sol',
            'reasoning_effort' => 'low',
            'verbosity' => 'medium',
            // Includes both visible output and reasoning tokens in the Responses API.
            'max_output_tokens' => 6000,
            'timeout' => 90,
            'max_retries' => 2,
        ),
        'contact_enhancement' => array(
            'model' => 'gpt-6-luna',
            'reasoning_effort' => 'none',
            'verbosity' => 'low',
            'max_output_tokens' => 500,
            'timeout' => 30,
            'max_retries' => 3,
        ),
    );

    $config = isset($configs[$feature]) ? $configs[$feature] : array();

    /**
     * Filter the OpenAI configuration for a utility AI feature.
     *
     * @param array  $config  Feature configuration.
     * @param string $feature Feature identifier.
     */
    $config = apply_filters('wpiko_chatbot_ai_feature_config', $config, $feature);
    return is_array($config) ? $config : array();
}

/**
 * Send a utility text-generation request through the OpenAI Responses API.
 *
 * @param string $api_key OpenAI API key.
 * @param string $feature Feature identifier used to load defaults.
 * @param string $instructions Developer instructions for the model.
 * @param string $input User input for the model.
 * @param array  $request_overrides Optional Responses API body overrides.
 * @return array|WP_Error Parsed response body on success, WP_Error on failure.
 */
function wpiko_chatbot_openai_feature_text_request($api_key, $feature, $instructions, $input, $request_overrides = array())
{
    $config = wpiko_chatbot_get_ai_feature_config($feature);
    if (empty($config['model'])) {
        return new WP_Error(
            'wpiko_chatbot_unknown_ai_feature',
            sprintf('Unknown AI feature configuration: %s', $feature)
        );
    }

    if (empty($api_key)) {
        return new WP_Error('wpiko_chatbot_missing_api_key', 'OpenAI API key is missing.');
    }

    $request_body = array(
        'model' => $config['model'],
        'instructions' => $instructions,
        'input' => $input,
        'max_output_tokens' => isset($config['max_output_tokens']) ? max(1, absint($config['max_output_tokens'])) : 1000,
        'reasoning' => array(
            'effort' => isset($config['reasoning_effort']) ? $config['reasoning_effort'] : 'none',
        ),
        'text' => array(
            'verbosity' => isset($config['verbosity']) ? $config['verbosity'] : 'medium',
        ),
        // Utility calls are single-turn and do not need server-side response storage.
        'store' => false,
    );

    if (!empty($request_overrides)) {
        $request_body = array_replace_recursive($request_body, $request_overrides);
    }

    /**
     * Filter a utility feature's final Responses API request body.
     *
     * @param array  $request_body Responses API request body.
     * @param string $feature      Feature identifier.
     * @param array  $config       Resolved feature configuration.
     */
    $request_body = apply_filters('wpiko_chatbot_ai_feature_request_body', $request_body, $feature, $config);
    if (!is_array($request_body)) {
        return new WP_Error(
            'wpiko_chatbot_invalid_ai_feature_request',
            sprintf('Invalid Responses API request body for feature: %s', $feature)
        );
    }

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($request_body),
        'timeout' => isset($config['timeout']) ? max(1, absint($config['timeout'])) : 60,
        'method' => 'POST',
    );

    $max_retries = isset($config['max_retries']) ? absint($config['max_retries']) : WPIKO_CHATBOT_OPENAI_MAX_RETRIES;
    $max_retries = max(1, $max_retries);
    $response = wpiko_chatbot_api_call_with_retry('https://api.openai.com/v1/responses', $args, $max_retries);

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $response_body = json_decode($raw_body, true);

    if ($response_code !== 200 || !is_array($response_body) || isset($response_body['error'])) {
        $api_message = isset($response_body['error']['message'])
            ? $response_body['error']['message']
            : 'OpenAI Responses API returned an invalid response.';

        return new WP_Error(
            'wpiko_chatbot_openai_feature_request_failed',
            $api_message,
            array('status' => $response_code, 'feature' => $feature)
        );
    }

    if (isset($response_body['status']) && $response_body['status'] !== 'completed') {
        $incomplete_reason = isset($response_body['incomplete_details']['reason'])
            ? $response_body['incomplete_details']['reason']
            : $response_body['status'];

        return new WP_Error(
            'wpiko_chatbot_openai_feature_incomplete',
            sprintf('OpenAI response was not completed: %s', $incomplete_reason),
            array('status' => $response_code, 'feature' => $feature)
        );
    }

    $output_text = function_exists('wpiko_chatbot_extract_responses_output_text')
        ? wpiko_chatbot_extract_responses_output_text($response_body)
        : null;

    if (!is_string($output_text) || $output_text === '') {
        return new WP_Error(
            'wpiko_chatbot_openai_feature_empty_output',
            'OpenAI Responses API returned no text output.',
            array('status' => $response_code, 'feature' => $feature)
        );
    }

    $response_body['wpiko_output_text'] = $output_text;
    return $response_body;
}

// User-friendly error messages
function wpiko_chatbot_get_user_friendly_error_message($error_message, $context = 'responses')
{
    // Use centralized error messages
    $custom_error_messages = wpiko_chatbot_get_error_messages();

    // Map specific technical errors to categories
    $error_mapping = array(
        // Connection Issues
        'cURL error 28' => 'connection_issue',
        'cURL error 7' => 'connection_issue',
        'Connection timed out' => 'connection_issue',
        'Responses API request failed' => 'connection_issue',
        'Failed to call Responses API' => 'connection_issue',
        'API request failed' => 'connection_issue',
        'Response timeout' => 'connection_issue',
        'The request timed out' => 'connection_issue',
        'timed out' => 'connection_issue',
        '504' => 'connection_issue',
        '400' => 'connection_issue',

        // Server Busy / Rate Limits
        'Rate limit exceeded' => 'server_busy',
        'Model overloaded' => 'server_busy',
        '429' => 'server_busy',
        '500' => 'server_busy',
        '502' => 'server_busy',
        '503' => 'server_busy',

        // Authentication
        'Authentication failed' => 'auth_failed',
        '401' => 'auth_failed',
        '403' => 'auth_failed',
        'Access denied' => 'auth_failed',

        // Validation / Format (mapped to general or specific if needed)
        'Invalid response format' => 'general_error',
        'Response generation failed' => 'general_error'
    );

    // Check for category mapping
    foreach ($error_mapping as $search_string => $category_key) {
        if (strpos($error_message, $search_string) !== false) {
            // Log the specific error before showing the generic message (uses debug logging)
            if (function_exists('wpiko_chatbot_log')) {
                wpiko_chatbot_log('Technical error mapped to ' . $category_key . ': ' . $error_message, 'error');
            }
            return $custom_error_messages[$category_key];
        }
    }

    // Pass through if the error message is already one of our custom messages (exact match)
    if (in_array($error_message, $custom_error_messages)) {
        return $error_message;
    }

    // Log the unknown error message for debugging
    if (function_exists('wpiko_chatbot_log')) {
        wpiko_chatbot_log('Unknown error message encountered: ' . $error_message, 'warning');
    }

    return $custom_error_messages['general_error'];
}

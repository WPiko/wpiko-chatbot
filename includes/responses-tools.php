<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Browser/account-bound history, enabled when private order exports are retired. */
function wpiko_chatbot_private_orders_retired() {
    return (bool) get_option('wpiko_chatbot_private_orders_retired', false);
}

function wpiko_chatbot_chat_browser_token() {
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $cookie = isset($_COOKIE['wpiko_chat_session']) ? (string) $_COOKIE['wpiko_chat_session'] : '';
    $token = preg_match('/^[a-zA-Z0-9]{48}$/D', $cookie) ? $cookie : wp_generate_password(48, false, false);
    if ($cookie !== $token && !headers_sent()) {
        // Positional arguments retain compatibility with PHP 7.0.
        setcookie('wpiko_chat_session', $token, time() + 30 * DAY_IN_SECONDS, '/', '', is_ssl(), true);
    }
    return $token;
}

function wpiko_chatbot_prepare_private_chat() {
    if (wpiko_chatbot_private_orders_retired()) {
        wpiko_chatbot_chat_browser_token();
    }
}
add_action('wpiko_chatbot_before_chat_request', 'wpiko_chatbot_prepare_private_chat');

function wpiko_chatbot_chat_state_key($conversation_id) {
    return 'wpiko_chatbot_order_chat_' . hash_hmac('sha256', wpiko_chatbot_chat_browser_token() . '|' . get_current_user_id() . '|' . $conversation_id, wp_salt('auth'));
}

function wpiko_chatbot_chat_session_is_bound($conversation_id) {
    return !wpiko_chatbot_private_orders_retired()
        || ($conversation_id && is_array(get_transient(wpiko_chatbot_chat_state_key($conversation_id))));
}

function wpiko_chatbot_private_chat_script_data($data) {
    if (wpiko_chatbot_private_orders_retired()) {
        // Cached guest pages share this marker; signed-in pages must not be publicly cached.
        $data['private_history_scope'] = 'orders-v1-' . hash_hmac('sha256', (string) get_current_user_id(), wp_salt('auth'));
    }
    return $data;
}
add_filter('wpiko_chatbot_frontend_script_data', 'wpiko_chatbot_private_chat_script_data');

function wpiko_chatbot_bind_chat_session($conversation_id) {
    if (!wpiko_chatbot_private_orders_retired()) {
        return $conversation_id;
    }
    $state = get_transient(wpiko_chatbot_chat_state_key($conversation_id));
    if (!is_array($state)) {
        // Never import legacy context or context belonging to another browser/account.
        $conversation_id = 'resp_' . wp_generate_password(32, false, false);
        set_transient(wpiko_chatbot_chat_state_key($conversation_id), array('response_id' => null), DAY_IN_SECONDS);
    }
    return $conversation_id;
}

function wpiko_chatbot_prepare_response_tools($body, $conversation_id) {
    // Bounds output (including reasoning) for normal, streaming and tool follow-up calls.
    $body['max_output_tokens'] = 8192;
    if (wpiko_chatbot_private_orders_retired()) {
        unset($body['previous_response_id']);
        $state = get_transient(wpiko_chatbot_chat_state_key($conversation_id));
        if (!empty($state['response_id'])) {
            $body['previous_response_id'] = $state['response_id'];
        }
        foreach ($body['tools'] ?? array() as $index => $tool) {
            if ($tool['type'] === 'file_search') {
                // Unclassified and retired order files can never enter retrieval.
                $body['tools'][$index]['filters'] = array('type' => 'eq', 'key' => 'wpiko_scope', 'value' => 'public_v1');
            }
        }
    }
    $tools = apply_filters('wpiko_chatbot_response_function_tools', array());
    if ($tools) {
        $body['tools'] = array_merge($body['tools'] ?? array(), $tools);
        $body['parallel_tool_calls'] = false;
    }
    return $body;
}

function wpiko_chatbot_record_private_response($conversation_id, $response_id) {
    if (wpiko_chatbot_private_orders_retired() && $response_id) {
        set_transient(wpiko_chatbot_chat_state_key($conversation_id), array('response_id' => $response_id), DAY_IN_SECONDS);
    }
}

function wpiko_chatbot_response_has_function_calls($response) {
    foreach ($response['output'] ?? array() as $item) {
        if (($item['type'] ?? '') === 'function_call') {
            return true;
        }
    }
    return false;
}

/** One bounded tool round; tool names are explicitly registered, never PHP callables. */
function wpiko_chatbot_run_response_tools($http_response, $body, $headers) {
    if (is_wp_error($http_response) || wp_remote_retrieve_response_code($http_response) !== 200) {
        return $http_response;
    }
    $response = json_decode(wp_remote_retrieve_body($http_response), true);
    if (!is_array($response) || !wpiko_chatbot_response_has_function_calls($response)) {
        return $http_response;
    }
    if (empty($response['id'])) {
        return new WP_Error('invalid_tool_response', 'Missing tool response identifier.');
    }
    $allowed = array();
    foreach ($body['tools'] ?? array() as $tool) {
        if (($tool['type'] ?? '') === 'function') {
            $allowed[] = $tool['name'];
        }
    }
    $outputs = array();
    $executed = false;
    foreach ($response['output'] as $item) {
        if (($item['type'] ?? '') !== 'function_call') {
            continue;
        }
        if (count($outputs) >= 8 || empty($item['call_id'])) {
            return new WP_Error('invalid_tool_response', 'Invalid or excessive tool calls.');
        }
        $result = array('error' => 'lookup_unavailable', 'message' => 'Please ask about one order at a time.');
        $arguments = json_decode($item['arguments'] ?? '', true);
        if (!$executed && in_array($item['name'] ?? '', $allowed, true) && is_array($arguments)) {
            $executed = true;
            try {
                $result = apply_filters('wpiko_chatbot_response_function_result', $result, $item['name'], $arguments);
            } catch (Throwable $error) {
                $result = array('error' => 'lookup_unavailable', 'message' => 'The order lookup could not be completed. Please try again later.');
            }
        }
        $outputs[] = array('type' => 'function_call_output', 'call_id' => $item['call_id'], 'output' => wp_json_encode($result));
    }
    $body['previous_response_id'] = $response['id'];
    $body['input'] = $outputs;
    $body['tool_choice'] = 'none';
    unset($body['stream']);
    $final = wpiko_chatbot_api_call_with_retry('https://api.openai.com/v1/responses', array(
        'headers' => $headers,
        'body' => wp_json_encode($body),
        'timeout' => 60,
    ));
    if (!is_wp_error($final) && wp_remote_retrieve_response_code($final) === 200) {
        $decoded = json_decode(wp_remote_retrieve_body($final), true);
        if (is_array($decoded) && wpiko_chatbot_response_has_function_calls($decoded)) {
            return new WP_Error('tool_round_limit', 'The order lookup could not be completed.');
        }
    }
    return $final;
}

/** Label new uploads; existing files are classified by the Pro background cleanup. */
function wpiko_chatbot_vector_file_attachment($file_id, $filename) {
    $data = array('file_id' => $file_id);
    if (wpiko_chatbot_private_orders_retired()) {
        $data['attributes'] = array('wpiko_scope' => strtolower($filename) === 'woocommerce_orders.json' ? 'private_orders' : 'public_v1');
    }
    return $data;
}

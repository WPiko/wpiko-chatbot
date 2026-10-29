<?php
/**
 * Conversation memory: how long a visitor's chat is remembered.
 *
 * 'tab' keeps the chat only while the browser tab is open (sessionStorage).
 * '1d', '7d' and '30d' keep it across visits on the same device (localStorage)
 * until the visitor clears the chat or that long passes without a message.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Longest supported memory. OpenAI keeps stored responses (used to continue a
 * conversation) for 30 days, and the chat session cookie lasts 30 days.
 */
if (!defined('WPIKO_CHATBOT_MAX_MEMORY_DAYS')) {
    define('WPIKO_CHATBOT_MAX_MEMORY_DAYS', 30);
}

/**
 * Days remembered for each stored value (0 = only while the tab is open).
 *
 * @return array<string, int>
 */
function wpiko_chatbot_conversation_memory_day_map()
{
    return array('tab' => 0, '1d' => 1, '7d' => 7, '30d' => 30);
}

/**
 * Available choices with their labels, keyed by stored value.
 *
 * @return array<string, string>
 */
function wpiko_chatbot_conversation_memory_choices()
{
    return array(
        'tab' => __('Only while the browser tab is open', 'wpiko-chatbot'),
        '1d' => __('Across visits, for 24 hours after the last message', 'wpiko-chatbot'),
        '7d' => __('Across visits, for 7 days after the last message (recommended)', 'wpiko-chatbot'),
        '30d' => __('Across visits, for 30 days after the last message', 'wpiko-chatbot'),
    );
}

/**
 * @param mixed $value Raw value.
 * @return string A valid choice ('tab' when unknown).
 */
function wpiko_chatbot_sanitize_conversation_memory($value)
{
    $value = is_string($value) ? strtolower(trim($value)) : '';
    return array_key_exists($value, wpiko_chatbot_conversation_memory_day_map()) ? $value : 'tab';
}

/**
 * Saved choice. Sites that never chose keep the original behaviour ('tab').
 *
 * @return string
 */
function wpiko_chatbot_get_conversation_memory()
{
    return wpiko_chatbot_sanitize_conversation_memory(get_option('wpiko_chatbot_conversation_memory', 'tab'));
}

/**
 * Default for a new site: remember conversations across visits for 7 days.
 * Existing sites that update keep 'tab' until the owner changes it.
 *
 * @param bool $is_fresh_install Whether the plugin was never set up on this site.
 * @return string
 */
function wpiko_chatbot_default_conversation_memory($is_fresh_install)
{
    return $is_fresh_install ? '7d' : 'tab';
}

/**
 * Days a conversation is remembered after its last message; 0 = only while the tab is open.
 *
 * @return int
 */
function wpiko_chatbot_conversation_memory_days()
{
    $days_by_choice = wpiko_chatbot_conversation_memory_day_map();
    $days = $days_by_choice[wpiko_chatbot_get_conversation_memory()];

    /**
     * Filter how many days a conversation is remembered after its last message.
     *
     * Return 0 to keep it only while the browser tab is open. Values above
     * 30 are lowered to 30 (OpenAI keeps conversation state for 30 days).
     *
     * @param int $days Days from the setting.
     */
    $days = (int) apply_filters('wpiko_chatbot_conversation_memory_days', $days);

    return max(0, min(WPIKO_CHATBOT_MAX_MEMORY_DAYS, $days));
}

/**
 * How long the server keeps the link between a browser and its conversation.
 *
 * @return int Seconds.
 */
function wpiko_chatbot_chat_state_lifetime()
{
    $days = wpiko_chatbot_conversation_memory_days();
    return $days > 0 ? $days * DAY_IN_SECONDS : DAY_IN_SECONDS;
}

/**
 * Pass the setting to the chat script.
 *
 * @param array $data Script data.
 * @return array
 */
function wpiko_chatbot_conversation_memory_script_data($data)
{
    $days = wpiko_chatbot_conversation_memory_days();
    $data['conversation_memory'] = array(
        'persistent' => $days > 0,
        'days' => $days,
    );

    if ($days > 0) {
        // Changes when someone logs in or out, so a remembered conversation is
        // never shown to another account. Cached guest pages all share one value.
        $data['conversation_memory']['account'] = substr(hash_hmac('sha256', 'memory-v1|' . get_current_user_id(), wp_salt('auth')), 0, 20);
    }
    return $data;
}
add_filter('wpiko_chatbot_frontend_script_data', 'wpiko_chatbot_conversation_memory_script_data');

/**
 * Whether OpenAI rejected the request because the earlier response it should
 * continue from no longer exists (expired or deleted).
 *
 * @param int          $status_code  HTTP status.
 * @param array|string $error_body   Decoded error body, or the raw body.
 * @param array        $request_body Request that was sent.
 * @return bool
 */
function wpiko_chatbot_is_missing_previous_response_error($status_code, $error_body, $request_body)
{
    if (empty($request_body['previous_response_id']) || !in_array((int) $status_code, array(400, 404), true)) {
        return false;
    }

    $error = is_array($error_body) && isset($error_body['error']) && is_array($error_body['error']) ? $error_body['error'] : array();
    if (isset($error['param']) && $error['param'] === 'previous_response_id') {
        return true;
    }

    $message = isset($error['message']) ? (string) $error['message'] : (is_string($error_body) ? $error_body : '');
    $message = strtolower($message);

    return strpos($message, 'previous_response_id') !== false || strpos($message, 'previous response') !== false;
}

/**
 * Recent messages of a conversation as plain text, oldest first.
 *
 * The newest visitor message is skipped: it is the one being answered and is
 * sent separately.
 *
 * @param string $conversation_id Conversation (session) ID.
 * @param int    $max_rows        Most messages to include.
 * @param int    $max_chars       Most characters in all.
 * @return string Empty when there is no earlier message.
 */
function wpiko_chatbot_get_recent_history_text($conversation_id, $max_rows = 20, $max_chars = 6000)
{
    global $wpdb;

    if (empty($conversation_id)) {
        return '';
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT role, message FROM `{$wpdb->prefix}wpiko_chatbot_conversations`
         WHERE session_id = %s AND role IN ('user', 'assistant', 'admin')
         ORDER BY id DESC LIMIT %d",
        $conversation_id,
        (int) $max_rows + 1
    ));

    if (empty($rows)) {
        return '';
    }

    if ($rows[0]->role === 'user') {
        array_shift($rows);
    }

    $labels = array(
        'user' => 'Visitor',
        'assistant' => 'Assistant',
        'admin' => 'Team member',
    );
    $lines = array();
    $total = 0;

    foreach ($rows as $row) {
        if (!isset($labels[$row->role])) {
            continue;
        }
        $text = html_entity_decode(wp_strip_all_tags((string) $row->message), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            continue;
        }
        if (mb_strlen($text) > 800) {
            $text = mb_substr($text, 0, 800) . '…';
        }

        $line = $labels[$row->role] . ': ' . $text;
        $total += mb_strlen($line);
        if ($total > $max_chars) {
            break;
        }
        $lines[] = $line;
    }

    return empty($lines) ? '' : implode("\n", array_reverse($lines));
}

/**
 * Turn a request that continued from an expired response into one that starts
 * a new chain, with the recent messages included so the AI keeps the context.
 *
 * @param array  $request_body    Request that failed.
 * @param string $conversation_id Conversation (session) ID.
 * @return array
 */
function wpiko_chatbot_prepare_history_recovery_request($request_body, $conversation_id)
{
    unset($request_body['previous_response_id']);

    $history = wpiko_chatbot_get_recent_history_text($conversation_id);
    if ($history !== '' && isset($request_body['input']) && is_string($request_body['input'])) {
        $request_body['input'] = "Earlier messages in this conversation (the visitor came back to it; use them as context):\n"
            . $history
            . "\n\nLatest user message:\n"
            . $request_body['input'];
    }

    wpiko_chatbot_log('Previous response is no longer available at OpenAI; continuing with recent messages as context.', 'warning');

    return $request_body;
}

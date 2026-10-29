<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot/tests/history-recovery-live.php
// First checks, for a guest and a logged-in user, how long the server keeps the
// link between a browser and its conversation, and that another account cannot
// continue it (no OpenAI requests). Then sends two real OpenAI requests (normal and streaming) that continue from a
// response ID OpenAI does not have, and checks that the chat recovers with the
// earlier messages as context. Removes its test conversation afterwards.
// Keep the tests folder out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');

global $wpdb;
$results = array();

// Session binding for guests and logged-in users (no OpenAI requests).
// Needs Pro's controlled order lookup (wpiko_chatbot_private_orders_retired).
if (wpiko_chatbot_private_orders_retired()) {
    $admin_id = get_current_user_id();
    $saved_memory = get_option('wpiko_chatbot_conversation_memory', null);
    $timeout_of = function ($conversation_id) {
        if (wp_using_ext_object_cache()) {
            return null;
        }
        $key = wpiko_chatbot_chat_state_key($conversation_id);
        return (int) get_option('_transient_timeout_' . $key) - time();
    };
    $check = function ($ok, $label) use (&$results) { $results[] = ($ok ? 'PASS' : 'FAIL') . '  ' . $label; };

    foreach (array(0 => 'guest', $admin_id => 'logged-in') as $user_id => $who) {
        wp_set_current_user($user_id);
        foreach (array('7d' => 7 * DAY_IN_SECONDS, 'tab' => DAY_IN_SECONDS) as $memory => $expected) {
            update_option('wpiko_chatbot_conversation_memory', $memory);
            $conversation_id = wpiko_chatbot_bind_chat_session('');
            wpiko_chatbot_record_private_response($conversation_id, 'resp_binding_test');
            $left = $timeout_of($conversation_id);
            if ($left !== null) {
                $check(abs($left - $expected) < 120, "$who, $memory: server keeps the conversation " . round($left / DAY_IN_SECONDS, 2) . ' days');
            }
            $check(wpiko_chatbot_bind_chat_session($conversation_id) === $conversation_id, "$who, $memory: same browser and account continues the conversation");
            wp_set_current_user($user_id ? 0 : $admin_id);
            $check(wpiko_chatbot_bind_chat_session($conversation_id) !== $conversation_id, "$who, $memory: another account cannot continue it");
            wp_set_current_user($user_id);
            delete_transient(wpiko_chatbot_chat_state_key($conversation_id));
        }
    }

    wp_set_current_user($admin_id);
    if ($saved_memory === null) {
        delete_option('wpiko_chatbot_conversation_memory');
    } else {
        update_option('wpiko_chatbot_conversation_memory', $saved_memory);
    }
} else {
    $results[] = 'SKIP  session binding (controlled order lookup is not active on this site)';
}

$missing_id = 'resp_wpikomissing' . wp_generate_password(20, false, false);

foreach (array('normal', 'streaming') as $mode) {
    $conversation_id = wpiko_chatbot_bind_chat_session('');
    wpiko_chatbot_save_message(0, $conversation_id, 'user', 'Hi, my name is Eleni and I need help choosing a teal teapot.', '');
    wpiko_chatbot_save_message(0, $conversation_id, 'assistant', '<p>Nice to meet you, Eleni! Happy to help with a teal teapot.</p>', '');
    wpiko_chatbot_record_private_response($conversation_id, $missing_id);
    wpiko_chatbot_save_response_id($conversation_id, $missing_id);

    $question = 'What is my name and which colour did I mention? Answer in one short sentence.';
    if ($mode === 'normal') {
        $result = wpiko_chatbot_responses_api_call($question, $conversation_id, '', $missing_id);
    } else {
        $deltas = '';
        $result = wpiko_chatbot_responses_api_stream($question, $conversation_id, '', $missing_id, array(
            'delta' => function ($delta) use (&$deltas) { $deltas .= $delta; },
        ));
    }

    $answer = isset($result['response']) ? wp_strip_all_tags($result['response']) : '';
    $ok = $answer !== '' && stripos($answer, 'Eleni') !== false && stripos($answer, 'teal') !== false
        && !empty($result['response_id']) && $result['response_id'] !== $missing_id;
    $results[] = ($ok ? 'PASS' : 'FAIL') . "  $mode: " . ($answer !== '' ? $answer : wp_json_encode($result));

    $wpdb->delete($wpdb->prefix . 'wpiko_chatbot_conversations', array('session_id' => $conversation_id));
    delete_transient(wpiko_chatbot_chat_state_key($conversation_id));
}

echo implode("\n", $results) . "\n";

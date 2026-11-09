<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot/tests/usage-tracking-live.php
// Prints the latest recorded usage rows, then makes real OpenAI requests through
// every tracked path (chat reply, translation, connection test and, with Pro,
// contact form writing help) and checks that each one is recorded with a cost.
// One extra request is made without a usage context and must NOT be recorded.
// Test conversations and the usage rows created by this run are removed again;
// add ?keep=1 to keep the usage rows (to look at the Dashboard widget).
// Keep tests out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

global $wpdb;
$table = wpiko_chatbot_usage_table();
$failures = 0;
$check = function ($ok, $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failures++;
    }
};
$print_rows = function ($rows) {
    foreach ($rows as $row) {
        printf(
            "  #%d %s %-20s %-24s in=%d (cached %d) out=%d (reasoning %d) searches=%d cost=$%.6f+$%.6f%s\n",
            $row->id, $row->created_at, $row->source, $row->model, $row->input_tokens, $row->cached_tokens,
            $row->output_tokens, $row->reasoning_tokens, $row->search_calls, $row->token_cost, $row->search_cost,
            $row->priced ? '' : ' (unpriced)'
        );
    }
};

echo "Usage table: $table, schema " . get_option('wpiko_chatbot_usage_schema') . ', tracking since ' . get_option('wpiko_chatbot_usage_since') . " UTC\n";
echo "Latest rows before this run:\n";
$print_rows($wpdb->get_results("SELECT * FROM `$table` ORDER BY id DESC LIMIT 5"));
$start_id = (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) FROM `$table`");
echo "\n";

$new_rows = function () use ($wpdb, $table, &$start_id) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` WHERE id > %d ORDER BY id ASC", $start_id));
    if (!empty($rows)) {
        $start_id = (int) end($rows)->id;
    }
    return $rows;
};
$test_ids = array();
$remember = function ($rows) use (&$test_ids) {
    foreach ($rows as $row) {
        $test_ids[] = (int) $row->id;
    }
};

// 1. Non-streaming chat reply.
$conversation_id = wpiko_chatbot_bind_chat_session('');
$result = wpiko_chatbot_responses_api_call('In one short sentence: what is this website about?', $conversation_id, '', null);
$session = isset($result['conversation_id']) ? $result['conversation_id'] : $conversation_id;
$rows = $new_rows();
$remember($rows);
$print_rows($rows);
$check(isset($result['response']) && $result['response'] !== '', 'chat reply received');
$chat_rows = array_filter($rows, function ($row) { return $row->source === 'chat'; });
$chat = reset($chat_rows);
$check($chat && $chat->session_id === $session, 'chat reply recorded for its conversation');
$check($chat && $chat->input_tokens > 0 && $chat->output_tokens > 0 && $chat->priced && $chat->token_cost > 0, 'chat reply has tokens and a cost');
$check($chat && $chat->response_id !== '' && $chat->response_id === (string) ($result['response_id'] ?? $chat->response_id), 'response ID stored');
$wpdb->delete($wpdb->prefix . 'wpiko_chatbot_conversations', array('session_id' => $session));
delete_transient(wpiko_chatbot_chat_state_key($session));

// 2. Utility feature (translation), through the shared helper.
$api_key = wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', ''));
$translation = wpiko_chatbot_openai_feature_text_request($api_key, 'translation', 'Translate the text to Greek. Return only the translation.', 'Good morning');
$rows = $new_rows();
$remember($rows);
$print_rows($rows);
$check(!is_wp_error($translation), 'translation request worked');
$check(count($rows) === 1 && $rows[0]->source === 'translation' && $rows[0]->session_id === '' && $rows[0]->token_cost > 0, 'translation recorded');
$check(wpiko_chatbot_usage_context() !== null && wpiko_chatbot_usage_context()['source'] === 'chat', 'chat context restored after the utility request');

// 3. Connection test.
$test = wpiko_chatbot_test_openai_connection($api_key);
$rows = $new_rows();
$remember($rows);
$print_rows($rows);
$check(!empty($test['ok']), 'connection test worked');
$check(count($rows) === 1 && $rows[0]->source === 'connection_test', 'connection test recorded');

// 4. Pro: contact form writing help (only when Pro is active).
if (defined('WPIKO_CHATBOT_PRO_VERSION')) {
    $enhanced = wpiko_chatbot_openai_feature_text_request($api_key, 'contact_enhancement', 'Make this text sound friendly. Return only the text.', 'need price info');
    $rows = $new_rows();
    $remember($rows);
    $print_rows($rows);
    $check(!is_wp_error($enhanced) && count($rows) === 1 && $rows[0]->source === 'contact_enhancement', 'Pro contact form writing help recorded');
}

// 5. A request without a usage context (like another plugin) is not recorded.
$previous = wpiko_chatbot_usage_context(null);
wp_remote_post(WPIKO_CHATBOT_RESPONSES_ENDPOINT, array(
    'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
    'body' => wp_json_encode(array('model' => 'gpt-6-luna', 'input' => 'Reply with OK', 'max_output_tokens' => 16, 'store' => false)),
    'timeout' => 30,
));
wpiko_chatbot_usage_context($previous);
$check(count($new_rows()) === 0, 'request without a usage context not recorded');

// 6. Dashboard numbers include this run.
$snapshot = wpiko_chatbot_get_usage_snapshot();
$check($snapshot['today']['cost'] > 0 && $snapshot['month']['cost'] >= $snapshot['today']['cost'], 'Dashboard snapshot has today\'s cost');
echo 'Today: ' . wpiko_chatbot_format_usage_cost($snapshot['today']['cost']) . ', this month: ' . wpiko_chatbot_format_usage_cost($snapshot['month']['cost']) . "\n";

if (empty($_GET['keep']) && !empty($test_ids)) {
    $wpdb->query('DELETE FROM `' . $table . '` WHERE id IN (' . implode(',', array_map('intval', $test_ids)) . ')');
    echo 'Removed ' . count($test_ids) . " test usage rows (add ?keep=1 to keep them).\n";
}

echo $failures ? "\n$failures FAILED\n" : "\nALL PASS\n";

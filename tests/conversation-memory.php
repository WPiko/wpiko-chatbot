<?php
// CLI-only regression test for the Conversation Memory setting and the recovery
// used when OpenAI no longer has the response a conversation continues from.
// No WordPress, database or network needed.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');
define('DAY_IN_SECONDS', 86400);

$options = array();
$filters = array();
$user_id = 0;
$history_rows = array();
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_filter($name, $fn, $priority = 10) { $GLOBALS['filters'][$name][] = $fn; }
function apply_filters($name, $value, ...$args) {
    foreach ($GLOBALS['filters'][$name] ?? array() as $fn) { $value = $fn($value, ...$args); }
    return $value;
}
function __($s, ...$args) { return $s; }
function wp_salt(...$args) { return 'test-only-salt'; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function wp_strip_all_tags($s) { return trim(strip_tags($s)); }
function wpiko_chatbot_log(...$args) {}
$wpdb = new class {
    public $prefix = 'test_';
    public $last = null;
    function prepare($sql, ...$args) { return array($sql, $args); }
    function get_results($prepared) {
        $this->last = $prepared;
        // Newest first, limited like the real query.
        return array_slice(array_reverse($GLOBALS['history_rows']), 0, $prepared[1][1]);
    }
};

require dirname(__DIR__) . '/includes/conversation-memory.php';

$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}
function row($role, $message) { return (object) array('role' => $role, 'message' => $message); }

// Setting and defaults.
check('Sites that never chose keep tab-only memory', wpiko_chatbot_get_conversation_memory(), 'tab');
check('Tab-only memory is 0 days', wpiko_chatbot_conversation_memory_days(), 0);
check('Tab-only server link stays 1 day', wpiko_chatbot_chat_state_lifetime(), DAY_IN_SECONDS);
check('New installs default to 7 days', wpiko_chatbot_default_conversation_memory(true), '7d');
check('Existing installs default to tab only', wpiko_chatbot_default_conversation_memory(false), 'tab');
check('Unknown value falls back to tab', wpiko_chatbot_sanitize_conversation_memory('forever'), 'tab');
check('Non-string value falls back to tab', wpiko_chatbot_sanitize_conversation_memory(array('7d')), 'tab');
foreach (array('1d' => 1, '7d' => 7, '30d' => 30) as $choice => $days) {
    $options['wpiko_chatbot_conversation_memory'] = $choice;
    check("$choice remembers $days days", wpiko_chatbot_conversation_memory_days(), $days);
    check("$choice keeps the server link $days days", wpiko_chatbot_chat_state_lifetime(), $days * DAY_IN_SECONDS);
}

// Filter is capped to what OpenAI keeps.
$options['wpiko_chatbot_conversation_memory'] = '7d';
add_filter('wpiko_chatbot_conversation_memory_days', function ($days) { return $GLOBALS['forced_days'] ?? $days; });
$forced_days = 90;
check('Filter above 30 days is capped at 30', wpiko_chatbot_conversation_memory_days(), 30);
$forced_days = -5;
check('Negative filter value means tab only', wpiko_chatbot_conversation_memory_days(), 0);
unset($forced_days);

// Data for the chat script.
$options['wpiko_chatbot_conversation_memory'] = 'tab';
$data = wpiko_chatbot_conversation_memory_script_data(array());
check('Script data: tab only', $data['conversation_memory'], array('persistent' => false, 'days' => 0));
$options['wpiko_chatbot_conversation_memory'] = '7d';
$guest = wpiko_chatbot_conversation_memory_script_data(array())['conversation_memory'];
check('Script data: across visits', array($guest['persistent'], $guest['days']), array(true, 7));
check('Account marker is 20 characters', strlen($guest['account']), 20);
$user_id = 12;
$member = wpiko_chatbot_conversation_memory_script_data(array())['conversation_memory'];
check('Account marker changes when someone logs in', $member['account'] !== $guest['account'], true);
$user_id = 0;
check('Guests share one account marker (cached pages)', wpiko_chatbot_conversation_memory_script_data(array())['conversation_memory']['account'], $guest['account']);

// Detecting an expired previous response.
$with_previous = array('input' => 'Hi', 'previous_response_id' => 'resp_old');
check('Error on the previous_response_id parameter', wpiko_chatbot_is_missing_previous_response_error(400, array('error' => array('param' => 'previous_response_id', 'message' => 'x')), $with_previous), true);
check('"Previous response ... not found" message', wpiko_chatbot_is_missing_previous_response_error(404, array('error' => array('message' => "Previous response with id 'resp_old' not found.")), $with_previous), true);
check('Raw body naming previous_response_id', wpiko_chatbot_is_missing_previous_response_error(400, 'Invalid previous_response_id', $with_previous), true);
check('Other 400 errors are not retried', wpiko_chatbot_is_missing_previous_response_error(400, array('error' => array('message' => 'Unsupported parameter: temperature')), $with_previous), false);
check('Server errors are not retried here', wpiko_chatbot_is_missing_previous_response_error(500, array('error' => array('message' => 'previous response')), $with_previous), false);
check('Requests without a previous response are not retried', wpiko_chatbot_is_missing_previous_response_error(400, array('error' => array('param' => 'previous_response_id')), array('input' => 'Hi')), false);

// Recovery request with recent messages.
$history_rows = array(
    row('user', 'Do you ship to Cyprus?'),
    row('assistant', '<p>Yes, we ship to <strong>Cyprus</strong> in 3&ndash;5 days.</p>'),
    row('admin', 'Hi, this is Maria from the team.'),
    row('error', 'Something went wrong.'),
    row('user', 'How much is shipping?'),
);
$recovered = wpiko_chatbot_prepare_history_recovery_request(array('model' => 'm', 'input' => 'How much is shipping?', 'previous_response_id' => 'resp_old'), 'resp_conv');
check('Recovery drops the expired previous_response_id', array_key_exists('previous_response_id', $recovered), false);
check('Recovery keeps other request fields', $recovered['model'], 'm');
check('Recovery queries this conversation only', $wpdb->last[1][0], 'resp_conv');
check('Recovery input has the earlier messages, oldest first, without HTML', $recovered['input'],
    "Earlier messages in this conversation (the visitor came back to it; use them as context):\n"
    . "Visitor: Do you ship to Cyprus?\n"
    . "Assistant: Yes, we ship to Cyprus in 3–5 days.\n"
    . "Team member: Hi, this is Maria from the team.\n\n"
    . "Latest user message:\nHow much is shipping?");

$history_rows = array(row('user', 'First message'));
$recovered = wpiko_chatbot_prepare_history_recovery_request(array('input' => 'First message', 'previous_response_id' => 'resp_old'), 'resp_conv');
check('Without earlier messages the input is unchanged', $recovered['input'], 'First message');

$history_rows = array();
for ($i = 1; $i <= 40; $i++) { $history_rows[] = row($i % 2 ? 'user' : 'assistant', str_repeat("Message $i ", 120)); }
$history = wpiko_chatbot_get_recent_history_text('resp_conv');
check('Long history stays within 6000 characters', mb_strlen($history) <= 6000 + 20, true);
check('Long messages are clipped', strpos($history, '…') !== false, true);
check('Most recent earlier message is kept', strpos($history, 'Message 39') !== false, true);

echo $failures ? "\n$failures check(s) failed\n" : "\nAll checks passed\n";
exit($failures ? 1 : 0);

<?php
// CLI-only tests for the AI cost of one conversation (Conversations inbox and
// Details panel): no WordPress, database or network.
// Run: php tests/conversation-cost.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
define('ABSPATH', '/tmp/');
define('WPIKO_CHATBOT_PLUGIN_FILE', dirname(__DIR__) . '/wpiko-chatbot.php');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('ARRAY_A', 'ARRAY_A');

$options = array('date_format' => 'F j, Y', 'wpiko_chatbot_usage_since' => '2026-11-09 10:00:00');
$pro = false;
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_action(...$args) {}
function do_action(...$args) {}
// Pro features count only while Pro is active; the test switches that with $pro.
function apply_filters($hook, $value, ...$args) {
    if ('wpiko_chatbot_usage_sources' === $hook && $GLOBALS['pro']) { $value['chat_tools'] = 'Order and product lookups'; }
    return $value;
}
function register_deactivation_hook(...$args) {}
function __($s, ...$args) { return $s; }
function _n($single, $plural, $count, ...$args) { return 1 === (int) $count ? $single : $plural; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function absint($n) { return abs((int) $n); }
function number_format_i18n($n, $d = 0) { return number_format($n, $d); }
function wp_timezone() { return new DateTimeZone('Asia/Nicosia'); }
function wp_date($format, $ts, $tz = null) { return (new DateTimeImmutable('@' . $ts))->setTimezone($tz ?: wp_timezone())->format($format); }
function wp_list_pluck($list, $field) { return array_map(function ($item) use ($field) { return $item->$field; }, $list); }
// Friendly model names, as in admin/includes/usage-widget.php.
function wpiko_chatbot_usage_model_labels($costs) { $out = array(); foreach ($costs as $model => $cost) { $out[$model === 'gpt-6-luna-2026-09-22' ? 'GPT-6 Luna' : $model] = $cost; } arsort($out); return $out; }

/** Minimal $wpdb for the per-conversation queries. */
class FakeWpdb {
    public $prefix = 'wp_';
    public $rows = array();
    public $queries = 0;
    public function suppress_errors($s = true) { return false; }
    public function prepare($query, $args) { return array('sql' => $query, 'args' => $args); }
    public function get_results($prepared, $output) {
        $this->queries++;
        $args = $prepared['args'];
        $by_session = strpos($prepared['sql'], 'WHERE session_id IN') !== false;
        $groups = array();
        foreach ($this->rows as $row) {
            if (!in_array($row['source'], $args, true)) { continue; }
            if ($by_session && !in_array($row['session_id'], $args, true)) { continue; }
            if (!$by_session && $row['session_id'] !== $args[0]) { continue; }
            if ($by_session) {
                $key = $row['session_id'];
                $groups[$key] = isset($groups[$key]) ? $groups[$key] : array('session_id' => $key, 'cost' => 0);
                $groups[$key]['cost'] += $row['token_cost'] + $row['search_cost'];
                continue;
            }
            $key = $row['source'] . '|' . $row['model'];
            if (!isset($groups[$key])) {
                $groups[$key] = array('source' => $row['source'], 'model' => $row['model'], 'calls' => 0, 'search_calls' => 0, 'input' => 0, 'output' => 0, 'token_cost' => 0, 'search_cost' => 0, 'unpriced' => 0);
            }
            $groups[$key]['calls']++;
            $groups[$key]['search_calls'] += $row['search_calls'];
            $groups[$key]['input'] += $row['input_tokens'];
            $groups[$key]['output'] += $row['output_tokens'];
            $groups[$key]['token_cost'] += $row['token_cost'];
            $groups[$key]['search_cost'] += $row['search_cost'];
            $groups[$key]['unpriced'] += $row['priced'] ? 0 : 1;
        }
        return array_values($groups);
    }
}
$wpdb = new FakeWpdb();

require dirname(__DIR__) . '/includes/usage-tracking.php';

$passed = 0;
function check($condition, $label) {
    global $passed;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $passed++;
}
function usage_row($session, $source, $token_cost, $searches = 0, $model = 'gpt-6-luna-2026-09-22', $priced = 1) {
    $GLOBALS['wpdb']->rows[] = array(
        'session_id' => $session, 'source' => $source, 'model' => $model, 'input_tokens' => 1000, 'output_tokens' => 100,
        'search_calls' => $searches, 'token_cost' => $token_cost, 'search_cost' => $searches * 0.0025, 'priced' => $priced,
    );
}

usage_row('a', 'chat', 0.0002, 1);
usage_row('a', 'chat', 0.0003, 0);
usage_row('a', 'chat_tools', 0.0001, 0);
usage_row('a', 'translation', 1.0, 0);          // admin feature, not part of the conversation
usage_row('b', 'chat', 0.00004, 0);
usage_row('', 'chat', 9.0, 0);                  // no session

/* ---- Inbox ---- */
$costs = wpiko_chatbot_usage_costs_by_session(array('a', 'b', 'c', 'a', ''));
check(abs($costs['a'] - 0.003) < 1e-9, 'without Pro, lookups are not counted: ' . $costs['a']);
check(abs($costs['b'] - 0.00004) < 1e-12 && !isset($costs['c']), 'conversations without usage are left out');
check(wpiko_chatbot_usage_costs_by_session(array()) === array(), 'empty list');
$GLOBALS['pro'] = true;
$costs = wpiko_chatbot_usage_costs_by_session(array('a'));
check(abs($costs['a'] - 0.0031) < 1e-9, 'with Pro, lookups count too');
$GLOBALS['pro'] = false;

/* ---- Details panel ---- */
$details = wpiko_chatbot_usage_conversation_details('a', '2026-11-10 08:00:00');
check($details['total'] === '$0.003', 'total: ' . $details['total']);
$rows = array();
foreach ($details['rows'] as $row) { $rows[$row['label']] = $row['value']; }
check($rows['AI replies'] === '2', 'replies');
check($rows['Knowledge searches'] === '1 ($0.0025)', 'knowledge searches with their cost: ' . $rows['Knowledge searches']);
check(!isset($rows['Order and product lookups']), 'no lookups row without Pro');
check($rows['Tokens'] === '2,000 in, 200 out', 'tokens: ' . $rows['Tokens']);
check($rows['Model'] === 'GPT-6 Luna', 'friendly model name');
check($details['note'] === 'Estimated with OpenAI list prices.', 'note');

$GLOBALS['pro'] = true;
$details = wpiko_chatbot_usage_conversation_details('a', '2026-11-10 08:00:00');
$rows = array();
foreach ($details['rows'] as $row) { $rows[$row['label']] = $row['value']; }
check($rows['Order and product lookups'] === '1' && $rows['AI replies'] === '2' && $details['total'] === '$0.0031', 'with Pro: lookups row');
$GLOBALS['pro'] = false;

// Started before tracking (site time 2026-11-09 12:00 = 10:00 UTC).
$details = wpiko_chatbot_usage_conversation_details('c', '2026-11-08 09:00:00');
check($details['total'] === 'Not tracked' && $details['rows'] === array() && $details['note'] === 'Costs are counted from November 9, 2026.', 'older conversation: Not tracked');
$details = wpiko_chatbot_usage_conversation_details('c', '2026-11-12 09:00:00');
check($details['total'] === '$0.00' && $details['note'] === 'No AI cost recorded for this conversation.', 'newer conversation without usage (for example only errors or a team member replied)');
$details = wpiko_chatbot_usage_conversation_details('b', '2026-11-09 11:30:00');
check(strpos($details['note'], 'Only replies from November 9, 2026 are counted.') !== false && $details['total'] === '$0.00004', 'conversation that started before tracking but continued after it');

// Unpriced model.
usage_row('d', 'chat', 0, 0, 'my-custom-model', 0);
$details = wpiko_chatbot_usage_conversation_details('d', '2026-11-12 09:00:00');
$rows = array();
foreach ($details['rows'] as $row) { $rows[$row['label']] = $row['value']; }
check(strpos($details['note'], 'without a known price') !== false && $rows['Model'] === 'my-custom-model', 'unpriced model mentioned');
check(wpiko_chatbot_usage_conversation_totals('') === null, 'no session');

echo "All $passed checks passed.\n";

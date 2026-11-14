<?php
// CLI-only tests for AI usage & cost tracking: no WordPress, database or network.
// Run: php tests/usage-tracking.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
define('ABSPATH', '/tmp/');
define('WPIKO_CHATBOT_PLUGIN_FILE', dirname(__DIR__) . '/wpiko-chatbot.php');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('ARRAY_A', 'ARRAY_A');

$options = array();
$actions = array();
$filters = array();
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['actions'][$hook][] = $callback; }
function do_action(...$args) {}
function apply_filters($hook, $value, ...$args) { return isset($GLOBALS['filters'][$hook]) ? call_user_func($GLOBALS['filters'][$hook], $value, ...$args) : $value; }
function register_deactivation_hook(...$args) {}
function __($s, ...$args) { return $s; }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function absint($n) { return abs((int) $n); }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
function number_format_i18n($n, $d = 0) { return number_format($n, $d); }
function wp_timezone() { return new DateTimeZone('Asia/Nicosia'); }
function wp_date($format, $ts, $tz = null) { return (new DateTimeImmutable('@' . $ts))->setTimezone($tz ?: wp_timezone())->format($format); }

/** Minimal $wpdb: stores inserted rows and answers the two report queries. */
class FakeWpdb {
    public $prefix = 'wp_';
    public $rows = array();
    public $fail_insert = false;
    public function suppress_errors($s = true) { return false; }
    public function prepare($query, $args) { return array('sql' => $query, 'args' => $args); }
    public function insert($table, $row, $formats) {
        if ($this->fail_insert) { return false; }
        $this->rows[] = $row;
        return 1;
    }
    private function matching($prepared) {
        list($start, $end) = $prepared['args'];
        $sources = array_slice($prepared['args'], 2);
        return array_filter($this->rows, function ($row) use ($start, $end, $sources) {
            return $row['created_at'] >= $start && $row['created_at'] < $end && in_array($row['source'], $sources, true);
        });
    }
    public function get_results($prepared, $output) {
        $groups = array();
        $by_model = strpos($prepared['sql'], 'GROUP BY model') !== false;
        foreach ($this->matching($prepared) as $row) {
            $key = $by_model ? $row['model'] : $row['source'];
            if (!isset($groups[$key])) {
                $groups[$key] = $by_model
                    ? array('model' => $key, 'cost' => 0)
                    : array('source' => $key, 'calls' => 0, 'input' => 0, 'cached' => 0, 'output' => 0, 'reasoning' => 0, 'search_calls' => 0, 'token_cost' => 0, 'search_cost' => 0, 'unpriced' => 0);
            }
            if ($by_model) {
                $groups[$key]['cost'] += $row['token_cost'] + $row['search_cost'];
                continue;
            }
            $groups[$key]['calls']++;
            foreach (array('input' => 'input_tokens', 'cached' => 'cached_tokens', 'output' => 'output_tokens', 'reasoning' => 'reasoning_tokens', 'search_calls' => 'search_calls', 'token_cost' => 'token_cost', 'search_cost' => 'search_cost') as $to => $from) {
                $groups[$key][$to] += $row[$from];
            }
            $groups[$key]['unpriced'] += $row['priced'] ? 0 : 1;
        }
        return array_values($groups);
    }
    public function get_var($prepared) {
        $sessions = array();
        foreach ($this->matching($prepared) as $row) {
            if ($row['session_id'] !== '') { $sessions[$row['session_id']] = true; }
        }
        return count($sessions);
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
function near($a, $b) { return abs($a - $b) < 1e-9; }
function response($model, $input, $cached, $output, $reasoning = 0, $searches = 0) {
    $out = array();
    for ($i = 0; $i < $searches; $i++) { $out[] = array('type' => 'file_search_call', 'id' => 'fs_' . $i); }
    $out[] = array('type' => 'message', 'content' => array(array('type' => 'output_text', 'text' => 'Hi')));
    return array('id' => 'resp_' . $model . $input, 'model' => $model, 'output' => $out, 'usage' => array(
        'input_tokens' => $input, 'input_tokens_details' => array('cached_tokens' => $cached),
        'output_tokens' => $output, 'output_tokens_details' => array('reasoning_tokens' => $reasoning),
    ));
}

// Price lookup.
check(wpiko_chatbot_usage_price_key('gpt-6-luna') === 'gpt-6-luna', 'exact model');
check(wpiko_chatbot_usage_price_key('gpt-6-luna-2026-09-22') === 'gpt-6-luna', 'dated snapshot');
check(wpiko_chatbot_usage_price_key('gpt-4.1-mini-2025-04-14') === 'gpt-4.1-mini', 'longest prefix wins');
check(wpiko_chatbot_usage_price_key('gpt-4.1-2025-04-14') === 'gpt-4.1', 'base model snapshot');
check(wpiko_chatbot_usage_price_key('gpt-6.1-sol') === 'gpt-6.1-sol' && wpiko_chatbot_usage_price_key('gpt-6-sol-2026') === 'gpt-6-sol', 'similar names stay apart');
check(wpiko_chatbot_usage_price_key('gpt-6-lunatic') === '' && wpiko_chatbot_usage_price_key('') === '', 'unknown model');
check(wpiko_chatbot_usage_model_rates('gpt-5.6-sol', strtotime('2026-11-30 23:00 UTC'))['input'] === 4.0, 'promo applies on its last day');
check(wpiko_chatbot_usage_model_rates('gpt-5.6-sol', strtotime('2026-12-01 00:30 UTC'))['output'] === 30.0, 'promo ends');
check(wpiko_chatbot_usage_model_rates('unknown') === null, 'no rates for unknown model');
check(wpiko_chatbot_usage_model_rates('gpt-5.6-sol', strtotime('2026-11-30 23:00 UTC'))['write_30m'] === 5.0, 'promo write pricing applies on its last day');
check(wpiko_chatbot_usage_model_rates('gpt-5.6-sol', strtotime('2026-12-01 00:30 UTC'))['write_12h'] === 12.5, 'write pricing changes when the promo ends');

// Cost.
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 1000000, 'cached' => 400000, 'output' => 200000), 4);
check(near($cost['token_cost'], 0.6 * 0.10 + 0.4 * 0.01 + 0.2 * 0.50), 'token cost with cached input');
check(near($cost['search_cost'], 0.01) && $cost['priced'], 'file search cost');
$cost = wpiko_chatbot_usage_calculate_cost('mystery', array('input' => 500, 'output' => 50), 1);
check($cost['token_cost'] === 0.0 && !$cost['priced'] && near($cost['search_cost'], 0.0025), 'unknown model is unpriced');
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 100, 'cached' => 900, 'output' => 0));
check(near($cost['token_cost'], 100 * 0.01 / 1000000), 'cached tokens never exceed input');
$filters['wpiko_chatbot_model_prices'] = function ($prices) { $prices['my-model'] = array('input' => 1, 'output' => 2); return $prices; };
check(near(wpiko_chatbot_usage_calculate_cost('my-model', array('input' => 1000000, 'cached' => 500000, 'output' => 0))['token_cost'], 1.0), 'filtered price, cached defaults to input price');
unset($filters['wpiko_chatbot_model_prices']);

// Cache writes replace ordinary input charges, and are separate from cache reads.
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna-2026-09-22', array('input' => 1000000, 'write_30m' => 1000000));
check(near($cost['token_cost'], 0.125), '30m writes use the full write rate, without adding ordinary input again');
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 1000000, 'write_12h' => 1000000));
check(near($cost['token_cost'], 0.25), '12h writes use their own rate');
$mixed_tokens = array('input' => 12000, 'cached' => 4000, 'write_30m' => 3000, 'write_12h' => 4000, 'output' => 2000);
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6.1-terra', $mixed_tokens, 2);
// 1,000 ordinary + 4,000 reads + 3,000 short writes + 4,000 long writes + output.
check(near($cost['token_cost'], 0.02495) && near($cost['search_cost'], 0.005), 'mixed writes, reads, ordinary input, output and file search are each charged once');
$writes_only = array('input' => 2000000, 'write_30m' => 1000000, 'write_12h' => 1000000);
check(near(wpiko_chatbot_usage_calculate_cost('gpt-5.6-sol', $writes_only, 0, strtotime('2026-11-30 23:59 UTC'))['token_cost'], 15), 'write cost uses promotional rates');
check(near(wpiko_chatbot_usage_calculate_cost('gpt-5.6-sol', $writes_only, 0, strtotime('2026-12-01 00:00 UTC'))['token_cost'], 18.75), 'write cost uses regular rates after expiry');
check(near(wpiko_chatbot_usage_calculate_cost('gpt-4.1-mini', $mixed_tokens)['token_cost'], 0.0068), 'older models have no cache-write premium');
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 100, 'cached' => 40, 'write_30m' => 900, 'write_12h' => 20));
check(near($cost['token_cost'], 0.0000104), 'oversized writes cannot charge more tokens than total input');
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 100, 'write_30m' => -50, 'write_12h' => -20));
check(near($cost['token_cost'], 0.00001), 'negative write counts are ignored');
$cost = wpiko_chatbot_usage_calculate_cost('gpt-6-luna', array('input' => 100, 'cached' => 900, 'write_30m' => 900, 'write_12h' => 900));
check(near($cost['token_cost'], 0.000001), 'cached reads leave no room for overlapping writes');
$cost = wpiko_chatbot_usage_calculate_cost('mystery', $mixed_tokens, 2);
check(!$cost['priced'] && $cost['token_cost'] === 0.0 && near($cost['search_cost'], 0.005), 'write counts do not turn an unknown model into a priced model');
$filters['wpiko_chatbot_model_prices'] = function ($prices) {
    $prices['my-model'] = array('input' => 1, 'cached' => 0.1, 'output' => 2, 'write_30m' => 1.25, 'write_12h' => 2.5);
    return $prices;
};
check(near(wpiko_chatbot_usage_calculate_cost('my-model', $mixed_tokens)['token_cost'], 0.01915), 'custom filtered write prices follow the documented mixed-lifetime calculation');
unset($filters['wpiko_chatbot_model_prices']);

// Reading usage.
$usage = wpiko_chatbot_usage_from_response(response('gpt-6-luna-2026-09-22', 1200, 1000, 300, 120, 2));
check($usage['input'] === 1200 && $usage['cached'] === 1000 && $usage['output'] === 300 && $usage['reasoning'] === 120 && $usage['search_calls'] === 2, 'usage fields');
check(wpiko_chatbot_usage_from_response(array('id' => 'x')) === null && wpiko_chatbot_usage_from_response('text') === null, 'no usage');
check($usage['write_30m'] === 0 && $usage['write_12h'] === 0, 'responses without cache-write fields remain compatible');
$write_response = response('gpt-6.1-terra', 12000, 4000, 2000, 0, 2);
$write_response['usage']['input_tokens_details'] += array(
    'cache_write_tokens' => 7000,
    'cache_write_tokens_details' => array('ttl_30m_tokens' => 3000, 'ttl_12h_tokens' => 4000),
);
$usage = wpiko_chatbot_usage_from_response($write_response);
check($usage['write_30m'] === 3000 && $usage['write_12h'] === 4000 && $usage['cached'] === 4000, 'API usage preserves non-overlapping writes by lifetime');
check(near(wpiko_chatbot_usage_calculate_cost($usage['model'], $usage)['token_cost'], 0.02495), 'API usage calculates the mixed-lifetime cost without double-counting total writes');
$total_only = $write_response;
unset($total_only['usage']['input_tokens_details']['cache_write_tokens_details']);
$usage = wpiko_chatbot_usage_from_response($total_only);
check($usage['write_30m'] === 7000 && $usage['write_12h'] === 0, 'aggregate-only writes use our default 30m lifetime');
$only_details = $write_response;
unset($only_details['usage']['input_tokens_details']['cache_write_tokens']);
$usage = wpiko_chatbot_usage_from_response($only_details);
check($usage['write_30m'] === 3000 && $usage['write_12h'] === 4000, 'lifetime counts work without an aggregate write count');
$no_details = response('gpt-4.1', 100, 0, 10);
unset($no_details['usage']['input_tokens_details']);
$usage = wpiko_chatbot_usage_from_response($no_details);
check($usage['cached'] === 0 && $usage['write_30m'] === 0 && $usage['write_12h'] === 0, 'missing input detail objects have no cache charges');
$invalid_writes = $write_response;
$invalid_writes['usage']['input_tokens_details']['cache_write_tokens_details'] = array('ttl_30m_tokens' => -3000, 'ttl_12h_tokens' => 99000);
$usage = wpiko_chatbot_usage_from_response($invalid_writes);
check($usage['write_30m'] === 0 && $usage['write_12h'] === 8000, 'API write counts are nonnegative and bounded by input minus reads');

// Context.
check(wpiko_chatbot_usage_context() === null, 'no context by default');
check(wpiko_chatbot_record_usage(response('gpt-6-luna', 10, 0, 5)) === false && count($wpdb->rows) === 0, 'nothing recorded without context');
$outer = wpiko_chatbot_usage_begin('chat', 'session-a');
check($outer === null && wpiko_chatbot_usage_context()['source'] === 'chat', 'begin');
$inner = wpiko_chatbot_usage_begin('chat_tools', 'session-a');
check(wpiko_chatbot_usage_context()['source'] === 'chat_tools', 'nested begin');
wpiko_chatbot_usage_end($inner);
check(wpiko_chatbot_usage_context()['source'] === 'chat' && wpiko_chatbot_usage_context()['session_id'] === 'session-a', 'end restores outer context');

// Recording.
check(wpiko_chatbot_record_usage(response('gpt-6-luna-2026-09-22', 2000, 1500, 400, 100, 1)) === true, 'chat reply recorded');
$row = $wpdb->rows[0];
check($row['source'] === 'chat' && $row['session_id'] === 'session-a' && $row['model'] === 'gpt-6-luna-2026-09-22' && $row['priced'] === 1, 'row fields');
check(near($row['token_cost'], round((500 * 0.10 + 1500 * 0.01 + 400 * 0.50) / 1000000, 8)) && near($row['search_cost'], 0.0025), 'row cost');
check(!array_key_exists('message', $row) && !array_key_exists('input', $row), 'no message text stored');
check(wpiko_chatbot_record_usage(response('gpt-6-luna', 0, 0, 0)) === false, 'empty usage skipped');
$wpdb->fail_insert = true;
check(wpiko_chatbot_record_usage(response('gpt-6-luna', 10, 0, 5)) === false, 'insert failure does not throw');
$wpdb->fail_insert = false;
$bad = response('', 30, 0, 10);
check(wpiko_chatbot_record_usage($bad, null, 'gpt-6-luna') && end($wpdb->rows)['model'] === 'gpt-6-luna', 'request model used when the response has none');
array_pop($wpdb->rows);
$weird = response('gpt-6-luna<script>', 30, 0, 10);
wpiko_chatbot_record_usage($weird);
check(end($wpdb->rows)['model'] === 'gpt-6-lunascript', 'model ID sanitized');
array_pop($wpdb->rows);

// HTTP capture.
$http = function ($body, $args = array(), $url = WPIKO_CHATBOT_RESPONSES_ENDPOINT, $context = 'response') {
    $args = array_merge(array('method' => 'POST', 'body' => json_encode(array('model' => 'gpt-6-luna'))), $args);
    wpiko_chatbot_usage_capture_http_response(array('body' => json_encode($body), 'response' => array('code' => 200)), $context, 'Requests', $args, $url);
};
$count = count($wpdb->rows);
$http(response('gpt-6-luna', 50, 0, 10), array(), 'https://api.openai.com/v1/files');
$http(response('gpt-6-luna', 50, 0, 10), array('method' => 'GET'));
$http(response('gpt-6-luna', 50, 0, 10), array('body' => json_encode(array('model' => 'gpt-6-luna', 'stream' => true))));
$http(response('gpt-6-luna', 50, 0, 10), array(), WPIKO_CHATBOT_RESPONSES_ENDPOINT, 'transports_list');
check(count($wpdb->rows) === $count, 'other URLs, GET, streams and other hook contexts are skipped');
wpiko_chatbot_usage_capture_http_response(new stdClass(), 'response', 'Requests', array('method' => 'POST'), WPIKO_CHATBOT_RESPONSES_ENDPOINT);
check(count($wpdb->rows) === $count, 'WP_Error-like responses skipped');
$http(response('gpt-6-luna', 50, 0, 10));
check(count($wpdb->rows) === $count + 1 && end($wpdb->rows)['source'] === 'chat', 'POST to Responses recorded');
$http($write_response);
$write_row = array_pop($wpdb->rows);
check(near($write_row['token_cost'], 0.02495) && near($write_row['search_cost'], 0.005), 'HTTP capture stores the corrected cost for reports and budget checks');
check($write_row['input_tokens'] === 12000 && $write_row['cached_tokens'] === 4000 && $write_row['output_tokens'] === 2000, 'saved total input already includes writes');
check(array_keys($write_row) === array_keys($row), 'write accounting works with the existing usage table');
$previous = wpiko_chatbot_usage_context(null);
$http(response('gpt-6-luna', 50, 0, 10));
check(count($wpdb->rows) === $count + 1, 'other plugins (no context) never counted');
wpiko_chatbot_usage_context($previous);
check(in_array('wpiko_chatbot_usage_capture_http_response', $actions['http_api_debug'], true), 'hooked to http_api_debug');

// Formatting.
check(wpiko_chatbot_format_usage_cost(0) === '$0.00', 'zero');
check(wpiko_chatbot_format_usage_cost(0.00004) === '< $0.0001', 'tiny');
check(wpiko_chatbot_format_usage_cost(0.0042) === '$0.0042' && wpiko_chatbot_format_usage_cost(0.004) === '$0.004', 'under a cent');
check(wpiko_chatbot_format_usage_cost(0.236) === '$0.24' && wpiko_chatbot_format_usage_cost(1234.5) === '$1,234.50', 'cents and thousands');
check(wpiko_chatbot_format_usage_cost(0.0000567, true) === '$0.000057' && wpiko_chatbot_format_usage_cost(0.00042, true) === '$0.00042', 'precise averages keep two significant digits');
check(wpiko_chatbot_format_usage_cost(0.0042, true) === '$0.0042' && wpiko_chatbot_format_usage_cost(0.5, true) === '$0.50', 'precise has no effect from 0.001');
check(wpiko_chatbot_format_usage_cost(0.000000001, true) === '< $0.00000001', 'precise floor');

// Sources: Pro features only while Pro is active.
check(!isset(wpiko_chatbot_usage_sources()['qa_generation']) && wpiko_chatbot_usage_conversation_sources() === array('chat'), 'free sources only without Pro');

// Reports.
$wpdb->rows = array();
$add = function ($when, $source, $session, $model, $token_cost, $search_cost = 0, $priced = 1, $searches = 0) use ($wpdb) {
    $wpdb->rows[] = array('created_at' => $when, 'source' => $source, 'model' => $model, 'session_id' => $session, 'response_id' => '',
        'input_tokens' => 1000, 'cached_tokens' => 200, 'output_tokens' => 100, 'reasoning_tokens' => 20, 'search_calls' => $searches,
        'token_cost' => $token_cost, 'search_cost' => $search_cost, 'priced' => $priced);
};
// Site time Asia/Nicosia (UTC+2). "Now" = Nov 9, 2026 10:00 local = 08:00 UTC.
$add('2026-11-09 07:00:00', 'chat', 's1', 'gpt-6-luna', 0.002, 0.0025, 1, 1);  // today
$add('2026-11-09 07:30:00', 'chat', 's1', 'gpt-6-luna', 0.001);                   // today, same session
$add('2026-11-08 21:30:00', 'chat', 's2', 'gpt-6-luna', 0.003);                   // Nov 8 23:30 local: yesterday
$add('2026-11-08 22:30:00', 'translation', '', 'gpt-6-luna', 0.010);              // Nov 9 00:30 local: today
$add('2026-11-02 09:00:00', 'chat', 's3', 'gpt-5.6-sol', 0.050);                  // this month, not last 7 days
$add('2026-11-03 09:00:00', 'qa_generation', '', 'gpt-6.1-terra', 1.000);         // Pro source: excluded without Pro
$add('2026-11-03 10:00:00', 'chat_tools', 's3', 'gpt-6-luna', 0.004);              // Pro source: excluded without Pro
$add('2026-11-04 10:00:00', 'chat', 's4', 'mystery', 0, 0, 0);                     // unpriced
$add('2026-10-31 21:30:00', 'chat', 's5', 'gpt-6-luna', 0.200);                   // Oct 31 23:30 local: last month
$add('2026-10-31 22:30:00', 'chat', 's6', 'gpt-6-luna', 0.300);                   // Nov 1 00:30 local: this month
$add('2026-11-04 10:00:00', 'other_plugin', 's7', 'gpt-6-luna', 9.000);            // unknown source: never counted
$options['wpiko_chatbot_usage_since'] = '2026-10-01 00:00:00';
$now = new DateTimeImmutable('2026-11-09 08:00:00', new DateTimeZone('UTC'));

$snapshot = wpiko_chatbot_get_usage_snapshot($now);
check(near($snapshot['today']['cost'], 0.002 + 0.0025 + 0.001 + 0.010), 'today uses site timezone');
check($snapshot['today']['replies'] === 2 && $snapshot['today']['conversations'] === 1, 'today replies and conversations');
check(near($snapshot['week']['cost'], 0.0155 + 0.003), 'last 7 days');
check(near($snapshot['month']['cost'], 0.0155 + 0.003 + 0.050 + 0.300), 'month excludes Pro sources without Pro and unknown sources');
check(!isset($snapshot['month']['sources']['qa_generation']) && !isset($snapshot['month']['sources']['other_plugin']), 'excluded sources hidden');
check($snapshot['month']['unpriced'] === 1 && $snapshot['month']['search_calls'] === 1 && near($snapshot['month']['search_cost'], 0.0025), 'unpriced and search totals');
check($snapshot['month']['conversations'] === 5 && $snapshot['month']['replies'] === 6, 'month conversations and replies');
check(is_array($snapshot['last_month']) && near($snapshot['last_month']['cost'], 0.200), 'last month');
check($snapshot['month_label'] === 'November 2026' && $snapshot['last_month_label'] === 'October 2026', 'month labels');
$expected_projection = $snapshot['month']['cost'] + $snapshot['month']['cost'] / ((strtotime('2026-11-09 08:00 UTC') - strtotime('2026-10-31 22:00 UTC')) / DAY_IN_SECONDS) * ((strtotime('2026-11-30 22:00 UTC') - strtotime('2026-11-09 08:00 UTC')) / DAY_IN_SECONDS);
check(near($snapshot['projected'], $expected_projection), 'projection from the daily rate');
check(array_keys($snapshot['models'])[0] === 'gpt-6-luna' && near($snapshot['models']['gpt-5.6-sol'], 0.05), 'costs by model');
check($snapshot['partial'] === true, 'last month started before tracking: the tracking note is shown');
$options['wpiko_chatbot_usage_since'] = '2026-09-20 00:00:00';
check(wpiko_chatbot_get_usage_snapshot($now)['partial'] === false, 'every period shown is fully tracked: no tracking note');
$options['wpiko_chatbot_usage_since'] = '2026-10-01 00:00:00';

// Tracking started this month: no last month, projection from the start date.
$options['wpiko_chatbot_usage_since'] = '2026-11-08 08:00:00';
$snapshot = wpiko_chatbot_get_usage_snapshot($now);
check($snapshot['last_month'] === null && $snapshot['projected'] === null, 'no last month or projection with one day of data');
check($snapshot['partial'] === true, 'tracking started this month: the tracking note is shown');

// Pro active: Pro sources are counted and conversation averages include lookups.
define('WPIKO_CHATBOT_PRO_VERSION', 'test');
$options['wpiko_chatbot_usage_since'] = '2026-10-01 00:00:00';
$snapshot = wpiko_chatbot_get_usage_snapshot($now);
check(near($snapshot['month']['cost'], 0.3685 + 1.004), 'Pro sources counted with Pro');
check(isset($snapshot['month']['sources']['qa_generation']) && near($snapshot['month']['conversation_cost'], 0.3585 + 0.004), 'conversation cost includes lookups');
check(array_keys($snapshot['month']['sources'])[0] === 'chat', 'sources keep their order');

// Reports consume the saved cost, including writes, without repricing older usage.
$historical_row = $wpdb->rows[0];
$wpdb->rows = array($historical_row);
$write_row['created_at'] = $historical_row['created_at'];
$wpdb->rows[] = $write_row;
$totals = wpiko_chatbot_usage_totals('2026-11-01 00:00:00', '2026-12-01 00:00:00');
check(near($totals['token_cost'], $historical_row['token_cost'] + 0.02495) && near($totals['search_cost'], $historical_row['search_cost'] + 0.005), 'report totals include the corrected saved cost');
check($wpdb->rows[0] === $historical_row, 'historical usage is not recalculated');

echo "PASS: $passed usage tracking assertions; no network calls.\n";

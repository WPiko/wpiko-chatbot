<?php
// CLI-only regression tests: no real credentials, WordPress DB, or network.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
define('ABSPATH', '/tmp/');
define('WPIKO_CHATBOT_PLUGIN_FILE', dirname(__DIR__) . '/wpiko-chatbot.php');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
$options = array();
$bypass = false;
$stream = false;
$logged_in = false;
function get_option($key, $default = false) { return isset($GLOBALS['options'][$key]) ? $GLOBALS['options'][$key] : $default; }
function apply_filters($hook, $value, ...$args) { return $hook === 'wpiko_chatbot_user_limit_bypass' ? $GLOBALS['bypass'] : $value; }
function add_action(...$args) {}
function add_filter(...$args) {}
function register_deactivation_hook(...$args) {}
function do_action(...$args) {}
function __($s, ...$args) { return $s; }
function wp_salt(...$args) { return 'test-only-salt'; }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function sanitize_textarea_field($s) { return trim(strip_tags($s)); }
function sanitize_email($s) { return $s; }
function wp_unslash($s) { return $s; }
function check_ajax_referer($action, $field) { if (($_POST[$field] ?? '') !== 'public-nonce') throw new RejectedRequest('nonce'); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_current_user_id() { return $GLOBALS['logged_in'] ? 7 : 0; }
function wp_get_current_user() { return (object) array('user_email' => 'test@example.test'); }
function wp_generate_password(...$args) { return 'test-session'; }
function wpiko_chatbot_decrypt_api_key($s) { return $s; }
function wpiko_chatbot_combine_responses_instructions(...$args) { return ''; }
function wpiko_chatbot_save_message(...$args) {}
function wpiko_chatbot_save_error_message(...$args) { throw new RuntimeException('Rejected traffic must not be saved'); }
function wpiko_chatbot_log(...$args) {}
function wpiko_chatbot_is_streaming_request() { return $GLOBALS['stream']; }
function wpiko_chatbot_send_stream_headers() {}
function wpiko_chatbot_send_stream_event($event, $data) { throw new RejectedRequest($data['type']); }
class InterceptedOutbound extends Exception {}
class RejectedRequest extends Exception {}
function wp_send_json_error($data, $status = null) {
    if ($data['type'] === 'rate_limit' && $status !== 429) throw new RuntimeException('Expected HTTP 429');
    throw new RejectedRequest($data['type']);
}
function wpiko_chatbot_api_call_with_retry($url, $args) {
    check($url === 'https://api.openai.com/v1/responses', 'correct API URL');
    check($args['headers']['Authorization'] === 'Bearer FAKE-KEY', 'fake credential only');
    check(json_decode($args['body'], true)['max_output_tokens'] === 8192, 'normal output bound');
    throw new InterceptedOutbound();
}
// Models storage behavior; real SQL/concurrency is covered by rate-limits-db.php.
$wpdb = new class {
    public $prefix = 'test_';
    public $rows = array();
    public $fail = false;
    function suppress_errors($value) { return false; }
    function prepare($sql, ...$args) { return array($sql, $args); }
    function query($prepared) {
        if ($this->fail) return false;
        list($sql, $a) = $prepared;
        if (strpos($sql, 'INSERT') === 0) {
            if (!isset($this->rows[$a[0]])) $this->rows[$a[0]] = array(0, $a[1]);
            return 1;
        }
        list($now, $unused, $expiry, $key, $unused2, $limit) = $a;
        $row = &$this->rows[$key];
        if ($row[1] <= $now) { $row = array(1, $expiry); return 1; }
        if ($row[0] >= $limit) return 0;
        $row[0]++;
        return 1;
    }
};
require dirname(__DIR__) . '/includes/user-limits.php';
require dirname(__DIR__) . '/includes/responses-tools.php';
require dirname(__DIR__) . '/includes/responses-api.php';
$source = file_get_contents(WPIKO_CHATBOT_PLUGIN_FILE);
$start = strpos($source, 'function wpiko_chatbot_ajax_handler()');
$end = strpos($source, "add_action('wp_ajax_wpiko_chatbot_send_message'", $start);
eval(substr($source, $start, $end - $start));
$count = 0;
function check($condition, $label) {
    $GLOBALS['count']++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
function reset_case($settings = array()) {
    $GLOBALS['options'] = $settings + array('wpiko_chatbot_api_key' => 'FAKE-KEY');
    $GLOBALS['wpdb']->rows = array();
    $GLOBALS['wpdb']->fail = false;
    $GLOBALS['bypass'] = $GLOBALS['stream'] = $GLOBALS['logged_in'] = false;
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    $_POST = array('security' => 'public-nonce', 'message' => 'Test');
}
function allowed() { return wpiko_chatbot_user_limits_check_and_record()['allowed']; }
function expire_scope($scope, $ip = '192.0.2.10') {
    $key = hash_hmac('sha256', $scope . ':' . bin2hex(inet_pton($ip)), wp_salt('auth'));
    if (isset($GLOBALS['wpdb']->rows[$key])) $GLOBALS['wpdb']->rows[$key][1] = 0;
}
function expect_handler($type) {
    try { wpiko_chatbot_ajax_handler(); throw new RuntimeException('Handler did not terminate'); }
    catch (InterceptedOutbound $e) { check($type === 'outbound', 'allowed handler reaches intercepted API'); }
    catch (RejectedRequest $e) { check($type === $e->getMessage(), 'handler rejection: ' . $type); }
}
reset_case();
check(wpiko_chatbot_user_limits_enabled(), 'new install defaults on');
for ($i = 0; $i < 20; $i++) { expire_scope('minute'); check(allowed(), 'default hourly capacity'); }
expire_scope('minute'); check(!allowed(), 'default hourly cap');
reset_case();
for ($i = 0; $i < 100; $i++) { expire_scope('minute'); expire_scope('hour'); check(allowed(), 'default daily capacity'); }
expire_scope('minute'); expire_scope('hour'); check(!allowed(), 'default daily cap');
foreach (array(array('wpiko_chatbot_user_limits_enabled' => '0'), array('wpiko_chatbot_user_limits_hourly' => 0, 'wpiko_chatbot_user_limits_daily' => 0)) as $settings) {
    reset_case($settings);
    for ($i = 0; $i < 10; $i++) expect_handler('outbound');
    expect_handler('rate_limit');
    $stream = true; expect_handler('rate_limit');
    $logged_in = true; expect_handler('rate_limit');
    expire_scope('minute'); check(allowed(), 'expired burst resets');
    for ($i = 11; $i < 60; $i++) { expire_scope('minute'); check(allowed(), 'hard hourly capacity'); }
    expire_scope('minute'); check(!allowed(), 'hard hourly cap survives opt out/zeros');
    for ($i = 60; $i < 300; $i++) { expire_scope('minute'); expire_scope('hour'); check(allowed(), 'hard daily capacity'); }
    expire_scope('minute'); expire_scope('hour'); check(!allowed(), 'hard daily cap survives opt out/zeros');
}
reset_case(array('wpiko_chatbot_user_limits_hourly' => 2));
check(allowed() && allowed() && !allowed(), 'custom tighter cap');
$bypass = true;
for ($i = 0; $i < 7; $i++) check(allowed(), 'takeover may bypass custom cap');
check(!allowed(), 'takeover cannot bypass safety cap');
reset_case(array('wpiko_chatbot_user_limits_site_daily' => 3));
for ($i = 1; $i <= 4; $i++) { $_SERVER['REMOTE_ADDR'] = '192.0.2.' . $i; check(allowed() === ($i <= 3), 'global cap covers rotating IPs'); }
reset_case(array('wpiko_chatbot_user_limits_site_daily' => 0));
check(allowed(), 'zero site setting uses default');
$key = hash_hmac('sha256', 'site_day:all', wp_salt('auth'));
$wpdb->rows[$key][0] = 1000;
check(!allowed(), 'zero site setting never disables protection');
reset_case();
$_SERVER['REMOTE_ADDR'] = ''; check(!allowed(), 'missing IP fails closed');
$_SERVER['REMOTE_ADDR'] = 'invalid'; check(!allowed(), 'invalid IP fails closed');
reset_case(); $wpdb->fail = true; expect_handler('rate_limit');
reset_case(); $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
for ($i = 0; $i < 10; $i++) check(allowed(), 'normal client');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.2'; check(!allowed(), 'untrusted proxy header cannot rotate bucket');
$_SERVER['REMOTE_ADDR'] = '::ffff:192.0.2.10'; check(!allowed(), 'IPv4 mapped address shares bucket');
reset_case(); $_SERVER['REMOTE_ADDR'] = '2001:db8::1';
for ($i = 0; $i < 10; $i++) check(allowed(), 'IPv6 client');
$_SERVER['REMOTE_ADDR'] = '2001:0db8:0:0:0:0:0:1'; check(!allowed(), 'IPv6 spelling cannot rotate bucket');
reset_case(); $_POST['security'] = 'invalid'; expect_handler('nonce');
foreach (array('', array('bad'), str_repeat('x', 16001)) as $bad) {
    reset_case(); $_POST['message'] = $bad; expect_handler('input_error');
    check(!$wpdb->rows, 'invalid input does not consume capacity');
    $stream = true; expect_handler('input_error');
}
reset_case(); $_POST['message'] = str_repeat('x', 16000); expect_handler('outbound');
$context = wpiko_chatbot_build_responses_request_context('Test', null, null);
check($context['request_body']['max_output_tokens'] === 8192, 'streaming output bound');
echo "PASS: $count rate-limit and handler assertions; no network calls.\n";

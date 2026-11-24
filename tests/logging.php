<?php
// CLI-only regression test for the debug log size limits.
// No WordPress, database or network needed. Run: php tests/logging.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');

$options = array('wpiko_chatbot_debug_logging' => '1');
$filters = array();
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); return true; }
function add_filter($name, $fn, $priority = 10) { $GLOBALS['filters'][$name][] = $fn; }
function apply_filters($name, $value, ...$args) { foreach ($GLOBALS['filters'][$name] ?? array() as $fn) { $value = $fn($value, ...$args); } return $value; }
function current_time($type) { return '2026-11-24 12:00:00'; }
function wp_json_encode($value) { return json_encode($value); }
function size_format($bytes, $decimals = 0) { return round($bytes / 1024, $decimals) . ' KB'; }
function add_action(...$args) {}

require dirname(__DIR__) . '/includes/logging.php';

$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}
function last_message() { $logs = get_option('wpiko_chatbot_debug_logs', array()); return end($logs)['message']; }

wpiko_chatbot_log('Short message');
check('Short messages are kept unchanged', last_message(), 'Short message');

wpiko_chatbot_log(str_repeat('a', 50000));
$long = last_message();
check('Long messages are cut to at most 2,000 characters, marker included', mb_strlen($long) <= 2000, true);
check('Cut messages say how long the original was', strpos($long, '(truncated, 50000 characters)') !== false, true);

wpiko_chatbot_log(str_repeat('Ω', 3000));
check('Multibyte text is cut on a character boundary', mb_check_encoding(last_message(), 'UTF-8'), true);
check('Shortening twice changes nothing', wpiko_chatbot_log_trim($long), $long);

$options['wpiko_chatbot_debug_logs'] = array();
for ($i = 0; $i < 210; $i++) {
    wpiko_chatbot_log(str_repeat('x', 100000));
}
$logs = get_option('wpiko_chatbot_debug_logs');
check('The log keeps at most 200 entries', count($logs), 200);
check('A full log of huge messages stays under 1 MB', strlen(serialize($logs)) < 1024 * 1024, true);

$options['wpiko_chatbot_debug_logs'] = array(array('timestamp' => 'old', 'level' => 'info', 'message' => str_repeat('y', 300000)));
wpiko_chatbot_log('next');
check('Oversized entries saved by older versions shrink on the next write', mb_strlen(get_option('wpiko_chatbot_debug_logs')[0]['message']) <= 2000, true);

$options['wpiko_chatbot_debug_logs'] = 'corrupted';
wpiko_chatbot_log('after corruption');
check('A corrupted log option is replaced instead of crashing', last_message(), 'after corruption');

$options['wpiko_chatbot_debug_logging'] = '0';
$options['wpiko_chatbot_debug_logs'] = array();
wpiko_chatbot_log('not saved');
check('Nothing is saved while debug logging is off', get_option('wpiko_chatbot_debug_logs'), array());

add_filter('wpiko_chatbot_log_message_max_length', function () { return 10; });
check('The length filter cannot go below 200 characters', mb_strlen(wpiko_chatbot_log_trim(str_repeat('z', 1000))), 200);

echo $failures ? "\n$failures check(s) failed.\n" : "\nAll logging checks passed.\n";
exit($failures ? 1 : 0);

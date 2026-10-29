<?php
// CLI-only regression test for the Topic focus rule in the instructions sent to OpenAI.
// No WordPress, database or network needed. Run: php tests/topic-focus.php
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');

$options = array();
$filters = array();
$row = (object) array('main_system_instructions' => '', 'specific_system_instructions' => '');
function get_option($key, $default = false) { return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default; }
function add_filter($name, $fn, $priority = 10) { $GLOBALS['filters'][$name][] = $fn; }
function apply_filters($name, $value, ...$args) { foreach ($GLOBALS['filters'][$name] ?? array() as $fn) { $value = $fn($value, ...$args); } return $value; }
function get_bloginfo($what) { return $what === 'name' ? 'Copper Kettle' : 'Coffee roasters'; }
function home_url($path = '') { return 'https://copperkettle.example/'; }
function wp_strip_all_tags($s) { return trim(strip_tags($s)); }
$wpdb = new class { public $prefix = 'wp_'; function get_row($sql) { return $GLOBALS['row']; } };

require dirname(__DIR__) . '/includes/instructions-handler.php';

$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}
$has_rule = function ($text) { return strpos($text, 'Only help with this website') !== false; };

check('Default topic focus is "website" (new and existing sites)', wpiko_chatbot_get_topic_focus(), 'website');
check('Rule is sent by default', $has_rule(wpiko_chatbot_combine_responses_instructions()), true);
check('Rule is sent when there are no knowledge files', $has_rule(wpiko_chatbot_combine_responses_instructions(false)), true);
check('Rule stays short (under 100 words)', str_word_count(wpiko_chatbot_get_topic_focus_instructions()) < 100, true);

$row->specific_system_instructions = 'Also recommend cafes in London.';
$combined = wpiko_chatbot_combine_responses_instructions();
check("Owner's specific instructions are still sent", strpos($combined, 'Also recommend cafes in London.') !== false, true);
check('Rule tells the AI that topics the owner describes count as related', strpos($combined, 'Topics the website owner describes in these instructions count as related.') !== false, true);

$options['wpiko_chatbot_responses_assistant_type'] = 'AI assistant';
$options['wpiko_chatbot_responses_website_specialization'] = 'specialty coffee';
$combined = wpiko_chatbot_combine_responses_instructions();
check('Main template asks for answers about the website, not any "user queries"', strpos($combined, 'responses to questions about this website and what it offers') !== false && strpos($combined, 'user queries') === false, true);

$options['wpiko_chatbot_topic_focus'] = 'any';
check('"Any question" sends no rule', $has_rule(wpiko_chatbot_combine_responses_instructions()), false);
$options['wpiko_chatbot_topic_focus'] = 'unexpected';
check('Unknown value falls back to "website"', wpiko_chatbot_get_topic_focus(), 'website');

add_filter('wpiko_chatbot_topic_focus_instructions', function () { return ''; });
check('Filter can remove the rule', $has_rule(wpiko_chatbot_combine_responses_instructions()), false);

echo $failures ? "\n$failures check(s) failed\n" : "\nAll checks passed\n";
exit($failures ? 1 : 0);

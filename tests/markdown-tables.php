<?php
// CLI-only: Markdown tables in AI replies (server formatter, history renderer and
// the browser's streaming formatter must agree). Uses WordPress's real KSES.
// Run: WPIKO_TEST_WP_ROOT=/path/to/wordpress php tests/markdown-tables.php
// CLI-only: actual WordPress formatting/KSES and plugin renderers, no DB or HTTP.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
$root = rtrim(getenv('WPIKO_TEST_WP_ROOT') ?: dirname(__DIR__, 4), '/') . '/';
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
$options = $filters = array();
define('ABSPATH', $root);
define('WPIKO_CHATBOT_PLUGIN_DIR', $root . 'wp-content/plugins/wpiko-chatbot/');
define('WPIKO_CHATBOT_PLUGIN_URL', 'https://example.test/plugin/');
function add_action(...$args) {}
function add_filter($name, $callback, $priority = 10, ...$args) { $GLOBALS['filters'][$name][$priority][] = $callback; }
function remove_filter(...$args) {}
function apply_filters($name, $value, ...$args) {
    $hooks = isset($GLOBALS['filters'][$name]) ? $GLOBALS['filters'][$name] : array();
    ksort($hooks);
    foreach ($hooks as $callbacks) foreach ($callbacks as $callback) $value = $callback($value, ...$args);
    return $value;
}
function is_utf8_charset(...$args) { return true; }
function _canonical_charset($charset) { return 'UTF-8'; }
function get_option($key, $default = false) { return $key === 'blog_charset' ? 'UTF-8' : (isset($GLOBALS['options'][$key]) ? $GLOBALS['options'][$key] : $default); }
function get_site_url() { return 'https://example.test'; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function check_ajax_referer(...$args) {}
function current_user_can(...$args) { return true; } // Simulates authorized admin renderer only.
function get_avatar(...$args) { return ''; }
function get_bloginfo(...$args) { return 'Review fixture'; }
function current_time(...$args) { return '2026-10-03'; }
function wp_send_json_success($data) { $GLOBALS['review_response'] = $data; }
function wp_send_json_error($data) { throw new Exception((string)$data); }
function wp_allowed_protocols() { return array('http', 'https', 'mailto'); }
if (file_exists($root . 'wp-includes/utf8.php')) require $root . 'wp-includes/utf8.php';
require $root . 'wp-includes/formatting.php';
require $root . 'wp-includes/kses.php';
foreach (array('class-wp-token-map.php', 'html-api/class-wp-html-tag-processor.php') as $file) {
    if (file_exists($root . 'wp-includes/' . $file)) require_once $root . 'wp-includes/' . $file;
}
foreach (glob($root . 'wp-includes/html-api/*.php') as $file) { require_once $file; }
require WPIKO_CHATBOT_PLUGIN_DIR . 'includes/markdown-handler.php';

$pass = 0;
$fail = 0;
function wpiko_table_check($name, $condition) {
    global $pass, $fail;
    if ($condition) { $pass++; return; }
    $fail++;
    fwrite(STDERR, "FAIL: $name\n");
}

// 1. A typical reply: text, table with bold and a link, follow-up line.
$reply = "Here is the difference:\n\n| Free includes | Pro adds |\n|---|---|\n| Learn from up to 10 website pages | Unlimited website scanning |\n| **Message limits** to control usage | See https://wpiko.com/chatbot-pricing/ |\n\nWant more details?";
$html = wpiko_chatbot_process_message($reply);
wpiko_table_check('table survives KSES', substr_count($html, '<div class="wpiko-table-wrap"><table class="wpiko-table">') === 1);
wpiko_table_check('header cells', substr_count($html, '<th>') === 2);
wpiko_table_check('body rows', substr_count($html, '<tr>') === 3);
wpiko_table_check('no raw pipes left', strpos($html, '|') === false);
wpiko_table_check('bold in a cell', strpos($html, '<td><strong>Message limits</strong> to control usage</td>') !== false);
wpiko_table_check('link in a cell', strpos($html, '<a href="https://wpiko.com/chatbot-pricing/"') !== false);
wpiko_table_check('table on one line', (bool) preg_match('/<table[^\n]*<\/table>/', $html));
wpiko_table_check('blank line before table kept', strpos($html, "Here is the difference:\n\n<div class=\"wpiko-table-wrap\">") !== false);
wpiko_table_check('line after table joins it', strpos($html, "</table></div>\nWant more details?") !== false);

// 2. Alignment, no outer pipes, escaped pipe, short row, line right after.
$tables = wpiko_chatbot_markdown_tables("Plan | Price | Sites\n:--- | ---: | :---:\nPersonal | $49 | 1\nAgency \\| Pro | $299\nLast line");
wpiko_table_check('right aligned header', strpos($tables, '<th class="align-right">Price</th>') !== false);
wpiko_table_check('center aligned cell', strpos($tables, '<td class="align-center">1</td>') !== false);
wpiko_table_check('escaped pipe is text', strpos($tables, '<td>Agency | Pro</td>') !== false);
wpiko_table_check('short row padded', substr_count($tables, '<td class="align-center"></td>') === 1);
wpiko_table_check('next line joined', substr($tables, -strlen('</table></div>Last line')) === '</table></div>Last line');
$aligned = wpiko_chatbot_sanitize_message_html($tables);
wpiko_table_check('align classes survive KSES', strpos($aligned, 'class="align-right"') !== false);

// 3. Not tables: left unchanged.
$not_tables = array(
    "a | b\nc | d",
    "Use A | B for this.\n---\nOK",
    "No pipes here",
    "| a | b |\n|---|\n| 1 | 2 |",
);
foreach ($not_tables as $index => $text) {
    wpiko_table_check("not a table #$index", wpiko_chatbot_markdown_tables($text) === $text);
}

// 4. Header only, Windows line endings.
wpiko_table_check('header-only table', wpiko_chatbot_markdown_tables("| A | B |\n|---|---|") === '<div class="wpiko-table-wrap"><table class="wpiko-table"><thead><tr><th>A</th><th>B</th></tr></thead></table></div>');
wpiko_table_check('CRLF', strpos(wpiko_chatbot_markdown_tables("| A | B |\r\n|---|---|\r\n| 1 | 2 |"), '<td>1</td><td>2</td>') !== false);

// 5. History: replies saved with a raw table (before 2.1.4) and new saved HTML.
$old_row = "Intro\n\n| A | B |\n|---|---|\n| 1 | 2 |\n\nEnd";
$history = wpiko_chatbot_render_message($old_row, 'assistant');
wpiko_table_check('old saved table renders', strpos($history, '<td>1</td><td>2</td>') !== false && strpos($history, '|') === false);
wpiko_table_check('no <br> inside the table', !preg_match('/<table.*<br.*<\/table>/s', $history));
wpiko_table_check('new saved HTML renders once', substr_count(wpiko_chatbot_render_message($html, 'assistant'), '<table') === 1);
wpiko_table_check('visitor pipes stay text', strpos(wpiko_chatbot_render_message("| A | B |\n|---|---|", 'user'), '<table') === false);

// 6. The browser's streaming formatter makes the same HTML (needs node).
$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $source = WPIKO_CHATBOT_PLUGIN_DIR . 'js/wpiko-chatbot.js';
    $sample = "| A | B |\n|:--|--:|\n| 1 | 2 |\n| x \\| y |\nEnd";
    $script = 'const src=require("fs").readFileSync(process.argv[1],"utf8");'
        . 'const s=src.indexOf("    function markdownTableCells(line)");'
        . 'const e=src.indexOf("    function handleSuccessfulResponseData(data)");'
        . 'const f=new Function(src.slice(s,e)+";return renderStreamingTables;")();'
        . 'process.stdout.write(f(process.argv[2]));';
    $js = shell_exec(escapeshellarg($node) . ' -e ' . escapeshellarg($script) . ' ' . escapeshellarg($source) . ' ' . escapeshellarg($sample));
    wpiko_table_check('JS matches PHP', $js === wpiko_chatbot_markdown_tables($sample));
    $partial = shell_exec(escapeshellarg($node) . ' -e ' . escapeshellarg($script) . ' ' . escapeshellarg($source) . ' ' . escapeshellarg("| A | B |\n|---|"));
    wpiko_table_check('JS waits for the separator row', $partial === "| A | B |\n|---|");
} else {
    echo "SKIP: node not found, JS comparison not run\n";
}

echo ($fail ? 'FAIL' : 'PASS') . ": $pass passed, $fail failed (Markdown tables).\n";
exit($fail ? 1 : 0);

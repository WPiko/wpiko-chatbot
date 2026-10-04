<?php
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
require WPIKO_CHATBOT_PLUGIN_DIR . 'includes/responses-api.php';
require WPIKO_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
require $root . 'wp-content/plugins/wpiko-chatbot-pro/includes/rest-api.php';
$wpdb = new class {
    public $prefix = 'wp_';
    public $rows = array();
    function prepare($sql, ...$args) { return $sql; }
    function get_results($sql) { return $this->rows; }
};
function wpiko_chatbot_is_license_active() { return true; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
require $root . 'wp-content/plugins/wpiko-chatbot-pro/includes/markdown-handler-integration.php';

$count = 0;
$fixtures = array();
function check($condition, $label) {
    $GLOBALS['count']++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
function parse_html($html) {
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<meta charset="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $doc;
}
function check_safe($html, $label) {
    $doc = parse_html($html);
    $bad = array('script', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'textarea', 'base');
    foreach ($doc->getElementsByTagName('*') as $node) {
        check(!in_array(strtolower($node->tagName), $bad, true), $label . ' tag ' . $node->tagName);
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            check(strpos($name, 'on') !== 0 && $name !== 'srcdoc', $label . ' attribute ' . $name);
            if ($name === 'href' || $name === 'src') {
                $value = preg_replace('/[\x00-\x20\x7f]+/', '', $attribute->value);
                check(!preg_match('/^(?:javascript|vbscript|data):/i', $value), $label . ' URL');
            }
        }
    }
}
$payloads = array(
    '<img src=x onerror=alert(123)>',
    '<script>alert(123)</script>',
    '&lt;img src=x onerror=alert(123)&gt;',
    '&#60;img src=x onerror=alert(123)&#62;',
    '&amp;lt;img src=x onerror=alert(123)&amp;gt;',
    '<svg onload=alert(123)><a href="javascript:alert(123)">link</a></svg>',
    '<a href="jav&#x61;script:alert(123)" onclick="alert(123)">link</a>',
    '<img src="data:image/svg+xml;base64,PHN2Zy8+" onerror="alert(123)">',
    '<iframe srcdoc="<script>alert(123)</script>"></iframe>',
    '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=x onerror=alert(123)>">',
    '</div><img src=x onerror=alert(123)><div>',
    '<div style="background:url(javascript:alert(123))" onmouseover="alert(123)">text</div>',
    '<a href="https://example.test/" data-evil="x" id="location">safe</a>',
    '%%URL999%% **bold**',
);
foreach ($payloads as $index => $input) {
    check_safe(wpiko_chatbot_format_responses_reply($input), 'new assistant ' . $index);
    foreach (array('assistant', 'user', 'admin', 'error') as $role) {
        // Raw historical rows deliberately bypass new-reply sanitization.
        $stored = $role === 'user' ? sanitize_textarea_field(wp_unslash($input)) : $input;
        $row = (object)array('role' => $role, 'message' => $stored, 'timestamp' => '2026-10-03 12:00:00', 'user_id' => 0, 'user_name' => '', 'user_email' => '', 'device_type' => '', 'country' => '', 'city' => '', 'region' => '');
        $wpdb->rows = array($row);
        $_POST = array('session_id' => 'security-regression');
        wpiko_chatbot_fetch_conversation();
        $outputs = array(
            'admin' => $GLOBALS['review_response']['messages'],
            'export' => wpiko_chatbot_generate_html_transcript(array($row), 'Bot', 'security-regression'),
            'legacy export' => wpiko_chatbot_generate_transcript(array($row)),
            'PWA' => $role === 'assistant' ? wpiko_chatbot_pro_rest_sanitize_transcript_message($stored, $role) : esc_html($stored),
        );
        foreach ($outputs as $surface => $html) check_safe($html, "$surface $role $index");
        $fixtures[] = array('name' => "$role $index", 'html' => wpiko_chatbot_render_message($stored, $role));
    }
}
$plain = wpiko_chatbot_render_message('&lt;script&gt;alert(123)&lt;/script&gt; 😊\nHello', 'user');
check(strpos($plain, '&lt;script&gt;') !== false, 'encoded visitor HTML remains literal text');
check(strpos($plain, '😊') !== false, 'Unicode emoji preserved');
$rich = wpiko_chatbot_format_responses_reply("## Heading\n**Bold** and *italic* 😊\n[Docs](https://example.test/docs)\nhello@example.test");
check(strpos($rich, '<h2>Heading</h2>') !== false, 'headings');
check(strpos($rich, '<strong>Bold</strong>') !== false, 'bold');
check(strpos($rich, 'href="https://example.test/docs"') !== false, 'markdown links');
check(strpos($rich, 'href="mailto:hello@example.test"') !== false, 'email links');
check_safe($rich, 'formatting');
$fixtures[] = array('name' => 'Formatting', 'html' => $rich);
$options['wpiko_chatbot_enable_contact_form'] = '1';
$options['wpiko_chatbot_enable_product_cards'] = 1;
wpiko_chatbot_pro_add_markdown_filters();
foreach (array('Need help 😊', 'A "quoted" message with https://example.test/help and hello@example.test', '&lt;img src=x onerror=alert(123)&gt;') as $prefill) {
    $marker = '[wpiko-contact-form:' . json_encode(array('message' => $prefill, 'custom_field_1' => 'Order_123'), JSON_UNESCAPED_SLASHES) . ']';
    $html = wpiko_chatbot_format_responses_reply($marker);
    check_safe($html, 'contact marker');
    $link = parse_html($html)->getElementsByTagName('a')->item(0);
    check($link !== null && $link->getAttribute('class') === 'wpiko-contact-button', 'contact button exists: ' . $html);
    $data = json_decode($link->getAttribute('data-wpiko-prefill'), true);
    check(isset($data['message']) && $data['message'] === $prefill, 'contact prefill preserved');
    check($data['custom_field_1'] === 'Order_123', 'custom field preserved');
    $historical_link = parse_html(wpiko_chatbot_render_message($html, 'assistant'))->getElementsByTagName('a')->item(0);
    check(json_decode($historical_link->getAttribute('data-wpiko-prefill'), true) === $data, 'historical contact JSON preserved');
    $fixtures[] = array('name' => 'Contact button', 'html' => $html);
}
$product = array('name' => 'A <script>bad</script> product', 'description' => 'A useful product', 'price' => '<del><span class="amount">€20</span></del> <ins><bdi>€10</bdi></ins>', 'url' => 'https://example.test/product', 'image' => 'https://example.test/product.png');
$card = wpiko_chatbot_sanitize_message_html(wpiko_chatbot_pro_format_product_link($product));
check_safe($card, 'product card');
check(strpos($card, 'product-link-container') !== false && strpos($card, '<bdi>€10</bdi>') !== false, 'product card and price retained');
$fixtures[] = array('name' => 'Product card', 'html' => $card);
$options['wpiko_chatbot_show_product_title'] = 0;
$options['wpiko_chatbot_show_product_description'] = 0;
$options['wpiko_chatbot_show_product_price'] = 0;
check(strpos(wpiko_chatbot_pro_format_product_link($product), 'product-info') === false, 'disabled product details omitted without inline styles');
$legacy_contact = wpiko_chatbot_format_responses_reply('Wpiko Form');
check(strpos($legacy_contact, 'wpiko-contact-button') !== false && strpos($legacy_contact, 'onclick') === false, 'legacy contact marker remains functional without inline handlers');
$legacy_marker = '<!--WPIKO_CONTACT_FORM:' . json_encode(array('message' => 'Help with https://example.test/docs'), JSON_UNESCAPED_SLASHES) . '-->';
check(strpos(wpiko_chatbot_format_responses_reply($legacy_marker), 'wpiko-contact-button') !== false, 'legacy JSON marker preserved');
$formatted_code = wpiko_chatbot_process_message('<pre><code>value_with_underscores https://example.test/test</code></pre>');
check(strpos($formatted_code, '<code>value_with_underscores https://example.test/test</code>') !== false, 'existing code blocks are not rewritten');

// A future output filter must not be able to bypass the final sanitizer.
add_filter('wpiko_chatbot_processed_markdown', function ($text) { return $text . '<img src=x onerror=alert(123)>'; }, 99);
check_safe(wpiko_chatbot_format_responses_reply('Hello'), 'late output filter');
if (isset($argv[1])) file_put_contents($argv[1], json_encode(array('payloads' => $payloads, 'fixtures' => $fixtures), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "PASS: $count assertions (message rendering, stored rows, exports, PWA and Pro features).\n";

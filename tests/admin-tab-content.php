<?php
// CLI-only regression test for the admin screen fallback in admin-wrapper.php.
// Renders the real template with stubbed navigation and screens; no WordPress needed.
if (PHP_SAPI !== 'cli' || defined('ABSPATH')) { exit; }
error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) { throw new ErrorException($message, 0, $level, $file, $line); });
define('ABSPATH', '/tmp/');
// The template loads admin/includes/plugin-navigation.php; point it at an empty stand-in.
$fixture = sys_get_temp_dir() . '/wpiko-chatbot-tab-content-test/';
if (!is_dir($fixture . 'admin/includes')) { mkdir($fixture . 'admin/includes', 0777, true); }
file_put_contents($fixture . 'admin/includes/plugin-navigation.php', '<?php');
define('WPIKO_CHATBOT_PLUGIN_DIR', $fixture);

$filters = array();
function add_filter($name, $fn, $priority = 10) { $GLOBALS['filters'][$name][$priority][] = $fn; }
function apply_filters($name, $value, ...$args) {
    $byPriority = $GLOBALS['filters'][$name] ?? array();
    ksort($byPriority);
    foreach ($byPriority as $callbacks) {
        foreach ($callbacks as $fn) { $value = $fn($value, ...$args); }
    }
    return $value;
}
function wpiko_chatbot_render_navigation(...$a) {}
function wpiko_chatbot_render_subnavigation(...$a) {}
function wpiko_chatbot_dashboard_section() { echo '[dashboard]'; }
foreach (array('ai_configuration', 'api_key', 'conversations', 'debug_log', 'error_messages', 'floating_chatbot', 'menu', 'proactive_greeting', 'questions', 'setup_wizard', 'shortcode_chatbot', 'style', 'user_limits') as $s) {
    eval('function wpiko_chatbot_' . $s . '_section() { echo "[' . $s . ']"; }');
}

function render($tab) {
    $wpiko_active_tab = $tab;
    ob_start();
    include __DIR__ . '/../admin/templates/admin-wrapper.php';
    preg_match_all('/\[[a-z_]+\]/', ob_get_clean(), $m);
    return $m[0];
}
$failures = 0;
function check($label, $actual, $expected) {
    if ($actual === $expected) { echo "PASS  $label\n"; return; }
    $GLOBALS['failures']++;
    echo "FAIL  $label\n      expected " . json_encode($expected) . "\n      got      " . json_encode($actual) . "\n";
}

// Free plugin alone: nothing handles unknown tabs.
check('Dashboard tab', render('dashboard'), array('[dashboard]'));
check('Built-in screen', render('debug_log'), array('[debug_log]'));
check('Unknown tab without add-ons opens the Dashboard', render('analytics'), array('[dashboard]'));

// Add-on that passes other tabs on unchanged (the documented way).
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) {
    if ($tab !== 'my_addon') { return $tab; }
    echo '[my_addon]';
    return true;
});
check('Add-on screen prints once, without the Dashboard', render('my_addon'), array('[my_addon]'));
check('Unknown tab with the add-on active opens the Dashboard', render('no_such_tab'), array('[dashboard]'));

// Older add-ons (for example Pro 2.1.1) return false for tabs they don't handle.
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) { return $tab === true ? true : false; }, 20);
check('Unknown tab with an add-on returning false opens the Dashboard', render('no_such_tab'), array('[dashboard]'));
check('Add-on screen still prints once after a false-returning add-on', render('my_addon'), array('[my_addon]'));

// A truthy value that isn't true is not treated as printed.
add_filter('wpiko_chatbot_admin_tab_content', function ($tab) { return $tab === true ? true : 1; }, 30);
check('Non-true truthy result opens the Dashboard', render('no_such_tab'), array('[dashboard]'));

echo $failures ? "\n$failures failed\n" : "\nAll passed\n";
exit($failures ? 1 : 0);

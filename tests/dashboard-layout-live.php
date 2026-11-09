<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot/tests/dashboard-layout-live.php
// Renders the Dashboard screen in three states and checks the order of its parts:
// 1. normal: stats (with the AI cost card), activity + AI usage & cost, Configuration status;
// 2. with an attention item: the Attention panel comes first;
// 3. setup not finished: Configuration status moves above the activity row.
// Nothing is saved: states are simulated with filters for this request only.
// Keep tests out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');

$failures = 0;
$check = function ($ok, $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ": $label\n";
    if (!$ok) {
        $failures++;
    }
};
$render = function () {
    ob_start();
    wpiko_chatbot_dashboard_section();
    return ob_get_clean();
};
$position = function ($html, $needle) {
    $found = strpos($html, $needle);
    return $found === false ? -1 : $found;
};
$parts = array(
    'attention' => 'class="dashboard-widget attention-widget"',
    'stats' => 'class="dashboard-stats-grid"',
    'row' => 'class="dashboard-main-content dashboard-overview-row"',
    'activity' => '<!-- Recent Activity -->',
    'usage' => 'id="wpiko-usage"',
    'config' => 'class="dashboard-widget configuration-widget"',
);
$order = function ($html) use ($parts, $position) {
    $found = array();
    foreach ($parts as $name => $needle) {
        $at = $position($html, $needle);
        if ($at >= 0) {
            $found[$name] = $at;
        }
    }
    asort($found);
    return implode(' > ', array_keys($found));
};

// Make sure the usual alerts do not appear, so only the simulated one is tested.
add_filter('wpiko_chatbot_dashboard_attention_items', function ($items) {
    return empty($GLOBALS['wpiko_test_attention']) ? array() : $GLOBALS['wpiko_test_attention'];
}, 99);
add_filter('pre_option_wpiko_chatbot_setup_completed', function ($value) {
    return empty($GLOBALS['wpiko_test_setup_incomplete']) ? '1' : '0';
});

// 1. Normal.
$html = $render();
echo 'Normal: ' . $order($html) . "\n";
$check($order($html) === 'stats > row > activity > usage > config', 'normal order: stats, activity + cost, configuration');
$check(strpos($html, 'stat-cost') !== false && strpos($html, 'AI cost this month') !== false && strpos($html, 'href="#wpiko-usage"') !== false, 'AI cost stat card links to the widget');
$check(strpos($html, 'Everything looks good') === false && strpos($html, 'attention-widget') === false, 'no Attention panel when nothing needs action');
$check(substr_count($html, 'class="stat-card ') === 5, 'five stat cards');
$check(substr_count($html, '<div') === substr_count($html, '</div>'), 'div tags balanced');

// 2. Attention item.
$GLOBALS['wpiko_test_attention'] = array(
    array('severity' => 'warning', 'title' => 'Test alert', 'description' => 'Simulated by the layout test.', 'url' => '#', 'action_label' => 'Fix'),
    array('severity' => 'info', 'description' => 'An item without a title is skipped.'),
);
$html = $render();
echo 'With attention: ' . $order($html) . "\n";
$check($order($html) === 'attention > stats > row > activity > usage > config', 'Attention panel first');
$check(substr_count($html, 'class="attention-item ') === 1, 'items without a title are skipped');
$check(substr_count($html, '<div') === substr_count($html, '</div>'), 'div tags balanced with attention');
$GLOBALS['wpiko_test_attention'] = array();

// 3. Setup not finished.
$GLOBALS['wpiko_test_setup_incomplete'] = true;
$html = $render();
echo 'Setup not finished: ' . $order($html) . "\n";
$check($order($html) === 'stats > config > row > activity > usage', 'Configuration status above the activity row');
$GLOBALS['wpiko_test_setup_incomplete'] = false;

echo $failures ? "\n$failures FAILED\n" : "\nALL PASS\n";

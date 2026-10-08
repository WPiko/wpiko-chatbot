<?php
/**
 * Run with wp --skip-plugins --skip-themes eval-file tests/rate-limits-db.php.
 * Uses an isolated table, no site options/conversations or OpenAI calls.
 * Requires a disposable local WordPress database and PHP pcntl for concurrency.
 */
if (PHP_SAPI !== 'cli' || !defined('WP_CLI') || !WP_CLI) { exit; }
if (!function_exists('pcntl_fork')) { throw new RuntimeException('pcntl is required'); }
global $wpdb;
if (!defined('WPIKO_CHATBOT_PLUGIN_FILE')) define('WPIKO_CHATBOT_PLUGIN_FILE', dirname(__DIR__) . '/wpiko-chatbot.php');
require_once dirname(__DIR__) . '/includes/user-limits.php';
$original_prefix = $wpdb->prefix;
$test_prefix = 'wpiko_test_' . bin2hex(random_bytes(6)) . '_';
$table = $test_prefix . 'wpiko_chatbot_rate_limits';
$wpdb->query("CREATE TABLE $table (bucket_key varchar(64) PRIMARY KEY, request_count bigint unsigned NOT NULL DEFAULT 0, expires_at bigint unsigned NOT NULL, KEY expires_at (expires_at)) ENGINE=InnoDB");
$wpdb->prefix = $test_prefix;
try {
    // Close inherited connection before forking: each worker opens its own.
    $wpdb->close();
    $workers = array();
    for ($i = 0; $i < 24; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
            $wpdb->prefix = $test_prefix;
            $accepted = 0;
            for ($j = 0; $j < 10; $j++) {
                if (wpiko_chatbot_rate_limit_reserve('parallel', 'same-client', 25, 3600)) $accepted++;
            }
            $wpdb->close();
            exit($accepted);
        }
        $workers[] = $pid;
    }
    $total = 0;
    foreach ($workers as $pid) {
        pcntl_waitpid($pid, $status);
        if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) > 10) throw new RuntimeException('worker failed');
        $total += pcntl_wexitstatus($status);
    }
    $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $wpdb->prefix = $test_prefix;
    $stored = (int) $wpdb->get_var("SELECT request_count FROM $table");
    if ($total !== 25 || $stored !== 25) throw new RuntimeException("Concurrency failed: accepted $total, stored $stored");
    echo "PASS: 240 attempts on 24 connections admitted exactly 25; counter = 25.\n";
    $wpdb->query("UPDATE $table SET expires_at = 0");
    if (!wpiko_chatbot_rate_limit_reserve('parallel', 'same-client', 25, 3600)) throw new RuntimeException('Expiry did not reset');
    if ((int) $wpdb->get_var("SELECT request_count FROM $table") !== 1) throw new RuntimeException('Reset count incorrect');
    echo "PASS: expired counter resets atomically to 1.\n";
    $wpdb->query("DROP TABLE $table");
    if (wpiko_chatbot_rate_limit_reserve('missing-table', 'same-client', 25, 3600)) throw new RuntimeException('Storage failure did not block');
    echo "PASS: missing counter storage fails closed.\n";
} finally {
    $wpdb->query("DROP TABLE IF EXISTS $table");
    $wpdb->prefix = $original_prefix;
}

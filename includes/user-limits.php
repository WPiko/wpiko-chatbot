<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Chat abuse controls. Custom limits default on; mandatory safety limits also
 * protect installations that previously disabled limits or saved zero caps.
 * Database reservations are atomic and independent of the object cache.
 */
function wpiko_chatbot_user_limits_enabled()
{
    return get_option('wpiko_chatbot_user_limits_enabled', '1') === '1';
}

/** Install on upgrades as well as activation, without changing saved preferences. */
function wpiko_chatbot_install_rate_limits()
{
    global $wpdb;
    if (get_option('wpiko_chatbot_rate_limits_schema') !== '1') {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'wpiko_chatbot_rate_limits';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            bucket_key varchar(64) NOT NULL,
            request_count bigint unsigned NOT NULL DEFAULT 0,
            expires_at bigint unsigned NOT NULL,
            PRIMARY KEY  (bucket_key),
            KEY expires_at (expires_at)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return; // Requests fail closed if storage could not be installed.
        }
        add_option('wpiko_chatbot_user_limits_enabled', '1');
        add_option('wpiko_chatbot_user_limits_hourly', 20);
        add_option('wpiko_chatbot_user_limits_daily', 100);
        add_option('wpiko_chatbot_user_limits_site_daily', 1000);
        update_option('wpiko_chatbot_rate_limits_schema', '1', false);
    }
    if (!wp_next_scheduled('wpiko_chatbot_cleanup_rate_limits')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'wpiko_chatbot_cleanup_rate_limits');
    }
}
add_action('init', 'wpiko_chatbot_install_rate_limits');

function wpiko_chatbot_cleanup_rate_limits()
{
    global $wpdb;
    $table = $wpdb->prefix . 'wpiko_chatbot_rate_limits';
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at <= %d LIMIT 10000", time()));
}
add_action('wpiko_chatbot_cleanup_rate_limits', 'wpiko_chatbot_cleanup_rate_limits');

function wpiko_chatbot_deactivate_rate_limits()
{
    wp_clear_scheduled_hook('wpiko_chatbot_cleanup_rate_limits');
}
register_deactivation_hook(WPIKO_CHATBOT_PLUGIN_FILE, 'wpiko_chatbot_deactivate_rate_limits');

/** Reserve one request, or reject it. No read/check/write race or cache dependency. */
function wpiko_chatbot_rate_limit_reserve($scope, $identity, $limit, $window)
{
    global $wpdb;
    $table = $wpdb->prefix . 'wpiko_chatbot_rate_limits';
    $key = hash_hmac('sha256', $scope . ':' . $identity, wp_salt('auth'));
    $now = time();
    $expires = $now + $window;
    $previous = $wpdb->suppress_errors(true);
    $inserted = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $table (bucket_key, request_count, expires_at) VALUES (%s, 0, %d)",
        $key, $expires
    ));
    $reserved = false;
    if ($inserted !== false) {
        // The condition and increment execute under the database's row lock.
        // Assignment order matters: evaluate the old expiry before replacing it.
        $reserved = $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET request_count = IF(expires_at <= %d, 1, request_count + 1),
                 expires_at = IF(expires_at <= %d, %d, expires_at)
             WHERE bucket_key = %s AND (expires_at <= %d OR request_count < %d)",
            $now, $now, $expires, $key, $now, $limit
        ));
    }
    $wpdb->suppress_errors($previous);
    return $reserved === 1;
}

/**
 * Default friendly message shown when a visitor hits the limit.
 *
 * @return string
 */
function wpiko_chatbot_user_limits_default_message()
{
    return __('You have reached the maximum number of messages allowed. Please try again later.', 'wpiko-chatbot');
}

/**
 * Get the configured friendly message (falls back to the default).
 *
 * @return string
 */
function wpiko_chatbot_user_limits_get_message()
{
    $message = trim((string) get_option('wpiko_chatbot_user_limits_message', ''));

    if ($message === '') {
        $message = wpiko_chatbot_user_limits_default_message();
    }

    return $message;
}

/**
 * Resolve the visitor's IP address.
 *
 * Uses REMOTE_ADDR by default because it cannot be spoofed at the TCP level.
 * Sites sitting behind a trusted reverse proxy / CDN can override the result
 * through the 'wpiko_chatbot_client_ip' filter.
 *
 * @return string Validated IP address, or empty string if none could be determined.
 */
function wpiko_chatbot_get_client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR'])
        ? trim(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])))
        : '';

    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }

    /**
     * Filter the detected client IP used for per-IP rate limiting.
     *
     * @param string $ip The validated REMOTE_ADDR (may be empty).
     */
    $ip = apply_filters('wpiko_chatbot_client_ip', $ip);

    if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }

    return $ip;
}

/**
 * Determine whether the limit should be bypassed for this conversation.
 *
 * Bypasses custom limits while an admin handles the conversation through Pro.
 * Safety counters still count these requests and can block them.
 *
 * @param string|null $conversation_id Current conversation/session ID.
 * @return bool
 */
function wpiko_chatbot_user_limits_should_bypass($conversation_id)
{
    $bypass = false;

    if (!empty($conversation_id)
        && function_exists('wpiko_chatbot_pro_is_takeover_active')
        && wpiko_chatbot_pro_is_takeover_active($conversation_id)
    ) {
        $bypass = true;
    }

    /**
     * Filter whether the per-IP request limit is bypassed for a conversation.
     *
     * @param bool        $bypass          Whether to bypass the limit.
     * @param string|null $conversation_id Current conversation/session ID.
     */
    return (bool) apply_filters('wpiko_chatbot_user_limit_bypass', $bypass, $conversation_id);
}

/**
 * Reserve capacity before any paid chat request, for guests and signed-in users.
 * Partial reservations are deliberately not refunded: failures and concurrent
 * requests cannot be used to create extra capacity. Missing IP/storage fails closed.
 */
function wpiko_chatbot_user_limits_check_and_record($conversation_id = null)
{
    $blocked = array('allowed' => false, 'message' => wpiko_chatbot_user_limits_get_message());
    $ip = wpiko_chatbot_get_client_ip();
    if ($ip === '') {
        return $blocked;
    }
    // Canonicalize equivalent IPv6 spellings and IPv4-mapped IPv6 addresses.
    $packed = inet_pton($ip);
    if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        $packed = substr($packed, 12);
    }
    $identity = bin2hex($packed);
    $hourly = 60;
    $daily = 300;
    if (wpiko_chatbot_user_limits_enabled() && !wpiko_chatbot_user_limits_should_bypass($conversation_id)) {
        $custom_hourly = (int) get_option('wpiko_chatbot_user_limits_hourly', 20);
        $custom_daily = (int) get_option('wpiko_chatbot_user_limits_daily', 100);
        if ($custom_hourly > 0) {
            $hourly = min($hourly, $custom_hourly);
        }
        if ($custom_daily > 0) {
            $daily = min($daily, $custom_daily);
        }
    }
    $site_daily = (int) get_option('wpiko_chatbot_user_limits_site_daily', 1000);
    if ($site_daily <= 0) {
        $site_daily = 1000;
    }
    $windows = array(
        array('minute', $identity, 10, MINUTE_IN_SECONDS),
        array('hour', $identity, $hourly, HOUR_IN_SECONDS),
        array('day', $identity, $daily, DAY_IN_SECONDS),
        array('site_day', 'all', $site_daily, DAY_IN_SECONDS),
    );
    foreach ($windows as $window) {
        if (!wpiko_chatbot_rate_limit_reserve($window[0], $window[1], $window[2], $window[3])) {
            return $blocked;
        }
    }
    return array('allowed' => true);
}

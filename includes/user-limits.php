<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * User Limits
 *
 * Lets the site admin cap how many chat requests a visitor can make, bucketed
 * per IP address. Protects against runaway OpenAI costs and abuse.
 *
 * - Feature is opt-in (disabled by default).
 * - Two configurable windows: hourly and daily (0 = that window is disabled).
 * - Hard block with a configurable friendly message, logged with role='error'.
 * - When an admin is actively handling a conversation via Pro's "Take Over"
 *   feature, the limit is bypassed for that conversation and those messages are
 *   not counted.
 */

/**
 * Whether the user limits feature is enabled.
 *
 * @return bool
 */
function wpiko_chatbot_user_limits_enabled()
{
    return get_option('wpiko_chatbot_user_limits_enabled', '0') === '1';
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

    if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }

    /**
     * Filter the detected client IP used for per-IP rate limiting.
     *
     * @param string $ip The validated REMOTE_ADDR (may be empty).
     */
    $ip = apply_filters('wpiko_chatbot_client_ip', $ip);

    if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }

    return $ip;
}

/**
 * Build the transient key for a given window and IP.
 *
 * @param string $window 'h' (hourly) or 'd' (daily).
 * @param string $ip     Visitor IP address.
 * @return string
 */
function wpiko_chatbot_user_limits_key($window, $ip)
{
    return 'wpiko_cb_rl_' . $window . '_' . md5($ip);
}

/**
 * Read the current request count for a window without incrementing it.
 *
 * Uses a fixed-window counter stored as array('count' => int, 'reset' => ts).
 *
 * @param string $key Transient key.
 * @return int Current count in the active window (0 if expired/none).
 */
function wpiko_chatbot_user_limits_peek($key)
{
    $data = get_transient($key);
    $now = time();

    if (!is_array($data) || !isset($data['reset']) || $now >= (int) $data['reset']) {
        return 0;
    }

    return (int) $data['count'];
}

/**
 * Increment the request count for a window, preserving the window's expiry.
 *
 * @param string $key    Transient key.
 * @param int    $window Window length in seconds.
 * @return int New count.
 */
function wpiko_chatbot_user_limits_bump($key, $window)
{
    $data = get_transient($key);
    $now = time();

    if (!is_array($data) || !isset($data['reset']) || $now >= (int) $data['reset']) {
        $data = array('count' => 0, 'reset' => $now + $window);
    }

    $data['count'] = (int) $data['count'] + 1;

    $ttl = (int) $data['reset'] - $now;
    if ($ttl < 1) {
        $ttl = 1;
    }

    set_transient($key, $data, $ttl);

    return (int) $data['count'];
}

/**
 * Determine whether the limit should be bypassed for this conversation.
 *
 * Bypasses while an admin is actively handling the conversation through Pro's
 * "Take Over" feature, so those messages are not blocked or counted.
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
 * Check the per-IP request limit and record the request when allowed.
 *
 * @param string|null $conversation_id Current conversation/session ID.
 * @return array {
 *     @type bool   $allowed Whether the request is permitted.
 *     @type string $message Friendly message to show when blocked.
 * }
 */
function wpiko_chatbot_user_limits_check_and_record($conversation_id = null)
{
    if (!wpiko_chatbot_user_limits_enabled()) {
        return array('allowed' => true);
    }

    if (wpiko_chatbot_user_limits_should_bypass($conversation_id)) {
        return array('allowed' => true);
    }

    $hourly = (int) get_option('wpiko_chatbot_user_limits_hourly', 0);
    $daily = (int) get_option('wpiko_chatbot_user_limits_daily', 0);

    // Both windows disabled => nothing to enforce.
    if ($hourly <= 0 && $daily <= 0) {
        return array('allowed' => true);
    }

    $ip = wpiko_chatbot_get_client_ip();
    if (empty($ip)) {
        // Cannot identify the visitor; fail open rather than blocking everyone.
        return array('allowed' => true);
    }

    if ($hourly > 0) {
        $hour_key = wpiko_chatbot_user_limits_key('h', $ip);
        if (wpiko_chatbot_user_limits_peek($hour_key) >= $hourly) {
            return array(
                'allowed' => false,
                'message' => wpiko_chatbot_user_limits_get_message(),
            );
        }
    }

    if ($daily > 0) {
        $day_key = wpiko_chatbot_user_limits_key('d', $ip);
        if (wpiko_chatbot_user_limits_peek($day_key) >= $daily) {
            return array(
                'allowed' => false,
                'message' => wpiko_chatbot_user_limits_get_message(),
            );
        }
    }

    // Allowed: record the request against each active window.
    if ($hourly > 0) {
        wpiko_chatbot_user_limits_bump(wpiko_chatbot_user_limits_key('h', $ip), HOUR_IN_SECONDS);
    }
    if ($daily > 0) {
        wpiko_chatbot_user_limits_bump(wpiko_chatbot_user_limits_key('d', $ip), DAY_IN_SECONDS);
    }

    return array('allowed' => true);
}

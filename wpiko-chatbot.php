<?php
/**
 * Plugin Name: WPiko Chatbot
 * Plugin URI: https://wpiko.com/chatbot
 * Description: AI chatbot for WordPress powered by your own OpenAI account. Learns your pages and answers visitors 24/7.
 * Version: 2.1.1
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.0
 * Author: WPiko
 * Author URI: https://wpiko.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpiko-chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants for consistent path/URL references
define('WPIKO_CHATBOT_PLUGIN_FILE', __FILE__);
define('WPIKO_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPIKO_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPIKO_CHATBOT_VERSION', '2.1.1');

// Ensures that the default options is set
function wpiko_chatbot_set_default_options()
{
    $default_image = WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png';
    add_option('wpiko_chatbot_image', $default_image);
    add_option('wpiko_chatbot_floating_position', 'right');

    // Set default to Responses API for new installations
    add_option('wpiko_chatbot_api_type', 'responses');
    add_option('wpiko_chatbot_responses_model', 'gpt-6-luna');
    add_option('wpiko_chatbot_responses_reasoning_effort', 'medium');
    add_option('wpiko_chatbot_responses_verbosity', 'medium');
}
register_activation_hook(__FILE__, 'wpiko_chatbot_set_default_options');



// Create database tables
register_activation_hook(__FILE__, 'wpiko_chatbot_create_table');
register_activation_hook(__FILE__, 'wpiko_chatbot_database_activation');

// Ensure database schema is up to date for existing installations
add_action('init', 'wpiko_chatbot_maybe_upgrade_database');
function wpiko_chatbot_maybe_upgrade_database()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // First check if the table exists
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));

    if (!$table_exists) {
        // Table doesn't exist yet, skip migration (will be created on first use)
        return;
    }

    // Check if the openai_response_id column exists
    $column_exists = $wpdb->get_results(
        "SHOW COLUMNS FROM `{$table_name}` LIKE 'openai_response_id'"
    );

    // If column doesn't exist, add it
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE `{$table_name}` ADD COLUMN `openai_response_id` VARCHAR(255) DEFAULT NULL"
        );

        // Update version to mark migration as complete
        update_option('wpiko_chatbot_db_version', '1.1');
    }
}

/**
 * Whether the chatbot's front-end assets are needed on the current page.
 *
 * The chat script, styles and sounds are only loaded where the chatbot is
 * actually displayed, so pages without it stay as fast as before.
 *
 * @return bool
 */
function wpiko_chatbot_should_load_frontend_assets()
{
    $load = wpiko_chatbot_floating_should_display() || wpiko_chatbot_current_page_has_shortcode();

    /**
     * Filter whether WPiko Chatbot loads its front-end assets on this page.
     *
     * Return true for pages that render the chatbot in a way the plugin
     * cannot detect in advance (for example a page builder template).
     *
     * @param bool $load Whether to load the assets.
     */
    return (bool) apply_filters('wpiko_chatbot_load_frontend_assets', $load);
}

/**
 * Run every front-end enqueue callback (core and add-ons) once.
 *
 * Add-ons hook their chatbot assets to 'wpiko_chatbot_enqueue_frontend_assets'
 * instead of 'wp_enqueue_scripts' so they follow the same loading rules.
 *
 * @return void
 */
function wpiko_chatbot_run_frontend_enqueue()
{
    if (did_action('wpiko_chatbot_enqueue_frontend_assets')) {
        return;
    }

    do_action('wpiko_chatbot_enqueue_frontend_assets');
}

function wpiko_chatbot_maybe_enqueue_frontend_assets()
{
    if (wpiko_chatbot_should_load_frontend_assets()) {
        wpiko_chatbot_run_frontend_enqueue();
    }
}
add_action('wp_enqueue_scripts', 'wpiko_chatbot_maybe_enqueue_frontend_assets', 9);

// Enqueuing the necessary scripts and files
function wpiko_chatbot_enqueue_scripts()
{
    // Use defined version constant with dynamic cache-busting
    $version = WPIKO_CHATBOT_VERSION;
    $version = apply_filters('wpiko_chatbot_asset_version', $version);

    wp_enqueue_script('wpiko-chatbot-dompurify', WPIKO_CHATBOT_PLUGIN_URL . 'js/vendor/dompurify/purify.min.js', array(), '3.4.16', true);
    wp_enqueue_script('wpiko-chatbot-message-safety', WPIKO_CHATBOT_PLUGIN_URL . 'js/message-safety.js', array('wpiko-chatbot-dompurify'), $version, true);
    wp_enqueue_script('wpiko-chatbot-js', WPIKO_CHATBOT_PLUGIN_URL . 'js/wpiko-chatbot.js', array('jquery', 'wpiko-chatbot-message-safety'), $version, true);
    wp_enqueue_style('wpiko-chatbot-css', WPIKO_CHATBOT_PLUGIN_URL . 'css/wpiko-chatbot.css', array(), $version);

    // Proactive greeting behavior (only when enabled alongside the floating chatbot)
    if (get_option('wpiko_chatbot_proactive_enabled', false) && get_option('wpiko_chatbot_enable_floating', false)) {
        wp_enqueue_script('wpiko-chatbot-proactive-js', WPIKO_CHATBOT_PLUGIN_URL . 'js/wpiko-chatbot-proactive.js', array('wpiko-chatbot-js'), $version, true);
    }

    // Read CSS content for inlining in transcript downloads
    $css_path = WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/transcript-styles.css';
    $css_content = '';
    if (file_exists($css_path)) {
        $css_content = file_get_contents($css_path);
    }

    // Localize script for frontend transcript CSS
    wp_localize_script('wpiko-chatbot-js', 'wpikoAjax', array(
        'pluginUrl' => WPIKO_CHATBOT_PLUGIN_URL,
        'frontendTranscriptCss' => $css_content
    ));
}
add_action('wpiko_chatbot_enqueue_frontend_assets', 'wpiko_chatbot_enqueue_scripts');

// Include files
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/admin-page.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/plugin-header.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/style-presets.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/deactivation-feedback.php';

require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/dashboard-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/api-key-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/ai-configuration-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/floating-chatbot-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/proactive-greeting-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/shortcode-chatbot-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/chatbot-style-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/chatbot-menu-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/questions-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/conversations-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/user-limits-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/error-messages-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/debug-log-section.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/sections/setup-wizard-section.php';

require_once WPIKO_CHATBOT_PLUGIN_DIR . 'chatbot-interface.php';

require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/logging.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/floating-chatbot.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/responses-tools.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/responses-api.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/conversation-handler.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/markdown-handler.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/api-helpers.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/openai-health.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/site-knowledge.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/sound-functions.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/files-list-handler.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/instructions-handler.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/cache-management.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/nonce-refresh.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/conversation-translation.php';
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/user-limits.php';

// Register shortcode
add_shortcode('wpiko_chatbot', 'wpiko_chatbot_shortcode');

// Shortcode function to display the chatbot
function wpiko_chatbot_shortcode($atts)
{
    // Process any attributes if needed
    $atts = shortcode_atts(array(
        // You can add custom attributes here if needed
    ), $atts);

    // Until an API key is added, only site admins see the chatbot.
    if (!wpiko_chatbot_is_configured() && !current_user_can('manage_options')) {
        return '';
    }

    // Load the assets now if the shortcode was not detected in advance
    // (for example inside a widget or a page builder layout). Scripts are
    // printed in the footer, so enqueueing here still works.
    wpiko_chatbot_run_frontend_enqueue();

    // Return the chatbot display
    return wpiko_chatbot_display();
}

function wpiko_chatbot_is_streaming_request()
{
    // Nonce verification is handled by check_ajax_referer() in the AJAX callers before this
    // helper is invoked; this only reads a request flag to determine the response mode.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    return isset($_POST['stream']) && sanitize_text_field(wp_unslash($_POST['stream'])) === '1';
}

function wpiko_chatbot_send_stream_headers()
{
    if (!headers_sent()) {
        status_header(200);
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
    }

    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');

    while (ob_get_level() > 0) {
        @ob_end_flush();
    }

    ob_implicit_flush(true);
}

function wpiko_chatbot_send_stream_event($event, $data)
{
    echo 'event: ' . sanitize_key($event) . "\n";
    echo 'data: ' . wp_json_encode($data) . "\n\n";
    flush();
}

function wpiko_chatbot_prepare_frontend_response_data($result)
{
    $response_text = isset($result['response']) ? $result['response'] : '';
    if (!is_string($response_text)) {
        wpiko_chatbot_log('Response is not a string, converting: ' . gettype($response_text), 'warning');
        $response_text = (string) $response_text;
    }

    $response_data = array(
        'response' => $response_text,
        'thread_id' => isset($result['conversation_id']) ? $result['conversation_id'] : null,
        'response_id' => isset($result['response_id']) ? $result['response_id'] : null,
        'human_takeover' => !empty($result['human_takeover'])
    );

    return apply_filters('wpiko_chatbot_frontend_response_data', $response_data, $result);
}

// Add AJAX handler for sending messages
function wpiko_chatbot_ajax_handler()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    do_action('wpiko_chatbot_before_chat_request');

    $is_streaming_request = wpiko_chatbot_is_streaming_request();

    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);

    if (empty($api_key)) {
        if ($is_streaming_request) {
            wpiko_chatbot_send_stream_headers();
            wpiko_chatbot_send_stream_event('error', array(
                'message' => 'The chat is currently offline. Please contact the site administrator.',
                'type' => 'config_error'
            ));
            wp_die();
        }

        wp_send_json_error(array(
            'message' => 'The chat is currently offline. Please contact the site administrator.',
            'type' => 'config_error'
        ));
        wp_die();
    }

    // Reject malformed or oversized input before reserving capacity.
    if (!isset($_POST['message']) || !is_string($_POST['message']) || trim($_POST['message']) === '' || strlen(wp_unslash($_POST['message'])) > 16000) {
        $input_error = isset($_POST['message']) && is_string($_POST['message']) && strlen(wp_unslash($_POST['message'])) > 16000
            ? __('Your message is too long. Please shorten it and try again.', 'wpiko-chatbot')
            : __('Please enter a message.', 'wpiko-chatbot');
        if ($is_streaming_request) {
            wpiko_chatbot_send_stream_headers();
            wpiko_chatbot_send_stream_event('error', array(
                'message' => $input_error,
                'type' => 'input_error'
            ));
            wp_die();
        }

        wp_send_json_error(array(
            'message' => $input_error,
            'type' => 'input_error'
        ));
        wp_die();
    }

    $message = sanitize_textarea_field(wp_unslash($_POST['message']));
    $conversation_id = isset($_POST['thread_id']) ? sanitize_text_field(wp_unslash($_POST['thread_id'])) : null;
    $conversation_id = wpiko_chatbot_bind_chat_session($conversation_id);
    $user_email = isset($_POST['user_email']) ? sanitize_email(wp_unslash($_POST['user_email'])) : '';

    // Optimistic Response ID: Accept previous_response_id from frontend to prevent race conditions
    $previous_response_id = isset($_POST['previous_response_id']) && !empty($_POST['previous_response_id'])
        ? sanitize_text_field(wp_unslash($_POST['previous_response_id']))
        : null;

    // Process user_name parameter
    if (isset($_POST['user_name'])) {
        $user_name = sanitize_text_field(wp_unslash($_POST['user_name']));
        if (!isset($_POST['wpiko_chatbot_nonce']) && isset($_POST['security'])) {
            $_POST['wpiko_chatbot_nonce'] = sanitize_text_field(wp_unslash($_POST['security']));
        }
    }

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
    }

    // Reserve per-IP and site-wide capacity before normal or streaming AI requests.
    $limit_status = wpiko_chatbot_user_limits_check_and_record($conversation_id);
    if (isset($limit_status['allowed']) && $limit_status['allowed'] === false) {
        $limit_message = isset($limit_status['message']) ? $limit_status['message'] : wpiko_chatbot_user_limits_default_message();

        // Do not persist rejected traffic: a flood must not create conversation rows.
        if ($is_streaming_request) {
            wpiko_chatbot_send_stream_headers();
            wpiko_chatbot_send_stream_event('error', array(
                'message' => $limit_message,
                'type' => 'rate_limit'
            ));
            wp_die();
        }

        wp_send_json_error(array(
            'message' => $limit_message,
            'type' => 'rate_limit'
        ), 429);
        wp_die();
    }

    if ($is_streaming_request) {
        wpiko_chatbot_send_stream_headers();

        wpiko_chatbot_responses_api_stream($message, $conversation_id, $user_email, $previous_response_id, array(
            'start' => function ($stream_conversation_id) {
                wpiko_chatbot_send_stream_event('start', array(
                    'thread_id' => $stream_conversation_id
                ));
            },
            'delta' => function ($delta) {
                wpiko_chatbot_send_stream_event('delta', array(
                    'delta' => $delta
                ));
            },
            'done' => function ($result) {
                wpiko_chatbot_send_stream_event('done', wpiko_chatbot_prepare_frontend_response_data($result));
            },
            'error' => function ($data) {
                wpiko_chatbot_send_stream_event('error', $data);
            }
        ));

        wp_die();
    }

    $result = wpiko_chatbot_responses_api_call($message, $conversation_id, $user_email, $previous_response_id);

    if (isset($result['success']) && $result['success'] === false) {
        wp_send_json_error($result['data']);
    } else {
        // Debug logging
        if (!wpiko_chatbot_private_orders_retired()) {
            wpiko_chatbot_log('Responses API result: ' . json_encode($result), 'info');
        }

        $response_data = wpiko_chatbot_prepare_frontend_response_data($result);
        wp_send_json_success($response_data);
    }

    wp_die();
}

add_action('wp_ajax_wpiko_chatbot_send_message', 'wpiko_chatbot_ajax_handler');
add_action('wp_ajax_nopriv_wpiko_chatbot_send_message', 'wpiko_chatbot_ajax_handler');


function wpiko_chatbot_localize_script()
{
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $sound_enabled = get_option('wpiko_chatbot_sound_enabled', '1');

    // Configuration is complete if API key is set (using Responses API)
    $config_complete = !empty($api_key);

    // Get reCAPTCHA settings
    $enable_recaptcha = get_option('wpiko_chatbot_enable_recaptcha', '0');
    $recaptcha_site_key = get_option('wpiko_chatbot_recaptcha_site_key', '');
    $hide_recaptcha_badge = get_option('wpiko_chatbot_hide_recaptcha_badge', '0');

    $user_email = '';
    $user_name = '';
    $is_user_logged_in = is_user_logged_in();
    if ($is_user_logged_in) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_name = $current_user->display_name;
    }

    // Get localized error messages for JS (using centralized function)
    $localized_errors = wpiko_chatbot_get_error_messages();

    // Get upload_error from Contact Form settings (Pro feature)
    // This error is managed in Pro's Contact Form Settings, with a fallback default
    $localized_errors['upload_error'] = get_option(
        'wpiko_chatbot_contact_upload_error',
        __('There was a problem with your file upload. Please ensure it is a valid image (JPG, PNG, GIF) under 3MB.', 'wpiko-chatbot')
    );

    // Get rate_limit_error from Contact Form settings (Pro feature)
    $localized_errors['rate_limit_error'] = get_option(
        'wpiko_chatbot_contact_rate_limit_error',
        __('You have submitted too many requests. Please try again later.', 'wpiko-chatbot')
    );

    // Get validation_error from Email Capture settings (Pro feature)
    $localized_errors['validation_error'] = get_option(
        'wpiko_chatbot_email_capture_validation_error',
        __('Please check your input and try again.', 'wpiko-chatbot')
    );

    $script_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'chatbotName' => get_option('wpiko_chatbot_name', 'My Chatbot'),
        'apiKeyEmpty' => empty($api_key),
        'apiType' => 'responses',
        'configComplete' => $config_complete,
        'floatingText' => get_option('wpiko_chatbot_floating_text', ''),
        'is_user_logged_in' => $is_user_logged_in,
        'user_email' => $user_email,
        'user_name' => $user_name,
        'welcome_message' => get_option('wpiko_chatbot_welcome_message', 'Welcome! Type your message to start chatting.'),
        'botAvatarUrl' => get_option('wpiko_chatbot_image', WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png'),
        'enable_transcript_download' => get_option('wpiko_chatbot_enable_transcript_download', '1'),
        'sound_enabled' => $sound_enabled,
        'streaming_enabled' => apply_filters('wpiko_chatbot_streaming_enabled', true) ? '1' : '0',
        'siteName' => get_bloginfo('name'),
        'errors' => $localized_errors,
        'proactive' => array(
            'trigger' => get_option('wpiko_chatbot_proactive_trigger', 'delay'),
            'delay' => (int) get_option('wpiko_chatbot_proactive_delay', 5),
            'scroll_depth' => (int) get_option('wpiko_chatbot_proactive_scroll_depth', 30),
            'frequency' => get_option('wpiko_chatbot_proactive_frequency', 'session'),
            'settings_version' => get_option('wpiko_chatbot_proactive_settings_version', ''),
        )
    );

    // Site admins testing the chat get setup hints instead of the generic visitor error.
    if (current_user_can('manage_options')) {
        $script_data['adminNotice'] = array(
            'missingKey' => __('Admin notice: the chatbot has no OpenAI API key yet, so it cannot answer. Add your key in WPiko Chatbot → API Key. (Visitors see a generic error message.)', 'wpiko-chatbot'),
            'apiKeyUrl' => add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'api_key', '_wpnonce' => wp_create_nonce('wpiko_chatbot_tab_nonce')), admin_url('admin.php')),
            'apiKeyLabel' => __('Add your API key', 'wpiko-chatbot'),
        );
    }

    $script_data = apply_filters('wpiko_chatbot_frontend_script_data', $script_data);

    wp_localize_script('wpiko-chatbot-js', 'wpikoChatbot', $script_data);
}
add_action('wpiko_chatbot_enqueue_frontend_assets', 'wpiko_chatbot_localize_script');

// Function to encrypt the API key
function wpiko_chatbot_encrypt_api_key($api_key)
{
    if (empty($api_key))
        return '';

    $encryption_key = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $encryption_key, 0, $iv);

    return base64_encode($encrypted . '::' . $iv);
}

// Function to decrypt the API key
function wpiko_chatbot_decrypt_api_key($encrypted_api_key)
{
    if (empty($encrypted_api_key))
        return '';

    $encryption_key = wp_salt('auth');
    list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_api_key), 2);

    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $encryption_key, 0, $iv);
}

/**
 * Stop an admin AJAX request unless the current user can manage the plugin.
 *
 * The 'wpiko_chatbot_nonce' nonce is also printed on the front end for the chat
 * widget, so a valid nonce alone never proves the request comes from an admin.
 * Every admin-only AJAX handler must call this after verifying its nonce.
 *
 * @param string $capability Capability required. Default 'manage_options'.
 * @return void
 */
function wpiko_chatbot_require_admin_ajax($capability = 'manage_options')
{
    if (!current_user_can($capability)) {
        wp_send_json_error(array('message' => __('You do not have permission to do this.', 'wpiko-chatbot')), 403);
    }
}

/**
 * Check if WPiko Chatbot Pro is active
 * 
 * @return bool Whether the pro plugin is active
 */
function wpiko_chatbot_is_pro_plugin_active()
{
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active('wpiko-chatbot-pro/wpiko-chatbot-pro.php');
}

/**
 * Check if WooCommerce is active
 */
function wpiko_chatbot_is_woocommerce_active()
{
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return is_plugin_active('woocommerce/woocommerce.php');
}

/**
 * AJAX handler: Check for new messages in a conversation
 * Used for polling when admin takeover is active
 */
function wpiko_chatbot_check_new_messages() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    $thread_id = isset($_POST['thread_id']) ? sanitize_text_field(wp_unslash($_POST['thread_id'])) : '';
    $last_id = isset($_POST['last_message_id']) ? intval($_POST['last_message_id']) : 0;

    if (empty($thread_id) || !wpiko_chatbot_chat_session_is_bound($thread_id)) {
        wp_send_json_error(array('message' => 'Conversation unavailable.'));
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, role, message, timestamp, user_name FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE session_id = %s AND id > %d AND role = 'admin' ORDER BY id ASC",
        $thread_id,
        $last_id
    ));

    // Piggyback heartbeat: if the request includes a heartbeat flag, process it
    $heartbeat = isset($_POST['heartbeat']) ? sanitize_text_field(wp_unslash($_POST['heartbeat'])) : '';
    $has_heartbeat = ! empty($heartbeat);
    if ($has_heartbeat) {
        $heartbeat_status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'online';
        do_action('wpiko_chatbot_user_heartbeat', $thread_id, $heartbeat_status);
    }

    // Check if takeover is still active
    $is_takeover = apply_filters('wpiko_chatbot_skip_ai_response', false, $thread_id);

    // Check if user is online (enriched by pro plugin if available)
    $user_online = apply_filters('wpiko_chatbot_is_user_online', false, $thread_id);

    $response_data = array(
        'messages' => $messages ? $messages : array(),
        'human_takeover' => $is_takeover,
        'user_online' => $user_online,
    );

    $response_data = apply_filters('wpiko_chatbot_check_new_messages_response', $response_data, $thread_id, $messages, $last_id);

    wp_send_json_success($response_data);
}
add_action('wp_ajax_wpiko_chatbot_check_new_messages', 'wpiko_chatbot_check_new_messages');
add_action('wp_ajax_nopriv_wpiko_chatbot_check_new_messages', 'wpiko_chatbot_check_new_messages');

/**
 * AJAX handler: User heartbeat — signals the user is still online
 * Stores last_seen timestamp in conversation_meta (if pro plugin provides the table)
 */
function wpiko_chatbot_user_heartbeat() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    $thread_id = isset($_POST['thread_id']) ? sanitize_text_field(wp_unslash($_POST['thread_id'])) : '';

    if (empty($thread_id) || !wpiko_chatbot_chat_session_is_bound($thread_id)) {
        wp_send_json_error(array('message' => 'Conversation unavailable.'));
        return;
    }

    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'online';

    // Use the pro plugin's meta table if available
    do_action('wpiko_chatbot_user_heartbeat', $thread_id, $status);

    wp_send_json_success(array('ok' => true));
}
add_action('wp_ajax_wpiko_chatbot_user_heartbeat', 'wpiko_chatbot_user_heartbeat');
add_action('wp_ajax_nopriv_wpiko_chatbot_user_heartbeat', 'wpiko_chatbot_user_heartbeat');

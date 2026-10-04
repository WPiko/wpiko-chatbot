<?php
/**
 * Optional feedback form shown when WPiko Chatbot is deactivated.
 *
 * Nothing is sent unless the admin chooses a reason and clicks
 * "Submit & deactivate". The site address and email are only included when
 * the admin ticks "You can email me about this" and confirms the email address.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPIKO_CHATBOT_FEEDBACK_ENDPOINT', 'https://wpiko.com/wp-json/wpiko-keymaster/v1/plugin-feedback');

/**
 * Remember when the plugin was first installed (used for feedback context).
 *
 * @return void
 */
function wpiko_chatbot_record_install_time()
{
    if (get_option('wpiko_chatbot_installed_at', false) === false) {
        add_option('wpiko_chatbot_installed_at', time(), '', false);
    }
}
register_activation_hook(WPIKO_CHATBOT_PLUGIN_FILE, 'wpiko_chatbot_record_install_time');
add_action('admin_init', 'wpiko_chatbot_record_install_time');

/**
 * Deactivation reasons shown in the form.
 *
 * @return array<string, array{label: string, prompt: string}>
 */
function wpiko_chatbot_deactivation_reasons()
{
    return array(
        'setup_failed' => array(
            'label' => __('I couldn\'t get it working', 'wpiko-chatbot'),
            'prompt' => __('What happened? Any error message helps us fix it.', 'wpiko-chatbot'),
        ),
        'openai_cost' => array(
            'label' => __('I don\'t want to pay for an OpenAI account', 'wpiko-chatbot'),
            'prompt' => '',
        ),
        'answer_quality' => array(
            'label' => __('The answers weren\'t good enough', 'wpiko-chatbot'),
            'prompt' => __('What did it get wrong?', 'wpiko-chatbot'),
        ),
        'missing_feature' => array(
            'label' => __('It\'s missing a feature I need', 'wpiko-chatbot'),
            'prompt' => __('Which feature?', 'wpiko-chatbot'),
        ),
        'slow_site' => array(
            'label' => __('It slowed down my site or broke something', 'wpiko-chatbot'),
            'prompt' => __('What did you notice?', 'wpiko-chatbot'),
        ),
        'found_better' => array(
            'label' => __('I found a better plugin', 'wpiko-chatbot'),
            'prompt' => __('Which one? We\'d love to know what it does better.', 'wpiko-chatbot'),
        ),
        'temporary' => array(
            'label' => __('It\'s temporary, I\'m troubleshooting', 'wpiko-chatbot'),
            'prompt' => '',
        ),
        'other' => array(
            'label' => __('Other', 'wpiko-chatbot'),
            'prompt' => __('Tell us a little more', 'wpiko-chatbot'),
        ),
    );
}

/**
 * Load the form assets on the Plugins screen.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function wpiko_chatbot_deactivation_feedback_assets($hook)
{
    if ($hook !== 'plugins.php' || !current_user_can('activate_plugins')) {
        return;
    }

    $reasons = array();
    foreach (wpiko_chatbot_deactivation_reasons() as $key => $reason) {
        $reasons[] = array('key' => $key, 'label' => $reason['label'], 'prompt' => $reason['prompt']);
    }

    wp_enqueue_style('wpiko-chatbot-deactivation-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/deactivation-feedback.css', array(), WPIKO_CHATBOT_VERSION);
    wp_enqueue_script('wpiko-chatbot-deactivation-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/deactivation-feedback.js', array(), WPIKO_CHATBOT_VERSION, true);
    wp_localize_script('wpiko-chatbot-deactivation-js', 'wpikoDeactivation', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_deactivation_feedback'),
        'pluginBasename' => plugin_basename(WPIKO_CHATBOT_PLUGIN_FILE),
        'supportUrl' => 'https://wpiko.com/support/',
        'contactEmail' => wp_get_current_user()->user_email,
        'siteHost' => wp_parse_url(home_url(), PHP_URL_HOST),
        'reasons' => $reasons,
        'i18n' => array(
            'title' => __('Quick question before you go', 'wpiko-chatbot'),
            'intro' => __('Why are you deactivating WPiko Chatbot? One click helps us make it better.', 'wpiko-chatbot'),
            'contact' => __('You can email me about this', 'wpiko-chatbot'),
            'emailLabel' => __('Reply to this email', 'wpiko-chatbot'),
            /* translators: %s: site host name. */
            'emailNote' => __('We\'ll also see your site address (%s) so we can look into it.', 'wpiko-chatbot'),
            'privacy' => __('We receive your answer plus the plugin, WordPress and PHP versions and how far setup got. Your email and site address are only sent if you tick the box above.', 'wpiko-chatbot'),
            'submit' => __('Submit & deactivate', 'wpiko-chatbot'),
            'skip' => __('Skip & deactivate', 'wpiko-chatbot'),
            'cancel' => __('Cancel', 'wpiko-chatbot'),
            'setupHelp' => __('Stuck on setup? Most problems are a missing OpenAI credit balance. Our support team can help for free:', 'wpiko-chatbot'),
            'setupHelpLink' => __('Get help', 'wpiko-chatbot'),
            'close' => __('Close', 'wpiko-chatbot'),
        ),
    ));
}
add_action('admin_enqueue_scripts', 'wpiko_chatbot_deactivation_feedback_assets');

/**
 * AJAX: forward the feedback to wpiko.com.
 *
 * @return void
 */
function wpiko_chatbot_deactivation_feedback_ajax()
{
    check_ajax_referer('wpiko_chatbot_deactivation_feedback', 'security');

    if (!current_user_can('activate_plugins')) {
        wp_send_json_error(null, 403);
    }

    $reasons = wpiko_chatbot_deactivation_reasons();
    $reason = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
    if (!isset($reasons[$reason])) {
        wp_send_json_error(array('message' => 'Unknown reason'), 400);
    }

    $details = isset($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
    $details = function_exists('mb_substr') ? mb_substr($details, 0, 2000) : substr($details, 0, 2000);
    $can_contact = isset($_POST['contact']) && sanitize_text_field(wp_unslash($_POST['contact'])) === '1';

    $last_test = get_option('wpiko_chatbot_last_connection_test', array());
    $health = function_exists('wpiko_chatbot_get_openai_health') ? wpiko_chatbot_get_openai_health() : array();
    $knowledge = function_exists('wpiko_chatbot_get_site_knowledge_state') ? wpiko_chatbot_get_site_knowledge_state() : array();
    $installed_at = (int) get_option('wpiko_chatbot_installed_at', 0);

    global $wpdb;
    $conversation_count = 0;
    $table = $wpdb->prefix . 'wpiko_chatbot_conversations';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $conversation_count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM `{$wpdb->prefix}wpiko_chatbot_conversations`");
    }

    $payload = array(
        'plugin' => 'wpiko-chatbot',
        'reason' => $reason,
        'details' => $details,
        'plugin_version' => WPIKO_CHATBOT_VERSION,
        'pro_active' => function_exists('wpiko_chatbot_is_pro_plugin_active') && wpiko_chatbot_is_pro_plugin_active(),
        'wp_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'locale' => get_locale(),
        'days_installed' => $installed_at ? (int) floor((time() - $installed_at) / DAY_IN_SECONDS) : null,
        'has_api_key' => function_exists('wpiko_chatbot_get_api_key') && wpiko_chatbot_get_api_key() !== '',
        'connection' => !empty($health['code']) ? $health['code'] : (isset($last_test['code']) ? $last_test['code'] : 'untested'),
        'setup_completed' => (bool) get_option('wpiko_chatbot_setup_completed', false),
        'floating_enabled' => (bool) get_option('wpiko_chatbot_enable_floating', false),
        'knowledge_pages' => isset($knowledge['pages']) ? (int) $knowledge['pages'] : 0,
        'conversations' => $conversation_count,
        'woocommerce' => function_exists('wpiko_chatbot_is_woocommerce_active') && wpiko_chatbot_is_woocommerce_active(),
    );

    if ($can_contact) {
        $contact_email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '';
        if (!is_email($contact_email)) {
            $contact_email = wp_get_current_user()->user_email;
        }
        $payload['contact_email'] = $contact_email;
        $payload['site_url'] = home_url('/');
    }

    // A short blocking request: non-blocking requests are not always delivered.
    // The browser moves on after a few seconds either way.
    wp_remote_post(WPIKO_CHATBOT_FEEDBACK_ENDPOINT, array(
        'timeout' => 4,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => wp_json_encode($payload),
    ));

    wp_send_json_success();
}
add_action('wp_ajax_wpiko_chatbot_deactivation_feedback', 'wpiko_chatbot_deactivation_feedback_ajax');

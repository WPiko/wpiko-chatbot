<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Get the navigation groups used by the plugin admin.
 *
 * Add-ons can register another group with the
 * `wpiko_chatbot_admin_tab_groups` filter.
 *
 * @return array<string, array<string, mixed>>
 */
function wpiko_chatbot_get_admin_tab_groups()
{
    $groups = array(
        'setup_ai' => array(
            'label' => 'Setup & AI',
            'order' => 10,
        ),
        'chat_experience' => array(
            'label' => 'Chat Experience',
            'order' => 20,
        ),
        'manage_troubleshoot' => array(
            'label' => 'Manage & Tools',
            'order' => 30,
        ),
        'more' => array(
            'label' => 'More',
            'order' => 90,
        ),
    );

    $groups = apply_filters('wpiko_chatbot_admin_tab_groups', $groups);

    uasort($groups, function ($first_group, $second_group) {
        return (int) ($first_group['order'] ?? 100) <=> (int) ($second_group['order'] ?? 100);
    });

    return $groups;
}

/**
 * Get all core and add-on admin tabs from one normalized registry.
 *
 * The existing `wpiko_chatbot_admin_tabs` filter remains available for
 * backward compatibility. Add-ons may optionally provide `group`, `order`,
 * `capability`, and `separator_before` values in addition to label and icon.
 *
 * @return array<string, array<string, mixed>>
 */
function wpiko_chatbot_get_admin_tabs()
{
    $tabs = array(
        'dashboard' => array(
            'label' => 'Dashboard',
            'icon' => 'dashicons-dashboard',
            'group' => '',
            'order' => 0,
        ),
        'api_key' => array(
            'label' => 'API Key',
            'icon' => 'dashicons-admin-network',
            'group' => 'setup_ai',
            'order' => 10,
        ),
        'ai_configuration' => array(
            'label' => 'AI Configuration',
            'icon' => 'dashicons-admin-generic',
            'group' => 'setup_ai',
            'order' => 20,
        ),
        'floating_chatbot' => array(
            'label' => 'Floating Chatbot',
            'icon' => 'dashicons-admin-settings',
            'group' => 'chat_experience',
            'order' => 10,
        ),
        'proactive_greeting' => array(
            'label' => 'Proactive Greeting',
            'icon' => 'dashicons-format-status',
            'group' => 'chat_experience',
            'order' => 20,
        ),
        'shortcode_chatbot' => array(
            'label' => 'Shortcode Chatbot',
            'icon' => 'dashicons-shortcode',
            'group' => 'chat_experience',
            'order' => 30,
        ),
        'chatbot_style' => array(
            'label' => 'Chatbot Style',
            'icon' => 'dashicons-admin-customizer',
            'group' => 'chat_experience',
            'order' => 40,
        ),
        'chatbot_menu' => array(
            'label' => 'Chatbot Menu',
            'icon' => 'dashicons-menu',
            'group' => 'chat_experience',
            'order' => 50,
        ),
        'questions' => array(
            'label' => 'Pre-made Questions',
            'icon' => 'dashicons-editor-help',
            'group' => 'chat_experience',
            'order' => 60,
        ),
        'error_messages' => array(
            'label' => 'Error Messages',
            'icon' => 'dashicons-warning',
            'group' => 'chat_experience',
            'order' => 70,
        ),
        'conversations' => array(
            'label' => 'Conversations',
            'icon' => 'dashicons-format-chat',
            'group' => 'manage_troubleshoot',
            'order' => 10,
        ),
        'user_limits' => array(
            'label' => 'User Limits',
            'icon' => 'dashicons-shield',
            'group' => 'manage_troubleshoot',
            'order' => 20,
        ),
        'debug_log' => array(
            'label' => 'Debug Log',
            'icon' => 'dashicons-admin-tools',
            'group' => 'manage_troubleshoot',
            'order' => 30,
        ),
    );

    $additional_tabs = apply_filters('wpiko_chatbot_admin_tabs', array());
    $additional_order = 100;

    foreach ($additional_tabs as $tab_id => $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $tabs[$tab_id] = wp_parse_args(
            $tab,
            array(
                'label' => ucfirst(str_replace('_', ' ', $tab_id)),
                'icon' => 'dashicons-plus',
                'group' => 'more',
                'order' => $additional_order,
            )
        );
        $additional_order += 10;
    }

    $tabs = apply_filters('wpiko_chatbot_admin_tab_registry', $tabs);
    $groups = wpiko_chatbot_get_admin_tab_groups();

    uasort($tabs, function ($first_tab, $second_tab) use ($groups) {
        $first_group = $first_tab['group'] ?? '';
        $second_group = $second_tab['group'] ?? '';
        $first_group_order = $first_group === '' ? 0 : (int) ($groups[$first_group]['order'] ?? 90);
        $second_group_order = $second_group === '' ? 0 : (int) ($groups[$second_group]['order'] ?? 90);

        if ($first_group_order !== $second_group_order) {
            return $first_group_order <=> $second_group_order;
        }

        return (int) ($first_tab['order'] ?? 100) <=> (int) ($second_tab['order'] ?? 100);
    });

    return $tabs;
}

/**
 * Get the small set of shortcuts shown in the WordPress admin submenu.
 *
 * Detailed navigation remains in the plugin's categorized sidebar.
 *
 * @return array<string, string>
 */
function wpiko_chatbot_get_admin_menu_shortcuts()
{
    return apply_filters(
        'wpiko_chatbot_admin_menu_shortcuts',
        array(
            'dashboard' => 'Dashboard',
            'conversations' => 'Conversations',
            'ai_configuration' => 'Settings',
        )
    );
}

// Add Admin Menu
function wpiko_chatbot_admin_menu()
{
    add_menu_page(
        'WPiko Chatbot',
        'WPiko Chatbot',
        'manage_options',
        'ai-chatbot',
        'wpiko_chatbot_admin_page',
        'dashicons-format-chat',
        20
    );

    $admin_tabs = wpiko_chatbot_get_admin_tabs();
    $menu_shortcuts = wpiko_chatbot_get_admin_menu_shortcuts();

    foreach ($menu_shortcuts as $tab_id => $menu_label) {
        if (!isset($admin_tabs[$tab_id])) {
            continue;
        }

        $tab = $admin_tabs[$tab_id];
        $label = !empty($menu_label)
            ? $menu_label
            : (isset($tab['label']) ? $tab['label'] : ucfirst(str_replace('_', ' ', $tab_id)));
        $capability = isset($tab['capability']) ? $tab['capability'] : 'manage_options';

        add_submenu_page(
            'ai-chatbot',
            $label,
            $label,
            $capability,
            'ai-chatbot&tab=' . $tab_id,
            'wpiko_chatbot_admin_page'
        );
    }

    // Remove the default submenu item
    remove_submenu_page('ai-chatbot', 'ai-chatbot');
}
add_action('admin_menu', 'wpiko_chatbot_admin_menu');

// Enqueue admin scripts and styles
function wpiko_chatbot_admin_enqueue_scripts($hook)
{
    if ($hook !== 'toplevel_page_ai-chatbot') {
        return;
    }

    // Get plugin version from the main plugin file for versioning
    // Use defined version constant with dynamic cache-busting
    $version = WPIKO_CHATBOT_VERSION;
    $version = apply_filters('wpiko_chatbot_asset_version', $version);
    $admin_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/admin-style.css') ?: $version;
    $admin_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/admin-script.js') ?: $version;

    wp_enqueue_media();
    wp_enqueue_style('dashicons');
    wp_enqueue_style('wpiko-chatbot-admin-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/admin-style.css', array(), $admin_style_version);
    wp_enqueue_style('wpiko-chatbot-dashboard-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/dashboard-style.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-plugin-header-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/plugin-header.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-ai-configuration-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/ai-configuration.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-conversation-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/conversation-style.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-conversation-mobile-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/conversation-mobile-style.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-modal-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/modal-style.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-file-management-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/file-management.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-debug-log-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/debug-log-style.css', array(), $version);
    wp_enqueue_style('wpiko-chatbot-style-tab-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/chatbot-style.css', array('wpiko-chatbot-admin-css'), $version);

    wp_enqueue_script('wpiko-chatbot-modal-handlers', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/modal-handlers.js', array('jquery'), $version, true);
    wp_enqueue_script('wpiko-chatbot-dashboard-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/dashboard.js', array('jquery'), $version, true);

    wp_enqueue_script('wpiko-chatbot-responses-api-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/responses-api.js', array('jquery'), $version, true);
    wp_enqueue_script('wpiko-chatbot-models-info-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/models-info.js', array('jquery'), $version, true);

    wp_localize_script('wpiko-chatbot-responses-api-js', 'wpikoChatbotAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'apiType' => 'responses'
    ));
    wp_enqueue_script('wpiko-chatbot-conversations-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/conversations.js', array('jquery'), $version, true);
    wp_enqueue_script('wpiko-chatbot-file-management-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/file-management.js', array('jquery'), $version, true);

    wp_enqueue_script('wpiko-chatbot-files-list-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/files-list.js', array('jquery'), $version, true);

    wp_enqueue_script('wpiko-chatbot-admin-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery'), $admin_script_version, true);
    wp_localize_script('wpiko-chatbot-admin-js', 'wpikoChatbotAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'apiType' => 'responses'
    ));

    wp_enqueue_script('wpiko-chatbot-style-tab-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/chatbot-style.js', array(), $version, true);
    wp_localize_script('wpiko-chatbot-style-tab-js', 'wpikoChatbotStyle', array(
        'presets' => wpiko_chatbot_get_style_presets_for_js(),
    ));
}
add_action('admin_enqueue_scripts', 'wpiko_chatbot_admin_enqueue_scripts');

// Functions to load File Management callback
function wpiko_chatbot_load_file_management_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    ob_start();
    include WPIKO_CHATBOT_PLUGIN_DIR . 'admin/templates/file-management.php';
    $content = ob_get_clean();

    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_file_management', 'wpiko_chatbot_load_file_management_callback');

// Functions to load Scan Website callback
function wpiko_chatbot_load_scan_website_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    // Allow Pro plugin to handle this earlier with priority 20
    if (!did_action('wp_ajax_wpiko_chatbot_load_scan_website')) {
        // Return empty content, Pro plugin will handle this
        wp_send_json_success('');
    }
}
add_action('wp_ajax_wpiko_chatbot_load_scan_website', 'wpiko_chatbot_load_scan_website_callback', 30);

// Functions to load QA Management callback
function wpiko_chatbot_load_qa_management_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    // Allow Pro plugin to handle this earlier with priority 20
    if (!did_action('wp_ajax_wpiko_chatbot_load_qa_management')) {
        // Return empty content, Pro plugin will handle this
        wp_send_json_success('');
    }
}
add_action('wp_ajax_wpiko_chatbot_load_qa_management', 'wpiko_chatbot_load_qa_management_callback', 30);

// Functions to load Woocommerce Integration callback
function wpiko_chatbot_load_woocommerce_integration_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');

    // Allow Pro plugin to handle this earlier with priority 20
    if (!did_action('wp_ajax_wpiko_chatbot_load_woocommerce_integration')) {
        // Return empty content, Pro plugin will handle this
        wp_send_json_success('');
    }
}
add_action('wp_ajax_wpiko_chatbot_load_woocommerce_integration', 'wpiko_chatbot_load_woocommerce_integration_callback', 30);

// Main admin page function
function wpiko_chatbot_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verify nonce if tab parameter is set
    if (isset($_GET['tab'])) {
        $nonce_verified = isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'wpiko_chatbot_tab_nonce');
        if (!$nonce_verified && !wp_doing_ajax()) {
            // Still allow tab switching but log it for debugging
            wpiko_chatbot_log('Tab switching without nonce verification', 'warning');
        }
    }

    // Properly unslash and sanitize tab parameter
    $wpiko_active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'dashboard';

    // Helper function to generate secure tab URLs
    $wpiko_get_tab_url = function ($tab) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'page' => 'ai-chatbot',
                    'tab' => $tab
                ),
                admin_url('admin.php')
            ),
            'wpiko_chatbot_tab_nonce'
        );
    };

    // Use the wrapper template that includes the header and navigation
    include WPIKO_CHATBOT_PLUGIN_DIR . 'admin/templates/admin-wrapper.php';
}
?>

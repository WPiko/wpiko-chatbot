<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/modal-shell.php';

/**
 * Oldest WPiko AI Chatbot Pro release that works with this admin design.
 */
if (!defined('WPIKO_CHATBOT_MIN_PRO_VERSION')) {
    define('WPIKO_CHATBOT_MIN_PRO_VERSION', '2.1.2');
}

/**
 * Ask admins to update WPiko AI Chatbot Pro when an older release is active.
 * Pro updates arrive separately (from GitHub), so for a while a site can run
 * this version with an older Pro.
 */
function wpiko_chatbot_outdated_pro_notice()
{
    if (!defined('WPIKO_CHATBOT_PRO_VERSION') || version_compare(WPIKO_CHATBOT_PRO_VERSION, WPIKO_CHATBOT_MIN_PRO_VERSION, '>=')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && !in_array($screen->id, array('toplevel_page_ai-chatbot', 'plugins', 'dashboard', 'update-core'), true)) {
        return;
    }
    ?>
    <div class="notice notice-warning">
        <p>
            <?php
            echo esc_html(sprintf(
                /* translators: 1: installed Pro version, 2: required Pro version. */
                __('WPiko AI Chatbot Pro %1$s is older than this version of WPiko AI Chatbot. Please update WPiko AI Chatbot Pro to %2$s or newer so its settings and training tools match the new admin.', 'wpiko-chatbot'),
                WPIKO_CHATBOT_PRO_VERSION,
                WPIKO_CHATBOT_MIN_PRO_VERSION
            ));
            ?>
            <a href="<?php echo esc_url(admin_url('plugins.php')); ?>"><?php esc_html_e('Go to Plugins', 'wpiko-chatbot'); ?></a>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'wpiko_chatbot_outdated_pro_notice');

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
        'monitor' => array(
            'label' => __('Monitor', 'wpiko-chatbot'),
            'order' => 10,
        ),
        'ai' => array(
            'label' => __('AI & Training', 'wpiko-chatbot'),
            'order' => 20,
        ),
        'chat_widget' => array(
            'label' => __('Chat Widget', 'wpiko-chatbot'),
            'order' => 30,
        ),
        'leads_sales' => array(
            'label' => __('Leads & Sales', 'wpiko-chatbot'),
            'order' => 40,
        ),
        'advanced' => array(
            'label' => __('Advanced', 'wpiko-chatbot'),
            'order' => 50,
        ),
        'more' => array(
            'label' => __('More', 'wpiko-chatbot'),
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
 * Get the sidebar entries that combine several screens behind sub-tabs.
 *
 * A tab joins one of these by setting `section` (and optionally
 * `section_label` for its sub-tab). The sidebar shows the section once and
 * links it to its first screen; the screens show a sub-tab row.
 * Add-ons can register more with the `wpiko_chatbot_admin_nav_sections` filter.
 *
 * @return array<string, array<string, mixed>>
 */
function wpiko_chatbot_get_admin_nav_sections()
{
    $sections = array(
        'ai_settings' => array(
            'label' => __('AI Settings', 'wpiko-chatbot'),
            'icon' => 'dashicons-admin-generic',
            'group' => 'ai',
            'order' => 10,
        ),
        'placement' => array(
            'label' => __('Placement', 'wpiko-chatbot'),
            'icon' => 'wpiko-placement',
            'group' => 'chat_widget',
            'order' => 10,
        ),
        'appearance' => array(
            'label' => __('Appearance', 'wpiko-chatbot'),
            'icon' => 'dashicons-admin-customizer',
            'group' => 'chat_widget',
            'order' => 20,
        ),
        'messages' => array(
            'label' => __('Messages', 'wpiko-chatbot'),
            'icon' => 'dashicons-format-status',
            'group' => 'chat_widget',
            'order' => 30,
        ),
    );

    return apply_filters('wpiko_chatbot_admin_nav_sections', $sections);
}

/**
 * Get all core and add-on admin tabs from one normalized registry.
 *
 * The existing `wpiko_chatbot_admin_tabs` filter remains available for
 * backward compatibility. Add-ons may optionally provide `group`, `order`,
 * `capability`, `section`, `section_label`, `pro` and `nav` values in
 * addition to label and icon. `nav` may be `false` (reachable by URL only)
 * or `'footer'` (small link at the bottom of the sidebar).
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
        'setup' => array(
            'label' => 'Setup Wizard',
            'icon' => 'dashicons-flag',
            'group' => '',
            'order' => 1,
        ),
        'conversations' => array(
            'label' => 'Conversations',
            'icon' => 'dashicons-format-chat',
            'group' => 'monitor',
            'order' => 10,
        ),
        'ai_configuration' => array(
            'label' => 'AI Configuration',
            'icon' => 'dashicons-admin-generic',
            'group' => 'ai',
            'section' => 'ai_settings',
            'section_label' => __('Model & Knowledge', 'wpiko-chatbot'),
            'order' => 10,
        ),
        'api_key' => array(
            'label' => 'API Key',
            'icon' => 'dashicons-admin-network',
            'group' => 'ai',
            'section' => 'ai_settings',
            'section_label' => __('API Connection', 'wpiko-chatbot'),
            'order' => 20,
        ),
        'floating_chatbot' => array(
            'label' => 'Floating Chatbot',
            'icon' => 'dashicons-admin-settings',
            'group' => 'chat_widget',
            'section' => 'placement',
            'section_label' => __('Floating Chatbot', 'wpiko-chatbot'),
            'order' => 10,
        ),
        'shortcode_chatbot' => array(
            'label' => 'Shortcode Chatbot',
            'icon' => 'dashicons-shortcode',
            'group' => 'chat_widget',
            'section' => 'placement',
            'section_label' => __('Shortcode', 'wpiko-chatbot'),
            'order' => 20,
        ),
        'chatbot_style' => array(
            'label' => 'Chatbot Style',
            'icon' => 'dashicons-admin-customizer',
            'group' => 'chat_widget',
            'section' => 'appearance',
            'section_label' => __('Style', 'wpiko-chatbot'),
            'order' => 10,
        ),
        'chatbot_menu' => array(
            'label' => 'Chatbot Menu',
            'icon' => 'dashicons-menu',
            'group' => 'chat_widget',
            'section' => 'appearance',
            'section_label' => __('Menu Options', 'wpiko-chatbot'),
            'order' => 20,
        ),
        'proactive_greeting' => array(
            'label' => 'Proactive Greeting',
            'icon' => 'dashicons-format-status',
            'group' => 'chat_widget',
            'section' => 'messages',
            'section_label' => __('Greeting', 'wpiko-chatbot'),
            'order' => 10,
        ),
        'questions' => array(
            'label' => 'Pre-made Questions',
            'icon' => 'dashicons-editor-help',
            'group' => 'chat_widget',
            'section' => 'messages',
            'section_label' => __('Pre-made Questions', 'wpiko-chatbot'),
            'order' => 20,
        ),
        'error_messages' => array(
            'label' => 'Error Messages',
            'icon' => 'dashicons-warning',
            'group' => 'chat_widget',
            'section' => 'messages',
            'section_label' => __('Error Messages', 'wpiko-chatbot'),
            'order' => 30,
        ),
        'user_limits' => array(
            'label' => 'User Limits',
            'icon' => 'dashicons-shield',
            'group' => 'advanced',
            'order' => 10,
        ),
        'debug_log' => array(
            'label' => 'Debug Log',
            'icon' => 'dashicons-admin-tools',
            'group' => 'advanced',
            'order' => 20,
        ),
    );

    // The wizard leaves the menu once setup is done. It stays reachable from
    // the Dashboard ("Run setup again") and comes back if the API key is removed.
    if (function_exists('wpiko_chatbot_setup_is_complete') && wpiko_chatbot_setup_is_complete()) {
        unset($tabs['setup']);
    }

    $additional_tabs = apply_filters('wpiko_chatbot_admin_tabs', array());
    $additional_order = 100;

    // Groups used before the navigation was reorganized.
    $legacy_groups = array(
        'setup_ai' => 'ai',
        'chat_experience' => 'chat_widget',
        'manage_troubleshoot' => 'advanced',
    );

    // Older Pro versions put every Pro screen in one "Pro Features" group.
    // Place those screens with the features they belong to instead.
    $legacy_pro_tabs = array(
        'analytics' => array('group' => 'monitor', 'order' => 20),
        'pwa_settings' => array('group' => 'monitor', 'order' => 30),
        'email_capture' => array('group' => 'leads_sales', 'order' => 10),
        'contact_form' => array('group' => 'leads_sales', 'order' => 20),
        'product_card' => array('group' => 'leads_sales', 'order' => 30),
        'license_activation' => array('group' => 'more', 'order' => 90, 'nav' => 'footer'),
    );

    foreach ($additional_tabs as $tab_id => $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $tab = wp_parse_args(
            $tab,
            array(
                'label' => ucfirst(str_replace('_', ' ', $tab_id)),
                'icon' => 'dashicons-plus',
                'group' => 'more',
                'order' => $additional_order,
            )
        );

        if (isset($legacy_groups[$tab['group']])) {
            $tab['group'] = $legacy_groups[$tab['group']];
        }

        if ($tab['group'] === 'pro_features') {
            $tab['pro'] = true;
            if (isset($legacy_pro_tabs[$tab_id])) {
                $tab = array_merge($tab, $legacy_pro_tabs[$tab_id]);
            }
            unset($tab['separator_before']);
        }

        $tabs[$tab_id] = $tab;
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
 * Build a nonce-protected URL for one admin screen.
 *
 * @param string $tab_id Tab ID.
 * @return string
 */
function wpiko_chatbot_get_admin_tab_url($tab_id)
{
    return wp_nonce_url(
        add_query_arg(array('page' => 'ai-chatbot', 'tab' => $tab_id), admin_url('admin.php')),
        'wpiko_chatbot_tab_nonce'
    );
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
        'WPiko AI Chatbot',
        'WPiko AI Chatbot',
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

/**
 * Place the shared header above page content and admin notices, as in wpiko SEO.
 */
function wpiko_chatbot_admin_header()
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'toplevel_page_ai-chatbot' || !current_user_can('manage_options')) {
        return;
    }

    require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/plugin-header.php';
    wpiko_chatbot_render_plugin_header();
}
add_action('in_admin_header', 'wpiko_chatbot_admin_header');

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
    $header_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/plugin-header.css') ?: $version;
    $header_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/plugin-header.js') ?: $version;
    $design_tokens_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/design-tokens.css') ?: $version;
    $dashboard_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/dashboard-style.css') ?: $version;
    $dashboard_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/dashboard.js') ?: $version;
    $settings_controls_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/settings-controls.css') ?: $version;
    $debug_log_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/debug-log-style.css') ?: $version;
    $api_key_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/api-key.css') ?: $version;
    $ai_configuration_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/ai-configuration.css') ?: $version;
    $chatbot_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/chatbot-style.css') ?: $version;
    $conversations_admin_style_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/conversations-admin.css') ?: $version;
    $conversations_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/conversations.js') ?: $version;
    $responses_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/responses-api.js') ?: $version;

    wp_enqueue_media();
    wp_enqueue_style('dashicons');

    // In-page success/error messages (replaces alert() popups). Pro scripts use it too.
    $notices_script_version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/admin-notices.js') ?: $version;
    wp_enqueue_script('wpiko-chatbot-admin-notices', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/admin-notices.js', array(), $notices_script_version, true);
    wp_enqueue_style('wpiko-chatbot-design-tokens', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/design-tokens.css', array(), $design_tokens_version);
    wp_enqueue_style('wpiko-chatbot-admin-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/admin-style.css', array('wpiko-chatbot-design-tokens'), $admin_style_version);
    wp_enqueue_style('wpiko-chatbot-settings-controls', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/settings-controls.css', array('wpiko-chatbot-admin-css'), $settings_controls_version);
    wp_enqueue_style('wpiko-chatbot-api-key-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/api-key.css', array('wpiko-chatbot-settings-controls'), $api_key_style_version);
    wp_enqueue_style('wpiko-chatbot-dashboard-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/dashboard-style.css', array('wpiko-chatbot-admin-css'), $dashboard_style_version);
    wp_enqueue_style('wpiko-chatbot-plugin-header-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/plugin-header.css', array('wpiko-chatbot-admin-css'), $header_style_version);
    wp_enqueue_script('wpiko-chatbot-plugin-header-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/plugin-header.js', array(), $header_script_version, true);
    wp_enqueue_style('wpiko-chatbot-ai-configuration-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/ai-configuration.css', array('wpiko-chatbot-settings-controls', 'wpiko-chatbot-setup-wizard-css'), $ai_configuration_style_version);
    wp_enqueue_style('wpiko-chatbot-conversation-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/conversation-style.css', array('wpiko-chatbot-settings-controls'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/conversation-style.css') ?: $version);
    wp_enqueue_style('wpiko-chatbot-conversation-mobile-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/conversation-mobile-style.css', array('wpiko-chatbot-settings-controls'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/conversation-mobile-style.css') ?: $version);
    wp_enqueue_style('wpiko-chatbot-conversations-admin-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/conversations-admin.css', array('wpiko-chatbot-settings-controls', 'wpiko-chatbot-conversation-css', 'wpiko-chatbot-conversation-mobile-css'), $conversations_admin_style_version);
    wp_enqueue_style('wpiko-chatbot-modal-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/modal-style.css', array('wpiko-chatbot-settings-controls'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/modal-style.css') ?: $version);
    wp_enqueue_style('wpiko-chatbot-file-management-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/file-management.css', array('wpiko-chatbot-settings-controls'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/file-management.css') ?: $version);
    wp_enqueue_style('wpiko-chatbot-debug-log-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/debug-log-style.css', array('wpiko-chatbot-settings-controls'), $debug_log_style_version);
    wp_enqueue_style('wpiko-chatbot-style-tab-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/chatbot-style.css', array('wpiko-chatbot-settings-controls'), $chatbot_style_version);

    wp_enqueue_script('wpiko-chatbot-modal-handlers', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/modal-handlers.js', array('jquery'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/modal-handlers.js') ?: $version, true);
    wp_enqueue_script('wpiko-chatbot-dashboard-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/dashboard.js', array('jquery'), $dashboard_script_version, true);

    wp_enqueue_script('wpiko-chatbot-responses-api-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/responses-api.js', array('jquery', 'wpiko-chatbot-admin-notices'), $responses_script_version, true);
    wp_enqueue_script('wpiko-chatbot-models-info-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/models-info.js', array('jquery'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/models-info.js') ?: $version, true);

    wp_localize_script('wpiko-chatbot-responses-api-js', 'wpikoChatbotAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'apiType' => 'responses'
    ));
    wp_enqueue_script('wpiko-chatbot-conversations-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/conversations.js', array('jquery', 'wpiko-chatbot-admin-notices'), $conversations_script_version, true);
    wp_enqueue_script('wpiko-chatbot-file-management-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/file-management.js', array('jquery', 'wpiko-chatbot-admin-notices', 'wpiko-chatbot-modal-handlers'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/file-management.js') ?: $version, true);

    wp_enqueue_script('wpiko-chatbot-files-list-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/files-list.js', array('jquery', 'wpiko-chatbot-admin-notices', 'wpiko-chatbot-modal-handlers'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/files-list.js') ?: $version, true);

    wp_enqueue_script('wpiko-chatbot-admin-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/admin-script.js', array('jquery', 'wpiko-chatbot-admin-notices'), $admin_script_version, true);
    wp_localize_script('wpiko-chatbot-admin-js', 'wpikoChatbotAdmin', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'apiType' => 'responses'
    ));

    wp_enqueue_style('wpiko-chatbot-setup-wizard-css', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/setup-wizard.css', array('wpiko-chatbot-settings-controls'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/setup-wizard.css') ?: $version);

    wp_enqueue_script('wpiko-chatbot-site-knowledge-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/site-knowledge.js', array('jquery'), $version, true);
    wp_localize_script('wpiko-chatbot-site-knowledge-js', 'wpikoSiteKnowledge', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'i18n' => array(
            'homepage' => __('Homepage', 'wpiko-chatbot'),
            /* translators: %d: number of words. */
            'words' => __('%d words', 'wpiko-chatbot'),
            /* translators: 1: selected pages, 2: page limit. */
            'selected' => __('%1$d of %2$d selected', 'wpiko-chatbot'),
            'loadError' => __('Could not load your pages. Refresh the page to try again.', 'wpiko-chatbot'),
            'noPages' => __('No published pages found. Publish a page (for example "About" or "FAQ"), or upload a document in File Management.', 'wpiko-chatbot'),
            'building' => __('Reading your pages and teaching your chatbot. This can take up to a minute…', 'wpiko-chatbot'),
            'buildError' => __('Something went wrong. Check the Debug Log, then try again.', 'wpiko-chatbot'),
            'buildButton' => __('Teach my chatbot these pages', 'wpiko-chatbot'),
            'updateButton' => __('Update what my chatbot knows', 'wpiko-chatbot'),
            'removeButton' => __('Remove these pages from my chatbot', 'wpiko-chatbot'),
            'removing' => __('Removing the pages from your chatbot…', 'wpiko-chatbot'),
            /* translators: %s: page titles. */
            'nowCovered' => __('Updated: %s now covered by Scan Website, so removed from Quick learn.', 'wpiko-chatbot'),
            /* translators: 1: number of pages, 2: time ago. */
            'builtStatus' => __('Your chatbot knows %1$s pages (updated %2$s ago).', 'wpiko-chatbot'),
            /* translators: 1: number of pages (always 1), 2: time ago. */
            'builtStatusOne' => __('Your chatbot knows %1$s page (updated %2$s ago).', 'wpiko-chatbot'),
            'stale' => __('Some of these pages changed since your chatbot last learned them. Click "Update" to refresh its knowledge.', 'wpiko-chatbot'),
            'learnedBadge' => __('Learned', 'wpiko-chatbot'),
            'notLearned' => __('Your chatbot has not learned any pages yet.', 'wpiko-chatbot'),
            'pending' => __('Not saved yet. Click the button to update your chatbot.', 'wpiko-chatbot'),
            'pendingFirst' => __('Not learned yet. Click the button to teach your chatbot.', 'wpiko-chatbot'),
        ),
    ));

    wp_enqueue_script('wpiko-chatbot-setup-wizard-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/setup-wizard.js', array('jquery', 'wpiko-chatbot-site-knowledge-js', 'wpiko-chatbot-admin-notices'), filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/js/setup-wizard.js') ?: $version, true);
    wp_localize_script('wpiko-chatbot-setup-wizard-js', 'wpikoSetupWizard', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpiko_chatbot_nonce'),
        'i18n' => array(
            'testing' => __('Testing…', 'wpiko-chatbot'),
            'testingLong' => __('Sending a tiny test message to OpenAI…', 'wpiko-chatbot'),
            'enterKey' => __('Paste your OpenAI API key first.', 'wpiko-chatbot'),
            'thinking' => __('Thinking…', 'wpiko-chatbot'),
            'genericError' => __('Something went wrong. Please try again.', 'wpiko-chatbot'),
        ),
    ));

    wp_enqueue_script('wpiko-chatbot-style-tab-js', WPIKO_CHATBOT_PLUGIN_URL . 'admin/js/chatbot-style.js', array(), $version, true);
    wp_localize_script('wpiko-chatbot-style-tab-js', 'wpikoChatbotStyle', array(
        'presets' => wpiko_chatbot_get_style_presets_for_js(),
    ));
}
add_action('admin_enqueue_scripts', 'wpiko_chatbot_admin_enqueue_scripts');

/**
 * Load the shared message styles after every other admin stylesheet,
 * including the Pro add-on's, so success and error messages look the same
 * on every screen (matching wpiko SEO).
 *
 * @param string $hook Current admin page hook.
 */
function wpiko_chatbot_admin_enqueue_notice_styles($hook)
{
    if ($hook !== 'toplevel_page_ai-chatbot') {
        return;
    }

    $version = filemtime(WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/notices.css') ?: WPIKO_CHATBOT_VERSION;
    wp_enqueue_style('wpiko-chatbot-notices', WPIKO_CHATBOT_PLUGIN_URL . 'admin/css/notices.css', array('wpiko-chatbot-design-tokens'), $version);
}
add_action('admin_enqueue_scripts', 'wpiko_chatbot_admin_enqueue_notice_styles', 100);

// Functions to load File Management callback
function wpiko_chatbot_load_file_management_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    ob_start();
    include WPIKO_CHATBOT_PLUGIN_DIR . 'admin/templates/file-management.php';
    $content = ob_get_clean();

    wp_send_json_success($content);
}
add_action('wp_ajax_wpiko_chatbot_load_file_management', 'wpiko_chatbot_load_file_management_callback');


// Functions to load Woocommerce Integration callback
function wpiko_chatbot_load_woocommerce_integration_callback()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

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

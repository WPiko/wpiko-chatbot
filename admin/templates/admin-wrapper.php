<?php
/**
 * Main Admin Page Wrapper Template
 * 
 * @package WPiko_Chatbot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include header component
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/plugin-header.php';

// Get the template name and other variables
$wpiko_active_tab = isset($wpiko_active_tab) ? $wpiko_active_tab : 'api_key';
$wpiko_get_tab_url = isset($wpiko_get_tab_url) ? $wpiko_get_tab_url : function($tab) {
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

$wpiko_admin_tabs = wpiko_chatbot_get_admin_tabs();
$wpiko_admin_tab_groups = wpiko_chatbot_get_admin_tab_groups();
$wpiko_standalone_tabs = array();
$wpiko_grouped_tabs = array();

foreach ($wpiko_admin_tabs as $wpiko_tab_id => $wpiko_tab) {
    $wpiko_capability = isset($wpiko_tab['capability']) ? $wpiko_tab['capability'] : 'manage_options';
    if (!current_user_can($wpiko_capability)) {
        continue;
    }

    $wpiko_group_id = isset($wpiko_tab['group']) ? $wpiko_tab['group'] : 'more';
    if ($wpiko_group_id === '') {
        $wpiko_standalone_tabs[$wpiko_tab_id] = $wpiko_tab;
        continue;
    }

    if (!isset($wpiko_admin_tab_groups[$wpiko_group_id])) {
        $wpiko_group_id = 'more';
    }

    if (!isset($wpiko_grouped_tabs[$wpiko_group_id])) {
        $wpiko_grouped_tabs[$wpiko_group_id] = array();
    }

    $wpiko_grouped_tabs[$wpiko_group_id][$wpiko_tab_id] = $wpiko_tab;
}

// Render the plugin header
wpiko_chatbot_render_plugin_header();
?>

<div class="wrap">
    <div class="wpiko-chatbot-admin-container">
        <div class="wpiko-chatbot-nav">
            <!-- Mobile Navigation Toggle -->
            <button class="wpiko-mobile-nav-toggle" id="wpiko-mobile-nav-toggle" aria-expanded="false" aria-controls="wpiko-nav-menu">
                <span class="dashicons dashicons-menu"></span>
                <span class="wpiko-current-tab"><?php
                    $wpiko_current_tab_label = isset($wpiko_admin_tabs[$wpiko_active_tab]['label'])
                        ? $wpiko_admin_tabs[$wpiko_active_tab]['label']
                        : ucfirst(str_replace('_', ' ', $wpiko_active_tab));
                    echo esc_html($wpiko_current_tab_label);
                ?></span>
                Menu
                <span class="dashicons dashicons-arrow-down nav-arrow"></span>
            </button>

            <ul id="wpiko-nav-menu" class="wpiko-nav-root">
                <?php foreach ($wpiko_standalone_tabs as $wpiko_tab_id => $wpiko_tab) : ?>
                    <?php
                    $wpiko_is_active = $wpiko_active_tab === $wpiko_tab_id;
                    $wpiko_icon = isset($wpiko_tab['icon']) ? $wpiko_tab['icon'] : 'dashicons-plus';
                    ?>
                    <li class="wpiko-nav-standalone wpiko-nav-<?php echo esc_attr(sanitize_html_class($wpiko_tab_id)); ?>">
                        <a href="<?php echo esc_url($wpiko_get_tab_url($wpiko_tab_id)); ?>"
                            class="<?php echo $wpiko_is_active ? 'nav-tab-active' : ''; ?>"
                            <?php echo $wpiko_is_active ? 'aria-current="page"' : ''; ?>>
                            <span class="dashicons <?php echo esc_attr($wpiko_icon); ?>"></span>
                            <?php echo esc_html($wpiko_tab['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <li class="wpiko-nav-actions">
                    <button type="button"
                        id="wpiko-nav-toggle-all"
                        class="wpiko-nav-toggle-all"
                        data-expand-label="<?php echo esc_attr__('Expand all', 'wpiko-chatbot'); ?>"
                        data-collapse-label="<?php echo esc_attr__('Collapse all', 'wpiko-chatbot'); ?>">
                        <?php esc_html_e('Expand all', 'wpiko-chatbot'); ?>
                    </button>
                </li>

                <?php foreach ($wpiko_admin_tab_groups as $wpiko_group_id => $wpiko_group) : ?>
                    <?php
                    if (empty($wpiko_grouped_tabs[$wpiko_group_id])) {
                        continue;
                    }

                    $wpiko_group_tabs = $wpiko_grouped_tabs[$wpiko_group_id];
                    $wpiko_is_group_active = isset($wpiko_group_tabs[$wpiko_active_tab]);
                    $wpiko_group_dom_id = 'wpiko-nav-group-' . sanitize_html_class($wpiko_group_id);
                    $wpiko_group_classes = array('wpiko-nav-group');
                    if ($wpiko_is_group_active) {
                        $wpiko_group_classes[] = 'is-open';
                        $wpiko_group_classes[] = 'has-active-tab';
                    }
                    if (!empty($wpiko_group['class'])) {
                        $wpiko_group_classes[] = sanitize_html_class($wpiko_group['class']);
                    }
                    ?>
                    <li class="<?php echo esc_attr(implode(' ', $wpiko_group_classes)); ?>"
                        data-nav-group="<?php echo esc_attr($wpiko_group_id); ?>">
                        <button type="button"
                            class="wpiko-nav-group-toggle"
                            aria-expanded="<?php echo $wpiko_is_group_active ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr($wpiko_group_dom_id); ?>">
                            <span><?php echo esc_html($wpiko_group['label']); ?></span>
                            <?php if (!empty($wpiko_group['badge'])) : ?>
                                <span class="wpiko-nav-group-badge"><?php echo esc_html($wpiko_group['badge']); ?></span>
                            <?php endif; ?>
                            <span class="dashicons dashicons-arrow-down wpiko-nav-group-arrow" aria-hidden="true"></span>
                        </button>

                        <ul id="<?php echo esc_attr($wpiko_group_dom_id); ?>" class="wpiko-nav-group-items">
                            <?php foreach ($wpiko_group_tabs as $wpiko_tab_id => $wpiko_tab) : ?>
                                <?php
                                $wpiko_is_active = $wpiko_active_tab === $wpiko_tab_id;
                                $wpiko_icon = isset($wpiko_tab['icon']) ? $wpiko_tab['icon'] : 'dashicons-plus';
                                $wpiko_item_class = !empty($wpiko_tab['separator_before']) ? 'has-separator-before' : '';
                                ?>
                                <li class="<?php echo esc_attr($wpiko_item_class); ?>">
                                    <a href="<?php echo esc_url($wpiko_get_tab_url($wpiko_tab_id)); ?>"
                                        class="<?php echo $wpiko_is_active ? 'nav-tab-active' : ''; ?>"
                                        <?php echo $wpiko_is_active ? 'aria-current="page"' : ''; ?>>
                                        <span class="dashicons <?php echo esc_attr($wpiko_icon); ?>"></span>
                                        <?php echo esc_html($wpiko_tab['label']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="wpiko-chatbot-content">
            <?php
            // Load the appropriate section based on active tab
            switch ($wpiko_active_tab) {
                case 'dashboard':
                    wpiko_chatbot_dashboard_section();
                    break;
                case 'setup':
                    wpiko_chatbot_setup_wizard_section();
                    break;
                case 'api_key':
                    wpiko_chatbot_api_key_section();
                    break;
                case 'ai_configuration':
                    wpiko_chatbot_ai_configuration_section();
                    break;
                case 'floating_chatbot':
                    wpiko_chatbot_floating_chatbot_section();
                    break;
                case 'proactive_greeting':
                    wpiko_chatbot_proactive_greeting_section();
                    break;
                case 'shortcode_chatbot':
                    wpiko_chatbot_shortcode_chatbot_section();
                    break;
                case 'chatbot_style':
                    wpiko_chatbot_style_section();
                    break;
                case 'chatbot_menu':
                    wpiko_chatbot_menu_section();
                    break;
                case 'questions':
                    wpiko_chatbot_questions_section();
                    break;
                case 'user_limits':
                    wpiko_chatbot_user_limits_section();
                    break;
                case 'conversations':
                    wpiko_chatbot_conversations_section();
                    break;
                case 'error_messages':
                    wpiko_chatbot_error_messages_section();
                    break;
                case 'debug_log':
                    wpiko_chatbot_debug_log_section();
                    break;
                default:
                    // Allow other plugins to handle their own tabs
                    $wpiko_tab_handled = false;
                    $wpiko_tab_handled = apply_filters('wpiko_chatbot_admin_tab_content', $wpiko_active_tab);
                    
                    // If no plugin handled this tab, default to dashboard
                    if (!$wpiko_tab_handled) {
                        wpiko_chatbot_dashboard_section();
                    }
                    break;
            }
            ?>
        </div>
    </div>
</div>

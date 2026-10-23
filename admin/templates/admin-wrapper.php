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

require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/plugin-navigation.php';
$wpiko_active_tab = isset($wpiko_active_tab) ? $wpiko_active_tab : 'dashboard';
?>

<div class="wrap wpiko-chatbot-admin-wrap">
    <div class="wpiko-chatbot-admin-container">
        <aside class="wpiko-chatbot-nav">
            <?php wpiko_chatbot_render_navigation($wpiko_active_tab, 'wpiko-chatbot-sidebar-menu', 'wpiko-chatbot-sidenav'); ?>
        </aside>
        <div class="wpiko-chatbot-content">
            <?php
            wpiko_chatbot_render_subnavigation($wpiko_active_tab);

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

<?php
/**
 * Plugin Header Component
 * 
 * @package WPiko_Chatbot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the plugin header with branding and navigation links
 */
function wpiko_chatbot_render_plugin_header() {
    ?>
    <div class="wpiko-chatbot-plugin-header">
        <div class="wpiko-chatbot-header-content">
            <div class="wpiko-chatbot-header-branding">
                <img
                    class="wpiko-chatbot-header-icon"
                    src="<?php echo esc_url(WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png'); ?>"
                    width="40"
                    height="40"
                    alt=""
                    aria-hidden="true"
                >
                <h1 class="wpiko-chatbot-header-title"><?php esc_html_e('WPiko Chatbot', 'wpiko-chatbot'); ?></h1>
                <?php if (wpiko_chatbot_is_pro_plugin_active()): ?>
                    <span class="wpiko-chatbot-header-pro-badge"><?php esc_html_e('PRO', 'wpiko-chatbot'); ?></span>
                <?php endif; ?>
            </div>
            <div class="wpiko-chatbot-header-links">
                <a href="https://wpiko.com" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <?php esc_html_e('Website', 'wpiko-chatbot'); ?>
                </a>
                <a href="https://wpiko.com/documentation/" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php esc_html_e('Documentation', 'wpiko-chatbot'); ?>
                </a>
                <a href="https://wpiko.com/learn/" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <?php esc_html_e('Learn', 'wpiko-chatbot'); ?>
                </a>
                <a href="https://wpiko.com/support/" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link">
                    <span class="dashicons dashicons-sos"></span>
                    <?php esc_html_e('Support', 'wpiko-chatbot'); ?>
                </a>
                <?php if (!wpiko_chatbot_is_pro_plugin_active()): ?>
                    <a href="https://wpiko.com/chatbot-pricing/" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link wpiko-chatbot-header-upgrade">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e('Upgrade to Pro', 'wpiko-chatbot'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

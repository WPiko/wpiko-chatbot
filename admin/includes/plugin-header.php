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

require_once WPIKO_CHATBOT_PLUGIN_DIR . 'admin/includes/plugin-navigation.php';

/**
 * Return a static resource icon matching the wpiko admin headers.
 *
 * @param string $name Icon name.
 * @return string
 */
function wpiko_chatbot_header_link_icon($name) {
    $paths = array(
        'website' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
        'documentation' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6M9 16h6"/>',
        'learn' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v6"/>',
        'support' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/>',
        'upgrade' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>',
    );

    return '<svg class="wpiko-chatbot-header-link-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Render the plugin header with branding and resource links.
 */
function wpiko_chatbot_render_plugin_header() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state.
    $current = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
    $is_pro = wpiko_chatbot_is_pro_plugin_active();
    $links = array(
        array('label' => __('Website', 'wpiko-chatbot'), 'url' => 'https://wpiko.com', 'icon' => 'website'),
        array('label' => __('Docs', 'wpiko-chatbot'), 'url' => 'https://wpiko.com/documentation/', 'icon' => 'documentation'),
        array('label' => __('Learn', 'wpiko-chatbot'), 'url' => 'https://wpiko.com/learn/', 'icon' => 'learn'),
        array('label' => __('Support', 'wpiko-chatbot'), 'url' => 'https://wpiko.com/support/', 'icon' => 'support'),
    );
    if (!$is_pro) {
        $links[] = array('label' => __('Upgrade to Pro', 'wpiko-chatbot'), 'url' => 'https://wpiko.com/chatbot-pricing/', 'icon' => 'upgrade');
    }
    ?>
    <div class="wpiko-chatbot-plugin-header" data-wpiko-chatbot-header>
        <div class="wpiko-chatbot-header-content">
            <div class="wpiko-chatbot-header-branding">
                <span class="wpiko-chatbot-header-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M7 3h10a4 4 0 0 1 4 4v9a4 4 0 0 1-4 4h-2l-3 3-3-3H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4z"/><path d="M8 8v5M12 10v6M16 8v5"/></svg></span>
                <span class="wpiko-chatbot-header-title"><?php esc_html_e('WPiko AI Chatbot', 'wpiko-chatbot'); ?></span>
                <?php if ($is_pro): ?>
                    <?php $license_tab = wpiko_chatbot_get_admin_tabs()['license_activation'] ?? null; ?>
                    <?php if ($license_tab && current_user_can($license_tab['capability'] ?? 'manage_options')): ?>
                        <a href="<?php echo esc_url(wpiko_chatbot_get_admin_tab_url('license_activation')); ?>" class="wpiko-chatbot-header-pro-badge" title="<?php esc_attr_e('Manage your Pro license', 'wpiko-chatbot'); ?>"><?php esc_html_e('PRO', 'wpiko-chatbot'); ?><span class="screen-reader-text"> <?php esc_html_e('license', 'wpiko-chatbot'); ?></span></a>
                    <?php else: ?>
                        <span class="wpiko-chatbot-header-pro-badge"><?php esc_html_e('PRO', 'wpiko-chatbot'); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <nav class="wpiko-chatbot-header-links" id="wpiko-chatbot-resource-links" aria-label="<?php esc_attr_e('wpiko links', 'wpiko-chatbot'); ?>">
                <?php foreach ($links as $link): ?>
                    <a href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener noreferrer" class="wpiko-chatbot-header-link<?php echo $link['icon'] === 'upgrade' ? ' wpiko-chatbot-header-upgrade' : ''; ?>">
                        <?php echo wpiko_chatbot_header_link_icon($link['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
                        <span><?php echo esc_html($link['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <button type="button" class="wpiko-chatbot-header-toggle" aria-expanded="false" aria-controls="wpiko-chatbot-mobile-menu wpiko-chatbot-resource-links" data-wpiko-chatbot-header-toggle>
                <span class="screen-reader-text"><?php esc_html_e('Menu', 'wpiko-chatbot'); ?></span>
                <span class="wpiko-chatbot-header-burger" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
            <?php wpiko_chatbot_render_navigation($current, 'wpiko-chatbot-mobile-menu', 'wpiko-chatbot-mobile-menu'); ?>
        </div>
    </div>
    <?php
}

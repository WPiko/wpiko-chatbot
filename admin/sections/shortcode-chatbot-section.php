<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_shortcode_chatbot_section() {
    $settings_saved = false;
    if (isset($_POST['action']) && $_POST['action'] == 'save_shortcode_chatbot') {
        check_admin_referer('save_shortcode_chatbot', 'shortcode_chatbot_nonce');
        
        if (isset($_POST['chatbot_width'])) {
            update_option('wpiko_chatbot_width', absint(wp_unslash($_POST['chatbot_width'])));
        }
        
        if (isset($_POST['chatbot_height'])) {
            update_option('wpiko_chatbot_height', absint(wp_unslash($_POST['chatbot_height'])));
        }
        
        $settings_saved = true;
    }
    
    $chatbot_width = get_option('wpiko_chatbot_width', 1280);
    $chatbot_height = get_option('wpiko_chatbot_height', 500);
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-shortcode-chatbot-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Shortcode Chatbot</h1>
            <p class="wpiko-chatbot-settings-subtitle">Embed the chatbot directly into your pages or posts using a shortcode.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Shortcode Chatbot settings updated successfully.</p></div>
        <?php endif; ?>
        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_shortcode_chatbot', 'shortcode_chatbot_nonce'); ?>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="shortcode-settings-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="shortcode-settings-title">Embed settings</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Chatbot shortcode</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <code class="wpiko-chatbot-settings-code" id="chatbot_shortcode">[wpiko_chatbot]</code>
                        <p class="wpiko-chatbot-settings-field-description">Add this shortcode to a Shortcode block in any page or post to display the chatbot.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="chatbot_width">Chatbot width (px)</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="number" name="chatbot_width" id="chatbot_width" value="<?php echo esc_attr($chatbot_width); ?>" aria-describedby="chatbot-width-description">
                        <p class="wpiko-chatbot-settings-field-description" id="chatbot-width-description">Maximum width of the chatbot container in pixels.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="chatbot_height">Chatbot height (px)</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="number" name="chatbot_height" id="chatbot_height" value="<?php echo esc_attr($chatbot_height); ?>" aria-describedby="chatbot-height-description">
                        <p class="wpiko-chatbot-settings-field-description" id="chatbot-height-description">Height of the chatbot messages area in pixels.</p>
                    </div>
                </div>
            </section>
            <input type="hidden" name="action" value="save_shortcode_chatbot">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Shortcode Chatbot Settings</button>
            </div>
        </form>
    </div>
    <?php
}

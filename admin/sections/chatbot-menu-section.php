<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_menu_section() {
    $settings_saved = false;
    if (isset($_POST['action']) && $_POST['action'] == 'save_chatbot_menu') {
        check_admin_referer('save_chatbot_menu', 'chatbot_menu_nonce');
        
        // Save sound enabled/disabled setting
        update_option('wpiko_chatbot_sound_enabled', isset($_POST['enable_sound']) ? '1' : '0');
        
        // Save transcript download enabled/disabled setting
        update_option('wpiko_chatbot_enable_transcript_download', isset($_POST['enable_transcript_download']) ? '1' : '0');

        // Save customizable menu text
        update_option('wpiko_chatbot_menu_clear_chat', sanitize_text_field(wp_unslash($_POST['menu_clear_chat'] ?? 'Start New Chat')));
        update_option('wpiko_chatbot_menu_sound_off', sanitize_text_field(wp_unslash($_POST['menu_sound_off'] ?? 'Turn Sound Off')));
        update_option('wpiko_chatbot_menu_sound_on', sanitize_text_field(wp_unslash($_POST['menu_sound_on'] ?? 'Turn Sound On')));
        update_option('wpiko_chatbot_menu_download_transcript', sanitize_text_field(wp_unslash($_POST['menu_download_transcript'] ?? 'Download Transcript')));
        
        $settings_saved = true;
    }
    
    $sound_enabled = get_option('wpiko_chatbot_sound_enabled', '1');
    $enable_transcript_download = get_option('wpiko_chatbot_enable_transcript_download', '1');

    // Get customizable menu text
    $menu_clear_chat = get_option('wpiko_chatbot_menu_clear_chat', 'Start New Chat');
    $menu_sound_off = get_option('wpiko_chatbot_menu_sound_off', 'Turn Sound Off');
    $menu_sound_on = get_option('wpiko_chatbot_menu_sound_on', 'Turn Sound On');
    $menu_download_transcript = get_option('wpiko_chatbot_menu_download_transcript', 'Download Transcript');
    
    ?>
    
    <div class="wpiko-chatbot-settings-page wpiko-chatbot-menu-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Chatbot Menu</h1>
            <p class="wpiko-chatbot-settings-subtitle">Choose the menu options available to visitors and customize their labels.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Chatbot Menu settings updated successfully.</p></div>
        <?php endif; ?>
        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_chatbot_menu', 'chatbot_menu_nonce'); ?>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="chatbot-menu-options-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="chatbot-menu-options-title">Menu options</h2>
                <table class="form-table wpiko-chatbot-settings-extension-table"><?php do_action('wpiko_chatbot_menu_options_before_sound'); ?></table>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Sound notifications</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <label class="wpiko-chatbot-settings-switch" for="enable_sound">
                            <input type="checkbox" role="switch" name="enable_sound" id="enable_sound" aria-describedby="enable_sound-description" <?php checked($sound_enabled, '1'); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Enable sound</span>
                        </label>
                        <p class="wpiko-chatbot-settings-field-description" id="enable_sound-description">Play a notification sound for new messages.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Chat transcripts</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <label class="wpiko-chatbot-settings-switch" for="enable_transcript_download">
                            <input type="checkbox" role="switch" name="enable_transcript_download" id="enable_transcript_download" aria-describedby="enable_transcript_download-description" <?php checked($enable_transcript_download, '1'); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Allow transcript downloads</span>
                        </label>
                        <p class="wpiko-chatbot-settings-field-description" id="enable_transcript_download-description">Let visitors download a copy of their conversation.</p>
                    </div>
                </div>
            </section>
            <details class="wpiko-chatbot-settings-card wpiko-chatbot-settings-disclosure">
                <summary class="wpiko-chatbot-settings-disclosure-title">Menu text customization</summary>
                <div class="wpiko-chatbot-settings-disclosure-content">
                    <p class="wpiko-chatbot-settings-field-description">Customize the text displayed in the chatbot menu.</p>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="menu_clear_chat">New chat text</label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <input type="text" name="menu_clear_chat" id="menu_clear_chat" value="<?php echo esc_attr($menu_clear_chat); ?>" aria-describedby="menu_clear_chat-description">
                            <p class="wpiko-chatbot-settings-field-description" id="menu_clear_chat-description">Default: Start New Chat</p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="menu_sound_off">Sound off text</label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <input type="text" name="menu_sound_off" id="menu_sound_off" value="<?php echo esc_attr($menu_sound_off); ?>" aria-describedby="menu_sound_off-description">
                            <p class="wpiko-chatbot-settings-field-description" id="menu_sound_off-description">Default: Turn Sound Off</p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="menu_sound_on">Sound on text</label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <input type="text" name="menu_sound_on" id="menu_sound_on" value="<?php echo esc_attr($menu_sound_on); ?>" aria-describedby="menu_sound_on-description">
                            <p class="wpiko-chatbot-settings-field-description" id="menu_sound_on-description">Default: Turn Sound On</p>
                        </div>
                    </div>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="menu_download_transcript">Download transcript text</label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <input type="text" name="menu_download_transcript" id="menu_download_transcript" value="<?php echo esc_attr($menu_download_transcript); ?>" aria-describedby="menu_download_transcript-description">
                            <p class="wpiko-chatbot-settings-field-description" id="menu_download_transcript-description">Default: Download Transcript</p>
                        </div>
                    </div>
                </div>
            </details>
            <input type="hidden" name="action" value="save_chatbot_menu">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Chatbot Menu Settings</button>
            </div>
        </form>
    </div>
    <?php
}

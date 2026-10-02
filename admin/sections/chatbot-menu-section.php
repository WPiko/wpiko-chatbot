<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_menu_section() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_chatbot_menu') {
        check_admin_referer('save_chatbot_menu', 'chatbot_menu_nonce');
        
        // Save sound enabled/disabled setting
        update_option('wpiko_chatbot_sound_enabled', isset($_POST['enable_sound']) ? '1' : '0');
        
        // Save transcript download enabled/disabled setting
        update_option('wpiko_chatbot_enable_transcript_download', isset($_POST['enable_transcript_download']) ? '1' : '0');

        // Save customizable menu text
        update_option('wpiko_chatbot_menu_clear_chat', sanitize_text_field(wp_unslash($_POST['menu_clear_chat'] ?? 'Clear Chat')));
        update_option('wpiko_chatbot_menu_sound_off', sanitize_text_field(wp_unslash($_POST['menu_sound_off'] ?? 'Turn Sound Off')));
        update_option('wpiko_chatbot_menu_sound_on', sanitize_text_field(wp_unslash($_POST['menu_sound_on'] ?? 'Turn Sound On')));
        update_option('wpiko_chatbot_menu_download_transcript', sanitize_text_field(wp_unslash($_POST['menu_download_transcript'] ?? 'Download Transcript')));
        
        echo '<div class="updated"><p>Chatbot Menu settings updated successfully.</p></div>';
    }
    
    $sound_enabled = get_option('wpiko_chatbot_sound_enabled', '1');
    $enable_transcript_download = get_option('wpiko_chatbot_enable_transcript_download', '1');

    // Get customizable menu text
    $menu_clear_chat = get_option('wpiko_chatbot_menu_clear_chat', 'Clear Chat');
    $menu_sound_off = get_option('wpiko_chatbot_menu_sound_off', 'Turn Sound Off');
    $menu_sound_on = get_option('wpiko_chatbot_menu_sound_on', 'Turn Sound On');
    $menu_download_transcript = get_option('wpiko_chatbot_menu_download_transcript', 'Download Transcript');
    
    ?>
    
    <div class="chatbot-menu-section">
        <h2> <span class="dashicons dashicons-menu"></span> Chatbot Menu</h2>
        <p class="description">Configure the menu options for your chatbot.</p>
        <form method="post" action="">
            <?php wp_nonce_field('save_chatbot_menu', 'chatbot_menu_nonce'); ?>
            <table class="form-table">
            <?php do_action('wpiko_chatbot_menu_options_before_sound'); ?>
            
                <tr valign="top">
                    <th scope="row"><label for="enable_sound">Enable Sound</label></th>
                    <td>
                        <label class="wpiko-switch">
                            <input type="checkbox" name="enable_sound" id="enable_sound" <?php checked($sound_enabled, '1'); ?>>
                            <span class="wpiko-slider round"></span>
                        </label>
                        <p class="description">Enable sound notifications for new messages.</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row"><label for="enable_transcript_download">Enable Transcript Download</label></th>
                    <td>
                        <label class="wpiko-switch">
                            <input type="checkbox" name="enable_transcript_download" id="enable_transcript_download" <?php checked($enable_transcript_download, '1'); ?>>
                            <span class="wpiko-slider round"></span>
                        </label>
                        <p class="description">Allow users to download chat transcripts.</p>
                    </td>
                </tr>
                
            </table>

            <div class="wpiko-accordion-item">
                <h3 class="collapsible-header">
                    <span><span class="dashicons dashicons-edit"></span> Menu Text Customization</span>
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </h3>
                <div class="collapsible-content">
                    <p class="description" style="margin-bottom: 20px;">Customize the text displayed in the chatbot menu.</p>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="menu_clear_chat">Clear Chat Text</label></th>
                            <td>
                                <input type="text" name="menu_clear_chat" id="menu_clear_chat" value="<?php echo esc_attr($menu_clear_chat); ?>" class="regular-text">
                                <p class="description">Default: <em>Clear Chat</em></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="menu_sound_off">Sound Off Text</label></th>
                            <td>
                                <input type="text" name="menu_sound_off" id="menu_sound_off" value="<?php echo esc_attr($menu_sound_off); ?>" class="regular-text">
                                <p class="description">Default: <em>Turn Sound Off</em></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="menu_sound_on">Sound On Text</label></th>
                            <td>
                                <input type="text" name="menu_sound_on" id="menu_sound_on" value="<?php echo esc_attr($menu_sound_on); ?>" class="regular-text">
                                <p class="description">Default: <em>Turn Sound On</em></p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="menu_download_transcript">Download Transcript Text</label></th>
                            <td>
                                <input type="text" name="menu_download_transcript" id="menu_download_transcript" value="<?php echo esc_attr($menu_download_transcript); ?>" class="regular-text">
                                <p class="description">Default: <em>Download Transcript</em></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    // Accordion toggle
                    $('.collapsible-header').click(function () {
                        // Toggle active class on header for arrow rotation
                        $(this).toggleClass('active');
                        // Toggle active class on next sibling content
                        $(this).next('.collapsible-content').toggleClass('active');
                    });
                });
            </script>
            <input type="hidden" name="action" value="save_chatbot_menu">
            <?php submit_button('Save Chatbot Menu Settings'); ?>
        </form>
    </div>
    <?php
}

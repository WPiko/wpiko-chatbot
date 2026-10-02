<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_shortcode_chatbot_section() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_shortcode_chatbot') {
        check_admin_referer('save_shortcode_chatbot', 'shortcode_chatbot_nonce');
        
        if (isset($_POST['chatbot_width'])) {
            update_option('wpiko_chatbot_width', absint(wp_unslash($_POST['chatbot_width'])));
        }
        
        if (isset($_POST['chatbot_height'])) {
            update_option('wpiko_chatbot_height', absint(wp_unslash($_POST['chatbot_height'])));
        }
        
        echo '<div class="updated"><p>Shortcode Chatbot settings updated successfully.</p></div>';
    }
    
    $chatbot_width = get_option('wpiko_chatbot_width', 1280);
    $chatbot_height = get_option('wpiko_chatbot_height', 500);
    ?>
    <div class="shortcode-chatbot-section">
        <h2> <span class="dashicons dashicons-shortcode"></span> Shortcode Chatbot</h2>
        <p class="description">Use a shortcode to embed the chatbot directly into your pages or posts for more precise placement.</p>
        <form method="post" action="">
            <?php wp_nonce_field('save_shortcode_chatbot', 'shortcode_chatbot_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="chatbot_shortcode">Add Chatbot</label></th>
                    <td>
                        <code>[wpiko_chatbot]</code>
                        <p class="description">Use the above shortcode to add the chatbot to any page or post.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="chatbot_width">Chatbot Width (px)</label></th>
                    <td>
                        <input type="number" name="chatbot_width" id="chatbot_width" value="<?php echo esc_attr($chatbot_width); ?>" class="small-text">
                        <p class="description">Enter the maximum width of the chatbot container in pixels.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="chatbot_height">Chatbot Height (px)</label></th>
                    <td>
                        <input type="number" name="chatbot_height" id="chatbot_height" value="<?php echo esc_attr($chatbot_height); ?>" class="small-text">
                        <p class="description">Enter the height of the chatbot messages area in pixels.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_shortcode_chatbot">
            <?php submit_button('Save Shortcode Chatbot Settings'); ?>
        </form>
    </div>
    <?php
}
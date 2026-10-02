<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_api_key_section() {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'save_api_key' || $_POST['action'] == 'update_api_key') {
            check_admin_referer('save_api_key', 'api_key_nonce');
            if (!empty($_POST['api_key'])) {
                $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
            
                // Validate the API key
                $is_valid = wpiko_chatbot_validate_api_key($api_key);
            
                if ($is_valid) {
                    $encrypted_api_key = wpiko_chatbot_encrypt_api_key($api_key);
                    update_option('wpiko_chatbot_api_key', $encrypted_api_key);
                    echo '<div class="updated"><p>API key validated and updated successfully.</p></div>';
                } else {
                    echo '<div class="error"><p>Invalid API key. Please check and try again.</p></div>';
                }
            }
        }
    }

    if (isset($_POST['delete_api_key'])) {
        check_admin_referer('delete_api_key', 'delete_api_key_nonce');
        delete_option('wpiko_chatbot_api_key');
        echo '<div class="updated"><p>API key deleted successfully.</p></div>';
    }

    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $masked_api_key = !empty($api_key) ? '••••••••••' . substr($api_key, -5) : '';
    ?>
    <div class="api-key-section">
        <h2> <span class="dashicons dashicons-admin-network"></span> API Key</h2>
        <p class="description">Enter your OpenAI API key to enable AI-powered chatbot functionality.</p>
        <form method="post" action="">
            <?php wp_nonce_field('save_api_key', 'api_key_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="api_key">OpenAI API Key</label></th>
                    <td>
                        <?php if (empty($api_key)) : ?>
                            <input type="text" name="api_key" id="api_key" value="" class="regular-text">
                            <p class="description">Enter your OpenAI API key. <a href="https://platform.openai.com/api-keys" target="_blank">Create OpenAPI key</a></p>
                        <?php else : ?>
                            <input type="text" id="masked_api_key" value="<?php echo esc_attr($masked_api_key); ?>" class="regular-text" readonly>
                            <?php wp_nonce_field('delete_api_key', 'delete_api_key_nonce'); ?>
                            <button type="submit" name="delete_api_key" class="button button-link-delete" onclick="return confirm('Are you sure you want to delete the API key?');" style="vertical-align: middle;"><span class="dashicons dashicons-trash" style="margin-right: 5px; line-height: inherit;"></span>Delete API Key</button>
                            <p class="description">API key is set. Only the last 5 digits are shown for security.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php if (empty($api_key)) : ?>
                <input type="hidden" name="action" value="save_api_key">
                <?php submit_button('Save API Key'); ?>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

// Function to validate the API key
function wpiko_chatbot_validate_api_key($api_key) {
    $url = 'https://api.openai.com/v1/models';
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        )
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    return $response_code === 200;
}

// AJAX handler to validate the API key
function wpiko_chatbot_validate_api_key_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
        wp_send_json_error('API key is required');
        return;
    }
    
    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
    $is_valid = wpiko_chatbot_validate_api_key($api_key);
    
    if ($is_valid) {
        $encrypted_api_key = wpiko_chatbot_encrypt_api_key($api_key);
        update_option('wpiko_chatbot_api_key', $encrypted_api_key);
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
add_action('wp_ajax_wpiko_chatbot_validate_api_key', 'wpiko_chatbot_validate_api_key_ajax');

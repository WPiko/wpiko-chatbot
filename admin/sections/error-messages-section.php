<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_error_messages_section()
{
    if (isset($_POST['action']) && $_POST['action'] == 'save_error_messages') {
        check_admin_referer('save_error_messages', 'error_messages_nonce');

        // Save grouped error messages
        $error_messages = array();
        if (isset($_POST['wpiko_chatbot_error_messages'])) {
            $unslashed_data = wp_unslash($_POST);
            if (is_array($unslashed_data['wpiko_chatbot_error_messages'])) {
                foreach ($unslashed_data['wpiko_chatbot_error_messages'] as $key => $message) {
                    $error_messages[$key] = sanitize_text_field($message);
                }
            }
        }
        update_option('wpiko_chatbot_error_messages_grouped', $error_messages);

        echo '<div class="updated"><p>Error messages updated successfully.</p></div>';
    }

    // Use centralized default error messages (DRY principle)
    $default_error_messages = wpiko_chatbot_get_default_error_messages();

    // Use centralized labels
    $error_labels = wpiko_chatbot_get_error_message_labels();

    // Get saved error messages merged with defaults
    $saved_error_messages = wpiko_chatbot_get_error_messages();
    ?>
    <div class="error-messages-section">
        <h2><span class="dashicons dashicons-warning"></span> Error Messages</h2>
        <p class="description">Customize the user-facing error messages. Technical errors are automatically mapped to these
            categories.</p>

        <form method="post" action="">
            <?php wp_nonce_field('save_error_messages', 'error_messages_nonce'); ?>

            <div class="api-error-section">
                <table class="form-table">
                    <?php foreach ($default_error_messages as $key => $default_message): ?>
                        <tr valign="top">
                            <th scope="row">
                                <label for="error_<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($error_labels[$key] ?? ucwords(str_replace('_', ' ', $key))); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" name="wpiko_chatbot_error_messages[<?php echo esc_attr($key); ?>]"
                                    id="error_<?php echo esc_attr($key); ?>"
                                    value="<?php echo esc_attr(stripslashes($saved_error_messages[$key])); ?>"
                                    class="large-text">
                                <p class="description">Default: <em><?php echo esc_html($default_message); ?></em></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <input type="hidden" name="action" value="save_error_messages">
            <?php submit_button('Save Error Messages'); ?>
        </form>
    </div>
    <?php
}
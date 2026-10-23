<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_error_messages_section()
{
    $settings_saved = false;
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

        $settings_saved = true;
    }

    // Use centralized default error messages (DRY principle)
    $default_error_messages = wpiko_chatbot_get_default_error_messages();

    // Use centralized labels
    $error_labels = wpiko_chatbot_get_error_message_labels();

    // Get saved error messages merged with defaults
    $saved_error_messages = wpiko_chatbot_get_error_messages();
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-error-messages-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Error Messages</h1>
            <p class="wpiko-chatbot-settings-subtitle">Customize the messages visitors see when something goes wrong. Technical errors are automatically mapped to these categories.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Error messages updated successfully.</p></div>
        <?php endif; ?>

        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_error_messages', 'error_messages_nonce'); ?>

            <section class="wpiko-chatbot-settings-card" aria-labelledby="chatbot-error-messages-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="chatbot-error-messages-title">Visitor messages</h2>
                <?php foreach ($default_error_messages as $key => $default_message): ?>
                    <div class="wpiko-chatbot-settings-field-row">
                        <label class="wpiko-chatbot-settings-field-label" for="error_<?php echo esc_attr($key); ?>">
                            <?php echo esc_html($error_labels[$key] ?? ucwords(str_replace('_', ' ', $key))); ?>
                        </label>
                        <div class="wpiko-chatbot-settings-field-control">
                            <input type="text" name="wpiko_chatbot_error_messages[<?php echo esc_attr($key); ?>]"
                                id="error_<?php echo esc_attr($key); ?>"
                                value="<?php echo esc_attr(stripslashes($saved_error_messages[$key])); ?>"
                                aria-describedby="error_<?php echo esc_attr($key); ?>-description">
                            <p class="wpiko-chatbot-settings-field-description" id="error_<?php echo esc_attr($key); ?>-description">Default: <?php echo esc_html($default_message); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <input type="hidden" name="action" value="save_error_messages">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Error Messages</button>
            </div>
        </form>
    </div>
    <?php
}

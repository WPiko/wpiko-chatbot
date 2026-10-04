<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_api_key_section() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = null;

    if (isset($_POST['action']) && ($_POST['action'] === 'save_api_key' || $_POST['action'] === 'update_api_key')) {
        check_admin_referer('save_api_key', 'api_key_nonce');
        $submitted_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';
        $notice = wpiko_chatbot_save_api_key_with_test($submitted_key);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'test_api_key') {
        check_admin_referer('test_api_key', 'test_api_key_nonce');
        $test = wpiko_chatbot_test_openai_connection(wpiko_chatbot_get_api_key());
        wpiko_chatbot_record_connection_test($test);
        $notice = array(
            'type' => $test['ok'] ? 'success' : 'error',
            'message' => $test['message'],
            'link' => $test['link'],
            'link_label' => $test['link_label'],
        );
    }

    if (isset($_POST['delete_api_key'])) {
        check_admin_referer('delete_api_key', 'delete_api_key_nonce');
        delete_option('wpiko_chatbot_api_key');
        delete_option('wpiko_chatbot_last_connection_test');
        wpiko_chatbot_clear_openai_health();
        $notice = array('type' => 'success', 'message' => __('API key deleted.', 'wpiko-chatbot'));
    }

    $api_key = wpiko_chatbot_get_api_key();
    $masked_api_key = !empty($api_key) ? '••••••••••' . substr($api_key, -5) : '';
    $last_test = get_option('wpiko_chatbot_last_connection_test', array());
    $health = wpiko_chatbot_get_openai_health();

    if ($notice) {
        $notice_class = $notice['type'] === 'success' ? 'notice-success' : ($notice['type'] === 'warning' ? 'notice-warning' : 'notice-error');
        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> wpiko-api-key-notice">
            <p><?php echo esc_html($notice['message']); ?></p>
            <?php if (!empty($notice['link'])) : ?>
                <p><a href="<?php echo esc_url($notice['link']); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php echo esc_html($notice['link_label']); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    <div class="api-key-section">
        <h2> <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('API Key', 'wpiko-chatbot'); ?></h2>
        <p class="description"><?php esc_html_e('Your chatbot uses your own OpenAI account. You pay OpenAI directly for what you use (usually a few dollars a month for a small site), with no WPiko subscription.', 'wpiko-chatbot'); ?></p>

        <?php if (empty($api_key)) : ?>
            <ol class="wpiko-api-key-steps">
                <li>
                    <strong><?php esc_html_e('Sign in to the OpenAI Platform', 'wpiko-chatbot'); ?></strong>
                    <span><?php esc_html_e('This is separate from a ChatGPT subscription. A ChatGPT Plus plan does not include API usage.', 'wpiko-chatbot'); ?></span>
                    <a href="https://platform.openai.com/signup" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open platform.openai.com', 'wpiko-chatbot'); ?></a>
                </li>
                <li>
                    <strong><?php esc_html_e('Add credit to your account', 'wpiko-chatbot'); ?></strong>
                    <span><?php esc_html_e('The API is prepaid. Without credit, the key is accepted but every answer fails. $5 is enough to start.', 'wpiko-chatbot'); ?></span>
                    <a href="<?php echo esc_url(wpiko_chatbot_openai_url('billing')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open OpenAI billing', 'wpiko-chatbot'); ?></a>
                </li>
                <li>
                    <strong><?php esc_html_e('Create a secret key and paste it below', 'wpiko-chatbot'); ?></strong>
                    <span><?php esc_html_e('Keys start with "sk-". OpenAI shows the key only once, so copy it straight away.', 'wpiko-chatbot'); ?></span>
                    <a href="<?php echo esc_url(wpiko_chatbot_openai_url('keys')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create an API key', 'wpiko-chatbot'); ?></a>
                </li>
            </ol>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('save_api_key', 'api_key_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="api_key"><?php esc_html_e('OpenAI API Key', 'wpiko-chatbot'); ?></label></th>
                    <td>
                        <?php if (empty($api_key)) : ?>
                            <input type="password" name="api_key" id="api_key" value="" class="regular-text" autocomplete="off" spellcheck="false" placeholder="sk-...">
                            <p class="description"><?php esc_html_e('When you save, WPiko sends one tiny test message to OpenAI (costs a fraction of a cent) to confirm the key and your credit work.', 'wpiko-chatbot'); ?></p>
                        <?php else : ?>
                            <input type="text" id="masked_api_key" value="<?php echo esc_attr($masked_api_key); ?>" class="regular-text" readonly>
                            <p class="description"><?php esc_html_e('API key is set. Only the last 5 characters are shown for security.', 'wpiko-chatbot'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php if (empty($api_key)) : ?>
                <input type="hidden" name="action" value="save_api_key">
                <?php submit_button(__('Save and Test API Key', 'wpiko-chatbot')); ?>
            <?php endif; ?>
        </form>

        <?php if (!empty($api_key)) : ?>
            <?php
            $status_ok = !empty($last_test) && !empty($last_test['ok']) && empty($health);
            $status_message = '';
            $status_link = '';
            $status_link_label = '';
            if (!empty($health)) {
                $status_message = $health['message'];
                $status_link = $health['link'];
                $status_link_label = $health['link_label'];
            } elseif (!empty($last_test)) {
                $status_message = $last_test['message'];
                $status_link = $last_test['link'];
                $status_link_label = $last_test['link_label'];
            }
            $status_time = !empty($health['time']) ? $health['time'] : (!empty($last_test['time']) ? $last_test['time'] : 0);
            $status_class = $status_ok ? 'is-ok' : (empty($last_test) && empty($health) ? 'is-unknown' : 'is-error');
            ?>
            <div class="wpiko-connection-status <?php echo esc_attr($status_class); ?>">
                <div class="wpiko-connection-status-header">
                    <span class="dashicons <?php echo esc_attr($status_ok ? 'dashicons-yes-alt' : ($status_class === 'is-unknown' ? 'dashicons-info-outline' : 'dashicons-warning')); ?>" aria-hidden="true"></span>
                    <strong>
                        <?php
                        if ($status_ok) {
                            esc_html_e('Connected to OpenAI', 'wpiko-chatbot');
                        } elseif ($status_class === 'is-unknown') {
                            esc_html_e('Connection not tested yet', 'wpiko-chatbot');
                        } else {
                            esc_html_e('The chatbot cannot answer visitors', 'wpiko-chatbot');
                        }
                        ?>
                    </strong>
                    <?php if ($status_time) : ?>
                        <span class="wpiko-connection-status-time">
                            <?php
                            /* translators: %s: human-readable time difference. */
                            echo esc_html(sprintf(__('checked %s ago', 'wpiko-chatbot'), human_time_diff($status_time)));
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($status_message !== '') : ?>
                    <p><?php echo esc_html($status_message); ?></p>
                <?php elseif ($status_class === 'is-unknown') : ?>
                    <p><?php esc_html_e('Run a test to confirm that OpenAI accepts this key and that your account has credit.', 'wpiko-chatbot'); ?></p>
                <?php endif; ?>
                <div class="wpiko-connection-status-actions">
                    <form method="post" action="">
                        <?php wp_nonce_field('test_api_key', 'test_api_key_nonce'); ?>
                        <input type="hidden" name="action" value="test_api_key">
                        <button type="submit" class="button button-primary"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e('Test connection', 'wpiko-chatbot'); ?></button>
                    </form>
                    <?php if ($status_link !== '' && !$status_ok) : ?>
                        <a href="<?php echo esc_url($status_link); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php echo esc_html($status_link_label); ?></a>
                    <?php endif; ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('delete_api_key', 'delete_api_key_nonce'); ?>
                        <button type="submit" name="delete_api_key" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete the API key? The chatbot will stop answering until a new key is added.', 'wpiko-chatbot')); ?>');"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e('Delete API Key', 'wpiko-chatbot'); ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Get the decrypted API key.
 *
 * @return string
 */
function wpiko_chatbot_get_api_key() {
    $api_key = wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', ''));
    return is_string($api_key) ? $api_key : '';
}

/**
 * Test a submitted key and save it when OpenAI accepts it.
 *
 * Keys that OpenAI accepts are saved even when the account has a problem
 * (for example no credit yet), so the admin only has to fix the account and
 * press "Test connection" instead of pasting the key again.
 *
 * @param string $api_key Submitted key.
 * @return array{type: string, message: string, link: string, link_label: string, saved: bool, test: array}
 */
function wpiko_chatbot_save_api_key_with_test($api_key) {
    $test = wpiko_chatbot_test_openai_connection($api_key);
    $saved = false;

    if ($test['ok'] || $test['key_valid'] === true) {
        update_option('wpiko_chatbot_api_key', wpiko_chatbot_encrypt_api_key($api_key));
        wpiko_chatbot_record_connection_test($test);
        $saved = true;
    }

    // First successful setup: switch the floating chatbot on so the admin can
    // see it on the site straight away (only if it was never configured).
    $floating_enabled_now = false;
    if ($test['ok'] && get_option('wpiko_chatbot_enable_floating', null) === null) {
        update_option('wpiko_chatbot_enable_floating', '1');
        $floating_enabled_now = true;
    }

    if ($test['ok']) {
        $type = 'success';
        $message = __('API key saved.', 'wpiko-chatbot') . ' ' . $test['message'];
        if ($floating_enabled_now) {
            $message .= ' ' . __('The floating chatbot is now switched on across your site. You can change where it appears in Floating Chatbot.', 'wpiko-chatbot');
        }
    } elseif ($saved) {
        $type = 'warning';
        $message = __('API key saved, but the chatbot cannot answer yet:', 'wpiko-chatbot') . ' ' . $test['message'];
    } else {
        $type = 'error';
        $message = __('The API key was not saved.', 'wpiko-chatbot') . ' ' . $test['message'];
    }

    return array(
        'type' => $type,
        'message' => $message,
        'link' => $test['link'],
        'link_label' => $test['link_label'],
        'saved' => $saved,
        'test' => $test,
        'floating_enabled_now' => $floating_enabled_now,
    );
}

/**
 * Validate an API key.
 *
 * Kept for backwards compatibility. Returns true when OpenAI accepts the key,
 * even if the account still needs credit.
 *
 * @param string $api_key API key.
 * @return bool
 */
function wpiko_chatbot_validate_api_key($api_key) {
    $test = wpiko_chatbot_test_openai_connection($api_key);
    return $test['ok'] || $test['key_valid'] === true;
}

// AJAX handler: test (and save) an API key. Used by the setup wizard.
function wpiko_chatbot_validate_api_key_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    $api_key = isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '';

    // An empty key re-tests the saved key.
    if ($api_key === '') {
        $saved_key = wpiko_chatbot_get_api_key();
        if ($saved_key === '') {
            wp_send_json_error(array('message' => __('Enter an OpenAI API key first.', 'wpiko-chatbot'), 'code' => 'missing_key'));
        }
        $test = wpiko_chatbot_test_openai_connection($saved_key);
        wpiko_chatbot_record_connection_test($test);
        $result = array(
            'type' => $test['ok'] ? 'success' : 'error',
            'message' => $test['message'],
            'link' => $test['link'],
            'link_label' => $test['link_label'],
            'saved' => true,
            'test' => $test,
        );
    } else {
        $result = wpiko_chatbot_save_api_key_with_test($api_key);
    }

    $payload = array(
        'message' => $result['message'],
        'link' => $result['link'],
        'link_label' => $result['link_label'],
        'saved' => $result['saved'],
        'connected' => !empty($result['test']['ok']),
        'code' => $result['test']['code'],
    );

    if ($result['saved']) {
        wp_send_json_success($payload);
    }

    wp_send_json_error($payload);
}
add_action('wp_ajax_wpiko_chatbot_validate_api_key', 'wpiko_chatbot_validate_api_key_ajax');

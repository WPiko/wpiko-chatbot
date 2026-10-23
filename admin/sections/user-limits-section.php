<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * User Limits Section
 *
 * Dedicated admin page for configuring per-IP request limits.
 */
function wpiko_chatbot_user_limits_section()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings_saved = false;
    // Handle User Limits settings save
    if (isset($_POST['action']) && $_POST['action'] == 'save_user_limits') {
        check_admin_referer('save_user_limits', 'user_limits_nonce');

        $user_limits_enabled = isset($_POST['user_limits_enabled']) ? '1' : '0';
        $user_limits_hourly = isset($_POST['user_limits_hourly']) ? max(0, absint($_POST['user_limits_hourly'])) : 0;
        $user_limits_daily = isset($_POST['user_limits_daily']) ? max(0, absint($_POST['user_limits_daily'])) : 0;
        $user_limits_message = isset($_POST['user_limits_message'])
            ? sanitize_textarea_field(wp_unslash($_POST['user_limits_message']))
            : '';

        update_option('wpiko_chatbot_user_limits_enabled', $user_limits_enabled);
        update_option('wpiko_chatbot_user_limits_hourly', $user_limits_hourly);
        update_option('wpiko_chatbot_user_limits_daily', $user_limits_daily);
        update_option('wpiko_chatbot_user_limits_message', $user_limits_message);
        $site_daily = isset($_POST['user_limits_site_daily']) ? absint($_POST['user_limits_site_daily']) : 1000;
        update_option('wpiko_chatbot_user_limits_site_daily', $site_daily > 0 ? $site_daily : 1000);

        $settings_saved = true;
    }

    $user_limits_enabled = get_option('wpiko_chatbot_user_limits_enabled', '1') === '1';
    $user_limits_hourly = (int) get_option('wpiko_chatbot_user_limits_hourly', 20);
    $user_limits_daily = (int) get_option('wpiko_chatbot_user_limits_daily', 100);
    $site_daily = max(1, (int) get_option('wpiko_chatbot_user_limits_site_daily', 1000));
    $user_limits_message = get_option('wpiko_chatbot_user_limits_message', '');
    $user_limits_message_placeholder = function_exists('wpiko_chatbot_user_limits_default_message')
        ? wpiko_chatbot_user_limits_default_message()
        : 'You have reached the maximum number of messages allowed. Please try again later.';
    ?>

    <div class="wpiko-chatbot-settings-page wpiko-user-limits-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>User Limits</h1>
            <p class="wpiko-chatbot-settings-subtitle">Protect your OpenAI budget with limits for each IP address and the whole site. Custom
                limits default to 20 messages per hour and 100 per day. Requests during live admin takeover
                bypass custom limits, but still count toward safety limits.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>User Limits updated successfully.</p></div>
        <?php endif; ?>

        <form method="post" action="" name="user_limits_form" id="user_limits_form" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_user_limits', 'user_limits_nonce'); ?>

            <section class="wpiko-chatbot-settings-card" aria-labelledby="user-limits-settings-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="user-limits-settings-title">Request limits</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Custom user limits</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <label class="wpiko-chatbot-settings-switch" for="user_limits_enabled">
                            <input type="checkbox" role="switch" name="user_limits_enabled" id="user_limits_enabled" value="1" aria-describedby="user-limits-enabled-description" <?php checked($user_limits_enabled); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Apply custom hourly and daily limits</span>
                        </label>
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-enabled-description">Enabled by default. Safety limits always apply, even when this switch is off:
                            10 requests per minute, 60 per hour and 300 per day per IP. The site-wide daily limit also always applies.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="user_limits_hourly">Hourly limit</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="number" name="user_limits_hourly" id="user_limits_hourly" min="0" step="1"
                            value="<?php echo esc_attr($user_limits_hourly); ?>" aria-describedby="user-limits-hourly-description">
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-hourly-description">Maximum requests per IP per hour. Set to <strong>0</strong> to
                            use the safety cap of 60 per hour. Values above 60 cannot raise that cap.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="user_limits_daily">Daily limit</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="number" name="user_limits_daily" id="user_limits_daily" min="0" step="1"
                            value="<?php echo esc_attr($user_limits_daily); ?>" aria-describedby="user-limits-daily-description">
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-daily-description">Maximum requests per IP per day. Set to <strong>0</strong> to
                            use the safety cap of 300 per day. Values above 300 cannot raise that cap.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="user_limits_site_daily">Site-wide daily limit</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="number" name="user_limits_site_daily" id="user_limits_site_daily" min="1" step="1"
                            value="<?php echo esc_attr($site_daily); ?>" aria-describedby="user-limits-site-description user-limits-size-description">
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-site-description">Maximum chat requests across all visitors in a 24-hour window. Default: 1,000.
                            This cannot be disabled. Zero resets it to the default. This is a request cap, not a currency budget.
                            Rejected or failed requests may consume capacity. Windows start with the first counted request.</p>
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-size-description">Chat messages are limited to 16,000 bytes (about 16,000 English characters).
                            AI output is limited to 8,192 tokens per API call, including reasoning.</p>
                    </div>
                </div>
            </section>

            <section class="wpiko-chatbot-settings-card" aria-labelledby="user-limits-message-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="user-limits-message-title">Limit reached message</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="user_limits_message">Visitor message</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <textarea name="user_limits_message" id="user_limits_message" rows="3" aria-describedby="user-limits-message-description"
                            placeholder="<?php echo esc_attr($user_limits_message_placeholder); ?>"><?php echo esc_textarea($user_limits_message); ?></textarea>
                        <p class="wpiko-chatbot-settings-field-description" id="user-limits-message-description">Friendly message shown to the visitor when they hit the limit.
                            Leave blank to use the default.</p>
                    </div>
                </div>
            </section>

            <input type="hidden" name="action" value="save_user_limits">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" class="button wpiko-chatbot-settings-save">Save User Limits</button>
            </div>
        </form>
    </div>
    <?php
}

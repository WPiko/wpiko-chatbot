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

        echo '<div class="updated"><p>User Limits updated successfully.</p></div>';
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

    <div class="user-limits-section">
        <div class="responses-api-section">
            <h3><span class="dashicons dashicons-shield"></span> User Limits</h3>
            <p class="description">Protect your OpenAI budget with limits for each IP address and the whole site. Custom
                limits default to 20 messages per hour and 100 per day. Requests during live admin takeover
                bypass custom limits, but still count toward safety limits.</p>

            <form method="post" action="" name="user_limits_form" id="user_limits_form">
                <?php wp_nonce_field('save_user_limits', 'user_limits_nonce'); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="user_limits_enabled">Enable Custom User Limits</label></th>
                        <td>
                            <label class="wpiko-switch">
                                <input type="checkbox" name="user_limits_enabled" id="user_limits_enabled" value="1" <?php checked($user_limits_enabled); ?>>
                                <span class="wpiko-slider round"></span>
                            </label>
                            <span>Apply your custom hourly and daily limits</span>
                            <p class="description">Enabled by default. Safety limits always apply, even when this switch is off:
                                10 requests per minute, 60 per hour and 300 per day per IP. The site-wide daily limit also always applies.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Hourly Limit</th>
                        <td>
                            <input type="number" name="user_limits_hourly" id="user_limits_hourly" min="0" step="1"
                                class="small-text" value="<?php echo esc_attr($user_limits_hourly); ?>">
                            <p class="description">Maximum requests per IP per hour. Set to <strong>0</strong> to
                                use the safety cap of 60 per hour. Values above 60 cannot raise that cap.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Daily Limit</th>
                        <td>
                            <input type="number" name="user_limits_daily" id="user_limits_daily" min="0" step="1"
                                class="small-text" value="<?php echo esc_attr($user_limits_daily); ?>">
                            <p class="description">Maximum requests per IP per day. Set to <strong>0</strong> to
                                use the safety cap of 300 per day. Values above 300 cannot raise that cap.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="user_limits_site_daily">Site-wide Daily Limit</label></th>
                        <td>
                            <input type="number" name="user_limits_site_daily" id="user_limits_site_daily" min="1" step="1"
                                class="small-text" value="<?php echo esc_attr($site_daily); ?>">
                            <p class="description">Maximum chat requests across all visitors in a 24-hour window. Default: 1,000.
                                This cannot be disabled. Zero resets it to the default. This is a request cap, not a currency budget.
                                Rejected or failed requests may consume capacity. Windows start with the first counted request.</p>
                            <p class="description">Chat messages are limited to 16,000 bytes (about 16,000 English characters).
                                AI output is limited to 8,192 tokens per API call, including reasoning.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Limit Reached Message</th>
                        <td>
                            <textarea name="user_limits_message" id="user_limits_message" class="large-text" rows="3"
                                placeholder="<?php echo esc_attr($user_limits_message_placeholder); ?>"><?php echo esc_textarea($user_limits_message); ?></textarea>
                            <p class="description">Friendly message shown to the visitor when they hit the limit.
                                Leave blank to use the default.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Save User Limits</th>
                        <td>
                            <button type="submit" class="button button-primary">Save User Limits</button>
                        </td>
                    </tr>
                </table>

                <input type="hidden" name="action" value="save_user_limits">
            </form>
        </div>
    </div>
    <?php
}

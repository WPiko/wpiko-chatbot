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

        echo '<div class="updated"><p>User Limits updated successfully.</p></div>';
    }

    $user_limits_enabled = get_option('wpiko_chatbot_user_limits_enabled', '0') === '1';
    $user_limits_hourly = (int) get_option('wpiko_chatbot_user_limits_hourly', 0);
    $user_limits_daily = (int) get_option('wpiko_chatbot_user_limits_daily', 0);
    $user_limits_message = get_option('wpiko_chatbot_user_limits_message', '');
    $user_limits_message_placeholder = function_exists('wpiko_chatbot_user_limits_default_message')
        ? wpiko_chatbot_user_limits_default_message()
        : 'You have reached the maximum number of messages allowed. Please try again later.';
    ?>

    <div class="user-limits-section">
        <div class="responses-api-section">
            <h3><span class="dashicons dashicons-shield"></span> User Limits</h3>
            <p class="description">Cap how many chat requests a single visitor (by IP address) can make. Use this to
                protect against runaway API costs and abuse. Limits are bypassed automatically while an admin is
                handling a conversation through the Take Over feature.</p>

            <form method="post" action="" name="user_limits_form" id="user_limits_form">
                <?php wp_nonce_field('save_user_limits', 'user_limits_nonce'); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="user_limits_enabled">Enable User Limits</label></th>
                        <td>
                            <label class="wpiko-switch">
                                <input type="checkbox" name="user_limits_enabled" id="user_limits_enabled" value="1" <?php checked($user_limits_enabled); ?>>
                                <span class="wpiko-slider round"></span>
                            </label>
                            <span>Limit the number of requests a visitor can make</span>
                            <p class="description">Disabled by default. When enabled, requests are counted per IP
                                address.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Hourly Limit</th>
                        <td>
                            <input type="number" name="user_limits_hourly" id="user_limits_hourly" min="0" step="1"
                                class="small-text" value="<?php echo esc_attr($user_limits_hourly); ?>">
                            <p class="description">Maximum requests per IP per hour. Set to <strong>0</strong> to
                                disable the hourly window.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Daily Limit</th>
                        <td>
                            <input type="number" name="user_limits_daily" id="user_limits_daily" min="0" step="1"
                                class="small-text" value="<?php echo esc_attr($user_limits_daily); ?>">
                            <p class="description">Maximum requests per IP per day. Set to <strong>0</strong> to
                                disable the daily window.</p>
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

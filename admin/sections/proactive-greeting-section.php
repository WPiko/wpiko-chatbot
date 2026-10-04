<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Frontend styles for the proactive greeting card.
 * Generates position variables (based on the floating icon position)
 * and device visibility media queries.
 */
function wpiko_chatbot_proactive_greeting_styles() {
    if (!get_option('wpiko_chatbot_proactive_enabled', false) || !get_option('wpiko_chatbot_enable_floating', false)) {
        return;
    }

    $floating_position = get_option('wpiko_chatbot_floating_position', 'right');
    $allowed_positions = array('right', 'left', 'top-right', 'top-left');

    if (!in_array($floating_position, $allowed_positions, true)) {
        $floating_position = 'right';
    }

    $is_right = in_array($floating_position, array('right', 'top-right'), true);
    $is_top = in_array($floating_position, array('top-right', 'top-left'), true);

    // Place the card above the floating icon (or below it for top positions),
    // aligned to the same side of the screen as the icon.
    $css_content = ':root {
        --proactive-greeting-top: ' . ($is_top ? '95px' : 'auto') . ';
        --proactive-greeting-bottom: ' . ($is_top ? 'auto' : '95px') . ';
        --proactive-greeting-right: ' . ($is_right ? '20px' : 'auto') . ';
        --proactive-greeting-left: ' . ($is_right ? 'auto' : '20px') . ';
    }';

    if (get_option('wpiko_chatbot_proactive_hide_desktop', false)) {
        $css_content .= '@media screen and (min-width: 1025px) {
            #wpiko-chatbot-proactive-greeting { display: none !important; }
        }';
    }

    if (get_option('wpiko_chatbot_proactive_hide_tablet', false)) {
        $css_content .= '@media screen and (min-width: 768px) and (max-width: 1024px) {
            #wpiko-chatbot-proactive-greeting { display: none !important; }
        }';
    }

    if (get_option('wpiko_chatbot_proactive_hide_mobile', false)) {
        $css_content .= '@media screen and (max-width: 767px) {
            #wpiko-chatbot-proactive-greeting { display: none !important; }
        }';
    }

    wp_register_style('wpiko-chatbot-proactive-inline', false, array(), WPIKO_CHATBOT_VERSION);
    wp_enqueue_style('wpiko-chatbot-proactive-inline');
    wp_add_inline_style('wpiko-chatbot-proactive-inline', $css_content);
}
add_action('wpiko_chatbot_enqueue_frontend_assets', 'wpiko_chatbot_proactive_greeting_styles');

function wpiko_chatbot_proactive_greeting_section() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_proactive_greeting') {
        check_admin_referer('save_proactive_greeting', 'proactive_greeting_nonce');

        update_option('wpiko_chatbot_proactive_enabled', isset($_POST['proactive_enabled']));

        if (isset($_POST['proactive_heading'])) {
            update_option('wpiko_chatbot_proactive_heading', sanitize_text_field(wp_unslash($_POST['proactive_heading'])));
        }

        if (isset($_POST['proactive_message'])) {
            update_option('wpiko_chatbot_proactive_message', sanitize_textarea_field(wp_unslash($_POST['proactive_message'])));
        }

        if (isset($_POST['proactive_trigger'])) {
            $trigger = sanitize_text_field(wp_unslash($_POST['proactive_trigger']));
            if (!in_array($trigger, array('immediate', 'delay', 'scroll'), true)) {
                $trigger = 'delay';
            }
            update_option('wpiko_chatbot_proactive_trigger', $trigger);
        }

        if (isset($_POST['proactive_delay'])) {
            $delay = absint(wp_unslash($_POST['proactive_delay']));
            $delay = min(max($delay, 0), 300);
            update_option('wpiko_chatbot_proactive_delay', $delay);
        }

        if (isset($_POST['proactive_scroll_depth'])) {
            $scroll_depth = absint(wp_unslash($_POST['proactive_scroll_depth']));
            $scroll_depth = min(max($scroll_depth, 1), 100);
            update_option('wpiko_chatbot_proactive_scroll_depth', $scroll_depth);
        }

        if (isset($_POST['proactive_frequency'])) {
            $frequency = sanitize_text_field(wp_unslash($_POST['proactive_frequency']));
            if (!in_array($frequency, array('session', 'once', 'always'), true)) {
                $frequency = 'session';
            }
            update_option('wpiko_chatbot_proactive_frequency', $frequency);
        }

        update_option('wpiko_chatbot_proactive_show_input', isset($_POST['proactive_show_input']) ? '1' : '0');

        if (isset($_POST['proactive_placeholder'])) {
            update_option('wpiko_chatbot_proactive_placeholder', sanitize_text_field(wp_unslash($_POST['proactive_placeholder'])));
        }

        update_option('wpiko_chatbot_proactive_hide_desktop', isset($_POST['proactive_hide_desktop']));
        update_option('wpiko_chatbot_proactive_hide_tablet', isset($_POST['proactive_hide_tablet']));
        update_option('wpiko_chatbot_proactive_hide_mobile', isset($_POST['proactive_hide_mobile']));

        echo '<div class="updated"><p>Proactive Greeting settings updated successfully.</p></div>';
    }

    $proactive_enabled = get_option('wpiko_chatbot_proactive_enabled', false);
    $proactive_heading = get_option('wpiko_chatbot_proactive_heading', 'Hi!');
    $proactive_message = get_option('wpiko_chatbot_proactive_message', "Looking for something specific? We'll help you find it!");
    $proactive_trigger = get_option('wpiko_chatbot_proactive_trigger', 'delay');
    $proactive_delay = get_option('wpiko_chatbot_proactive_delay', 5);
    $proactive_scroll_depth = get_option('wpiko_chatbot_proactive_scroll_depth', 30);
    $proactive_frequency = get_option('wpiko_chatbot_proactive_frequency', 'session');
    $proactive_show_input = get_option('wpiko_chatbot_proactive_show_input', '1');
    $proactive_placeholder = get_option('wpiko_chatbot_proactive_placeholder', 'Write a message...');
    $proactive_hide_desktop = get_option('wpiko_chatbot_proactive_hide_desktop', false);
    $proactive_hide_tablet = get_option('wpiko_chatbot_proactive_hide_tablet', false);
    $proactive_hide_mobile = get_option('wpiko_chatbot_proactive_hide_mobile', false);

    $floating_enabled = get_option('wpiko_chatbot_enable_floating', false);

    if (!in_array($proactive_trigger, array('immediate', 'delay', 'scroll'), true)) {
        $proactive_trigger = 'delay';
    }
    if (!in_array($proactive_frequency, array('session', 'once', 'always'), true)) {
        $proactive_frequency = 'session';
    }
    ?>
    <div class="proactive-greeting-section">
        <h2> <span class="dashicons dashicons-format-status"></span> Proactive Greeting</h2>
        <p class="description">Automatically greet visitors with a short message card next to the chat icon, inviting them to start chatting — no click required.</p>
        <?php if (!$floating_enabled): ?>
            <div class="notice notice-warning inline"><p>The Proactive Greeting requires the <strong>Floating Chatbot</strong> to be enabled. Enable it in the Floating Chatbot tab.</p></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php wp_nonce_field('save_proactive_greeting', 'proactive_greeting_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="proactive_enabled">Enable Proactive Greeting</label></th>
                    <td>
                        <label class="wpiko-switch">
                            <input type="checkbox" name="proactive_enabled" id="proactive_enabled" <?php checked($proactive_enabled); ?>>
                            <span class="wpiko-slider round"></span>
                        </label>
                        <p class="description">Show a greeting card next to the floating chat icon.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_heading">Greeting Heading</label></th>
                    <td>
                        <input type="text" name="proactive_heading" id="proactive_heading" value="<?php echo esc_attr($proactive_heading); ?>" class="regular-text">
                        <p class="description">The bold heading of the greeting card. Leave empty to hide the heading.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_message">Greeting Message</label></th>
                    <td>
                        <textarea name="proactive_message" id="proactive_message" rows="3" class="large-text"><?php echo esc_textarea($proactive_message); ?></textarea>
                        <p class="description">The message inviting visitors to start chatting.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_trigger">When to Appear</label></th>
                    <td>
                        <select name="proactive_trigger" id="proactive_trigger">
                            <option value="immediate" <?php selected($proactive_trigger, 'immediate'); ?>>Immediately on page load</option>
                            <option value="delay" <?php selected($proactive_trigger, 'delay'); ?>>After a delay</option>
                            <option value="scroll" <?php selected($proactive_trigger, 'scroll'); ?>>After scrolling down the page</option>
                        </select>
                        <p class="description">Choose when the greeting card appears to visitors.</p>
                    </td>
                </tr>
                <tr valign="top" class="proactive-delay-row">
                    <th scope="row"><label for="proactive_delay">Delay (seconds)</label></th>
                    <td>
                        <input type="number" name="proactive_delay" id="proactive_delay" value="<?php echo esc_attr($proactive_delay); ?>" class="small-text" min="0" max="300">
                        <p class="description">How many seconds to wait before showing the greeting (0–300).</p>
                    </td>
                </tr>
                <tr valign="top" class="proactive-scroll-row">
                    <th scope="row"><label for="proactive_scroll_depth">Scroll Depth (%)</label></th>
                    <td>
                        <input type="number" name="proactive_scroll_depth" id="proactive_scroll_depth" value="<?php echo esc_attr($proactive_scroll_depth); ?>" class="small-text" min="1" max="100">
                        <p class="description">Show the greeting once the visitor has scrolled this percentage of the page (1–100).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_frequency">How Often to Show</label></th>
                    <td>
                        <select name="proactive_frequency" id="proactive_frequency">
                            <option value="session" <?php selected($proactive_frequency, 'session'); ?>>Once per session</option>
                            <option value="once" <?php selected($proactive_frequency, 'once'); ?>>Only once (per visitor)</option>
                            <option value="always" <?php selected($proactive_frequency, 'always'); ?>>Every page visit</option>
                        </select>
                        <p class="description">"Once per session" resets when the visitor closes their browser tab. "Only once" remembers across visits on the same device.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_show_input">Quick Message Box</label></th>
                    <td>
                        <label class="wpiko-switch">
                            <input type="checkbox" name="proactive_show_input" id="proactive_show_input" <?php checked($proactive_show_input, '1'); ?>>
                            <span class="wpiko-slider round"></span>
                        </label>
                        <p class="description">Let visitors type a message directly from the greeting card. The full chat opens with their message sent.<?php if (wpiko_chatbot_is_pro_plugin_active()): ?> If Email Capture is enabled, visitors will be asked for their details first and their message is sent right after.<?php endif; ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="proactive_placeholder">Message Box Placeholder</label></th>
                    <td>
                        <input type="text" name="proactive_placeholder" id="proactive_placeholder" value="<?php echo esc_attr($proactive_placeholder); ?>" class="regular-text">
                        <p class="description">Placeholder text shown inside the quick message box.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Hide Greeting on Devices</th>
                    <td>
                        <fieldset>
                            <label for="proactive_hide_desktop">
                                <input type="checkbox" name="proactive_hide_desktop" id="proactive_hide_desktop" <?php checked($proactive_hide_desktop); ?>>
                                Desktop
                            </label><br>
                            <label for="proactive_hide_tablet">
                                <input type="checkbox" name="proactive_hide_tablet" id="proactive_hide_tablet" <?php checked($proactive_hide_tablet); ?>>
                                Tablet
                            </label><br>
                            <label for="proactive_hide_mobile">
                                <input type="checkbox" name="proactive_hide_mobile" id="proactive_hide_mobile" <?php checked($proactive_hide_mobile); ?>>
                                Mobile
                            </label>
                        </fieldset>
                        <p class="description">Choose which device types should not display the greeting card. The chatbot device visibility settings from the Floating Chatbot tab also apply.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_proactive_greeting">
            <?php submit_button('Save Proactive Greeting Settings'); ?>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            function toggleTriggerRows() {
                var trigger = $('#proactive_trigger').val();
                $('.proactive-delay-row').toggle(trigger === 'delay');
                $('.proactive-scroll-row').toggle(trigger === 'scroll');
            }
            toggleTriggerRows();
            $('#proactive_trigger').on('change', toggleTriggerRows);
        });
    </script>
    <?php
}

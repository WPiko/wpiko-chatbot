<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Render one color field row: swatch picker + hex text input.
 *
 * @param string $field       Field name, matching a key of wpiko_chatbot_get_style_color_fields().
 * @param string $label       Visible label.
 * @param string $description Helper text below the field.
 */
function wpiko_chatbot_style_color_row($field, $label, $description)
{
    $value = wpiko_chatbot_get_style_color($field);
    ?>
    <tr valign="top">
        <th scope="row"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <div class="wpiko-color-field">
                <input type="color" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>"
                    class="wpiko-color-input" value="<?php echo esc_attr($value); ?>">
                <input type="text" class="wpiko-color-hex" data-color-for="<?php echo esc_attr($field); ?>"
                    value="<?php echo esc_attr(strtoupper($value)); ?>" maxlength="7" spellcheck="false"
                    aria-label="<?php echo esc_attr($label . ' hex value'); ?>">
                <button type="button" class="wpiko-color-reset" data-color-for="<?php echo esc_attr($field); ?>"
                    data-default="<?php echo esc_attr(wpiko_chatbot_get_style_color_default($field)); ?>"
                    title="Reset to default">
                    <span class="dashicons dashicons-image-rotate"></span>
                    <span class="screen-reader-text"><?php echo esc_html('Reset ' . $label . ' to default'); ?></span>
                </button>
            </div>
            <p class="description"><?php echo esc_html($description); ?></p>
        </td>
    </tr>
    <?php
}

function wpiko_chatbot_style_section()
{
    $is_pro_active = defined('WPIKO_CHATBOT_PRO_VERSION');
    $color_fields = wpiko_chatbot_get_style_color_fields();
    $pro_color_fields = wpiko_chatbot_get_pro_style_color_fields();

    if (isset($_POST['action']) && $_POST['action'] == 'save_chatbot_style') {
        check_admin_referer('save_chatbot_style', 'chatbot_style_nonce');

        update_option('wpiko_chatbot_name', wp_kses_post(isset($_POST['chatbot_name']) ? wp_unslash($_POST['chatbot_name']) : ''));
        update_option('wpiko_chatbot_subtitle_text', wp_kses_post(isset($_POST['subtitle_text']) ? wp_unslash($_POST['subtitle_text']) : ''));
        update_option('wpiko_chatbot_image', isset($_POST['chatbot_image']) ? esc_url_raw(wp_unslash($_POST['chatbot_image'])) : '');
        update_option('wpiko_chatbot_welcome_message', wp_kses_post(isset($_POST['welcome_message']) ? wp_unslash($_POST['welcome_message']) : ''));
        update_option('wpiko_chatbot_input_placeholder', wp_kses_post(isset($_POST['input_placeholder']) ? wp_unslash($_POST['input_placeholder']) : ''));

        foreach ($color_fields as $field => $option_name) {
            if (!$is_pro_active && in_array($field, $pro_color_fields, true)) {
                continue;
            }

            $posted = isset($_POST[$field]) ? sanitize_hex_color(wp_unslash($_POST[$field])) : '';
            update_option($option_name, $posted ? $posted : wpiko_chatbot_get_style_color_default($field));
        }

        // Save the user message border setting
        $show_user_border = isset($_POST['show_user_border']) ? '1' : '0';
        update_option('wpiko_chatbot_show_user_border', $show_user_border);

        // Remember which preset the saved colors belong to (or 'custom').
        $posted_preset = isset($_POST['style_preset']) ? sanitize_key(wp_unslash($_POST['style_preset'])) : '';
        update_option('wpiko_chatbot_style_preset', $posted_preset ? $posted_preset : 'custom');

        echo '<div class="updated"><p>Style settings updated successfully.</p></div>';
    } elseif (isset($_POST['action']) && $_POST['action'] == 'reset_advanced_options') {
        check_admin_referer('reset_advanced_options', 'reset_advanced_options_nonce');

        wpiko_chatbot_apply_style_preset(wpiko_chatbot_get_default_style_preset_id(), $is_pro_active);

        echo '<div class="updated"><p>Style options have been reset to the default preset.</p></div>';
    }

    $chatbot_name = get_option('wpiko_chatbot_name', 'My Chatbot');
    $subtitle_text = get_option('wpiko_chatbot_subtitle_text', 'AI assistant');
    $chatbot_image = get_option('wpiko_chatbot_image', WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png');
    $welcome_message = get_option('wpiko_chatbot_welcome_message', 'Welcome! Type your message to start chatting.');
    $input_placeholder = get_option('wpiko_chatbot_input_placeholder', 'Type your message...');
    $show_user_border = get_option('wpiko_chatbot_show_user_border', '1');

    $presets = wpiko_chatbot_get_normalized_style_presets();
    $active_preset = wpiko_chatbot_get_active_style_preset_id();
    ?>

    <div class="style-settings-section">
        <h2> <span class="dashicons dashicons-admin-customizer"></span> Chatbot Style</h2>
        <p class="description">Customize the appearance of your chatbot to match your website's design and enhance user
            experience.</p>

        <div class="wpiko-style-layout">
            <div class="wpiko-style-main">
                <form method="post" action="" id="chatbot-style-form">
                    <?php wp_nonce_field('save_chatbot_style', 'chatbot_style_nonce'); ?>
                    <input type="hidden" name="style_preset" id="style_preset"
                        value="<?php echo esc_attr($active_preset); ?>">

                    <div class="wpiko-style-presets">
                        <div class="wpiko-style-presets-head">
                            <h3>Appearance Presets</h3>
                            <p class="description">Pick a ready-made color scheme, then fine-tune anything below. Nothing
                                is saved until you press <em>Save Style Settings</em>.</p>
                        </div>
                        <div class="wpiko-preset-grid">
                            <?php foreach ($presets as $preset_id => $preset): ?>
                                <button type="button"
                                    class="wpiko-preset-card<?php echo $active_preset === $preset_id ? ' is-active' : ''; ?>"
                                    data-preset="<?php echo esc_attr($preset_id); ?>"
                                    aria-pressed="<?php echo $active_preset === $preset_id ? 'true' : 'false'; ?>">
                                    <span class="wpiko-preset-thumb"
                                        style="background: <?php echo esc_attr($preset['colors']['chatbot_background_color']); ?>; border-color: <?php echo esc_attr($preset['colors']['chatbot_border_color']); ?>;">
                                        <span class="wpiko-preset-thumb-bar"
                                            style="background: <?php echo esc_attr($preset['colors']['chatbot_header_color']); ?>; border-bottom-color: <?php echo esc_attr($preset['colors']['chatbot_border_color']); ?>;">
                                            <span class="wpiko-preset-thumb-dot"
                                                style="background: <?php echo esc_attr($preset['colors']['primary_color']); ?>;"></span>
                                            <span class="wpiko-preset-thumb-line"
                                                style="background: <?php echo esc_attr($preset['colors']['chatbot_name_color']); ?>;"></span>
                                        </span>
                                        <span class="wpiko-preset-thumb-bubble bot"
                                            style="background: <?php echo esc_attr($preset['colors']['bot_background_color']); ?>; color: <?php echo esc_attr($preset['colors']['bot_text_color']); ?>;"></span>
                                        <span class="wpiko-preset-thumb-bubble user"
                                            style="background: <?php echo esc_attr($preset['colors']['user_background_color']); ?>; border-color: <?php echo esc_attr($preset['colors']['user_text_color']); ?>;"></span>
                                        <span class="wpiko-preset-thumb-input"
                                            style="background: <?php echo esc_attr($preset['colors']['input_background_color']); ?>;">
                                            <span class="wpiko-preset-thumb-send"
                                                style="background: <?php echo esc_attr($preset['colors']['primary_color']); ?>;"></span>
                                        </span>
                                    </span>
                                    <span class="wpiko-preset-meta">
                                        <span class="wpiko-preset-name">
                                            <?php echo esc_html($preset['label']); ?>
                                            <span class="wpiko-preset-check dashicons dashicons-yes-alt"
                                                aria-hidden="true"></span>
                                        </span>
                                        <?php if (!empty($preset['description'])): ?>
                                            <span class="wpiko-preset-desc"><?php echo esc_html($preset['description']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="wpiko-preset-custom-note" <?php echo $active_preset === 'custom' ? '' : 'hidden'; ?>>
                            <span class="dashicons dashicons-art" aria-hidden="true"></span>
                            You are using a custom color scheme. Selecting a preset will replace every color below.
                        </p>
                    </div>

                    <h3 class="wpiko-style-group-title">Identity &amp; Text</h3>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><label for="chatbot_name">Chatbot Display Name</label></th>
                            <td>
                                <input type="text" name="chatbot_name" id="chatbot_name"
                                    value="<?php echo esc_attr($chatbot_name); ?>" class="regular-text">
                                <p class="description">Enter the name you want to display for your chatbot.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="subtitle_text">Sub-header Text</label></th>
                            <td>
                                <input type="text" name="subtitle_text" id="subtitle_text"
                                    value="<?php echo esc_attr($subtitle_text); ?>" class="regular-text">
                                <p class="description">Enter the text to display below the chatbot name.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="chatbot_image">Chatbot Image</label></th>
                            <td>
                                <input type="text" name="chatbot_image" id="chatbot_image"
                                    value="<?php echo esc_url($chatbot_image); ?>" class="regular-text">
                                <input type="button" name="upload-btn" id="upload-btn" class="button button-secondary"
                                    value="Upload Image">
                                <p class="description">Upload or enter the URL of the image for your chatbot.</p>
                                <div id="chatbot-image-preview" style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($chatbot_image); ?>" style="max-width: 100px; height: auto;">
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="welcome_message">Welcome Message</label></th>
                            <td>
                                <input type="text" name="welcome_message" id="welcome_message"
                                    value="<?php echo esc_attr($welcome_message); ?>" class="regular-text">
                                <p class="description">Enter a brief welcome message for your chatbot. Leave empty to hide
                                    the welcome message.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><label for="input_placeholder">Input Placeholder</label></th>
                            <td>
                                <input type="text" name="input_placeholder" id="input_placeholder"
                                    value="<?php echo esc_attr($input_placeholder); ?>" class="regular-text">
                                <p class="description">Enter the placeholder text for the chat input field.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 class="wpiko-style-group-title">Main Colors</h3>
                    <table class="form-table">
                        <?php
                        wpiko_chatbot_style_color_row('primary_color', 'Primary Color', 'Used for the send button, links and highlights.');
                        wpiko_chatbot_style_color_row('primary_text_color', 'Primary Text Color', 'Text and icons drawn on top of the primary color.');
                        ?>
                        <tr valign="top">
                            <th scope="row">Advanced Style Options</th>
                            <td>
                                <button type="button" id="toggle-advanced-options" class="button button-secondary">Show
                                    Advanced Options</button>
                                <button type="button" id="reset-advanced-options" class="button button-secondary">Reset
                                    Advanced Options</button>
                                <p class="description">Advanced options control every remaining surface of the chatbot.</p>
                            </td>
                        </tr>
                    </table>

                    <div id="advanced-options" style="display: none;">

                        <h3 class="wpiko-style-group-title">Chat Window</h3>
                        <table class="form-table">
                            <?php
                            wpiko_chatbot_style_color_row('chatbot_background_color', 'Chatbot Background Color', 'The main background of the chat window.');
                            wpiko_chatbot_style_color_row('chatbot_header_color', 'Chatbot Header Color', 'Background of the header bar at the top.');
                            wpiko_chatbot_style_color_row('chatbot_border_color', 'Chatbot Border Color', 'Borders and dividers inside the chatbot.');
                            wpiko_chatbot_style_color_row('chatbot_name_color', 'Chatbot Name Color', 'The chatbot name, sub-header and floating text.');
                            ?>
                        </table>

                        <h3 class="wpiko-style-group-title">Messages</h3>
                        <table class="form-table">
                            <?php wpiko_chatbot_style_color_row('user_background_color', 'User Background Color', 'Background of messages sent by the visitor.'); ?>
                            <?php wpiko_chatbot_style_color_row('user_text_color', 'User Text Color', 'Text color of messages sent by the visitor.'); ?>
                            <tr valign="top">
                                <th scope="row"><label for="show_user_border">User Message Border</label></th>
                                <td>
                                    <label class="wpiko-switch">
                                        <input type="checkbox" name="show_user_border" id="show_user_border" value="1" <?php checked('1', $show_user_border); ?>>
                                        <span class="wpiko-slider round"></span>
                                    </label>
                                    <p class="description">Enable or disable border around user messages and user avatar.
                                    </p>
                                </td>
                            </tr>
                            <?php
                            wpiko_chatbot_style_color_row('bot_background_color', 'Bot Background Color', 'Background of messages sent by the chatbot.');
                            wpiko_chatbot_style_color_row('bot_text_color', 'Bot Text Color', 'Text color of messages sent by the chatbot. Also used for the input field text.');
                            ?>
                        </table>

                        <h3 class="wpiko-style-group-title">Input &amp; Icons</h3>
                        <table class="form-table">
                            <?php
                            wpiko_chatbot_style_color_row('icon_color', 'Icon Color', 'The menu icon in the chatbot interface.');
                            wpiko_chatbot_style_color_row('input_background_color', 'Input Background Color', 'Background color of the chat input area.');
                            wpiko_chatbot_style_color_row('floating_text_bg_color', 'Floating Text Background Color', 'Background of the small text bubble next to the floating chatbot button.');
                            ?>
                        </table>

                        <?php if ($is_pro_active): ?>
                            <h3 class="wpiko-style-group-title">Live Chat Takeover <span class="wpiko-style-pro-badge">Pro</span>
                            </h3>
                            <table class="form-table">
                                <?php
                                wpiko_chatbot_style_color_row('admin_message_label_color', 'Admin Takeover Label Color', 'Label shown above messages sent by a human agent.');
                                wpiko_chatbot_style_color_row('admin_message_background_color', 'Admin Takeover Message Background', 'Background of messages sent by a human agent.');
                                wpiko_chatbot_style_color_row('admin_message_border_color', 'Admin Takeover Message Border', 'Border of messages sent by a human agent.');
                                wpiko_chatbot_style_color_row('admin_message_text_color', 'Admin Takeover Message Text', 'Text color of messages sent by a human agent.');
                                ?>
                            </table>
                        <?php endif; ?>

                    </div>

                    <input type="hidden" name="action" value="save_chatbot_style">
                    <div class="wpiko-style-actions">
                        <?php submit_button('Save Style Settings', 'primary', 'submit', false); ?>
                        <span class="wpiko-style-dirty" hidden>
                            <span class="dashicons dashicons-warning" aria-hidden="true"></span> Unsaved changes
                        </span>
                    </div>
                </form>
            </div>

            <aside class="wpiko-style-side">
                <div class="wpiko-style-preview-wrap">
                    <div class="wpiko-style-preview-head">
                        <h3>Live Preview</h3>
                        <span class="wpiko-style-preview-hint">Updates as you edit</span>
                    </div>

                    <div id="wpiko-style-preview" class="wpiko-style-preview">
                        <div class="wpiko-preview-header">
                            <span class="wpiko-preview-avatar">
                                <img src="<?php echo esc_url($chatbot_image); ?>" alt="" id="wpiko-preview-image">
                                <span class="wpiko-preview-status-dot"></span>
                            </span>
                            <span class="wpiko-preview-identity">
                                <span class="wpiko-preview-name" id="wpiko-preview-name"><?php echo esc_html($chatbot_name); ?></span>
                                <span class="wpiko-preview-subtitle"
                                    id="wpiko-preview-subtitle"><?php echo esc_html($subtitle_text); ?></span>
                            </span>
                            <span class="wpiko-preview-menu" aria-hidden="true">
                                <span></span><span></span><span></span>
                            </span>
                        </div>

                        <div class="wpiko-preview-body">
                            <?php // The welcome message is the first bot message, exactly as on the front end. ?>
                            <div class="wpiko-preview-row bot" id="wpiko-preview-welcome-row"
                                <?php echo $welcome_message === '' ? 'hidden' : ''; ?>>
                                <span class="wpiko-preview-msg-avatar">
                                    <img src="<?php echo esc_url($chatbot_image); ?>" alt="" id="wpiko-preview-msg-image">
                                </span>
                                <div class="wpiko-preview-bubble bot" id="wpiko-preview-welcome"><?php echo esc_html($welcome_message); ?></div>
                            </div>

                            <div class="wpiko-preview-row user">
                                <div class="wpiko-preview-bubble user">Do you ship internationally?</div>
                                <span class="wpiko-preview-msg-avatar user">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </span>
                            </div>

                            <div class="wpiko-preview-row bot">
                                <span class="wpiko-preview-msg-avatar">
                                    <img src="<?php echo esc_url($chatbot_image); ?>" alt="" id="wpiko-preview-msg-image-2">
                                </span>
                                <div class="wpiko-preview-bubble bot">Yes — we deliver to over 40 countries.</div>
                            </div>

                            <?php if ($is_pro_active): ?>
                                <div class="wpiko-preview-row admin">
                                    <div class="wpiko-preview-admin-msg">
                                        <span class="wpiko-preview-admin-label">Live agent</span>
                                        A team member has joined the chat.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="wpiko-preview-input">
                            <span class="wpiko-preview-placeholder"
                                id="wpiko-preview-placeholder"><?php echo esc_html($input_placeholder); ?></span>
                            <span class="wpiko-preview-send">
                                <?php // Same artwork as #chatbot-send in chatbot-interface.php. ?>
                                <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                    <path fill="currentColor" fill-rule="evenodd"
                                        d="M15.192 8.906a1.143 1.143 0 0 1 1.616 0l5.143 5.143a1.143 1.143 0 0 1-1.616 1.616l-3.192-3.192v9.813a1.143 1.143 0 0 1-2.286 0v-9.813l-3.192 3.192a1.143 1.143 0 1 1-1.616-1.616z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="wpiko-preview-floating">
                        <span class="wpiko-preview-floating-text" id="wpiko-preview-floating-text">Need help?</span>
                        <span class="wpiko-preview-floating-icon">
                            <?php // Same artwork as the #wpiko-chatbot-floating-icon mask in css/wpiko-chatbot.css. ?>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path>
                            </svg>
                        </span>
                    </div>

                    <p class="description wpiko-preview-note">The preview is an approximation — fonts and spacing follow
                        your theme on the front end.</p>
                </div>
            </aside>
        </div>

        <form method="post" action="" id="reset-advanced-options-form" style="display: none;">
            <?php wp_nonce_field('reset_advanced_options', 'reset_advanced_options_nonce'); ?>
            <input type="hidden" name="action" value="reset_advanced_options">
        </form>
    </div>
    <?php
}

<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_floating_chatbot_styles() {
    $floating_position = get_option('wpiko_chatbot_floating_position', 'right');
    $hide_on_desktop = get_option('wpiko_chatbot_hide_on_desktop', false);
    $hide_on_tablet = get_option('wpiko_chatbot_hide_on_tablet', false);
    $hide_on_mobile = get_option('wpiko_chatbot_hide_on_mobile', false);
    $allowed_positions = array('right', 'left', 'top-right', 'top-left');

    if (!in_array($floating_position, $allowed_positions, true)) {
        $floating_position = 'right';
    }

    $is_right = in_array($floating_position, array('right', 'top-right'), true);
    $is_left = in_array($floating_position, array('left', 'top-left'), true);
    $is_top = in_array($floating_position, array('top-right', 'top-left'), true);
    $is_bottom = !$is_top;
    
    $position_styles = array(
        'floating_position_top' => $is_top ? '20px' : 'auto',
        'floating_position_bottom' => $is_bottom ? '20px' : 'auto',
        'floating_position_right' => $is_right ? '20px' : 'auto',
        'floating_position_left' => $is_left ? '20px' : 'auto',
        'floating_wrapper_top' => $is_top ? '30px' : 'auto',
        'floating_wrapper_bottom' => $is_bottom ? '30px' : 'auto',
        'floating_wrapper_right' => $is_right ? '80px' : 'auto',
        'floating_wrapper_left' => $is_left ? '80px' : 'auto',
        'floating_container_top' => $is_top ? '100px' : 'auto',
        'floating_container_bottom' => $is_bottom ? '100px' : 'auto',
        'floating_container_right' => $is_right ? '20px' : 'auto',
        'floating_container_left' => $is_left ? '20px' : 'auto',
        
        'floating_wrapper_text_right' => $is_right ? '10px' : '0',
        'floating_wrapper_text_left' => $is_left ? '10px' : '0'
    );
    
    // Create the CSS content
    $css_content = ':root {
        --floating-position-top: ' . esc_attr($position_styles['floating_position_top']) . ';
        --floating-position-bottom: ' . esc_attr($position_styles['floating_position_bottom']) . ';
        --floating-position-right: ' . esc_attr($position_styles['floating_position_right']) . ';
        --floating-position-left: ' . esc_attr($position_styles['floating_position_left']) . ';
        --floating-wrapper-top: ' . esc_attr($position_styles['floating_wrapper_top']) . ';
        --floating-wrapper-bottom: ' . esc_attr($position_styles['floating_wrapper_bottom']) . ';
        --floating-wrapper-right: ' . esc_attr($position_styles['floating_wrapper_right']) . ';
        --floating-wrapper-left: ' . esc_attr($position_styles['floating_wrapper_left']) . ';
        --floating-container-top: ' . esc_attr($position_styles['floating_container_top']) . ';
        --floating-container-bottom: ' . esc_attr($position_styles['floating_container_bottom']) . ';
        --floating-container-right: ' . esc_attr($position_styles['floating_container_right']) . ';
        --floating-container-left: ' . esc_attr($position_styles['floating_container_left']) . ';
        
        --floating-wrapper-text-right: ' . esc_attr($position_styles['floating_wrapper_text_right']) . ';
        --floating-wrapper-text-left: ' . esc_attr($position_styles['floating_wrapper_text_left']) . ';
    }';

    if ($hide_on_desktop) {
        $css_content .= '@media screen and (min-width: 1025px) {
            #wpiko-chatbot-floating-wrapper,
            #wpiko-chatbot-floating-container {
                display: none !important;
            }
        }';
    }

    if ($hide_on_tablet) {
        $css_content .= '@media screen and (min-width: 768px) and (max-width: 1024px) {
            #wpiko-chatbot-floating-wrapper,
            #wpiko-chatbot-floating-container {
                display: none !important;
            }
        }';
    }

    if ($hide_on_mobile) {
        $css_content .= '@media screen and (max-width: 767px) {
            #wpiko-chatbot-floating-wrapper,
            #wpiko-chatbot-floating-container {
                display: none !important;
            }
        }';
    }
    
    // Register a dummy handle and add inline CSS using WordPress function
    wp_register_style('wpiko-chatbot-floating-position-inline', false, array(), WPIKO_CHATBOT_VERSION);
    wp_enqueue_style('wpiko-chatbot-floating-position-inline');
    wp_add_inline_style('wpiko-chatbot-floating-position-inline', $css_content);
}

add_action('wpiko_chatbot_enqueue_frontend_assets', 'wpiko_floating_chatbot_styles');

function wpiko_chatbot_floating_chatbot_section() {
    $settings_saved = false;
    if (isset($_POST['action']) && $_POST['action'] == 'save_floating_chatbot') {
        check_admin_referer('save_floating_chatbot', 'floating_chatbot_nonce');
        $allowed_positions = array('right', 'left', 'top-right', 'top-left');
        
        update_option('wpiko_chatbot_enable_floating', isset($_POST['enable_floating_chatbot']));
        
        if (isset($_POST['chatbot_floating_position'])) {
            $floating_position = sanitize_text_field(wp_unslash($_POST['chatbot_floating_position']));

            if (!in_array($floating_position, $allowed_positions, true)) {
                $floating_position = 'right';
            }

            update_option('wpiko_chatbot_floating_position', $floating_position);
        }
        
        if (isset($_POST['floating_text'])) {
            update_option('wpiko_chatbot_floating_text', sanitize_text_field(wp_unslash($_POST['floating_text'])));
        }
        
        update_option('wpiko_chatbot_exclude_home', isset($_POST['exclude_home']));
        update_option('wpiko_chatbot_exclude_blog', isset($_POST['exclude_blog']));
        update_option('wpiko_chatbot_exclude_articles', isset($_POST['exclude_articles']));
        update_option('wpiko_chatbot_exclude_cart', isset($_POST['exclude_cart']));
        update_option('wpiko_chatbot_exclude_checkout', isset($_POST['exclude_checkout']));
        update_option('wpiko_chatbot_hide_on_desktop', isset($_POST['hide_on_desktop']));
        update_option('wpiko_chatbot_hide_on_tablet', isset($_POST['hide_on_tablet']));
        update_option('wpiko_chatbot_hide_on_mobile', isset($_POST['hide_on_mobile']));
        
        if (isset($_POST['custom_exclusions'])) {
            update_option('wpiko_chatbot_custom_exclusions', sanitize_textarea_field(wp_unslash($_POST['custom_exclusions'])));
        }
        
        // Validate, unslash, and sanitize floating width and height
        if (isset($_POST['chatbot_floating_width'])) {
            update_option('wpiko_chatbot_floating_width', absint(wp_unslash($_POST['chatbot_floating_width'])));
        }
        
        if (isset($_POST['chatbot_floating_height'])) {
            update_option('wpiko_chatbot_floating_height', absint(wp_unslash($_POST['chatbot_floating_height'])));
        }
        
        $settings_saved = true;
    }
    
    $enable_floating_chatbot = get_option('wpiko_chatbot_enable_floating', false);
    $floating_text = get_option('wpiko_chatbot_floating_text', 'Chat with us');
    $exclude_home = get_option('wpiko_chatbot_exclude_home', false);
    $exclude_blog = get_option('wpiko_chatbot_exclude_blog', false);
    $exclude_articles = get_option('wpiko_chatbot_exclude_articles', false);
    $exclude_cart = get_option('wpiko_chatbot_exclude_cart', false);
    $exclude_checkout = get_option('wpiko_chatbot_exclude_checkout', false);
    $hide_on_desktop = get_option('wpiko_chatbot_hide_on_desktop', false);
    $hide_on_tablet = get_option('wpiko_chatbot_hide_on_tablet', false);
    $hide_on_mobile = get_option('wpiko_chatbot_hide_on_mobile', false);
    $custom_exclusions = get_option('wpiko_chatbot_custom_exclusions', '');
    
    $chatbot_floating_width = get_option('wpiko_chatbot_floating_width', 380);
    $chatbot_floating_height = get_option('wpiko_chatbot_floating_height', 620);
    $floating_position = get_option('wpiko_chatbot_floating_position', 'right');

    if (!in_array($floating_position, array('right', 'left', 'top-right', 'top-left'), true)) {
        $floating_position = 'right';
    }
    
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-floating-chatbot-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Floating Chatbot</h1>
            <p class="wpiko-chatbot-settings-subtitle">Configure a floating chat icon so visitors can open your chatbot from anywhere on your website.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Floating Chatbot settings updated successfully.</p></div>
        <?php endif; ?>
        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_floating_chatbot', 'floating_chatbot_nonce'); ?>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="floating-display-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="floating-display-title">Display settings</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Floating chatbot</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <label class="wpiko-chatbot-settings-switch" for="enable_floating_chatbot">
                            <input type="checkbox" role="switch" name="enable_floating_chatbot" id="enable_floating_chatbot" aria-describedby="enable_floating_chatbot-description" <?php checked($enable_floating_chatbot); ?>>
                            <span class="wpiko-chatbot-settings-switch__track" aria-hidden="true"></span>
                            <span class="wpiko-chatbot-settings-switch__label">Enable floating chatbot</span>
                        </label>
                        <p class="wpiko-chatbot-settings-field-description" id="enable_floating_chatbot-description">Display a floating chat icon on your website.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="chatbot_floating_position">Icon position</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <select name="chatbot_floating_position" id="chatbot_floating_position" aria-describedby="chatbot_floating_position-description">
                            <option value="right" <?php selected($floating_position, 'right'); ?>>Bottom Right</option>
                            <option value="left" <?php selected($floating_position, 'left'); ?>>Bottom Left</option>
                            <option value="top-right" <?php selected($floating_position, 'top-right'); ?>>Top Right</option>
                            <option value="top-left" <?php selected($floating_position, 'top-left'); ?>>Top Left</option>
                        </select>
                        <p class="wpiko-chatbot-settings-field-description" id="chatbot_floating_position-description">Choose which corner of the screen displays the floating chat icon.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="floating_text">Floating text</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <input type="text" name="floating_text" id="floating_text" value="<?php echo esc_attr($floating_text); ?>" aria-describedby="floating_text-description">
                        <p class="wpiko-chatbot-settings-field-description" id="floating_text-description">Text displayed next to the chat icon. Leave empty to hide the text.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Chat window size</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <div class="wpiko-chatbot-settings-inline-fields">
                            <label for="chatbot_floating_width">Width (px)
                                <input type="number" name="chatbot_floating_width" id="chatbot_floating_width" value="<?php echo esc_attr($chatbot_floating_width); ?>" aria-describedby="floating-size-description">
                            </label>
                            <label for="chatbot_floating_height">Height (px)
                                <input type="number" name="chatbot_floating_height" id="chatbot_floating_height" value="<?php echo esc_attr($chatbot_floating_height); ?>" aria-describedby="floating-size-description">
                            </label>
                        </div>
                        <p class="wpiko-chatbot-settings-field-description" id="floating-size-description">Set the width of the chat container and the height of the messages area in pixels.</p>
                    </div>
                </div>
            </section>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="floating-visibility-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="floating-visibility-title">Visibility &amp; exclusions</h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Hide on devices</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <fieldset class="wpiko-chatbot-settings-checkbox-group">
                            <legend class="screen-reader-text">Hide floating chatbot on devices</legend>
                            <label for="hide_on_desktop"><input type="checkbox" name="hide_on_desktop" id="hide_on_desktop" <?php checked($hide_on_desktop); ?>> Desktop</label>
                            <label for="hide_on_tablet"><input type="checkbox" name="hide_on_tablet" id="hide_on_tablet" <?php checked($hide_on_tablet); ?>> Tablet</label>
                            <label for="hide_on_mobile"><input type="checkbox" name="hide_on_mobile" id="hide_on_mobile" <?php checked($hide_on_mobile); ?>> Mobile</label>
                        </fieldset>
                        <p class="wpiko-chatbot-settings-field-description">Choose which device types should not display the floating chatbot.</p>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <div class="wpiko-chatbot-settings-field-label">Exclude pages</div>
                    <div class="wpiko-chatbot-settings-field-control">
                        <fieldset class="wpiko-chatbot-settings-checkbox-group">
                            <legend class="screen-reader-text">Exclude floating chatbot from pages</legend>
                            <label for="exclude_home"><input type="checkbox" name="exclude_home" id="exclude_home" <?php checked($exclude_home); ?>> Home Page</label>
                            <label for="exclude_blog"><input type="checkbox" name="exclude_blog" id="exclude_blog" <?php checked($exclude_blog); ?>> Posts Page</label>
                            <label for="exclude_articles"><input type="checkbox" name="exclude_articles" id="exclude_articles" <?php checked($exclude_articles); ?>> Article Pages</label>
                            <label for="exclude_cart"><input type="checkbox" name="exclude_cart" id="exclude_cart" <?php checked($exclude_cart); ?>> Cart Page</label>
                            <label for="exclude_checkout"><input type="checkbox" name="exclude_checkout" id="exclude_checkout" <?php checked($exclude_checkout); ?>> Checkout Page</label>
                        </fieldset>
                    </div>
                </div>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="custom_exclusions">Custom page exclusions</label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <textarea name="custom_exclusions" id="custom_exclusions" rows="4" aria-describedby="custom_exclusions-description"><?php echo esc_textarea($custom_exclusions); ?></textarea>
                        <p class="wpiko-chatbot-settings-field-description" id="custom_exclusions-description">Enter one URL path per line, such as <code>/about-us/</code> or <code>/contact-us/</code>.</p>
                    </div>
                </div>
            </section>
            <input type="hidden" name="action" value="save_floating_chatbot">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Floating Chatbot Settings</button>
            </div>
        </form>
    </div>
    <?php
}

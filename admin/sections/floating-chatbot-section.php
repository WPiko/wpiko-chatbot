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

add_action('wp_enqueue_scripts', 'wpiko_floating_chatbot_styles');

function wpiko_chatbot_floating_chatbot_section() {
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
        
        echo '<div class="updated"><p>Floating Chatbot settings updated successfully.</p></div>';
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
    <div class="floating-chatbot-section">
        <h2> <span class="dashicons dashicons-admin-settings"></span> Floating Chatbot</h2>
        <p class="description">Configure a chatbot that appears as a floating icon on your website, allowing easy access for visitors.</p>
        <form method="post" action="">
            <?php wp_nonce_field('save_floating_chatbot', 'floating_chatbot_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="enable_floating_chatbot">Enable Floating Chatbot</label></th>
                    <td>
                        <label class="wpiko-switch">
                            <input type="checkbox" name="enable_floating_chatbot" id="enable_floating_chatbot" <?php checked($enable_floating_chatbot); ?>>
                            <span class="wpiko-slider round"></span>
                        </label>
                        <p class="description">Enable a floating chatbot icon on your website.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="chatbot_floating_position">Floating Icon Position</label></th>
                    <td>
                        <select name="chatbot_floating_position" id="chatbot_floating_position">
                            <option value="right" <?php selected($floating_position, 'right'); ?>>Bottom Right</option>
                            <option value="left" <?php selected($floating_position, 'left'); ?>>Bottom Left</option>
                            <option value="top-right" <?php selected($floating_position, 'top-right'); ?>>Top Right</option>
                            <option value="top-left" <?php selected($floating_position, 'top-left'); ?>>Top Left</option>
                        </select>
                       <p class="description">Choose which corner of the screen to display the floating chatbot icon.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="floating_text">Chatbot Floating Text</label></th>
                    <td>
                        <input type="text" name="floating_text" id="floating_text" value="<?php echo esc_attr($floating_text); ?>" class="regular-text">
                        <p class="description">Enter the text to display next to the chat icon. Leave empty to hide the text.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Hide Chatbot on Devices</th>
                    <td>
                        <fieldset>
                            <label for="hide_on_desktop">
                                <input type="checkbox" name="hide_on_desktop" id="hide_on_desktop" <?php checked($hide_on_desktop); ?>>
                                Desktop
                            </label><br>
                            <label for="hide_on_tablet">
                                <input type="checkbox" name="hide_on_tablet" id="hide_on_tablet" <?php checked($hide_on_tablet); ?>>
                                Tablet
                            </label><br>
                            <label for="hide_on_mobile">
                                <input type="checkbox" name="hide_on_mobile" id="hide_on_mobile" <?php checked($hide_on_mobile); ?>>
                                Mobile
                            </label>
                        </fieldset>
                        <p class="description">Choose which device types should not display the floating chatbot widget.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Exclude Chatbot from Pages</th>
                    <td>
                        <fieldset>
                            <label for="exclude_home">
                                <input type="checkbox" name="exclude_home" id="exclude_home" <?php checked($exclude_home); ?>>
                                Home Page
                            </label><br>
                            <label for="exclude_blog">
                                <input type="checkbox" name="exclude_blog" id="exclude_blog" <?php checked($exclude_blog); ?>>
                                Posts Page
                            </label><br>
                            <label for="exclude_articles">
                                <input type="checkbox" name="exclude_articles" id="exclude_articles" <?php checked($exclude_articles); ?>>
                                Article Pages
                            </label><br>
                            <label for="exclude_cart">
                                <input type="checkbox" name="exclude_cart" id="exclude_cart" <?php checked($exclude_cart); ?>>
                                Cart Page
                            </label><br>
                            <label for="exclude_checkout">
                                <input type="checkbox" name="exclude_checkout" id="exclude_checkout" <?php checked($exclude_checkout); ?>>
                                Checkout Page
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="custom_exclusions">Custom Page Exclusions</label></th>
                    <td>
                        <textarea name="custom_exclusions" id="custom_exclusions" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($custom_exclusions); ?></textarea>
                        <p class="description">Enter page URL paths to hide the chatbot, one per line. For example:<br>/about-us/<br>/contact-us/</p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="chatbot_floating_width">Floating Chatbot Width (px)</label></th>
                    <td>
                        <input type="number" name="chatbot_floating_width" id="chatbot_floating_width" value="<?php echo esc_attr($chatbot_floating_width); ?>" class="small-text">
                        <p class="description">Enter the width of the floating chatbot container in pixels.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="chatbot_floating_height">Floating Chatbot Height (px)</label></th>
                    <td>
                        <input type="number" name="chatbot_floating_height" id="chatbot_floating_height" value="<?php echo esc_attr($chatbot_floating_height); ?>" class="small-text">
                        <p class="description">Enter the height of the floating chatbot messages area in pixels.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_floating_chatbot">
            <?php submit_button('Save Floating Chatbot Settings'); ?>
        </form>
    </div>
    <?php
}

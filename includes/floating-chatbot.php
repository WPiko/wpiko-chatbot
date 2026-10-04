<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Whether the chatbot has an API key and can answer visitors.
 *
 * @return bool
 */
function wpiko_chatbot_is_configured()
{
    static $configured = null;
    if ($configured === null) {
        $configured = (string) wpiko_chatbot_decrypt_api_key(get_option('wpiko_chatbot_api_key', '')) !== '';
    }
    return $configured;
}

/**
 * Whether the floating chatbot should be shown on the current front-end page.
 *
 * Until an API key is added, the chatbot is only shown to site admins, so
 * visitors never see a chatbot that cannot answer.
 *
 * @return bool
 */
function wpiko_chatbot_floating_should_display()
{
    static $result = null;
    if ($result !== null) {
        return $result;
    }

    $result = false;

    if (!get_option('wpiko_chatbot_enable_floating', false)) {
        return $result;
    }

    if (!wpiko_chatbot_is_configured() && !current_user_can('manage_options')) {
        return $result;
    }

    $exclude_home = get_option('wpiko_chatbot_exclude_home', false);
    $exclude_blog = get_option('wpiko_chatbot_exclude_blog', false);
    $exclude_articles = get_option('wpiko_chatbot_exclude_articles', false);
    $exclude_cart = get_option('wpiko_chatbot_exclude_cart', false);
    $exclude_checkout = get_option('wpiko_chatbot_exclude_checkout', false);
    $custom_exclusions = get_option('wpiko_chatbot_custom_exclusions', '');

    $body_class = get_body_class();
    $show_chatbot = true;

    // The page embeds the chatbot with the shortcode, so the floating one is not needed.
    if (wpiko_chatbot_current_page_has_shortcode()) {
        $show_chatbot = false;
    }

    // Exclusion checks
    if (in_array('home', $body_class) && $exclude_home) {
        $show_chatbot = false;
    } elseif (in_array('blog', $body_class) && $exclude_blog) {
        $show_chatbot = false;
    } elseif (in_array('post-template-default', $body_class) && $exclude_articles) {
        $show_chatbot = false;
    } elseif (function_exists('is_cart') && is_cart() && $exclude_cart) {
        $show_chatbot = false;
    } elseif (function_exists('is_checkout') && is_checkout() && $exclude_checkout) {
        $show_chatbot = false;
    }

    // Check custom exclusions
    if ($show_chatbot && !empty($custom_exclusions)) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $current_url = trailingslashit(wp_parse_url($request_uri, PHP_URL_PATH));
        $exclusion_list = array_map('trim', explode("\n", $custom_exclusions));
        foreach ($exclusion_list as $exclusion) {
            if ($exclusion !== '' && trailingslashit($exclusion) === $current_url) {
                $show_chatbot = false;
                break;
            }
        }
    }

    $result = (bool) apply_filters('wpiko_chatbot_show_floating', $show_chatbot);
    return $result;
}

/**
 * Whether the current singular page embeds the [wpiko_chatbot] shortcode.
 *
 * Covers the post content and Elementor data. Other page builders are covered
 * by the late-loading fallback in the shortcode callback.
 *
 * @return bool
 */
function wpiko_chatbot_current_page_has_shortcode()
{
    static $has_shortcode = null;
    if ($has_shortcode !== null) {
        return $has_shortcode;
    }

    $has_shortcode = false;
    $post = get_queried_object();

    if ($post instanceof WP_Post) {
        if (has_shortcode($post->post_content, 'wpiko_chatbot')) {
            $has_shortcode = true;
        } else {
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
            if (is_string($elementor_data) && strpos($elementor_data, 'wpiko_chatbot') !== false) {
                $has_shortcode = true;
            }
        }
    }

    return $has_shortcode;
}

// Function to display the floating chatbot
function wpiko_chatbot_add_floating_icon()
{
    if (wpiko_chatbot_floating_should_display()) {
        $show_chatbot = true;

        if ($show_chatbot) {
            // Use defined version constant
            $version = WPIKO_CHATBOT_VERSION;

            // Get the full chatbot display and extract CSS separately
            $chatbot_output = wpiko_chatbot_display();

            // Extract CSS from the output and handle it properly
            if (preg_match('/<style>(.*?)<\/style>/s', $chatbot_output, $style_matches)) {
                // Use WordPress proper way to add inline CSS
                $css_content = wp_strip_all_tags($style_matches[1]);
                if (!empty($css_content)) {
                    // Register a dummy handle and add inline CSS using WordPress function
                    wp_register_style('wpiko-chatbot-floating-inline', false, array(), $version, true);
                    wp_enqueue_style('wpiko-chatbot-floating-inline');
                    wp_add_inline_style('wpiko-chatbot-floating-inline', $css_content);
                }
            }

            // Remove style tags from output before sanitizing
            $chatbot_output_no_style = preg_replace('/<style>.*?<\/style>/s', '', $chatbot_output);

            // Create a custom kses allowlist that includes SVG elements and attributes
            $allowed_html = wp_kses_allowed_html('post');

            // Add SVG support
            $allowed_html['svg'] = array(
                'class' => true,
                'aria-hidden' => true,
                'aria-labelledby' => true,
                'role' => true,
                'xmlns' => true,
                'width' => true,
                'height' => true,
                'viewbox' => true,
                'viewBox' => true,
                'fill' => true,
                'fill-rule' => true,
                'fill-opacity' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
                'stroke-opacity' => true,
                'clip-rule' => true,
            );
            $allowed_html['g'] = array('fill' => true, 'fill-rule' => true, 'clip-rule' => true);
            $allowed_html['title'] = array('title' => true);
            $allowed_html['path'] = array(
                'd' => true,
                'fill' => true,
                'fill-rule' => true,
                'clip-rule' => true,
                'stroke' => true,
                'stroke-width' => true,
            );
            $allowed_html['circle'] = array(
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            );
            $allowed_html['polygon'] = array(
                'points' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            );
            $allowed_html['polyline'] = array(
                'points' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            );
            $allowed_html['line'] = array(
                'x1' => true,
                'x2' => true,
                'y1' => true,
                'y2' => true,
                'stroke' => true,
                'stroke-width' => true,
            );
            $allowed_html['rect'] = array(
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'rx' => true,
                'ry' => true,
                'fill' => true,
                'stroke' => true,
                'stroke-width' => true,
            );

            // Add button aria-label
            if (!isset($allowed_html['button'])) {
                $allowed_html['button'] = array();
            }
            $allowed_html['button']['aria-label'] = true;
            $allowed_html['button']['aria-expanded'] = true;
            $allowed_html['button']['title'] = true;
            $allowed_html['button']['type'] = true;

            // Add textarea placeholder support
            if (!isset($allowed_html['textarea'])) {
                $allowed_html['textarea'] = array();
            }
            $allowed_html['textarea']['placeholder'] = true;
            $allowed_html['textarea']['id'] = true;
            $allowed_html['textarea']['rows'] = true;
            $allowed_html['textarea']['cols'] = true;

            // Add input placeholder support
            if (!isset($allowed_html['input'])) {
                $allowed_html['input'] = array();
            }
            $allowed_html['input']['placeholder'] = true;
            $allowed_html['input']['type'] = true;
            $allowed_html['input']['id'] = true;
            $allowed_html['input']['value'] = true;

            $floating_text = get_option('wpiko_chatbot_floating_text', '');
            echo '<div id="wpiko-chatbot-floating-wrapper">';
            if (!empty($floating_text)) {
                echo '<span id="wpiko-chatbot-floating-text">' . esc_html($floating_text) . '</span>';
            }
            echo '<div id="wpiko-chatbot-floating-icon">';
            echo '<span id="wpiko-chatbot-notification-badge" style="display:none;"></span>';
            echo '</div>';
            echo '</div>';
            echo '<div id="wpiko-chatbot-floating-container" style="display:none;">';
            echo wp_kses($chatbot_output_no_style, $allowed_html);
            echo '</div>';

            // Proactive greeting card (hidden by default; shown by JS based on trigger/frequency settings)
            if (get_option('wpiko_chatbot_proactive_enabled', false)) {
                wpiko_chatbot_render_proactive_greeting();
            }
        }
    }
}
add_action('wp_footer', 'wpiko_chatbot_add_floating_icon');

/**
 * Render the proactive greeting card markup.
 * Hidden by default; js/wpiko-chatbot-proactive.js decides when to reveal it.
 */
function wpiko_chatbot_render_proactive_greeting()
{
    $heading = get_option('wpiko_chatbot_proactive_heading', 'Hi!');
    $message = get_option('wpiko_chatbot_proactive_message', "Looking for something specific? We'll help you find it!");
    $show_input = get_option('wpiko_chatbot_proactive_show_input', '1') === '1';
    $placeholder = get_option('wpiko_chatbot_proactive_placeholder', 'Write a message...');

    // Nothing to display without a heading or message
    if ($heading === '' && $message === '') {
        return;
    }

    echo '<div id="wpiko-chatbot-proactive-greeting" style="display:none;" role="complementary" aria-label="Chat greeting">';
    echo '<button type="button" id="wpiko-chatbot-proactive-close" aria-label="Dismiss greeting">&times;</button>';
    echo '<div id="wpiko-chatbot-proactive-bubble">';
    if ($heading !== '') {
        echo '<div id="wpiko-chatbot-proactive-heading">' . esc_html($heading) . '</div>';
    }
    if ($message !== '') {
        echo '<div id="wpiko-chatbot-proactive-message">' . esc_html($message) . '</div>';
    }
    echo '</div>';
    if ($show_input) {
        echo '<div id="wpiko-chatbot-proactive-input-row">';
        echo '<input type="text" id="wpiko-chatbot-proactive-input" placeholder="' . esc_attr($placeholder) . '" maxlength="1000" autocomplete="off">';
        echo '<button type="button" id="wpiko-chatbot-proactive-send" aria-label="Send message">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"></path><path d="M5 12l7-7 7 7"></path></svg>';
        echo '</button>';
        echo '</div>';
    }
    echo '</div>';
}

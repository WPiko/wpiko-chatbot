<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_get_user_avatar_svg()
{
    return '<svg class="user-avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
    </svg>';
}

// Create the chatbot container
function wpiko_chatbot_display()
{
    $chatbot_name = get_option('wpiko_chatbot_name', 'My Chatbot');
    $chatbot_image = get_option('wpiko_chatbot_image', WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png');
    $welcome_message = get_option('wpiko_chatbot_welcome_message', 'Welcome! Type your message to start chatting.');
    $chatbot_width = get_option('wpiko_chatbot_width', 1280);
    $chatbot_height = get_option('wpiko_chatbot_height', 500);
    $input_placeholder = get_option('wpiko_chatbot_input_placeholder', 'Type your message...');
    // Colors and their defaults are defined in admin/includes/style-presets.php.
    $primary_color = wpiko_chatbot_get_style_color('primary_color');
    $primary_text_color = wpiko_chatbot_get_style_color('primary_text_color');
    $chatbot_background_color = wpiko_chatbot_get_style_color('chatbot_background_color');
    $chatbot_header_color = wpiko_chatbot_get_style_color('chatbot_header_color');
    $chatbot_border_color = wpiko_chatbot_get_style_color('chatbot_border_color');
    $chatbot_name_color = wpiko_chatbot_get_style_color('chatbot_name_color');
    $subtitle_text = get_option('wpiko_chatbot_subtitle_text', 'AI assistant');

    $user_background_color = wpiko_chatbot_get_style_color('user_background_color');
    $user_text_color = wpiko_chatbot_get_style_color('user_text_color');
    $show_user_border = get_option('wpiko_chatbot_show_user_border', '1');
    $user_border_style = $show_user_border === '1' ? 'solid' : 'none';

    $bot_background_color = wpiko_chatbot_get_style_color('bot_background_color');
    $bot_text_color = wpiko_chatbot_get_style_color('bot_text_color');
    $icon_color = wpiko_chatbot_get_style_color('icon_color');
    $input_background_color = wpiko_chatbot_get_style_color('input_background_color');
    $floating_text_bg_color = wpiko_chatbot_get_style_color('floating_text_bg_color');
    $questions = get_option('wpiko_chatbot_questions', array());
    $has_questions = !empty($questions);
    $chatbot_floating_width = get_option('wpiko_chatbot_floating_width', 380);
    $chatbot_floating_height = get_option('wpiko_chatbot_floating_height', 620);

    // Email capture is now handled by the pro plugin
    $enable_email_capture = false; // Default to false in free version
    // Allow pro plugin to override email capture setting
    $enable_email_capture = apply_filters('wpiko_chatbot_enable_email_capture', $enable_email_capture);
    $user_email = '';

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
    }

    // Generate dynamic CSS using WordPress compliant method
    $css_content = ':root {
        --primary-color: ' . esc_attr($primary_color) . ';
        --primary-text-color: ' . esc_attr($primary_text_color) . ';
        --chatbot-background-color: ' . esc_attr($chatbot_background_color) . ';
        --chatbot-header-color: ' . esc_attr($chatbot_header_color) . ';
        --chatbot-border-color: ' . esc_attr($chatbot_border_color) . ';
        --chatbot-name-color: ' . esc_attr($chatbot_name_color) . ';
        
        --user-background-color: ' . esc_attr($user_background_color) . ';
        --user-text-color: ' . esc_attr($user_text_color) . ';
        --user-border-style: ' . esc_attr($user_border_style) . ';
        
        --bot-background-color: ' . esc_attr($bot_background_color) . ';
        --bot-text-color: ' . esc_attr($bot_text_color) . ';
        --icon-color: ' . esc_attr($icon_color) . ';
        --input-background-color: ' . esc_attr($input_background_color) . ';
        --floating-text-bg-color: ' . esc_attr($floating_text_bg_color) . ';
        
        --chatbot-width: ' . esc_attr($chatbot_width) . 'px;
        --chatbot-height: ' . esc_attr($chatbot_height) . 'px;
        
        --chatbot-floating-width: ' . esc_attr($chatbot_floating_width) . 'px;
        --chatbot-floating-height: ' . esc_attr($chatbot_floating_height) . 'px;
    }';

    // Add conditional CSS for questions
    if (!$has_questions) {
        $css_content .= '
        #pre-made-questions {
            display: none !important;
        }';
    }

    // Register and enqueue inline CSS using WordPress function
    wp_register_style('wpiko-chatbot-interface-inline', false, array(), WPIKO_CHATBOT_VERSION);
    wp_enqueue_style('wpiko-chatbot-interface-inline');
    wp_add_inline_style('wpiko-chatbot-interface-inline', $css_content);

    ob_start();
    ?>
    <div id="wpiko-chatbot-container">

        <?php if ($enable_email_capture && !is_user_logged_in()):
            // Get customizable email capture content
            $email_capture_title = get_option('wpiko_chatbot_email_capture_title', 'Enter your details to get started');
            $email_capture_description = get_option('wpiko_chatbot_email_capture_description', '');
            $email_capture_button_text = get_option('wpiko_chatbot_email_capture_button_text', 'Continue');
            $email_capture_name_placeholder = get_option('wpiko_chatbot_email_capture_name_placeholder', 'Enter your name');
            $email_capture_email_placeholder = get_option('wpiko_chatbot_email_capture_email_placeholder', 'Enter your email');
            ?>
            <div id="email-capture-overlay-container" style="display:none">
                <div id="email-capture-overlay">
                    <h3><?php echo esc_html($email_capture_title); ?></h3>
                    <?php if (!empty($email_capture_description)): ?>
                        <p><?php echo esc_html($email_capture_description); ?></p>
                    <?php endif; ?>
                    <input type="text" id="user-name" placeholder="<?php echo esc_attr($email_capture_name_placeholder); ?>">
                    <input type="email" id="user-email" placeholder="<?php echo esc_attr($email_capture_email_placeholder); ?>">
                    <button id="submit-email"><?php echo esc_html($email_capture_button_text); ?></button>
                </div>
            </div>
        <?php endif; ?>

        <div id="chatbot-header">
            <div id="chatbot-image-wrapper">
                <img src="<?php echo esc_url($chatbot_image); ?>" alt="Chatbot Image" id="chatbot-image">
                <span id="chatbot-status-dot"></span>
            </div>
            <div id="chatbot-info">
                <div id="chatbot-name"><?php echo esc_html($chatbot_name); ?></div>
                <div id="chatbot-status"><?php echo esc_html($subtitle_text); ?></div>
            </div>
            <button id="wpiko-chatbot-mobile-close" aria-label="Close chatbot"></button>
            <button id="wpiko-chatbot-expand" type="button" aria-label="Expand chatbot" aria-expanded="false" title="Expand chatbot">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3"></path>
                    <path d="M16 3h3a2 2 0 0 1 2 2v3"></path>
                    <path d="M8 21H5a2 2 0 0 1-2-2v-3"></path>
                    <path d="M16 21h3a2 2 0 0 0 2-2v-3"></path>
                </svg>
            </button>
            <div id="chatbot-menu">
                <button id="chatbot-menu-button" title="Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="12" cy="5" r="1"></circle>
                        <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                </button>
                <div id="chatbot-menu-dropdown" style="display: none;">
                    <ul>
                        <li id="clear-chat">
                            <span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18" />
                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                </svg></span>
                            <span
                                class="menu-text"><?php echo esc_html(get_option('wpiko_chatbot_menu_clear_chat', 'Clear Chat')); ?></span>
                        </li>
                        <?php
                        // Allow pro plugin to add additional menu items
                        do_action('wpiko_chatbot_menu_items');
                        ?>

                        <?php if (get_option('wpiko_chatbot_sound_enabled', '1') === '1'): ?>
                            <li id="toggle-sound">
                                <span class="sound-on">
                                    <span class="menu-item-content">
                                        <span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                                                <line x1="23" y1="9" x2="17" y2="15" />
                                                <line x1="17" y1="9" x2="23" y2="15" />
                                            </svg></span>
                                        <span
                                            class="menu-text"><?php echo esc_html(get_option('wpiko_chatbot_menu_sound_off', 'Turn Sound Off')); ?></span>
                                    </span>
                                </span>
                                <span class="sound-off" style="display: none;">
                                    <span class="menu-item-content">
                                        <span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                                                <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
                                                <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
                                            </svg></span>
                                        <span
                                            class="menu-text"><?php echo esc_html(get_option('wpiko_chatbot_menu_sound_on', 'Turn Sound On')); ?></span>
                                    </span>
                                </span>
                            </li>
                        <?php endif; ?>

                        <?php if (get_option('wpiko_chatbot_enable_transcript_download', '1') === '1'): ?>
                            <li id="download-transcript">
                                <span class="menu-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="7 10 12 15 17 10" />
                                        <line x1="12" y1="15" x2="12" y2="3" />
                                    </svg></span>
                                <span
                                    class="menu-text"><?php echo esc_html(get_option('wpiko_chatbot_menu_download_transcript', 'Download Transcript')); ?></span>
                            </li>
                        <?php endif; ?>

                    </ul>
                </div>

            </div>
        </div>
        <div id="chatbot-messages">
            <?php if (!empty($welcome_message)): ?>
                <div class="message-container welcome-message-container">
                    <div class="message-wrapper bot-message-wrapper">
                        <img src="<?php echo esc_url($chatbot_image); ?>" alt="Bot" class="message-avatar bot-avatar">
                        <div class="bot-message">
                            <span><?php echo esc_html($welcome_message); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div id="pre-made-questions" <?php echo $has_questions ? '' : 'style="display:none;"'; ?>>
            <?php foreach ($questions as $index => $question): ?>
                    <button class="pre-made-question" style="--question-index: <?php echo esc_attr((string) absint($index)); ?>">
                    <span class="pmq-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </span>
                    <span class="pmq-text"><?php echo esc_html($question); ?></span>
                    <span class="pmq-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="input-container">
            <div id="chatbot-input-container">
                <textarea id="chatbot-input" placeholder="<?php echo esc_attr($input_placeholder); ?>"></textarea>
                <button id="chatbot-send">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 32 32"
                        class="icon-2xl">
                        <path fill="currentColor" fill-rule="evenodd"
                            d="M15.192 8.906a1.143 1.143 0 0 1 1.616 0l5.143 5.143a1.143 1.143 0 0 1-1.616 1.616l-3.192-3.192v9.813a1.143 1.143 0 0 1-2.286 0v-9.813l-3.192 3.192a1.143 1.143 0 1 1-1.616-1.616z"
                            clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

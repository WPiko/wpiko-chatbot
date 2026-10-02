<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_enqueue_sound() {
    $sound_enabled = get_option('wpiko_chatbot_sound_enabled', true);

    if ($sound_enabled) {
        wp_enqueue_script('howler', WPIKO_CHATBOT_PLUGIN_URL . 'js/howler.min.js', array(), '2.2.3', true);
        wp_enqueue_script('wpiko-chatbot-sound', WPIKO_CHATBOT_PLUGIN_URL . 'js/chatbot-sound.js', array('howler'), '1.0', true);
        
        $sound_url = WPIKO_CHATBOT_PLUGIN_URL . 'sounds/message-notification.mp3';
        $error_sound_url = WPIKO_CHATBOT_PLUGIN_URL . 'sounds/error-notification.mp3';
        $clear_chat_sound_url = WPIKO_CHATBOT_PLUGIN_URL . 'sounds/clear-chat-notification.mp3';
        wp_localize_script('wpiko-chatbot-sound', 'wpikoChatbotSound', array(
            'messageReceivedSound' => $sound_url,
            'errorSound' => $error_sound_url,
            'clearChatSound' => $clear_chat_sound_url,
        ));
    }
}
add_action('wp_enqueue_scripts', 'wpiko_chatbot_enqueue_sound');

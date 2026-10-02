<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Nonce Refresh Handler for WPiko Chatbot
 * 
 * This file provides an AJAX endpoint to refresh WordPress nonces
 * when cached pages contain expired nonces, preventing "Access denied" errors.
 */

/**
 * Handle nonce refresh requests
 * 
 * This endpoint returns a fresh nonce without requiring an existing valid nonce.
 * It's designed to handle cases where cached pages contain expired nonces.
 * 
 * Rate limiting is implemented to prevent abuse.
 */
function wpiko_chatbot_refresh_nonce_handler() {
    // Rate limiting: Allow max 10 nonce refreshes per minute per IP
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_limit_key = 'wpiko_nonce_refresh_' . md5($ip);
    $refresh_count = get_transient($rate_limit_key);
    $max_refreshes = 10;
    $rate_limit_window = MINUTE_IN_SECONDS;

    if ($refresh_count !== false && $refresh_count >= $max_refreshes) {
        wp_send_json_error(array(
            'message' => 'Too many requests. Please wait a moment.',
            'type' => 'rate_limit_error'
        ));
        return;
    }

    // Increment rate limit counter
    $new_count = ($refresh_count !== false) ? $refresh_count + 1 : 1;
    set_transient($rate_limit_key, $new_count, $rate_limit_window);

    // Generate a fresh nonce
    $new_nonce = wp_create_nonce('wpiko_chatbot_nonce');

    // Also generate contact form nonce if pro plugin is active
    $contact_form_nonce = null;
    if (function_exists('wpiko_chatbot_is_license_active') && wpiko_chatbot_is_license_active()) {
        $contact_form_nonce = wp_create_nonce('wpiko_chatbot_contact_form');
    }

    // Log the nonce refresh for debugging (only in debug mode)
    if (function_exists('wpiko_chatbot_log') && defined('WP_DEBUG') && WP_DEBUG) {
        wpiko_chatbot_log('Nonce refreshed for IP: ' . $ip, 'info');
    }

    $response = array(
        'nonce' => $new_nonce
    );

    if ($contact_form_nonce !== null) {
        $response['contact_form_nonce'] = $contact_form_nonce;
    }

    wp_send_json_success($response);
}

// Register AJAX handlers for both logged-in and guest users
add_action('wp_ajax_wpiko_chatbot_refresh_nonce', 'wpiko_chatbot_refresh_nonce_handler');
add_action('wp_ajax_nopriv_wpiko_chatbot_refresh_nonce', 'wpiko_chatbot_refresh_nonce_handler');

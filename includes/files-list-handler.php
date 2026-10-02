<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// AJAX handler for listing files
function wpiko_chatbot_list_files_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Use Responses API for file listing
    $result = function_exists('wpiko_chatbot_list_responses_files') ? wpiko_chatbot_list_responses_files() : array('success' => false, 'message' => 'Responses file list function missing');

    if ($result['success']) {
        $response_data = array('files' => $result['files']);
        
        // Include performance information if available
        if (isset($result['performance'])) {
            $response_data['performance'] = $result['performance'];
        }
        
        wp_send_json_success($response_data);
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}

// AJAX handler for deleting files
function wpiko_chatbot_delete_file_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    if (!isset($_POST['file_id'])) {
        wp_send_json_error(array('message' => 'Missing file ID'));
    }

    $file_id = sanitize_text_field(wp_unslash($_POST['file_id']));
    
    // Use Responses API for file deletion
    $result = function_exists('wpiko_chatbot_delete_responses_file') ? wpiko_chatbot_delete_responses_file($file_id) : array('success' => false, 'message' => 'Responses delete function missing');

    if ($result['success']) {
        wp_send_json_success(array('message' => 'File deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Error deleting file: ' . $result['message']));
    }
}

// AJAX handler for refreshing file cache
function wpiko_chatbot_refresh_file_cache_ajax() {
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    // Clear the file cache to force fresh data fetch
    wpiko_chatbot_clear_file_cache();
    
    // Optionally, immediately refetch files to repopulate cache
    $api_type = get_option('wpiko_chatbot_api_type', 'responses');
    if ($api_type === 'responses') {
        $result = function_exists('wpiko_chatbot_list_responses_files') ? wpiko_chatbot_list_responses_files() : array('success' => false, 'message' => 'Responses file list function missing');
        if ($result['success']) {
            $response_data = array(
                'message' => 'File cache refreshed successfully',
                'files' => $result['files']
            );
            if (isset($result['performance'])) {
                $response_data['performance'] = $result['performance'];
            }
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error(array('message' => 'Cache cleared but failed to refetch files: ' . $result['message']));
        }
    }
}

// AJAX actions
add_action('wp_ajax_wpiko_chatbot_list_files', 'wpiko_chatbot_list_files_ajax');
add_action('wp_ajax_wpiko_chatbot_delete_file', 'wpiko_chatbot_delete_file_ajax');
add_action('wp_ajax_wpiko_chatbot_refresh_file_cache', 'wpiko_chatbot_refresh_file_cache_ajax');
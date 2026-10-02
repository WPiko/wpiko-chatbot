<?php
/**
 * Logging functionality for WPIKO Chatbot
 *
 * @package WPIKO_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Maximum number of log entries to keep in database
define('WPIKO_CHATBOT_MAX_LOG_ENTRIES', 200);

/**
 * Log a message for debugging purposes
 * Logs to WP_DEBUG log AND/OR to database based on settings
 *
 * @param string $message The message to log
 * @param string $level   The log level (info, warning, error)
 * @return void
 */
function wpiko_chatbot_log($message, $level = 'info') {
    // Check if debug logging is enabled in plugin settings
    $debug_logging_enabled = get_option('wpiko_chatbot_debug_logging', '0') === '1';
    
    // Log to WP_DEBUG if enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $prefix = '[WPIKO Chatbot]';
        
        // Add level indicator to prefix
        switch ($level) {
            case 'warning':
                $prefix .= ' [WARNING]';
                break;
            case 'error':
                $prefix .= ' [ERROR]';
                break;
            default:
                $prefix .= ' [INFO]';
                break;
        }
        
        // Log the message to error_log
        error_log($prefix . ' ' . $message);
    }
    
    // Also log to database if debug logging is enabled (works without WP_DEBUG)
    if ($debug_logging_enabled) {
        wpiko_chatbot_db_log($message, $level);
    }
}

/**
 * Log a message to the database
 * This works independently of WP_DEBUG
 *
 * @param string $message The message to log
 * @param string $level   The log level (info, warning, error)
 * @return void
 */
function wpiko_chatbot_db_log($message, $level = 'info') {
    $logs = get_option('wpiko_chatbot_debug_logs', array());
    
    // Add new log entry
    $logs[] = array(
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message
    );
    
    // Keep only the last X entries to prevent database bloat
    if (count($logs) > WPIKO_CHATBOT_MAX_LOG_ENTRIES) {
        $logs = array_slice($logs, -WPIKO_CHATBOT_MAX_LOG_ENTRIES);
    }
    
    update_option('wpiko_chatbot_debug_logs', $logs, false); // autoload = false for performance
}

/**
 * Get all debug log entries
 *
 * @param string $level Filter by level (optional: 'info', 'warning', 'error', or empty for all)
 * @param int $limit Maximum number of entries to return (0 for all)
 * @return array Array of log entries
 */
function wpiko_chatbot_get_debug_logs($level = '', $limit = 0) {
    $logs = get_option('wpiko_chatbot_debug_logs', array());
    
    // Filter by level if specified
    if (!empty($level)) {
        $logs = array_filter($logs, function($log) use ($level) {
            return $log['level'] === $level;
        });
    }
    
    // Sort by timestamp descending (newest first)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Apply limit if specified
    if ($limit > 0) {
        $logs = array_slice($logs, 0, $limit);
    }
    
    return $logs;
}

/**
 * Clear all debug logs
 *
 * @return bool True on success
 */
function wpiko_chatbot_clear_debug_logs() {
    return delete_option('wpiko_chatbot_debug_logs');
}

/**
 * Get log statistics
 *
 * @return array Statistics about logs
 */
function wpiko_chatbot_get_log_stats() {
    $logs = get_option('wpiko_chatbot_debug_logs', array());
    
    $stats = array(
        'total' => count($logs),
        'info' => 0,
        'warning' => 0,
        'error' => 0,
        'oldest' => null,
        'newest' => null
    );
    
    foreach ($logs as $log) {
        if (isset($log['level'])) {
            $stats[$log['level']]++;
        }
    }
    
    if (!empty($logs)) {
        // Sort to get oldest and newest
        usort($logs, function($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });
        $stats['oldest'] = $logs[0]['timestamp'];
        $stats['newest'] = $logs[count($logs) - 1]['timestamp'];
    }
    
    return $stats;
}

/**
 * Check if debug logging is enabled
 *
 * @return bool True if enabled
 */
function wpiko_chatbot_is_debug_logging_enabled() {
    return get_option('wpiko_chatbot_debug_logging', '0') === '1';
}

/**
 * AJAX handler to clear debug logs
 */
function wpiko_chatbot_ajax_clear_debug_logs() {
    check_ajax_referer('wpiko_chatbot_clear_logs', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    wpiko_chatbot_clear_debug_logs();
    wp_send_json_success(array('message' => 'Logs cleared successfully'));
}
add_action('wp_ajax_wpiko_chatbot_clear_debug_logs', 'wpiko_chatbot_ajax_clear_debug_logs');

/**
 * AJAX handler to toggle debug logging
 */
function wpiko_chatbot_ajax_toggle_debug_logging() {
    check_ajax_referer('wpiko_chatbot_toggle_logging', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    update_option('wpiko_chatbot_debug_logging', $enabled ? '1' : '0');
    
    wp_send_json_success(array(
        'enabled' => $enabled,
        'message' => $enabled ? 'Debug logging enabled' : 'Debug logging disabled'
    ));
}
add_action('wp_ajax_wpiko_chatbot_toggle_debug_logging', 'wpiko_chatbot_ajax_toggle_debug_logging');

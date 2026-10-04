<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Get the CSS used by the standalone HTML transcript.
 *
 * Combines the shared admin design tokens (the :root block in admin-style.css)
 * with the transcript stylesheet, so the exported file renders correctly on its
 * own without any of the plugin's admin stylesheets being loaded.
 *
 * @return string CSS content
 */
function wpiko_chatbot_get_transcript_css() {
    $css = '';

    // Design tokens: extract only the :root block from the admin stylesheet.
    $tokens_path = WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/admin-style.css';
    if (file_exists($tokens_path)) {
        $admin_css = file_get_contents($tokens_path);
        if ($admin_css !== false && preg_match('/:root\s*\{[^}]*\}/', $admin_css, $matches)) {
            $css .= $matches[0] . "\n\n";
        }
    }

    // Transcript styles.
    $css_path = WPIKO_CHATBOT_PLUGIN_DIR . 'admin/css/transcript-styles.css';
    if (file_exists($css_path)) {
        $transcript_css = file_get_contents($css_path);
        if ($transcript_css !== false) {
            $css .= $transcript_css;
        }
    }

    return $css;
}

/**
 * Generate a styled HTML transcript of a conversation
 *
 * @param array $conversations Array of conversation database objects
 * @param string $chatbot_name Name of the chatbot
 * @param string $session_id Session ID for reference
 * @return string HTML content
 */
function wpiko_chatbot_generate_html_transcript($conversations, $chatbot_name, $session_id) {
    // Get the site name
    $site_name = get_bloginfo('name');
    $date_generated = current_time('F j, Y');

    // Inline the CSS for transcript styling.
    // The transcript stylesheet relies on the shared --wpiko-* design tokens, so the
    // :root token block must be inlined too, otherwise the standalone HTML file has
    // no variable definitions and every colour/border/radius declaration is dropped.
    $css_content = wpiko_chatbot_get_transcript_css();

    // Start HTML content with inlined CSS
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Transcript - ' . esc_html($site_name) . '</title>';
    if (!empty($css_content)) {
        $html .= '<style type="text/css">' . $css_content . '</style>';
    }
    $html .= '</head>
<body>
    <div class="transcript-container">
        <div class="transcript-header">
            <h1>Chat Transcript</h1>
            <p>From ' . esc_html($site_name) . ' | Generated on ' . esc_html($date_generated) . '</p>
        </div>
        <div class="messages-container">';
    
    // Add each message
    foreach ($conversations as $conversation) {
        if ($conversation->role === 'user') {
            $role = 'user';
            $role_label = !empty($conversation->user_name) ? $conversation->user_name : 'User';
        } elseif ($conversation->role === 'admin') {
            $role = 'admin';
            $role_label = !empty($conversation->user_name) ? $conversation->user_name : 'Admin';
        } elseif ($conversation->role === 'error') {
            $role = 'error';
            $role_label = 'Error';
        } else {
            $role = 'bot';
            $role_label = $chatbot_name;
        }
        $timestamp = gmdate('F j, Y - g:i a', strtotime($conversation->timestamp));
        
        $message = wpiko_chatbot_render_message($conversation->message, $conversation->role);
        
        $html .= '
        <div class="message ' . esc_attr($role) . '">
            <div class="message-header">
                <span class="role-label">' . esc_html($role_label) . '</span>
                <span class="timestamp">' . esc_html($timestamp) . '</span>
            </div>
            <div class="message-content">
                <div class="message-text">' . $message . '</div>
            </div>
        </div>';
    }
    
    // Close HTML structure
    $html .= '
        </div>
        <div class="footer">
            <p>Powered by ' . esc_html($site_name) . ' • Session ID: ' . esc_html($session_id) . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

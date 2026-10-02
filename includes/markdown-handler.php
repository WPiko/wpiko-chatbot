<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Add markdown
function wpiko_chatbot_process_markdown($text) {
    // Convert headers
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
    
    // Convert bold text
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
    
    // Convert italic text
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
    
    return $text;
}

/**
 * Process emoji in text for proper display - Conversations History
 *
 * @param string $text Text that may contain emoji characters or HTML entities
 * @return string Text with properly decoded emojis
 */
function wpiko_chatbot_process_emoji($text) {
    // First decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Ensure emoji are properly displayed (convert shortcodes to actual emoji if any)
    if (function_exists('wp_emoji_decode')) {
        $text = wp_emoji_decode($text);
    }
    
    return $text;
}





// Make links clickable
function wpiko_chatbot_process_links($text, $use_wp_functions = true) {
    // Ensure $text is a string before processing
    if (!is_string($text)) {
        wpiko_chatbot_log('wpiko_chatbot_process_links received non-string input: ' . gettype($text) . ' - ' . json_encode($text), 'warning');
        $text = is_array($text) ? json_encode($text) : (string) $text;
    }
    
    // Store URLs in array and replace with placeholders
    $urls = array();
    
    // First, protect URLs by replacing them with placeholders
    $text = preg_replace_callback('/(https?:\/\/[^\s<>"]+)/i', function($matches) use (&$urls) {
        $url = $matches[1];
        // Handle trailing punctuation and markdown characters
        $clean_url = rtrim($url, '.,:;!?*');
        $suffix = substr($url, strlen($clean_url));
        
        $placeholder = '%%URL' . count($urls) . '%%';
        $urls[] = $clean_url;
        return $placeholder . $suffix;
    }, $text);

    // Allow plugins to extract and protect custom markers before markdown processing
    // This prevents markdown (bold/italic) from mangling content inside markers
    $text = apply_filters('wpiko_chatbot_before_markdown', $text);

    // Apply markdown processing
    $text = wpiko_chatbot_process_markdown($text);

    // Restore URLs
    $text = preg_replace_callback('/%%URL(\d+)%%/', function($matches) use ($urls) {
        return $urls[(int)$matches[1]];
    }, $text);

    $site_url = get_site_url();
    $site_domain = wp_parse_url($site_url, PHP_URL_HOST);

    // Apply filters to allow pro plugin to add contact form functionality
    $text = apply_filters('wpiko_chatbot_processed_markdown', $text);

    // Handle markdown-style links
    $text = preg_replace_callback('/\[([^\]]+)\]\s*\(?\s*((?:https?:\/\/|www\.)[^\s\)]+)\s*\)?/', function($matches) use ($use_wp_functions, $site_domain) {
        $linkText = $matches[1];
        $url = trim($matches[2], '()');
        if (strpos($url, 'www.') === 0) {
            $url = 'http://' . $url;
        }
        
        $link_domain = wp_parse_url($url, PHP_URL_HOST);
        $target = ($link_domain === $site_domain) ? '' : ' target="_blank"';
        
        if ($use_wp_functions) {
            $link = sprintf('<a href="%s"%s rel="noopener noreferrer">%s</a>', esc_url($url), $target, esc_html($linkText));
        } else {
            $link = "<a href=\"$url\"$target rel=\"noopener noreferrer\">$linkText</a>";
        }
        
        return $link;
    }, $text);

    // Handle plain URLs
    $urlPattern = '/((?:https?:\/\/|www\.)[^\s<>"]+)(?![^<>]*>|[^<>]*<\/a>)/i';
    $text = preg_replace_callback($urlPattern, function($matches) use ($use_wp_functions, $site_domain) {
        $url = rtrim($matches[1], '.,:;!?*');
        if (strpos($url, 'www.') === 0) {
            $url = 'http://' . $url;
        }
        
        $link_domain = wp_parse_url($url, PHP_URL_HOST);
        $target = ($link_domain === $site_domain) ? '' : ' target="_blank"';
        
        if ($use_wp_functions) {
            $link = sprintf('<a href="%s"%s rel="noopener noreferrer">%s</a>', esc_url($url), $target, esc_html($url));
        } else {
            $link = "<a href=\"$url\"$target rel=\"noopener noreferrer\">$url</a>";
        }
        
        return $link;
    }, $text);

    // Handle email addresses
    $emailPattern = '/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})\b/';
    $text = preg_replace_callback($emailPattern, function($matches) use ($use_wp_functions) {
        $email = $matches[1];
        if ($use_wp_functions) {
            return sprintf('<a href="mailto:%s">%s</a>', esc_attr($email), esc_html($email));
        } else {
            return "<a href=\"mailto:$email\">$email</a>";
        }
    }, $text);

    return $text;
}

// Alias for backward compatibility
function wpiko_chatbot_make_links_clickable($text) {
    return wpiko_chatbot_process_links($text, true);
}

// Alias for backward compatibility
function wpiko_chatbot_clean_and_format_links($text) {
    $text = wpiko_chatbot_process_links($text, false);
    return $text;
}

// Main link processing function
function wpiko_chatbot_process_message($text) {
    // First protect URLs, then apply markdown, then process links
    return wpiko_chatbot_process_links($text, true);
}

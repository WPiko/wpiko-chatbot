<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * HTML supported in chat replies. Never allow scripts, event handlers, arbitrary
 * data attributes, inline styles, forms, SVG, or embedded documents here.
 */
function wpiko_chatbot_get_allowed_message_html() {
    $allowed = array();
    foreach (array('b', 'bdi', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'ins', 'li', 'ol', 'p', 'pre', 's', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul') as $tag) {
        $allowed[$tag] = array('class' => true);
    }
    $allowed['a'] = array(
        'href' => true, 'title' => true, 'target' => true, 'rel' => true,
        'class' => true, 'data-wpiko-prefill' => true,
    );
    $allowed['img'] = array(
        'src' => true, 'alt' => true, 'class' => true,
        'width' => true, 'height' => true, 'loading' => true,
    );
    return $allowed;
}

/**
 * Final HTML boundary for model output and previously stored rich messages.
 * Do not decode entities or run HTML-producing filters after this step.
 */
function wpiko_chatbot_sanitize_message_html($message) {
    return wp_kses((string) $message, wpiko_chatbot_get_allowed_message_html(), array('http', 'https', 'mailto', 'tel'));
}

/**
 * Render historical messages, including rows saved before output was secured.
 * Visitor, admin and error messages are plain text; only assistants use HTML.
 */
function wpiko_chatbot_render_message($message, $role) {
    if ($role === 'assistant') {
        // Browsers render emoji entities directly. Decoding rich HTML here would
        // revive escaped tags and break quoted attributes such as contact JSON.
        return wpiko_chatbot_process_message(nl2br((string) $message));
    }
    $message = wpiko_chatbot_process_emoji((string) $message);
    return nl2br(esc_html($message));
}

/**
 * Transform text only: never rewrite HTML attributes or existing links/code.
 */
function wpiko_chatbot_map_message_text($text, $callback) {
    $parts = wp_html_split($text);
    $protected_depth = 0;
    foreach ($parts as $index => $part) {
        if ($index % 2) {
            if (preg_match('/^<\/(?:a|code|pre)\s*>/i', $part)) {
                $protected_depth = max(0, $protected_depth - 1);
            } elseif (preg_match('/^<(?:a|code|pre)(?:\s|>)/i', $part)) {
                $protected_depth++;
            }
        } elseif (!$protected_depth) {
            $parts[$index] = call_user_func($callback, $part);
        }
    }
    return implode('', $parts);
}

function wpiko_chatbot_replace_message_text($pattern, $callback, $text) {
    return wpiko_chatbot_map_message_text($text, function ($part) use ($pattern, $callback) {
        return preg_replace_callback($pattern, $callback, $part);
    });
}

// Add markdown
function wpiko_chatbot_process_markdown($text) {
    return wpiko_chatbot_map_message_text($text, function ($text) {
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
    });
}

/**
 * Process emoji in text for proper display - Conversations History
 *
 * @param string $text Text that may contain emoji characters or HTML entities
 * @return string Decoded plain text; must be escaped before HTML output.
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
    
    // Protect structured markers before URLs too, so prefill URLs are not
    // stranded as URL placeholders inside the marker's saved JSON.
    $text = apply_filters('wpiko_chatbot_before_markdown', $text);

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

    // Apply markdown processing
    $text = wpiko_chatbot_process_markdown($text);

    // Restore URLs
    $text = preg_replace_callback('/%%URL(\d+)%%/', function($matches) use ($urls) {
        return isset($urls[(int)$matches[1]]) ? $urls[(int)$matches[1]] : $matches[0];
    }, $text);

    $site_url = get_site_url();
    $site_domain = wp_parse_url($site_url, PHP_URL_HOST);

    // Apply filters to allow pro plugin to add contact form functionality
    $text = apply_filters('wpiko_chatbot_processed_markdown', $text);

    // Handle markdown-style links
    $text = wpiko_chatbot_replace_message_text('/\[([^\]]+)\]\s*\(?\s*((?:https?:\/\/|www\.)[^\s\)]+)\s*\)?/', function($matches) use ($use_wp_functions, $site_domain) {
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
    $text = wpiko_chatbot_replace_message_text($urlPattern, function($matches) use ($use_wp_functions, $site_domain) {
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
    $text = wpiko_chatbot_replace_message_text($emailPattern, function($matches) use ($use_wp_functions) {
        $email = $matches[1];
        if ($use_wp_functions) {
            return sprintf('<a href="mailto:%s">%s</a>', esc_attr($email), esc_html($email));
        } else {
            return "<a href=\"mailto:$email\">$email</a>";
        }
    }, $text);

    // Markdown, model HTML and Pro-generated markup all cross the same boundary.
    return wpiko_chatbot_sanitize_message_html($text);
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

<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'wpiko_chatbot_system_instructions';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        main_system_instructions longtext NOT NULL,
        specific_system_instructions longtext NOT NULL,
        knowledge_system_instructions longtext NOT NULL,
        products_system_instructions longtext NOT NULL,
        orders_system_instructions longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// This function will be called when the plugin is activated
function wpiko_chatbot_database_activation() {
    global $wpdb;
    wpiko_chatbot_create_database_tables();
    
    $table_name = $wpdb->prefix . 'wpiko_chatbot_system_instructions';
    
    // Check if there are any existing instructions
    $existing = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_system_instructions` LIMIT 1");
    
    $default_knowledge_instructions = "Always provide responses based on the knowledge files uploaded to the Vector Store.\n" .
        "Before answering any user query, always search the uploaded files first.\n" .
        "If no relevant information is found, clearly state: \"I couldn't find relevant information.\"\n" .
        "If a query is beyond your knowledge or requires human intervention, suggest contacting support.\n" .
        "Never rely on general knowledge unless explicitly asked.";
    
    if (!$existing) {
        // Insert default instructions for new installations
        $wpdb->insert(
            $table_name,
            array(
                'main_system_instructions' => '',
                'specific_system_instructions' => '',
                'knowledge_system_instructions' => $default_knowledge_instructions,
                'products_system_instructions' => '',
                'orders_system_instructions' => ''
            )
        );
    }
}

// Function to get system instructions
function wpiko_chatbot_get_system_instructions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_system_instructions';
    
    $result = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_system_instructions` ORDER BY id DESC LIMIT 1");
    
    if ($result) {
        return array(
            'main' => $result->main_system_instructions,
            'specific' => $result->specific_system_instructions,
            'knowledge' => $result->knowledge_system_instructions,
            'products' => $result->products_system_instructions,
            'orders' => $result->orders_system_instructions
        );
    }
    
    return array(
        'main' => '',
        'specific' => '',
        'knowledge' => '',
        'products' => '',
        'orders' => ''
    );
}

// Function to update system instructions
function wpiko_chatbot_update_system_instructions($main_instructions, $specific_instructions, $knowledge_instructions, $products_instructions, $orders_instructions) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_system_instructions';
    
    // Save the structured instruction fields if POST data is present
    // Check nonce before processing POST data
    if (isset($_POST['website_specialization']) || isset($_POST['assistant_tone']) || isset($_POST['assistant_style']) || isset($_POST['assistant_type'])) {
        if (!isset($_POST['wpiko_chatbot_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_nonce'])), 'wpiko_chatbot_nonce')) {
            // If nonce verification fails, log and skip processing POST data
            wpiko_chatbot_log('Nonce verification failed in wpiko_chatbot_update_system_instructions', 'warning');
        } else {
            // Process POST data only if nonce verification succeeds
            if (isset($_POST['assistant_type'])) {
                update_option('wpiko_chatbot_assistant_type', sanitize_text_field(wp_unslash($_POST['assistant_type'])));
            }
            
            if (isset($_POST['website_specialization'])) {
                update_option('wpiko_chatbot_website_specialization', sanitize_text_field(wp_unslash($_POST['website_specialization'])));
            }
            
            if (isset($_POST['assistant_tone'])) {
                update_option('wpiko_chatbot_assistant_tone', sanitize_text_field(wp_unslash($_POST['assistant_tone'])));
            }
            
            if (isset($_POST['assistant_style'])) {
                update_option('wpiko_chatbot_assistant_style', sanitize_text_field(wp_unslash($_POST['assistant_style'])));
            }
        }
    }
    
    // Prepare data without any extra escaping since wpdb will handle it
    $data = array(
        'main_system_instructions' => wp_unslash($main_instructions),
        'specific_system_instructions' => wp_unslash($specific_instructions),
        'knowledge_system_instructions' => wp_unslash($knowledge_instructions),
        'products_system_instructions' => wp_unslash($products_instructions),
        'orders_system_instructions' => wp_unslash($orders_instructions)
    );
    
    $existing = $wpdb->get_row("SELECT id FROM `{$wpdb->prefix}wpiko_chatbot_system_instructions` LIMIT 1");
    
    if ($existing) {
        $wpdb->update(
            $table_name,
            $data,
            array('id' => $existing->id)
        );
    } else {
        $wpdb->insert($table_name, $data);
    }
    
    return true;
}

// Function to combine main and specific instructions into a single instruction
function wpiko_chatbot_combine_instructions() {
    $instructions = wpiko_chatbot_get_system_instructions();
    
    // Get all instructions
    $main_instructions = trim($instructions['main']);
    $specific_instructions = trim($instructions['specific']);
    $knowledge_instructions = trim($instructions['knowledge']);
    $products_instructions = trim($instructions['products']);
    $orders_instructions = trim($instructions['orders']);
    
    // Initialize the combined instructions with main instructions
    $combined = $main_instructions;
    
    // Add each section of instructions if they exist
    if (!empty($specific_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "SPECIFIC INSTRUCTIONS:\n\n" . $specific_instructions;
    }
    
    if (!empty($knowledge_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "KNOWLEDGE INSTRUCTIONS:\n\n" . $knowledge_instructions;
    }
    
    if (!empty($products_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "PRODUCTS INSTRUCTIONS:\n\n" . $products_instructions;
    }
    
    if (!empty($orders_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "ORDERS INSTRUCTIONS:\n\n" . $orders_instructions;
    }

    // Allow pro plugins to append additional instruction sections (e.g. contact form AI instructions)
    $combined = apply_filters('wpiko_chatbot_combined_instructions', $combined);
    
    return $combined;
}

/**
 * Get the system instructions specifically for Responses API
 * This will use Responses-specific options if available, otherwise fall back to general options
 */
function wpiko_chatbot_get_responses_system_instructions() {
    $instructions = wpiko_chatbot_get_system_instructions();
    
    // Check if we have Responses-specific structured instructions
    $responses_assistant_type = get_option('wpiko_chatbot_responses_assistant_type', '');
    $responses_website_specialization = get_option('wpiko_chatbot_responses_website_specialization', '');
    $responses_assistant_tone = get_option('wpiko_chatbot_responses_assistant_tone', '');
    $responses_assistant_style = get_option('wpiko_chatbot_responses_assistant_style', '');
    
    // If we have Responses-specific structured options, generate the main instructions
    if (!empty($responses_assistant_type) || !empty($responses_website_specialization)) {
        $website_name = get_bloginfo('name');
        $assistant_type = !empty($responses_assistant_type) ? $responses_assistant_type : get_option('wpiko_chatbot_assistant_type', 'AI assistant');
        $website_specialization = !empty($responses_website_specialization) ? $responses_website_specialization : get_option('wpiko_chatbot_website_specialization', '');
        
        // Get tone and style names for display
        $tone_options = array(
            'friendly' => 'Friendly',
            'casual' => 'Casual',
            'professional' => 'Professional',
            'formal' => 'Formal',
            'humorous' => 'Humorous',
            'educational' => 'Educational',
            'enthusiastic' => 'Enthusiastic'
        );
        $style_options = array(
            'professional' => 'Professional',
            'conversational' => 'Conversational',
            'helpful' => 'Helpful',
            'concise' => 'Concise',
            'detailed' => 'Detailed'
        );
        
        $tone_key = !empty($responses_assistant_tone) ? $responses_assistant_tone : get_option('wpiko_chatbot_assistant_tone', 'friendly');
        $style_key = !empty($responses_assistant_style) ? $responses_assistant_style : get_option('wpiko_chatbot_assistant_style', 'professional');
        
        $assistant_tone = isset($tone_options[$tone_key]) ? $tone_options[$tone_key] : 'Friendly';
        $assistant_style = isset($style_options[$style_key]) ? $style_options[$style_key] : 'Professional';
        
        // NOTE: This instruction template is also defined in admin/js/responses-api.js
        // If you modify this template, make sure to update both files to keep them in sync
        $main_instructions = "You are an " . $assistant_type . " for the website " . $website_name . ". " .
                           "The website specializes in " . $website_specialization . ". " .
                           "Your goal is to provide helpful, accurate, and engaging responses to user queries " .
                           "while maintaining a " . $assistant_tone . " and " . $assistant_style . " tone. " .
                           "Always respond as if you are a helpful member of the website team.";
        
        return array(
            'main' => $main_instructions,
            'specific' => $instructions['specific'],
            'knowledge' => $instructions['knowledge'],
            'products' => $instructions['products'],
            'orders' => $instructions['orders']
        );
    }
    
    // Fall back to general instructions
    return $instructions;
}

/**
 * Combine instructions specifically for the Responses API
 */
function wpiko_chatbot_combine_responses_instructions() {
    $instructions = wpiko_chatbot_get_responses_system_instructions();
    
    // Get all instructions
    $main_instructions = trim($instructions['main']);
    $specific_instructions = trim($instructions['specific']);
    $knowledge_instructions = trim($instructions['knowledge']);
    $products_instructions = trim($instructions['products']);
    $orders_instructions = trim($instructions['orders']);
    
    // Initialize the combined instructions with main instructions
    $combined = $main_instructions;
    
    // Add each section of instructions if they exist
    if (!empty($specific_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "SPECIFIC INSTRUCTIONS:\n\n" . $specific_instructions;
    }
    
    if (!empty($knowledge_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "KNOWLEDGE INSTRUCTIONS:\n\n" . $knowledge_instructions;
    }
    
    if (!empty($products_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "PRODUCTS INSTRUCTIONS:\n\n" . $products_instructions;
    }
    
    if (!empty($orders_instructions)) {
        if (!empty($combined)) $combined .= "\n\n=====\n\n";
        $combined .= "ORDERS INSTRUCTIONS:\n\n" . $orders_instructions;
    }

    // Allow pro plugins to append additional instruction sections (e.g. contact form AI instructions)
    $combined = apply_filters('wpiko_chatbot_combined_instructions', $combined);
    
    return $combined;
}

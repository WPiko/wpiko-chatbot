<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include the transcript generator file
require_once WPIKO_CHATBOT_PLUGIN_DIR . 'includes/transcript-generator.php';

// Prevent WordPress from converting emoji to HTML entities
remove_filter('the_content', 'wp_encode_emoji');

// Conversation Handler

// Create table
function wpiko_chatbot_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED,
        session_id VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        user_email VARCHAR(255),
        user_name VARCHAR(255),
        country VARCHAR(100),
        city VARCHAR(100),
        region VARCHAR(100),
        device_type VARCHAR(20),
        openai_response_id VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Ensure the table is using UTF-8mb4
    $wpdb->query("ALTER TABLE `{$wpdb->prefix}wpiko_chatbot_conversations` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Ensure the openai_response_id column exists for existing installations
 * This handles upgrades from older versions of the plugin
 */
function wpiko_chatbot_ensure_response_id_column()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Check if column exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `$table_name` LIKE %s",
        'openai_response_id'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `openai_response_id` VARCHAR(255) DEFAULT NULL");
        wpiko_chatbot_log('Added openai_response_id column to conversations table', 'info');
    }
}

/**
 * Get the last OpenAI response ID for a session
 * Used for multi-turn conversations with the Responses API
 * 
 * @param string $session_id The session/conversation ID
 * @return string|null The last response ID or null if not found
 */
function wpiko_chatbot_get_last_response_id($session_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    $response_id = $wpdb->get_var($wpdb->prepare(
        "SELECT openai_response_id FROM `$table_name` 
         WHERE session_id = %s AND openai_response_id IS NOT NULL AND openai_response_id != '' 
         ORDER BY timestamp DESC LIMIT 1",
        $session_id
    ));

    return $response_id;
}

/**
 * Save the OpenAI response ID for the latest assistant message in a session
 * 
 * @param string $session_id The session/conversation ID
 * @param string $response_id The OpenAI response ID to save
 * @return bool True on success, false on failure
 */
function wpiko_chatbot_save_response_id($session_id, $response_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Update the most recent assistant message that doesn't have a response ID yet
    $result = $wpdb->query($wpdb->prepare(
        "UPDATE `$table_name` 
         SET openai_response_id = %s 
         WHERE session_id = %s 
         AND role = 'assistant' 
         AND (openai_response_id IS NULL OR openai_response_id = '')
         ORDER BY timestamp DESC 
         LIMIT 1",
        $response_id,
        $session_id
    ));

    return $result !== false;
}

// Save messages to database
function wpiko_chatbot_save_message($user_id, $thread_id, $role, $message, $user_email = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $location = wpiko_chatbot_get_user_location();
    $device_type = wpiko_chatbot_get_device_type();
    $user_name = '';

    // Get user name from localStorage if not logged in
    if (!$user_id && isset($_POST['user_name'])) {
        // Check nonce before processing POST data
        if (!isset($_POST['wpiko_chatbot_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpiko_chatbot_nonce'])), 'wpiko_chatbot_nonce')) {
            // If nonce verification fails, don't process the user_name
            wpiko_chatbot_log('Nonce verification failed in wpiko_chatbot_save_message', 'warning');
        } else {
            $user_name = sanitize_text_field(wp_unslash($_POST['user_name']));
        }
    }

    $wpdb->insert($table_name, array(
        'user_id' => $user_id,
        'session_id' => $thread_id,
        'role' => $role,
        'message' => $message, // Store message without additional encoding
        'timestamp' => current_time('mysql'),
        'user_email' => $user_email,
        'user_name' => $user_name,
        'country' => $location['country'],
        'city' => $location['city'],
        'region' => $location['region'],
        'device_type' => $device_type
    ));

    // Allow Pro plugin to react to new messages (e.g., push notifications)
    do_action('wpiko_chatbot_message_saved', $user_id, $thread_id, $role, $message, $user_email);
}

// Save Error messages to database
function wpiko_chatbot_save_error_message($user_id, $session_id, $user_friendly_error, $user_email, $user_name = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $location = wpiko_chatbot_get_user_location();
    $device_type = wpiko_chatbot_get_device_type();

    $wpdb->insert($table_name, array(
        'user_id' => $user_id,
        'session_id' => $session_id,
        'role' => 'error',
        'message' => $user_friendly_error,
        'timestamp' => current_time('mysql'),
        'user_email' => $user_email,
        'user_name' => $user_name,
        'country' => $location['country'],
        'city' => $location['city'],
        'region' => $location['region'],
        'device_type' => $device_type
    ));
}

// Get conversations
function wpiko_chatbot_get_conversations($per_page, $offset, $start_date = '', $end_date = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    $per_page = intval($per_page);
    $offset = intval($offset);

    // Build query based on which date filters are provided
    // Note: Using inline SQL to satisfy Plugin Check requirements
    if (!empty($start_date) && !empty($end_date)) {
        // Both dates provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c1.session_id, 
                MAX(c1.id) as id, 
                c1.user_id, 
                MAX(c1.timestamp) as timestamp,
                MAX(c1.country) as country,
                MAX(c1.city) as city,
                MAX(c1.region) as region,
                MAX(c1.device_type) as device_type,
                (
                    SELECT c2.message 
                    FROM {$table_name} c2 
                    WHERE c2.session_id = c1.session_id 
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT c2.role
                    FROM {$table_name} c2
                    WHERE c2.session_id = c1.session_id
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC
                    LIMIT 1
                ) as last_message_role,
                MAX(c1.user_email) as user_email,
                (
                    SELECT c3.user_name
                    FROM {$table_name} c3
                    WHERE c3.session_id = c1.session_id
                      AND c3.role = 'user'
                    ORDER BY c3.timestamp ASC
                    LIMIT 1
                ) as user_name
            FROM {$table_name} c1
            WHERE c1.timestamp >= %s AND c1.timestamp <= %s
            GROUP BY c1.session_id
            ORDER BY MAX(c1.timestamp) DESC
            LIMIT %d OFFSET %d",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59',
            $per_page,
            $offset
        ));
    } elseif (!empty($start_date)) {
        // Only start date provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c1.session_id, 
                MAX(c1.id) as id, 
                c1.user_id, 
                MAX(c1.timestamp) as timestamp,
                MAX(c1.country) as country,
                MAX(c1.city) as city,
                MAX(c1.region) as region,
                MAX(c1.device_type) as device_type,
                (
                    SELECT c2.message 
                    FROM {$table_name} c2 
                    WHERE c2.session_id = c1.session_id 
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT c2.role
                    FROM {$table_name} c2
                    WHERE c2.session_id = c1.session_id
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC
                    LIMIT 1
                ) as last_message_role,
                MAX(c1.user_email) as user_email,
                (
                    SELECT c3.user_name
                    FROM {$table_name} c3
                    WHERE c3.session_id = c1.session_id
                      AND c3.role = 'user'
                    ORDER BY c3.timestamp ASC
                    LIMIT 1
                ) as user_name
            FROM {$table_name} c1
            WHERE c1.timestamp >= %s
            GROUP BY c1.session_id
            ORDER BY MAX(c1.timestamp) DESC
            LIMIT %d OFFSET %d",
            $start_date . ' 00:00:00',
            $per_page,
            $offset
        ));
    } elseif (!empty($end_date)) {
        // Only end date provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c1.session_id, 
                MAX(c1.id) as id, 
                c1.user_id, 
                MAX(c1.timestamp) as timestamp,
                MAX(c1.country) as country,
                MAX(c1.city) as city,
                MAX(c1.region) as region,
                MAX(c1.device_type) as device_type,
                (
                    SELECT c2.message 
                    FROM {$table_name} c2 
                    WHERE c2.session_id = c1.session_id 
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT c2.role
                    FROM {$table_name} c2
                    WHERE c2.session_id = c1.session_id
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC
                    LIMIT 1
                ) as last_message_role,
                MAX(c1.user_email) as user_email,
                (
                    SELECT c3.user_name
                    FROM {$table_name} c3
                    WHERE c3.session_id = c1.session_id
                      AND c3.role = 'user'
                    ORDER BY c3.timestamp ASC
                    LIMIT 1
                ) as user_name
            FROM {$table_name} c1
            WHERE c1.timestamp <= %s
            GROUP BY c1.session_id
            ORDER BY MAX(c1.timestamp) DESC
            LIMIT %d OFFSET %d",
            $end_date . ' 23:59:59',
            $per_page,
            $offset
        ));
    } else {
        // No date filters
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c1.session_id, 
                MAX(c1.id) as id, 
                c1.user_id, 
                MAX(c1.timestamp) as timestamp,
                MAX(c1.country) as country,
                MAX(c1.city) as city,
                MAX(c1.region) as region,
                MAX(c1.device_type) as device_type,
                (
                    SELECT c2.message 
                    FROM {$table_name} c2 
                    WHERE c2.session_id = c1.session_id 
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC 
                    LIMIT 1
                ) as last_message,
                (
                    SELECT c2.role
                    FROM {$table_name} c2
                    WHERE c2.session_id = c1.session_id
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC
                    LIMIT 1
                ) as last_message_role,
                MAX(c1.user_email) as user_email,
                (
                    SELECT c3.user_name
                    FROM {$table_name} c3
                    WHERE c3.session_id = c1.session_id
                      AND c3.role = 'user'
                    ORDER BY c3.timestamp ASC
                    LIMIT 1
                ) as user_name
            FROM {$table_name} c1
            GROUP BY c1.session_id
            ORDER BY MAX(c1.timestamp) DESC
            LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
    }
}



function wpiko_chatbot_get_total_conversations($start_date = '', $end_date = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Build query based on which date filters are provided
    if (!empty($start_date) && !empty($end_date)) {
        // Both dates provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$table_name} WHERE timestamp >= %s AND timestamp <= %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        ));
    } elseif (!empty($start_date)) {
        // Only start date provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$table_name} WHERE timestamp >= %s",
            $start_date . ' 00:00:00'
        ));
    } elseif (!empty($end_date)) {
        // Only end date provided
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$table_name} WHERE timestamp <= %s",
            $end_date . ' 23:59:59'
        ));
    } else {
        // No date filters - no user input, safe to run without prepare
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
        return $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM {$table_name}");
    }
}


/**
 * Render a single conversation list item for the admin sidebar.
 * Used by the conversations section and the AJAX search endpoint.
 */
function wpiko_chatbot_render_conversation_item($conversation)
{
    // Prioritize account details for logged-in users
    if ($conversation->user_id != 0) {
        $user_data = get_userdata($conversation->user_id);
        $user_display = $user_data ? ($user_data->display_name ?: $user_data->user_login) : 'Unknown User';
    } else {
        // For guest users, use email capture name if available, otherwise 'Guest'
        $user_display = !empty($conversation->user_name) ? $conversation->user_name : 'Guest';
    }
    $message_class = ($conversation->last_message_role == 'error') ? 'error-message' : '';
    $formatted_timestamp = get_date_from_gmt(get_gmt_from_date($conversation->timestamp), 'Y-m-d H:i:s');

    ob_start();
    ?>
    <div class="conversation-item" data-session-id="<?php echo esc_attr($conversation->session_id); ?>">
        <div class="user-avatar">
            <?php echo get_avatar($conversation->user_id, 40, '', '', array('class' => 'avatar')); ?>
        </div>
        <div class="conversation-info">
            <div class="user-name"><?php echo esc_html($user_display); ?></div>
            <div class="last-message <?php echo esc_attr($message_class); ?>"><?php echo esc_html($conversation->last_message); ?></div>
            <div class="timestamp" data-timestamp="<?php echo esc_attr($conversation->timestamp); ?>">
                <?php echo esc_html($formatted_timestamp); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX handler for searching conversations in the admin sidebar
add_action('wp_ajax_wpiko_chatbot_search_conversations', 'wpiko_chatbot_search_conversations');

function wpiko_chatbot_search_conversations()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $search = isset($_POST['search']) ? trim(sanitize_text_field(wp_unslash($_POST['search']))) : '';
    if (mb_strlen($search) < 2) {
        wp_send_json_error('Search term too short');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    $like = '%' . $wpdb->esc_like($search) . '%';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe prefix
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT
            c1.session_id,
            MAX(c1.id) as id,
            c1.user_id,
            MAX(c1.timestamp) as timestamp,
            (
                SELECT c2.message
                FROM {$table_name} c2
                WHERE c2.session_id = c1.session_id
                  AND c2.role IN ('user', 'error')
                ORDER BY c2.timestamp DESC
                LIMIT 1
            ) as last_message,
            (
                SELECT c2.role
                FROM {$table_name} c2
                WHERE c2.session_id = c1.session_id
                  AND c2.role IN ('user', 'error')
                ORDER BY c2.timestamp DESC
                LIMIT 1
            ) as last_message_role,
            (
                SELECT c3.user_name
                FROM {$table_name} c3
                WHERE c3.session_id = c1.session_id
                  AND c3.role = 'user'
                ORDER BY c3.timestamp ASC
                LIMIT 1
            ) as user_name
        FROM {$table_name} c1
        WHERE c1.message LIKE %s OR c1.user_name LIKE %s OR c1.user_email LIKE %s
        GROUP BY c1.session_id
        ORDER BY MAX(c1.timestamp) DESC
        LIMIT 30",
        $like,
        $like,
        $like
    ));

    $html = '';
    foreach ((array) $results as $conversation) {
        $html .= wpiko_chatbot_render_conversation_item($conversation);
    }

    wp_send_json_success(array(
        'html' => $html,
        'count' => count((array) $results),
    ));
}

// AJAX handlers for conversation management
add_action('wp_ajax_wpiko_chatbot_fetch_conversation', 'wpiko_chatbot_fetch_conversation');

function wpiko_chatbot_fetch_conversation()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Validate that session_id exists before using it
    if (!isset($_POST['session_id'])) {
        wp_send_json_error('Missing session ID');
        return;
    }

    $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Get conversation messages
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE session_id = %s ORDER BY id ASC",
        $session_id
    ));

    if ($conversations) {
        // Get conversation details from the first message
        $first_message = reset($conversations);

        // Format messages HTML
        $messages_html = '<div class="messages-container">';
        foreach ($conversations as $conversation) {
            $message = wpiko_chatbot_render_message($conversation->message, $conversation->role);
            $role_class = $conversation->role === 'user' ? 'user' : ($conversation->role === 'error' ? 'error' : ($conversation->role === 'admin' ? 'admin' : 'bot'));

            // Determine display label for message header
            if ($conversation->role === 'admin') {
                $role_label = !empty($conversation->user_name) ? $conversation->user_name : 'Admin';
            } else {
                $role_label = ucfirst($role_class);
            }

            // Use the timestamp directly from the database without WordPress timezone conversion
            $timestamp = $conversation->timestamp;

            $avatar = $conversation->role === 'user' ?
                get_avatar($conversation->user_id, 40) :
                '<img src="' . esc_url(get_option('wpiko_chatbot_image', WPIKO_CHATBOT_PLUGIN_URL . 'assets/images/chatbot-icon.png')) . '" class="avatar" width="40" height="40" alt="Chatbot">';

            $avatar = apply_filters('wpiko_chatbot_admin_message_avatar_html', $avatar, $conversation, $session_id);

            $messages_html .= sprintf(
                '<div class="message %s">
                    <div class="message-avatar">%s</div>
                    <div class="message-content-wrapper">
                        <div class="message-header">
                            %s
                        </div>
                        <div class="message-content">
                            <div class="message-text">%s</div>
                        </div>
                    </div>
                </div>',
                esc_attr($role_class),
                $avatar,
                $conversation->role === 'user' ?
                sprintf(
                    '<span class="timestamp">%s</span><span class="role-label">%s</span>',
                    esc_html($timestamp),
                    esc_html($role_label)
                ) :
                sprintf(
                    '<span class="role-label">%s</span><span class="timestamp">%s</span>',
                    esc_html($role_label),
                    esc_html($timestamp)
                ),
                $message // Already escaped or allowlisted by wpiko_chatbot_render_message().
            );
        }
        $messages_html .= '</div>';

        // Get user display name - prioritize logged-in user account details over email capture
        if ($first_message->user_id != 0) {
            // User is logged in - use account details
            $user_data = get_userdata($first_message->user_id);
            $user_display = $user_data ? $user_data->display_name : $user_data->user_login;
        } else {
            // Guest user - use email capture name if available, otherwise 'Guest'
            $user_display = $first_message->user_name ?: 'Guest';
        }

        // Get user email - prioritize logged-in user's email over email capture
        $user_email_display = $first_message->user_email;
        if ($first_message->user_id != 0) {
            $user_data = get_userdata($first_message->user_id);
            if ($user_data && !empty($user_data->user_email)) {
                $user_email_display = $user_data->user_email;
            }
        }

        // Send JSON response with correct charset
        header('Content-Type: application/json; charset=UTF-8');

        $response_data = array(
            'messages' => $messages_html,
            'device_type' => $first_message->device_type,
            'country' => $first_message->country,
            'city' => $first_message->city,
            'region' => $first_message->region,
            'user_email' => $user_email_display,
            'user_name' => $user_display,
            'raw_user_name' => $first_message->user_name,
            'user_id' => $first_message->user_id
        );

        // Allow pro plugin to enrich response (e.g. user presence)
        $response_data = apply_filters('wpiko_chatbot_fetch_conversation_data', $response_data, $session_id);

        wp_send_json_success($response_data);
    } else {
        wp_send_json_error('Failed to fetch conversation');
    }
}

add_action('wp_ajax_wpiko_chatbot_delete_conversation', 'wpiko_chatbot_delete_conversation');

function wpiko_chatbot_delete_conversation()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Validate that session_id exists before using it
    if (!isset($_POST['session_id'])) {
        wp_send_json_error('Missing session ID');
        return;
    }

    $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    $deleted = $wpdb->delete($table_name, array('session_id' => $session_id));

    if ($deleted) {
        // Allow pro plugin to clean up related meta data
        do_action('wpiko_chatbot_conversation_deleted', $session_id);
        wp_send_json_success('Conversation deleted');
    } else {
        wp_send_json_error('Failed to delete conversation');
    }
}

function wpiko_chatbot_get_user_location()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $response = wp_remote_get("http://ip-api.com/json/{$ip}");

    if (!is_wp_error($response)) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'],
                'city' => $data['city'],
                'region' => $data['regionName']
            );
        }
    }
    return array(
        'country' => null,
        'city' => null,
        'region' => null
    );
}

function wpiko_chatbot_get_device_type()
{
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($user_agent))) {
        return 'tablet';
    }
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($user_agent))) {
        return 'mobile';
    }
    return 'desktop';
}

function wpiko_chatbot_generate_transcript($conversations)
{
    $transcript = '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    // Add emoji support meta tag
    $transcript .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';

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
            $role_label = 'Bot';
        }
        $timestamp = gmdate('Y-m-d H:i:s', strtotime($conversation->timestamp));

        $message = wpiko_chatbot_render_message($conversation->message, $conversation->role);

        $transcript .= "<div class='message {$role}'><div class='message-content'><span class='role-label'>" .
            esc_html($role_label) . "</span> <span class='timestamp'>[{$timestamp}]</span><div class='message-text'>{$message}</div></div></div>";
    }
    $transcript .= '</div></body></html>';
    return $transcript;
}

function wpiko_chatbot_save_transcript($transcript, $session_id)
{
    $upload_dir = wp_upload_dir();
    $transcript_dir = $upload_dir['basedir'] . '/chat-transcripts';
    if (!file_exists($transcript_dir)) {
        wp_mkdir_p($transcript_dir);
    }
    $file_name = 'chat-transcript-' . $session_id . '.html';
    $file_path = $transcript_dir . '/' . $file_name;
    file_put_contents($file_path, $transcript);
    return $upload_dir['baseurl'] . '/chat-transcripts/' . $file_name;
}

// Function to download conversation
function wpiko_chatbot_download_conversation()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Validate that session_id exists before using it
    if (!isset($_POST['session_id'])) {
        wp_send_json_error('Missing session ID');
        return;
    }

    $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    // Get conversation messages
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE session_id = %s ORDER BY id ASC",
        $session_id
    ));

    if ($conversations) {
        // Get the chatbot name from options or use default
        $chatbot_name = get_option('wpiko_chatbot_name', 'Chatbot');

        // Generate HTML transcript (function located in transcript-generator.php)
        $html_content = wpiko_chatbot_generate_html_transcript($conversations, $chatbot_name, $session_id);

        wp_send_json_success($html_content);
    } else {
        wp_send_json_error('No conversation found');
    }
}

add_action('wp_ajax_wpiko_chatbot_download_conversation', 'wpiko_chatbot_download_conversation');

// Function to download emails
function wpiko_chatbot_download_emails()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';

    $emails = $wpdb->get_col("SELECT DISTINCT user_email FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE user_email != ''");

    if ($emails) {
        $csv_content = "Email Address\n";
        foreach ($emails as $email) {
            $csv_content .= "$email\n";
        }

        wp_send_json_success($csv_content);
    } else {
        wp_send_json_error('No emails found');
    }
}
add_action('wp_ajax_wpiko_chatbot_download_emails', 'wpiko_chatbot_download_emails');

// Function to get properly sized avatar
function wpiko_chatbot_get_avatar()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // Validate that user_id exists before using it
    if (!isset($_POST['user_id'])) {
        wp_send_json_error('Missing user ID');
        return;
    }

    // Validate that size exists before using it
    if (!isset($_POST['size'])) {
        wp_send_json_error('Missing avatar size parameter');
        return;
    }

    $user_id = intval($_POST['user_id']);
    $size = intval($_POST['size']);

    $avatar = get_avatar($user_id, $size, '', '', array('class' => 'avatar-image'));
    wp_send_json_success($avatar);
}
add_action('wp_ajax_wpiko_chatbot_get_avatar', 'wpiko_chatbot_get_avatar');

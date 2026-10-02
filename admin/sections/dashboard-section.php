<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_dashboard_section() {
    global $wpdb;
    
    // Dashboard stats calculation
    $table_name = $wpdb->prefix . 'wpiko_chatbot_conversations';
    
    // Check if table exists before querying
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
    $recent_error_count = 0;
    $latest_error = null;
    
    if ($table_exists) {
        // Get basic stats
        $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM `{$wpdb->prefix}wpiko_chatbot_conversations`");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE role IN ('user', 'assistant')");
        
        // Count unique users: registered users by ID, guest users by session_id
        $total_users = $wpdb->get_var("
            SELECT COUNT(DISTINCT 
                CASE 
                    WHEN user_id != 0 THEN CONCAT('user_', user_id)
                    ELSE CONCAT('guest_', session_id)
                END
            ) FROM `{$wpdb->prefix}wpiko_chatbot_conversations`
        ");
        
        // Get today's stats
        $today = current_time('Y-m-d');
        $conversations_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE DATE(timestamp) = %s",
            $today
        ));

        // Keep dashboard alerts operational and lightweight by using local data only.
        $attention_since = gmdate('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS);
        $recent_error_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE role = 'error' AND timestamp >= %s",
            $attention_since
        ));

        if ($recent_error_count > 0) {
            $latest_error = $wpdb->get_row($wpdb->prepare(
                "SELECT message, timestamp FROM `{$wpdb->prefix}wpiko_chatbot_conversations` WHERE role = 'error' AND timestamp >= %s ORDER BY timestamp DESC LIMIT %d",
                $attention_since,
                1
            ));
        }
        
        // Get recent conversations for activity feed
        $recent_conversations = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                c1.session_id, 
                c1.user_id, 
                MAX(c1.user_name) as user_name, 
                MAX(c1.user_email) as user_email, 
                MAX(c1.timestamp) as timestamp,
                (
                    SELECT c2.message 
                    FROM `{$wpdb->prefix}wpiko_chatbot_conversations` c2 
                    WHERE c2.session_id = c1.session_id 
                      AND c2.role IN ('user', 'error')
                    ORDER BY c2.timestamp DESC 
                    LIMIT 1
                ) as last_message
             FROM `{$wpdb->prefix}wpiko_chatbot_conversations` c1
             WHERE c1.role = 'user'
             GROUP BY c1.session_id
             ORDER BY MAX(c1.timestamp) DESC 
             LIMIT %d",
            5
        ));
    } else {
        // Default values when table doesn't exist
        $total_conversations = 0;
        $total_messages = 0;
        $total_users = 0;
        $conversations_today = 0;
        $recent_conversations = array();
    }
    
    // Check configuration status
    $encrypted_api_key = get_option('wpiko_chatbot_api_key', '');
    $api_key = wpiko_chatbot_decrypt_api_key($encrypted_api_key);
    $is_configured = !empty($api_key);
    $chatbot_name = get_option('wpiko_chatbot_name', 'My Chatbot');
    $floating_enabled = get_option('wpiko_chatbot_enable_floating', false);
    $proactive_enabled = get_option('wpiko_chatbot_proactive_enabled', false);
    $proactive_heading = trim((string) get_option('wpiko_chatbot_proactive_heading', 'Hi!'));
    $proactive_message = trim((string) get_option('wpiko_chatbot_proactive_message', "Looking for something specific? We'll help you find it!"));
    $proactive_has_content = $proactive_heading !== '' || $proactive_message !== '';

    if (!$proactive_enabled) {
        $proactive_class = 'config-optional';
        $proactive_action = 'Enable';
        $proactive_tab = 'proactive_greeting';
    } elseif (!$floating_enabled) {
        $proactive_class = 'config-incomplete';
        $proactive_action = 'Enable Floating';
        $proactive_tab = 'floating_chatbot';
    } elseif (!$proactive_has_content) {
        $proactive_class = 'config-incomplete';
        $proactive_action = 'Add Content';
        $proactive_tab = 'proactive_greeting';
    } else {
        $proactive_class = 'config-complete';
        $proactive_action = 'Configure';
        $proactive_tab = 'proactive_greeting';
    }

    $selected_model = get_option('wpiko_chatbot_responses_model', 'gpt-5.6-luna');
    $sound_enabled = get_option('wpiko_chatbot_sound_enabled', '1');
    $transcript_download = get_option('wpiko_chatbot_enable_transcript_download', '1');

    // Actionable dashboard alerts. Add-ons can contribute alerts through the filter.
    $attention_items = array();

    if (!$table_exists) {
        $attention_items[] = array(
            'severity' => 'critical',
            'icon' => 'dashicons-database-remove',
            'title' => __('Conversation storage is unavailable', 'wpiko-chatbot'),
            'description' => __('The conversations database table could not be found. Review the debug log for details.', 'wpiko-chatbot'),
            'url' => wp_nonce_url('?page=ai-chatbot&tab=debug_log', 'wpiko_chatbot_tab_nonce'),
            'action_label' => __('View debug log', 'wpiko-chatbot'),
        );
    }

    if (!$is_configured) {
        $attention_items[] = array(
            'severity' => 'critical',
            'icon' => 'dashicons-admin-network',
            'title' => __('Connect OpenAI to activate the chatbot', 'wpiko-chatbot'),
            'description' => __('Add your API key before visitors can receive chatbot responses.', 'wpiko-chatbot'),
            'url' => wp_nonce_url('?page=ai-chatbot&tab=api_key', 'wpiko_chatbot_tab_nonce'),
            'action_label' => __('Add API key', 'wpiko-chatbot'),
        );
    }

    if ($recent_error_count > 0) {
        $error_description = __('Review the affected conversations and confirm the chatbot is responding normally.', 'wpiko-chatbot');

        if ($latest_error && !empty($latest_error->message)) {
            $error_description = sprintf(
                /* translators: 1: latest error message, 2: relative time */
                __('Latest: %1$s — %2$s ago.', 'wpiko-chatbot'),
                wp_trim_words($latest_error->message, 12, '…'),
                human_time_diff(strtotime($latest_error->timestamp), current_time('timestamp'))
            );
        }

        $attention_items[] = array(
            'severity' => 'warning',
            'icon' => 'dashicons-warning',
            'title' => sprintf(
                /* translators: %s: number of chatbot errors */
                _n('%s chatbot error in the last 24 hours', '%s chatbot errors in the last 24 hours', $recent_error_count, 'wpiko-chatbot'),
                number_format_i18n($recent_error_count)
            ),
            'description' => $error_description,
            'url' => wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_chatbot_tab_nonce'),
            'action_label' => __('Review conversations', 'wpiko-chatbot'),
        );
    }

    /**
     * Filter actionable items displayed on the dashboard.
     *
     * Each item supports severity, icon, title, description, url, and action_label.
     *
     * @param array $attention_items Dashboard attention items.
     * @param array $dashboard_state Current core dashboard state.
     */
    $attention_items = apply_filters(
        'wpiko_chatbot_dashboard_attention_items',
        $attention_items,
        array(
            'is_configured' => $is_configured,
            'table_exists' => $table_exists,
            'recent_error_count' => $recent_error_count,
        )
    );
    
    // Format numbers
    $total_conversations = number_format((int)$total_conversations);
    $total_messages = number_format((int)$total_messages);
    $total_users = number_format((int)$total_users);
    $conversations_today = number_format((int)$conversations_today);
    ?>
    
    <div class="wpiko-chatbot-dashboard">
        <!-- Welcome Section -->
        <div class="dashboard-welcome">
            <div class="welcome-main">
                <div class="welcome-icon" aria-hidden="true">
                    <span class="dashicons dashicons-dashboard"></span>
                </div>
                <div class="welcome-content">
                    <span class="welcome-eyebrow"><?php esc_html_e('WPiko Chatbot', 'wpiko-chatbot'); ?></span>
                    <h1><?php esc_html_e('Dashboard', 'wpiko-chatbot'); ?></h1>
                    <p class="welcome-description"><?php esc_html_e("A quick overview of your chatbot's performance, activity, and setup.", 'wpiko-chatbot'); ?></p>
                </div>
            </div>
            <div class="welcome-status">
                <?php if ($is_configured): ?>
                    <div class="status-indicator status-active" role="status">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span><?php esc_html_e('Chatbot Active', 'wpiko-chatbot'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="status-indicator status-inactive" role="status">
                        <span class="dashicons dashicons-warning"></span>
                        <span><?php esc_html_e('Setup Required', 'wpiko-chatbot'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats Grid -->
        <div class="dashboard-stats-grid">
            <div class="stat-card stat-conversations">
                <div class="stat-icon">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($total_conversations); ?></div>
                    <div class="stat-label">Total Conversations</div>
                </div>
            </div>
            
            <div class="stat-card stat-messages">
                <div class="stat-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($total_messages); ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
            </div>
            
            <div class="stat-card stat-users">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($total_users); ?></div>
                    <div class="stat-label">Unique Users</div>
                </div>
            </div>
            
            <div class="stat-card stat-today">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo esc_html($conversations_today); ?></div>
                    <div class="stat-label">Today's Conversations</div>
                </div>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="dashboard-main-content">
            <!-- Left Column -->
            <div class="dashboard-left-column">
                <!-- Recent Activity -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-clock"></span> Recent Activity</h3>
                        <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_chatbot_tab_nonce')); ?>" class="widget-action">View All</a>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($recent_conversations)): ?>
                            <div class="activity-list">
                                <?php foreach ($recent_conversations as $conversation): 
                                    $user_display = 'Guest';
                                    if ($conversation->user_id != 0) {
                                        $user_data = get_userdata($conversation->user_id);
                                        $user_display = $user_data ? ($user_data->display_name ?: $user_data->user_login) : 'Unknown User';
                                    } elseif (!empty($conversation->user_name)) {
                                        $user_display = $conversation->user_name;
                                    } elseif (!empty($conversation->user_email)) {
                                        $user_display = $conversation->user_email;
                                    }
                                    
                                    $time_ago = human_time_diff(strtotime($conversation->timestamp), current_time('timestamp'));
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-avatar">
                                            <?php echo get_avatar($conversation->user_id, 32); ?>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-user"><?php echo esc_html($user_display); ?></div>
                                            <div class="activity-message"><?php echo esc_html(wp_trim_words($conversation->last_message, 8, '...')); ?></div>
                                        </div>
                                        <div class="activity-meta">
                                            <span class="activity-time"><?php echo esc_html($time_ago); ?> ago</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-activity">
                                <span class="dashicons dashicons-info-outline"></span>
                                <p>No conversations yet. Once users start chatting, their activity will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attention Required -->
                <div class="dashboard-widget attention-widget">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Attention Required', 'wpiko-chatbot'); ?></h3>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($attention_items)): ?>
                            <div class="attention-list">
                                <?php
                                foreach ($attention_items as $attention_item):
                                    if (!is_array($attention_item) || empty($attention_item['title'])) {
                                        continue;
                                    }

                                    $allowed_severities = array('critical', 'warning', 'info');
                                    $severity = isset($attention_item['severity']) && in_array($attention_item['severity'], $allowed_severities, true)
                                        ? $attention_item['severity']
                                        : 'info';
                                    $icon = !empty($attention_item['icon']) ? sanitize_html_class($attention_item['icon']) : 'dashicons-info-outline';
                                    ?>
                                    <div class="attention-item attention-<?php echo esc_attr($severity); ?>">
                                        <div class="attention-icon" aria-hidden="true">
                                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                        </div>
                                        <div class="attention-details">
                                            <div class="attention-title"><?php echo esc_html($attention_item['title']); ?></div>
                                            <?php if (!empty($attention_item['description'])): ?>
                                                <div class="attention-description"><?php echo esc_html($attention_item['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($attention_item['url']) && !empty($attention_item['action_label'])): ?>
                                            <a href="<?php echo esc_url($attention_item['url']); ?>" class="attention-action">
                                                <?php echo esc_html($attention_item['action_label']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="attention-empty" role="status">
                                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                                <div>
                                    <strong><?php esc_html_e('Everything looks good', 'wpiko-chatbot'); ?></strong>
                                    <p><?php esc_html_e('No configuration problems or chatbot errors were detected in the last 24 hours.', 'wpiko-chatbot'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="dashboard-right-column">
                <!-- Configuration Status -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h3><span class="dashicons dashicons-admin-generic"></span> Configuration Status</h3>
                    </div>
                    <div class="widget-content">
                        <div class="config-status-list">
                            <div class="config-item <?php echo $is_configured ? 'config-complete' : 'config-incomplete'; ?>">
                                <span class="config-indicator"></span>
                                <span class="config-label">API Key</span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=api_key', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action">Setup</a>
                            </div>
                            <div class="config-item config-complete">
                                <span class="config-indicator"></span>
                                <span class="config-label">AI Model <span class="config-value"><?php echo esc_html($selected_model); ?></span></span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=ai_configuration', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action">Configure</a>
                            </div>
                            <div class="config-item config-complete">
                                <span class="config-indicator"></span>
                                <span class="config-label">Chatbot Name <span class="config-value"><?php echo esc_html($chatbot_name); ?></span></span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=chatbot_style', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action">Configure</a>
                            </div>
                            <div class="config-item <?php echo $floating_enabled ? 'config-complete' : 'config-optional'; ?>">
                                <span class="config-indicator"></span>
                                <span class="config-label">Floating Chatbot</span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=floating_chatbot', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo $floating_enabled ? 'Configure' : 'Enable'; ?></a>
                            </div>
                            <div class="config-item <?php echo esc_attr($proactive_class); ?>">
                                <span class="config-indicator"></span>
                                <span class="config-label">Proactive Greeting</span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=' . $proactive_tab, 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo esc_html($proactive_action); ?></a>
                            </div>
                            <div class="config-item <?php echo ($sound_enabled === '1') ? 'config-complete' : 'config-optional'; ?>">
                                <span class="config-indicator"></span>
                                <span class="config-label">Sound Notifications</span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=chatbot_menu', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($sound_enabled === '1') ? 'Configure' : 'Enable'; ?></a>
                            </div>
                            <div class="config-item <?php echo ($transcript_download === '1') ? 'config-complete' : 'config-optional'; ?>">
                                <span class="config-indicator"></span>
                                <span class="config-label">Transcript Download</span>
                                <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=chatbot_menu', 'wpiko_chatbot_tab_nonce')); ?>" class="config-action"><?php echo ($transcript_download === '1') ? 'Configure' : 'Enable'; ?></a>
                            </div>
                            
                            <?php
                            // Hook for pro plugin to add configuration status items
                            do_action('wpiko_chatbot_dashboard_config_status');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

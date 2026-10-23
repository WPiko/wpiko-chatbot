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
                MAX(c1.country) as country,
                MAX(c1.city) as city,
                (
                    SELECT COUNT(*)
                    FROM `{$wpdb->prefix}wpiko_chatbot_conversations` c3
                    WHERE c3.session_id = c1.session_id
                      AND c3.role IN ('user', 'assistant', 'admin')
                ) as message_count,
                (
                    SELECT c4.role
                    FROM `{$wpdb->prefix}wpiko_chatbot_conversations` c4
                    WHERE c4.session_id = c1.session_id
                    ORDER BY c4.timestamp DESC, c4.id DESC
                    LIMIT 1
                ) as last_role,
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
    $selected_model = get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');

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
            'title' => __('Finish setting up your chatbot', 'wpiko-chatbot'),
            'description' => __('Connect OpenAI, teach the chatbot about your business and test it. It takes about 5 minutes.', 'wpiko-chatbot'),
            'url' => wpiko_chatbot_setup_wizard_url(),
            'action_label' => __('Start the setup wizard', 'wpiko-chatbot'),
        );
    }

    // Configured but not shown anywhere: the most common "nothing happens" moment.
    if ($is_configured && !$floating_enabled) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One cheap lookup on the plugin dashboard only.
        $shortcode_page = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision', 'nav_menu_item') AND post_content LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like('[wpiko_chatbot') . '%'
        ));

        if (!$shortcode_page) {
            $attention_items[] = array(
                'severity' => 'warning',
                'icon' => 'dashicons-hidden',
                'title' => __('Your chatbot is not visible on your site yet', 'wpiko-chatbot'),
                'description' => __('Turn on the floating chatbot to show it on every page, or add the [wpiko_chatbot] shortcode to a page.', 'wpiko-chatbot'),
                'url' => wp_nonce_url('?page=ai-chatbot&tab=floating_chatbot', 'wpiko_chatbot_tab_nonce'),
                'action_label' => __('Turn on floating chatbot', 'wpiko-chatbot'),
            );
        }
    }

    $openai_health = $is_configured ? wpiko_chatbot_get_openai_health() : array();
    if (!empty($openai_health)) {
        $health_titles = array(
            'no_credit' => __('Your OpenAI account has no credit', 'wpiko-chatbot'),
            'invalid_key' => __('OpenAI rejected your API key', 'wpiko-chatbot'),
            'model_access' => __('Your OpenAI account cannot use the selected model', 'wpiko-chatbot'),
            'forbidden' => __('OpenAI refused the chatbot\'s requests', 'wpiko-chatbot'),
        );
        $health_title = isset($health_titles[$openai_health['code']]) ? $health_titles[$openai_health['code']] : __('The chatbot cannot answer visitors', 'wpiko-chatbot');
        $has_external_fix = !empty($openai_health['link']);

        $attention_items[] = array(
            'severity' => 'critical',
            'icon' => 'dashicons-warning',
            'title' => $health_title,
            'description' => $openai_health['message'],
            'url' => $has_external_fix ? $openai_health['link'] : wp_nonce_url('?page=ai-chatbot&tab=api_key', 'wpiko_chatbot_tab_nonce'),
            'action_label' => $has_external_fix ? $openai_health['link_label'] : __('Test the connection', 'wpiko-chatbot'),
            'external' => $has_external_fix,
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
        <!-- Page Header -->
        <div class="dashboard-welcome">
            <div class="welcome-content">
                <h1><?php esc_html_e('Dashboard', 'wpiko-chatbot'); ?></h1>
                <p class="welcome-description"><?php esc_html_e("A quick overview of your chatbot's performance, activity, and setup.", 'wpiko-chatbot'); ?></p>
            </div>
            <div class="welcome-status">
                <?php if ($is_configured && !empty($openai_health)): ?>
                    <div class="status-indicator status-inactive" role="status">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <span><?php esc_html_e('Needs Attention', 'wpiko-chatbot'); ?></span>
                    </div>
                <?php elseif ($is_configured): ?>
                    <div class="status-indicator status-active" role="status">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <span><?php esc_html_e('Chatbot Active', 'wpiko-chatbot'); ?></span>
                    </div>
                    <a class="wpiko-rerun-setup" href="<?php echo esc_url(wpiko_chatbot_setup_wizard_url()); ?>">
                        <span class="dashicons dashicons-flag" aria-hidden="true"></span>
                        <?php esc_html_e('Run setup again', 'wpiko-chatbot'); ?>
                    </a>
                <?php else: ?>
                    <div class="status-indicator status-inactive" role="status">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <span><?php esc_html_e('Setup Required', 'wpiko-chatbot'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="dashboard-stats-grid">
            <?php
            $stats = array(
                array('class' => 'conversations', 'icon' => 'format-chat', 'label' => __('Total conversations', 'wpiko-chatbot'), 'value' => $total_conversations, 'description' => __('All recorded chat sessions.', 'wpiko-chatbot')),
                array('class' => 'messages', 'icon' => 'email-alt', 'label' => __('Total messages', 'wpiko-chatbot'), 'value' => $total_messages, 'description' => __('User and assistant messages.', 'wpiko-chatbot')),
                array('class' => 'users', 'icon' => 'groups', 'label' => __('Unique users', 'wpiko-chatbot'), 'value' => $total_users, 'description' => __('Registered users and guest sessions.', 'wpiko-chatbot')),
                array('class' => 'today', 'icon' => 'calendar-alt', 'label' => __("Today's conversations", 'wpiko-chatbot'), 'value' => $conversations_today, 'description' => __('Chat sessions with activity today.', 'wpiko-chatbot')),
            );
            foreach ($stats as $stat):
                ?>
                <div class="stat-card stat-<?php echo esc_attr($stat['class']); ?>">
                    <div class="stat-header">
                        <span class="stat-icon" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr($stat['icon']); ?>"></span></span>
                        <h2 class="stat-label"><?php echo esc_html($stat['label']); ?></h2>
                    </div>
                    <div class="stat-number"><?php echo esc_html($stat['value']); ?></div>
                    <p class="stat-description"><?php echo esc_html($stat['description']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-main-content">
            <div class="dashboard-activity-column">
                <!-- Recent Activity -->
                <div class="dashboard-widget">
                    <div class="widget-header">
                        <h2><?php esc_html_e('Recent activity', 'wpiko-chatbot'); ?></h2>
                        <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_chatbot_tab_nonce')); ?>" class="widget-action" aria-label="<?php esc_attr_e('View all conversations', 'wpiko-chatbot'); ?>"><?php esc_html_e('View all', 'wpiko-chatbot'); ?></a>
                    </div>
                    <div class="widget-content">
                        <?php if (!empty($recent_conversations)): ?>
                            <ul class="activity-list">
                                <?php foreach ($recent_conversations as $conversation):
                                    $user_display = __('Guest', 'wpiko-chatbot');
                                    $is_guest = true;
                                    if ($conversation->user_id != 0) {
                                        $user_data = get_userdata($conversation->user_id);
                                        $user_display = $user_data ? ($user_data->display_name ?: $user_data->user_login) : __('Unknown user', 'wpiko-chatbot');
                                        $is_guest = false;
                                    } elseif (!empty($conversation->user_name)) {
                                        $user_display = $conversation->user_name;
                                        $is_guest = false;
                                    } elseif (!empty($conversation->user_email)) {
                                        $user_display = $conversation->user_email;
                                        $is_guest = false;
                                    }

                                    $location = implode(', ', array_filter(array(trim((string) $conversation->city), trim((string) $conversation->country))));
                                    $last_ts = strtotime($conversation->timestamp);
                                    $now_ts = strtotime(current_time('mysql'));
                                    $time_ago = ($now_ts - $last_ts < 60)
                                        ? __('Just now', 'wpiko-chatbot')
                                        /* translators: %s: time difference, e.g. "5 minutes". */
                                        : sprintf(__('%s ago', 'wpiko-chatbot'), human_time_diff($last_ts, $now_ts));
                                    $message_count = (int) $conversation->message_count;
                                    $has_error = $conversation->last_role === 'error';
                                    $show_email = !empty($conversation->user_email) && $user_display !== $conversation->user_email;
                                    $conversation_url = add_query_arg('conversation', rawurlencode($conversation->session_id), wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_chatbot_tab_nonce'));
                                ?>
                                    <li>
                                        <a class="activity-item<?php echo $has_error ? ' has-error' : ''; ?>" href="<?php echo esc_url($conversation_url); ?>">
                                            <span class="activity-avatar">
                                                <?php echo get_avatar($conversation->user_id, 32, '', ''); ?>
                                            </span>
                                            <span class="activity-details">
                                                <span class="activity-user">
                                                    <span class="activity-name"><?php echo esc_html($user_display); ?></span>
                                                    <?php if ($show_email) : ?>
                                                        <span class="dashicons dashicons-email-alt activity-email" title="<?php echo esc_attr($conversation->user_email); ?>" aria-hidden="true"></span>
                                                        <span class="screen-reader-text"><?php echo esc_html(sprintf(/* translators: %s: email address. */ __('Email: %s', 'wpiko-chatbot'), $conversation->user_email)); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($is_guest && $location !== '') : ?>
                                                        <span class="activity-location"><?php echo esc_html($location); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($has_error) : ?>
                                                        <span class="activity-badge is-error"><?php esc_html_e('Error', 'wpiko-chatbot'); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="activity-message"><?php echo esc_html(wp_trim_words($conversation->last_message, 12, '…')); ?></span>
                                            </span>
                                            <span class="activity-meta">
                                                <span class="activity-time" title="<?php echo esc_attr(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $conversation->timestamp)); ?>"><?php echo esc_html($time_ago); ?></span>
                                                <span class="activity-count">
                                                    <?php
                                                    /* translators: %s: number of messages. */
                                                    echo esc_html(sprintf(_n('%s message', '%s messages', $message_count, 'wpiko-chatbot'), number_format_i18n($message_count)));
                                                    ?>
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="no-activity">
                                <span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
                                <div>
                                    <p><?php esc_html_e('No conversations yet. When visitors start chatting, the latest ones appear here.', 'wpiko-chatbot'); ?></p>
                                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer" class="no-activity-link"><?php esc_html_e('Try your chatbot on your site', 'wpiko-chatbot'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'wpiko-chatbot'); ?></span></a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Attention Required -->
                <div class="dashboard-widget attention-widget">
                    <div class="widget-header">
                        <h2><?php esc_html_e('Attention required', 'wpiko-chatbot'); ?></h2>
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
                                            <a href="<?php echo esc_url($attention_item['url']); ?>" class="attention-action"<?php if (!empty($attention_item['external'])): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
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
            <!-- Configuration Status -->
            <?php
            $tab_url = function ($slug) {
                return wp_nonce_url('?page=ai-chatbot&tab=' . $slug, 'wpiko_chatbot_tab_nonce');
            };

            // Essentials: the four things the chatbot needs to answer well.
            if (!$is_configured) {
                $connection = array('state' => 'todo', 'value' => __('Not connected', 'wpiko-chatbot'), 'action' => __('Connect', 'wpiko-chatbot'));
            } elseif (!empty($openai_health)) {
                $connection = array('state' => 'error', 'value' => __('Connected, but OpenAI is refusing requests', 'wpiko-chatbot'), 'action' => __('Fix', 'wpiko-chatbot'));
            } else {
                $connection = array('state' => 'done', 'value' => __('Connected', 'wpiko-chatbot'), 'action' => __('Manage', 'wpiko-chatbot'));
            }

            $model_names = function_exists('wpiko_chatbot_get_responses_api_models') ? wpiko_chatbot_get_responses_api_models() : array();
            $model_label = isset($model_names[$selected_model]) ? $model_names[$selected_model] : $selected_model;

            $knowledge = array('state' => 'todo', 'value' => __('Connect OpenAI first', 'wpiko-chatbot'), 'action' => __('Add content', 'wpiko-chatbot'), 'async' => false);
            if ($is_configured) {
                $cached_knowledge = get_transient('wpiko_chatbot_dashboard_knowledge');
                if (is_array($cached_knowledge) && empty($cached_knowledge['ok'])) {
                    $knowledge = array('state' => 'neutral', 'value' => __('Could not check right now', 'wpiko-chatbot'), 'action' => __('Open', 'wpiko-chatbot'), 'async' => false);
                } elseif (is_array($cached_knowledge)) {
                    $knowledge = !empty($cached_knowledge['total'])
                        ? array('state' => 'done', 'value' => $cached_knowledge['label'], 'action' => __('Manage', 'wpiko-chatbot'), 'async' => false)
                        : array('state' => 'warn', 'value' => __('Empty, so answers will be generic', 'wpiko-chatbot'), 'action' => __('Add content', 'wpiko-chatbot'), 'async' => false);
                } else {
                    $knowledge = array('state' => 'loading', 'value' => __('Checking…', 'wpiko-chatbot'), 'action' => __('Manage', 'wpiko-chatbot'), 'async' => true);
                }
            }

            if ($floating_enabled) {
                $visibility = array('state' => 'done', 'value' => __('Floating chatbot on every page', 'wpiko-chatbot'), 'action' => __('Manage', 'wpiko-chatbot'));
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One cheap lookup on the plugin dashboard only.
                $shortcode_post = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision', 'nav_menu_item') AND post_content LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like('[wpiko_chatbot') . '%'
                ));
                $visibility = $shortcode_post
                    /* translators: %s: page title. */
                    ? array('state' => 'done', 'value' => sprintf(__('Shortcode on “%s”', 'wpiko-chatbot'), $shortcode_post->post_title !== '' ? $shortcode_post->post_title : __('(no title)', 'wpiko-chatbot')), 'action' => __('Manage', 'wpiko-chatbot'))
                    : array('state' => 'todo', 'value' => __('Not shown on your site yet', 'wpiko-chatbot'), 'action' => __('Turn on', 'wpiko-chatbot'));
            }

            $essentials = array(
                'connection' => array_merge($connection, array('label' => __('OpenAI connection', 'wpiko-chatbot'), 'url' => $tab_url('api_key'))),
                'model' => array('state' => $is_configured ? 'done' : 'todo', 'label' => __('AI model', 'wpiko-chatbot'), 'value' => $model_label, 'action' => __('Change', 'wpiko-chatbot'), 'url' => $tab_url('ai_configuration')),
                'knowledge' => array_merge($knowledge, array('label' => __('Knowledge base', 'wpiko-chatbot'), 'url' => $tab_url('ai_configuration'))),
                'visibility' => array_merge($visibility, array('label' => __('Visible on your site', 'wpiko-chatbot'), 'url' => $tab_url('floating_chatbot'))),
            );
            $essentials_done = count(array_filter($essentials, function ($item) {
                return $item['state'] === 'done';
            }));
            $essentials_total = count($essentials);
            $features = wpiko_chatbot_dashboard_features();
            ?>
            <div class="dashboard-widget configuration-widget">
                <div class="widget-header setup-header">
                    <div class="setup-heading">
                        <h2><?php esc_html_e('Configuration status', 'wpiko-chatbot'); ?></h2>
                        <p class="setup-subtitle">
                            <?php
                            /* translators: %s: chatbot name. */
                            echo esc_html(sprintf(__('Chatbot “%s”', 'wpiko-chatbot'), $chatbot_name));
                            ?>
                        </p>
                    </div>
                    <div class="setup-progress" data-total="<?php echo esc_attr($essentials_total); ?>" data-done="<?php echo esc_attr($essentials_done); ?>">
                        <span class="setup-progress-text">
                            <?php
                            /* translators: 1: essentials done, 2: total essentials. */
                            echo wp_kses(sprintf(__('<strong>%1$s</strong> of %2$s essentials done', 'wpiko-chatbot'), '<span class="setup-progress-done">' . (int) $essentials_done . '</span>', (int) $essentials_total), array('strong' => array(), 'span' => array('class' => array())));
                            ?>
                        </span>
                        <span class="setup-progress-bar" aria-hidden="true"><span style="width: <?php echo esc_attr(round(100 * $essentials_done / max(1, $essentials_total))); ?>%"></span></span>
                    </div>
                </div>
                <div class="widget-content">
                    <h3 class="setup-group-title"><?php esc_html_e('Essentials', 'wpiko-chatbot'); ?></h3>
                    <ul class="setup-list">
                        <?php foreach ($essentials as $key => $item) : ?>
                            <li>
                                <a class="setup-item is-<?php echo esc_attr($item['state']); ?>" href="<?php echo esc_url($item['url']); ?>" data-essential="<?php echo esc_attr($key); ?>"<?php echo !empty($item['async']) ? ' data-async="1"' : ''; ?>>
                                    <span class="setup-indicator" aria-hidden="true"></span>
                                    <span class="setup-copy">
                                        <span class="setup-label"><?php echo esc_html($item['label']); ?></span>
                                        <span class="setup-value" title="<?php echo esc_attr($item['value']); ?>"><?php echo esc_html($item['value']); ?></span>
                                    </span>
                                    <span class="setup-action"><?php echo esc_html($item['action']); ?><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($features) : ?>
                        <h3 class="setup-group-title"><?php esc_html_e('Optional features', 'wpiko-chatbot'); ?></h3>
                        <ul class="feature-chips">
                            <?php foreach ($features as $feature) :
                                if ($feature['locked']) {
                                    $chip_class = 'is-locked';
                                    $chip_state = 'PRO';
                                } elseif ($feature['state'] === 'warn') {
                                    $chip_class = 'is-warn';
                                    $chip_state = !empty($feature['state_label']) ? $feature['state_label'] : __('Check', 'wpiko-chatbot');
                                } else {
                                    $chip_class = $feature['on'] ? 'is-on' : 'is-off';
                                    $chip_state = !empty($feature['state_label']) ? $feature['state_label'] : ($feature['on'] ? __('On', 'wpiko-chatbot') : __('Off', 'wpiko-chatbot'));
                                }
                                ?>
                                <li>
                                    <a class="feature-chip <?php echo esc_attr($chip_class); ?>" href="<?php echo esc_url($feature['url']); ?>"<?php echo $feature['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <span class="feature-dot" aria-hidden="true"></span>
                                        <span class="feature-label"><?php echo esc_html($feature['label']); ?></span>
                                        <span class="feature-state"><?php echo esc_html($chip_state); ?></span>
                                        <?php if ($feature['external']) : ?>
                                            <span class="screen-reader-text"><?php esc_html_e('(opens the WPiko pricing page in a new tab)', 'wpiko-chatbot'); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php
                    // Rows added by older add-ons (e.g. WPiko AI Chatbot Pro 2.1.1) through
                    // the original hook keep working in the classic list style.
                    ob_start();
                    do_action('wpiko_chatbot_dashboard_config_status');
                    $legacy_config = trim((string) ob_get_clean());
                    if ($legacy_config !== '') {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup printed by add-ons, escaped there.
                        echo '<div class="config-status-list config-legacy">' . $legacy_config . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Knowledge base summary for the dashboard, from the vector store file list.
 *
 * Cached for a day. Every upload and deletion made through the plugin, and
 * saving or deleting the API key, clears the cache, so the day only matters
 * for files changed directly on OpenAI. A failed check is kept for 5 minutes
 * so an unreachable OpenAI is not asked again on every page load.
 *
 * @return array{ok: bool, total: int, label: string}
 */
function wpiko_chatbot_dashboard_knowledge_summary()
{
    $cached = get_transient('wpiko_chatbot_dashboard_knowledge');
    if (is_array($cached)) {
        return $cached;
    }

    $files = function_exists('wpiko_chatbot_list_responses_files') ? wpiko_chatbot_list_responses_files() : array('success' => false);
    if (empty($files['success'])) {
        $summary = array('ok' => false, 'total' => 0, 'label' => '');
        set_transient('wpiko_chatbot_dashboard_knowledge', $summary, 5 * MINUTE_IN_SECONDS);
        return $summary;
    }

    $counts = array('documents' => 0, 'scanned' => 0, 'qa' => 0, 'quick' => 0, 'products' => 0);
    foreach ((array) $files['files'] as $file) {
        $name = isset($file['filename']) ? (string) $file['filename'] : '';
        if (strpos($name, 'website-pages-') === 0) {
            $counts['quick']++;
        } elseif (strpos($name, 'page_') === 0) {
            $counts['scanned']++;
        } elseif (strpos($name, 'qa_data_') === 0) {
            $counts['qa']++;
        } elseif (strpos($name, 'woocommerce_') === 0) {
            $counts['products']++;
        } else {
            $counts['documents']++;
        }
    }

    $parts = array();
    if ($counts['documents']) {
        /* translators: %s: number of uploaded documents. */
        $parts[] = sprintf(_n('%s document', '%s documents', $counts['documents'], 'wpiko-chatbot'), number_format_i18n($counts['documents']));
    }
    if ($counts['scanned']) {
        /* translators: %s: number of items added with Scan Website. */
        $parts[] = sprintf(_n('%s scanned item', '%s scanned items', $counts['scanned'], 'wpiko-chatbot'), number_format_i18n($counts['scanned']));
    }
    if ($counts['quick']) {
        $state = function_exists('wpiko_chatbot_get_site_knowledge_state') ? wpiko_chatbot_get_site_knowledge_state() : array();
        $pages = isset($state['pages']) ? (int) $state['pages'] : 0;
        /* translators: %s: number of pages in Quick learn. */
        $parts[] = $pages ? sprintf(_n('Quick learn (%s page)', 'Quick learn (%s pages)', $pages, 'wpiko-chatbot'), number_format_i18n($pages)) : __('Quick learn', 'wpiko-chatbot');
    }
    if ($counts['qa']) {
        $parts[] = __('Q&A Builder', 'wpiko-chatbot');
    }
    if ($counts['products']) {
        $parts[] = __('Product catalog', 'wpiko-chatbot');
    }

    $summary = array(
        'ok' => true,
        'total' => array_sum($counts),
        'label' => implode(' · ', $parts),
    );
    // A list read from the local cache because OpenAI could not be reached
    // may be out of date, so check again sooner.
    $from_fallback = isset($files['source']) && $files['source'] === 'cache_fallback';
    set_transient('wpiko_chatbot_dashboard_knowledge', $summary, $from_fallback ? 5 * MINUTE_IN_SECONDS : DAY_IN_SECONDS);
    return $summary;
}

/** AJAX: knowledge base summary, loaded after the dashboard renders. */
function wpiko_chatbot_dashboard_knowledge_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }
    wp_send_json_success(wpiko_chatbot_dashboard_knowledge_summary());
}
add_action('wp_ajax_wpiko_chatbot_dashboard_knowledge', 'wpiko_chatbot_dashboard_knowledge_ajax');

/**
 * Optional features shown as On/Off chips on the dashboard.
 *
 * Add-ons add their own through the `wpiko_chatbot_dashboard_features` filter.
 *
 * @return array[] Each: label, on (bool), url, pro (bool), locked (bool), state ('' or 'warn').
 */
function wpiko_chatbot_dashboard_features()
{
    $tab = function ($slug) {
        return wp_nonce_url('?page=ai-chatbot&tab=' . $slug, 'wpiko_chatbot_tab_nonce');
    };

    // The greeting only shows with the floating chatbot and some text to show.
    $proactive_on = (bool) get_option('wpiko_chatbot_proactive_enabled', false);
    $proactive_ready = (bool) get_option('wpiko_chatbot_enable_floating', false)
        && (trim((string) get_option('wpiko_chatbot_proactive_heading', 'Hi!')) !== '' || trim((string) get_option('wpiko_chatbot_proactive_message', "Looking for something specific? We'll help you find it!")) !== '');
    $features = array(
        'proactive' => array(
            'label' => __('Proactive greeting', 'wpiko-chatbot'),
            'on' => $proactive_on,
            'state' => ($proactive_on && !$proactive_ready) ? 'warn' : '',
            'url' => $tab($proactive_on && !get_option('wpiko_chatbot_enable_floating', false) ? 'floating_chatbot' : 'proactive_greeting'),
        ),
        'sound' => array(
            'label' => __('Sound notifications', 'wpiko-chatbot'),
            'on' => get_option('wpiko_chatbot_sound_enabled', '1') === '1',
            'url' => $tab('chatbot_menu'),
        ),
        'transcript' => array(
            'label' => __('Transcript download', 'wpiko-chatbot'),
            'on' => get_option('wpiko_chatbot_enable_transcript_download', '1') === '1',
            'url' => $tab('chatbot_menu'),
        ),
    );

    // Without Pro, show its features locked so site owners know they exist.
    // Their settings screens only exist with Pro, so they open the pricing page.
    if (!wpiko_chatbot_is_pro_plugin_active()) {
        $locked = array(
            'email_capture' => __('Email capture', 'wpiko-chatbot'),
            'contact_form' => __('Contact form', 'wpiko-chatbot'),
            'product_card' => __('Product cards', 'wpiko-chatbot'),
        );
        foreach ($locked as $key => $label) {
            if ($key === 'product_card' && !class_exists('WooCommerce')) {
                continue;
            }
            $features[$key] = array('label' => $label, 'on' => false, 'url' => WPIKO_CHATBOT_PRICING_URL, 'external' => true, 'pro' => true, 'locked' => true);
        }
    }

    /**
     * Filter the optional features listed on the dashboard.
     *
     * @param array[] $features Keyed by feature: label, on, url, pro, locked, state.
     */
    $features = apply_filters('wpiko_chatbot_dashboard_features', $features);

    $clean = array();
    foreach ((array) $features as $key => $feature) {
        if (empty($feature['label']) || empty($feature['url'])) {
            continue;
        }
        $clean[$key] = wp_parse_args($feature, array('on' => false, 'pro' => false, 'locked' => false, 'external' => false, 'state' => ''));
    }
    return $clean;
}

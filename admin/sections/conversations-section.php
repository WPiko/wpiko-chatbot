<?php
// Ensure this file is being run within the WordPress context
if (!defined('WPINC')) {
    die;
}

function wpiko_chatbot_conversations_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    
    $per_page = 10;
    
    // Handle form submission for date filter
    $start_date = '';
    $end_date = '';
    // Verify nonce for date filter
    if (isset($_GET['wpiko_conversations_filter_nonce']) && wp_verify_nonce(sanitize_key($_GET['wpiko_conversations_filter_nonce']), 'wpiko_conversations_filter')) {
        $start_date = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : '';
    }

    // Pagination
    $current_page = 1;
    // Verify nonce for pagination
    if (isset($_GET['wpiko_conversations_filter_nonce']) && wp_verify_nonce(sanitize_key($_GET['wpiko_conversations_filter_nonce']), 'wpiko_conversations_filter')) {
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    }
    $offset = ($current_page - 1) * $per_page;
    $total_items = wpiko_chatbot_get_total_conversations($start_date, $end_date);
    $total_pages = ceil($total_items / $per_page);

    $conversations = wpiko_chatbot_get_conversations($per_page, $offset, $start_date, $end_date);

    // HTML for the conversations section
    ?>
    <div class="conversation-history-section wpiko-chatbot-settings-page wpiko-conversations-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Conversations</h1>
            <p class="wpiko-chatbot-settings-subtitle">View and manage conversations between visitors and your chatbot.</p>
        </header>
            
        <!-- Date filter form -->
        <section class="wpiko-chatbot-settings-card" aria-labelledby="conversation-filter-title">
            <h2 class="wpiko-chatbot-settings-card-title" id="conversation-filter-title">Date range</h2>
            <form method="get" action="" id="conversation-filter-form" class="conversation-filter-form">
                <input type="hidden" name="page" value="ai-chatbot">
                <input type="hidden" name="tab" value="conversations">
                <?php wp_nonce_field('wpiko_conversations_filter', 'wpiko_conversations_filter_nonce'); ?>
                <div class="wpiko-chatbot-settings-field-control">
                    <label class="wpiko-chatbot-settings-field-label" for="start_date">Start date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                </div>
                <div class="wpiko-chatbot-settings-field-control">
                    <label class="wpiko-chatbot-settings-field-label" for="end_date">End date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                </div>
                <div class="wpiko-conversation-filter-actions">
                    <input type="submit" class="button wpiko-chatbot-settings-save" value="Filter">
                    <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_conversations_filter', 'wpiko_conversations_filter_nonce')); ?>" class="button" id="reset-filter">Reset</a>
                </div>
            </form>
        </section>

        <!-- Conversations layout -->
        <div class="conversations-container wpiko-chatbot-settings-card" aria-label="Conversation inbox">
            <!-- Left sidebar - Conversations list -->
            <div class="conversations-sidebar">
                <div class="conversations-sidebar-header">
                    <div class="sidebar-title-row">
                        <div class="conversation-panel-heading">
                            <span class="conversation-panel-icon" aria-hidden="true">
                                <span class="dashicons dashicons-email-alt"></span>
                            </span>
                            <span class="conversation-panel-copy">
                                <span class="sidebar-title">Inbox</span>
                                <span class="conversation-panel-subtitle">Recent conversations</span>
                            </span>
                        </div>
                        <span class="conversations-count"><?php echo esc_html(number_format_i18n($total_items)); ?></span>
                    </div>
                    <div class="conversations-search">
                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                        <input type="search" id="conversation-search" placeholder="Search conversations..." aria-label="Search conversations" autocomplete="off">
                        <button type="button" class="clear-search" title="Clear search" aria-label="Clear search" style="display: none;">&times;</button>
                    </div>
                </div>
                <div class="conversations-list">
                    <?php if ($conversations): ?>
                        <?php foreach ($conversations as $conversation) {
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic values are escaped inside the renderer
                            echo wpiko_chatbot_render_conversation_item($conversation);
                        } ?>
                    <?php else: ?>
                        <div class="no-conversations">No conversations found.</div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php
                    // Create base URL with nonce included
                    $base_url = add_query_arg(array(
                        'paged' => '%#%',
                        'wpiko_conversations_filter_nonce' => wp_create_nonce('wpiko_conversations_filter')
                    ));

                    // Compact pager: previous / "Page [n] of N" / next. Page numbers
                    // never fit the narrow inbox column once there are many pages.
                    $total_pages = (int) $total_pages;
                    $current_page = min($current_page, max(1, $total_pages));
                    if ($total_pages > 1) :
                        $page_url = function ($page) use ($base_url) {
                            return str_replace('%#%', (string) (int) $page, $base_url);
                        };
                        ?>
                        <nav class="conversations-pagination" aria-label="<?php esc_attr_e('Conversation pages', 'wpiko-chatbot'); ?>">
                            <?php if ($current_page > 1) : ?>
                                <a class="conversations-page-step" href="<?php echo esc_url($page_url($current_page - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'wpiko-chatbot'); ?>" title="<?php esc_attr_e('Previous page', 'wpiko-chatbot'); ?>">
                                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                                </a>
                            <?php else : ?>
                                <span class="conversations-page-step is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-left-alt2"></span></span>
                            <?php endif; ?>

                            <label class="conversations-page-jump">
                                <span><?php esc_html_e('Page', 'wpiko-chatbot'); ?></span>
                                <select class="conversations-page-select" data-url="<?php echo esc_attr($base_url); ?>" aria-label="<?php esc_attr_e('Go to page', 'wpiko-chatbot'); ?>">
                                    <?php for ($page = 1; $page <= $total_pages; $page++) : ?>
                                        <option value="<?php echo esc_attr($page); ?>" <?php selected($page, $current_page); ?>><?php echo esc_html(number_format_i18n($page)); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span>
                                    <?php
                                    /* translators: %s: total number of pages. */
                                    echo esc_html(sprintf(__('of %s', 'wpiko-chatbot'), number_format_i18n($total_pages)));
                                    ?>
                                </span>
                            </label>

                            <?php if ($current_page < $total_pages) : ?>
                                <a class="conversations-page-step" href="<?php echo esc_url($page_url($current_page + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'wpiko-chatbot'); ?>" title="<?php esc_attr_e('Next page', 'wpiko-chatbot'); ?>">
                                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                                </a>
                            <?php else : ?>
                                <span class="conversations-page-step is-disabled" aria-hidden="true"><span class="dashicons dashicons-arrow-right-alt2"></span></span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Center - Conversation transcript -->
            <div class="conversation-transcript">
                <div class="conversation-panel-header">
                    <div class="conversation-panel-heading">
                        <span class="conversation-panel-icon" aria-hidden="true">
                            <span class="dashicons dashicons-format-chat"></span>
                        </span>
                        <span class="conversation-panel-copy">
                            <span class="conversation-panel-title">Conversation</span>
                            <span class="conversation-panel-subtitle">Full message history</span>
                        </span>
                    </div>
                    <span class="conversation-panel-status">
                        <span class="conversation-panel-status-dot" aria-hidden="true"></span>
                        Ready
                    </span>
                </div>
                <div class="transcript-placeholder">
                    <div class="placeholder-card">
                        <span class="placeholder-icon" aria-hidden="true">
                            <span class="dashicons dashicons-format-chat"></span>
                        </span>
                        <span class="placeholder-title">No conversation selected</span>
                        <span class="placeholder-hint">Choose a conversation from your inbox to view the full transcript.</span>
                    </div>
                </div>
                <div class="transcript-content" style="display: none;">
                    <!-- Transcript content will be loaded here -->
                </div>
            </div>

            <!-- Right sidebar - Conversation details -->
            <div class="conversation-details">
                <div class="conversation-panel-header">
                    <div class="conversation-panel-heading">
                        <span class="conversation-panel-icon" aria-hidden="true">
                            <span class="dashicons dashicons-id-alt"></span>
                        </span>
                        <span class="conversation-panel-copy">
                            <span class="conversation-panel-title">Details</span>
                            <span class="conversation-panel-subtitle">Contact and session info</span>
                        </span>
                    </div>
                </div>
                <div class="details-placeholder">
                    <div class="placeholder-card">
                        <span class="placeholder-icon" aria-hidden="true">
                            <span class="dashicons dashicons-admin-users"></span>
                        </span>
                        <span class="placeholder-title">No details yet</span>
                        <span class="placeholder-hint">Contact information and conversation actions will appear here.</span>
                    </div>
                </div>
                <div class="details-content" style="display: none;">
                    <!-- User Info Section -->
                    <div class="user-info-section">
                    <div class="user-avatar-large" id="details-avatar">
                        <!-- Avatar will be updated via JavaScript -->
                    </div>
                        <h3 class="user-name"></h3>
                        <?php do_action('wpiko_chatbot_conversation_user_location'); ?>
                        <div class="user-email"></div>
                    </div>

                    <!-- Additional Info Section -->
                    <div class="additional-info-section">
                        <h4>Additional Info</h4>
                        <div class="info-item">
                            <span class="info-label">User status:</span>
                            <span class="user-status info-value"></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">User device:</span>
                            <span class="user-device info-value"></span>
                        </div>
                        <?php do_action('wpiko_chatbot_conversation_additional_info'); ?>
                    </div>

                    <div class="actions">
                        <?php do_action('wpiko_chatbot_conversation_contact_user_button'); ?>
                        <div class="download-conversation-wrapper">
                            <div class="integrated-download-button">
                                <button type="button" class="translation-icon-button" title="Translation options" aria-label="Translation options">
                                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
                                </button>
                                <div class="translation-dropdown" style="display: none;">
                                    <select id="translation-language" class="translation-language-select" aria-label="Transcript translation language">
                                        <option value="none">No Translation</option>
                                        <option value="English">English</option>
                                        <option value="Spanish">Spanish</option>
                                        <option value="French">French</option>
                                        <option value="German">German</option>
                                        <option value="Italian">Italian</option>
                                        <option value="Portuguese">Portuguese</option>
                                        <option value="Dutch">Dutch</option>
                                        <option value="Russian">Russian</option>
                                        <option value="Chinese (Simplified)">Chinese (Simplified)</option>
                                        <option value="Chinese (Traditional)">Chinese (Traditional)</option>
                                        <option value="Japanese">Japanese</option>
                                        <option value="Korean">Korean</option>
                                        <option value="Arabic">Arabic</option>
                                        <option value="Hindi">Hindi</option>
                                        <option value="Turkish">Turkish</option>
                                        <option value="Polish">Polish</option>
                                        <option value="Swedish">Swedish</option>
                                        <option value="Danish">Danish</option>
                                        <option value="Norwegian">Norwegian</option>
                                        <option value="Finnish">Finnish</option>
                                        <option value="Greek">Greek</option>
                                        <option value="Czech">Czech</option>
                                        <option value="Hungarian">Hungarian</option>
                                        <option value="Romanian">Romanian</option>
                                        <option value="Thai">Thai</option>
                                        <option value="Vietnamese">Vietnamese</option>
                                        <option value="Indonesian">Indonesian</option>
                                        <option value="Malay">Malay</option>
                                        <option value="Hebrew">Hebrew</option>
                                        <option value="Ukrainian">Ukrainian</option>
                                    </select>
                                </div>
                                <button type="button" class="button download-conversation">
                                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Download Conversation
                                </button>
                            </div>
                        </div>
                        <button type="button" class="button delete-conversation wpiko-chatbot-settings-danger">
                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    Delete Conversation
                </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Container -->
        <div class="settings-container">
            <?php do_action('wpiko_chatbot_conversation_auto_delete_settings'); ?>

            <!-- Download Emails Section -->
            <?php
            $email_stats = wpiko_chatbot_get_email_stats();
            $email_last_seen = '';
            if ($email_stats['last_seen'] !== '') {
                $last_seen_ts = strtotime($email_stats['last_seen']);
                $now_local = strtotime(current_time('mysql'));
                $email_last_seen = ($last_seen_ts && $now_local - $last_seen_ts < 60)
                    ? __('Just now', 'wpiko-chatbot')
                    /* translators: %s: time difference, e.g. "3 days". */
                    : sprintf(__('%s ago', 'wpiko-chatbot'), human_time_diff($last_seen_ts, $now_local));
            }
            $email_capture_on = get_option('wpiko_chatbot_enable_email_capture', '0') === '1';
            // Email Capture's settings screen only exists with Pro installed.
            $email_capture_external = !wpiko_chatbot_is_pro_plugin_active();
            $email_capture_url = $email_capture_external ? WPIKO_CHATBOT_PRICING_URL : wp_nonce_url('?page=ai-chatbot&tab=email_capture', 'wpiko_chatbot_tab_nonce');
            ?>
            <div class="download-emails-section wpiko-chatbot-settings-card">
                <div class="download-emails-content">
                    <div class="wpiko-settings-card-header">
                        <div class="wpiko-settings-card-heading">
                            <h2 class="wpiko-chatbot-settings-card-title"><?php esc_html_e('Download emails', 'wpiko-chatbot'); ?></h2>
                        </div>
                    </div>
                    <p class="download-description wpiko-chatbot-settings-field-description"><?php esc_html_e('Export the contacts from your chatbot conversations as a CSV file: visitors who were signed in to your site, and visitors who gave their email through Email Capture.', 'wpiko-chatbot'); ?></p>

                    <dl class="email-stats">
                        <div class="email-stat">
                            <dt><?php esc_html_e('Email addresses', 'wpiko-chatbot'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n($email_stats['total'])); ?></dd>
                        </div>
                        <div class="email-stat">
                            <dt><?php esc_html_e('New in 30 days', 'wpiko-chatbot'); ?></dt>
                            <dd><?php echo esc_html(number_format_i18n($email_stats['recent'])); ?></dd>
                        </div>
                        <div class="email-stat">
                            <dt><?php esc_html_e('Last collected', 'wpiko-chatbot'); ?></dt>
                            <dd class="email-stat-text"><?php echo $email_last_seen !== '' ? esc_html($email_last_seen) : '&mdash;'; ?></dd>
                        </div>
                    </dl>

                    <?php if ($email_stats['total'] > 0) : ?>
                        <div class="email-export-row">
                            <label class="email-export-range">
                                <span><?php esc_html_e('Include', 'wpiko-chatbot'); ?></span>
                                <span class="wpiko-select-wrap">
                                    <select id="download_emails_range">
                                        <option value="0"><?php esc_html_e('All time', 'wpiko-chatbot'); ?></option>
                                        <option value="7"><?php esc_html_e('Active in the last 7 days', 'wpiko-chatbot'); ?></option>
                                        <option value="30"><?php esc_html_e('Active in the last 30 days', 'wpiko-chatbot'); ?></option>
                                        <option value="90"><?php esc_html_e('Active in the last 90 days', 'wpiko-chatbot'); ?></option>
                                    </select>
                                </span>
                            </label>
                            <button type="button" id="download_emails" class="button wpiko-chatbot-settings-save">
                                <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <?php esc_html_e('Download CSV', 'wpiko-chatbot'); ?>
                            </button>
                        </div>
                        <p class="download-note"><?php esc_html_e('Columns: email, name, number of conversations, first and last contact. Opens in Excel, Numbers or Google Sheets.', 'wpiko-chatbot'); ?></p>
                    <?php else : ?>
                        <p class="email-empty"><?php esc_html_e('No email addresses yet. They are saved when signed-in visitors chat, or when visitors give their email through Email Capture.', 'wpiko-chatbot'); ?></p>
                    <?php endif; ?>

                    <?php if (!$email_capture_on) : ?>
                        <p class="email-capture-tip">
                            <span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
                            <span>
                                <?php esc_html_e('Collect more contacts: Email Capture asks visitors for their name and email before they start chatting.', 'wpiko-chatbot'); ?>
                                <?php if ($email_capture_external) : ?>
                                    <a href="<?php echo esc_url($email_capture_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get Email Capture', 'wpiko-chatbot'); ?><span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'wpiko-chatbot'); ?></span></a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($email_capture_url); ?>"><?php esc_html_e('Set up Email Capture', 'wpiko-chatbot'); ?></a>
                                <?php endif; ?>
                                <?php if (!(function_exists('wpiko_chatbot_is_license_active') && wpiko_chatbot_is_license_active())) : ?>
                                    <span class="wpiko-pro-pill">PRO</span>
                                <?php endif; ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <?php
}

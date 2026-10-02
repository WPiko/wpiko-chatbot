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
    <div class="conversation-history-section">
        <h2><span class="dashicons dashicons-format-chat"></span> Conversations</h2>
        <p class="description">View and manage chat interactions between users and your AI chatbot.</p>
            
        <!-- Date filter form -->
        <form method="get" action="" id="conversation-filter-form" class="conversation-filter-form">
            <input type="hidden" name="page" value="ai-chatbot">
            <input type="hidden" name="tab" value="conversations">
            <?php wp_nonce_field('wpiko_conversations_filter', 'wpiko_conversations_filter_nonce'); ?>
            <h3>Filter Conversations</h3>
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            <input type="submit" class="button" value="Filter">
            <a href="<?php echo esc_url(wp_nonce_url('?page=ai-chatbot&tab=conversations', 'wpiko_conversations_filter', 'wpiko_conversations_filter_nonce')); ?>" class="button" id="reset-filter">Reset</a>
        </form>

        <!-- Conversations layout -->
        <div class="conversations-container" aria-label="Conversation inbox">
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
                        <span class="dashicons dashicons-search"></span>
                        <input type="search" id="conversation-search" placeholder="Search conversations..." aria-label="Search conversations" autocomplete="off">
                        <button type="button" class="clear-search" title="Clear search" style="display: none;">&times;</button>
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

                    $pagination_links = paginate_links(array(
                        'base' => $base_url,
                        'format' => '',
                        'prev_text' => __('&laquo;', 'wpiko-chatbot'),
                        'next_text' => __('&raquo;', 'wpiko-chatbot'),
                        'total' => $total_pages,
                        'current' => $current_page
                    ));

                    // Only render the pagination bar when there is more than one page,
                    // otherwise it shows as an empty white box at the bottom of the list.
                    if (!empty($pagination_links)) :
                        ?>
                        <div class="tablenav-pages conversations-pagination">
                            <?php echo wp_kses_post($pagination_links); ?>
                        </div>
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
                                <button class="translation-icon-button" title="Translation options">
                                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
                                </button>
                                <div class="translation-dropdown" style="display: none;">
                                    <select id="translation-language" class="translation-language-select">
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
                                <button class="button download-conversation">
                                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Download Conversation
                                </button>
                            </div>
                        </div>
                        <button class="button delete-conversation">
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
            <div class="download-emails-section">
                <div class="download-emails-content">
                    <div class="wpiko-settings-card-header">
                        <span class="wpiko-settings-card-icon wpiko-settings-card-icon-accent"><span class="dashicons dashicons-email-alt"></span></span>
                        <div class="wpiko-settings-card-heading">
                            <h3>Download Emails</h3>
                            <span class="wpiko-settings-card-subtitle">Export captured contacts as a CSV</span>
                        </div>
                    </div>
                    <p class="download-description">Export a list of all email addresses collected from your chat conversations &mdash; useful for marketing campaigns or maintaining your contact records.</p>
                    <p class="download-note">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <span>The file downloads as <strong>chatbot_user_emails.csv</strong>, ready to import into your CRM or newsletter tool.</span>
                    </p>
                    <button id="download_emails" class="button button-primary">
                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download Emails
                    </button>
                </div>
            </div>
        </div>

    </div>
    <?php
}

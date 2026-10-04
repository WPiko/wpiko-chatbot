<?php
/**
 * Debug Log Admin Section
 * 
 * @package WPIKO_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Render the Debug Log admin section
 */
function wpiko_chatbot_debug_log_section()
{
    // Check user capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save the "delete data on uninstall" preference.
    if (isset($_POST['wpiko_chatbot_uninstall_pref_nonce'])) {
        check_admin_referer('wpiko_chatbot_uninstall_pref', 'wpiko_chatbot_uninstall_pref_nonce');
        update_option('wpiko_chatbot_delete_data_on_uninstall', isset($_POST['wpiko_chatbot_delete_data_on_uninstall']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>' . esc_html__('Preference saved.', 'wpiko-chatbot') . '</p></div>';
    }
    $delete_on_uninstall = get_option('wpiko_chatbot_delete_data_on_uninstall', '0') === '1';

    // Get current settings and stats
    $debug_logging_enabled = wpiko_chatbot_is_debug_logging_enabled();
    $logs = wpiko_chatbot_get_debug_logs('', 100); // Get last 100 logs
    $stats = wpiko_chatbot_get_log_stats();
    ?>
    <div class="wpiko-debug-log-container">
        <!-- Main Card -->
        <div class="wpiko-card">

            <div class="wpiko-card-header">
                <div class="header-title">
                    <h3><span class="dashicons dashicons-admin-tools"></span> Debug Log</h3>
                    <p class="description">Monitor and troubleshoot your chatbot's activity.</p>
                </div>

                <!-- Debug Logging Toggle moved to header -->
                <div class="debug-logging-toggle-wrapper">
                    <span
                        class="toggle-status-label"><?php echo $debug_logging_enabled ? 'Logging Active' : 'Logging Disabled'; ?></span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="wpiko-debug-logging-toggle" <?php checked($debug_logging_enabled); ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- Log Statistics Grid -->
            <div class="log-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-total"><span class="dashicons dashicons-database"></span></div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo esc_html($stats['total']); ?></span>
                        <span class="stat-label">Total Entries</span>
                    </div>
                </div>
                <div class="stat-card stat-error">
                    <div class="stat-icon icon-error"><span class="dashicons dashicons-dismiss"></span></div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo esc_html($stats['error']); ?></span>
                        <span class="stat-label">Errors</span>
                    </div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-icon icon-warning"><span class="dashicons dashicons-warning"></span></div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo esc_html($stats['warning']); ?></span>
                        <span class="stat-label">Warnings</span>
                    </div>
                </div>
                <div class="stat-card stat-info">
                    <div class="stat-icon icon-info"><span class="dashicons dashicons-info-outline"></span></div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo esc_html($stats['info']); ?></span>
                        <span class="stat-label">Info</span>
                    </div>
                </div>
            </div>

            <!-- Toolbar & Actions -->
            <div class="log-toolbar">
                <div class="log-filter-wrapper">
                    <span class="dashicons dashicons-filter"></span>
                    <select id="wpiko-log-filter" class="log-filter">
                        <option value="">All Levels</option>
                        <option value="error">Errors Only</option>
                        <option value="warning">Warnings Only</option>
                        <option value="info">Info Only</option>
                    </select>
                </div>

                <div class="log-actions-right">
                    <button type="button" id="wpiko-refresh-logs" class="button button-secondary icon-button"
                        title="Refresh">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                    <button type="button" id="wpiko-copy-logs" class="button button-secondary icon-button"
                        title="Copy to Clipboard">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                    <button type="button" id="wpiko-clear-logs" class="button button-link-delete">
                        <span class="dashicons dashicons-trash"></span> Clear Logs
                    </button>
                </div>
            </div>

            <!-- Log Viewer Table -->
            <div class="log-viewer-container">
                <table class="log-viewer">
                    <thead>
                        <tr>
                            <th class="log-col-time">Time</th>
                            <th class="log-col-level">Level</th>
                            <th class="log-col-message">Message</th>
                        </tr>
                    </thead>
                    <tbody id="wpiko-log-entries">
                        <?php if (empty($logs)): ?>
                            <tr class="no-logs">
                                <td colspan="3">
                                    <div class="no-logs-state">
                                        <div class="no-logs-icon">
                                            <span class="dashicons dashicons-saved"></span>
                                        </div>
                                        <p class="no-logs-message">
                                            No log entries found.
                                            <span><?php echo $debug_logging_enabled ? 'New events will appear here automatically.' : 'Enable logging to start capturing events.'; ?></span>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-entry log-level-<?php echo esc_attr($log['level']); ?>"
                                    data-level="<?php echo esc_attr($log['level']); ?>">
                                    <td class="log-col-time">
                                        <span class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                    </td>
                                    <td class="log-col-level">
                                        <span class="log-level-badge level-<?php echo esc_attr($log['level']); ?>">
                                            <?php echo esc_html(strtoupper($log['level'])); ?>
                                        </span>
                                    </td>
                                    <td class="log-col-message">
                                        <div class="log-message-content"><?php echo esc_html($log['message']); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer note -->
            <div class="wpiko-card-footer">
                <p><strong>Note:</strong> Disable debug logging when not actively troubleshooting to optimize website
                    performance.</p>
            </div>
        </div>

        <!-- Plugin data on uninstall -->
        <div class="wpiko-card wpiko-uninstall-card">
            <div class="wpiko-card-header">
                <div class="header-title">
                    <h3><span class="dashicons dashicons-database-remove"></span> <?php esc_html_e('Plugin data', 'wpiko-chatbot'); ?></h3>
                    <p class="description"><?php esc_html_e('By default your settings and conversations are kept when the plugin is deleted, so nothing is lost if you reinstall it.', 'wpiko-chatbot'); ?></p>
                </div>
            </div>
            <form method="post" action="" style="padding: 0 24px 20px;">
                <?php wp_nonce_field('wpiko_chatbot_uninstall_pref', 'wpiko_chatbot_uninstall_pref_nonce'); ?>
                <label>
                    <input type="checkbox" name="wpiko_chatbot_delete_data_on_uninstall" value="1" <?php checked($delete_on_uninstall); ?>>
                    <?php esc_html_e('Delete all WPiko Chatbot settings, conversations and logs from this site when the plugin is deleted', 'wpiko-chatbot'); ?>
                </label>
                <p class="description"><?php esc_html_e('Files you uploaded to OpenAI stay in your OpenAI account. Delete the knowledge base in AI Configuration first if you also want those removed.', 'wpiko-chatbot'); ?></p>
                <?php submit_button(__('Save preference', 'wpiko-chatbot'), 'secondary', 'submit', false); ?>
            </form>
        </div>

        <!-- Hidden data for JavaScript -->
        <input type="hidden" id="wpiko-clear-logs-nonce" value="<?php echo esc_attr(wp_create_nonce('wpiko_chatbot_clear_logs')); ?>">
        <input type="hidden" id="wpiko-toggle-logging-nonce"
            value="<?php echo esc_attr(wp_create_nonce('wpiko_chatbot_toggle_logging')); ?>">
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            // Toggle debug logging
            $('#wpiko-debug-logging-toggle').on('change', function () {
                var enabled = $(this).is(':checked') ? '1' : '0';
                var $label = $('.toggle-status-label');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpiko_chatbot_toggle_debug_logging',
                        security: $('#wpiko-toggle-logging-nonce').val(),
                        enabled: enabled
                    },
                    success: function (response) {
                        if (response.success) {
                            $label.text(response.data.enabled ? 'Logging Active' : 'Logging Disabled');
                        }
                    }
                });
            });

            // Clear logs
            $('#wpiko-clear-logs').on('click', function () {
                if (!confirm('Are you sure you want to clear all debug logs? This action cannot be undone.')) {
                    return;
                }

                var $button = $(this);
                $button.prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpiko_chatbot_clear_debug_logs',
                        security: $('#wpiko-clear-logs-nonce').val()
                    },
                    success: function (response) {
                        if (response.success) {
                            $('#wpiko-log-entries').html('<tr class="no-logs"><td colspan="3"><div class="no-logs-state"><div class="no-logs-icon"><span class="dashicons dashicons-saved"></span></div><p class="no-logs-message">Logs cleared successfully.<span>New events will appear here automatically.</span></p></div></td></tr>');
                            // Update stats
                            $('.stat-number').text('0');
                        }
                        $button.prop('disabled', false);
                    },
                    error: function () {
                        alert('Failed to clear logs. Please try again.');
                        $button.prop('disabled', false);
                    }
                });
            });

            // Refresh page
            $('#wpiko-refresh-logs').on('click', function () {
                location.reload();
            });

            // Copy logs to clipboard
            $('#wpiko-copy-logs').on('click', function () {
                var logText = '';
                var currentFilter = $('#wpiko-log-filter').val();
                var filterLabel = currentFilter ? currentFilter.toUpperCase() + ' only' : 'All levels';
                var visibleCount = 0;

                $('#wpiko-log-entries tr.log-entry:visible').each(function () {
                    var timestamp = $(this).find('.log-timestamp').text().trim();
                    var level = $(this).find('.log-level-badge').text().trim();
                    var message = $(this).find('.log-message-content').text().trim();
                    logText += '[' + timestamp + '] [' + level + '] ' + message + '\n';
                    visibleCount++;
                });

                if (!logText || visibleCount === 0) {
                    logText = 'No log entries to copy.';
                }

                // Add header with filter info
                var header = '=== WPIKO Chatbot Debug Log ===\n' +
                    'Exported: ' + new Date().toISOString() + '\n' +
                    'Filter: ' + filterLabel + '\n' +
                    'Entries: ' + visibleCount + '\n' +
                    '================================\n\n';

                logText = header + logText;

                // Copy to clipboard with fallback for non-HTTPS contexts
                copyToClipboard(logText, function (success) {
                    var $button = $('#wpiko-copy-logs');
                    if (success) {
                        // Show tooltip or change icon temporarily
                        var $icon = $button.find('.dashicons');
                        var originalClass = $icon.attr('class');
                        $icon.attr('class', 'dashicons dashicons-yes');
                        setTimeout(function () {
                            $icon.attr('class', originalClass);
                        }, 2000);
                    } else {
                        alert('Failed to copy to clipboard. Please try again or use Ctrl+C after selecting the text.');
                    }
                });
            });

            // Clipboard helper function with fallback
            function copyToClipboard(text, callback) {
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () {
                        callback(true);
                    }).catch(function () {
                        // Fallback to textarea method
                        fallbackCopy(text, callback);
                    });
                } else {
                    // Fallback for non-HTTPS or older browsers
                    fallbackCopy(text, callback);
                }
            }

            // Fallback copy method using textarea
            function fallbackCopy(text, callback) {
                var textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '0';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    var successful = document.execCommand('copy');
                    callback(successful);
                } catch (err) {
                    callback(false);
                }

                document.body.removeChild(textArea);
            }

            // Filter logs by level
            $('#wpiko-log-filter').on('change', function () {
                var level = $(this).val();

                if (level === '') {
                    $('#wpiko-log-entries tr.log-entry').show();
                } else {
                    $('#wpiko-log-entries tr.log-entry').hide();
                    $('#wpiko-log-entries tr.log-entry[data-level="' + level + '"]').show();
                }
            });
        });
    </script>
    <?php
}

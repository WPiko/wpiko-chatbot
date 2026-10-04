<?php
/**
 * Runs when WPiko Chatbot is deleted from the Plugins screen.
 *
 * Temporary data is always removed. Settings, conversations and logs are only
 * removed when the admin opted in under Manage & Tools → Debug Log → Plugin data,
 * so reinstalling the plugin never loses a working setup by surprise.
 *
 * @package WPiko_Chatbot
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up a single site.
 *
 * @return void
 */
function wpiko_chatbot_uninstall_site()
{
    global $wpdb;

    // Always: transients and caches.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_wpiko_chatbot_') . '%',
            $wpdb->esc_like('_transient_timeout_wpiko_chatbot_') . '%'
        )
    );
    delete_option('wpiko_chatbot_openai_health');
    delete_option('wpiko_chatbot_last_connection_test');

    if (get_option('wpiko_chatbot_delete_data_on_uninstall', '0') !== '1') {
        return;
    }

    // Opted in: remove every setting and table the plugin created.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('wpiko_chatbot_') . '%'
        )
    );

    $tables = array(
        $wpdb->prefix . 'wpiko_chatbot_conversations',
        $wpdb->prefix . 'wpiko_chatbot_system_instructions',
        $wpdb->prefix . 'wpiko_chatbot_conversation_meta',
        $wpdb->prefix . 'wpiko_chatbot_qa',
    );
    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
}

if (is_multisite()) {
    $wpiko_site_ids = get_sites(array('fields' => 'ids', 'number' => 0));
    foreach ($wpiko_site_ids as $wpiko_site_id) {
        switch_to_blog($wpiko_site_id);
        wpiko_chatbot_uninstall_site();
        restore_current_blog();
    }
} else {
    wpiko_chatbot_uninstall_site();
}

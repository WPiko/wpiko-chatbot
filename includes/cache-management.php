<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Cache Management for WPiko Chatbot
 * 
 * This file handles automatic cache invalidation when chatbot settings are changed
 * to prevent caching issues with popular WordPress caching plugins.
 */

class WPiko_Chatbot_Cache_Manager
{

    private static $instance = null;
    private $cache_key_version = '';
    private $monitored_options = array();

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_monitored_options();
        $this->init_hooks();
        $this->cache_key_version = $this->get_cache_key_version();
    }

    /**
     * Initialize the list of options that should trigger cache clearing
     */
    private function init_monitored_options()
    {
        $this->monitored_options = array(
            // Chatbot style options
            'wpiko_chatbot_name',
            'wpiko_chatbot_image',
            'wpiko_chatbot_welcome_message',
            'wpiko_chatbot_input_placeholder',
            'wpiko_chatbot_primary_color',
            'wpiko_chatbot_primary_text_color',
            'wpiko_chatbot_chatbot_background_color',
            'wpiko_chatbot_chatbot_header_color',
            'wpiko_chatbot_chatbot_border_color',
            'wpiko_chatbot_chatbot_name_color',
            'wpiko_chatbot_user_background_color',
            'wpiko_chatbot_user_text_color',
            'wpiko_chatbot_bot_background_color',
            'wpiko_chatbot_bot_text_color',
            'wpiko_chatbot_admin_message_label_color',
            'wpiko_chatbot_admin_message_background_color',
            'wpiko_chatbot_admin_message_border_color',
            'wpiko_chatbot_admin_message_text_color',
            'wpiko_chatbot_icon_color',
            'wpiko_chatbot_input_background_color',
            'wpiko_chatbot_floating_text_bg_color',
            'wpiko_chatbot_show_user_border',

            // Chatbot menu options
            'wpiko_chatbot_sound_enabled',
            'wpiko_chatbot_enable_transcript_download',

            // Floating chatbot options
            'wpiko_chatbot_enable_floating',
            'wpiko_chatbot_floating_position',
            'wpiko_chatbot_floating_text',
            'wpiko_chatbot_floating_width',
            'wpiko_chatbot_floating_height',
            'wpiko_chatbot_width',
            'wpiko_chatbot_height',
            'wpiko_chatbot_hide_on_desktop',
            'wpiko_chatbot_hide_on_tablet',
            'wpiko_chatbot_hide_on_mobile',

            // Proactive greeting options
            'wpiko_chatbot_proactive_enabled',
            'wpiko_chatbot_proactive_heading',
            'wpiko_chatbot_proactive_message',
            'wpiko_chatbot_proactive_trigger',
            'wpiko_chatbot_proactive_delay',
            'wpiko_chatbot_proactive_scroll_depth',
            'wpiko_chatbot_proactive_frequency',
            'wpiko_chatbot_proactive_show_input',
            'wpiko_chatbot_proactive_placeholder',
            'wpiko_chatbot_proactive_hide_desktop',
            'wpiko_chatbot_proactive_hide_tablet',
            'wpiko_chatbot_proactive_hide_mobile',

            // Floating chatbot exclusion options
            'wpiko_chatbot_exclude_home',
            'wpiko_chatbot_exclude_blog',
            'wpiko_chatbot_exclude_articles',
            'wpiko_chatbot_exclude_cart',
            'wpiko_chatbot_exclude_checkout',
            'wpiko_chatbot_custom_exclusions',

            // Questions
            'wpiko_chatbot_questions',

            // Pro plugin options (if active)
            'wpiko_chatbot_enable_email_capture',
            'wpiko_chatbot_enable_contact_form',
            'wpiko_chatbot_email_capture_title',
            'wpiko_chatbot_email_capture_description',
            'wpiko_chatbot_email_capture_button_text',
            'wpiko_chatbot_contact_form_dropdown',
            'wpiko_chatbot_contact_form_attachments',
            'wpiko_chatbot_contact_form_dropdown_options',
            'wpiko_chatbot_contact_form_custom_field_1',
            'wpiko_chatbot_contact_form_custom_field_1_label',
            'wpiko_chatbot_contact_form_custom_field_1_required',
            'wpiko_chatbot_contact_form_custom_field_2',
            'wpiko_chatbot_contact_form_custom_field_2_label',
            'wpiko_chatbot_contact_form_custom_field_2_required',
        );

        // Allow pro plugin to add more options
        $this->monitored_options = apply_filters('wpiko_chatbot_cache_monitored_options', $this->monitored_options);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Hook into option updates to clear cache
        foreach ($this->monitored_options as $option) {
            add_action("update_option_{$option}", array($this, 'on_option_update'), 10, 3);
        }

        // Add cache-busting to script/style enqueuing
        add_filter('wpiko_chatbot_asset_version', array($this, 'get_dynamic_version'));

        // Add cache headers for chatbot content
        add_action('wp_head', array($this, 'add_cache_headers'), 1);

        // Clear cache when assistant is updated
        add_action('wpiko_chatbot_assistant_updated', array($this, 'clear_chatbot_cache'));

        // Admin notices for cache clearing
        add_action('admin_notices', array($this, 'show_cache_notices'));
    }

    /**
     * Handle option updates
     */
    public function on_option_update($old_value, $value, $option)
    {
        // Treat updated proactive settings as a new greeting configuration.
        // The frontend uses this version to reset only proactive frequency
        // markers, without affecting them when unrelated chatbot settings change.
        if (strpos($option, 'wpiko_chatbot_proactive_') === 0) {
            $this->update_proactive_settings_version();
        }

        // Update cache key version
        $this->update_cache_key_version();

        // Clear various caches
        $cleared_plugins = $this->clear_chatbot_cache();

        // If this is during a form submission (POST request), show immediate notice
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && !wp_doing_ajax()) {
            $this->show_immediate_cache_notice($cleared_plugins);
        } else {
            // Set a transient to show admin notice on next page load (for other updates)
            set_transient('wpiko_chatbot_cache_cleared', true, 30);
            set_transient('wpiko_chatbot_cleared_plugins', $cleared_plugins, 30);
        }

        // Log cache clearing
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log("Cache cleared due to option update: {$option}", 'info');
        }
    }

    /**
     * Get or generate cache key version
     */
    private function get_cache_key_version()
    {
        $version = get_option('wpiko_chatbot_cache_version', '');

        if (empty($version)) {
            $version = $this->generate_cache_version();
            update_option('wpiko_chatbot_cache_version', $version);
        }

        return $version;
    }

    /**
     * Update cache key version
     */
    private function update_cache_key_version()
    {
        $new_version = $this->generate_cache_version();
        update_option('wpiko_chatbot_cache_version', $new_version);
        $this->cache_key_version = $new_version;
    }

    /**
     * Update the version used to scope proactive greeting browser state.
     */
    private function update_proactive_settings_version()
    {
        update_option('wpiko_chatbot_proactive_settings_version', $this->generate_cache_version());
    }

    /**
     * Generate a unique cache version
     */
    private function generate_cache_version()
    {
        return md5(time() . wp_rand());
    }

    /**
     * Get dynamic version for assets
     */
    public function get_dynamic_version($version)
    {
        return $version . '-' . $this->cache_key_version;
    }

    /**
     * Clear chatbot-related caches
     */
    public function clear_chatbot_cache()
    {
        // Clear WordPress transients
        $this->clear_wordpress_cache();

        // Clear popular caching plugins and track what was cleared
        $cleared_plugins = $this->clear_plugin_caches();

        // Clear page cache for pages containing chatbot
        $this->clear_page_cache();

        // Clear object cache
        $this->clear_object_cache();

        // Store information about what was cleared for the admin notice
        set_transient('wpiko_chatbot_cleared_plugins', $cleared_plugins, 30);

        return $cleared_plugins;
    }

    /**
     * Clear WordPress native cache
     */
    private function clear_wordpress_cache()
    {
        // Clear WordPress transients related to chatbot
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_wpiko_chatbot_%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_wpiko_chatbot_%'
            )
        );
    }

    /**
     * Clear popular caching plugin caches
     */
    private function clear_plugin_caches()
    {
        $cleared_plugins = array();

        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $cleared_plugins[] = 'WP Rocket';
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $cleared_plugins[] = 'W3 Total Cache';
        }

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $cleared_plugins[] = 'WP Super Cache';
        }

        // LiteSpeed Cache - Use modern action hook API 
        if (defined('LSCWP_V')) {
            // Modern LiteSpeed Cache API using action hooks
            do_action('litespeed_purge_all');
            $cleared_plugins[] = 'LiteSpeed Cache';
        }

        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
            $cleared_plugins[] = 'WP Fastest Cache';
        }

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
            $cleared_plugins[] = 'Autoptimize';
        }

        // WP Optimize
        if (class_exists('WP_Optimize')) {
            $wp_optimize = WP_Optimize();
            if (method_exists($wp_optimize, 'get_page_cache')) {
                $wp_optimize->get_page_cache()->purge();
                $cleared_plugins[] = 'WP Optimize';
            }
        }

        // Breeze
        if (defined('BREEZE_PLUGIN_DIR') || class_exists('Breeze_Admin')) {
            // Use WordPress action hook (modern approach)
            do_action('breeze_clear_all_cache');
            $cleared_plugins[] = 'Breeze';
        }

        // SG Optimizer
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
            $cleared_plugins[] = 'SG Optimizer';
        }

        // Comet Cache
        if (class_exists('comet_cache')) {
            comet_cache::clear();
            $cleared_plugins[] = 'Comet Cache';
        }

        // Cache Enabler
        if (class_exists('Cache_Enabler')) {
            Cache_Enabler::clear_complete_cache();
            $cleared_plugins[] = 'Cache Enabler';
        }

        // Swift Performance
        if (class_exists('Swift_Performance_Cache')) {
            Swift_Performance_Cache::clear_all_cache();
            $cleared_plugins[] = 'Swift Performance';
        }

        // Hummingbird
        if (class_exists('Hummingbird\\WP_Hummingbird')) {
            do_action('wphb_clear_page_cache');
            $cleared_plugins[] = 'Hummingbird';
        }

        return $cleared_plugins;
    }

    /**
     * Clear page cache for specific pages
     */
    private function clear_page_cache()
    {
        // Clear homepage cache (most likely to have floating chatbot)
        $this->clear_page_cache_by_url(home_url());

        // If floating chatbot is enabled, cache is cleared globally by caching plugins
        // so we don't need to identify specific pages
        if (get_option('wpiko_chatbot_enable_floating', '0') === '1') {
            return;
        }

        // For shortcode usage, search for posts containing the shortcode (more efficient than meta_query)
        global $wpdb;

        // Get posts that contain the chatbot shortcode
        $posts_with_shortcode = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_type FROM {$wpdb->posts} 
                WHERE post_status = 'publish' 
                AND post_content LIKE %s 
                AND post_type IN ('page', 'post')
                LIMIT 50",
                '%[wpiko_chatbot%'
            )
        );

        // Clear cache for each page with shortcode
        foreach ($posts_with_shortcode as $post) {
            $this->clear_page_cache_by_url(get_permalink($post->ID));
        }
    }

    /**
     * Clear cache for specific URL
     */
    private function clear_page_cache_by_url($url)
    {
        // WP Rocket
        if (function_exists('rocket_clean_files')) {
            rocket_clean_files($url);
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_url')) {
            w3tc_flush_url($url);
        }

        // LiteSpeed Cache - Use modern action hook API for URL purging
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_url', $url);
        }
    }

    /**
     * Clear object cache
     */
    private function clear_object_cache()
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Add cache headers for chatbot content
     */
    public function add_cache_headers()
    {
        // Only add headers if chatbot is present on the page
        if ($this->is_chatbot_page()) {
            ?>
            <meta name="wpiko-chatbot-cache-version" content="<?php echo esc_attr($this->cache_key_version); ?>">
            <script>
                // Add cache-busting to AJAX requests
                if (typeof wpikoChatbot !== 'undefined') {
                    wpikoChatbot.cache_version = '<?php echo esc_js($this->cache_key_version); ?>';
                }
            </script>
            <?php
        }
    }

    /**
     * Check if current page has chatbot
     */
    private function is_chatbot_page()
    {
        global $post;

        // Check if floating chatbot is enabled
        if (get_option('wpiko_chatbot_enable_floating', false)) {
            return true;
        }

        // Check if page has shortcode
        if ($post && has_shortcode($post->post_content, 'wpiko_chatbot')) {
            return true;
        }

        // Check if page has meta indicating chatbot presence
        if ($post && get_post_meta($post->ID, '_wpiko_chatbot_enabled', true) === '1') {
            return true;
        }

        return false;
    }

    /**
     * Show admin notices when cache is cleared
     */
    public function show_cache_notices()
    {
        if (get_transient('wpiko_chatbot_cache_cleared')) {
            delete_transient('wpiko_chatbot_cache_cleared');

            // Get information about which plugins were cleared
            $cleared_plugins = get_transient('wpiko_chatbot_cleared_plugins');
            delete_transient('wpiko_chatbot_cleared_plugins');

            // Generate appropriate message based on what was cleared
            $message = $this->generate_cache_notice_message($cleared_plugins);

            // Always show notice now that we provide helpful messages for all cases
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>WPiko Chatbot:</strong>
                    <?php echo wp_kses_post($message); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Show immediate cache notice during form submissions
     */
    public function show_immediate_cache_notice($cleared_plugins)
    {
        // Generate appropriate message based on what was cleared
        $message = $this->generate_cache_notice_message($cleared_plugins);

        // Show immediate success notice
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>WPiko Chatbot:</strong> ' . wp_kses_post($message) . '</p>';
        echo '</div>';
    }

    /**
     * Generate appropriate cache notice message
     */
    private function generate_cache_notice_message($cleared_plugins)
    {
        if (empty($cleared_plugins)) {
            // No caching plugins detected - still show a helpful message with read more link
            return 'No caching plugin detected. If you\'re using a caching plugin, you may need to clear your cache manually to see changes. <a href="https://wpiko.com/docs/chatbot/performance-and-optimization-chatbot/cache-busting-system/" target="_blank" rel="noopener">Read more</a>';
        } elseif (count($cleared_plugins) === 1) {
            // Single caching plugin
            return 'Cache automatically cleared for ' . esc_html($cleared_plugins[0]) . '. Your chatbot interface should now reflect the latest changes.';
        } else {
            // Multiple caching plugins
            $plugin_list = implode(', ', array_slice($cleared_plugins, 0, -1)) . ' and ' . end($cleared_plugins);
            return 'Cache automatically cleared for ' . esc_html($plugin_list) . '. Your chatbot interface should now reflect the latest changes.';
        }
    }

    /**
     * Get current cache version (public method)
     */
    public function get_cache_version()
    {
        return $this->cache_key_version;
    }
}

// Initialize the cache manager
function wpiko_chatbot_init_cache_manager()
{
    return WPiko_Chatbot_Cache_Manager::get_instance();
}
add_action('init', 'wpiko_chatbot_init_cache_manager');

// Helper functions for other parts of the plugin
function wpiko_chatbot_get_cache_version()
{
    $cache_manager = WPiko_Chatbot_Cache_Manager::get_instance();
    return $cache_manager->get_cache_version();
}

function wpiko_chatbot_get_detected_cache_plugins()
{
    $cache_manager = WPiko_Chatbot_Cache_Manager::get_instance();
    return $cache_manager->get_detected_cache_plugins();
}

function wpiko_chatbot_clear_file_cache()
{
    $cache_manager = WPiko_Chatbot_Cache_Manager::get_instance();
    return $cache_manager->clear_chatbot_cache();
}

<?php
/**
 * AI usage and cost tracking.
 *
 * Every billed OpenAI Responses API call made by the chatbot is recorded with
 * its token counts, file search calls and an estimated cost. No message text
 * is stored. Costs are calculated with the list prices below when the call is
 * made, so later price changes do not rewrite history.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WPIKO_CHATBOT_USAGE_SCHEMA', '1');
define('WPIKO_CHATBOT_PRICES_UPDATED', '2026-11-14');
define('WPIKO_CHATBOT_USAGE_RETENTION_DAYS', 400);
define('WPIKO_CHATBOT_RESPONSES_ENDPOINT', 'https://api.openai.com/v1/responses');

/**
 * Usage table name.
 *
 * @return string
 */
function wpiko_chatbot_usage_table()
{
    global $wpdb;
    return $wpdb->prefix . 'wpiko_chatbot_usage';
}

/**
 * Create the usage table on activation and on upgrades.
 *
 * @return void
 */
function wpiko_chatbot_install_usage_tracking()
{
    global $wpdb;

    if (get_option('wpiko_chatbot_usage_schema') !== WPIKO_CHATBOT_USAGE_SCHEMA) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = wpiko_chatbot_usage_table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            source varchar(40) NOT NULL DEFAULT '',
            model varchar(80) NOT NULL DEFAULT '',
            session_id varchar(255) NOT NULL DEFAULT '',
            response_id varchar(100) NOT NULL DEFAULT '',
            input_tokens int(10) unsigned NOT NULL DEFAULT 0,
            cached_tokens int(10) unsigned NOT NULL DEFAULT 0,
            output_tokens int(10) unsigned NOT NULL DEFAULT 0,
            reasoning_tokens int(10) unsigned NOT NULL DEFAULT 0,
            search_calls smallint(5) unsigned NOT NULL DEFAULT 0,
            token_cost decimal(16,8) NOT NULL DEFAULT 0,
            search_cost decimal(16,8) NOT NULL DEFAULT 0,
            priced tinyint(1) unsigned NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY source_created (source,created_at)
        ) $charset;");

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return; // Try again on the next request; chat keeps working without tracking.
        }

        // The day tracking started, shown on the Dashboard (earlier chats have no usage data).
        add_option('wpiko_chatbot_usage_since', current_time('mysql', true), '', false);
        update_option('wpiko_chatbot_usage_schema', WPIKO_CHATBOT_USAGE_SCHEMA);
    }

    if (!wp_next_scheduled('wpiko_chatbot_cleanup_usage')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'wpiko_chatbot_cleanup_usage');
    }
}
add_action('init', 'wpiko_chatbot_install_usage_tracking');

/**
 * Remove usage rows older than the retention period.
 *
 * @return void
 */
function wpiko_chatbot_cleanup_usage()
{
    global $wpdb;
    $days = (int) apply_filters('wpiko_chatbot_usage_retention_days', WPIKO_CHATBOT_USAGE_RETENTION_DAYS);
    if ($days < 62) {
        $days = 62; // Keep at least this month and last month for the Dashboard.
    }
    $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
    $table = wpiko_chatbot_usage_table();
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal table name.
    $wpdb->query($wpdb->prepare("DELETE FROM `$table` WHERE created_at < %s LIMIT 10000", $cutoff));
}
add_action('wpiko_chatbot_cleanup_usage', 'wpiko_chatbot_cleanup_usage');

/**
 * Stop the cleanup schedule when the plugin is deactivated.
 *
 * @return void
 */
function wpiko_chatbot_deactivate_usage_tracking()
{
    wp_clear_scheduled_hook('wpiko_chatbot_cleanup_usage');
}
register_deactivation_hook(WPIKO_CHATBOT_PLUGIN_FILE, 'wpiko_chatbot_deactivate_usage_tracking');

/* -------------------------------------------------------------------------
 * Prices
 * ---------------------------------------------------------------------- */

/**
 * OpenAI Standard list prices in USD per 1M tokens.
 *
 * Checked on WPIKO_CHATBOT_PRICES_UPDATED against https://developers.openai.com/api/docs/pricing
 * and the model pages. A `promo` entry applies until (and including) its `until`
 * date (UTC). Long-context prices (very large prompts) are not used by the chatbot.
 * Cache writes replace ordinary input pricing for the written tokens; they are
 * not an extra charge on top of input. Models without write rates use input rates.
 *
 * @return array<string, array<string, mixed>>
 */
function wpiko_chatbot_get_model_prices()
{
    $prices = array(
        'gpt-6-astra' => array('input' => 10.00, 'cached' => 1.00, 'output' => 50.00, 'write_30m' => 12.50, 'write_12h' => 25.00),
        'gpt-6.1-sol' => array('input' => 2.00, 'cached' => 0.10, 'output' => 10.00, 'write_30m' => 2.50, 'write_12h' => 5.00),
        'gpt-6.1-terra' => array('input' => 1.00, 'cached' => 0.05, 'output' => 5.00, 'write_30m' => 1.25, 'write_12h' => 2.50),
        'gpt-6-sol' => array('input' => 2.00, 'cached' => 0.20, 'output' => 10.00, 'write_30m' => 2.50, 'write_12h' => 5.00),
        'gpt-6-luna' => array('input' => 0.10, 'cached' => 0.01, 'output' => 0.50, 'write_30m' => 0.125, 'write_12h' => 0.25),
        'gpt-5.6-sol' => array(
            'input' => 5.00,
            'cached' => 0.50,
            'output' => 30.00,
            'write_30m' => 6.25,
            'write_12h' => 12.50,
            'promo' => array('until' => '2026-11-30', 'input' => 4.00, 'cached' => 0.40, 'output' => 20.00, 'write_30m' => 5.00, 'write_12h' => 10.00),
        ),
        'gpt-5.6-terra' => array('input' => 2.00, 'cached' => 0.20, 'output' => 12.00, 'write_30m' => 2.50, 'write_12h' => 5.00),
        'gpt-5.6-luna' => array('input' => 0.20, 'cached' => 0.02, 'output' => 1.20, 'write_30m' => 0.25, 'write_12h' => 0.50),
        'gpt-5.5' => array('input' => 5.00, 'cached' => 0.50, 'output' => 30.00),
        'gpt-5.4' => array('input' => 2.50, 'cached' => 0.25, 'output' => 15.00),
        'gpt-5.4-mini' => array('input' => 0.75, 'cached' => 0.075, 'output' => 4.50),
        'gpt-5.2' => array('input' => 1.75, 'cached' => 0.175, 'output' => 14.00),
        'gpt-4.1' => array('input' => 2.00, 'cached' => 0.50, 'output' => 8.00),
        'gpt-4.1-mini' => array('input' => 0.40, 'cached' => 0.10, 'output' => 1.60),
    );

    /**
     * Filter the model prices used for cost estimates (USD per 1M tokens).
     *
     * @param array $prices Model ID => array( input, cached, output [, write_30m, write_12h, promo] ).
     */
    $prices = apply_filters('wpiko_chatbot_model_prices', $prices);
    return is_array($prices) ? $prices : array();
}

/**
 * Price of one file search tool call in USD ($2.50 per 1,000 calls).
 *
 * @return float
 */
function wpiko_chatbot_file_search_call_price()
{
    return max(0, (float) apply_filters('wpiko_chatbot_file_search_call_price', 0.0025));
}

/**
 * Find the price entry for a model ID, including dated snapshots
 * such as "gpt-6-luna-2026-09-22".
 *
 * @param string $model Model ID.
 * @return string Matching price key, or '' when unknown.
 */
function wpiko_chatbot_usage_price_key($model)
{
    $model = strtolower(trim((string) $model));
    if ($model === '') {
        return '';
    }
    $prices = wpiko_chatbot_get_model_prices();
    if (isset($prices[$model])) {
        return $model;
    }
    $match = '';
    foreach (array_keys($prices) as $key) {
        $key = (string) $key;
        if (strpos($model, $key . '-') === 0 && strlen($key) > strlen($match)) {
            $match = $key;
        }
    }
    return $match;
}

/**
 * Rates for a model on a given day.
 *
 * @param string   $model Model ID.
 * @param int|null $time  Unix time (default now).
 * @return array{input: float, cached: float, output: float, write_30m: float, write_12h: float}|null
 */
function wpiko_chatbot_usage_model_rates($model, $time = null)
{
    $key = wpiko_chatbot_usage_price_key($model);
    if ($key === '') {
        return null;
    }
    $prices = wpiko_chatbot_get_model_prices();
    $entry = $prices[$key];
    if (!is_array($entry)) {
        return null;
    }
    $day = gmdate('Y-m-d', $time === null ? time() : (int) $time);
    if (!empty($entry['promo']['until']) && $day <= $entry['promo']['until']) {
        $entry = array_merge($entry, $entry['promo']);
    }
    if (!isset($entry['input'], $entry['output'])) {
        return null;
    }
    return array(
        'input' => (float) $entry['input'],
        'cached' => isset($entry['cached']) ? (float) $entry['cached'] : (float) $entry['input'],
        'output' => (float) $entry['output'],
        'write_30m' => isset($entry['write_30m']) ? (float) $entry['write_30m'] : (float) $entry['input'],
        'write_12h' => isset($entry['write_12h']) ? (float) $entry['write_12h'] : (float) $entry['input'],
    );
}

/**
 * Estimated cost of one API call.
 *
 * @param string   $model        Model ID.
 * @param array    $tokens       input, cached, output and optional write_30m/write_12h token counts.
 * @param int      $search_calls File search calls.
 * @param int|null $time         Unix time of the call.
 * @return array{token_cost: float, search_cost: float, priced: bool}
 */
function wpiko_chatbot_usage_calculate_cost($model, $tokens, $search_calls = 0, $time = null)
{
    $rates = wpiko_chatbot_usage_model_rates($model, $time);
    $token_cost = 0.0;
    if ($rates !== null) {
        $input = max(0, (int) ($tokens['input'] ?? 0));
        $cached = min($input, max(0, (int) ($tokens['cached'] ?? 0)));
        $write_12h = min($input - $cached, max(0, (int) ($tokens['write_12h'] ?? 0)));
        $write_30m = min($input - $cached - $write_12h, max(0, (int) ($tokens['write_30m'] ?? 0)));
        $ordinary = $input - $cached - $write_30m - $write_12h;
        $output = max(0, (int) ($tokens['output'] ?? 0));
        $token_cost = (
            $ordinary * $rates['input']
            + $cached * $rates['cached']
            + $write_30m * $rates['write_30m']
            + $write_12h * $rates['write_12h']
            + $output * $rates['output']
        ) / 1000000;
    }
    return array(
        'token_cost' => $token_cost,
        'search_cost' => max(0, (int) $search_calls) * wpiko_chatbot_file_search_call_price(),
        'priced' => $rates !== null,
    );
}

/* -------------------------------------------------------------------------
 * Recording
 * ---------------------------------------------------------------------- */

/**
 * Get or replace the current usage context (what the next API calls are for).
 *
 * Call with no argument to read it. Call with an array (source, session_id)
 * or null to replace it; the previous context is returned.
 *
 * @param array|null $context New context.
 * @return array|null
 */
function wpiko_chatbot_usage_context($context = null)
{
    static $current = null;
    if (func_num_args() > 0) {
        $previous = $current;
        $current = is_array($context) ? $context : null;
        return $previous;
    }
    return $current;
}

/**
 * Start attributing OpenAI calls to a source.
 *
 * @param string $source     Source key (chat, translation, qa_generation...).
 * @param string $session_id Chat session, if any.
 * @return array|null Previous context, for wpiko_chatbot_usage_end().
 */
function wpiko_chatbot_usage_begin($source, $session_id = '')
{
    return wpiko_chatbot_usage_context(array(
        'source' => sanitize_key((string) $source),
        'session_id' => (string) $session_id,
    ));
}

/**
 * Restore the context that was active before wpiko_chatbot_usage_begin().
 *
 * @param array|null $previous Previous context.
 * @return void
 */
function wpiko_chatbot_usage_end($previous)
{
    wpiko_chatbot_usage_context($previous);
}

/**
 * Read the billed usage from a Responses API response object.
 *
 * @param mixed $response Decoded response.
 * @return array|null
 */
function wpiko_chatbot_usage_from_response($response)
{
    if (!is_array($response) || empty($response['usage']) || !is_array($response['usage'])) {
        return null;
    }
    $usage = $response['usage'];
    $input = absint($usage['input_tokens'] ?? 0);
    $input_details = isset($usage['input_tokens_details']) && is_array($usage['input_tokens_details']) ? $usage['input_tokens_details'] : array();
    $write_details = isset($input_details['cache_write_tokens_details']) && is_array($input_details['cache_write_tokens_details']) ? $input_details['cache_write_tokens_details'] : array();
    $cached = min($input, max(0, (int) ($input_details['cached_tokens'] ?? 0)));
    $write_12h = min($input - $cached, max(0, (int) ($write_details['ttl_12h_tokens'] ?? 0)));
    // Older responses may report only total writes. Our requests use the default 30m lifetime.
    $write_30m = isset($write_details['ttl_30m_tokens'])
        ? max(0, (int) $write_details['ttl_30m_tokens'])
        : max(0, (int) ($input_details['cache_write_tokens'] ?? 0) - $write_12h);
    $write_30m = min($input - $cached - $write_12h, $write_30m);
    $search_calls = 0;
    $output_items = isset($response['output']) && is_array($response['output']) ? $response['output'] : array();
    foreach ($output_items as $item) {
        if (is_array($item) && ($item['type'] ?? '') === 'file_search_call') {
            $search_calls++;
        }
    }
    return array(
        'model' => isset($response['model']) && is_string($response['model']) ? $response['model'] : '',
        'response_id' => isset($response['id']) && is_string($response['id']) ? $response['id'] : '',
        'input' => $input,
        'cached' => $cached,
        'write_30m' => $write_30m,
        'write_12h' => $write_12h,
        'output' => absint($usage['output_tokens'] ?? 0),
        'reasoning' => absint($usage['output_tokens_details']['reasoning_tokens'] ?? 0),
        'search_calls' => $search_calls,
    );
}

/**
 * Record the usage of one Responses API call for the current context.
 *
 * Never throws: tracking must not break a chat reply.
 *
 * @param mixed      $response       Decoded Responses API response.
 * @param array|null $context        Context (default: current context).
 * @param string     $fallback_model Model from the request, if the response has none.
 * @return bool Whether a row was saved.
 */
function wpiko_chatbot_record_usage($response, $context = null, $fallback_model = '')
{
    try {
        if ($context === null) {
            $context = wpiko_chatbot_usage_context();
        }
        if (!is_array($context) || empty($context['source'])) {
            return false;
        }
        $usage = wpiko_chatbot_usage_from_response($response);
        if ($usage === null || ($usage['input'] + $usage['output'] + $usage['search_calls']) === 0) {
            return false;
        }

        $model = $usage['model'] !== '' ? $usage['model'] : (string) $fallback_model;
        $model = substr(preg_replace('/[^A-Za-z0-9._:-]/', '', $model), 0, 80);
        $cost = wpiko_chatbot_usage_calculate_cost($model, $usage, $usage['search_calls']);

        // The saved token cost includes cache writes. Existing reports sum this cost;
        // input_tokens still includes all input categories, so no schema change is needed.
        $row = array(
            'created_at' => gmdate('Y-m-d H:i:s'),
            'source' => substr((string) $context['source'], 0, 40),
            'model' => $model,
            'session_id' => substr((string) ($context['session_id'] ?? ''), 0, 255),
            'response_id' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $usage['response_id']), 0, 100),
            'input_tokens' => $usage['input'],
            'cached_tokens' => $usage['cached'],
            'output_tokens' => $usage['output'],
            'reasoning_tokens' => $usage['reasoning'],
            'search_calls' => min(65535, $usage['search_calls']),
            'token_cost' => round($cost['token_cost'], 8),
            'search_cost' => round($cost['search_cost'], 8),
            'priced' => $cost['priced'] ? 1 : 0,
        );

        global $wpdb;
        $suppressed = $wpdb->suppress_errors(true);
        $saved = $wpdb->insert(
            wpiko_chatbot_usage_table(),
            $row,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%f', '%f', '%d')
        );
        $wpdb->suppress_errors($suppressed);

        if ($saved) {
            /**
             * Fires after the usage of one OpenAI call was recorded.
             *
             * @param array $row Saved row (no message text).
             */
            do_action('wpiko_chatbot_usage_recorded', $row);
        }
        return (bool) $saved;
    } catch (Throwable $error) {
        if (function_exists('wpiko_chatbot_log')) {
            wpiko_chatbot_log('Usage tracking failed: ' . $error->getMessage(), 'warning');
        }
        return false;
    }
}

/**
 * Record usage for every non-streaming Responses API request the plugin makes
 * (chat replies, retries, tool follow-ups, utility features, connection tests).
 *
 * Only requests made while a usage context is active are recorded, so other
 * plugins calling OpenAI on the same site are never counted.
 *
 * @param array|WP_Error $response HTTP response.
 * @param string         $context  Hook context.
 * @param string         $class    Transport class.
 * @param array          $args     Request arguments.
 * @param string         $url      Request URL.
 * @return void
 */
function wpiko_chatbot_usage_capture_http_response($response, $context, $class, $args, $url)
{
    if ($context !== 'response' || !is_array($response) || $url !== WPIKO_CHATBOT_RESPONSES_ENDPOINT) {
        return;
    }
    if (!is_array($args) || strtoupper((string) ($args['method'] ?? '')) !== 'POST' || wpiko_chatbot_usage_context() === null) {
        return;
    }
    $request = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'], true) : null;
    if (is_array($request) && !empty($request['stream'])) {
        return; // Streamed replies are recorded by the stream parser.
    }
    $body = json_decode((string) wp_remote_retrieve_body($response), true);
    wpiko_chatbot_record_usage($body, null, is_array($request) && isset($request['model']) ? (string) $request['model'] : '');
}
add_action('http_api_debug', 'wpiko_chatbot_usage_capture_http_response', 10, 5);

/* -------------------------------------------------------------------------
 * Reporting
 * ---------------------------------------------------------------------- */

/**
 * Whether the Pro add-on is active (its features' costs are only counted then).
 *
 * @return bool
 */
function wpiko_chatbot_usage_pro_active()
{
    return defined('WPIKO_CHATBOT_PRO_VERSION');
}

/**
 * Sources included in the totals, with their labels.
 *
 * Pro features are listed only while WPiko AI Chatbot Pro is active.
 *
 * @return array<string, string>
 */
function wpiko_chatbot_usage_sources()
{
    $sources = array(
        'chat' => __('Chat replies', 'wpiko-chatbot'),
    );
    if (wpiko_chatbot_usage_pro_active()) {
        $sources['chat_tools'] = __('Order and product lookups', 'wpiko-chatbot');
        $sources['qa_generation'] = __('Website scan (Q&A)', 'wpiko-chatbot');
        $sources['contact_enhancement'] = __('Contact form writing help', 'wpiko-chatbot');
    }
    $sources['translation'] = __('Conversation translations', 'wpiko-chatbot');
    $sources['connection_test'] = __('Connection tests', 'wpiko-chatbot');

    /**
     * Filter the usage sources counted on the Dashboard.
     *
     * @param array $sources Source key => label.
     */
    $sources = apply_filters('wpiko_chatbot_usage_sources', $sources);
    return is_array($sources) ? $sources : array();
}

/**
 * Sources that belong to a visitor conversation (used for per-conversation averages).
 *
 * @return string[]
 */
function wpiko_chatbot_usage_conversation_sources()
{
    return array_values(array_intersect(array('chat', 'chat_tools'), array_keys(wpiko_chatbot_usage_sources())));
}

/**
 * Totals for a period (GMT, start inclusive, end exclusive).
 *
 * @param string $start_gmt Y-m-d H:i:s.
 * @param string $end_gmt   Y-m-d H:i:s.
 * @return array
 */
function wpiko_chatbot_usage_totals($start_gmt, $end_gmt)
{
    global $wpdb;
    $table = wpiko_chatbot_usage_table();
    $sources = wpiko_chatbot_usage_sources();
    $totals = array(
        'cost' => 0.0,
        'token_cost' => 0.0,
        'search_cost' => 0.0,
        'calls' => 0,
        'input' => 0,
        'cached' => 0,
        'output' => 0,
        'reasoning' => 0,
        'search_calls' => 0,
        'unpriced' => 0,
        'replies' => 0,
        'conversations' => 0,
        'conversation_cost' => 0.0,
        'sources' => array(),
    );
    if (empty($sources)) {
        return $totals;
    }

    $source_keys = array_keys($sources);
    $placeholders = implode(',', array_fill(0, count($source_keys), '%s'));
    $suppressed = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are internal.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT source, COUNT(*) AS calls, SUM(input_tokens) AS input, SUM(cached_tokens) AS cached,
            SUM(output_tokens) AS output, SUM(reasoning_tokens) AS reasoning, SUM(search_calls) AS search_calls,
            SUM(token_cost) AS token_cost, SUM(search_cost) AS search_cost, SUM(priced = 0) AS unpriced
         FROM `$table` WHERE created_at >= %s AND created_at < %s AND source IN ($placeholders)
         GROUP BY source",
        array_merge(array($start_gmt, $end_gmt), $source_keys)
    ), ARRAY_A);

    $conversation_sources = wpiko_chatbot_usage_conversation_sources();
    if (!empty($conversation_sources)) {
        $conversation_placeholders = implode(',', array_fill(0, count($conversation_sources), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are internal.
        $totals['conversations'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM `$table`
             WHERE created_at >= %s AND created_at < %s AND session_id <> '' AND source IN ($conversation_placeholders)",
            array_merge(array($start_gmt, $end_gmt), $conversation_sources)
        ));
    }
    $wpdb->suppress_errors($suppressed);

    foreach ((array) $rows as $row) {
        $source = $row['source'];
        if (!isset($sources[$source])) {
            continue;
        }
        $item = array(
            'label' => $sources[$source],
            'calls' => (int) $row['calls'],
            'input' => (int) $row['input'],
            'cached' => (int) $row['cached'],
            'output' => (int) $row['output'],
            'reasoning' => (int) $row['reasoning'],
            'search_calls' => (int) $row['search_calls'],
            'token_cost' => (float) $row['token_cost'],
            'search_cost' => (float) $row['search_cost'],
            'unpriced' => (int) $row['unpriced'],
        );
        $item['cost'] = $item['token_cost'] + $item['search_cost'];
        $totals['sources'][$source] = $item;

        foreach (array('calls', 'input', 'cached', 'output', 'reasoning', 'search_calls', 'unpriced') as $key) {
            $totals[$key] += $item[$key];
        }
        $totals['token_cost'] += $item['token_cost'];
        $totals['search_cost'] += $item['search_cost'];
        $totals['cost'] += $item['cost'];
        if (in_array($source, $conversation_sources, true)) {
            $totals['conversation_cost'] += $item['cost'];
        }
    }
    $totals['replies'] = isset($totals['sources']['chat']) ? $totals['sources']['chat']['calls'] : 0;

    // Keep the order of wpiko_chatbot_usage_sources().
    $ordered = array();
    foreach ($source_keys as $key) {
        if (isset($totals['sources'][$key])) {
            $ordered[$key] = $totals['sources'][$key];
        }
    }
    $totals['sources'] = $ordered;

    return $totals;
}

/**
 * Cost per model for a period (included sources only).
 *
 * @param string $start_gmt Y-m-d H:i:s.
 * @param string $end_gmt   Y-m-d H:i:s.
 * @return array<string, float> Model => cost, highest first.
 */
function wpiko_chatbot_usage_costs_by_model($start_gmt, $end_gmt)
{
    global $wpdb;
    $table = wpiko_chatbot_usage_table();
    $source_keys = array_keys(wpiko_chatbot_usage_sources());
    if (empty($source_keys)) {
        return array();
    }
    $placeholders = implode(',', array_fill(0, count($source_keys), '%s'));
    $suppressed = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and placeholders are internal.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT model, SUM(token_cost + search_cost) AS cost FROM `$table`
         WHERE created_at >= %s AND created_at < %s AND source IN ($placeholders)
         GROUP BY model ORDER BY cost DESC",
        array_merge(array($start_gmt, $end_gmt), $source_keys)
    ), ARRAY_A);
    $wpdb->suppress_errors($suppressed);

    $costs = array();
    foreach ((array) $rows as $row) {
        $costs[$row['model'] !== '' ? $row['model'] : __('Unknown model', 'wpiko-chatbot')] = (float) $row['cost'];
    }
    return $costs;
}

/**
 * Convert a site-time date to a GMT MySQL datetime.
 *
 * @param DateTimeImmutable $date Date.
 * @return string
 */
function wpiko_chatbot_usage_gmt($date)
{
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/**
 * Everything the Dashboard widget shows.
 *
 * @param DateTimeImmutable|null $now Current time (for tests).
 * @return array
 */
function wpiko_chatbot_get_usage_snapshot($now = null)
{
    $timezone = wp_timezone();
    $now = $now instanceof DateTimeImmutable ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
    $today = $now->setTime(0, 0, 0);
    $tomorrow = $today->modify('+1 day');
    $week_start = $today->modify('-6 days');
    $month_start = $today->modify('first day of this month');
    $next_month = $month_start->modify('+1 month');
    $last_month_start = $month_start->modify('-1 month');

    $since_gmt = (string) get_option('wpiko_chatbot_usage_since', '');
    try {
        $since = $since_gmt !== ''
            ? (new DateTimeImmutable($since_gmt, new DateTimeZone('UTC')))->setTimezone($timezone)
            : $now;
    } catch (Exception $error) {
        $since = $now;
    }

    $snapshot = array(
        'since' => $since,
        'today' => wpiko_chatbot_usage_totals(wpiko_chatbot_usage_gmt($today), wpiko_chatbot_usage_gmt($tomorrow)),
        'week' => wpiko_chatbot_usage_totals(wpiko_chatbot_usage_gmt($week_start), wpiko_chatbot_usage_gmt($tomorrow)),
        'month' => wpiko_chatbot_usage_totals(wpiko_chatbot_usage_gmt($month_start), wpiko_chatbot_usage_gmt($next_month)),
        'last_month' => null,
        'projected' => null,
        // Some of the periods shown started before tracking did (true for about the first two months).
        'partial' => $since > $last_month_start,
        'models' => wpiko_chatbot_usage_costs_by_model(wpiko_chatbot_usage_gmt($month_start), wpiko_chatbot_usage_gmt($next_month)),
        'month_label' => wp_date('F Y', $now->getTimestamp(), $timezone),
        'last_month_label' => wp_date('F Y', $last_month_start->getTimestamp(), $timezone),
    );

    // Last month is only meaningful when tracking was already running then.
    if ($since < $month_start) {
        $snapshot['last_month'] = wpiko_chatbot_usage_totals(wpiko_chatbot_usage_gmt($last_month_start), wpiko_chatbot_usage_gmt($month_start));
    }

    // Projection: the daily rate so far, for the rest of the month (after 3 days of data).
    $tracked_from = $since > $month_start ? $since : $month_start;
    $elapsed_days = ($now->getTimestamp() - $tracked_from->getTimestamp()) / DAY_IN_SECONDS;
    $remaining_days = max(0, ($next_month->getTimestamp() - $now->getTimestamp()) / DAY_IN_SECONDS);
    if ($elapsed_days >= 3 && $snapshot['month']['cost'] > 0) {
        $snapshot['projected'] = $snapshot['month']['cost'] + ($snapshot['month']['cost'] / $elapsed_days) * $remaining_days;
    }

    return $snapshot;
}

/**
 * Whether the site has chat messages from before usage tracking started.
 *
 * Used to explain that those earlier chats are not in the totals. New sites
 * have none, so they never see that note.
 *
 * @param DateTimeImmutable $since Tracking start.
 * @return bool
 */
function wpiko_chatbot_usage_has_earlier_chats($since)
{
    global $wpdb;
    $table = $wpdb->prefix . 'wpiko_chatbot_conversations';
    // The conversations table stores database server time; compare in site time, which is close enough here.
    $before = $since->format('Y-m-d H:i:s');
    $suppressed = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table name; one cheap lookup on the Dashboard during the first two months.
    $found = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM `$table` WHERE role = 'assistant' AND timestamp < %s LIMIT 1", $before));
    $wpdb->suppress_errors($suppressed);
    return (string) $found === '1';
}

/**
 * Estimated cost of each of the given conversations (chat replies, knowledge
 * search and, with Pro, order/product lookups). Conversations without a
 * recorded cost are left out.
 *
 * @param string[] $session_ids Conversation session IDs.
 * @return array<string, float> Session ID => cost.
 */
function wpiko_chatbot_usage_costs_by_session($session_ids)
{
    global $wpdb;
    $session_ids = array_values(array_unique(array_filter(array_map('strval', (array) $session_ids), 'strlen')));
    $sources = wpiko_chatbot_usage_conversation_sources();
    if (empty($session_ids) || empty($sources)) {
        return array();
    }

    $table = wpiko_chatbot_usage_table();
    $session_placeholders = implode(',', array_fill(0, count($session_ids), '%s'));
    $source_placeholders = implode(',', array_fill(0, count($sources), '%s'));
    $suppressed = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name and placeholders are internal; one query per inbox page.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT session_id, SUM(token_cost + search_cost) AS cost FROM `$table`
         WHERE session_id IN ($session_placeholders) AND source IN ($source_placeholders)
         GROUP BY session_id",
        array_merge($session_ids, $sources)
    ), ARRAY_A);
    $wpdb->suppress_errors($suppressed);

    $costs = array();
    foreach ((array) $rows as $row) {
        $costs[(string) $row['session_id']] = (float) $row['cost'];
    }
    return $costs;
}

/**
 * Everything recorded for one conversation, or null when nothing was recorded.
 *
 * @param string $session_id Conversation session ID.
 * @return array|null
 */
function wpiko_chatbot_usage_conversation_totals($session_id)
{
    global $wpdb;
    $session_id = (string) $session_id;
    $sources = wpiko_chatbot_usage_conversation_sources();
    if ($session_id === '' || empty($sources)) {
        return null;
    }

    $table = wpiko_chatbot_usage_table();
    $placeholders = implode(',', array_fill(0, count($sources), '%s'));
    $suppressed = $wpdb->suppress_errors(true);
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name and placeholders are internal; one conversation.
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT source, model, COUNT(*) AS calls, SUM(search_calls) AS search_calls, SUM(input_tokens) AS input,
            SUM(output_tokens) AS output, SUM(token_cost) AS token_cost, SUM(search_cost) AS search_cost, SUM(priced = 0) AS unpriced
         FROM `$table` WHERE session_id = %s AND source IN ($placeholders)
         GROUP BY source, model",
        array_merge(array($session_id), $sources)
    ), ARRAY_A);
    $wpdb->suppress_errors($suppressed);

    if (empty($rows)) {
        return null;
    }

    $totals = array(
        'cost' => 0.0,
        'search_cost' => 0.0,
        'replies' => 0,
        'lookups' => 0,
        'searches' => 0,
        'input' => 0,
        'output' => 0,
        'unpriced' => 0,
        'models' => array(),
    );
    foreach ($rows as $row) {
        $calls = (int) $row['calls'];
        $cost = (float) $row['token_cost'] + (float) $row['search_cost'];
        if ($row['source'] === 'chat') {
            $totals['replies'] += $calls;
        } else {
            $totals['lookups'] += $calls;
        }
        $totals['cost'] += $cost;
        $totals['search_cost'] += (float) $row['search_cost'];
        $totals['searches'] += (int) $row['search_calls'];
        $totals['input'] += (int) $row['input'];
        $totals['output'] += (int) $row['output'];
        $totals['unpriced'] += (int) $row['unpriced'];
        if ($row['model'] !== '') {
            $totals['models'][$row['model']] = ($totals['models'][$row['model']] ?? 0) + $cost;
        }
    }
    return $totals;
}

/**
 * The "AI cost" block of a conversation's Details panel.
 *
 * @param string      $session_id         Conversation session ID.
 * @param string|null $first_message_time Site-time timestamp of the conversation's first message.
 * @return array{total: string, rows: array<int, array{label: string, value: string}>, note: string}
 */
function wpiko_chatbot_usage_conversation_details($session_id, $first_message_time = null)
{
    $totals = wpiko_chatbot_usage_conversation_totals($session_id);
    $since_gmt = (string) get_option('wpiko_chatbot_usage_since', '');
    $since = null;
    if ($since_gmt !== '') {
        try {
            $since = (new DateTimeImmutable($since_gmt, new DateTimeZone('UTC')))->setTimezone(wp_timezone());
        } catch (Exception $error) {
            $since = null;
        }
    }
    $since_label = $since ? wp_date(get_option('date_format', 'F j, Y'), $since->getTimestamp()) : '';
    // The conversations table stores site time.
    $started_before_tracking = $since && $first_message_time && $first_message_time < $since->format('Y-m-d H:i:s');

    if ($totals === null) {
        return array(
            'total' => $started_before_tracking ? __('Not tracked', 'wpiko-chatbot') : wpiko_chatbot_format_usage_cost(0),
            'rows' => array(),
            'note' => $started_before_tracking
                /* translators: %s: date cost tracking started. */
                ? sprintf(__('Costs are counted from %s.', 'wpiko-chatbot'), $since_label)
                : __('No AI cost recorded for this conversation.', 'wpiko-chatbot'),
        );
    }

    $rows = array(
        array(
            'label' => __('AI replies', 'wpiko-chatbot'),
            'value' => number_format_i18n($totals['replies']),
        ),
    );
    if ($totals['searches'] > 0) {
        $rows[] = array(
            'label' => __('Knowledge search', 'wpiko-chatbot'),
            'value' => sprintf(
                /* translators: 1: number of knowledge searches, 2: their cost. */
                __('%1$s (%2$s)', 'wpiko-chatbot'),
                number_format_i18n($totals['searches']),
                wpiko_chatbot_format_usage_cost($totals['search_cost'], true)
            ),
        );
    }
    if ($totals['lookups'] > 0) {
        $rows[] = array(
            'label' => __('Order and product lookups', 'wpiko-chatbot'),
            'value' => number_format_i18n($totals['lookups']),
        );
    }
    $rows[] = array(
        'label' => __('Tokens', 'wpiko-chatbot'),
        'value' => sprintf(
            /* translators: 1: input tokens, 2: output tokens. */
            __('%1$s in, %2$s out', 'wpiko-chatbot'),
            number_format_i18n($totals['input']),
            number_format_i18n($totals['output'])
        ),
    );
    if (!empty($totals['models'])) {
        $models = function_exists('wpiko_chatbot_usage_model_labels')
            ? array_keys(wpiko_chatbot_usage_model_labels($totals['models']))
            : array_keys($totals['models']);
        $rows[] = array(
            'label' => _n('Model', 'Models', count($models), 'wpiko-chatbot'),
            'value' => implode(', ', $models),
        );
    }

    // The "Estimate" badge already says it's an estimate; the note is only for exceptions.
    $notes = array();
    if ($started_before_tracking) {
        /* translators: %s: date cost tracking started. */
        $notes[] = sprintf(__('Only replies from %s are counted.', 'wpiko-chatbot'), $since_label);
    }
    if ($totals['unpriced'] > 0) {
        $notes[] = __('Some replies used a model without a known price and are not included.', 'wpiko-chatbot');
    }
    $note = implode(' ', $notes);

    return array(
        'total' => wpiko_chatbot_format_usage_cost($totals['cost'], true),
        'rows' => $rows,
        'note' => $note,
    );
}

/**
 * Average cost of one chat reply over the last N days (including knowledge
 * search and lookups), or null without data.
 *
 * @param int $days Days.
 * @return float|null
 */
function wpiko_chatbot_usage_average_reply_cost($days = 30)
{
    $end = gmdate('Y-m-d H:i:s', time() + MINUTE_IN_SECONDS);
    $start = gmdate('Y-m-d H:i:s', time() - max(1, (int) $days) * DAY_IN_SECONDS);
    $totals = wpiko_chatbot_usage_totals($start, $end);
    if ($totals['replies'] < 1) {
        return null;
    }
    return $totals['conversation_cost'] / $totals['replies'];
}

/**
 * Format a USD amount. Amounts under one cent keep up to four decimals, or
 * two significant digits when $precise is set (for averages such as the cost
 * of one reply, which can be a fraction of a hundredth of a cent).
 *
 * @param float $usd     Amount.
 * @param bool  $precise Show tiny amounts with two significant digits.
 * @return string
 */
function wpiko_chatbot_format_usage_cost($usd, $precise = false)
{
    $usd = (float) $usd;
    if ($usd <= 0) {
        return '$' . number_format_i18n(0, 2);
    }
    if ($usd >= 0.01) {
        return '$' . number_format_i18n($usd, 2);
    }
    $decimals = $precise ? min(8, max(4, (int) ceil(-log10($usd)) + 1)) : 4;
    if (round($usd, $decimals) <= 0) {
        return '< $' . number_format_i18n(pow(10, -$decimals), $decimals);
    }
    global $wp_locale;
    $decimal = isset($wp_locale->number_format['decimal_point']) ? $wp_locale->number_format['decimal_point'] : '.';
    return '$' . rtrim(rtrim(number_format_i18n($usd, $decimals), '0'), $decimal);
}

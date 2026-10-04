<?php
/**
 * OpenAI connection checks and error classification.
 *
 * Turns raw OpenAI responses into clear, actionable messages for site admins
 * (for example "your OpenAI account has no credit") while visitors keep seeing
 * the friendly, customizable error messages.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Useful OpenAI dashboard links.
 *
 * @param string $which One of billing, keys, limits, verification.
 * @return string URL.
 */
function wpiko_chatbot_openai_url($which)
{
    $urls = array(
        'billing' => 'https://platform.openai.com/settings/organization/billing/overview',
        'keys' => 'https://platform.openai.com/api-keys',
        'limits' => 'https://platform.openai.com/settings/organization/limits',
        'verification' => 'https://platform.openai.com/settings/organization/general',
        'status' => 'https://status.openai.com/',
    );

    return isset($urls[$which]) ? $urls[$which] : 'https://platform.openai.com/';
}

/**
 * Classify an OpenAI API failure.
 *
 * @param int          $status_code     HTTP status code (0 when the request never completed).
 * @param array|string $body            Decoded JSON body, raw body string, or null.
 * @param string       $transport_error Network/cURL error message, if any.
 * @param string       $model           Model used for the request, for clearer messages.
 * @return array{
 *     code: string,
 *     error_key: string,
 *     admin_message: string,
 *     admin_link: string,
 *     admin_link_label: string,
 *     key_valid: bool|null,
 *     account_problem: bool,
 *     openai_message: string
 * }
 */
function wpiko_chatbot_classify_openai_error($status_code, $body = null, $transport_error = '', $model = '')
{
    $status_code = (int) $status_code;

    if (is_string($body) && $body !== '') {
        $decoded = json_decode($body, true);
        $body = is_array($decoded) ? $decoded : array('error' => array('message' => $body));
    }
    if (!is_array($body)) {
        $body = array();
    }

    $error = isset($body['error']) && is_array($body['error']) ? $body['error'] : array();
    $openai_message = isset($error['message']) && is_string($error['message']) ? $error['message'] : '';
    $openai_code = isset($error['code']) && is_string($error['code']) ? $error['code'] : '';
    $openai_type = isset($error['type']) && is_string($error['type']) ? $error['type'] : '';
    $haystack = strtolower($openai_message . ' ' . $openai_code . ' ' . $openai_type);

    $result = array(
        'code' => 'unknown',
        'error_key' => 'Response generation failed',
        'admin_message' => '',
        'admin_link' => '',
        'admin_link_label' => '',
        'key_valid' => null,
        'account_problem' => false,
        'openai_message' => $openai_message,
    );

    $model_label = $model !== '' ? $model : __('the selected model', 'wpiko-chatbot');

    if ($transport_error !== '' && $status_code === 0) {
        $is_timeout = stripos($transport_error, 'timed out') !== false || stripos($transport_error, 'cURL error 28') !== false;
        $result['code'] = $is_timeout ? 'timeout' : 'network';
        $result['error_key'] = $is_timeout ? 'Response timeout' : 'Failed to call Responses API';
        $result['admin_message'] = $is_timeout
            /* translators: %s: technical error message. */
            ? sprintf(__('OpenAI took too long to answer (%s). This is usually temporary. If it keeps happening, choose a lower reasoning effort or a faster model in AI Configuration.', 'wpiko-chatbot'), $transport_error)
            /* translators: %s: technical error message. */
            : sprintf(__('Your server could not connect to api.openai.com (%s). Ask your hosting provider to allow outgoing HTTPS connections to api.openai.com.', 'wpiko-chatbot'), $transport_error);
        return $result;
    }

    $is_quota = $openai_code === 'insufficient_quota'
        || $openai_type === 'insufficient_quota'
        || strpos($haystack, 'exceeded your current quota') !== false
        || strpos($haystack, 'billing') !== false;

    $is_model_access = $openai_code === 'model_not_found'
        || strpos($haystack, 'does not have access to model') !== false
        || strpos($haystack, 'must be verified') !== false
        || strpos($haystack, 'organization verification') !== false;

    if ($status_code === 401) {
        $result['code'] = 'invalid_key';
        $result['error_key'] = 'OpenAI account problem';
        $result['key_valid'] = false;
        $result['account_problem'] = true;
        $result['admin_message'] = __('OpenAI rejected the API key. It may have been mistyped, deleted, or revoked, or it belongs to a project that was archived. Create a new secret key in your OpenAI dashboard and save it in WPiko Chatbot → API Key.', 'wpiko-chatbot');
        $result['admin_link'] = wpiko_chatbot_openai_url('keys');
        $result['admin_link_label'] = __('Open OpenAI API keys', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code === 429 && $is_quota) {
        $result['code'] = 'no_credit';
        $result['error_key'] = 'OpenAI account problem';
        $result['key_valid'] = true;
        $result['account_problem'] = true;
        $result['admin_message'] = __('Your API key works, but your OpenAI account has no credit. The OpenAI API is prepaid and separate from a ChatGPT subscription: add credit (for example $5) in your OpenAI billing settings, then test again. It can take a few minutes for new credit to become active.', 'wpiko-chatbot');
        $result['admin_link'] = wpiko_chatbot_openai_url('billing');
        $result['admin_link_label'] = __('Add credit in OpenAI billing', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code === 429) {
        $result['code'] = 'rate_limited';
        $result['error_key'] = 'Rate limit exceeded';
        $result['key_valid'] = true;
        $result['admin_message'] = __('OpenAI is rate-limiting this API key (too many requests or tokens per minute for your usage tier). Visitors can retry in a moment. New accounts have low limits that rise automatically as you use the API.', 'wpiko-chatbot');
        $result['admin_link'] = wpiko_chatbot_openai_url('limits');
        $result['admin_link_label'] = __('View OpenAI rate limits', 'wpiko-chatbot');
        return $result;
    }

    if ($is_model_access && in_array($status_code, array(400, 403, 404), true)) {
        $result['code'] = 'model_access';
        $result['error_key'] = 'OpenAI account problem';
        $result['key_valid'] = true;
        $result['account_problem'] = true;
        $result['admin_message'] = sprintf(
            /* translators: %s: model identifier. */
            __('Your OpenAI account cannot use %s yet. Some newer models require organization verification in OpenAI. Choose a different model in AI Configuration, or verify your organization and try again.', 'wpiko-chatbot'),
            $model_label
        );
        $result['admin_link'] = wpiko_chatbot_openai_url('verification');
        $result['admin_link_label'] = __('Open OpenAI organization settings', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code === 403) {
        $result['code'] = 'forbidden';
        $result['error_key'] = 'OpenAI account problem';
        $result['key_valid'] = true;
        $result['account_problem'] = true;
        $result['admin_message'] = sprintf(
            /* translators: %s: error message returned by OpenAI. */
            __('OpenAI refused the request: "%s". Check the key\'s project permissions in your OpenAI dashboard (the key needs access to the Responses API).', 'wpiko-chatbot'),
            $openai_message !== '' ? $openai_message : 'HTTP 403'
        );
        $result['admin_link'] = wpiko_chatbot_openai_url('keys');
        $result['admin_link_label'] = __('Open OpenAI API keys', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code === 504 || $status_code === 408) {
        $result['code'] = 'timeout';
        $result['error_key'] = 'Response timeout';
        $result['key_valid'] = true;
        $result['admin_message'] = __('OpenAI took too long to answer. This is usually temporary. If it keeps happening, choose a lower reasoning effort or a faster model in AI Configuration.', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code >= 500) {
        $result['code'] = 'server_error';
        $result['error_key'] = 'Model overloaded';
        $result['key_valid'] = true;
        /* translators: %d: HTTP status code. */
        $result['admin_message'] = sprintf(__('OpenAI is having problems right now (HTTP %d). This is usually temporary and not caused by your settings.', 'wpiko-chatbot'), $status_code);
        $result['admin_link'] = wpiko_chatbot_openai_url('status');
        $result['admin_link_label'] = __('Check OpenAI status', 'wpiko-chatbot');
        return $result;
    }

    if ($status_code === 400 || $status_code === 404) {
        $result['code'] = 'bad_request';
        $result['error_key'] = 'Response generation failed';
        $result['key_valid'] = true;
        $result['admin_message'] = sprintf(
            /* translators: %s: error message returned by OpenAI. */
            __('OpenAI could not process the request: "%s". Check your AI Configuration settings (model, reasoning effort) and the Debug Log for details.', 'wpiko-chatbot'),
            $openai_message !== '' ? $openai_message : 'HTTP ' . $status_code
        );
        return $result;
    }

    $result['admin_message'] = sprintf(
        /* translators: 1: HTTP status code, 2: error message returned by OpenAI. */
        __('OpenAI returned an unexpected error (HTTP %1$d): %2$s', 'wpiko-chatbot'),
        $status_code,
        $openai_message !== '' ? $openai_message : __('no details', 'wpiko-chatbot')
    );

    return $result;
}

/**
 * Pick the cheapest reasoning effort a model accepts, for connection tests.
 *
 * @param string $model Model identifier.
 * @return string|null Effort, or null when the model takes no reasoning setting.
 */
function wpiko_chatbot_lowest_reasoning_effort($model)
{
    if (!wpiko_chatbot_responses_model_uses_fixed_sampling($model)) {
        return null;
    }

    $config = wpiko_chatbot_get_responses_reasoning_effort_config();
    if (isset($config[$model]['allowed']) && is_array($config[$model]['allowed']) && !empty($config[$model]['allowed'])) {
        return in_array('none', $config[$model]['allowed'], true) ? 'none' : reset($config[$model]['allowed']);
    }

    return 'low';
}

/**
 * Test an API key with a tiny real request to the Responses API.
 *
 * Listing models succeeds even for accounts without credit, so a real (very
 * small) generation request is the only reliable way to know the chatbot will
 * actually be able to answer visitors.
 *
 * @param string $api_key API key to test.
 * @param string $model   Model to test with. Defaults to the configured chatbot model.
 * @return array{ok: bool, code: string, key_valid: bool|null, message: string, link: string, link_label: string, model: string}
 */
function wpiko_chatbot_test_openai_connection($api_key, $model = '')
{
    $api_key = trim((string) $api_key);

    if ($api_key === '') {
        return array(
            'ok' => false,
            'code' => 'missing_key',
            'key_valid' => false,
            'message' => __('Enter an OpenAI API key first.', 'wpiko-chatbot'),
            'link' => wpiko_chatbot_openai_url('keys'),
            'link_label' => __('Create an OpenAI API key', 'wpiko-chatbot'),
            'model' => '',
        );
    }

    if (strpos($api_key, 'sk-') !== 0) {
        return array(
            'ok' => false,
            'code' => 'invalid_key',
            'key_valid' => false,
            'message' => __('That does not look like an OpenAI secret key. OpenAI keys start with "sk-". Copy the full key from your OpenAI dashboard (it is only shown once when you create it).', 'wpiko-chatbot'),
            'link' => wpiko_chatbot_openai_url('keys'),
            'link_label' => __('Open OpenAI API keys', 'wpiko-chatbot'),
            'model' => '',
        );
    }

    if ($model === '') {
        $model = get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');
    }
    $models = wpiko_chatbot_get_responses_api_models();
    if (!isset($models[$model])) {
        $model = 'gpt-6-luna';
    }

    $request_body = array(
        'model' => $model,
        'input' => 'Reply with the single word: OK',
        'max_output_tokens' => 16,
        'store' => false,
    );
    $effort = wpiko_chatbot_lowest_reasoning_effort($model);
    if ($effort !== null) {
        $request_body['reasoning'] = array('effort' => $effort);
    }

    $response = wp_remote_post('https://api.openai.com/v1/responses', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => wp_json_encode($request_body),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        $classification = wpiko_chatbot_classify_openai_error(0, null, $response->get_error_message(), $model);
    } else {
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wpiko_chatbot_log('OpenAI connection test succeeded with model ' . $model, 'info');
            return array(
                'ok' => true,
                'code' => 'ok',
                'key_valid' => true,
                'message' => sprintf(
                    /* translators: %s: model name. */
                    __('Connected. OpenAI answered a test message using %s, so your chatbot is ready to reply to visitors.', 'wpiko-chatbot'),
                    $models[$model]
                ),
                'link' => '',
                'link_label' => '',
                'model' => $model,
            );
        }
        $classification = wpiko_chatbot_classify_openai_error($status_code, wp_remote_retrieve_body($response), '', $model);
    }

    wpiko_chatbot_log('OpenAI connection test failed (' . $classification['code'] . '): ' . $classification['openai_message'], 'warning');

    return array(
        'ok' => false,
        'code' => $classification['code'],
        'key_valid' => $classification['key_valid'],
        'message' => $classification['admin_message'],
        'link' => $classification['admin_link'],
        'link_label' => $classification['admin_link_label'],
        'model' => $model,
    );
}

/**
 * Get the last known OpenAI account problem, if any.
 *
 * @return array Empty array when everything is fine.
 */
function wpiko_chatbot_get_openai_health()
{
    $health = get_option('wpiko_chatbot_openai_health', array());
    return is_array($health) && !empty($health['code']) ? $health : array();
}

/**
 * Remember an OpenAI account problem so admins are alerted in the dashboard.
 *
 * @param array $problem Array with code, message, link, link_label.
 * @return void
 */
function wpiko_chatbot_set_openai_health($problem)
{
    $current = wpiko_chatbot_get_openai_health();
    $code = isset($problem['code']) ? (string) $problem['code'] : '';
    $message = isset($problem['message']) ? (string) $problem['message'] : '';

    // Avoid a database write on every failed visitor message.
    if (!empty($current) && $current['code'] === $code && $current['message'] === $message) {
        return;
    }

    update_option('wpiko_chatbot_openai_health', array(
        'code' => $code,
        'message' => $message,
        'link' => isset($problem['link']) ? (string) $problem['link'] : '',
        'link_label' => isset($problem['link_label']) ? (string) $problem['link_label'] : '',
        'time' => time(),
    ), false);
}

/**
 * Clear any stored OpenAI account problem.
 *
 * @return void
 */
function wpiko_chatbot_clear_openai_health()
{
    if (get_option('wpiko_chatbot_openai_health', false) !== false) {
        delete_option('wpiko_chatbot_openai_health');
    }
}

/**
 * Store (or clear) health from a connection test result.
 *
 * @param array $test Result of wpiko_chatbot_test_openai_connection().
 * @return void
 */
function wpiko_chatbot_record_connection_test($test)
{
    update_option('wpiko_chatbot_last_connection_test', array(
        'ok' => !empty($test['ok']),
        'code' => $test['code'],
        'message' => $test['message'],
        'link' => $test['link'],
        'link_label' => $test['link_label'],
        'model' => $test['model'],
        'time' => time(),
    ), false);

    if (!empty($test['ok'])) {
        wpiko_chatbot_clear_openai_health();
    } elseif (in_array($test['code'], array('invalid_key', 'no_credit', 'model_access', 'forbidden'), true)) {
        wpiko_chatbot_set_openai_health(array(
            'code' => $test['code'],
            'message' => $test['message'],
            'link' => $test['link'],
            'link_label' => $test['link_label'],
        ));
    }
}

/**
 * Build the error payload returned to the chat widget for a failed request.
 *
 * Visitors get the friendly, customizable message. Site admins testing the
 * chatbot see what actually went wrong and how to fix it. Account problems are
 * recorded so the plugin dashboard can alert the admin.
 *
 * @param array $classification Result of wpiko_chatbot_classify_openai_error().
 * @param array $debug          Extra debug details for admins.
 * @return array{visitor_message: string, data: array}
 */
function wpiko_chatbot_build_api_error_response($classification, $debug = array())
{
    $visitor_message = wpiko_chatbot_get_user_friendly_error_message($classification['error_key'], 'responses');

    if (!empty($classification['account_problem'])) {
        wpiko_chatbot_set_openai_health(array(
            'code' => $classification['code'],
            'message' => $classification['admin_message'],
            'link' => $classification['admin_link'],
            'link_label' => $classification['admin_link_label'],
        ));
    }

    $data = array(
        'message' => $visitor_message,
        'type' => 'responses_api_error',
        'error_code' => $classification['code'],
    );

    if (current_user_can('manage_options')) {
        if ($classification['admin_message'] !== '') {
            $data['message'] = sprintf(
                /* translators: 1: explanation for the admin, 2: message visitors see. */
                __('Admin notice: %1$s (Visitors see: "%2$s")', 'wpiko-chatbot'),
                $classification['admin_message'],
                $visitor_message
            );
        }
        if ($classification['admin_link'] !== '') {
            $data['admin_link'] = $classification['admin_link'];
            $data['admin_link_label'] = $classification['admin_link_label'];
        }
        if (!empty($debug)) {
            $data['debug'] = $debug;
        }
    }

    return array(
        'visitor_message' => $visitor_message,
        'data' => $data,
    );
}

/**
 * Show a site-wide admin notice when the chatbot cannot answer visitors.
 *
 * Displayed on the WordPress Dashboard and Plugins screens only (the plugin's
 * own dashboard has its own alert).
 *
 * @return void
 */
function wpiko_chatbot_openai_health_admin_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, array('dashboard', 'plugins'), true)) {
        return;
    }

    $health = wpiko_chatbot_get_openai_health();
    if (empty($health)) {
        return;
    }

    $api_key_url = wp_nonce_url(admin_url('admin.php?page=ai-chatbot&tab=api_key'), 'wpiko_chatbot_tab_nonce');
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('WPiko Chatbot cannot answer visitors right now.', 'wpiko-chatbot'); ?></strong>
            <?php echo esc_html($health['message']); ?>
        </p>
        <p>
            <?php if (!empty($health['link'])) : ?>
                <a href="<?php echo esc_url($health['link']); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php echo esc_html($health['link_label']); ?></a>
            <?php endif; ?>
            <a href="<?php echo esc_url($api_key_url); ?>" class="button"><?php esc_html_e('Test the connection again', 'wpiko-chatbot'); ?></a>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'wpiko_chatbot_openai_health_admin_notice');

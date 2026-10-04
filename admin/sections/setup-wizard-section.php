<?php
/**
 * Setup wizard: connect OpenAI, teach the chatbot about the site, go live.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * URL of the setup wizard.
 *
 * @return string
 */
function wpiko_chatbot_setup_wizard_url()
{
    return add_query_arg(
        array(
            'page' => 'ai-chatbot',
            'tab' => 'setup',
            '_wpnonce' => wp_create_nonce('wpiko_chatbot_tab_nonce'),
        ),
        admin_url('admin.php')
    );
}

/**
 * Whether setup is finished (API key saved and the wizard completed).
 *
 * @return bool
 */
function wpiko_chatbot_setup_is_complete()
{
    return wpiko_chatbot_get_api_key() !== '' && (bool) get_option('wpiko_chatbot_setup_completed', false);
}

/**
 * Sites that were already set up before the wizard existed count as complete,
 * so the wizard does not appear in their menu after updating. Runs once.
 *
 * @return void
 */
function wpiko_chatbot_migrate_setup_state()
{
    if (get_option('wpiko_chatbot_setup_migrated', false)) {
        return;
    }

    if (wpiko_chatbot_get_api_key() !== '' && !get_option('wpiko_chatbot_setup_completed', false)) {
        update_option('wpiko_chatbot_setup_completed', time(), false);
    }

    update_option('wpiko_chatbot_setup_migrated', '1', false);
}
add_action('admin_init', 'wpiko_chatbot_migrate_setup_state', 5);

/**
 * Remember to open the wizard after a fresh activation.
 *
 * @return void
 */
function wpiko_chatbot_flag_activation_redirect()
{
    set_transient('wpiko_chatbot_activation_redirect', 1, 60);
}
register_activation_hook(WPIKO_CHATBOT_PLUGIN_FILE, 'wpiko_chatbot_flag_activation_redirect');

/**
 * Open the wizard right after activation when the chatbot is not set up yet.
 *
 * @return void
 */
function wpiko_chatbot_maybe_redirect_to_wizard()
{
    if (!get_transient('wpiko_chatbot_activation_redirect')) {
        return;
    }

    delete_transient('wpiko_chatbot_activation_redirect');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of a core query flag.
    if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can('manage_options')) {
        return;
    }

    if (wpiko_chatbot_get_api_key() !== '') {
        return;
    }

    wp_safe_redirect(wpiko_chatbot_setup_wizard_url());
    exit;
}
add_action('admin_init', 'wpiko_chatbot_maybe_redirect_to_wizard');

/**
 * Add "Start setup" and "Settings" links on the Plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function wpiko_chatbot_plugin_action_links($links)
{
    $settings_url = add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'dashboard'), admin_url('admin.php'));
    $extra = array(
        'settings' => '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'wpiko-chatbot') . '</a>',
    );

    if (wpiko_chatbot_get_api_key() === '') {
        $extra['setup'] = '<a href="' . esc_url(wpiko_chatbot_setup_wizard_url()) . '" style="font-weight:600;">' . esc_html__('Start setup', 'wpiko-chatbot') . '</a>';
    }

    return array_merge($extra, $links);
}
add_filter('plugin_action_links_' . plugin_basename(WPIKO_CHATBOT_PLUGIN_FILE), 'wpiko_chatbot_plugin_action_links');

/**
 * AJAX: save the chatbot name and the "what does your business do" basics.
 *
 * @return void
 */
function wpiko_chatbot_wizard_save_basics_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    $name = isset($_POST['chatbot_name']) ? sanitize_text_field(wp_unslash($_POST['chatbot_name'])) : '';
    $business = isset($_POST['business']) ? sanitize_text_field(wp_unslash($_POST['business'])) : '';
    $tone = isset($_POST['tone']) ? sanitize_key(wp_unslash($_POST['tone'])) : 'friendly';

    $allowed_tones = array('friendly', 'casual', 'professional', 'formal', 'humorous', 'educational', 'enthusiastic');
    if (!in_array($tone, $allowed_tones, true)) {
        $tone = 'friendly';
    }

    if ($name !== '') {
        update_option('wpiko_chatbot_name', $name);
    }

    if ($business !== '') {
        // These options drive the Basic Instructions in AI Configuration.
        if (get_option('wpiko_chatbot_responses_assistant_type', '') === '') {
            update_option('wpiko_chatbot_responses_assistant_type', 'AI assistant');
        }
        update_option('wpiko_chatbot_responses_website_specialization', $business);
        update_option('wpiko_chatbot_responses_assistant_tone', $tone);
        if (get_option('wpiko_chatbot_responses_assistant_style', '') === '') {
            update_option('wpiko_chatbot_responses_assistant_style', 'helpful');
        }
    }

    wp_send_json_success(array('message' => __('Saved.', 'wpiko-chatbot')));
}
add_action('wp_ajax_wpiko_chatbot_wizard_save_basics', 'wpiko_chatbot_wizard_save_basics_ajax');

/**
 * AJAX: finish the wizard and choose whether the floating chatbot is shown.
 *
 * @return void
 */
function wpiko_chatbot_wizard_finish_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    $show_floating = isset($_POST['show_floating']) && sanitize_text_field(wp_unslash($_POST['show_floating'])) === '1';
    update_option('wpiko_chatbot_enable_floating', $show_floating ? '1' : '');
    update_option('wpiko_chatbot_setup_completed', time(), false);

    wp_send_json_success(array(
        'site_url' => home_url('/'),
        'dashboard_url' => add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'dashboard'), admin_url('admin.php')),
    ));
}
add_action('wp_ajax_wpiko_chatbot_wizard_finish', 'wpiko_chatbot_wizard_finish_ajax');

/**
 * Render the setup wizard.
 *
 * @return void
 */
function wpiko_chatbot_setup_wizard_section()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $api_key = wpiko_chatbot_get_api_key();
    $last_test = get_option('wpiko_chatbot_last_connection_test', array());
    $health = wpiko_chatbot_get_openai_health();
    $connected = $api_key !== '' && !empty($last_test['ok']) && empty($health);

    $chatbot_name = get_option('wpiko_chatbot_name', '');
    if ($chatbot_name === '' || $chatbot_name === 'My Chatbot') {
        $chatbot_name = get_bloginfo('name') !== '' ? sprintf(
            /* translators: %s: site name. */
            __('%s Assistant', 'wpiko-chatbot'),
            wp_strip_all_tags(get_bloginfo('name'))
        ) : __('Assistant', 'wpiko-chatbot');
    }
    $business = get_option('wpiko_chatbot_responses_website_specialization', '');
    $tone = get_option('wpiko_chatbot_responses_assistant_tone', 'friendly');
    $floating_enabled = get_option('wpiko_chatbot_enable_floating', null);
    $show_floating_default = $floating_enabled === null || (bool) $floating_enabled;
    $tones = array(
        'friendly' => __('Friendly', 'wpiko-chatbot'),
        'professional' => __('Professional', 'wpiko-chatbot'),
        'casual' => __('Casual', 'wpiko-chatbot'),
        'formal' => __('Formal', 'wpiko-chatbot'),
        'enthusiastic' => __('Enthusiastic', 'wpiko-chatbot'),
    );
    ?>
    <div class="wpiko-wizard" data-connected="<?php echo $connected ? '1' : '0'; ?>">
        <div class="wpiko-wizard-intro">
            <h2><?php esc_html_e('Set up your chatbot', 'wpiko-chatbot'); ?></h2>
            <p><?php esc_html_e('Three short steps and your chatbot will be answering visitors on your site.', 'wpiko-chatbot'); ?></p>
            <ol class="wpiko-wizard-progress" aria-label="<?php esc_attr_e('Setup progress', 'wpiko-chatbot'); ?>">
                <li data-step="1" class="is-current"><span>1</span> <?php esc_html_e('Connect OpenAI', 'wpiko-chatbot'); ?></li>
                <li data-step="2"><span>2</span> <?php esc_html_e('Teach it your business', 'wpiko-chatbot'); ?></li>
                <li data-step="3"><span>3</span> <?php esc_html_e('Test and go live', 'wpiko-chatbot'); ?></li>
            </ol>
        </div>

        <!-- Step 1 -->
        <section class="wpiko-wizard-step is-active" data-step="1">
            <h3><?php esc_html_e('Connect your OpenAI account', 'wpiko-chatbot'); ?></h3>

            <div class="wpiko-wizard-connected" <?php echo $connected ? '' : 'hidden'; ?>>
                <p class="wpiko-wizard-success"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e('Connected. OpenAI accepted your key and your account can answer messages.', 'wpiko-chatbot'); ?></p>
            </div>

            <div class="wpiko-wizard-connect" <?php echo $connected ? 'hidden' : ''; ?>>
                <p class="description"><?php esc_html_e('The chatbot runs on your own OpenAI account, so you pay OpenAI directly for what you use (usually a few dollars a month for a small site).', 'wpiko-chatbot'); ?></p>
                <ol class="wpiko-api-key-steps">
                    <li>
                        <strong><?php esc_html_e('Sign in to the OpenAI Platform', 'wpiko-chatbot'); ?></strong>
                        <span><?php esc_html_e('This is separate from ChatGPT. A ChatGPT Plus plan does not include API usage.', 'wpiko-chatbot'); ?></span>
                        <a href="https://platform.openai.com/signup" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open platform.openai.com', 'wpiko-chatbot'); ?></a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Add credit to your account', 'wpiko-chatbot'); ?></strong>
                        <span><?php esc_html_e('The API is prepaid. Without credit, the key is accepted but every answer fails. $5 is enough to start.', 'wpiko-chatbot'); ?></span>
                        <a href="<?php echo esc_url(wpiko_chatbot_openai_url('billing')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open OpenAI billing', 'wpiko-chatbot'); ?></a>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Create a secret key and paste it below', 'wpiko-chatbot'); ?></strong>
                        <span><?php esc_html_e('Keys start with "sk-". OpenAI shows the key only once, so copy it straight away.', 'wpiko-chatbot'); ?></span>
                        <a href="<?php echo esc_url(wpiko_chatbot_openai_url('keys')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create an API key', 'wpiko-chatbot'); ?></a>
                    </li>
                </ol>

                <div class="wpiko-wizard-key-row" <?php echo $api_key !== '' ? 'hidden' : ''; ?>>
                    <label for="wpiko-wizard-api-key" class="screen-reader-text"><?php esc_html_e('OpenAI API key', 'wpiko-chatbot'); ?></label>
                    <input type="password" id="wpiko-wizard-api-key" class="regular-text" placeholder="sk-..." autocomplete="off" spellcheck="false">
                    <button type="button" class="button button-primary" id="wpiko-wizard-save-key"><?php esc_html_e('Save and test', 'wpiko-chatbot'); ?></button>
                </div>
                <div class="wpiko-wizard-retest-row" <?php echo $api_key !== '' ? '' : 'hidden'; ?>>
                    <button type="button" class="button button-primary" id="wpiko-wizard-retest"><?php esc_html_e('Test my connection again', 'wpiko-chatbot'); ?></button>
                    <button type="button" class="button-link" id="wpiko-wizard-change-key"><?php esc_html_e('Use a different key', 'wpiko-chatbot'); ?></button>
                </div>
                <div class="wpiko-wizard-message" aria-live="polite">
                    <?php if ($api_key !== '' && !$connected) : ?>
                        <?php
                        $pending_message = !empty($health['message']) ? $health['message'] : (!empty($last_test['message']) ? $last_test['message'] : __('Your key is saved. Test the connection to make sure the chatbot can answer.', 'wpiko-chatbot'));
                        $pending_link = !empty($health['link']) ? $health['link'] : (!empty($last_test['link']) ? $last_test['link'] : '');
                        $pending_label = !empty($health['link_label']) ? $health['link_label'] : (!empty($last_test['link_label']) ? $last_test['link_label'] : '');
                        ?>
                        <div class="wpiko-wizard-notice is-error">
                            <p><?php echo esc_html($pending_message); ?></p>
                            <?php if ($pending_link !== '') : ?>
                                <a class="button" href="<?php echo esc_url($pending_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($pending_label); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wpiko-wizard-nav">
                <span></span>
                <button type="button" class="button button-primary button-hero wpiko-wizard-next" data-next="2" <?php echo $connected ? '' : 'disabled'; ?>><?php esc_html_e('Continue', 'wpiko-chatbot'); ?></button>
            </div>
        </section>

        <!-- Step 2 -->
        <section class="wpiko-wizard-step" data-step="2" hidden>
            <h3><?php esc_html_e('Teach it about your business', 'wpiko-chatbot'); ?></h3>

            <div class="wpiko-wizard-basics">
                <div class="wpiko-wizard-field">
                    <label for="wpiko-wizard-name"><?php esc_html_e('Chatbot name', 'wpiko-chatbot'); ?></label>
                    <input type="text" id="wpiko-wizard-name" class="regular-text" value="<?php echo esc_attr($chatbot_name); ?>">
                </div>
                <div class="wpiko-wizard-field">
                    <label for="wpiko-wizard-business"><?php esc_html_e('What does your business do?', 'wpiko-chatbot'); ?></label>
                    <input type="text" id="wpiko-wizard-business" class="large-text" value="<?php echo esc_attr($business); ?>" placeholder="<?php esc_attr_e('e.g. handmade leather bags shipped across Europe', 'wpiko-chatbot'); ?>">
                </div>
                <div class="wpiko-wizard-field">
                    <label for="wpiko-wizard-tone"><?php esc_html_e('Tone', 'wpiko-chatbot'); ?></label>
                    <select id="wpiko-wizard-tone">
                        <?php foreach ($tones as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($tone, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php wpiko_chatbot_render_site_knowledge_panel('wizard'); ?>

            <p class="description wpiko-wizard-files-hint">
                <?php esc_html_e('You can also upload PDFs or documents (price lists, FAQs, manuals) later in AI Configuration → File Management.', 'wpiko-chatbot'); ?>
            </p>

            <div class="wpiko-wizard-nav">
                <button type="button" class="button wpiko-wizard-back" data-back="1"><?php esc_html_e('Back', 'wpiko-chatbot'); ?></button>
                <button type="button" class="button button-primary button-hero wpiko-wizard-next" data-next="3"><?php esc_html_e('Save and continue', 'wpiko-chatbot'); ?></button>
            </div>
        </section>

        <!-- Step 3 -->
        <section class="wpiko-wizard-step" data-step="3" hidden>
            <h3><?php esc_html_e('Test it, then go live', 'wpiko-chatbot'); ?></h3>
            <p class="description"><?php esc_html_e('Ask a question like a visitor would. Test messages also appear in Conversations.', 'wpiko-chatbot'); ?></p>

            <div class="wpiko-wizard-chat">
                <div class="wpiko-wizard-chat-messages" aria-live="polite"></div>
                <div class="wpiko-wizard-chat-suggestions">
                    <button type="button" class="button button-small"><?php esc_html_e('What do you offer?', 'wpiko-chatbot'); ?></button>
                    <button type="button" class="button button-small"><?php esc_html_e('How can I contact you?', 'wpiko-chatbot'); ?></button>
                    <button type="button" class="button button-small"><?php esc_html_e('Who are you?', 'wpiko-chatbot'); ?></button>
                </div>
                <div class="wpiko-wizard-chat-input">
                    <label for="wpiko-wizard-chat-text" class="screen-reader-text"><?php esc_html_e('Test message', 'wpiko-chatbot'); ?></label>
                    <input type="text" id="wpiko-wizard-chat-text" placeholder="<?php esc_attr_e('Type a question…', 'wpiko-chatbot'); ?>" autocomplete="off">
                    <button type="button" class="button button-primary" id="wpiko-wizard-chat-send"><?php esc_html_e('Send', 'wpiko-chatbot'); ?></button>
                </div>
            </div>

            <label class="wpiko-wizard-toggle">
                <input type="checkbox" id="wpiko-wizard-show-floating" <?php checked($show_floating_default); ?>>
                <span>
                    <strong><?php esc_html_e('Show the chatbot on every page', 'wpiko-chatbot'); ?></strong>
                    <?php esc_html_e('Adds a chat button to the corner of your site. You can exclude pages or use the [wpiko_chatbot] shortcode instead later.', 'wpiko-chatbot'); ?>
                </span>
            </label>

            <div class="wpiko-wizard-nav">
                <button type="button" class="button wpiko-wizard-back" data-back="2"><?php esc_html_e('Back', 'wpiko-chatbot'); ?></button>
                <button type="button" class="button button-primary button-hero" id="wpiko-wizard-finish"><?php esc_html_e('Finish and view my site', 'wpiko-chatbot'); ?></button>
            </div>
            <div class="wpiko-wizard-done" hidden>
                <p class="wpiko-wizard-success"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e('All set! Your chatbot is live. Your site opened in a new tab.', 'wpiko-chatbot'); ?></p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'dashboard'), admin_url('admin.php'))); ?>"><?php esc_html_e('Go to the dashboard', 'wpiko-chatbot'); ?></a>
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'chatbot_style', '_wpnonce' => wp_create_nonce('wpiko_chatbot_tab_nonce')), admin_url('admin.php'))); ?>"><?php esc_html_e('Match it to my brand colors', 'wpiko-chatbot'); ?></a>
                </p>
            </div>
        </section>
    </div>
    <?php
}

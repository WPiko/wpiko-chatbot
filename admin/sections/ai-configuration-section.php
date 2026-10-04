<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_ai_configuration_section()
{

    if (isset($_POST['action']) && $_POST['action'] == 'save_ai_configuration') {
        check_admin_referer('save_ai_configuration', 'ai_configuration_nonce');

        // Update Responses API settings
        $submitted_responses_model = isset($_POST['responses_model']) ? sanitize_text_field(wp_unslash($_POST['responses_model'])) : get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');
        if (isset($_POST['responses_model'])) {
            $supported_models = function_exists('wpiko_chatbot_get_responses_api_models')
                ? wpiko_chatbot_get_responses_api_models()
                : array('gpt-6-luna' => 'GPT-6 Luna');
            if (!isset($supported_models[$submitted_responses_model])) {
                $submitted_responses_model = 'gpt-6-luna';
            }
            update_option('wpiko_chatbot_responses_model', $submitted_responses_model);
        }
        if (isset($_POST['responses_reasoning_effort'])) {
            $submitted_reasoning_effort = sanitize_text_field(wp_unslash($_POST['responses_reasoning_effort']));
            if (function_exists('wpiko_chatbot_normalize_responses_reasoning_effort')) {
                $submitted_reasoning_effort = wpiko_chatbot_normalize_responses_reasoning_effort($submitted_responses_model, $submitted_reasoning_effort);
            }
            update_option('wpiko_chatbot_responses_reasoning_effort', $submitted_reasoning_effort);
        }
        if (isset($_POST['responses_verbosity'])) {
            update_option('wpiko_chatbot_responses_verbosity', sanitize_text_field(wp_unslash($_POST['responses_verbosity'])));
        }

        echo '<div class="updated"><p>AI Configuration updated successfully.</p></div>';
    }

    $responses_model = get_option('wpiko_chatbot_responses_model', 'gpt-6-luna');
    $reasoning_effort = get_option('wpiko_chatbot_responses_reasoning_effort', 'medium');
    if (function_exists('wpiko_chatbot_normalize_responses_reasoning_effort')) {
        $reasoning_effort = wpiko_chatbot_normalize_responses_reasoning_effort($responses_model, $reasoning_effort);
    }
    $verbosity = get_option('wpiko_chatbot_responses_verbosity', 'medium');
    ?>
    <div class="ai-configuration-section">

        <?php
        // Action hook for adding content after AI configuration title (e.g., WooCommerce integration state)
        do_action('wpiko_chatbot_after_ai_configuration_title');
        ?>

        <form method="post" action="" name="ai_configuration_form" id="ai_configuration_form">
            <?php wp_nonce_field('save_ai_configuration', 'ai_configuration_nonce'); ?>

            <!-- Responses API Settings -->
            <div id="responses-api-settings" style="display: block;">
                <div class="responses-api-section">
                    <h3><span class="dashicons dashicons-admin-generic"></span> AI Configuration</h3>
                    <p class="description">Set up your AI chatbot settings to customize how it interacts with your website
                        visitors.</p>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">AI Model Settings</th>
                            <td>
                                <div class="wpiko-model-config-wrapper">
                                    <!-- Model Selection -->
                                    <div class="wpiko-config-field">
                                        <label for="responses_model">Model</label>
                                        <select name="responses_model" id="responses_model">
                                            <?php
                                            // Use the same canonical registry as request validation.
                                            $available_models = function_exists('wpiko_chatbot_get_responses_api_models')
                                                ? wpiko_chatbot_get_responses_api_models()
                                                : array('gpt-6-luna' => 'GPT-6 Luna');

                                            // Previously saved deprecated models fall back to the current default.
                                            if (!isset($available_models[$responses_model])) {
                                                $responses_model = 'gpt-6-luna';
                                            }
                                            foreach ($available_models as $model_value => $model_label) {
                                                printf(
                                                    '<option value="%s" %s>%s</option>',
                                                    esc_attr($model_value),
                                                    selected($model_value, $responses_model, false),
                                                    esc_html($model_label)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <!-- Reasoning Effort (GPT-5+ only) -->
                                    <div class="wpiko-config-field wpiko-gpt5-setting" style="display:none;">
                                        <label for="responses_reasoning_effort">Reasoning Effort</label>
                                        <select name="responses_reasoning_effort" id="responses_reasoning_effort">
                                            <?php
                                            $efforts = array(
                                                'none' => 'None',
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High',
                                                'xhigh' => 'X-High',
                                                'max' => 'Max'
                                            );
                                            foreach ($efforts as $value => $label) {
                                                printf(
                                                    '<option value="%s" %s>%s</option>',
                                                    esc_attr($value),
                                                    selected($value, $reasoning_effort, false),
                                                    esc_html($label)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <!-- Verbosity (GPT-5+ only) -->
                                    <div class="wpiko-config-field wpiko-gpt5-setting" style="display:none;">
                                        <label for="responses_verbosity">Verbosity</label>
                                        <select name="responses_verbosity" id="responses_verbosity">
                                            <?php
                                            $verbosities = array(
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High'
                                            );
                                            foreach ($verbosities as $value => $label) {
                                                printf(
                                                    '<option value="%s" %s>%s</option>',
                                                    esc_attr($value),
                                                    selected($value, $verbosity, false),
                                                    esc_html($label)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="wpiko-config-descriptions">
                                    <p class="description">Select the AI model.
                                        <span class="wpiko-gpt5-setting" style="display:none;">
                                            For this model, you can also adjust <strong>Reasoning Effort</strong>
                                            (computation depth) and <strong>Verbosity</strong> (response length).
                                        </span>
                                    </p>
                                </div>

                                <!-- Dynamic model information will be inserted here by JavaScript -->
                                <div id="wpiko-responses-model-info"></div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">System Instructions</th>
                            <td>
                                <div class="instructions-tabs">
                                    <ul class="nav-tab-wrapper">
                                        <li><a href="#" class="nav-tab nav-tab-active" data-tab="responses-basic">Basic
                                                Instructions</a></li>
                                        <li><a href="#" class="nav-tab" data-tab="responses-advanced">Advanced
                                                Instructions</a></li>
                                    </ul>

                                    <div class="tab-content" id="responses-basic-tab" style="display: block;">
                                        <table class="form-table">
                                            <tr valign="top">
                                                <td>
                                                    <div class="structured-instructions">

                                                        <div class="instruction-field assistant-type-field">
                                                            <label>You are an</label>
                                                            <input type="text" name="responses_assistant_type"
                                                                id="responses_assistant_type" class="medium-text"
                                                                value="<?php echo esc_attr(get_option('wpiko_chatbot_responses_assistant_type', get_option('wpiko_chatbot_assistant_type', 'AI assistant'))); ?>"
                                                                placeholder="AI assistant">
                                                            <span>for the website <strong
                                                                    class="website-name"><?php echo esc_html(get_bloginfo('name')); ?></strong>.</span>
                                                        </div>

                                                        <div class="instruction-field">
                                                            <label>The website specializes in:</label>
                                                            <input type="text" name="responses_website_specialization"
                                                                id="responses_website_specialization" class="medium-text"
                                                                value="<?php echo esc_attr(get_option('wpiko_chatbot_responses_website_specialization', get_option('wpiko_chatbot_website_specialization', ''))); ?>"
                                                                placeholder="e.g., web development, digital marketing, etc.">
                                                        </div>

                                                        <div class="instruction-field">
                                                            <label>Assistant Tone:</label>
                                                            <select name="responses_assistant_tone"
                                                                id="responses_assistant_tone">
                                                                <?php
                                                                $tones = array(
                                                                    'friendly' => 'Friendly',
                                                                    'casual' => 'Casual',
                                                                    'professional' => 'Professional',
                                                                    'formal' => 'Formal',
                                                                    'humorous' => 'Humorous',
                                                                    'educational' => 'Educational',
                                                                    'enthusiastic' => 'Enthusiastic'
                                                                );
                                                                $selected_tone = get_option('wpiko_chatbot_responses_assistant_tone', get_option('wpiko_chatbot_assistant_tone', 'friendly'));
                                                                foreach ($tones as $value => $label) {
                                                                    printf(
                                                                        '<option value="%s" %s>%s</option>',
                                                                        esc_attr($value),
                                                                        selected($value, $selected_tone, false),
                                                                        esc_html($label)
                                                                    );
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>

                                                        <div class="instruction-field">
                                                            <label>Assistant Style:</label>
                                                            <select name="responses_assistant_style"
                                                                id="responses_assistant_style">
                                                                <?php
                                                                $styles = array(
                                                                    'professional' => 'Professional',
                                                                    'conversational' => 'Conversational',
                                                                    'helpful' => 'Helpful',
                                                                    'concise' => 'Concise',
                                                                    'detailed' => 'Detailed'
                                                                );
                                                                $selected_style = get_option('wpiko_chatbot_responses_assistant_style', get_option('wpiko_chatbot_assistant_style', 'professional'));
                                                                foreach ($styles as $value => $label) {
                                                                    printf(
                                                                        '<option value="%s" %s>%s</option>',
                                                                        esc_attr($value),
                                                                        selected($value, $selected_style, false),
                                                                        esc_html($label)
                                                                    );
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="responses_main_system_instructions"
                                                        id="responses_main_system_instructions" value="<?php
                                                        $instructions = wpiko_chatbot_get_system_instructions();
                                                        echo esc_attr($instructions['main']);
                                                        ?>">
                                                    <p class="description">Customize how your AI assistant introduces itself
                                                        and understands your website's purpose and content.</p>
                                                    <?php if (trim($instructions['main']) === '' && get_option('wpiko_chatbot_responses_assistant_type', '') === '' && get_option('wpiko_chatbot_responses_website_specialization', '') === '') : ?>
                                                        <div class="wpiko-default-instructions-note">
                                                            <strong><?php esc_html_e('Using the built-in default until you save your own:', 'wpiko-chatbot'); ?></strong>
                                                            <p><?php echo esc_html(wpiko_chatbot_get_default_main_instructions()); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="tab-content" id="responses-advanced-tab" style="display: none;">
                                        <table class="form-table">
                                            <tr valign="top">
                                                <th scope="row">Specific System Instructions</th>
                                                <td>
                                                    <textarea name="responses_specific_system_instructions"
                                                        id="responses_specific_system_instructions" class="large-text"
                                                        rows="5" placeholder="Examples:
Keep responses under 3 sentences when possible.
For support inquiries, direct users to contact@example.com."><?php echo esc_textarea($instructions['specific']); ?></textarea>
                                                    <p class="description">Enter specific rules and guidelines for how your
                                                        assistant should behave and respond.</p>
                                                    <p class="description"><?php esc_html_e('Knowledge, WooCommerce product and order instructions are managed automatically by the plugin when the relevant features are available. Use this field for additional preferences, such as response length or recommendation style.', 'wpiko-chatbot'); ?></p>
                                                </td>
                                            </tr>
                                            <?php
                                            // Allow extensions to add other advanced configuration controls.
                                            do_action('wpiko_chatbot_responses_advanced_system_instructions');
                                            ?>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Save Configuration</th>
                            <td>
                                <div class="save-config-actions">
                                    <button type="button" id="save_responses_config" class="button button-primary">Save
                                        Configuration</button>
                                    <span id="responses_save_status"></span>
                                </div>
                            </td>
                        </tr>

                    </table>
                </div>

                <!-- Responses Actions Section -->
                <div id="responses-actions-section" style="display: block;">
                    <h3><span class="dashicons dashicons-admin-tools"></span> Train AI Assistant</h3>
                    <p class="description">Deliver better user experiences by shaping your AI assistant's knowledge.</p>

                    <?php
                    // Display Vector Store Information
                    $vector_store_details = wpiko_chatbot_get_responses_vector_store_details();
                    if ($vector_store_details['success']) {
                        $created_date = !empty($vector_store_details['created_at']) ? gmdate('F j, Y', $vector_store_details['created_at']) : 'N/A';
                        $file_counts = $vector_store_details['file_counts'] ?? array();
                        $total_files = isset($file_counts['total']) ? $file_counts['total'] : 0;
                        $completed_files = isset($file_counts['completed']) ? $file_counts['completed'] : 0;
                        $status = $vector_store_details['status'] ?? 'unknown';
                        $status_class = $status === 'completed' ? 'status-active' : 'status-processing';
                        $status_text = ucfirst($status);
                        ?>
                        <div class="vector-store-info">
                            <div class="vector-store-header">
                                <span class="dashicons dashicons-database"></span>
                                <h4>Vector Store Information
                                    <span class="vector-store-info-icon"
                                        title="A Vector Store is a specialized database in OpenAI that stores and indexes your uploaded files (PDFs, documents, etc.) for semantic search. It enables your AI chatbot to search through your knowledge base and provide accurate answers based on your content.">
                                        <span class="dashicons dashicons-info"></span>
                                    </span>
                                </h4>
                                <span
                                    class="vector-store-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                                <button type="button" class="button button-link-refresh vector-store-refresh-btn"
                                    title="Refresh Vector Store Information">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                                <button type="button" class="button button-link-delete vector-store-delete-btn"
                                    title="Delete Vector Store and All Files">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="vector-store-details">
                                <div class="vector-store-item">
                                    <strong>Name:</strong>
                                    <span><?php echo esc_html($vector_store_details['name']); ?></span>
                                </div>
                                <div class="vector-store-item">
                                    <strong>ID:</strong>
                                    <code class="vector-store-id"><?php echo esc_html($vector_store_details['id']); ?></code>
                                </div>
                                <div class="vector-store-item">
                                    <strong>Created:</strong>
                                    <span><?php echo esc_html($created_date); ?></span>
                                </div>
                                <div class="vector-store-item">
                                    <strong>Files:</strong>
                                    <span><?php echo esc_html($completed_files . ' completed / ' . $total_files . ' total'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php } elseif (isset($vector_store_details['not_found']) && $vector_store_details['not_found']) {
                        // Vector Store was deleted from OpenAI dashboard
                        ?>
                        <div class="vector-store-info vector-store-error">
                            <div class="vector-store-header">
                                <span class="dashicons dashicons-warning"></span>
                                <h4>Vector Store Not Found
                                    <span class="vector-store-info-icon"
                                        title="A Vector Store is a specialized database in OpenAI that stores and indexes your uploaded files (PDFs, documents, etc.) for semantic search. It enables your AI chatbot to search through your knowledge base and provide accurate answers based on your content.">
                                        <span class="dashicons dashicons-info"></span>
                                    </span>
                                </h4>
                                <span class="vector-store-status status-error">Error</span>
                                <button type="button" class="button button-link-refresh vector-store-refresh-btn"
                                    title="Refresh Vector Store Information">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                            </div>
                            <div class="vector-store-error-message">
                                <p><strong>The Vector Store was deleted or is no longer accessible.</strong></p>
                                <p>This may have happened if you deleted it from the OpenAI dashboard. To fix this issue:</p>
                                <ol>
                                    <li>Upload a new file using the "File Management" button below, or</li>
                                    <li>Use any of the training tools (Scan Website, Q&A Builder, etc.)</li>
                                </ol>
                                <p>A new Vector Store will be automatically created when you upload your first file.</p>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="assistant-action-buttons">
                        <button type="button" class="button button-secondary wpiko-file-management-button">
                            <span class="dashicons dashicons-upload"></span> File Management
                        </button>

                        <?php
                        // Check if additional response actions are available
                        if (has_action('wpiko_chatbot_responses_scan_website_button')): ?>
                            <?php do_action('wpiko_chatbot_responses_scan_website_button'); ?>
                            <?php do_action('wpiko_chatbot_responses_qa_builder_button'); ?>

                            <?php if (wpiko_chatbot_is_woocommerce_active()): ?>
                                <?php do_action('wpiko_chatbot_responses_woocommerce_integration_button'); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            // Pro training tools, shown locked so free users know they exist.
                            $wpiko_locked_tools = array(
                                'scan' => array(
                                    'icon' => 'dashicons-search',
                                    'label' => __('Scan Website', 'wpiko-chatbot'),
                                    'info' => __('Scan all your website pages and turn them into AI-written questions and answers, with no page limit.', 'wpiko-chatbot'),
                                ),
                                'qa' => array(
                                    'icon' => 'dashicons-editor-help',
                                    'label' => __('Q&A Builder', 'wpiko-chatbot'),
                                    'info' => __('Write and edit exact answers for the questions your visitors ask most, so the chatbot always replies the way you want.', 'wpiko-chatbot'),
                                ),
                            );
                            if (wpiko_chatbot_is_woocommerce_active()) {
                                $wpiko_locked_tools['woo'] = array(
                                    'icon' => 'dashicons-cart',
                                    'label' => __('WooCommerce', 'wpiko-chatbot'),
                                    'info' => __('Keep your products and orders in sync so the chatbot can recommend products, show product cards and answer order questions.', 'wpiko-chatbot'),
                                );
                            }
                            foreach ($wpiko_locked_tools as $wpiko_tool_id => $wpiko_tool) : ?>
                                <button type="button" class="button button-secondary wpiko-pro-locked-button" aria-expanded="false" aria-controls="wpiko-pro-locked-info" data-info="<?php echo esc_attr($wpiko_tool['info']); ?>">
                                    <span class="dashicons <?php echo esc_attr($wpiko_tool['icon']); ?>" aria-hidden="true"></span>
                                    <?php echo esc_html($wpiko_tool['label']); ?>
                                    <span class="wpiko-pro-pill">PRO</span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!has_action('wpiko_chatbot_responses_scan_website_button')): ?>
                        <div id="wpiko-pro-locked-info" class="wpiko-pro-locked-info" hidden>
                            <p class="wpiko-pro-locked-text"></p>
                            <a href="https://wpiko.com/chatbot-pricing/" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e('See WPiko Chatbot Pro', 'wpiko-chatbot'); ?></a>
                        </div>
                        <script>
                            (function () {
                                var info = document.getElementById('wpiko-pro-locked-info');
                                document.querySelectorAll('.wpiko-pro-locked-button').forEach(function (button) {
                                    button.addEventListener('click', function () {
                                        var isOpen = button.getAttribute('aria-expanded') === 'true';
                                        document.querySelectorAll('.wpiko-pro-locked-button').forEach(function (other) {
                                            other.setAttribute('aria-expanded', 'false');
                                        });
                                        if (isOpen) {
                                            info.hidden = true;
                                            return;
                                        }
                                        button.setAttribute('aria-expanded', 'true');
                                        info.querySelector('.wpiko-pro-locked-text').textContent = button.getAttribute('data-info');
                                        info.hidden = false;
                                    });
                                });
                            })();
                        </script>
                    <?php endif; ?>

                    <?php wpiko_chatbot_render_site_knowledge_panel('settings'); ?>

                    <!-- Modal container for file management is defined globally below -->

                    <?php
                    // Check if additional response modal actions are available
                    if (has_action('wpiko_chatbot_responses_scan_website_modal')): ?>
                        <?php do_action('wpiko_chatbot_responses_scan_website_modal'); ?>
                        <?php do_action('wpiko_chatbot_responses_qa_builder_modal'); ?>
                        <?php do_action('wpiko_chatbot_responses_woocommerce_integration_modal'); ?>
                    <?php endif; ?>
                </div>
            </div>


            <input type="hidden" name="action" value="save_ai_configuration">
        </form>

        <!-- Global File Management Modal (visible for Responses API) -->
        <div id="file-management-modal" class="wpiko-modal">
            <div class="wpiko-modal-content">
                <span class="wpiko-modal-close">&times;</span>
                <div id="file-management-container">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            // Define model capabilities
            // value: label mapping for reasoning efforts
            var effortLabels = {
                'none': 'None',
                'low': 'Low',
                'medium': 'Medium',
                'high': 'High',
                'xhigh': 'X-High',
                'max': 'Max'
            };

            var modelConfigs = {
                'gpt-6-astra': { efforts: ['low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-6.1-sol': { efforts: ['low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-6-sol': { efforts: ['none', 'low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-6-luna': { efforts: ['none', 'low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-5.2': { efforts: ['none', 'low', 'medium', 'high', 'xhigh'], defaultEffort: 'none' },
                'gpt-5.4': { efforts: ['none', 'low', 'medium', 'high', 'xhigh'], defaultEffort: 'none' },
                'gpt-5.4-mini': { efforts: ['none', 'low', 'medium', 'high', 'xhigh'], defaultEffort: 'none' },
                'gpt-5.5-2026-04-23': { efforts: ['none', 'low', 'medium', 'high', 'xhigh'], defaultEffort: 'none' },
                'gpt-5.6-sol': { efforts: ['none', 'low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-5.6-terra': { efforts: ['none', 'low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' },
                'gpt-5.6-luna': { efforts: ['none', 'low', 'medium', 'high', 'xhigh', 'max'], defaultEffort: 'medium' }
            };

            var $modelSelect = $('#responses_model');
            var $effortSelect = $('#responses_reasoning_effort');
            var $effortWrapper = $('.wpiko-gpt5-setting'); // This controls both reasoning and verbosity currently

            // Store the initial loaded value to restore/select if valid
            var currentEffort = $effortSelect.val();

            function updateAIConfiguration() {
                var model = $modelSelect.val();

                // Determine if we should show settings
                // Show the controls when the selected model supports configurable reasoning.

                var config = modelConfigs[model];

                if (config) {
                    // Update Reasoning Effort Options
                    var supportedEfforts = config.efforts;
                    var defaultEffort = config.defaultEffort;

                    // Save current selection to see if we can keep it
                    var previousSelection = $effortSelect.val();

                    // Clear current options
                    $effortSelect.empty();

                    // Populate new options
                    $.each(supportedEfforts, function (index, value) {
                        var label = effortLabels[value] || value;
                        $effortSelect.append($('<option>', {
                            value: value,
                            text: label
                        }));
                    });

                    // Attempt to restore previous selection if valid, otherwise use default
                    if (supportedEfforts.includes(previousSelection)) {
                        $effortSelect.val(previousSelection);
                    } else if (supportedEfforts.includes(currentEffort) && firstRun) {
                        // On first run, try to keep PHP loaded value
                        $effortSelect.val(currentEffort);
                    } else {
                        $effortSelect.val(defaultEffort);
                    }

                    $effortWrapper.show();
                } else {
                    // Non-reasoning models do not use these controls.
                    $effortWrapper.hide();
                }
            }

            var firstRun = true;
            updateAIConfiguration();
            firstRun = false;

            $modelSelect.on('change', updateAIConfiguration);
        });
    </script>
    <?php

}

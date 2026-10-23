<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$wpiko_has_pro_tools = has_action('wpiko_chatbot_responses_scan_website_button');
?>

<!-- File Management content (loaded into the shared popup shell) -->
<div id="file-management-section" class="wpiko-modal-stack">
    <section class="wpiko-modal-section" aria-labelledby="wpiko-upload-title">
        <div class="wpiko-modal-section-header">
            <h3 id="wpiko-upload-title" class="wpiko-modal-section-title"><?php esc_html_e('Upload files', 'wpiko-chatbot'); ?></h3>
        </div>
        <div id="file-management-content">
            <div id="file-drop-zone" class="wpiko-dropzone file-upload-container">
                <input type="file" name="assistant_file" id="assistant_file" class="wpiko-dropzone-input" accept=".txt,.pdf,.json,.doc,.docx,.html,.md" multiple aria-describedby="wpiko-dropzone-hint">
                <span class="dashicons dashicons-upload wpiko-dropzone-icon" aria-hidden="true"></span>
                <p class="wpiko-dropzone-title">
                    <?php
                    printf(
                        /* translators: %s: "Choose files" link. */
                        esc_html__('Drop files here or %s', 'wpiko-chatbot'),
                        '<label for="assistant_file" class="wpiko-dropzone-browse">' . esc_html__('choose files', 'wpiko-chatbot') . '</label>'
                    );
                    ?>
                </p>
                <p id="wpiko-dropzone-hint" class="wpiko-dropzone-hint"><?php esc_html_e('PDF, TXT, MD, JSON, DOC, DOCX or HTML · up to 20 MB each', 'wpiko-chatbot'); ?></p>
                <p class="file-name wpiko-dropzone-selection" aria-live="polite"></p>
            </div>
            <div class="wpiko-modal-actions">
                <button type="button" id="upload_file" class="button wpiko-chatbot-settings-save" disabled><?php esc_html_e('Upload files', 'wpiko-chatbot'); ?></button>
            </div>
            <div id="file-upload-status" style="display: none;"></div>
            <div id="file-upload-progress" class="upload-progress-container"></div>
        </div>
    </section>

    <section class="wpiko-modal-section uploaded-files-section" aria-labelledby="wpiko-files-title">
        <div id="file-list-container">
            <div class="wpiko-modal-section-header uploaded-files-heading">
                <h3 id="wpiko-files-title" class="wpiko-modal-section-title"><?php esc_html_e('Your files', 'wpiko-chatbot'); ?> <span class="wpiko-count" id="assistant-files-count" hidden></span></h3>
                <button type="button" class="refresh-file-cache wpiko-icon-button" aria-label="<?php esc_attr_e('Reload the file list', 'wpiko-chatbot'); ?>" title="<?php esc_attr_e('Reload the file list from OpenAI', 'wpiko-chatbot'); ?>">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                </button>
            </div>
            <ul id="assistant-files-list" class="wpiko-file-list"></ul>
            <?php if ($wpiko_has_pro_tools) : ?>
                <p class="description"><?php esc_html_e('Scanned pages, posts and docs, Q&A and WooCommerce files are listed in their own tools.', 'wpiko-chatbot'); ?></p>
            <?php endif; ?>
        </div>
    </section>
</div>

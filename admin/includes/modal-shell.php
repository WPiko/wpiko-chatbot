<?php
/**
 * Shared shell for the Train AI popups (File Management and the Pro tools).
 *
 * Every popup gets the same header (icon, title, description, close button),
 * a scrolling body that receives the AJAX-loaded content, and an optional
 * footer. Content templates can place actions in the footer by wrapping them
 * in <div class="wpiko-modal-footer-slot">; modal-handlers.js moves them there.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('wpiko_chatbot_render_modal')) {
    /**
     * Print a popup shell.
     *
     * @param array $args {
     *     @type string $id           Modal element ID.
     *     @type string $container_id ID of the element that receives the loaded content.
     *     @type string $title        Title shown in the header.
     *     @type string $description  One-line description under the title.
     *     @type string $icon         Dashicons class, e.g. 'dashicons-upload'.
     *     @type string $badge        Optional small badge next to the title, e.g. 'PRO'.
     *     @type string $size         'default' or 'wide'.
     * }
     */
    function wpiko_chatbot_render_modal($args) {
        $args = wp_parse_args($args, array(
            'id'           => '',
            'container_id' => '',
            'title'        => '',
            'description'  => '',
            'icon'         => '',
            'badge'        => '',
            'size'         => 'default',
        ));

        if ($args['id'] === '') {
            return;
        }

        $id       = $args['id'];
        $title_id = $id . '-title';
        $desc_id  = $id . '-description';
        $classes  = 'wpiko-modal wpiko-chatbot-settings-page';
        if ($args['size'] === 'wide') {
            $classes .= ' wpiko-modal--wide';
        }
        ?>
        <div id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($classes); ?>" hidden>
            <div class="wpiko-modal-backdrop" data-wpiko-modal-dismiss></div>
            <div class="wpiko-modal-content" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($title_id); ?>"<?php echo $args['description'] !== '' ? ' aria-describedby="' . esc_attr($desc_id) . '"' : ''; ?> tabindex="-1">
                <header class="wpiko-modal-header">
                    <?php if ($args['icon'] !== '') : ?>
                        <span class="wpiko-modal-icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr($args['icon']); ?>"></span></span>
                    <?php endif; ?>
                    <div class="wpiko-modal-heading">
                        <h2 id="<?php echo esc_attr($title_id); ?>" class="wpiko-modal-title">
                            <?php echo esc_html($args['title']); ?>
                            <?php if ($args['badge'] !== '') : ?>
                                <span class="premium-feature-badge"><?php echo esc_html($args['badge']); ?></span>
                            <?php endif; ?>
                        </h2>
                        <?php if ($args['description'] !== '') : ?>
                            <p id="<?php echo esc_attr($desc_id); ?>" class="wpiko-modal-description"><?php echo esc_html($args['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="wpiko-modal-close" data-wpiko-modal-dismiss aria-label="<?php esc_attr_e('Close', 'wpiko-chatbot'); ?>">
                        <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4z" fill="currentColor"/></svg>
                    </button>
                </header>
                <div class="wpiko-modal-body">
                    <div class="wpiko-modal-loading" role="status" aria-live="polite">
                        <span class="screen-reader-text"><?php esc_html_e('Loading…', 'wpiko-chatbot'); ?></span>
                        <span class="wpiko-skeleton wpiko-skeleton--title" aria-hidden="true"></span>
                        <span class="wpiko-skeleton wpiko-skeleton--line" aria-hidden="true"></span>
                        <span class="wpiko-skeleton wpiko-skeleton--line wpiko-skeleton--short" aria-hidden="true"></span>
                        <span class="wpiko-skeleton wpiko-skeleton--block" aria-hidden="true"></span>
                    </div>
                    <div id="<?php echo esc_attr($args['container_id']); ?>" class="wpiko-modal-body-content" hidden></div>
                </div>
                <footer class="wpiko-modal-footer" hidden>
                    <span class="wpiko-modal-dirty" hidden><?php esc_html_e('Unsaved changes', 'wpiko-chatbot'); ?></span>
                    <div class="wpiko-modal-footer-actions"></div>
                </footer>
            </div>
        </div>
        <?php
    }
}

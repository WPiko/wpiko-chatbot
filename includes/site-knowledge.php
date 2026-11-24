<?php
/**
 * "Quick learn from your pages": turns selected pages into a knowledge file.
 *
 * Builds one Markdown knowledge file from up to
 * WPIKO_CHATBOT_SITE_KNOWLEDGE_PAGE_LIMIT pages and uploads it to the
 * chatbot's OpenAI vector store, so a brand-new chatbot can answer questions
 * about the business straight away. No AI requests are made per page. Pages
 * are converted the same way as Pro's Scan Website (knowledge-markdown.php):
 * exact wording, tables, a short header per page and context lines.
 *
 * The limit is the same for every site (with or without Pro) and keeps the
 * build fast on any host. WPiko AI Chatbot Pro's separate "Scan Website" tool
 * covers whole sites: every page, post and doc as clean Markdown.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WPIKO_CHATBOT_SITE_KNOWLEDGE_PAGE_LIMIT', 10);

/** Maximum characters kept per page, to keep the knowledge file focused. */
define('WPIKO_CHATBOT_SITE_KNOWLEDGE_MAX_CHARS_PER_PAGE', 30000);

/**
 * Maximum number of pages that can be included.
 *
 * @return int
 */
function wpiko_chatbot_site_knowledge_page_limit()
{
    /**
     * Filter the number of pages "Quick learn from your pages" can include.
     *
     * For site owners who want to adjust the limit on their own site.
     *
     * @param int $limit Page limit. Default 10.
     */
    return max(1, (int) apply_filters('wpiko_chatbot_site_knowledge_page_limit', WPIKO_CHATBOT_SITE_KNOWLEDGE_PAGE_LIMIT));
}

/**
 * Pages already covered by another knowledge source (for example WPiko
 * Chatbot Pro's Scan Website). They are left out of the quick-learn file so
 * the same page is never stored twice.
 *
 * @return array<int, string> Page ID => short label.
 */
function wpiko_chatbot_site_knowledge_covered_pages()
{
    /**
     * Filter the pages that are already in the knowledge base another way.
     *
     * @param array<int, string> $covered Page ID => label shown in the list.
     */
    $covered = apply_filters('wpiko_chatbot_site_knowledge_covered_pages', array());
    $clean = array();
    if (is_array($covered)) {
        foreach ($covered as $page_id => $label) {
            $page_id = absint($page_id);
            if ($page_id) {
                $clean[$page_id] = is_string($label) && $label !== '' ? $label : __('Already in the knowledge base', 'wpiko-chatbot');
            }
        }
    }
    return $clean;
}

/**
 * Get the saved state of the site knowledge file.
 *
 * @return array{page_ids: int[], file_id: string, built_at: int, pages: int, chars: int, stale: bool}
 */
function wpiko_chatbot_get_site_knowledge_state()
{
    $state = get_option('wpiko_chatbot_site_knowledge', array());
    $state = is_array($state) ? $state : array();

    return wp_parse_args($state, array(
        'page_ids' => array(),
        'file_id' => '',
        'built_at' => 0,
        'pages' => 0,
        'chars' => 0,
        'stale' => false,
    ));
}

/**
 * Words that usually mark the pages a visitor asks about most.
 *
 * @return string[]
 */
function wpiko_chatbot_site_knowledge_priority_words()
{
    return apply_filters('wpiko_chatbot_site_knowledge_priority_words', array(
        'about', 'contact', 'faq', 'question', 'service', 'pricing', 'price', 'plans', 'shipping', 'delivery',
        'return', 'refund', 'policy', 'terms', 'hours', 'location', 'booking', 'appointment', 'team', 'support',
        'help', 'how', 'product', 'menu', 'warranty',
    ));
}

/**
 * List the pages the admin can choose from, most useful first.
 *
 * @return array[] Each item: id, title, url, words, recommended.
 */
function wpiko_chatbot_site_knowledge_candidates()
{
    $pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 100,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'has_password' => false,
        'suppress_filters' => false,
    ));

    $front_page_id = (int) get_option('page_on_front');
    $priority_words = wpiko_chatbot_site_knowledge_priority_words();
    $covered = wpiko_chatbot_site_knowledge_covered_pages();
    // WooCommerce's Cart, Checkout and My account pages only show each
    // visitor's own cart, form or account. The Shop page is built from the
    // product list, so its saved text is usually empty. None of them have
    // text to learn from.
    $excluded_ids = array();
    foreach (array('cart', 'checkout', 'myaccount', 'shop') as $woo_page) {
        $woo_page_id = function_exists('wc_get_page_id')
            ? (int) wc_get_page_id($woo_page)
            : (int) get_option('woocommerce_' . $woo_page . '_page_id');
        if ($woo_page_id > 0) {
            $excluded_ids[] = $woo_page_id;
        }
    }

    $candidates = array();
    foreach ($pages as $page) {
        if (in_array((int) $page->ID, $excluded_ids, true)) {
            continue;
        }

        $haystack = strtolower($page->post_title . ' ' . $page->post_name);
        $score = 0;
        if ((int) $page->ID === $front_page_id) {
            $score += 100;
        }
        foreach ($priority_words as $word) {
            if (strpos($haystack, $word) !== false) {
                $score += 10;
                break;
            }
        }

        $word_count = str_word_count(wp_strip_all_tags($page->post_content));

        $candidates[] = array(
            'id' => (int) $page->ID,
            'title' => $page->post_title !== '' ? $page->post_title : __('(no title)', 'wpiko-chatbot'),
            'url' => get_permalink($page),
            'words' => $word_count,
            'score' => $score,
            'is_front_page' => (int) $page->ID === $front_page_id,
            'covered' => isset($covered[(int) $page->ID]) ? $covered[(int) $page->ID] : '',
        );
    }

    usort($candidates, function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return $b['words'] <=> $a['words'];
    });

    $limit = wpiko_chatbot_site_knowledge_page_limit();
    $recommended = 0;
    foreach ($candidates as $index => $candidate) {
        $is_recommended = $candidate['covered'] === '' && $recommended < $limit && ($candidate['score'] > 0 || $candidate['words'] > 50);
        $candidates[$index]['recommended'] = $is_recommended;
        if ($is_recommended) {
            $recommended++;
        }
    }

    return $candidates;
}

/**
 * Get a page's text as Markdown, including page-builder content where possible.
 *
 * Very long pages are cut at WPIKO_CHATBOT_SITE_KNOWLEDGE_MAX_CHARS_PER_PAGE
 * characters, at a paragraph break when there is one nearby.
 *
 * @param int $post_id Page ID.
 * @return array{title: string, url: string, text: string, truncated: bool, post: WP_Post}|null
 */
function wpiko_chatbot_site_knowledge_page_text($post_id)
{
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || !empty($post->post_password)) {
        return null;
    }

    $text = wpiko_chatbot_html_to_markdown(wpiko_chatbot_knowledge_post_html($post));

    $max = WPIKO_CHATBOT_SITE_KNOWLEDGE_MAX_CHARS_PER_PAGE;
    $truncated = false;
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length > $max) {
        $truncated = true;
        $text = function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
        $break = function_exists('mb_strrpos') ? mb_strrpos($text, "\n\n", 0, 'UTF-8') : strrpos($text, "\n\n");
        if ($break !== false && $break > $max * 0.8) {
            $text = function_exists('mb_substr') ? mb_substr($text, 0, $break, 'UTF-8') : substr($text, 0, $break);
        }
        $text = rtrim($text);
        // Close a code block that was cut in half.
        if (preg_match_all('/^\s*(?:```|~~~)/m', $text) % 2 === 1) {
            $text .= "\n```";
        }
    }

    return array(
        'title' => wpiko_chatbot_knowledge_title($post),
        'url' => get_permalink($post),
        'text' => $text,
        'truncated' => $truncated,
        'post' => $post,
    );
}

/**
 * Convert HTML to readable text, keeping headings, lists and link targets.
 *
 * Quick learn now uses wpiko_chatbot_html_to_markdown(); this stays for
 * code that called it.
 *
 * @param string $html HTML.
 * @return string
 */
function wpiko_chatbot_html_to_text($html)
{
    // Drop scripts, styles and forms entirely.
    $html = preg_replace('#<(script|style|noscript|form|svg|iframe)[^>]*>.*?</\1>#is', ' ', (string) $html);

    $html = preg_replace_callback(
        '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
        function ($matches) {
            $url = $matches[1];
            $text = trim(wp_strip_all_tags($matches[2]));
            if ($text === '' || strpos($url, '#') === 0 || stripos($url, 'javascript:') === 0) {
                return $text;
            }
            return $text . ' (' . $url . ')';
        },
        $html
    );

    $html = preg_replace('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', "\n\n## $2\n\n", $html);
    $html = preg_replace('/<\/(p|div|section|article|header|footer|tr|table|blockquote)>/i', "\n\n", $html);
    $html = preg_replace('/<br[^>]*>/i', "\n", $html);
    $html = preg_replace('/<li[^>]*>/i', "\n- ", $html);
    $html = preg_replace('/<\/t[dh]>/i', ' | ', $html);

    $text = wp_strip_all_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
    $lines = array_map('trim', explode("\n", $text));
    $text = implode("\n", $lines);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
}

/**
 * Build the Quick learn file for the given pages (without uploading it).
 *
 * @param int[] $page_ids Pages to include, in order.
 * @return array{document: string, included: int[], skipped: string[], truncated: string[]}
 */
function wpiko_chatbot_site_knowledge_document($page_ids)
{
    $site_name = wp_strip_all_tags(get_bloginfo('name'));
    $document = '# ' . sprintf('Website content: %s', $site_name) . "\n";
    $document .= 'Website address: ' . home_url('/') . "\n";
    $tagline = wp_strip_all_tags(get_bloginfo('description'));
    if ($tagline !== '') {
        $document .= 'Tagline: ' . $tagline . "\n";
    }
    $document .= 'This file contains the text of pages on this website. Use it to answer visitor questions and mention the page link when it helps.' . "\n";

    $included = array();
    $skipped = array();
    $truncated = array();
    foreach ((array) $page_ids as $page_id) {
        $page = wpiko_chatbot_site_knowledge_page_text($page_id);
        if (!$page || trim($page['text']) === '') {
            $skipped[] = $page ? $page['title'] : get_the_title($page_id);
            continue;
        }

        $header = '# Page: ' . $page['title'] . "\n" . 'Source: ' . $page['url'];
        $section = wpiko_chatbot_knowledge_section($page['post']);
        if ($section) {
            $header .= "\n" . 'Section: ' . implode(' › ', $section);
        }
        $summary = wpiko_chatbot_knowledge_summary($page['post']);
        if ($summary !== '') {
            $header .= "\n" . 'Summary: ' . $summary;
        }

        // The file holds several pages and OpenAI splits it without looking
        // at where a page starts, so every page gets context lines.
        $body = wpiko_chatbot_markdown_add_context($page['text'], $page['post'], array(
            'header_tokens' => wpiko_chatbot_estimate_tokens($header),
            'source' => 'quick_learn',
            'always' => true,
        ));

        $document .= "\n\n---\n\n" . $header . "\n\n" . $body . "\n";
        $included[] = (int) $page_id;
        if (!empty($page['truncated'])) {
            $truncated[] = $page['title'];
        }
    }

    return array(
        'document' => $document,
        'included' => $included,
        'skipped' => $skipped,
        'truncated' => $truncated,
    );
}

/**
 * Build the knowledge file from the selected pages and upload it.
 *
 * Replaces the previous site knowledge file, if any.
 *
 * @param int[] $page_ids Pages to include.
 * @return array{success: bool, message: string, pages?: int, chars?: int}
 */
function wpiko_chatbot_build_site_knowledge($page_ids)
{
    $limit = wpiko_chatbot_site_knowledge_page_limit();
    $page_ids = array_values(array_unique(array_filter(array_map('absint', (array) $page_ids))));

    // Never store a page twice: skip pages another knowledge source already covers.
    $covered = wpiko_chatbot_site_knowledge_covered_pages();
    $page_ids = array_values(array_filter($page_ids, function ($page_id) use ($covered) {
        return !isset($covered[$page_id]);
    }));

    if (empty($page_ids)) {
        $state = wpiko_chatbot_get_site_knowledge_state();
        if (!empty($state['file_id'])) {
            return wpiko_chatbot_clear_site_knowledge();
        }
        return array('success' => false, 'message' => __('Choose at least one page.', 'wpiko-chatbot'));
    }

    if (count($page_ids) > $limit) {
        $page_ids = array_slice($page_ids, 0, $limit);
    }

    if (wpiko_chatbot_get_api_key() === '') {
        return array('success' => false, 'message' => __('Add your OpenAI API key first.', 'wpiko-chatbot'));
    }

    $site_name = wp_strip_all_tags(get_bloginfo('name'));
    $built = wpiko_chatbot_site_knowledge_document($page_ids);
    $document = $built['document'];
    $included = count($built['included']);
    $included_ids = $built['included'];
    $skipped = $built['skipped'];

    if ($included === 0) {
        return array('success' => false, 'message' => __('The selected pages have no readable text. Try other pages, or upload a document with your business information instead.', 'wpiko-chatbot'));
    }

    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    $temp_file = wp_tempnam('wpiko-website-pages');
    if (!$temp_file || !$wp_filesystem || !$wp_filesystem->put_contents($temp_file, $document)) {
        return array('success' => false, 'message' => __('Could not create a temporary file on the server.', 'wpiko-chatbot'));
    }

    $result = wpiko_chatbot_upload_file_to_responses(array(
        'tmp_name' => $temp_file,
        'name' => 'website-pages-' . sanitize_title($site_name !== '' ? $site_name : 'site') . '.md',
        'type' => 'text/markdown',
    ));
    wp_delete_file($temp_file);

    if (empty($result['success'])) {
        $message = isset($result['message']) ? $result['message'] : __('Upload failed.', 'wpiko-chatbot');
        wpiko_chatbot_log('Site knowledge upload failed: ' . $message, 'error');
        return array('success' => false, 'message' => $message);
    }

    $state = wpiko_chatbot_get_site_knowledge_state();
    if (!empty($state['file_id']) && $state['file_id'] !== $result['file_id']) {
        wpiko_chatbot_delete_responses_file($state['file_id']);
    }

    // Remember only the pages that are really in the file, so the ticks in the
    // panel always match what the chatbot knows.
    update_option('wpiko_chatbot_site_knowledge', array(
        'page_ids' => $included_ids,
        'file_id' => $result['file_id'],
        'built_at' => time(),
        'pages' => $included,
        'chars' => strlen($document),
        'stale' => false,
    ), false);

    if (function_exists('wpiko_chatbot_clear_file_cache')) {
        wpiko_chatbot_clear_file_cache();
    }

    $message = sprintf(
        /* translators: %d: number of pages. */
        _n('Your chatbot has learned %d page. It can now answer questions about it.', 'Your chatbot has learned %d pages. It can now answer questions about them.', $included, 'wpiko-chatbot'),
        $included
    );
    if (!empty($skipped)) {
        $message .= ' ' . sprintf(
            /* translators: %s: page titles. */
            __('Skipped because they have no readable text: %s.', 'wpiko-chatbot'),
            implode(', ', array_map('wp_strip_all_tags', $skipped))
        );
    }
    if (!empty($built['truncated'])) {
        $message .= ' ' . sprintf(
            /* translators: %s: page titles. */
            __('Only the first part of these very long pages was added: %s. Upload the rest as a document in File Management if the chatbot needs it.', 'wpiko-chatbot'),
            implode(', ', array_map('wp_strip_all_tags', $built['truncated']))
        );
    }

    return array(
        'success' => true,
        'message' => $message,
        'pages' => $included,
        'chars' => strlen($document),
    );
}

/**
 * Remove the quick-learn file from the knowledge base.
 *
 * @return array{success: bool, message: string, pages: int}
 */
function wpiko_chatbot_clear_site_knowledge()
{
    $state = wpiko_chatbot_get_site_knowledge_state();

    if (!empty($state['file_id'])) {
        $result = wpiko_chatbot_delete_responses_file($state['file_id']);
        $already_gone = !empty($result['message']) && preg_match('/not found|no such/i', $result['message']);
        if (empty($result['success']) && !$already_gone) {
            return array(
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : __('Could not remove the pages. Please try again.', 'wpiko-chatbot'),
            );
        }
    }

    wpiko_chatbot_reset_site_knowledge_state();

    return array(
        'success' => true,
        'message' => __('Removed. Your chatbot no longer uses these pages.', 'wpiko-chatbot'),
        'pages' => 0,
    );
}

/**
 * Forget the quick-learn file (it no longer exists in OpenAI).
 *
 * @return void
 */
function wpiko_chatbot_reset_site_knowledge_state()
{
    update_option('wpiko_chatbot_site_knowledge', array(
        'page_ids' => array(),
        'file_id' => '',
        'built_at' => 0,
        'pages' => 0,
        'chars' => 0,
        'stale' => false,
    ), false);
}

/**
 * Take pages out of the quick-learn file, for example because WPiko AI Chatbot
 * Pro's Scan Website now covers them. The file is rebuilt without them, or
 * removed when no pages are left.
 *
 * @param int[] $page_ids Pages to take out.
 * @return array|null Result, or null when none of the pages were in the file.
 */
function wpiko_chatbot_site_knowledge_remove_pages($page_ids)
{
    $state = wpiko_chatbot_get_site_knowledge_state();
    if (empty($state['file_id'])) {
        return null;
    }

    $current = array_map('intval', $state['page_ids']);
    $remove = array_map('intval', (array) $page_ids);
    $remaining = array_values(array_diff($current, $remove));

    if (count($remaining) === count($current)) {
        return null;
    }

    if (empty($remaining)) {
        return wpiko_chatbot_clear_site_knowledge();
    }

    return wpiko_chatbot_build_site_knowledge($remaining);
}

/**
 * Keep the saved state honest when the quick-learn file is deleted elsewhere
 * (for example in File Management).
 *
 * @param string $file_id Deleted file ID.
 * @return void
 */
function wpiko_chatbot_site_knowledge_on_file_deleted($file_id)
{
    $state = wpiko_chatbot_get_site_knowledge_state();
    if (!empty($state['file_id']) && $state['file_id'] === $file_id) {
        wpiko_chatbot_reset_site_knowledge_state();
    }
}
add_action('wpiko_chatbot_responses_file_deleted', 'wpiko_chatbot_site_knowledge_on_file_deleted');
add_action('wpiko_chatbot_responses_vector_store_deleted', 'wpiko_chatbot_reset_site_knowledge_state');

/**
 * Mark the knowledge as out of date when an included page changes.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function wpiko_chatbot_site_knowledge_mark_stale($post_id, $post)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_type !== 'page') {
        return;
    }

    $state = wpiko_chatbot_get_site_knowledge_state();
    if (!$state['stale'] && in_array((int) $post_id, array_map('intval', $state['page_ids']), true)) {
        $state['stale'] = true;
        update_option('wpiko_chatbot_site_knowledge', $state, false);
    }
}
add_action('save_post', 'wpiko_chatbot_site_knowledge_mark_stale', 10, 2);

/**
 * Data for the "Quick learn from your pages" panel.
 *
 * @return array
 */
function wpiko_chatbot_site_knowledge_panel_data()
{
    $state = wpiko_chatbot_get_site_knowledge_state();
    $limit = wpiko_chatbot_site_knowledge_page_limit();
    $candidates = wpiko_chatbot_site_knowledge_candidates();
    $covered = wpiko_chatbot_site_knowledge_covered_pages();

    // Ticked = pages the chatbot has actually learned. Nothing is pre-ticked:
    // a ticked page that is not in the knowledge base would suggest the
    // chatbot knows it when it does not.
    $learned = array_values(array_filter(array_map('intval', $state['page_ids']), function ($page_id) use ($covered) {
        return !isset($covered[$page_id]);
    }));

    return array(
        'limit' => $limit,
        'candidates' => $candidates,
        'selected' => array_slice($learned, 0, $limit),
        'learned' => array_slice($learned, 0, $limit),
        'built_at' => (int) $state['built_at'],
        'built_ago' => $state['built_at'] ? human_time_diff((int) $state['built_at']) : '',
        'pages' => (int) $state['pages'],
        'stale' => (bool) $state['stale'],
    );
}

/**
 * AJAX: list pages for the panel.
 *
 * @return void
 */
function wpiko_chatbot_site_knowledge_candidates_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    wp_send_json_success(wpiko_chatbot_site_knowledge_panel_data());
}
add_action('wp_ajax_wpiko_chatbot_site_knowledge_candidates', 'wpiko_chatbot_site_knowledge_candidates_ajax');

/**
 * AJAX: build and upload the knowledge file.
 *
 * @return void
 */
function wpiko_chatbot_site_knowledge_build_ajax()
{
    check_ajax_referer('wpiko_chatbot_nonce', 'security');
    wpiko_chatbot_require_admin_ajax();

    if (function_exists('set_time_limit')) {
        set_time_limit(120);
    }

    $page_ids = isset($_POST['page_ids']) ? array_map('absint', (array) wp_unslash($_POST['page_ids'])) : array();
    $result = wpiko_chatbot_build_site_knowledge($page_ids);

    if ($result['success']) {
        $result['state'] = wpiko_chatbot_site_knowledge_panel_data();
        wp_send_json_success($result);
    }

    wp_send_json_error($result);
}
add_action('wp_ajax_wpiko_chatbot_site_knowledge_build', 'wpiko_chatbot_site_knowledge_build_ajax');

/**
 * Render the "Quick learn from your pages" panel. The list is filled by
 * admin/js/site-knowledge.js.
 *
 * @param string $context Where the panel is shown: 'wizard' or 'settings'.
 * @return void
 */
function wpiko_chatbot_render_site_knowledge_panel($context = 'settings')
{
    $limit = wpiko_chatbot_site_knowledge_page_limit();
    ?>
    <div class="wpiko-site-knowledge" data-context="<?php echo esc_attr($context); ?>">
        <div class="wpiko-site-knowledge-header">
            <div>
                <h3><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> <?php esc_html_e('Quick learn from your pages', 'wpiko-chatbot'); ?></h3>
                <p class="description">
                    <?php esc_html_e('Tick the pages your chatbot should know about.', 'wpiko-chatbot'); ?>
                </p>
            </div>
            <span class="wpiko-site-knowledge-count" aria-live="polite"></span>
        </div>
        <?php
        $pro_licensed = wpiko_chatbot_is_pro_plugin_active() && function_exists('wpiko_chatbot_is_license_active') && wpiko_chatbot_is_license_active();
        $scan_url = add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'ai_configuration', 'open' => 'scan-website'), admin_url('admin.php'));
        ?>
        <div class="wpiko-knowledge-compare" aria-label="<?php esc_attr_e('Quick learn compared with Scan Website', 'wpiko-chatbot'); ?>">
            <div class="wpiko-knowledge-compare-card">
                <strong><span class="dashicons dashicons-media-text" aria-hidden="true"></span> <?php esc_html_e('Quick learn', 'wpiko-chatbot'); ?></strong>
                <ul>
                    <li><?php esc_html_e('Copies the page text', 'wpiko-chatbot'); ?></li>
                    <li><?php esc_html_e('Ready in seconds, no AI cost', 'wpiko-chatbot'); ?></li>
                    <li>
                        <?php
                        /* translators: %d: page limit. */
                        echo esc_html(sprintf(__('Up to %d pages', 'wpiko-chatbot'), $limit));
                        ?>
                    </li>
                </ul>
            </div>
            <div class="wpiko-knowledge-compare-card is-pro">
                <strong><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> <?php esc_html_e('Scan Website', 'wpiko-chatbot'); ?> <span class="wpiko-pro-pill">PRO</span></strong>
                <ul>
                    <li><?php esc_html_e('Pages, posts and docs as clean Markdown', 'wpiko-chatbot'); ?></li>
                    <li><?php esc_html_e('Exact wording, no AI cost, can update automatically', 'wpiko-chatbot'); ?></li>
                    <li><?php esc_html_e('Any number of pages, posts and docs', 'wpiko-chatbot'); ?></li>
                </ul>
                <?php if ($pro_licensed) : ?>
                    <a href="<?php echo esc_url($scan_url); ?>" class="button button-small wpiko-open-scan-website"><?php esc_html_e('Open Scan Website', 'wpiko-chatbot'); ?></a>
                <?php else : ?>
                    <a href="https://wpiko.com/chatbot-pricing/" class="button button-small" target="_blank" rel="noopener noreferrer"><?php esc_html_e('See Pro', 'wpiko-chatbot'); ?></a>
                <?php endif; ?>
            </div>
            <p class="wpiko-knowledge-compare-tip">
                <?php esc_html_e('A page scanned with Scan Website is removed from Quick learn, so nothing is stored twice.', 'wpiko-chatbot'); ?>
            </p>
        </div>
        <div class="wpiko-site-knowledge-status" aria-live="polite"></div>
        <div class="wpiko-site-knowledge-list" role="group" aria-label="<?php esc_attr_e('Pages to learn from', 'wpiko-chatbot'); ?>">
            <p class="wpiko-site-knowledge-loading"><span class="spinner is-active"></span> <?php esc_html_e('Loading your pages…', 'wpiko-chatbot'); ?></p>
        </div>
        <div class="wpiko-site-knowledge-actions">
            <button type="button" class="button button-primary wpiko-site-knowledge-build"><?php esc_html_e('Teach my chatbot these pages', 'wpiko-chatbot'); ?></button>
            <span class="wpiko-site-knowledge-pending" aria-live="polite"></span>
            <span class="wpiko-site-knowledge-result" aria-live="polite"></span>
        </div>
    </div>
    <?php
}

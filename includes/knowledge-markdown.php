<?php
/**
 * Knowledge files: clean Markdown from WordPress content.
 *
 * Turns pages, posts and docs into Markdown with their exact wording and adds
 * short context lines to long text, so every piece the knowledge search
 * returns says which page and section it comes from. Used by Quick learn in
 * WPiko AI Chatbot and by Scan Website in WPiko AI Chatbot Pro, so both add
 * the same kind of file to the knowledge base.
 *
 * This file is in WPiko AI Chatbot as includes/knowledge-markdown.php and, for
 * sites that still run an older WPiko AI Chatbot, in WPiko AI Chatbot Pro as
 * includes/knowledge-markdown-backup.php. Keep the two files identical.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!function_exists('wpiko_chatbot_html_to_markdown')) :

/**
 * Plain one-line text: no tags, entities decoded, spaces collapsed.
 *
 * @param mixed $text Text.
 * @return string
 */
function wpiko_chatbot_knowledge_plain($text)
{
    if (!is_scalar($text)) {
        return '';
    }
    $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/**
 * An item's own title, without the "Private:" or "Protected:" prefix
 * WordPress adds on the site.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function wpiko_chatbot_knowledge_title($post)
{
    $title = wpiko_chatbot_knowledge_plain($post->post_title);
    return $title !== '' ? $title : '(no title)';
}

/**
 * Rendered HTML of an item's main content, the way visitors see it.
 *
 * Reads Elementor content (kept outside the editor content), renders blocks
 * and shortcodes, and leaves out the chatbot's own shortcode.
 *
 * @param WP_Post $post Post.
 * @return string HTML.
 */
function wpiko_chatbot_knowledge_post_html($post)
{
    $html = '';

    // Elementor keeps its content outside post_content.
    if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin') && get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
        $elementor = \Elementor\Plugin::instance();
        if (isset($elementor->frontend) && method_exists($elementor->frontend, 'get_builder_content_for_display')) {
            $html = (string) $elementor->frontend->get_builder_content_for_display($post->ID);
        }
    }

    if ($html === '') {
        $html = (string) $post->post_content;
        if (function_exists('has_blocks') && has_blocks($html)) {
            $html = do_blocks($html);
        } else {
            // Classic editor content keeps paragraphs as blank lines.
            $html = wpautop($html);
        }
        // Do not put the chatbot inside its own knowledge.
        $html = preg_replace('/\[wpiko_chatbot(?:\s[^\]]*)?\]/', '', $html);
        $html = do_shortcode($html);
    }

    return $html;
}

/**
 * Titles of an item's parent pages (or parent docs), top level first.
 *
 * @param WP_Post $post Post.
 * @return string[] Empty when the item has no parent.
 */
function wpiko_chatbot_knowledge_section($post)
{
    $titles = array();
    if (is_post_type_hierarchical($post->post_type)) {
        foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
            $title = wpiko_chatbot_knowledge_plain(get_post_field('post_title', $ancestor_id));
            if ($title !== '') {
                $titles[] = $title;
            }
        }
    }

    /**
     * Filter the section of an item in knowledge files: the titles shown in
     * its "Section:" line and at the start of its context lines.
     *
     * @param string[] $titles Parent titles, top level first.
     * @param WP_Post  $post   The item.
     */
    $titles = (array) apply_filters('wpiko_chatbot_knowledge_section', $titles, $post);
    return array_values(array_filter(array_map('wpiko_chatbot_knowledge_plain', $titles), 'strlen'));
}

/**
 * One sentence about the item that the site owner wrote: the SEO meta
 * description, or the manual excerpt when there is none.
 *
 * @param WP_Post $post Post.
 * @return string Empty when there is none.
 */
function wpiko_chatbot_knowledge_summary($post)
{
    $summary = '';
    // WPiko SEO, Yoast SEO, Rank Math, SEOPress, All in One SEO.
    $keys = array('_wpiko_seo_meta_description', '_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc', '_aioseo_description');
    foreach ($keys as $key) {
        $value = wpiko_chatbot_knowledge_plain(get_post_meta($post->ID, $key, true));
        // Templates such as %%excerpt%%, %excerpt% or #post_excerpt are only filled in on the site.
        if ($value === '' || preg_match('/%%?[a-z_]+%%?|#[a-z]+_[a-z_]+|#tagline\b/i', $value)) {
            continue;
        }
        $summary = $value;
        break;
    }
    if ($summary === '' && has_excerpt($post)) {
        $summary = wpiko_chatbot_knowledge_plain($post->post_excerpt);
    }

    /**
     * Filter the "Summary:" line of an item in knowledge files. Return an
     * empty string to leave it out.
     *
     * @param string  $summary SEO description or manual excerpt.
     * @param WP_Post $post    The item.
     */
    $summary = wpiko_chatbot_knowledge_plain(apply_filters('wpiko_chatbot_knowledge_summary', $summary, $post));
    if (function_exists('mb_strlen') && mb_strlen($summary, 'UTF-8') > 300) {
        $summary = rtrim(mb_substr($summary, 0, 299, 'UTF-8')) . '…';
    }
    return $summary;
}

/**
 * The first part of an item's context lines: its section (parent pages), or
 * the terms of its first public taxonomy (for example a docs plugin's
 * Product, or a post's category).
 *
 * @param WP_Post $post Post.
 * @return string Empty when the item has neither.
 */
function wpiko_chatbot_knowledge_context_root($post)
{
    $section = wpiko_chatbot_knowledge_section($post);
    if ($section) {
        return implode(' › ', $section);
    }
    foreach (get_object_taxonomies($post, 'objects') as $taxonomy) {
        // Tags are keywords, not a place in the site.
        if (empty($taxonomy->public) || empty($taxonomy->show_ui) || in_array($taxonomy->name, array('post_format', 'post_tag'), true)) {
            continue;
        }
        $terms = get_the_terms($post, $taxonomy->name);
        if (!$terms || is_wp_error($terms)) {
            continue;
        }
        $names = array();
        foreach ($terms as $term) {
            if ($taxonomy->name === 'category' && (int) $term->term_id === (int) get_option('default_category')) {
                continue;
            }
            $names[] = wpiko_chatbot_knowledge_plain($term->name);
        }
        $names = array_filter($names, 'strlen');
        if ($names) {
            return implode(', ', array_slice($names, 0, 3));
        }
    }
    return '';
}

/**
 * How OpenAI splits a knowledge file into pieces for the knowledge search.
 *
 * Set on upload instead of relying on OpenAI's default, so the spacing of
 * context lines always matches the piece size.
 *
 * @param string $source 'quick_learn' or 'scan_website'.
 * @return array{max_chunk_size_tokens: int, chunk_overlap_tokens: int}
 */
function wpiko_chatbot_knowledge_chunking($source = '')
{
    /**
     * Filter the piece size of Quick learn and Scan Website files.
     *
     * @param array  $chunking max_chunk_size_tokens (100-4096, default 800) and
     *                         chunk_overlap_tokens (at most half, default 400).
     * @param string $source   'quick_learn' or 'scan_website'.
     */
    $chunking = (array) apply_filters('wpiko_chatbot_knowledge_chunking', array(
        'max_chunk_size_tokens' => 800,
        'chunk_overlap_tokens' => 400,
    ), $source);
    $size = isset($chunking['max_chunk_size_tokens']) ? (int) $chunking['max_chunk_size_tokens'] : 800;
    $size = min(4096, max(100, $size));
    $overlap = isset($chunking['chunk_overlap_tokens']) ? (int) $chunking['chunk_overlap_tokens'] : 400;
    $overlap = min((int) floor($size / 2), max(0, $overlap));
    return array('max_chunk_size_tokens' => $size, 'chunk_overlap_tokens' => $overlap);
}

/**
 * Rough, cautious token count for any language.
 *
 * Counts about 3.5 Latin characters per token and treats other scripts
 * (Greek, Cyrillic, Arabic, CJK...) as denser, so the estimate is on the
 * high side and context lines are never spaced too far apart.
 *
 * @param string $text Text.
 * @return int
 */
function wpiko_chatbot_estimate_tokens($text)
{
    $text = (string) $text;
    if ($text === '') {
        return 0;
    }
    $ascii = strlen((string) preg_replace('/[^\x00-\x7F]/', '', $text));
    $other = preg_match_all('/[^\x00-\x7F]/u', $text);
    if ($other === false) {
        // Invalid UTF-8: count the remaining bytes in pairs.
        $other = (strlen($text) - $ascii) / 2;
    }
    return (int) ceil($ascii / 3.5 + $other * 0.75);
}

/**
 * Split Markdown into blocks separated by blank lines, keeping code fences whole.
 *
 * @param string $markdown Markdown.
 * @return string[]
 */
function wpiko_chatbot_markdown_blocks($markdown)
{
    $blocks = array();
    $current = array();
    $fence = '';
    foreach (explode("\n", (string) $markdown) as $line) {
        if ($fence !== '') {
            $current[] = $line;
            if (preg_match('/^\s*(`{3,}|~{3,})\s*$/', $line, $m) && $m[1][0] === $fence[0] && strlen($m[1]) >= strlen($fence)) {
                $fence = '';
            }
            continue;
        }
        if (preg_match('/^\s*(`{3,}|~{3,})/', $line, $m)) {
            $fence = $m[1];
            $current[] = $line;
            continue;
        }
        if (trim($line) === '') {
            if ($current) {
                $blocks[] = implode("\n", $current);
                $current = array();
            }
            continue;
        }
        $current[] = $line;
    }
    if ($current) {
        $blocks[] = implode("\n", $current);
    }
    return $blocks;
}

/**
 * Heading text without Markdown links, emphasis or code marks.
 *
 * @param string $heading Heading text.
 * @return string
 */
function wpiko_chatbot_markdown_heading_text($heading)
{
    $text = preg_replace('/!?\[((?:\\\\.|[^\]])*)\]\([^)]*\)/', '$1', (string) $heading);
    $text = str_replace(array('\\[', '\\]', '**', '__', '~~', '`'), array('[', ']', '', '', '', ''), $text);
    $text = trim(preg_replace('/(^|\s)[*_]+|[*_]+(\s|$)/', '$1$2', $text));
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 80) {
        $text = rtrim(mb_substr($text, 0, 79, 'UTF-8')) . '…';
    }
    return $text;
}

/**
 * Add short context lines to the Markdown text of an item.
 *
 * OpenAI splits each file into pieces of a few hundred words and the
 * knowledge search returns pieces, not files. Only the first piece has the
 * header, so a piece from the middle of a long text would not say which
 * page, product or section it belongs to. This adds a line such as
 * "(WPiko AI Chatbot › Website Content Scanning › Bulk scan)" under the
 * headings, and between paragraphs where a section is long, close enough
 * together that the pieces of the text contain one.
 *
 * @param string  $body Markdown text of the item (without its header).
 * @param WP_Post $post The item.
 * @param array   $args {
 *     @type int    $header_tokens Estimated tokens of the header before the text. Default 0.
 *     @type string $source        'quick_learn' or 'scan_website'.
 *     @type bool   $always        Add lines even when the whole file fits in one piece
 *                                 (for files that hold several items). Default false.
 * }
 * @return string
 */
function wpiko_chatbot_markdown_add_context($body, $post, $args = array())
{
    $args = wp_parse_args($args, array('header_tokens' => 0, 'source' => '', 'always' => false));
    $chunking = wpiko_chatbot_knowledge_chunking($args['source']);
    $size = $chunking['max_chunk_size_tokens'];
    $header_tokens = max(0, (int) $args['header_tokens']);

    // The whole file fits in one piece: the header already gives the context.
    if (!$args['always'] && $header_tokens + wpiko_chatbot_estimate_tokens($body) <= (int) floor($size * 0.85)) {
        return $body;
    }

    // A line at least this often keeps one in every piece, with room for the
    // line itself and for estimates that are a little low.
    $gap = max(60, (int) floor($size * 0.6));
    // After a new heading, but not more often than this (short FAQ sections).
    $min_gap = (int) floor($gap / 3);

    $root = wpiko_chatbot_knowledge_context_root($post);
    $title = wpiko_chatbot_knowledge_title($post);
    $source = $args['source'];

    $out = array();
    $headings = array();
    $since = $header_tokens;
    $heading_changed = false;

    $add_line = function () use (&$out, &$since, &$heading_changed, &$headings, $post, $root, $title, $source) {
        $parts = array();
        if ($root !== '') {
            $parts[] = $root;
        }
        $parts[] = $title;
        foreach (array_slice($headings, -3) as $heading) {
            if ($heading['text'] !== '') {
                $parts[] = $heading['text'];
            }
        }

        /**
         * Filter the parts of a context line, joined with " › ".
         *
         * @param string[] $parts    Section or first taxonomy terms, title, then the current headings.
         * @param WP_Post  $post     The item.
         * @param string[] $headings The current headings, top level first.
         * @param string   $source   'quick_learn' or 'scan_website'.
         */
        $parts = (array) apply_filters('wpiko_chatbot_knowledge_context_parts', $parts, $post, wp_list_pluck($headings, 'text'), $source);
        $parts = array_filter(array_map('wpiko_chatbot_knowledge_plain', $parts), 'strlen');
        if ($parts) {
            $out[] = '(' . implode(' › ', $parts) . ')';
        }
        $since = 0;
        $heading_changed = false;
    };

    foreach (wpiko_chatbot_markdown_blocks($body) as $block) {
        $tokens = wpiko_chatbot_estimate_tokens($block);

        if (strpos($block, "\n") === false && preg_match('/^(#{2,6})[ \t]+(.+)$/', $block, $m)) {
            $level = strlen($m[1]);
            while ($headings && $headings[count($headings) - 1]['level'] >= $level) {
                array_pop($headings);
            }
            $headings[] = array('level' => $level, 'text' => wpiko_chatbot_markdown_heading_text($m[2]));
            $heading_changed = true;
            $out[] = $block;
            $since += $tokens;
            continue;
        }

        if (($heading_changed && $since >= $min_gap) || ($since > 0 && $since + $tokens > $gap)) {
            $add_line();
        }

        // A long list is split between its top-level items.
        if ($tokens > $gap && preg_match('/^([-*+]|\d+[.)])[ \t]/', $block)) {
            $group = array();
            $item = array();
            $lines = explode("\n", $block);
            $lines[] = null; // End marker.
            foreach ($lines as $line) {
                if ($line !== null && (!$item || !preg_match('/^([-*+]|\d+[.)])[ \t]/', $line))) {
                    $item[] = $line;
                    continue;
                }
                $item_text = implode("\n", $item);
                $item_tokens = wpiko_chatbot_estimate_tokens($item_text);
                if ($group && $since + $item_tokens > $gap) {
                    $out[] = implode("\n", $group);
                    $group = array();
                    $add_line();
                }
                $group[] = $item_text;
                $since += $item_tokens;
                $item = $line === null ? array() : array($line);
            }
            if ($group) {
                $out[] = implode("\n", $group);
            }
            continue;
        }

        $out[] = $block;
        $since += $tokens;
    }

    return implode("\n\n", $out);
}

/**
 * Convert rendered HTML to Markdown.
 *
 * Keeps headings, paragraphs, lists (nested), links, emphasis, code blocks,
 * tables, quotes and accordions (details/summary). Drops scripts, styles,
 * forms, menus, buttons, embeds, hidden text and layout-only markup.
 *
 * @param string $html HTML.
 * @return string Markdown.
 */
function wpiko_chatbot_html_to_markdown($html)
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        // Rare hosts without PHP's DOM extension: plain text keeps the content.
        $text = html_entity_decode(wp_strip_all_tags(preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n\n", $html)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }
    $converter = new WPiko_Chatbot_Html_To_Markdown();
    return $converter->convert($html);
}

/**
 * DOM-based HTML to Markdown converter.
 */
class WPiko_Chatbot_Html_To_Markdown {

    /** @var string[] Code blocks, kept out of whitespace clean-up until the end. */
    private $blocks = array();

    /** @var string Unique token prefix for protected blocks. */
    private $token;

    /** @var string Site origin for relative links, e.g. https://example.com */
    private $origin = '';

    /** Elements whose content is never knowledge. */
    private static $skip = array(
        'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'object', 'embed',
        'video', 'audio', 'source', 'track', 'map', 'form', 'input', 'select', 'option', 'textarea',
        'button', 'label', 'nav', 'head', 'meta', 'link', 'title',
    );

    /**
     * Inline elements that can sit inside a word (W<span>Piko</span>, H<sub>2</sub>O):
     * never separated from the text next to them.
     */
    private static $in_word = array('span', 'sup', 'sub', 'abbr', 'wbr', 'br');

    /** Block-level containers: their content is separated from what is around it. */
    private static $block = array(
        'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'address', 'figure',
        'center', 'body', 'html', 'fieldset', 'hgroup',
    );

    public function __construct() {
        $this->token = 'WPIKOMD' . substr(md5(uniqid('', true)), 0, 8) . 'X';
        if (function_exists('home_url')) {
            $parts = wp_parse_url(home_url('/'));
            if (!empty($parts['scheme']) && !empty($parts['host'])) {
                $this->origin = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
            }
        }
    }

    /**
     * @param string $html HTML fragment.
     * @return string Markdown.
     */
    public function convert($html) {
        $this->blocks = array();

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the fragment as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $root = $child;
                break;
            }
        }
        if (!$root) {
            return '';
        }

        $markdown = $this->children($root);
        return $this->finish($markdown);
    }

    /* ------------------------------------------------------------------ */

    private function children(DOMNode $node) {
        $out = '';
        $after_element = false;
        foreach ($node->childNodes as $child) {
            $this->append($out, $child, $this->node($child), $after_element);
        }
        return $out;
    }

    /**
     * Add a child's Markdown, with a space where the HTML relies on CSS.
     *
     * Themes and page builders often put elements right next to each other
     * and separate them only with CSS (a bold label shown on its own line,
     * menu links spaced with a gap, a <small> note under a table cell). In
     * plain text they would run together ("**Lifetime**Pay once",
     * "[Features](…)[Pricing](…)"), so a space is added between an element
     * and a word that touches it.
     *
     * @param string  $out           Markdown so far.
     * @param DOMNode $child         The child the part comes from.
     * @param string  $part          The child's Markdown.
     * @param bool    $after_element Whether the previous part came from an element.
     */
    private function append(&$out, DOMNode $child, $part, &$after_element) {
        if ($part === '') {
            return;
        }
        $is_element = $child->nodeType === XML_ELEMENT_NODE && !in_array(strtolower($child->nodeName), self::$in_word, true);
        if (($is_element || $after_element) && $out !== ''
            && preg_match('/(?:[\p{L}\p{N}](?:\*\*|\*|~~|`)?|\]\([^()\s]*\)|>)$/u', $out)
            && preg_match('/^(?:\*\*|\*|~~|`|!?\[|<(?=https?:))?[\p{L}\p{N}]/u', $part)) {
            $out .= ' ';
        }
        $out .= $part;
        $after_element = $is_element;
    }

    private function node(DOMNode $node) {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            // Collapse HTML whitespace (including non-breaking spaces) to one space.
            return preg_replace('/[\s\x{00A0}]+/u', ' ', $node->nodeValue);
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        /** @var DOMElement $node */
        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::$skip, true) || $this->is_hidden($node)) {
            return '';
        }

        switch ($tag) {
            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                // The file title is the only level-1 heading.
                $level = max(2, (int) substr($tag, 1));
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n" . str_repeat('#', $level) . ' ' . $text . "\n\n";

            case 'p':
                return "\n\n" . $this->children($node) . "\n\n";

            case 'br':
                return "\n";

            case 'hr':
                return "\n\n---\n\n";

            case 'strong': case 'b':
                return $this->wrap($this->children($node), '**');

            case 'em': case 'i': case 'cite':
                return $this->wrap($this->children($node), '*');

            case 'del': case 's': case 'strike':
                return $this->wrap($this->children($node), '~~');

            case 'code': case 'kbd': case 'samp': case 'tt':
                return $this->inline_code($node->textContent);

            case 'pre':
                return $this->pre($node);

            case 'a':
                return $this->link($node);

            case 'img':
                return $this->image($node);

            case 'ul': case 'ol':
                return "\n\n" . $this->list_block($node) . "\n\n";

            case 'li':
                // A list item outside a list: treat as a bullet.
                return "\n- " . trim($this->children($node)) . "\n";

            case 'blockquote':
                return $this->blockquote($node);

            case 'table':
                return $this->table($node);

            case 'details':
                return $this->details($node);

            case 'summary':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n**" . $text . "**\n\n";

            case 'figcaption':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n*" . $text . "*\n\n";

            case 'dl':
                return "\n\n" . $this->children($node) . "\n\n";

            case 'dt':
                $text = $this->inline_text($node);
                return $text === '' ? '' : "\n\n**" . $text . "**\n";

            case 'dd':
                return "\n" . trim($this->children($node)) . "\n\n";

            case 'sup':
                return '^' . trim($this->children($node));

            case 'sub':
                return '~' . trim($this->children($node));
        }

        if (in_array($tag, self::$block, true)) {
            return "\n\n" . $this->children($node) . "\n\n";
        }

        // span, mark, abbr, small, time, unknown tags: keep the text.
        return $this->children($node);
    }

    /* ------------------------------------------------------------------ */

    private function is_hidden(DOMElement $node) {
        if ($node->hasAttribute('hidden') || $node->getAttribute('aria-hidden') === 'true') {
            return true;
        }
        $style = strtolower(str_replace(' ', '', $node->getAttribute('style')));
        if ($style !== '' && (strpos($style, 'display:none') !== false || strpos($style, 'visibility:hidden') !== false)) {
            return true;
        }
        $class = ' ' . $node->getAttribute('class') . ' ';
        return strpos($class, ' screen-reader-text ') !== false || strpos($class, ' sr-only ') !== false;
    }

    /** Inline content of an element on one line. */
    private function inline_text(DOMNode $node) {
        $text = $this->children($node);
        $text = preg_replace('/\s*\n+\s*/', ' ', $text);
        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    private function wrap($text, $marker) {
        // Keep surrounding spaces outside the markers: "**bold** text", not "**bold **text".
        if (trim($text) === '') {
            return $text;
        }
        preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $m);
        return $m[1] . $marker . $m[2] . $marker . $m[3];
    }

    private function inline_code($code) {
        $code = preg_replace('/[\s\x{00A0}]+/u', ' ', $code);
        if (trim($code) === '') {
            return '';
        }
        $fence = strpos($code, '`') !== false ? '``' : '`';
        $pad = $fence === '``' ? ' ' : '';
        return $fence . $pad . $code . $pad . $fence;
    }

    private function pre(DOMElement $node) {
        $code = rtrim($node->textContent, "\r\n");
        $code = ltrim($code, "\r\n");
        if (trim($code) === '') {
            return '';
        }
        $language = $this->code_language($node);
        if ($language === '') {
            foreach ($node->getElementsByTagName('code') as $inner) {
                $language = $this->code_language($inner);
                break;
            }
        }
        $fence = '```';
        while (strpos($code, $fence) !== false) {
            $fence .= '`';
        }
        return "\n\n" . $this->protect($fence . $language . "\n" . $code . "\n" . $fence) . "\n\n";
    }

    private function code_language(DOMElement $node) {
        if (preg_match('/(?:^|\s)(?:language|lang)-([A-Za-z0-9_+#.-]+)/', $node->getAttribute('class'), $m)) {
            return strtolower($m[1]);
        }
        $lang = $node->getAttribute('data-lang') ?: $node->getAttribute('data-language');
        return preg_match('/^[A-Za-z0-9_+#.-]+$/', $lang) ? strtolower($lang) : '';
    }

    private function link(DOMElement $node) {
        $text = $this->inline_text($node);
        $href = $this->absolute_url(trim($node->getAttribute('href')));

        if ($href === '' || $href[0] === '#' || preg_match('/^(javascript|data|vbscript):/i', $href)) {
            return $text;
        }
        if ($text === '') {
            // Image-only links: the image's alt text names the link.
            return '';
        }
        if ($text === $href && preg_match('#^https?://#i', $href)) {
            return '<' . $href . '>';
        }
        return '[' . str_replace(array('[', ']'), array('\[', '\]'), $text) . '](' . $this->escape_url($href) . ')';
    }

    private function image(DOMElement $node) {
        $alt = trim(preg_replace('/\s+/', ' ', $node->getAttribute('alt')));
        $src = $this->absolute_url(trim($node->getAttribute('src')));
        // Decorative images (no alt text) carry no knowledge.
        if ($alt === '' || $src === '' || stripos($src, 'data:') === 0) {
            return '';
        }
        return '![' . str_replace(array('[', ']'), '', $alt) . '](' . $this->escape_url($src) . ')';
    }

    private function absolute_url($url) {
        if ($url === '' || $this->origin === '') {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if ($url[0] === '/') {
            return $this->origin . $url;
        }
        return $url;
    }

    private function escape_url($url) {
        return str_replace(array(' ', '(', ')'), array('%20', '%28', '%29'), $url);
    }

    private function list_block(DOMElement $list, $depth = 0) {
        $ordered = strtolower($list->nodeName) === 'ol';
        $number = $ordered && $list->hasAttribute('start') ? (int) $list->getAttribute('start') : 1;
        $lines = array();

        foreach ($list->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'ul' || $tag === 'ol') {
                // Invalid but common: a list directly inside a list.
                $lines[] = $this->indent($this->list_block($child, $depth + 1), '  ');
                continue;
            }
            if ($tag !== 'li' || $this->is_hidden($child)) {
                continue;
            }

            $marker = $ordered ? $number . '. ' : '- ';
            $number++;

            $content = '';
            $after_element = false;
            foreach ($child->childNodes as $part) {
                $part_tag = $part->nodeType === XML_ELEMENT_NODE ? strtolower($part->nodeName) : '';
                if ($part_tag === 'ul' || $part_tag === 'ol') {
                    $content .= "\n" . $this->list_block($part, $depth + 1) . "\n";
                    $after_element = false;
                } else {
                    $this->append($content, $part, $this->node($part), $after_element);
                }
            }
            // Items stay tight: one line per paragraph inside the item.
            $content = trim(preg_replace("/[ \t]*\n[ \t]*\n+[ \t]*/", "\n", $content));
            $content = preg_replace('/[ \t]{2,}/', ' ', $content);

            $lines[] = $marker . $this->indent($content, str_repeat(' ', strlen($marker)), false);
        }

        return implode("\n", $lines);
    }

    /** Indent every line (or every line after the first). */
    private function indent($text, $pad, $first = true) {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (($i > 0 || $first) && $line !== '') {
                $lines[$i] = $pad . $line;
            }
        }
        return implode("\n", $lines);
    }

    private function blockquote(DOMElement $node) {
        $content = trim($this->tidy($this->children($node)));
        if ($content === '') {
            return '';
        }
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            $lines[$i] = $line === '' ? '>' : '> ' . $line;
        }
        return "\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function details(DOMElement $node) {
        $summary = '';
        $body = '';
        $after_element = false;
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'summary' && $summary === '') {
                $summary = $this->inline_text($child);
            } else {
                $this->append($body, $child, $this->node($child), $after_element);
            }
        }
        $out = "\n\n";
        if ($summary !== '') {
            $out .= '**' . $summary . "**\n\n";
        }
        return $out . $body . "\n\n";
    }

    private function table(DOMElement $table) {
        $rows = array();
        $header_row = null;
        $width = 0;

        foreach ($this->table_rows($table) as $tr) {
            $cells = array();
            $all_th = true;
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $cell_tag = strtolower($cell->nodeName);
                if ($cell_tag !== 'td' && $cell_tag !== 'th') {
                    continue;
                }
                if ($cell_tag === 'td') {
                    $all_th = false;
                }
                $text = $this->tidy($this->children($cell));
                $text = trim(preg_replace('/\s*\n+\s*/', ' ', $text));
                $text = str_replace('|', '\|', $text);
                $cells[] = $text;
                $span = (int) $cell->getAttribute('colspan');
                for ($s = 1; $s < $span && $s < 20; $s++) {
                    $cells[] = '';
                }
            }
            if (!$cells) {
                continue;
            }
            $width = max($width, count($cells));
            $in_head = strtolower($tr->parentNode->nodeName) === 'thead';
            if ($header_row === null && !$rows && ($in_head || $all_th)) {
                $header_row = $cells;
            } else {
                $rows[] = $cells;
            }
        }

        if ($width === 0) {
            return '';
        }

        // Layout tables with a single column read better as paragraphs.
        if ($width === 1) {
            $lines = array();
            foreach (array_merge($header_row ? array($header_row) : array(), $rows) as $cells) {
                if ($cells[0] !== '') {
                    $lines[] = $cells[0];
                }
            }
            return "\n\n" . implode("\n\n", $lines) . "\n\n";
        }

        if ($header_row === null) {
            // Markdown tables need a header row: use the first row.
            $header_row = array_shift($rows);
        }

        $out = array();
        $out[] = $this->table_line($header_row, $width);
        $out[] = '|' . str_repeat(' --- |', $width);
        foreach ($rows as $cells) {
            $out[] = $this->table_line($cells, $width);
        }
        $caption = '';
        foreach ($table->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'caption') {
                $caption = $this->inline_text($child);
            }
        }
        return "\n\n" . ($caption !== '' ? '**' . $caption . "**\n\n" : '') . $this->protect(implode("\n", $out)) . "\n\n";
    }

    private function table_rows(DOMElement $table) {
        $rows = array();
        foreach ($table->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'tr') {
                $rows[] = $child;
            } elseif (in_array($tag, array('thead', 'tbody', 'tfoot'), true)) {
                foreach ($child->childNodes as $tr) {
                    if ($tr->nodeType === XML_ELEMENT_NODE && strtolower($tr->nodeName) === 'tr') {
                        $rows[] = $tr;
                    }
                }
            }
        }
        return $rows;
    }

    private function table_line($cells, $width) {
        $cells = array_pad($cells, $width, '');
        return '| ' . implode(' | ', $cells) . ' |';
    }

    /* ------------------------------------------------------------------ */

    /** Keep a finished block (code, table) away from whitespace clean-up. */
    private function protect($text) {
        $key = $this->token . count($this->blocks);
        $this->blocks[$key] = $text;
        return $key;
    }

    /** Whitespace clean-up of a fragment. */
    private function tidy($text) {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $line = preg_replace('/(?<=\S)[ \t]{2,}/', ' ', $line);
            // Keep the indentation of list items and their continuation lines
            // (two or more spaces); drop the single spaces left by HTML whitespace.
            $lines[$i] = preg_match('/^ {2,}\S/', $line) ? rtrim($line) : trim($line);
        }
        $text = implode("\n", $lines);
        return preg_replace("/\n{3,}/", "\n\n", $text);
    }

    private function finish($markdown) {
        $markdown = $this->tidy($markdown);

        // Lines that are only emphasis markers left by empty elements.
        $markdown = preg_replace('/^(\*\*|\*|~~)\s*\1$/m', '', $markdown);
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);

        // Restore protected blocks, newest first (they can nest).
        for ($i = count($this->blocks) - 1; $i >= 0; $i--) {
            $key = $this->token . $i;
            $markdown = str_replace($key, $this->blocks[$key], $markdown);
        }

        return trim($markdown);
    }
}

endif;

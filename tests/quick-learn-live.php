<?php
// Live check, LOCAL SITE ONLY: open as an admin at
// /wp-content/plugins/wpiko-chatbot/tests/quick-learn-live.php
// Checks the shared knowledge Markdown converter (includes/knowledge-markdown.php)
// and builds a Quick learn file from temporary test pages, without uploading it.
// No OpenAI requests; the test pages are removed afterwards. ?show=1 prints the
// built file. ?remote=1 also asks OpenAI how the current Quick learn file is
// split (one read-only request). Keep tests out of release ZIPs.
require dirname(__DIR__, 4) . '/wp-load.php';

if (!current_user_can('manage_options') || !preg_match('/\.(local|test|localhost)$/', (string) wp_parse_url(home_url(), PHP_URL_HOST))) {
    status_header(403);
    exit('Admins on a local site only.');
}
header('Content-Type: text/plain; charset=utf-8');

$results = array();
$check = function ($ok, $label) use (&$results) { $results[] = ($ok ? 'PASS' : 'FAIL') . '  ' . $label; };
$md = function ($html) { return wpiko_chatbot_html_to_markdown($html); };

// Converter.
$check(strpos($md('<p><strong>Lifetime</strong>Pay once, use it forever</p>'), '**Lifetime** Pay once') !== false, 'Bold label and the text after it are separated');
$menu = $md('<p><a href="/a">Features</a><a href="/b">How it works</a><a href="https://example.com/c">Pro</a></p>');
$check(preg_match('/\[Features\]\([^)]*\) \[How it works\]\([^)]*\) \[Pro\]/', $menu) === 1, 'Links next to each other are separated');
$table = $md('<table><tr><th>Feature</th><th>Free</th></tr><tr><td>Quick learn from your pages<small>Up to 10 pages</small></td><td>Yes</td></tr></table>');
$check(strpos($table, '| Feature | Free |') !== false && strpos($table, '| Quick learn from your pages Up to 10 pages | Yes |') !== false, 'Tables keep their header row; a <small> note is separated');
$check(strpos($md('<ul><li><strong>Fast</strong>setup</li></ul>'), '- **Fast** setup') !== false, 'List item label and text are separated');
$inline = $md('<p>Water is H<sub>2</sub>O, Einstein wrote E=mc<sup>2</sup>, the brand is W<span class="x">Piko</span>. Click <a href="/x">here</a>. See <em>this</em> page.</p>');
$check(strpos($inline, 'H~2O') !== false && strpos($inline, 'mc^2') !== false && strpos($inline, 'WPiko') !== false, 'Sub, sup and span inside a word stay joined');
$check(preg_match('/\[here\]\([^)]*\)\. See \*this\* page\./', $inline) === 1, 'Normal sentences are unchanged');
$noise = $md('<nav><a href="/">Home</a></nav><p>Visible text<span class="screen-reader-text">Hidden text</span></p><div style="display:none">Hidden div</div><form><button>Send</button></form>');
$check(strpos($noise, 'Visible text') !== false && strpos($noise, 'Hidden') === false && strpos($noise, 'Home') === false && strpos($noise, 'Send') === false, 'Menus, hidden text, forms and buttons are left out');

// Quick learn file from temporary pages.
$created = array();
$make = function ($args) use (&$created) {
    $id = wp_insert_post(wp_slash($args + array('post_type' => 'page', 'post_status' => 'publish')), true);
    if (!is_wp_error($id)) {
        $created[] = $id;
    }
    return $id;
};
$paragraph = '<p>Our test shop opens Monday to Friday from 9:00 to 17:00 and ships within two working days to Cyprus and Greece.</p>';
$parent = $make(array('post_title' => 'WPiko QL Services'));
$long = $make(array('post_title' => 'WPiko QL Delivery', 'post_parent' => $parent, 'post_content' => '<h2>Shipping</h2>' . str_repeat($paragraph, 12) . '<h2>Returns</h2>' . str_repeat($paragraph, 12) . '[wpiko_chatbot]'));
$short = $make(array('post_title' => 'WPiko QL Contact', 'post_content' => '<p>Call +357 22 000000.</p>'));
$huge = $make(array('post_title' => 'WPiko QL Huge', 'post_content' => str_repeat($paragraph, 400)));
update_post_meta($long, '_yoast_wpseo_metadesc', 'Delivery times and returns for the test shop.');
$check(!array_filter(array($parent, $long, $short, $huge), 'is_wp_error'), 'Test pages created');

$built = wpiko_chatbot_site_knowledge_document(array($long, $short, $huge));
$doc = $built['document'];
$check($built['included'] === array((int) $long, (int) $short, (int) $huge), 'All three pages included');
$check(strpos($doc, "# Page: WPiko QL Delivery\nSource: ") !== false, 'Page header with Source');
$check(strpos($doc, "\nSection: WPiko QL Services\nSummary: Delivery times and returns for the test shop.\n") !== false, 'Page header with Section and Summary');
$check(strpos($doc, '## Shipping') !== false && strpos($doc, '## Returns') !== false, 'Headings kept');
$check(strpos($doc, '(WPiko QL Services › WPiko QL Delivery › Returns)') !== false, 'Context lines name the section, page and heading');
$check(strpos($doc, 'wpiko_chatbot]') === false, 'The chatbot shortcode is left out');
$check(strpos($doc, 'Call +357 22 000000.') !== false, 'Short page text kept exactly');
$check($built['truncated'] === array('WPiko QL Huge'), 'A very long page is reported as cut');
$huge_part = substr($doc, strpos($doc, '# Page: WPiko QL Huge'));
$check(preg_match('/Greece\.\s*$/', $huge_part) === 1, 'The cut page ends at a paragraph break');
$check(substr_count($huge_part, '(WPiko QL Huge)') >= 15, 'The long page gets context lines all the way (' . substr_count($huge_part, '(WPiko QL Huge)') . ')');

// Upload request: piece size for Quick learn and Scan Website files only.
$quick = wpiko_chatbot_vector_file_attachment('file-test', 'website-pages-wpiko.md');
$scan = wpiko_chatbot_vector_file_attachment('file-test', 'page_Pricing-94.md');
$other = wpiko_chatbot_vector_file_attachment('file-test', 'price-list.pdf');
$check(isset($quick['chunking_strategy']['static']) && $quick['chunking_strategy']['static'] === array('max_chunk_size_tokens' => 800, 'chunk_overlap_tokens' => 400), 'Quick learn file is uploaded with 800/400 pieces');
$check(isset($scan['chunking_strategy']['static']['max_chunk_size_tokens']), 'Scan Website files are uploaded with a fixed piece size');
$check(!isset($other['chunking_strategy']) && $other['file_id'] === 'file-test', 'Other files keep OpenAI\'s default pieces');
$evil = function ($data) { $data['file_id'] = 'file-other'; $data['attributes'] = array('wpiko_scope' => 'private_orders'); return $data; };
add_filter('wpiko_chatbot_vector_file_attachment', $evil, 99);
$guarded = wpiko_chatbot_vector_file_attachment('file-test', 'notes.txt');
remove_filter('wpiko_chatbot_vector_file_attachment', $evil, 99);
$check($guarded['file_id'] === 'file-test' && (!isset($guarded['attributes']) || $guarded['attributes']['wpiko_scope'] !== 'private_orders'), 'A filter cannot change the file or its privacy label');

if (!empty($_GET['remote'])) {
    $state = wpiko_chatbot_get_site_knowledge_state();
    $store = get_option('wpiko_chatbot_responses_vector_store_id', '');
    if ($state['file_id'] && $store) {
        $response = wp_remote_get('https://api.openai.com/v1/vector_stores/' . rawurlencode($store) . '/files/' . rawurlencode($state['file_id']), array(
            'headers' => array('Authorization' => 'Bearer ' . wpiko_chatbot_get_api_key()),
            'timeout' => 20,
        ));
        $file = json_decode(wp_remote_retrieve_body($response), true);
        $static = isset($file['chunking_strategy']['static']) ? $file['chunking_strategy']['static'] : array();
        $check(isset($static['max_chunk_size_tokens']) && (int) $static['max_chunk_size_tokens'] === 800 && (int) $static['chunk_overlap_tokens'] === 400 && isset($file['status']) && $file['status'] === 'completed', 'OpenAI stored the Quick learn file (' . $state['pages'] . ' pages) with 800/400 pieces, status ' . (isset($file['status']) ? $file['status'] : 'unknown'));
    } else {
        $results[] = 'SKIP  no Quick learn file yet';
    }
}

if (!empty($_GET['show'])) {
    echo $doc . "\n\n";
}

foreach (array_reverse($created) as $id) {
    wp_delete_post($id, true);
}

echo implode("\n", $results) . "\n\n";
$failed = count(preg_grep('/^FAIL/', $results));
echo $failed ? $failed . " FAILED\n" : "ALL PASS\n";

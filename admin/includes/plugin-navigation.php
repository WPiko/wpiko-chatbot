<?php
/**
 * Shared desktop and mobile admin navigation.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render an outline icon, retaining Dashicons support for add-on tabs.
 *
 * @param string $icon Registered icon class.
 */
function wpiko_chatbot_render_navigation_icon($icon) {
    $paths = array(
        'dashicons-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'dashicons-flag' => '<path d="M5 21V3c5-4 9 4 14 0v10c-5 4-9-4-14 0"/>',
        'dashicons-admin-network' => '<circle cx="8" cy="8" r="5"/><path d="m12 12 9 9m-3-3 3-3m-6 0 3-3"/>',
        'dashicons-admin-generic' => '<path d="m9 3-1 3-3 1-2 5 2 5 3 1 1 3h6l1-3 3-1 2-5-2-5-3-1-1-3z"/><circle cx="12" cy="12" r="3"/>',
        'dashicons-admin-settings' => '<rect x="3" y="3" width="18" height="14" rx="3"/><path d="m8 17-3 4v-5M7 8h10M7 12h6"/>',
        'dashicons-format-status' => '<path d="M21 11a8 8 0 0 1-8 8H7l-5 3 2-6a8 8 0 0 1 5-12"/><path d="m17 2 1 4 4 1-4 1-1 4-1-4-4-1 4-1z"/>',
        'dashicons-shortcode' => '<path d="m8 6-6 6 6 6m8-12 6 6-6 6M14 3l-4 18"/>',
        'dashicons-admin-customizer' => '<path d="m14 3 7 7-9 9-7-7zM5 12l-3 9 9-3M14 7l3 3"/>',
        'dashicons-menu' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'dashicons-editor-help' => '<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 4m0 4h.01"/>',
        'dashicons-warning' => '<path d="m12 3 10 18H2zM12 9v5m0 3h.01"/>',
        'dashicons-format-chat' => '<path d="M21 11a8 8 0 0 1-8 8H7l-5 3 2-6a8 8 0 1 1 17-5zM8 10h8M8 14h5"/>',
        'dashicons-shield' => '<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6z"/><path d="m8 12 3 3 5-6"/>',
        'dashicons-admin-tools' => '<path d="M14 6a5 5 0 0 0-6 6l-6 6 4 4 6-6a5 5 0 0 0 6-6l-4 4-4-4z"/>',
        'dashicons-email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'dashicons-email-alt' => '<path d="m3 9 9-7 9 7v12H3zM3 9l9 7 9-7M3 21l6-7m12 7-6-7"/>',
        'dashicons-align-full-width' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M8 13h8M8 17h5"/>',
        'dashicons-chart-bar' => '<path d="M3 3v18h18M8 16v-5m5 5V7m5 9V4"/>',
        'dashicons-smartphone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 5h4m-2 14h.01"/>',
        'wpiko-placement' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 8h18"/><circle cx="16" cy="15" r="2"/>',
        'dashicons-unlock' => '<rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0m-4 9v3"/>',
    );
    if (isset($paths[$icon])) {
        echo '<svg class="wpiko-chatbot-nav-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$icon] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
    } else {
        echo '<span class="wpiko-chatbot-nav-icon dashicons ' . esc_attr(sanitize_html_class($icon)) . '" aria-hidden="true"></span>';
    }
}

/**
 * Collect the sidebar entries for the current user, grouped for display.
 *
 * Screens that share a `section` collapse into one entry linking to the
 * first of them. Screens with `nav` set to `'footer'` are returned apart.
 *
 * @return array{groups: array<string, array<string, mixed>>, entries: array<string, array<string, array<string, mixed>>>, footer: array<string, array<string, mixed>>}
 */
function wpiko_chatbot_get_navigation_entries() {
    $groups = wpiko_chatbot_get_admin_tab_groups();
    $sections = wpiko_chatbot_get_admin_nav_sections();
    $entries = array();
    $footer = array();

    foreach (wpiko_chatbot_get_admin_tabs() as $tab_id => $tab) {
        if (!current_user_can($tab['capability'] ?? 'manage_options')) {
            continue;
        }

        $nav = $tab['nav'] ?? true;
        if ($nav === false) {
            continue;
        }
        if ($nav === 'footer') {
            $footer[$tab_id] = $tab;
            continue;
        }

        $section_id = $tab['section'] ?? '';
        if ($section_id !== '' && isset($sections[$section_id])) {
            $section = $sections[$section_id];
            $group = $section['group'] ?? 'more';
            $key = 'section:' . $section_id;
            if (!isset($entries[$group][$key])) {
                $entries[$group][$key] = array(
                    'label' => $section['label'] ?? $tab['label'],
                    'icon' => $section['icon'] ?? ($tab['icon'] ?? 'dashicons-plus'),
                    'order' => (int) ($section['order'] ?? 100),
                    'pro' => !empty($section['pro']),
                    'tabs' => array(),
                );
            }
            $entries[$group][$key]['tabs'][$tab_id] = (int) ($tab['order'] ?? 100);
            continue;
        }

        $group = $tab['group'] ?? 'more';
        $entries[$group][$tab_id] = array(
            'label' => $tab['label'],
            'icon' => $tab['icon'] ?? 'dashicons-plus',
            'order' => (int) ($tab['order'] ?? 100),
            'pro' => !empty($tab['pro']),
            'separator_before' => !empty($tab['separator_before']),
            'tabs' => array($tab_id => 0),
        );
    }

    // Entries in an unregistered group remain visible under More.
    if (!isset($groups['more'])) {
        $groups['more'] = array('label' => __('More', 'wpiko-chatbot'), 'order' => 90);
    }
    foreach (array_keys($entries) as $group_id) {
        if ($group_id !== '' && !isset($groups[$group_id])) {
            $entries['more'] = array_merge($entries['more'] ?? array(), $entries[$group_id]);
            unset($entries[$group_id]);
        }
    }

    foreach ($entries as $group_id => $group_entries) {
        foreach ($group_entries as $key => $entry) {
            asort($entry['tabs']);
            $entries[$group_id][$key]['tabs'] = array_keys($entry['tabs']);
            $entries[$group_id][$key]['tab'] = $entries[$group_id][$key]['tabs'][0];
        }
        uasort($entries[$group_id], function ($first, $second) {
            return $first['order'] <=> $second['order'];
        });
    }

    $groups = array('' => array('label' => '')) + $groups;

    return array('groups' => $groups, 'entries' => $entries, 'footer' => $footer);
}

/**
 * Render the same capability-filtered tab registry on desktop and mobile.
 *
 * @param string $current Active tab.
 * @param string $id Unique navigation ID.
 * @param string $class Navigation class.
 */
function wpiko_chatbot_render_navigation($current, $id, $class) {
    $navigation = wpiko_chatbot_get_navigation_entries();
    ?>
    <nav id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($class); ?> wpiko-chatbot-navigation" aria-label="<?php esc_attr_e('WPiko AI Chatbot screens', 'wpiko-chatbot'); ?>">
        <?php foreach ($navigation['groups'] as $group_id => $group): ?>
            <?php if (empty($navigation['entries'][$group_id])) { continue; } ?>
            <div class="wpiko-chatbot-navgroup<?php echo !empty($group['class']) ? ' ' . esc_attr(sanitize_html_class($group['class'])) : ''; ?>">
                <?php if (($group['label'] ?? '') !== ''): ?>
                    <span class="wpiko-chatbot-navgroup-label">
                        <span><?php echo esc_html($group['label']); ?></span>
                        <?php if (!empty($group['badge'])): ?><span class="wpiko-chatbot-nav-badge"><?php echo esc_html($group['badge']); ?></span><?php endif; ?>
                    </span>
                <?php endif; ?>
                <?php foreach ($navigation['entries'][$group_id] as $entry): ?>
                    <?php
                    $is_current = in_array($current, $entry['tabs'], true);
                    $classes = 'wpiko-chatbot-nav-link';
                    $classes .= $is_current ? ' is-current' : '';
                    $classes .= !empty($entry['separator_before']) ? ' has-separator-before' : '';
                    ?>
                    <a href="<?php echo esc_url(wpiko_chatbot_get_admin_tab_url($entry['tab'])); ?>" class="<?php echo esc_attr($classes); ?>"<?php if ($is_current): ?> aria-current="page"<?php endif; ?>>
                        <?php wpiko_chatbot_render_navigation_icon($entry['icon']); ?>
                        <span class="wpiko-chatbot-nav-label"><?php echo esc_html($entry['label']); ?></span>
                        <?php if (!empty($entry['pro'])): ?>
                            <span class="wpiko-chatbot-nav-pro"><?php esc_html_e('PRO', 'wpiko-chatbot'); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!empty($navigation['footer'])): ?>
            <div class="wpiko-chatbot-navfooter">
                <?php foreach ($navigation['footer'] as $tab_id => $tab): ?>
                    <a href="<?php echo esc_url(wpiko_chatbot_get_admin_tab_url($tab_id)); ?>" class="wpiko-chatbot-nav-link<?php echo $current === $tab_id ? ' is-current' : ''; ?>"<?php if ($current === $tab_id): ?> aria-current="page"<?php endif; ?>>
                        <?php wpiko_chatbot_render_navigation_icon($tab['icon'] ?? 'dashicons-plus'); ?>
                        <span class="wpiko-chatbot-nav-label"><?php echo esc_html($tab['nav_label'] ?? $tab['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </nav>
    <?php
}

/**
 * Render the sub-tab row for screens that share a sidebar entry.
 *
 * @param string $current Active tab.
 */
function wpiko_chatbot_render_subnavigation($current) {
    $tabs = wpiko_chatbot_get_admin_tabs();
    $section_id = $tabs[$current]['section'] ?? '';
    $sections = wpiko_chatbot_get_admin_nav_sections();
    if ($section_id === '' || !isset($sections[$section_id])) {
        return;
    }

    $siblings = array();
    foreach ($tabs as $tab_id => $tab) {
        if (($tab['section'] ?? '') !== $section_id || ($tab['nav'] ?? true) === false) {
            continue;
        }
        if (!current_user_can($tab['capability'] ?? 'manage_options')) {
            continue;
        }
        $siblings[$tab_id] = $tab;
    }
    if (count($siblings) < 2) {
        return;
    }
    uasort($siblings, function ($first, $second) {
        return (int) ($first['order'] ?? 100) <=> (int) ($second['order'] ?? 100);
    });
    ?>
    <nav class="wpiko-chatbot-subnav" aria-label="<?php echo esc_attr($sections[$section_id]['label']); ?>">
        <?php foreach ($siblings as $tab_id => $tab): ?>
            <a href="<?php echo esc_url(wpiko_chatbot_get_admin_tab_url($tab_id)); ?>" class="wpiko-chatbot-subnav-link<?php echo $current === $tab_id ? ' is-current' : ''; ?>"<?php if ($current === $tab_id): ?> aria-current="page"<?php endif; ?>><?php echo esc_html($tab['section_label'] ?? $tab['label']); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
}

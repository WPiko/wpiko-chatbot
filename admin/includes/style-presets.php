<?php
/**
 * Chatbot appearance presets.
 *
 * Every preset is a complete set of colors for the chatbot interface. Presets are
 * applied in the browser (they simply fill the color fields of the Chatbot Style
 * form), so nothing is written to the database until the user saves.
 *
 * To add a new preset, either append it to the array in
 * wpiko_chatbot_get_style_presets() or register it from anywhere with the
 * 'wpiko_chatbot_style_presets' filter:
 *
 *     add_filter('wpiko_chatbot_style_presets', function ($presets) {
 *         $presets['forest'] = array(
 *             'label'       => 'Forest',
 *             'description' => 'Deep greens with a calm, natural feel.',
 *             'colors'      => array('primary_color' => '#2f6f4f', ...),
 *         );
 *         return $presets;
 *     });
 *
 * Any color a preset leaves out falls back to the default preset, so partial
 * presets are safe.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * ID of the preset used as the plugin default and as the fallback for any color
 * a preset does not define.
 *
 * @return string
 */
function wpiko_chatbot_get_default_style_preset_id()
{
    return 'aurora-blue';
}

/**
 * Map of style form field names to the option they are stored in.
 *
 * The field name is what presets use as their color keys and what the form
 * inputs are named, which keeps the preset definitions readable.
 *
 * @return array<string, string>
 */
function wpiko_chatbot_get_style_color_fields()
{
    return array(
        'primary_color' => 'wpiko_chatbot_primary_color',
        'primary_text_color' => 'wpiko_chatbot_primary_text_color',
        'chatbot_background_color' => 'wpiko_chatbot_chatbot_background_color',
        'chatbot_header_color' => 'wpiko_chatbot_chatbot_header_color',
        'chatbot_border_color' => 'wpiko_chatbot_chatbot_border_color',
        'chatbot_name_color' => 'wpiko_chatbot_chatbot_name_color',
        'user_background_color' => 'wpiko_chatbot_user_background_color',
        'user_text_color' => 'wpiko_chatbot_user_text_color',
        'bot_background_color' => 'wpiko_chatbot_bot_background_color',
        'bot_text_color' => 'wpiko_chatbot_bot_text_color',
        'admin_message_label_color' => 'wpiko_chatbot_admin_message_label_color',
        'admin_message_background_color' => 'wpiko_chatbot_admin_message_background_color',
        'admin_message_border_color' => 'wpiko_chatbot_admin_message_border_color',
        'admin_message_text_color' => 'wpiko_chatbot_admin_message_text_color',
        'icon_color' => 'wpiko_chatbot_icon_color',
        'input_background_color' => 'wpiko_chatbot_input_background_color',
        'floating_text_bg_color' => 'wpiko_chatbot_floating_text_bg_color',
    );
}

/**
 * Color fields that belong to the Pro live chat takeover feature.
 *
 * @return string[]
 */
function wpiko_chatbot_get_pro_style_color_fields()
{
    return array(
        'admin_message_label_color',
        'admin_message_background_color',
        'admin_message_border_color',
        'admin_message_text_color',
    );
}

/**
 * All available appearance presets.
 *
 * @return array<string, array>
 */
function wpiko_chatbot_get_style_presets()
{
    $presets = array(

        'aurora-blue' => array(
            'label' => 'Aurora Blue',
            'description' => 'Bright and airy with a confident blue accent. The plugin default.',
            'colors' => array(
                'primary_color' => '#0968fe',
                'primary_text_color' => '#ffffff',
                'chatbot_background_color' => '#ffffff',
                'chatbot_header_color' => '#fbfbfb',
                'chatbot_border_color' => '#f1f1f1',
                'chatbot_name_color' => '#333333',
                'user_background_color' => '#ffffff',
                'user_text_color' => '#444444',
                'bot_background_color' => '#fbfbfb',
                'bot_text_color' => '#333333',
                'admin_message_label_color' => '#0968fe',
                'admin_message_background_color' => '#f5f9ff',
                'admin_message_border_color' => '#e1edff',
                'admin_message_text_color' => '#002358',
                'icon_color' => '#707070',
                'input_background_color' => '#fbfbfb',
                'floating_text_bg_color' => '#ffffff',
            ),
            'settings' => array(
                'show_user_border' => '1',
            ),
        ),

        'midnight' => array(
            'label' => 'Midnight',
            'description' => 'Deep navy with a teal accent, for dark websites.',
            'colors' => array(
                'primary_color' => '#03b5aa',
                'primary_text_color' => '#ffffff',
                'chatbot_background_color' => '#121a28',
                'chatbot_header_color' => '#182233',
                'chatbot_border_color' => '#253346',
                'chatbot_name_color' => '#f1f5f9',
                'user_background_color' => '#1f2c40',
                'user_text_color' => '#e6ecf5',
                'bot_background_color' => '#1b2739',
                'bot_text_color' => '#cfd9e6',
                'admin_message_label_color' => '#03b5aa',
                'admin_message_background_color' => '#12312f',
                'admin_message_border_color' => '#1d4a47',
                'admin_message_text_color' => '#d7f2ef',
                'icon_color' => '#94a3b8',
                'input_background_color' => '#182233',
                'floating_text_bg_color' => '#1f2c40',
            ),
            'settings' => array(
                // A light outline around user messages is too loud on a dark surface.
                'show_user_border' => '0',
            ),
        ),

        'terracotta' => array(
            'label' => 'Terracotta',
            'description' => 'Warm clay and sand tones for a soft, editorial look.',
            'colors' => array(
                'primary_color' => '#c2603f',
                'primary_text_color' => '#ffffff',
                'chatbot_background_color' => '#fffaf5',
                'chatbot_header_color' => '#fdf2ea',
                'chatbot_border_color' => '#f0dfd2',
                'chatbot_name_color' => '#4a2f22',
                'user_background_color' => '#ffffff',
                'user_text_color' => '#5c4033',
                'bot_background_color' => '#fdf2ea',
                'bot_text_color' => '#4a2f22',
                'admin_message_label_color' => '#c2603f',
                'admin_message_background_color' => '#fdf0e7',
                'admin_message_border_color' => '#f3dcce',
                'admin_message_text_color' => '#6b3a22',
                'icon_color' => '#a1795f',
                'input_background_color' => '#fdf2ea',
                'floating_text_bg_color' => '#fffaf5',
            ),
            'settings' => array(
                'show_user_border' => '1',
            ),
        ),

    );

    $presets = apply_filters('wpiko_chatbot_style_presets', $presets);

    return is_array($presets) ? $presets : array();
}

/**
 * Presets normalized so every entry has a label, a description, a full set of
 * colors and a settings array. Colors missing from a preset inherit from the
 * default preset, and unknown color keys are dropped.
 *
 * @return array<string, array>
 */
function wpiko_chatbot_get_normalized_style_presets()
{
    $presets = wpiko_chatbot_get_style_presets();
    $fields = wpiko_chatbot_get_style_color_fields();
    $default_id = wpiko_chatbot_get_default_style_preset_id();

    $fallback_colors = array();
    if (isset($presets[$default_id]['colors']) && is_array($presets[$default_id]['colors'])) {
        $fallback_colors = $presets[$default_id]['colors'];
    }

    $normalized = array();

    foreach ($presets as $id => $preset) {
        if (!is_array($preset)) {
            continue;
        }

        $id = sanitize_key($id);
        if ($id === '') {
            continue;
        }

        $colors = array();
        $preset_colors = isset($preset['colors']) && is_array($preset['colors']) ? $preset['colors'] : array();

        foreach ($fields as $field => $option_name) {
            $value = isset($preset_colors[$field]) ? $preset_colors[$field] : '';
            $value = sanitize_hex_color($value);

            if (!$value && isset($fallback_colors[$field])) {
                $value = sanitize_hex_color($fallback_colors[$field]);
            }

            $colors[$field] = $value ? $value : '#ffffff';
        }

        $show_user_border = '1';
        if (isset($preset['settings']['show_user_border'])) {
            $show_user_border = $preset['settings']['show_user_border'] ? '1' : '0';
        }

        $normalized[$id] = array(
            'label' => isset($preset['label']) ? (string) $preset['label'] : ucwords(str_replace('-', ' ', $id)),
            'description' => isset($preset['description']) ? (string) $preset['description'] : '',
            'colors' => $colors,
            'settings' => array(
                'show_user_border' => $show_user_border,
            ),
        );
    }

    return $normalized;
}

/**
 * A single normalized preset, or null when the ID is unknown.
 *
 * @param string $preset_id
 * @return array|null
 */
function wpiko_chatbot_get_style_preset($preset_id)
{
    $presets = wpiko_chatbot_get_normalized_style_presets();
    $preset_id = sanitize_key($preset_id);

    return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
}

/**
 * The default preset, falling back to the first registered one if the default
 * ID was filtered away.
 *
 * @return array
 */
function wpiko_chatbot_get_default_style_preset()
{
    $presets = wpiko_chatbot_get_normalized_style_presets();
    $default_id = wpiko_chatbot_get_default_style_preset_id();

    if (isset($presets[$default_id])) {
        return $presets[$default_id];
    }

    return !empty($presets) ? reset($presets) : array('colors' => array(), 'settings' => array('show_user_border' => '1'));
}

/**
 * Default value for a single style color option.
 *
 * @param string $field Field name, e.g. 'primary_color'.
 * @return string Hex color.
 */
function wpiko_chatbot_get_style_color_default($field)
{
    $preset = wpiko_chatbot_get_default_style_preset();

    return isset($preset['colors'][$field]) ? $preset['colors'][$field] : '#ffffff';
}

/**
 * Current saved value for a single style color option.
 *
 * @param string $field Field name, e.g. 'primary_color'.
 * @return string Hex color.
 */
function wpiko_chatbot_get_style_color($field)
{
    $fields = wpiko_chatbot_get_style_color_fields();

    if (!isset($fields[$field])) {
        return '#ffffff';
    }

    $value = sanitize_hex_color(get_option($fields[$field], ''));

    return $value ? $value : wpiko_chatbot_get_style_color_default($field);
}

/**
 * Write a preset's colors to the options table.
 *
 * @param string $preset_id
 * @param bool   $include_pro Whether the Pro live chat colors should be written too.
 * @return bool True when the preset existed and was applied.
 */
function wpiko_chatbot_apply_style_preset($preset_id, $include_pro = true)
{
    $preset = wpiko_chatbot_get_style_preset($preset_id);

    if (!$preset) {
        return false;
    }

    $fields = wpiko_chatbot_get_style_color_fields();
    $pro_fields = wpiko_chatbot_get_pro_style_color_fields();

    foreach ($fields as $field => $option_name) {
        if (!$include_pro && in_array($field, $pro_fields, true)) {
            continue;
        }

        update_option($option_name, $preset['colors'][$field]);
    }

    update_option('wpiko_chatbot_show_user_border', $preset['settings']['show_user_border']);
    update_option('wpiko_chatbot_style_preset', sanitize_key($preset_id));

    return true;
}

/**
 * Which preset the saved colors currently match, or 'custom' when they match none.
 *
 * The stored option is only trusted when the colors still line up, so editing a
 * single color by hand correctly reports the style as custom.
 *
 * @return string Preset ID or 'custom'.
 */
function wpiko_chatbot_get_active_style_preset_id()
{
    $presets = wpiko_chatbot_get_normalized_style_presets();
    $fields = wpiko_chatbot_get_style_color_fields();

    // The Pro takeover colors are neither shown nor saved when Pro is inactive,
    // so they must not take part in the comparison.
    if (!defined('WPIKO_CHATBOT_PRO_VERSION')) {
        foreach (wpiko_chatbot_get_pro_style_color_fields() as $pro_field) {
            unset($fields[$pro_field]);
        }
    }

    $current = array();
    foreach ($fields as $field => $option_name) {
        $current[$field] = wpiko_chatbot_get_style_color($field);
    }
    $current_border = get_option('wpiko_chatbot_show_user_border', '1') === '1' ? '1' : '0';

    $matches_preset = function ($preset) use ($fields, $current, $current_border) {
        if ($preset['settings']['show_user_border'] !== $current_border) {
            return false;
        }

        foreach ($fields as $field => $option_name) {
            if (strtolower($preset['colors'][$field]) !== strtolower($current[$field])) {
                return false;
            }
        }

        return true;
    };

    // Fast path: trust the last saved preset, but only while its colors still hold.
    $stored = sanitize_key(get_option('wpiko_chatbot_style_preset', ''));
    if ($stored && isset($presets[$stored]) && $matches_preset($presets[$stored])) {
        return $stored;
    }

    foreach ($presets as $id => $preset) {
        if ($matches_preset($preset)) {
            return $id;
        }
    }

    return 'custom';
}

/**
 * Preset data in the shape the admin JavaScript expects.
 *
 * @return array
 */
function wpiko_chatbot_get_style_presets_for_js()
{
    $presets = wpiko_chatbot_get_normalized_style_presets();
    $prepared = array();

    foreach ($presets as $id => $preset) {
        $prepared[$id] = array(
            'label' => $preset['label'],
            'colors' => $preset['colors'],
            'showUserBorder' => $preset['settings']['show_user_border'],
        );
    }

    return $prepared;
}

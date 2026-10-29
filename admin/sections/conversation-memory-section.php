<?php
/**
 * Chat Widget → Messages → Conversation Memory.
 *
 * @package WPiko_Chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_conversation_memory_section()
{
    $settings_saved = false;
    if (isset($_POST['action']) && $_POST['action'] === 'save_conversation_memory') {
        check_admin_referer('save_conversation_memory', 'conversation_memory_nonce');

        $memory = isset($_POST['conversation_memory']) ? wpiko_chatbot_sanitize_conversation_memory(wp_unslash($_POST['conversation_memory'])) : 'tab';
        update_option('wpiko_chatbot_conversation_memory', $memory);

        $settings_saved = true;
    }

    $memory = wpiko_chatbot_get_conversation_memory();
    $menu_url = add_query_arg(array('page' => 'ai-chatbot', 'tab' => 'chatbot_menu', '_wpnonce' => wp_create_nonce('wpiko_chatbot_tab_nonce')), admin_url('admin.php'));
    $clear_label = get_option('wpiko_chatbot_menu_clear_chat', 'Start New Chat');
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-conversation-memory-page">
        <header class="wpiko-chatbot-settings-header">
            <h1><?php esc_html_e('Conversation Memory', 'wpiko-chatbot'); ?></h1>
            <p class="wpiko-chatbot-settings-subtitle"><?php esc_html_e('Choose how long the chatbot remembers a visitor\'s conversation.', 'wpiko-chatbot'); ?></p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p><?php esc_html_e('Conversation memory updated successfully.', 'wpiko-chatbot'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_conversation_memory', 'conversation_memory_nonce'); ?>

            <section class="wpiko-chatbot-settings-card" aria-labelledby="conversation-memory-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="conversation-memory-title"><?php esc_html_e('Visitor conversations', 'wpiko-chatbot'); ?></h2>
                <div class="wpiko-chatbot-settings-field-row">
                    <label class="wpiko-chatbot-settings-field-label" for="conversation_memory"><?php esc_html_e('Remember conversations', 'wpiko-chatbot'); ?></label>
                    <div class="wpiko-chatbot-settings-field-control">
                        <select name="conversation_memory" id="conversation_memory" aria-describedby="conversation_memory-description">
                                <?php foreach (wpiko_chatbot_conversation_memory_choices() as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($memory, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                        </select>
                        <div class="wpiko-chatbot-settings-field-description" id="conversation_memory-description">
                            <p><strong><?php esc_html_e('Only while the browser tab is open:', 'wpiko-chatbot'); ?></strong> <?php esc_html_e('the chat stays while the visitor moves between pages. Closing the tab or opening the site in a new tab starts a new chat.', 'wpiko-chatbot'); ?></p>
                            <p><strong><?php esc_html_e('Across visits:', 'wpiko-chatbot'); ?></strong> <?php esc_html_e('the visitor can close the browser and continue the same chat later on the same device, in any tab. The chat starts fresh after the chosen time without a message.', 'wpiko-chatbot'); ?></p>
                            <p>
                                <?php
                                printf(
                                    /* translators: 1: label of the clear chat menu option, 2: link to Menu Options. */
                                    esc_html__('Visitors can start a new chat at any time with "%1$s" in the chat menu. You can rename it in %2$s.', 'wpiko-chatbot'),
                                    esc_html($clear_label),
                                    '<a href="' . esc_url($menu_url) . '">' . esc_html__('Menu Options', 'wpiko-chatbot') . '</a>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="wpiko-chatbot-settings-card" aria-labelledby="conversation-memory-privacy-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="conversation-memory-privacy-title"><?php esc_html_e('Privacy', 'wpiko-chatbot'); ?></h2>
                <div class="wpiko-chatbot-settings-field-description">
                    <p><?php esc_html_e('The conversation is saved in the visitor\'s browser. Anyone using the same browser can see it until it expires or is cleared, so on shared computers "Only while the browser tab is open" is the safer choice.', 'wpiko-chatbot'); ?></p>
                    <p><?php esc_html_e('When someone logs in or out, a remembered conversation is removed from the browser, so it is never shown to a different account.', 'wpiko-chatbot'); ?></p>
                    <p><?php esc_html_e('Mention how long conversations are kept in the visitor\'s browser in your privacy policy. Conversations listed under Monitor → Conversations are not affected by this setting.', 'wpiko-chatbot'); ?></p>
                </div>
            </section>

            <input type="hidden" name="action" value="save_conversation_memory">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save"><?php esc_html_e('Save Conversation Memory', 'wpiko-chatbot'); ?></button>
            </div>
        </form>
    </div>
    <?php
}

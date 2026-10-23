<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_questions_section() {
    $settings_saved = false;
    if (isset($_POST['action']) && $_POST['action'] == 'save_questions') {
        check_admin_referer('save_questions', 'questions_nonce');
        
        // Initialize final sanitized questions array
        $questions = array();
        
        // Check if form data is submitted
        if (isset($_POST['questions'])) {
            // First unslash the entire array
            $unslashed_data = wp_unslash($_POST);
            
            // Validate it's an array and process
            if (is_array($unslashed_data['questions'])) {
                foreach ($unslashed_data['questions'] as $question) {
                    // Sanitize each question
                    $questions[] = sanitize_text_field($question);
                }
            }
        }
        update_option('wpiko_chatbot_questions', $questions);
        
        $settings_saved = true;
    }
    
    $questions = get_option('wpiko_chatbot_questions', array());
    ?>
    <div class="wpiko-chatbot-settings-page wpiko-questions-page">
        <header class="wpiko-chatbot-settings-header">
            <h1>Pre-made Questions</h1>
            <p class="wpiko-chatbot-settings-subtitle">Offer quick conversation starters. These questions appear as clickable buttons in the chat.</p>
        </header>
        <?php if ($settings_saved): ?>
            <div class="wpiko-chatbot-settings-notice is-success" role="status"><p>Pre-made questions updated successfully.</p></div>
        <?php endif; ?>
        <form method="post" action="" class="wpiko-chatbot-settings-form">
            <?php wp_nonce_field('save_questions', 'questions_nonce'); ?>
            <section class="wpiko-chatbot-settings-card" aria-labelledby="chatbot-questions-title">
                <h2 class="wpiko-chatbot-settings-card-title" id="chatbot-questions-title">Questions</h2>
                <p class="wpiko-chatbot-settings-field-description" id="chatbot-questions-description">Add a question on each line, in the order you want visitors to see them.</p>
                <div id="questions-container" class="wpiko-chatbot-settings-repeater" <?php echo empty($questions) ? 'hidden' : ''; ?>>
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-row wpiko-chatbot-settings-repeater-row">
                            <input type="text" name="questions[]" value="<?php echo esc_attr($question); ?>" aria-label="<?php echo esc_attr('Question ' . ($index + 1)); ?>" aria-describedby="chatbot-questions-description">
                            <button type="button" class="button remove-question wpiko-chatbot-settings-danger" aria-label="<?php echo esc_attr('Remove question ' . ($index + 1)); ?>">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="wpiko-chatbot-settings-empty" id="questions-empty" <?php echo empty($questions) ? '' : 'hidden'; ?>>No questions yet. Add a question to help visitors start a conversation.</p>
                <div class="wpiko-chatbot-settings-actions">
                    <button type="button" id="add-question" class="button"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Add Question</button>
                </div>
            </section>
            <input type="hidden" name="action" value="save_questions">
            <div class="wpiko-chatbot-settings-actions">
                <button type="submit" name="submit" id="submit" class="button wpiko-chatbot-settings-save">Save Questions</button>
            </div>
        </form>
    </div>
    <?php
}

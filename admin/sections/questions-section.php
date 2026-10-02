<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function wpiko_chatbot_questions_section() {
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
        
        echo '<div class="updated"><p>Pre-made questions updated successfully.</p></div>';
    }
    
    $questions = get_option('wpiko_chatbot_questions', array());
    ?>
    <div class="pre-made-questions-section">
        <h2> <span class="dashicons dashicons-editor-help"></span> Pre-made Questions</h2>
        <p class="description questions-description">Add frequently asked questions here to provide quick options for users to start conversations. These questions will appear as clickable buttons in the chat interface.</p>
        <form method="post" action="">
            <?php wp_nonce_field('save_questions', 'questions_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Questions</th>
                    <td>
                        <div id="questions-container">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-row">
                                    <input type="text" name="questions[]" value="<?php echo esc_attr($question); ?>" class="regular-text">
                                    <button type="button" class="button remove-question">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-question" class="button"><span class="dashicons dashicons-plus-alt2"></span> Add Question</button>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="save_questions">
            <?php submit_button('Save Questions'); ?>
        </form>
    </div>
    <?php
}
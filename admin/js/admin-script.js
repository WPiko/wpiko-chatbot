jQuery(document).ready(function($) {

    // API Key validation
    $('form[name="api_key_form"]').on('submit', function(e) {
        e.preventDefault();
        var apiKey = $('#api_key').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_validate_api_key',
                api_key: apiKey,
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    window.wpikoChatbotNotices.show('Invalid API key. Please check and try again.', 'error', { before: $('form[name="api_key_form"]') });
                }
            },
            error: function() {
                window.wpikoChatbotNotices.show('An error occurred while validating the API key.', 'error', { before: $('form[name="api_key_form"]') });
            }
        });
    });
    
    
    // Image - Logo Upload Functionality  
    var uploadBtn = document.getElementById('upload-btn');
    if (uploadBtn) {
        uploadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var image = wp.media({ 
                title: 'Upload Image',
                multiple: false
            }).open()
            .on('select', function(e){
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                var image_field = document.getElementById('chatbot_image');
                image_field.value = image_url;
                document.querySelector('#chatbot-image-preview img').src = image_url;
                // Let the live preview on the Chatbot Style tab pick up the new image.
                image_field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    // Chatbot Style - Advanced Options Toggle
    var $advancedOptions = document.getElementById('advanced-options');
    var $toggleButton = document.getElementById('toggle-advanced-options');
    var isAdvancedOpen = localStorage.getItem('wpiko_chatbot_advanced_open') === 'true';

    function updateAdvancedOptionsState(isOpen) {
        $toggleButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            $advancedOptions.style.display = 'block';
            $toggleButton.textContent = 'Hide Advanced Options';
        } else {
            $advancedOptions.style.display = 'none';
            $toggleButton.textContent = 'Show Advanced Options';
        }
        localStorage.setItem('wpiko_chatbot_advanced_open', isOpen);
    }

    // Set initial state
    if ($toggleButton && $advancedOptions) {
        updateAdvancedOptionsState(isAdvancedOpen);

        $toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            isAdvancedOpen = !isAdvancedOpen;
            updateAdvancedOptionsState(isAdvancedOpen);
        });
    }

    // Handle the reset button click
    var resetButton = document.getElementById('reset-advanced-options');
    if (resetButton) {
        resetButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Reset every style color back to the default "Aurora Blue" preset? This saves immediately and cannot be undone.')) {
                document.getElementById('reset-advanced-options-form').submit();
            }
        });
    }
    
    // Pre-made Questions Functionality
    var $addQuestionButton = $('#add-question');
    var $questionsContainer = $('#questions-container');

    if ($addQuestionButton.length && $questionsContainer.length) {
        function updateQuestionRows() {
            var $rows = $questionsContainer.children('.question-row');
            $rows.each(function(index) {
                $(this).find('input[name="questions[]"]').attr('aria-label', 'Question ' + (index + 1));
                $(this).find('.remove-question').attr('aria-label', 'Remove question ' + (index + 1));
            });
            $('#questions-empty').prop('hidden', $rows.length > 0);
            $questionsContainer.prop('hidden', $rows.length === 0);
        }

        updateQuestionRows();

        $addQuestionButton.click(function() {
            $questionsContainer.append('<div class="question-row wpiko-chatbot-settings-repeater-row"><input type="text" name="questions[]" value="" aria-describedby="chatbot-questions-description"><button type="button" class="button remove-question wpiko-chatbot-settings-danger">Remove</button></div>');
            updateQuestionRows();
            $questionsContainer.find('.question-row:last input').trigger('focus');
        });

        $questionsContainer.on('click', '.remove-question', function() {
            var $row = $(this).closest('.question-row');
            var $nextInput = $row.next('.question-row').find('input').first();
            if (!$nextInput.length) {
                $nextInput = $row.prev('.question-row').find('input').first();
            }
            $row.remove();
            updateQuestionRows();
            ($nextInput.length ? $nextInput : $addQuestionButton).trigger('focus');
        });
    }
});

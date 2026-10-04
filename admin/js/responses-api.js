jQuery(document).ready(function ($) {

    // Handle System Instructions tab switching
    $('.instructions-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();

        var tabId = $(this).data('tab');

        // Remove active class from all tabs and add to clicked tab
        $('.instructions-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Hide all tab content and show the selected one
        $('.tab-content').hide();
        $('#' + tabId + '-tab').show();
    });

    // Function to update main instructions dynamically for Responses API
    function updateResponsesMainInstructions() {
        var websiteName = $('.website-name').text() || 'Your Website';
        var assistantType = $('#responses_assistant_type').val() || 'AI assistant';
        var websiteSpecialization = $('#responses_website_specialization').val() || '';
        var assistantTone = $('#responses_assistant_tone option:selected').text();
        var assistantStyle = $('#responses_assistant_style option:selected').text();

        // NOTE: This instruction template is also defined in includes/instructions-handler.php
        // If you modify this template, make sure to update both files to keep them in sync
        var mainInstructions = "You are an " + assistantType + " for the website " + websiteName + ". " +
            "The website specializes in " + websiteSpecialization + ". " +
            "Your goal is to provide helpful, accurate, and engaging responses to user queries " +
            "while maintaining a " + assistantTone + " and " + assistantStyle + " tone. " +
            "Always respond as if you are a helpful member of the website team.";

        $('#responses_main_system_instructions').val(mainInstructions);
    }

    // Update instructions when fields change
    $('#responses_website_specialization').on('input', updateResponsesMainInstructions);
    $('#responses_assistant_type').on('input', updateResponsesMainInstructions);
    $('#responses_website_specialization').on('input', updateResponsesMainInstructions);
    $('#responses_assistant_tone').on('change', updateResponsesMainInstructions);
    $('#responses_assistant_style').on('change', updateResponsesMainInstructions);

    // Initialize instructions on page load
    updateResponsesMainInstructions();

    // Handle Vector Store refresh
    $(document).on('click', '.vector-store-refresh-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $vectorStoreInfo = $button.closest('.vector-store-info');

        // Disable button and show loading state
        $button.prop('disabled', true);
        var $icon = $button.find('.dashicons');
        $icon.css('animation', 'rotation 1s infinite linear');

        $.ajax({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_get_responses_vector_store_details',
                security: wpikoChatbotAdmin.nonce
            },
            success: function (response) {
                if (response.success && response.data) {
                    var data = response.data;
                    var createdDate = new Date(data.created_at * 1000).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    var fileCounts = data.file_counts || {};
                    var totalFiles = fileCounts.total || 0;
                    var completedFiles = fileCounts.completed || 0;
                    var status = data.status || 'unknown';
                    var statusClass = status === 'completed' ? 'status-active' : 'status-processing';
                    var statusText = status.charAt(0).toUpperCase() + status.slice(1);

                    // Update the Vector Store information
                    var html = '<div class="vector-store-header">' +
                        '<span class="dashicons dashicons-database"></span>' +
                        '<h4>Vector Store Information' +
                        '<span class="vector-store-info-icon" title="A Vector Store is a specialized database in OpenAI that stores and indexes your uploaded files (PDFs, documents, etc.) for semantic search. It enables your AI chatbot to search through your knowledge base and provide accurate answers based on your content.">' +
                        '<span class="dashicons dashicons-info"></span>' +
                        '</span>' +
                        '</h4>' +
                        '<span class="vector-store-status ' + statusClass + '">' + statusText + '</span>' +
                        '<button type="button" class="button button-link-refresh vector-store-refresh-btn" title="Refresh Vector Store Information">' +
                        '<span class="dashicons dashicons-update"></span>' +
                        '</button>' +
                        '<button type="button" class="button button-link-delete vector-store-delete-btn" title="Delete Vector Store and All Files">' +
                        '<span class="dashicons dashicons-trash"></span>' +
                        '</button>' +
                        '</div>' +
                        '<div class="vector-store-details">' +
                        '<div class="vector-store-item">' +
                        '<strong>Name:</strong>' +
                        '<span>' + data.name + '</span>' +
                        '</div>' +
                        '<div class="vector-store-item">' +
                        '<strong>ID:</strong>' +
                        '<code class="vector-store-id">' + data.id + '</code>' +
                        '</div>' +
                        '<div class="vector-store-item">' +
                        '<strong>Created:</strong>' +
                        '<span>' + createdDate + '</span>' +
                        '</div>' +
                        '<div class="vector-store-item">' +
                        '<strong>Files:</strong>' +
                        '<span>' + completedFiles + ' completed / ' + totalFiles + ' total</span>' +
                        '</div>' +
                        '</div>';

                    $vectorStoreInfo.removeClass('vector-store-error').html(html);
                } else if (response.data && response.data.not_found) {
                    // Vector Store was deleted from OpenAI dashboard
                    var html = '<div class="vector-store-header">' +
                        '<span class="dashicons dashicons-warning"></span>' +
                        '<h4>Vector Store Not Found' +
                        '<span class="vector-store-info-icon" title="A Vector Store is a specialized database in OpenAI that stores and indexes your uploaded files (PDFs, documents, etc.) for semantic search. It enables your AI chatbot to search through your knowledge base and provide accurate answers based on your content.">' +
                        '<span class="dashicons dashicons-info"></span>' +
                        '</span>' +
                        '</h4>' +
                        '<span class="vector-store-status status-error">Error</span>' +
                        '<button type="button" class="button button-link-refresh vector-store-refresh-btn" title="Refresh Vector Store Information">' +
                        '<span class="dashicons dashicons-update"></span>' +
                        '</button>' +
                        '</div>' +
                        '<div class="vector-store-error-message">' +
                        '<p><strong>The Vector Store was deleted or is no longer accessible.</strong></p>' +
                        '<p>This may have happened if you deleted it from the OpenAI dashboard. To fix this issue:</p>' +
                        '<ol>' +
                        '<li>Upload a new file using the "File Management" button below, or</li>' +
                        '<li>Use any of the training tools (Scan Website, Q&A Builder, etc.)</li>' +
                        '</ol>' +
                        '<p>A new Vector Store will be automatically created when you upload your first file.</p>' +
                        '</div>';

                    $vectorStoreInfo.addClass('vector-store-error').html(html);
                } else {
                    alert('Error refreshing Vector Store information: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function () {
                alert('Connection error occurred while trying to refresh Vector Store information.');
            },
            complete: function () {
                // Re-enable button and stop animation
                $button.prop('disabled', false);
                $icon.css('animation', '');
            }
        });
    });

    // Handle Vector Store deletion
    $(document).on('click', '.vector-store-delete-btn', function (e) {
        e.preventDefault();

        var confirmed = confirm(
            'Are you sure you want to delete the Vector Store?\n\n' +
            'This will permanently remove:\n' +
            '• The Vector Store itself\n' +
            '• All files uploaded to the Vector Store\n' +
            '• All training data (website pages, Q&A content, documents, etc.)\n\n' +
            'This action cannot be undone.\n\n' +
            'Click OK to delete or Cancel to keep it.'
        );

        if (!confirmed) {
            return;
        }

        var $button = $(this);
        var $vectorStoreInfo = $button.closest('.vector-store-info');

        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.find('.dashicons').removeClass('dashicons-trash').addClass('dashicons-update').css('animation', 'rotation 1s infinite linear');

        $.ajax({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_delete_responses_vector_store',
                security: wpikoChatbotAdmin.nonce
            },
            success: function (response) {
                if (response.success) {
                    var message = response.data.message || 'Vector Store has been deleted.';
                    // Show success message
                    $vectorStoreInfo.html(
                        '<div class="notice notice-success" style="margin: 0; padding: 15px;">' +
                        '<p><strong>Success!</strong> ' + message + ' The page will reload in 2 seconds...</p>' +
                        '</div>'
                    );

                    // Reload page after 2 seconds
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    alert('Error: ' + (response.data.message || 'Failed to delete Vector Store'));
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-trash').css('animation', '');
                }
            },
            error: function () {
                alert('Connection error occurred while trying to delete the Vector Store.');
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-trash').css('animation', '');
            }
        });
    });

    // Handle Responses API configuration save
    $('#save_responses_config').on('click', function () {
        var $button = $(this);
        var $status = $('#responses_save_status');

        $button.prop('disabled', true).text('Saving...');
        $status.removeClass('success error').addClass('info').text('Saving configuration...');

        var data = {
            action: 'wpiko_chatbot_update_responses_config',
            security: wpikoChatbotAdmin.nonce,
            model: $('#responses_model').val(),
            responses_assistant_type: $('#responses_assistant_type').val(),
            responses_website_specialization: $('#responses_website_specialization').val(),
            responses_assistant_tone: $('#responses_assistant_tone').val(),
            responses_assistant_style: $('#responses_assistant_style').val(),
            responses_reasoning_effort: $('#responses_reasoning_effort').val(),
            responses_verbosity: $('#responses_verbosity').val(),
            main_system_instructions: $('#responses_main_system_instructions').val(),
            specific_system_instructions: $('#responses_specific_system_instructions').val()
        };

        $.post(wpikoChatbotAdmin.ajax_url, data, function (response) {
            if (response.success) {
                $status.removeClass('error info').addClass('success').text('Configuration saved successfully!');
                setTimeout(function () {
                    $status.removeClass('success error info').text('');
                }, 3000);
            } else {
                $status.removeClass('success info').addClass('error').text('Error: ' + response.data.message);
            }
        }).fail(function () {
            $status.removeClass('success info').addClass('error').text('Connection error occurred.');
        }).always(function () {
            $button.prop('disabled', false).text('Save Configuration');
        });
    });


});

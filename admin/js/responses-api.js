jQuery(document).ready(function ($) {

    // Handle System Instructions tab switching
    $('.instructions-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();

        var tabId = $(this).data('tab');

        var $tabs = $(this).closest('.instructions-tabs');
        $tabs.find('.nav-tab').removeClass('nav-tab-active').attr({'aria-selected': 'false', tabindex: '-1'});
        $(this).addClass('nav-tab-active').attr({'aria-selected': 'true', tabindex: '0'});

        // Hide all tab content and show the selected one
        $tabs.find('.tab-content').hide();
        $tabs.find('#' + tabId + '-tab').show();
    });

    $('.instructions-tabs [role="tab"]').on('keydown', function (event) {
        var keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (keys.indexOf(event.key) === -1) {
            return;
        }
        event.preventDefault();
        var $tabs = $(this).closest('[role="tablist"]').find('[role="tab"]');
        var index = $tabs.index(this);
        var next = event.key === 'Home' ? 0 : event.key === 'End' ? $tabs.length - 1 :
            (index + (event.key === 'ArrowRight' ? 1 : -1) + $tabs.length) % $tabs.length;
        $tabs.eq(next).trigger('click').trigger('focus');
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

    // Vector Store information box. Refreshed with the refresh button and
    // automatically whenever files are added or removed on this page.
    var $vectorStoreSlot = $('#wpiko-vector-store-info-slot');
    var vectorStore = {
        busy: false,
        queued: false,
        timer: null,
        lastTotal: null,
        pollsLeft: 0
    };

    function vectorStoreTotal(data) {
        return data && data.file_counts ? (parseInt(data.file_counts.total, 10) || 0) : null;
    }

    function vectorStoreIndexing(data) {
        var counts = (data && data.file_counts) || {};
        return (parseInt(counts.in_progress, 10) || 0) > 0 || (data && data.status === 'in_progress');
    }

    /**
     * Load the Vector Store details and swap the box in place.
     *
     * @param {Object} options manual: show the button spinner and errors.
     *                         afterChange: files just changed on this page.
     */
    function refreshVectorStoreInfo(options) {
        options = options || {};
        if (!$vectorStoreSlot.length || typeof wpikoChatbotAdmin === 'undefined') {
            return;
        }
        if (vectorStore.busy) {
            vectorStore.queued = true;
            return;
        }
        vectorStore.busy = true;
        clearTimeout(vectorStore.timer);

        var $button = $vectorStoreSlot.find('.vector-store-refresh-btn');
        var $icon = $button.find('.dashicons');
        $button.prop('disabled', true);
        $icon.css('animation', 'rotation 1s infinite linear');

        var totalBefore = vectorStore.lastTotal;

        $.ajax({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_get_responses_vector_store_details',
                security: wpikoChatbotAdmin.nonce
            }
        }).done(function (response) {
            var data = (response && response.data) || {};
            var hasMarkup = typeof data.html === 'string';

            // Errors other than "not found" (for example OpenAI not responding)
            // keep the current box instead of hiding it.
            if (response && (response.success || data.not_found || $vectorStoreSlot.is(':empty'))) {
                $vectorStoreSlot.html(hasMarkup ? data.html : '');
            }

            if (response && response.success) {
                var total = vectorStoreTotal(data);
                vectorStore.lastTotal = total;

                if (options.afterChange) {
                    // OpenAI can take a moment to update its counts. Check a
                    // few more times while files are indexing, and once more
                    // if the total has not moved yet.
                    vectorStore.pollsLeft = vectorStoreIndexing(data) ? 10 : (total === totalBefore ? 1 : 0);
                } else if (!vectorStoreIndexing(data)) {
                    // Follow-up check: stop once nothing is indexing.
                    vectorStore.pollsLeft = 0;
                }

                if (vectorStore.pollsLeft > 0) {
                    vectorStore.pollsLeft--;
                    vectorStore.timer = setTimeout(refreshVectorStoreInfo, 4000);
                }
            } else if (options.manual && !data.not_found) {
                window.wpikoChatbotNotices.show('Error refreshing Vector Store information: ' + (data.message || 'Unknown error'), 'error', { before: $vectorStoreSlot });
            }
        }).fail(function () {
            if (options.manual) {
                window.wpikoChatbotNotices.show('Connection error occurred while trying to refresh Vector Store information.', 'error', { before: $vectorStoreSlot });
            }
        }).always(function () {
            vectorStore.busy = false;
            // The box may have been replaced; reset whichever button is there.
            $vectorStoreSlot.find('.vector-store-refresh-btn').prop('disabled', false).find('.dashicons').css('animation', '');
            if (vectorStore.queued) {
                vectorStore.queued = false;
                refreshVectorStoreInfo({ afterChange: true });
            }
        });
    }

    // Remember the starting count so the first automatic refresh can tell
    // whether OpenAI has caught up.
    (function () {
        var match = $vectorStoreSlot.find('.vector-store-item').last().text().match(/\/\s*(\d+)\s*total/);
        vectorStore.lastTotal = match ? parseInt(match[1], 10) : ($vectorStoreSlot.is(':empty') ? 0 : null);
    })();

    function scheduleVectorStoreRefresh() {
        if (!$vectorStoreSlot.length) {
            return;
        }
        // Many changes in a row (for example a Scan Website batch) refresh once.
        clearTimeout(vectorStore.timer);
        vectorStore.pollsLeft = 0;
        vectorStore.timer = setTimeout(function () {
            refreshVectorStoreInfo({ afterChange: true });
        }, 1200);
    }

    // The server marks every response that added or removed knowledge files
    // (see wpiko_chatbot_clear_vector_store_files_cache()).
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (!xhr || !xhr.getResponseHeader || xhr.getResponseHeader('X-Wpiko-Knowledge-Changed') !== '1') {
            return;
        }
        var data = settings && typeof settings.data === 'string' ? settings.data : '';
        // Deleting the whole Vector Store reloads the page itself.
        if (data.indexOf('action=wpiko_chatbot_delete_responses_vector_store') !== -1) {
            return;
        }
        scheduleVectorStoreRefresh();
    });

    // Tools whose changes finish outside an AJAX request (for example a
    // WooCommerce background sync) trigger this event when they are done.
    $(document).on('wpiko:knowledgeChanged', scheduleVectorStoreRefresh);

    $(document).on('click', '.vector-store-refresh-btn', function (e) {
        e.preventDefault();
        refreshVectorStoreInfo({ manual: true });
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
                    // Show success message in place of the Vector Store details.
                    window.wpikoChatbotNotices.show(message + ' The page will reload in 2 seconds...', 'success', { before: $vectorStoreInfo, dismissible: false });
                    $vectorStoreInfo.remove();

                    // Reload page after 2 seconds
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    window.wpikoChatbotNotices.show('Error: ' + ((response.data && response.data.message) || 'Failed to delete Vector Store'), 'error', { before: $vectorStoreInfo });
                    $button.prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-trash').css('animation', '');
                }
            },
            error: function () {
                window.wpikoChatbotNotices.show('Connection error occurred while trying to delete the Vector Store.', 'error', { before: $vectorStoreInfo });
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-trash').css('animation', '');
            }
        });
    });

    // Handle Responses API configuration save
    $('#save_responses_config').on('click', function () {
        var $button = $(this);
        var $actions = $button.closest('.save-config-actions');

        $button.prop('disabled', true).text('Saving...');

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
                window.wpikoChatbotNotices.show('Configuration saved successfully.', 'success', { before: $actions });
            } else {
                window.wpikoChatbotNotices.show('Error: ' + ((response.data && response.data.message) || 'The configuration could not be saved.'), 'error', { before: $actions });
            }
        }).fail(function () {
            window.wpikoChatbotNotices.show('Connection error occurred. Please try again.', 'error', { before: $actions });
        }).always(function () {
            $button.prop('disabled', false).text('Save Configuration');
        });
    });


});

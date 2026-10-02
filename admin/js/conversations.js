jQuery(document).ready(function($) {

    // Translation Icon Toggle
    $('.translation-icon-button').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $dropdown = $(this).siblings('.translation-dropdown');
        
        if ($dropdown.is(':visible')) {
            $dropdown.slideUp(200);
        } else {
            $dropdown.slideDown(200);
        }
    });

    // Handle translation language selection
    $('#translation-language').on('change', function() {
        const $button = $('.translation-icon-button');
        const selectedValue = $(this).val();
        
        if (selectedValue !== 'none' && selectedValue !== '') {
            $button.addClass('active');
            $button.attr('title', 'Translation: ' + selectedValue);
        } else {
            $button.removeClass('active');
            $button.attr('title', 'Translation options');
        }
        
        // Hide dropdown after selection
        $('.translation-dropdown').slideUp(200);
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.integrated-download-button').length) {
            $('.translation-dropdown').slideUp(200);
        }
    });

    // Mobile Responsive Styles
    if (window.matchMedia("(max-width: 1420px)").matches) {
        const mobileChevronLeft = '<svg class="mobile-nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12.5 15-5-5 5-5"/></svg>';
        const mobileChevronRight = '<svg class="mobile-nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7.5 5 5 5-5 5"/></svg>';

        // Delegated so it also applies to items injected by search
        $(document).on('click', '.conversation-item', function() {
            $('.conversation-transcript').addClass('active');
            $('.conversations-sidebar').hide();
        });

        // Add navigation buttons to transcript
        $('.conversation-transcript').prepend(
            '<div class="mobile-nav-buttons">' +
                '<button type="button" class="button wpiko-mobile-nav-button back-to-conversations" aria-label="Back to conversations">' +
                    mobileChevronLeft + '<span>Conversations</span>' +
                '</button>' +
                '<button type="button" class="button wpiko-mobile-nav-button wpiko-mobile-nav-button-primary view-details-button" aria-label="View conversation details">' +
                    '<span>Details</span>' + mobileChevronRight +
                '</button>' +
            '</div>'
        );

        // Handle back to conversations
        $(document).on('click', '.back-to-conversations', function() {
            $('.conversation-transcript').removeClass('active');
            $('.conversation-details').removeClass('active');
            $('.conversations-sidebar').show();
        });

        // Handle view details toggle
        $(document).on('click', '.view-details-button', function() {
            const $details = $('.conversation-details');
            const $transcript = $('.conversation-transcript');

            if ($details.hasClass('active')) {
                $details.removeClass('active');
                $transcript.show();
            } else {
                $details.addClass('active');
                $transcript.hide();
        
                // Add a "Back to Chat" button in the details section
                if (!$('.details-mobile-nav').length) {
                    $details.prepend(
                        '<div class="details-mobile-nav">' +
                            '<button type="button" class="button wpiko-mobile-nav-button back-to-chat-button" aria-label="Back to conversation">' +
                                mobileChevronLeft + '<span>Conversation</span>' +
                            '</button>' +
                        '</div>'
                    );
                }
            }
        });

        // Handle back to chat from details section
        $(document).on('click', '.back-to-chat-button', function() {
            $('.conversation-details').removeClass('active');
            $('.conversation-transcript').show();
        });
    }

    // Date Filter Functionality
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const resetButton = document.getElementById('reset-filter');
    const filterForm = document.getElementById('conversation-filter-form');

    if(startDate && endDate) {
        startDate.addEventListener('change', function() {
            endDate.min = startDate.value;
        });

        endDate.addEventListener('change', function() {
            startDate.max = endDate.value;
        });
    }

    if(resetButton && filterForm) {
        resetButton.addEventListener('click', function(e) {
            e.preventDefault();
            startDate.value = '';
            endDate.value = '';
            startDate.max = '';
            endDate.min = '';
            filterForm.submit();
        });
    }

    // Conversation Selection and Display
    let currentSessionId = null;

    // Delegated so it also applies to items injected by search
    $(document).on('click', '.conversation-item', function() {
        const $clickedItem = $(this);
        const sessionId = $clickedItem.data('session-id');
        if (currentSessionId === sessionId) return;
        
        currentSessionId = sessionId;
        
        // Update active state
        $('.conversation-item').removeClass('active');
        $clickedItem.addClass('active');
        
        // Show loading state
        $('.transcript-placeholder').hide();
        $('.transcript-content').html('<div class="loading">Loading conversation...</div>').show();
        $('.details-placeholder').hide();
        $('.details-content').show();
        
        // Fetch conversation data
        $.ajax({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'post',
            data: {
                action: 'wpiko_chatbot_fetch_conversation',
                session_id: sessionId,
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update transcript
                    $('.transcript-content').html(response.data.messages);
                    
                    // Update user info section with properly sized avatar
                    $.ajax({
                        url: wpikoChatbotAdmin.ajax_url,
                        type: 'post',
                        data: {
                            action: 'wpiko_chatbot_get_avatar',
                            user_id: response.data.user_id,
                            size: 160,
                            security: wpikoChatbotAdmin.nonce
                        },
                        success: function(avatarResponse) {
                            if (avatarResponse.success) {
                                $('#details-avatar').html(avatarResponse.data);
                            }
                        }
                    });
                    
                    // Update user name - use the computed user_name which already handles email capture logic
                    $('.user-info-section .user-name').text(response.data.user_name || 'Guest');
                    
                    // Format location display
                    if (!$('.user-country').hasClass('locked')) {
                        let location = [];
                        if (response.data.city) location.push(response.data.city);
                        if (response.data.country) location.push(response.data.country);
                        
                        let locationText = location.length > 0 ? location.join(', ') : 'Unknown Location';
                        $('.user-info-section .user-country').text(locationText);
                    }
                    $('.user-info-section .user-email').text(response.data.user_email || 'No Email');

                    // Update contact button state and email
                    currentUserEmail = response.data.user_email || '';
                    const $contactButton = $('.contact-user');
                    if ($contactButton.length) {
                        $contactButton.prop('disabled', !currentUserEmail);
                        if (!currentUserEmail) {
                            $contactButton.attr('title', 'No email address available for this user');
                        } else {
                            $contactButton.attr('title', 'Send email to ' + currentUserEmail);
                        }
                    }

                    // Update additional info section
                    $('.api-type').text(response.data.api_type || 'N/A');
                    $('.user-status').text(response.data.user_id == 0 ? 'Guest' : 'Logged In');
                    $('.user-device').text(response.data.device_type || 'N/A');

                    // Update user online presence if the field exists
                    var $presenceEl = $('.user-presence');
                    if ($presenceEl.length) {
                        if (response.data.user_online) {
                            $presenceEl.text('Online').removeClass('offline').addClass('online');
                        } else if (response.data.user_last_seen) {
                            $presenceEl.text('Offline').removeClass('online').addClass('offline');
                        } else {
                            $presenceEl.text('N/A').removeClass('online offline');
                        }
                    }
                    
                    // Scroll transcript to bottom
                    const transcriptContent = $('.transcript-content')[0];
                    transcriptContent.scrollTop = transcriptContent.scrollHeight;

                    // Trigger event for Pro plugin hooks (takeover button, etc.)
                    $(document).trigger('wpiko_conversation_loaded', [sessionId]);
                } else {
                    $('.transcript-content').html('<div class="error">Failed to load conversation</div>');
                }
            },
            error: function() {
                $('.transcript-content').html('<div class="error">Error loading conversation</div>');
            }
        });
    });

    // Conversation Search
    const $searchInput = $('#conversation-search');
    if ($searchInput.length) {
        const $conversationsList = $('.conversations-list');
        const $countBadge = $('.conversations-count');
        const originalListHtml = $conversationsList.html();
        const originalCount = $countBadge.text();
        let searchTimer = null;
        let searchXhr = null;

        function highlightActiveItem() {
            if (currentSessionId) {
                $conversationsList
                    .find('.conversation-item[data-session-id="' + currentSessionId + '"]')
                    .addClass('active');
            }
        }

        function restoreConversationsList() {
            if (searchXhr) {
                searchXhr.abort();
                searchXhr = null;
            }
            clearTimeout(searchTimer);
            $conversationsList.html(originalListHtml);
            $countBadge.text(originalCount);
            $('.clear-search').hide();
            $conversationsList.find('.conversation-item').removeClass('active');
            highlightActiveItem();
            updateTimestamps();
        }

        function runConversationSearch(term) {
            if (searchXhr) {
                searchXhr.abort();
            }
            $conversationsList.html('<div class="search-loading">Searching...</div>');

            searchXhr = $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'post',
                data: {
                    action: 'wpiko_chatbot_search_conversations',
                    search: term,
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.count > 0) {
                            $conversationsList.html(response.data.html);
                            highlightActiveItem();
                            updateTimestamps();
                        } else {
                            $conversationsList.html('<div class="no-conversations">No conversations match your search.</div>');
                        }
                        $countBadge.text(response.data.count);
                    } else {
                        $conversationsList.html('<div class="no-conversations">Search failed. Please try again.</div>');
                    }
                },
                error: function(xhr, status) {
                    if (status !== 'abort') {
                        $conversationsList.html('<div class="no-conversations">Search failed. Please try again.</div>');
                    }
                }
            });
        }

        $searchInput.on('input', function() {
            const term = $(this).val().trim();
            clearTimeout(searchTimer);

            if (term.length === 0) {
                restoreConversationsList();
                return;
            }

            $('.clear-search').show();

            if (term.length < 2) {
                return;
            }

            searchTimer = setTimeout(function() {
                runConversationSearch(term);
            }, 300);
        });

        // Handle Escape key to clear search
        $searchInput.on('keydown', function(e) {
            if (e.key === 'Escape') {
                $(this).val('');
                restoreConversationsList();
            }
        });

        $('.clear-search').on('click', function() {
            $searchInput.val('');
            restoreConversationsList();
            $searchInput.trigger('focus');
        });
    }

    // Contact functionality
    let currentUserEmail = '';

    function closeContactModal() {
        $('#contact-modal').remove();
    }

    function showContactForm() {
        const modalHtml = `
            <div id="contact-modal" class="modal" style="display: block;">
                <div class="modal-content">
                    <h2>Contact User</h2>
                    <p class="description">Send an email message directly to the user.</p>
                    <form id="contact-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="subject">Subject:</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="message">Message:</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                            <div class="file-preview"></div>
                        </div>
                        <div class="form-actions">
                            <div class="message-toolbar">
                                <input type="file" id="attachment" name="attachment" accept="image/*" class="file-input">
                                    <label for="attachment" class="file-label" title="Attach image">
                                        <svg fill="#000000" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g data-name="Layer 2"> <g data-name="attach"> <rect width="24" height="24" opacity="0"></rect> <path d="M9.29 21a6.23 6.23 0 0 1-4.43-1.88 6 6 0 0 1-.22-8.49L12 3.2A4.11 4.11 0 0 1 15 2a4.48 4.48 0 0 1 3.19 1.35 4.36 4.36 0 0 1 .15 6.13l-7.4 7.43a2.54 2.54 0 0 1-1.81.75 2.72 2.72 0 0 1-1.95-.82 2.68 2.68 0 0 1-.08-3.77l6.83-6.86a1 1 0 0 1 1.37 1.41l-6.83 6.86a.68.68 0 0 0 .08.95.78.78 0 0 0 .53.23.56.56 0 0 0 .4-.16l7.39-7.43a2.36 2.36 0 0 0-.15-3.31 2.38 2.38 0 0 0-3.27-.15L6.06 12a4 4 0 0 0 .22 5.67 4.22 4.22 0 0 0 3 1.29 3.67 3.67 0 0 0 2.61-1.06l7.39-7.43a1 1 0 1 1 1.42 1.41l-7.39 7.43A5.65 5.65 0 0 1 9.29 21z"></path> </g> </g> </g></svg>                                    </label>
                                    <button type="button" class="emoji-picker-button" title="Insert emoji">
                                        <svg fill="#000000" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <defs> <style> </style> </defs> <g id="Layer_2" data-name="Layer 2"> <g id="smiling-face"> <g id="smiling-face" data-name="smiling-face"> <rect width="24" height="24" opacity="0"></rect> <path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm5 9a5 5 0 0 1-10 0z" id="🎨-Icon-Сolor"></path> </g> </g> </g> </g></svg>
                                    </button>
                                    <div class="ai-enhance-group">
                                        <button type="button" class="ai-enhance-button" title="Enhance text with AI">
                                            <svg fill="#000000" viewBox="0 0 256 256" id="Flat" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M56,60a8.00008,8.00008,0,0,1,8-8H76V40a8,8,0,0,1,16,0V52h12a8,8,0,0,1,0,16H92V80a8,8,0,0,1-16,0V68H64A8.00008,8.00008,0,0,1,56,60Zm184,84H224V128a8,8,0,0,0-16,0v16H192a8,8,0,0,0,0,16h16v16a8,8,0,0,0,16,0V160h16a8,8,0,0,0,0-16Zm-58.34619-26.33984L75.31445,224a16.02252,16.02252,0,0,1-22.62793.001L32,203.31348a15.99888,15.99888,0,0,1,.001-22.62793L180.68555,32a16.02162,16.02162,0,0,1,22.62793-.001L224,52.68652a15.99888,15.99888,0,0,1-.001,22.62793l-42.33886,42.33887-.00293.00342ZM176,100.686,212.68555,64l.001-.001.00684-.00732L192,43.31348,155.314,80ZM184,192h-8v-8a8,8,0,0,0-16,0v8h-8a8,8,0,0,0,0,16h8v8a8,8,0,0,0,16,0v-8h8a8,8,0,0,0,0-16Z"></path> </g></svg>
                                        </button>
                                        <select class="tone-selector" title="Select writing tone">
                                            <option value="professional">Professional</option>
                                            <option value="friendly">Friendly</option>
                                            <option value="casual">Casual</option>
                                            <option value="formal">Formal</option>
                                            <option value="enthusiastic">Enthusiastic</option>
                                        </select>
                                    </div>
                            </div>
                            <div class="loading-spinner"></div>
                            <button type="submit" class="button button-primary">Send</button>
                            <button type="button" class="button cancel-contact">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>`;

        $('body').append(modalHtml);

        // Close modal handlers
        $('#contact-modal').on('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });

        $('.cancel-contact').on('click', function() {
            closeContactModal();
        });

        // Handle file input change
        $('#attachment').on('change', function() {
            const file = this.files[0];
            const preview = $('.file-preview');
            preview.empty();

            if (file) {
                // Check file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    this.value = '';
                    return;
                }

                // Check file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum size is 5MB.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.html(`
                        <div class="image-preview">
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-image" title="Remove image">×</button>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            }
        });

        // Handle remove image button
        $(document).on('click', '.remove-image', function() {
            $('#attachment').val('');
            $('.file-preview').empty();
        });

        // Initialize emoji picker
        $('.emoji-picker-button').on('click', function(e) {
            e.preventDefault();
            const button = $(this);
            const messageTextarea = $('#message');
            
            // Create emoji picker if it doesn't exist
            if (!$('#emoji-picker').length) {
                const picker = $('<div>', {
                    id: 'emoji-picker',
                    class: 'emoji-picker'
                });
                
                // Common emojis
                const emojis = ['😊', '😂', '🤔', '👍', '👎', '❤️', '🎉', '🔥', '✨', '⭐', '📎', '📌', '⚡', '💡', '💪', '🙌', '👏', '🤝', '👋', '✅'];
                
                emojis.forEach(emoji => {
                    $('<button>', {
                        type: 'button',
                        class: 'emoji-option',
                        text: emoji
                    }).appendTo(picker);
                });
                
                $('body').append(picker);
                
                // Position picker near the button
                const buttonPos = button.offset();
                picker.css({
                    top: buttonPos.top + button.outerHeight(),
                    left: buttonPos.left
                });
                
        // Handle emoji selection
        $('.emoji-option').on('click', function() {
            const emoji = $(this).text();
            const cursorPos = messageTextarea[0].selectionStart;
            const text = messageTextarea.val();
            messageTextarea.val(
                text.substring(0, cursorPos) +
                emoji +
                text.substring(cursorPos)
            );
            messageTextarea.focus();
        });
                
        // Close picker when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#emoji-picker, .emoji-picker-button').length) {
                        $('#emoji-picker').remove();
                    }
                });
            } else {
                $('#emoji-picker').remove();
            }
        });

        // Handle AI text enhancement
        $('.ai-enhance-button').on('click', function() {
            const messageTextarea = $('#message');
            const currentText = messageTextarea.val();
            
            if (!currentText.trim()) {
                alert('Please enter some text to enhance');
                return;
            }

            const $button = $(this);
            
            // Disable button and show spinner
            $button.prop('disabled', true);

            const selectedTone = $('.tone-selector').val();
            
            // Create and show review modal
            const reviewModal = $(`
                <div id="ai-review-modal" class="ai-review-modal">
                    <div class="ai-review-content">
                        <h2>Review Enhanced Text</h2>
                        <div class="text-comparison">
                            <div class="text-column">
                                <h4>Original Text</h4>
                                <div class="text-content original-text"></div>
                            </div>
                            <div class="text-column">
                                <h4>Enhanced Text</h4>
                                <div class="text-content enhanced-text"></div>
                            </div>
                        </div>
                        <div class="review-actions">
                            <button type="button" class="button cancel-enhancement">Cancel</button>
                            <button type="button" class="button button-primary accept-enhancement">Use Enhanced Text</button>
                        </div>
                    </div>
                </div>
            `);

            // Add modal to body
            $('body').append(reviewModal);
            
            // Show original text
            reviewModal.find('.original-text').text(currentText);
            
            // Show loading state in enhanced text
            reviewModal.find('.enhanced-text').html('<div>Enhancing text...</div>');
            
            // Display modal
            reviewModal.show();

            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpiko_chatbot_enhance_text',
                    security: wpikoChatbotAdmin.nonce,
                    text: currentText,
                    tone: selectedTone
                },
                success: function(response) {
                    if (response.success) {
                        reviewModal.find('.enhanced-text').text(response.data);
                    } else {
                        alert('Failed to enhance text: ' + response.data);
                        reviewModal.remove();
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error enhancing text: ' + error);
                    reviewModal.remove();
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                }
            });

            // Handle accept enhanced text
            reviewModal.on('click', '.accept-enhancement', function() {
                messageTextarea.val(reviewModal.find('.enhanced-text').text());
                reviewModal.remove();
            });

            // Handle cancel enhancement
            reviewModal.on('click', '.cancel-enhancement', function() {
                reviewModal.remove();
            });

            // Close modal when clicking outside
            reviewModal.on('click', function(e) {
                if (e.target === this) {
                    reviewModal.remove();
                }
            });
        });

        // Handle form submission
        $('#contact-form').submit(function(e) {
            e.preventDefault();
            const subject = $('#subject').val();
            const message = $('#message').val();

            const $form = $(this);
            const $submitButton = $form.find('button[type="submit"]');
            const $cancelButton = $form.find('.cancel-contact');
            const $spinner = $form.find('.loading-spinner');

            // Disable buttons and show spinner
            $submitButton.prop('disabled', true);
            $cancelButton.prop('disabled', true);
            $spinner.show();

            const formData = new FormData();
            formData.append('action', 'wpiko_chatbot_send_contact_email');
            formData.append('security', wpikoChatbotAdmin.nonce);
            formData.append('to_email', currentUserEmail);
            formData.append('subject', subject);
            formData.append('message', message);

            const attachmentInput = document.getElementById('attachment');
            if (attachmentInput.files.length > 0) {
                formData.append('attachment', attachmentInput.files[0]);
            }

            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'post',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Email sent successfully');
                        closeContactModal();
                    } else {
                        alert('Failed to send email: ' + response.data);
                        // Re-enable buttons and hide spinner
                        $submitButton.prop('disabled', false);
                        $cancelButton.prop('disabled', false);
                        $spinner.hide();
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error sending email: ' + (xhr.responseJSON ? xhr.responseJSON.data : error));
                    // Re-enable buttons and hide spinner
                    $submitButton.prop('disabled', false);
                    $cancelButton.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });
    }

    // Contact user button click handler
    $('.contact-user').click(function(e) {
        e.preventDefault();
        if (currentUserEmail) {
            showContactForm();
        }
    });

    // Download conversation
    $('.download-conversation').click(function(e) {
        e.preventDefault();
        if (!currentSessionId) return;

        // Get selected language
        const selectedLanguage = $('#translation-language').val();
        const isTranslating = selectedLanguage && selectedLanguage !== 'none';
        
        // Show loading state
        const $button = $(this);
        const originalText = $button.html();
        $button.prop('disabled', true);
        
        if (isTranslating) {
            $button.html('<span class="dashicons dashicons-update dashicons-spin"></span> Translating...');
        } else {
            $button.html('Downloading...');
        }

        // Determine which action to use
        const action = isTranslating ? 'wpiko_chatbot_download_translated_conversation' : 'wpiko_chatbot_download_conversation';
        const ajaxData = {
            action: action,
            session_id: currentSessionId,
            security: wpikoChatbotAdmin.nonce
        };
        
        // Add language parameter if translating
        if (isTranslating) {
            ajaxData.language = selectedLanguage;
        }

        $.ajax({
            url: wpikoChatbotAdmin.ajax_url,
            type: 'post',
            data: ajaxData,
            success: function(response) {
                // Restore button state
                $button.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    // Create a Blob with HTML content and correct MIME type
                    const blob = new Blob([response.data], { type: 'text/html;charset=utf-8' });
                    const link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    
                    // Add language suffix to filename if translated
                    let filename = 'conversation_' + currentSessionId;
                    if (isTranslating) {
                        const langCode = selectedLanguage.replace(/\s+/g, '_').toLowerCase();
                        filename += '_' + langCode;
                    }
                    link.download = filename + '.html';
                    link.click();
                } else {
                    alert('Failed to download conversation: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Restore button state
                $button.prop('disabled', false).html(originalText);
                
                let errorMessage = 'Error downloading conversation';
                if (isTranslating) {
                    errorMessage = 'Error translating conversation. Please try again or download without translation.';
                }
                alert(errorMessage);
                console.error('Download error:', error);
            }
        });
    });

    // Delete conversation
    $('.delete-conversation').click(function(e) {
        e.preventDefault();
        if (!currentSessionId) return;
        
        if (confirm('Are you sure you want to delete this conversation?')) {
            $.ajax({
                url: wpikoChatbotAdmin.ajax_url,
                type: 'post',
                data: {
                    action: 'wpiko_chatbot_delete_conversation',
                    session_id: currentSessionId,
                    security: wpikoChatbotAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete conversation');
                    }
                },
                error: function() {
                    alert('Error deleting conversation');
                }
            });
        }
    });

    // Emails Download Functionality
    $('#download_emails').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_download_emails',
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const blob = new Blob([response.data], { type: 'text/csv' });
                    const link = document.createElement('a');
                    link.href = window.URL.createObjectURL(blob);
                    link.download = 'chatbot_user_emails.csv';
                    link.click();
                } else {
                    alert('Failed to download emails: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while downloading emails.');
            }
        });
    });

    // Initialize timestamp updates
    updateTimestamps();
    setInterval(updateTimestamps, 60000);
});

// Timestamp Display Functions
function timeAgo(timestamp) {
    const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);
    let interval = seconds / 31536000;
    
    if (interval > 1) return Math.floor(interval) + " years ago";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + " months ago";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + " days ago";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + " hours ago";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + " minutes ago";
    return Math.floor(seconds) + " seconds ago";
}

function updateTimestamps() {
    document.querySelectorAll('.timestamp').forEach(element => {
        const timestamp = element.getAttribute('data-timestamp');
        if (timestamp) {
            element.textContent = timeAgo(timestamp);
        }
    });
}

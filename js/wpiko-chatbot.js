document.addEventListener('DOMContentLoaded', function () {
    // Check for cache version mismatch and notify if needed
    checkCacheVersion();

    const inputField = document.getElementById('chatbot-input');
    const sendButton = document.getElementById('chatbot-send');
    const messagesContainer = document.getElementById('chatbot-messages');
    const downloadButton = document.getElementById('download-transcript');
    let threadId = sessionStorage.getItem('wpiko_chatbot_thread_id') || null;
    const isTranscriptDownloadEnabled = wpikoChatbot.enable_transcript_download === '1';
    let isSoundEnabled = wpikoChatbot.sound_enabled === '1';

    // Request queue: prevent sending new messages while one is in progress
    let isRequestInProgress = false;

    // Optimistic Response ID: track the last response ID for multi-turn conversations
    // This prevents race conditions when users send messages quickly
    let lastResponseId = sessionStorage.getItem('wpiko_chatbot_last_response_id') || null;

    // Human takeover polling
    let pollingInterval = null;
    let lastKnownMessageId = 0;
    let takeoverCheckInterval = null;

    // User presence heartbeat
    let heartbeatInterval = null;
    let heartbeatNeeded = false; // Demand-driven: only true when admin is watching or takeover active
    let sameSiteNavigationIntentAt = 0;

    // Sound On/Off functionality
    const toggleSoundOption = document.getElementById('toggle-sound');
    const isProTakeoverFrontendActive = !!(wpikoChatbot && wpikoChatbot.pro_takeover_frontend);
    if (toggleSoundOption) {
        if (isSoundEnabled) {
            toggleSoundOption.style.display = 'block';
            toggleSoundOption.addEventListener('click', function () {
                isSoundEnabled = !isSoundEnabled;
                updateSoundToggleDisplay();
                // Save the sound preference to localStorage
                localStorage.setItem('wpiko_chatbot_sound_enabled', isSoundEnabled ? '1' : '0');
            });
        } else {
            toggleSoundOption.style.display = 'none';
        }
    }

    function updateSoundToggleDisplay() {
        if (!toggleSoundOption) return;

        const soundOnSpan = toggleSoundOption.querySelector('.sound-on');
        const soundOffSpan = toggleSoundOption.querySelector('.sound-off');
        if (isSoundEnabled) {
            soundOnSpan.style.display = 'inline';
            soundOffSpan.style.display = 'none';
        } else {
            soundOnSpan.style.display = 'none';
            soundOffSpan.style.display = 'inline';
        }
    }

    // Initialize sound toggle display
    updateSoundToggleDisplay();

    // Function for menu Button
    const menuButton = document.getElementById('chatbot-menu-button');
    const menuDropdown = document.getElementById('chatbot-menu-dropdown');
    if (menuDropdown) {
        menuDropdown.style.display = 'none'; // Ensure menu is always closed initially
    }
    const downloadTranscriptOption = document.getElementById('download-transcript');

    if (menuButton) {
        menuButton.addEventListener('click', function (e) {
            e.stopPropagation();
            menuDropdown.style.display = menuDropdown.style.display === 'none' ? 'block' : 'none';
        });
    }

    if (downloadTranscriptOption && isTranscriptDownloadEnabled) {
        downloadTranscriptOption.addEventListener('click', function () {
            downloadTranscript();
            menuDropdown.style.display = 'none';
        });
    } else if (downloadTranscriptOption) {
        downloadTranscriptOption.style.display = 'none';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function () {
        if (menuDropdown) {
            menuDropdown.style.display = 'none';
        }
    });

    // Prevent dropdown from closing when clicking inside it
    if (menuDropdown) {
        menuDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    // Auto-scroll function
    function scrollToBottom() {
        const mainChatMessages = document.getElementById('chatbot-messages');
        const floatingChatMessages = document.querySelector('#wpiko-chatbot-floating-container #chatbot-messages');

        if (mainChatMessages) {
            mainChatMessages.scrollTop = mainChatMessages.scrollHeight;
        }

        if (floatingChatMessages) {
            floatingChatMessages.scrollTop = floatingChatMessages.scrollHeight;
        }
    }

    // Initialize chatbot with stored messages
    function initChatbot() {
        if (!messagesContainer) return;

        // Clear existing messages
        messagesContainer.innerHTML = '';

        // Get the current welcome message (could be null)
        const currentWelcomeMessage = getWelcomeMessage();

        const storedMessages = sessionStorage.getItem('wpiko_chatbot_messages');
        if (storedMessages) {
            const messages = JSON.parse(storedMessages);
            messages.forEach((message, index) => {
                if (index === 0 && message.type === 'bot' && currentWelcomeMessage) {
                    // Update the first message if it's a bot message and there's a welcome message
                    appendMessage('bot', currentWelcomeMessage, 'general_error', true, true);
                } else {
                    appendMessage(message.type, message.content, message.errorType || 'general_error', true, true, message.meta || {});
                }
            });

            // Hide pre-made questions if there are stored messages (more than just the welcome message)
            if (messages.length > 1 || (messages.length === 1 && messages[0].type === 'user')) {
                hidePreMadeQuestions();
            } else {
                showPreMadeQuestions();
            }
        } else {
            // If no stored messages, show pre-made questions
            showPreMadeQuestions();

            // Add welcome message if it exists
            if (currentWelcomeMessage) {
                appendMessage('bot', currentWelcomeMessage, 'general_error', true, true);
                sessionStorage.setItem('wpiko_chatbot_messages', JSON.stringify([{ type: 'bot', content: currentWelcomeMessage }]));
            }
        }

        // Scroll to bottom for both main chatbot and floating chatbot
        scrollToBottom();

        // Restore chatbot open/closed state
        const isChatbotOpen = sessionStorage.getItem('wpiko_chatbot_is_open') === 'true';
        const floatingContainer = document.getElementById('wpiko-chatbot-floating-container');
        const floatingWrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
        if (floatingContainer && floatingWrapper) {
            floatingContainer.style.display = isChatbotOpen ? 'block' : 'none';
            floatingWrapper.classList.toggle('open', isChatbotOpen);
            setExpandedState(isChatbotOpen && sessionStorage.getItem('wpiko_chatbot_is_expanded') === 'true');

            // If the floating chatbot is open, scroll to bottom after a short delay
            if (isChatbotOpen) {
                setTimeout(scrollToBottom, 100);
                // Make sure badge is cleared if opened
                sessionStorage.removeItem('wpiko_chatbot_unread_count');
            } else {
                // Attempt to show badge if unread count > 0 on load
                setTimeout(function () {
                    if (typeof unreadCount !== 'undefined' && unreadCount > 0) {
                        const notificationBadge = document.getElementById('wpiko-chatbot-notification-badge');
                        if (notificationBadge) {
                            notificationBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                            notificationBadge.style.display = 'flex';
                            floatingWrapper.classList.add('has-notification');
                        }
                    }
                }, 50);
            }
        }
    }

    // Get the welcome message
    function getWelcomeMessage() {
        // Check if welcome_message exists and is not an empty string
        if (wpikoChatbot && wpikoChatbot.welcome_message !== undefined && wpikoChatbot.welcome_message !== '') {
            return wpikoChatbot.welcome_message;
        }

        // If welcome_message is undefined or an empty string, return null
        return null;
    }

    // Floating Chatbot Logic
    let originalOverflow;

    function disableMainScroll() {
        document.documentElement.classList.add('chatbot-open');
        document.body.classList.add('chatbot-open');
    }

    function enableMainScroll() {
        document.documentElement.classList.remove('chatbot-open');
        document.body.classList.remove('chatbot-open');
    }

    const floatingWrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
    const floatingContainer = document.getElementById('wpiko-chatbot-floating-container');
    const floatingText = document.getElementById('wpiko-chatbot-floating-text');
    const notificationBadge = document.getElementById('wpiko-chatbot-notification-badge');
    const expandButton = document.getElementById('wpiko-chatbot-expand');
    let unreadCount = parseInt(sessionStorage.getItem('wpiko_chatbot_unread_count') || '0', 10);

    function setExpandedState(isExpanded) {
        if (!floatingContainer || !expandButton) return;

        floatingContainer.classList.toggle('wpiko-chatbot-expanded', isExpanded);
        expandButton.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        expandButton.setAttribute('aria-label', isExpanded ? 'Restore chatbot size' : 'Expand chatbot');
        expandButton.setAttribute('title', isExpanded ? 'Restore chatbot size' : 'Expand chatbot');
        sessionStorage.setItem('wpiko_chatbot_is_expanded', isExpanded ? 'true' : 'false');
    }

    function isChatbotClosed() {
        return floatingContainer && floatingContainer.style.display === 'none';
    }

    function showNotificationBadge() {
        if (!notificationBadge || !floatingWrapper) return;
        unreadCount++;
        sessionStorage.setItem('wpiko_chatbot_unread_count', unreadCount);
        notificationBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
        notificationBadge.style.display = 'flex';
        floatingWrapper.classList.add('has-notification');
    }

    function clearNotificationBadge() {
        if (!notificationBadge || !floatingWrapper) return;
        unreadCount = 0;
        sessionStorage.removeItem('wpiko_chatbot_unread_count');
        notificationBadge.style.display = 'none';
        notificationBadge.textContent = '';
        floatingWrapper.classList.remove('has-notification');
    }

    // Call initChatbot after DOM is loaded
    initChatbot();

    // Start background takeover check if we already have an active conversation
    if (threadId && !isProTakeoverFrontendActive) {
        startTakeoverCheck(threadId);
    }

    // Mobile close button functionality
    const mobileCloseButton = document.getElementById('wpiko-chatbot-mobile-close');

    if (mobileCloseButton && floatingContainer && floatingWrapper) {
        mobileCloseButton.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent event bubbling
            floatingContainer.style.display = 'none';
            floatingWrapper.classList.remove('open');
            sessionStorage.setItem('wpiko_chatbot_is_open', 'false');
            setExpandedState(false);
            enableMainScroll();
        });
    }

    if (expandButton && floatingContainer) {
        expandButton.addEventListener('click', function (e) {
            e.stopPropagation();
            setExpandedState(!floatingContainer.classList.contains('wpiko-chatbot-expanded'));
            setTimeout(scrollToBottom, 100);
        });
    }

    if (floatingWrapper && floatingContainer) {
        floatingWrapper.addEventListener('click', function () {
            if (floatingContainer.style.display === 'none') {
                floatingContainer.style.display = 'block';
                floatingWrapper.classList.add('open');
                sessionStorage.setItem('wpiko_chatbot_is_open', 'true');
                clearNotificationBadge();
                disableMainScroll();

                // Ensure it scrolls to bottom after the widget opens
                setTimeout(scrollToBottom, 100);
            } else {
                floatingContainer.style.display = 'none';
                floatingWrapper.classList.remove('open');
                sessionStorage.setItem('wpiko_chatbot_is_open', 'false');
                setExpandedState(false);
                enableMainScroll();
            }
        });
    }

    // Function to clears the chat
    function clearChat() {
        // Trigger sound immediately when clear chat is confirmed
        if (isSoundEnabled) {
            const clearChatEvent = new CustomEvent('wpiko-chatbot-clear', {
                detail: { isSoundEnabled: true }
            });
            document.dispatchEvent(clearChatEvent);
        }

        // Tell the server this thread was explicitly reset by the visitor.
        if (typeof sendOfflineBeacon === 'function') {
            sendOfflineBeacon('chat_cleared');
        }

        messagesContainer.innerHTML = '';
        sessionStorage.removeItem('wpiko_chatbot_messages');

        // Reset the conversation chain - clear thread ID and response ID
        threadId = null;
        lastResponseId = null;
        sessionStorage.removeItem('wpiko_chatbot_thread_id');
        sessionStorage.removeItem('wpiko_chatbot_last_response_id');

        // Stop heartbeat and polling since conversation is cleared
        stopHeartbeat();
        stopTakeoverPolling();

        // Display welcome message after clearing only if it exists
        const welcomeMessage = getWelcomeMessage();
        if (welcomeMessage) {
            appendMessage('bot', welcomeMessage, 'general_error', true, true);
        }

        // Show pre-made questions after clearing the chat
        showPreMadeQuestions();
    }


    // Clear chat button
    const clearChatOption = document.getElementById('clear-chat');

    if (clearChatOption) {
        clearChatOption.addEventListener('click', function () {
            if (confirm('Are you sure you want to clear the chat history?')) {
                clearChat();
                menuDropdown.style.display = 'none';
            }
        });
    }

    // Function to hide the pre-made questions
    function hidePreMadeQuestions() {
        const preMadeQuestions = document.getElementById('pre-made-questions');
        if (preMadeQuestions) {
            preMadeQuestions.style.display = 'none';
        }
    }

    // Function to show the pre-made questions
    function showPreMadeQuestions() {
        const preMadeQuestions = document.getElementById('pre-made-questions');
        if (preMadeQuestions) {
            preMadeQuestions.style.display = 'flex';
            // Re-trigger staggered entrance animations
            const buttons = preMadeQuestions.querySelectorAll('.pre-made-question');
            buttons.forEach(function (btn) {
                btn.style.animation = 'none';
                btn.offsetHeight; // force reflow
                btn.style.animation = '';
            });
        }
    }

    // Add event listeners for pre-made questions
    const preMadeQuestions = document.querySelectorAll('.pre-made-question');
    preMadeQuestions.forEach(question => {
        question.addEventListener('click', function () {
            const questionText = this.textContent;
            document.getElementById('chatbot-input').value = questionText;
            sendMessage();

            // Hide pre-made questions after selection in floating chatbot
            if (floatingWrapper && floatingContainer) {
                hidePreMadeQuestions();
            }
        });
    });

    // Chatbot Status
    function updateChatbotStatus(isOnline) {
        // If isOnline is not provided, check configComplete
        if (isOnline === undefined) {
            isOnline = wpikoChatbot.configComplete;
        }

        const statusDot = document.getElementById('chatbot-status-dot');

        if (statusDot) {
            statusDot.className = isOnline ? 'online' : 'offline';
        }
    }

    // Call this function when the page loads
    updateChatbotStatus();

    if (sendButton) {
        sendButton.addEventListener('click', sendMessage);
    }

    // Shift+Enter Function
    if (inputField) {
        inputField.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                if (e.shiftKey) {
                    // Shift+Enter: add a new line
                    return true;
                } else {
                    // Enter without shift: send message
                    e.preventDefault();
                    sendMessage();
                }
            }
        });
    }

    // Click Event for download button
    if (downloadButton) {
        downloadButton.addEventListener('click', function () {
            downloadTranscript();
        });
    }

    // Send Message Function
    function sendMessage() {
        const message = inputField.value.trim();
        if (message) {
            // Request Queue: Prevent sending if a request is already in progress
            if (isRequestInProgress) {
                console.log('WPiko Chatbot: Request already in progress, please wait...');
                return;
            }

            // Check if pro plugin has email validation and run it
            if (typeof window.wpikoProValidateEmailBeforeSend === 'function') {
                if (!window.wpikoProValidateEmailBeforeSend()) {
                    return;
                }
            }

            const userEmail = getUserEmail();
            const userName = getUserName();

            // Check if the chatbot is offline
            if (!wpikoChatbot.configComplete) {
                appendMessage('error', wpikoChatbot.errors.general_error, 'config_error');
                updateChatbotStatus();
                return;
            }

            appendMessage('user', message);
            inputField.value = '';

            var shouldShowLoadingIndicator = true;
            if (typeof window.wpikoChatbotShouldShowLoadingIndicator === 'function') {
                shouldShowLoadingIndicator = window.wpikoChatbotShouldShowLoadingIndicator({
                    message: message,
                    threadId: threadId
                }) !== false;
            }

            if (shouldShowLoadingIndicator) {
                appendLoadingIndicator();
            }

            // Scroll to bottom for both main chatbot and floating chatbot
            scrollToBottom();

            // Hide pre-made questions after sending a message
            hidePreMadeQuestions();

            // Request Queue: Mark request as in progress and disable input
            isRequestInProgress = true;
            setInputState(false);

            // Use the retry-enabled message sending
            sendMessageWithRetry(message, userEmail, userName, false);
        }
    }

    /**
     * Refresh the nonce from the server
     * @returns {Promise} Resolves with the new nonce, or rejects on error
     */
    function refreshNonce() {
        return new Promise(function (resolve, reject) {
            jQuery.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'post',
                timeout: 10000,
                data: {
                    action: 'wpiko_chatbot_refresh_nonce'
                },
                success: function (response) {
                    if (response.success && response.data && response.data.nonce) {
                        // Update the global nonce
                        wpikoChatbot.nonce = response.data.nonce;
                        // Update contact form nonce if available
                        if (response.data.contact_form_nonce) {
                            wpikoChatbot.contact_form_nonce = response.data.contact_form_nonce;
                        }
                        console.log('WPiko Chatbot: Nonce refreshed successfully');
                        resolve(response.data.nonce);
                    } else {
                        reject(new Error('Invalid nonce refresh response'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('WPiko Chatbot: Failed to refresh nonce:', error);
                    reject(error);
                }
            });
        });
    }

    /**
     * Send message with automatic retry on nonce expiration (403 error)
     * @param {string} message - The message to send
     * @param {string} userEmail - User's email
     * @param {string} userName - User's name
     * @param {boolean} isRetry - Whether this is a retry attempt
     */
    function sendMessageWithRetry(message, userEmail, userName, isRetry) {
        if (shouldUseStreamingResponses()) {
            sendStreamingMessage(message, userEmail, userName, isRetry)
                .then(function () {
                    finishMessageRequest();
                })
                .catch(function (errorInfo) {
                    if (errorInfo && errorInfo.status === 403 && !isRetry) {
                        console.log('WPiko Chatbot: Nonce may be expired, attempting to refresh...');
                        refreshNonce()
                            .then(function () {
                                console.log('WPiko Chatbot: Retrying streamed message with new nonce...');
                                sendMessageWithRetry(message, userEmail, userName, true);
                            })
                            .catch(function () {
                                removeLoadingIndicator();
                                appendMessage('error', wpikoChatbot.errors.auth_failed, 'auth_error');
                                updateChatbotStatus();
                                finishMessageRequest();
                            });
                        return;
                    }

                    removeLoadingIndicator();
                    appendMessage('error', getStreamingErrorMessage(errorInfo), getStreamingErrorType(errorInfo));
                    updateChatbotStatus(errorInfo && errorInfo.keepOnline ? true : undefined);
                    finishMessageRequest();
                });
            return;
        }

        sendAjaxMessageWithRetry(message, userEmail, userName, isRetry);
    }

    function shouldUseStreamingResponses() {
        return wpikoChatbot.streaming_enabled === '1'
            && typeof window.fetch === 'function'
            && typeof window.ReadableStream !== 'undefined'
            && typeof window.TextDecoder !== 'undefined';
    }

    function sendAjaxMessageWithRetry(message, userEmail, userName, isRetry) {
        jQuery.ajax({
            url: wpikoChatbot.ajax_url,
            type: 'post',
            timeout: 90000, // 90 second timeout - gives server time to respond but prevents infinite wait
            data: {
                action: 'wpiko_chatbot_send_message',
                message: message,
                thread_id: threadId,
                security: wpikoChatbot.nonce,
                wpiko_chatbot_nonce: wpikoChatbot.nonce,
                user_email: userEmail,
                user_name: userName,
                previous_response_id: lastResponseId // Optimistic Response ID to prevent race conditions
            },
            success: function (response) {
                removeLoadingIndicator();
                if (response.success === false) {
                    console.error('Error in chatbot response:', response);
                    if (response.data && response.data.debug) {
                        console.error('OpenAI debug:', response.data.debug);
                    }
                    var msg = response.data && response.data.message ? response.data.message : wpikoChatbot.errors.general_error;
                    // If admin-only debug is present, append status code for quicker triage (UI stays generic otherwise)
                    if (response.data && response.data.debug && response.data.debug.status) {
                        msg += ' (code: ' + response.data.debug.status + ')';
                    }
                    appendMessage('error', msg, (response.data && response.data.type) || 'general_error');
                    updateChatbotStatus(false);
                } else {
                    appendMessage('bot', response.data.response);
                    handleSuccessfulResponseData(response.data || {});
                }
            },
            error: function (xhr, status, error) {
                // Check if this is a 403 error (nonce expired) and we haven't retried yet
                if (xhr.status === 403 && !isRetry) {
                    console.log('WPiko Chatbot: Nonce may be expired, attempting to refresh...');

                    // Try to refresh the nonce and retry the request
                    refreshNonce()
                        .then(function () {
                            console.log('WPiko Chatbot: Retrying message with new nonce...');
                            sendMessageWithRetry(message, userEmail, userName, true);
                        })
                        .catch(function () {
                            // Nonce refresh failed, show error to user
                            removeLoadingIndicator();
                            appendMessage('error', wpikoChatbot.errors.auth_failed, 'auth_error');
                            updateChatbotStatus();
                        });
                    return;
                }

                console.error('AJAX error:', status, error);
                console.error('Response Text:', xhr.responseText);
                removeLoadingIndicator();
                let errorMessage = wpikoChatbot.errors.general_error;
                let errorType = 'general_error';

                // Handle specific error types with actionable messages
                if (status === 'timeout') {
                    errorMessage = wpikoChatbot.errors.connection_issue;
                    errorType = 'timeout_error';
                } else if (status === 'abort') {
                    errorMessage = wpikoChatbot.errors.general_error; // Or create a cancellation message if needed, but general is fine
                    errorType = 'abort_error';
                } else if (xhr.status === 0) {
                    errorMessage = wpikoChatbot.errors.connection_issue;
                    errorType = 'network_error';
                } else if (xhr.status === 403) {
                    // This only triggers if retry also failed
                    errorMessage = wpikoChatbot.errors.auth_failed;
                    errorType = 'auth_error';
                } else if (xhr.status === 429) {
                    errorMessage = wpikoChatbot.errors.server_busy;
                    errorType = 'rate_limit_error';
                } else if (xhr.status >= 500) {
                    errorMessage = wpikoChatbot.errors.server_busy;
                    errorType = 'server_error';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    if (typeof xhr.responseJSON.data === 'object' && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                        errorType = xhr.responseJSON.data.type || 'general_error';
                    } else if (typeof xhr.responseJSON.data === 'string') {
                        errorMessage = xhr.responseJSON.data;
                    }
                } else if (xhr.status !== 200 && xhr.status !== 0) {
                    errorMessage = wpikoChatbot.errors.general_error + ' (Status: ' + xhr.status + ')';
                }

                appendMessage('error', errorMessage, errorType);
                updateChatbotStatus();
            },
            complete: function () {
                finishMessageRequest();
            }
        });
    }

    async function sendStreamingMessage(message, userEmail, userName) {
        const formData = new FormData();
        formData.append('action', 'wpiko_chatbot_send_message');
        formData.append('message', message);
        formData.append('thread_id', threadId || '');
        formData.append('security', wpikoChatbot.nonce);
        formData.append('wpiko_chatbot_nonce', wpikoChatbot.nonce);
        formData.append('user_email', userEmail || '');
        formData.append('user_name', userName || '');
        formData.append('previous_response_id', lastResponseId || '');
        formData.append('stream', '1');

        const response = await fetch(wpikoChatbot.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        if (response.status === 403) {
            throw { status: 403, type: 'auth_error' };
        }

        if (!response.ok) {
            throw { status: response.status, type: response.status >= 500 ? 'server_error' : 'general_error' };
        }

        if (!response.body) {
            throw { type: 'network_error' };
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let accumulatedText = '';
        let streamingElement = null;
        let streamFinished = false;

        while (true) {
            const readResult = await reader.read();
            if (readResult.done) {
                break;
            }

            buffer += decoder.decode(readResult.value, { stream: true });
            const events = buffer.split(/\n\n/);
            buffer = events.pop();

            for (let index = 0; index < events.length; index++) {
                const parsedEvent = parseSseEvent(events[index]);
                if (!parsedEvent) {
                    continue;
                }

                if (parsedEvent.event === 'start') {
                    if (parsedEvent.data && parsedEvent.data.thread_id) {
                        threadId = parsedEvent.data.thread_id;
                        sessionStorage.setItem('wpiko_chatbot_thread_id', threadId);
                    }
                    continue;
                }

                if (parsedEvent.event === 'delta') {
                    const delta = parsedEvent.data && typeof parsedEvent.data.delta === 'string' ? parsedEvent.data.delta : '';
                    if (!delta) {
                        continue;
                    }

                    removeLoadingIndicator();
                    accumulatedText += delta;
                    if (!streamingElement) {
                        streamingElement = appendStreamingBotMessage();
                    }
                    pushStreamingDelta(streamingElement, accumulatedText);
                    continue;
                }

                if (parsedEvent.event === 'done') {
                    removeLoadingIndicator();
                    streamFinished = true;
                    const responseData = parsedEvent.data || {};

                    if (responseData.response) {
                        if (streamingElement) {
                            finishStreamingBotMessage(streamingElement, responseData.response);
                            storeMessage('bot', responseData.response, 'general_error', {});
                        } else {
                            appendMessage('bot', responseData.response);
                        }
                    } else if (streamingElement) {
                        cancelStreamingBotMessage(streamingElement);
                        streamingElement.remove();
                    }

                    handleSuccessfulResponseData(responseData);
                    continue;
                }

                if (parsedEvent.event === 'error') {
                    if (streamingElement) {
                        cancelStreamingBotMessage(streamingElement);
                        streamingElement.remove();
                    }
                    const errorData = parsedEvent.data || {};
                    throw {
                        message: errorData.message,
                        type: errorData.type || 'general_error',
                        debug: errorData.debug
                    };
                }
            }
        }

        if (buffer.trim() !== '') {
            const parsedEvent = parseSseEvent(buffer);
            if (parsedEvent && parsedEvent.event === 'error') {
                const errorData = parsedEvent.data || {};
                throw { message: errorData.message, type: errorData.type || 'general_error' };
            }
        }

        if (!streamFinished) {
            if (streamingElement) {
                cancelStreamingBotMessage(streamingElement);
                streamingElement.remove();
            }
            throw { type: 'network_error' };
        }
    }

    function parseSseEvent(rawEvent) {
        const lines = rawEvent.split(/\r?\n/);
        let eventName = 'message';
        const dataLines = [];

        lines.forEach(function (line) {
            if (line.indexOf('event:') === 0) {
                eventName = line.slice(6).trim();
            } else if (line.indexOf('data:') === 0) {
                dataLines.push(line.slice(5).replace(/^ /, ''));
            }
        });

        if (!dataLines.length) {
            return null;
        }

        let data = dataLines.join('\n');
        try {
            data = JSON.parse(data);
        } catch (error) {
            data = { raw: data };
        }

        return { event: eventName, data: data };
    }

    function appendStreamingBotMessage() {
        if (isSoundEnabled) {
            const messageReceivedEvent = new CustomEvent('wpiko-chatbot-message-received', {
                detail: { isSoundEnabled: true }
            });
            document.dispatchEvent(messageReceivedEvent);
        }

        if (isChatbotClosed()) {
            showNotificationBadge();
        }

        const messageElement = document.createElement('div');
        messageElement.className = 'message-container';
        messageElement.innerHTML = `
                <div class="message-wrapper bot-message-wrapper">
                    <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                    <div class="bot-message streaming"><span class="stream-text"></span><span class="wpiko-stream-caret" aria-hidden="true"></span></div>
                </div>`;
        messagesContainer.appendChild(messageElement);

        const botMessage = messageElement.querySelector('.bot-message');
        messageElement._streamState = {
            raw: '',
            revealed: 0,
            finalHtml: null,
            finished: false,
            rafId: null,
            lastRenderLen: -1,
            botMessage: botMessage,
            textSpan: botMessage.querySelector('.stream-text'),
            caret: botMessage.querySelector('.wpiko-stream-caret')
        };

        scrollToBottom();
        return messageElement;
    }

    // Receive a new accumulated raw chunk and (re)start the paced reveal loop.
    function pushStreamingDelta(messageElement, accumulatedRaw) {
        const st = messageElement && messageElement._streamState;
        if (!st || st.finished) return;
        st.raw = String(accumulatedRaw || '');
        ensureStreamLoop(messageElement);
    }

    // Mark the stream as complete. Lets the paced reveal drain any remaining
    // words, then swaps in the authoritative server HTML and removes the caret.
    function finishStreamingBotMessage(messageElement, finalHtml) {
        const st = messageElement && messageElement._streamState;
        if (!st) return;
        st.finalHtml = (finalHtml == null) ? '' : String(finalHtml);
        ensureStreamLoop(messageElement);
    }

    // Stop a stream early (errors / aborts) without leaving an orphan rAF.
    function cancelStreamingBotMessage(messageElement) {
        const st = messageElement && messageElement._streamState;
        if (!st) return;
        st.finished = true;
        if (st.rafId !== null) {
            cancelAnimationFrame(st.rafId);
            st.rafId = null;
        }
    }

    function ensureStreamLoop(messageElement) {
        const st = messageElement._streamState;
        if (!st || st.finished || st.rafId !== null) return;
        st.rafId = requestAnimationFrame(function () {
            st.rafId = null;
            stepStream(messageElement);
        });
    }

    function stepStream(messageElement) {
        const st = messageElement._streamState;
        if (!st || st.finished) return;

        // Element removed from DOM (e.g. conversation cleared) -> stop cleanly.
        if (!messageElement.isConnected) {
            cancelStreamingBotMessage(messageElement);
            return;
        }

        if (st.revealed < st.raw.length) {
            st.revealed = advanceRevealPointer(st.raw, st.revealed);
            renderStreamFrame(st);
            st.rafId = requestAnimationFrame(function () {
                st.rafId = null;
                stepStream(messageElement);
            });
            return;
        }

        // Caught up with everything revealed so far.
        if (st.finalHtml !== null) {
            finalizeStream(messageElement);
            return;
        }
        // Otherwise idle: caret keeps blinking via CSS until the next delta
        // calls ensureStreamLoop() again. No rAF spin while waiting.
    }

    // Reveal roughly one short word per frame (~60fps). Accelerates when the
    // backlog grows so the typing never lags far behind the network.
    function advanceRevealPointer(raw, pos) {
        const len = raw.length;
        if (pos >= len) return len;

        const backlog = len - pos;
        let words = 1;
        if (backlog > 120) {
            words = Math.ceil(backlog / 60);
        }

        let i = pos;
        for (let w = 0; w < words && i < len; w++) {
            // Include leading whitespace (newlines count as whitespace).
            while (i < len && isStreamSpace(raw.charAt(i))) i++;
            // Consume the word; chunk very long tokens (e.g. URLs) so frames stay cheap.
            const wordStart = i;
            while (i < len && !isStreamSpace(raw.charAt(i))) {
                i++;
                if (i - wordStart >= 40) break;
            }
        }

        if (i <= pos) i = Math.min(len, pos + 1);
        return i;
    }

    function isStreamSpace(ch) {
        return ch === ' ' || ch === '\n' || ch === '\t' || ch === '\r';
    }

    function renderStreamFrame(st) {
        if (st.revealed === st.lastRenderLen) return;
        st.lastRenderLen = st.revealed;
        st.textSpan.innerHTML = renderStreamingMarkdown(st.raw.slice(0, st.revealed));
        updateContainsLink(st.botMessage);
        scrollToBottom();
    }

    function finalizeStream(messageElement) {
        const st = messageElement._streamState;
        st.finished = true;
        if (st.rafId !== null) {
            cancelAnimationFrame(st.rafId);
            st.rafId = null;
        }

        if (st.finalHtml) {
            st.textSpan.innerHTML = String(st.finalHtml).replace(/\n/g, '<br>');
        } else if (st.finalHtml === '') {
            st.textSpan.innerHTML = renderStreamingMarkdown(st.raw);
        }

        if (st.caret && st.caret.parentNode) {
            st.caret.parentNode.removeChild(st.caret);
        }
        st.botMessage.classList.remove('streaming');
        updateContainsLink(st.botMessage);
        scrollToBottom();
    }

    function updateContainsLink(botMessage) {
        const html = botMessage.innerHTML;
        if (html.indexOf('<a') !== -1 || html.indexOf('mailto:') !== -1) {
            botMessage.classList.add('contains-link');
        } else {
            botMessage.classList.remove('contains-link');
        }
    }

    function isExternalStreamUrl(url) {
        try {
            return new URL(url, window.location.href).host !== window.location.host;
        } catch (error) {
            return true;
        }
    }

    // Client-side markdown renderer used while streaming. Mirrors the server-side
    // wpiko_chatbot_process_links()/process_markdown() logic so the in-progress
    // text looks the same as the final message. The authoritative server HTML
    // (links, product cards, contact forms) replaces this on completion.
    function renderStreamingMarkdown(text) {
        if (!text) return '';

        let out = String(text);

        // Hide contact-form markers (and any dangling/partial one at the very end)
        // so raw JSON never flashes before the real form arrives in the final HTML.
        out = out.replace(/\[wpiko-contact-form:[\s\S]*?\]/gi, '');
        out = out.replace(/<!--WPIKO_CONTACT_FORM:[\s\S]*?-->/gi, '');
        out = out.replace(/\[wpiko-contact-form:[\s\S]*$/i, '');
        out = out.replace(/<!--WPIKO_CONTACT_FORM:[\s\S]*$/i, '');

        // Neutralize any raw HTML coming from the model.
        out = escapeHtml(out);

        // Protect URLs so markdown emphasis characters inside them are not mangled.
        const urls = [];
        out = out.replace(/(https?:\/\/[^\s<>"]+)/gi, function (match) {
            const clean = match.replace(/[.,:;!?*]+$/, '');
            const suffix = match.slice(clean.length);
            urls.push(clean);
            return '%%URL' + (urls.length - 1) + '%%' + suffix;
        });

        // Headers (line-based).
        out = out.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        out = out.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        out = out.replace(/^# (.*?)$/gm, '<h1>$1</h1>');

        // Bold before italic (mirrors PHP order).
        out = out.replace(/\*\*([^*]+?)\*\*/g, '<strong>$1</strong>');
        out = out.replace(/__([^_]+?)__/g, '<strong>$1</strong>');
        out = out.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
        out = out.replace(/(^|[^A-Za-z0-9_])_([^_\n]+?)_(?=[^A-Za-z0-9_]|$)/g, '$1<em>$2</em>');

        // Restore protected URLs.
        out = out.replace(/%%URL(\d+)%%/g, function (match, idx) {
            return urls[parseInt(idx, 10)] || '';
        });

        // Markdown-style links [text](url).
        out = out.replace(/\[([^\]]+)\]\s*\(?\s*((?:https?:\/\/|www\.)[^\s)]+)\s*\)?/g, function (match, label, url) {
            url = url.replace(/^\(+|\)+$/g, '');
            if (url.indexOf('www.') === 0) url = 'http://' + url;
            const target = isExternalStreamUrl(url) ? ' target="_blank"' : '';
            return '<a href="' + escapeAttribute(url) + '"' + target + ' rel="noopener noreferrer">' + label + '</a>';
        });

        // Plain URLs (skip anything already inside a tag/anchor).
        out = out.replace(/((?:https?:\/\/|www\.)[^\s<>"]+)(?![^<>]*>|[^<>]*<\/a>)/gi, function (match) {
            const display = match.replace(/[.,:;!?*]+$/, '');
            let href = display;
            if (href.indexOf('www.') === 0) href = 'http://' + href;
            const target = isExternalStreamUrl(href) ? ' target="_blank"' : '';
            return '<a href="' + escapeAttribute(href) + '"' + target + ' rel="noopener noreferrer">' + display + '</a>';
        });

        // Email addresses.
        out = out.replace(/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/g, function (match, email) {
            return '<a href="mailto:' + escapeAttribute(email) + '">' + email + '</a>';
        });

        // Newlines to <br>, but not the one directly after a block header.
        out = out.replace(/\n/g, '<br>');
        out = out.replace(/<\/(h[1-3])><br>/g, '</$1>');

        return out;
    }

    function handleSuccessfulResponseData(data) {
        if (data.thread_id) {
            threadId = data.thread_id;
            sessionStorage.setItem('wpiko_chatbot_thread_id', threadId);
        }

        if (data.response_id) {
            lastResponseId = data.response_id;
            sessionStorage.setItem('wpiko_chatbot_last_response_id', lastResponseId);
        }

        updateChatbotStatus(true);

        if (typeof window.wpikoChatbotHandleTakeoverResponse === 'function') {
            window.wpikoChatbotHandleTakeoverResponse(data || {});
        }

        if (!isProTakeoverFrontendActive && data.human_takeover && threadId) {
            startTakeoverPolling(threadId);
        }

        if (threadId && !isProTakeoverFrontendActive) {
            startTakeoverCheck(threadId);
        }

        if (threadId) {
            startHeartbeat();
        }
    }

    function finishMessageRequest() {
        removeLoadingIndicator();
        isRequestInProgress = false;
        setInputState(true);
    }

    function getStreamingErrorMessage(errorInfo) {
        if (errorInfo && errorInfo.message) {
            return errorInfo.message;
        }

        if (errorInfo && errorInfo.status === 403) {
            return wpikoChatbot.errors.auth_failed;
        }

        if (errorInfo && errorInfo.status === 429) {
            return wpikoChatbot.errors.server_busy;
        }

        if (errorInfo && errorInfo.status >= 500) {
            return wpikoChatbot.errors.server_busy;
        }

        if (errorInfo && errorInfo.type === 'network_error') {
            return wpikoChatbot.errors.connection_issue;
        }

        return wpikoChatbot.errors.general_error;
    }

    function getStreamingErrorType(errorInfo) {
        if (errorInfo && errorInfo.type) {
            return errorInfo.type;
        }

        if (errorInfo && errorInfo.status === 403) {
            return 'auth_error';
        }

        if (errorInfo && errorInfo.status === 429) {
            return 'rate_limit_error';
        }

        if (errorInfo && errorInfo.status >= 500) {
            return 'server_error';
        }

        return 'general_error';
    }

    /**
     * Enable or disable the input field and send button
     * Used to prevent multiple simultaneous requests
     * @param {boolean} enabled - Whether to enable or disable the input
     */
    function setInputState(enabled) {
        if (inputField) {
            inputField.disabled = !enabled;
            if (enabled) {
                inputField.focus();
            }
        }
        if (sendButton) {
            sendButton.disabled = !enabled;
            sendButton.style.opacity = enabled ? '1' : '0.6';
            sendButton.style.cursor = enabled ? 'pointer' : 'not-allowed';
        }
    }

    //Loading Indicator
    function appendLoadingIndicator() {
        const loadingElement = document.createElement('div');
        loadingElement.className = 'message-container loading';
        loadingElement.innerHTML = `
            <div class="message-wrapper bot-message-wrapper">
                <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                <div class="bot-message">
                    <div class="loading-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                </div>
            </div>
        `;
        messagesContainer.appendChild(loadingElement);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Clear Loading Indicator
    function removeLoadingIndicator() {
        const loadingElements = messagesContainer.querySelectorAll('.loading');
        loadingElements.forEach(element => element.remove());
    }

    // Append Message
    function appendMessage(type, content, errorType = 'general_error', skipStoring = false, suppressSound = false, meta = {}) {
        if (content === null || content === '') return;
        const messageElement = document.createElement('div');
        messageElement.className = 'message-container';

        if (type === 'user') {
            messageElement.innerHTML = `
                <div class="message-wrapper user-message-wrapper">
                    <span class="user-message">${escapeHtml(content)}</span>
                    <div class="message-avatar user-avatar">
                        ${getUserAvatarSvg()}
                    </div>
                </div>`;
        } else if (type === 'bot') {

            // Sound functionality (bot response)
            if (isSoundEnabled && !suppressSound) {
                const messageReceivedEvent = new CustomEvent('wpiko-chatbot-message-received', {
                    detail: { isSoundEnabled: true }
                });
                document.dispatchEvent(messageReceivedEvent);
            }

            // Visual notification when chatbot is closed
            if (!suppressSound && isChatbotClosed()) {
                showNotificationBadge();
            }

            // Ensure content is a string before processing
            if (typeof content !== 'string') {
                console.warn('Bot message content is not a string:', content);
                content = String(content || '');
            }

            content = content.replace(/\n/g, '<br>');
            messageElement.innerHTML = `
                <div class="message-wrapper bot-message-wrapper">
                    <img src="${wpikoChatbot.botAvatarUrl}" alt="Bot" class="message-avatar bot-avatar">
                    <div class="bot-message ${content.includes('<a') || content.includes('mailto:') ? 'contains-link' : ''}">
                        <span>${content}</span>
                    </div>
                </div>`;
        } else if (type === 'admin') {
            const adminName = escapeHtml(meta.label || 'Live agent');
            const adminInitials = escapeHtml(getInitials(meta.label || 'Live agent'));
            const adminAvatar = meta.avatarUrl ? escapeAttribute(meta.avatarUrl) : '';

            if (isSoundEnabled && !suppressSound) {
                const messageReceivedEvent = new CustomEvent('wpiko-chatbot-message-received', {
                    detail: { isSoundEnabled: true }
                });
                document.dispatchEvent(messageReceivedEvent);
            }

            // Visual notification when chatbot is closed
            if (!suppressSound && isChatbotClosed()) {
                showNotificationBadge();
            }

            content = String(content || '').replace(/\n/g, '<br>');
            messageElement.innerHTML = `
                <div class="message-wrapper admin-message-wrapper">
                    ${adminAvatar
                    ? `<img src="${adminAvatar}" alt="${adminName}" class="message-avatar admin-avatar-image">`
                    : `<div class="message-avatar admin-avatar" aria-hidden="true">${adminInitials}</div>`}
                    <div class="admin-message-group">
                        <div class="admin-message-label">${adminName}</div>
                        <div class="admin-message ${content.includes('<a') || content.includes('mailto:') ? 'contains-link' : ''}">
                            <span>${content}</span>
                        </div>
                    </div>
                </div>`;
        } else if (type === 'system') {
            messageElement.classList.add('system-message-container');
            if (meta.eventType === 'takeover_notice' || meta.eventType === 'release_notice') {
                messageElement.classList.add('takeover-system-message');
            }
            messageElement.innerHTML = `<div class="system-message-pill">${escapeHtml(content)}</div>`;
        } else if (type === 'error') {
            messageElement.innerHTML = `<div class="error-message ${errorType}">${escapeHtml(content)}</div>`;

            // Trigger error sound
            if (isSoundEnabled) {
                const errorEvent = new CustomEvent('wpiko-chatbot-error', {
                    detail: { isSoundEnabled: true }
                });
                document.dispatchEvent(errorEvent);
            }
        }

        messagesContainer.appendChild(messageElement);
        scrollToBottom();

        if (!skipStoring) {
            storeMessage(type, content, errorType, meta);
        }
    }

    function storeMessage(type, content, errorType = 'general_error', meta = {}) {
        const storedMessages = JSON.parse(sessionStorage.getItem('wpiko_chatbot_messages') || '[]');
        storedMessages.push({ type, content, errorType, meta });
        sessionStorage.setItem('wpiko_chatbot_messages', JSON.stringify(storedMessages));
    }

    function getInitials(name) {
        return String(name || 'A')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map(function (part) {
                return part.charAt(0).toUpperCase();
            })
            .join('') || 'A';
    }

    window.wpikoChatbotAppendMessage = appendMessage;
    window.wpikoChatbotGetInitials = getInitials;

    function getUserAvatarSvg() {
        return `<svg class="user-avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>`;
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function escapeAttribute(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Function Download Transcript
    function downloadTranscript() {

        if (!isTranscriptDownloadEnabled) {
            console.log('Transcript download is disabled');
            return;
        }

        jQuery.ajax({
            url: wpikoChatbot.ajax_url,
            type: 'post',
            data: {
                action: 'get_chatbot_name',
                security: wpikoChatbot.nonce
            },
            success: function (response) {
                const chatbotName = response.success ? response.data : (wpikoChatbot.chatbotName || 'Chatbot');
                generateAndDownloadTranscript(chatbotName);
            },
            error: function () {
                generateAndDownloadTranscript(wpikoChatbot.chatbotName || 'Chatbot');
            }
        });
    }

    function generateAndDownloadTranscript(chatbotName) {
        if (!isTranscriptDownloadEnabled) {
            console.log('Transcript download is disabled');
            return;
        }
        const messages = messagesContainer.querySelectorAll('.message-container');

        const siteName = (wpikoChatbot && wpikoChatbot.siteName) ? wpikoChatbot.siteName : window.location.hostname;

        const dateObj = new Date();
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const dateGenerated = `${months[dateObj.getMonth()]} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;

        // Create HTML structure with inlined CSS
        let html = `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Chat Transcript - ${siteName}</title>`;

        // Add inlined CSS if available
        if (wpikoAjax && wpikoAjax.frontendTranscriptCss) {
            html += `\n            <style type="text/css">\n${wpikoAjax.frontendTranscriptCss}\n            </style>`;
        }

        html += `
        </head>
        <body>
            <div class="transcript-container">
                <div class="transcript-header">
                    <h1>Chat Transcript</h1>
                    <p>From ${siteName} | Generated on ${dateGenerated}</p>
                </div>
                <div class="messages-container">
        `;

        messages.forEach(message => {
            const userMessage = message.querySelector('.user-message');
            const botMessage = message.querySelector('.bot-message');
            const adminMessage = message.querySelector('.admin-message');
            const adminLabel = message.querySelector('.admin-message-label');
            const systemMessage = message.querySelector('.system-message-pill');
            const errorMessage = message.querySelector('.error-message');

            if (userMessage) {
                const userMessageText = userMessage.innerHTML;
                html += `
                <div class="message user">
                    <div class="message-header">
                        <span class="role-label">${typeof getUserName === 'function' ? (getUserName() || 'User') : 'User'}</span>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${userMessageText}</div>
                    </div>
                </div>`;
            }
            if (botMessage && !botMessage.querySelector('.loading-dots')) {
                html += `
                <div class="message bot">
                    <div class="message-header">
                        <span class="role-label">${chatbotName}</span>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${botMessage.innerHTML}</div>
                    </div>
                </div>`;
            }
            if (adminMessage) {
                const adminName = adminLabel ? adminLabel.textContent.trim() : 'Live agent';
                html += `
                <div class="message admin">
                    <div class="message-header">
                        <span class="role-label">${adminName}</span>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${adminMessage.innerHTML}</div>
                    </div>
                </div>`;
            }
            if (systemMessage) {
                const isTakeoverSystemMessage = message.classList.contains('takeover-system-message');
                html += `
                <div class="message bot">
                    ${isTakeoverSystemMessage ? '' : `<div class="message-header">
                        <span class="role-label">System</span>
                    </div>`}
                    <div class="message-content">
                        <div class="message-text">${systemMessage.innerHTML}</div>
                    </div>
                </div>`;
            }
            if (errorMessage) {
                html += `
                <div class="message error">
                    <div class="message-header">
                        <span class="role-label">Error</span>
                    </div>
                    <div class="message-content">
                        <div class="message-text">${errorMessage.innerHTML}</div>
                    </div>
                </div>`;
            }
        });

        html += `
                </div>
            </div>
        </body>
        </html>`;

        const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'chat-transcript.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }



    // Fallback functions for getUserEmail and getUserName
    // These provide basic functionality for logged-in users and pro plugin compatibility
    function getUserEmail() {
        // Always prioritize logged-in user details
        if (wpikoChatbot.is_user_logged_in) {
            return wpikoChatbot.user_email;
        }

        // Check if pro plugin has captured guest email data
        if (typeof localStorage !== 'undefined') {
            return localStorage.getItem('wpiko_chatbot_user_email') || '';
        }

        return '';
    }

    function getUserName() {
        // Always prioritize logged-in user details
        if (wpikoChatbot.is_user_logged_in) {
            return wpikoChatbot.user_name || '';
        }

        // Check if pro plugin has captured guest name data
        if (typeof localStorage !== 'undefined') {
            return localStorage.getItem('wpiko_chatbot_user_name') || '';
        }

        return '';
    }

    // Only set global functions if they don't already exist
    // Allow the pro plugin to override these functions
    if (typeof window.getUserEmail === 'undefined') {
        window.getUserEmail = getUserEmail;
    }
    if (typeof window.getUserName === 'undefined') {
        window.getUserName = getUserName;
    }

    /**
     * Start background check for takeover status.
     * This runs even when the user hasn't sent a message during takeover,
     * so admin-initiated takeover + replies are detected proactively.
     * Also piggybacks heartbeat to avoid a separate AJAX call.
     */
    function startTakeoverCheck(currentThreadId) {
        if (takeoverCheckInterval) return;

        // On first run, sync lastKnownMessageId so we don't replay old messages
        var synced = lastKnownMessageId > 0;

        takeoverCheckInterval = setInterval(function () {
            // If we're already in polling mode, no need to check
            if (pollingInterval) return;

            jQuery.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'post',
                timeout: 15000,
                data: {
                    action: 'wpiko_chatbot_check_new_messages',
                    thread_id: currentThreadId,
                    last_message_id: lastKnownMessageId,
                    heartbeat: isHeartbeatEnabled() && heartbeatNeeded ? 1 : 0,
                    security: wpikoChatbot.nonce
                },
                success: function (response) {
                    if (!response.success) return;

                    // Track demand-driven heartbeat flag from server
                    if (typeof response.data.heartbeat_needed !== 'undefined') {
                        heartbeatNeeded = !!response.data.heartbeat_needed;
                    }

                    // On first successful response, just sync the ID without showing old messages
                    if (!synced && response.data.messages && response.data.messages.length > 0) {
                        response.data.messages.forEach(function (msg) {
                            if (parseInt(msg.id, 10) > lastKnownMessageId) {
                                lastKnownMessageId = parseInt(msg.id, 10);
                            }
                        });
                        synced = true;
                        // If takeover is already active, start polling for future messages
                        if (response.data.human_takeover) {
                            startTakeoverPolling(currentThreadId);
                        }
                        return;
                    }
                    synced = true;

                    if (response.data.human_takeover) {
                        // Takeover became active — show any new messages and start full polling
                        if (response.data.messages && response.data.messages.length > 0) {
                            response.data.messages.forEach(function (msg) {
                                if (msg.event_type === 'takeover_notice') {
                                    if (parseInt(msg.id, 10) > lastKnownMessageId) {
                                        lastKnownMessageId = parseInt(msg.id, 10);
                                    }
                                    return;
                                }

                                appendMessage('bot', msg.message);
                                if (parseInt(msg.id, 10) > lastKnownMessageId) {
                                    lastKnownMessageId = parseInt(msg.id, 10);
                                }
                            });
                        }
                        startTakeoverPolling(currentThreadId);
                    }
                }
            });
        }, 5000);
    }

    /**
     * Stop the background takeover check
     */
    function stopTakeoverCheck() {
        if (takeoverCheckInterval) {
            clearInterval(takeoverCheckInterval);
            takeoverCheckInterval = null;
        }
    }

    /**
     * Start polling for admin messages during human takeover
     * Also piggybacks heartbeat to avoid a separate AJAX call.
     */
    function startTakeoverPolling(currentThreadId) {
        // Don't start if already polling
        if (pollingInterval) return;

        pollingInterval = setInterval(function () {
            jQuery.ajax({
                url: wpikoChatbot.ajax_url,
                type: 'post',
                timeout: 15000,
                data: {
                    action: 'wpiko_chatbot_check_new_messages',
                    thread_id: currentThreadId,
                    last_message_id: lastKnownMessageId,
                    heartbeat: isHeartbeatEnabled() && heartbeatNeeded ? 1 : 0,
                    security: wpikoChatbot.nonce
                },
                success: function (response) {
                    if (response.success && response.data.messages && response.data.messages.length > 0) {
                        response.data.messages.forEach(function (msg) {
                            appendMessage('bot', msg.message);
                            // Track the latest message ID
                            if (parseInt(msg.id, 10) > lastKnownMessageId) {
                                lastKnownMessageId = parseInt(msg.id, 10);
                            }
                        });
                    }
                    // Stop polling if takeover is no longer active
                    if (response.success && !response.data.human_takeover) {
                        stopTakeoverPolling();
                    }
                    // Track demand-driven heartbeat flag from server
                    if (response.success && typeof response.data.heartbeat_needed !== 'undefined') {
                        heartbeatNeeded = !!response.data.heartbeat_needed;
                    }
                },
                error: function () {
                    // Silently ignore polling errors
                }
            });
        }, 3000);
    }

    /**
     * Stop the takeover polling
     */
    function stopTakeoverPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
        stopTakeoverCheck();
    }

    /**
     * Check if the heartbeat feature is enabled
     * Only active when Pro plugin is installed with active license and PWA enabled
     */
    function isHeartbeatEnabled() {
        return !!(wpikoChatbot && wpikoChatbot.heartbeat_enabled);
    }

    /**
     * Send a heartbeat to the server to signal user is still online
     */
    function sendHeartbeat() {
        if (!isHeartbeatEnabled()) return;

        var currentThreadId = sessionStorage.getItem('wpiko_chatbot_thread_id');
        if (!currentThreadId) return;

        jQuery.ajax({
            url: wpikoChatbot.ajax_url,
            type: 'post',
            timeout: 10000,
            data: {
                action: 'wpiko_chatbot_user_heartbeat',
                thread_id: currentThreadId,
                security: wpikoChatbot.nonce
            },
            error: function () {
                // Silently ignore heartbeat errors
            }
        });
    }

    /**
     * Start the heartbeat interval (every 30 seconds)
     * Only fires standalone heartbeats when no takeover polling is active,
     * since polling requests piggyback the heartbeat signal.
     * Respects demand-driven heartbeat_needed flag from server.
     */
    function startHeartbeat() {
        if (!isHeartbeatEnabled()) return;
        if (heartbeatInterval) return;
        // Send immediately on start
        sendHeartbeat();
        heartbeatInterval = setInterval(function () {
            // Skip standalone heartbeat if polling/takeover check is already piggybacking it
            if (pollingInterval || takeoverCheckInterval) return;
            // Only send if server indicated heartbeat is needed (admin watching)
            if (!heartbeatNeeded) return;
            sendHeartbeat();
        }, 30000);
    }

    /**
     * Stop the heartbeat interval
     */
    function stopHeartbeat() {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    }

    /**
     * Send an immediate offline signal via sendBeacon (survives page unload)
     */
    function sendOfflineBeacon(status) {
        if (!isHeartbeatEnabled()) return;
        var currentThreadId = sessionStorage.getItem('wpiko_chatbot_thread_id');
        if (!currentThreadId) return;

        status = status || 'page_left';

        var data = new FormData();
        data.append('action', 'wpiko_chatbot_user_heartbeat');
        data.append('thread_id', currentThreadId);
        data.append('security', wpikoChatbot.nonce);
        data.append('status', status);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(wpikoChatbot.ajax_url, data);
        }
    }

    function markSameSiteNavigationIntent(targetUrl) {
        if (!targetUrl) return;

        try {
            var parsedUrl = new URL(targetUrl, window.location.href);

            if (parsedUrl.origin !== window.location.origin) return;
            if (parsedUrl.protocol !== 'http:' && parsedUrl.protocol !== 'https:') return;
            if (parsedUrl.pathname === window.location.pathname && parsedUrl.search === window.location.search) return;

            sameSiteNavigationIntentAt = Date.now();
        } catch (e) {
            // Ignore invalid URLs and fall back to the unload beacon.
        }
    }

    function shouldSuppressOfflineBeacon() {
        return sameSiteNavigationIntentAt > 0 && (Date.now() - sameSiteNavigationIntentAt) < 1500;
    }

    document.addEventListener('click', function (event) {
        var targetElement = event.target;
        if (!targetElement || typeof targetElement.closest !== 'function') return;
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var link = targetElement.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;

        var linkTarget = (link.getAttribute('target') || '').toLowerCase();
        if (linkTarget && linkTarget !== '_self') return;

        markSameSiteNavigationIntent(link.href);
    }, true);

    document.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;

        var form = event.target;
        if (!form || typeof form.getAttribute !== 'function') return;

        var formTarget = (form.getAttribute('target') || '').toLowerCase();
        if (formTarget && formTarget !== '_self') return;

        markSameSiteNavigationIntent(form.getAttribute('action') || window.location.href);
    }, true);

    // Start heartbeat if we already have an active conversation
    if (threadId) {
        startHeartbeat();
    }

    // Handle page visibility changes — pause heartbeat when tab is hidden
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopHeartbeat();
        } else if (sessionStorage.getItem('wpiko_chatbot_thread_id')) {
            startHeartbeat();
        }
    });

    // Send an immediate offline signal for real exits; same-site page navigation keeps the thread open.
    window.addEventListener('beforeunload', function () {
        if (!shouldSuppressOfflineBeacon()) {
            sendOfflineBeacon('page_left');
        }
        stopHeartbeat();
    });
});

// Cache version checking function
function checkCacheVersion() {
    // Get the cache version from meta tag
    const metaTag = document.querySelector('meta[name="wpiko-chatbot-cache-version"]');
    if (!metaTag) return;

    const currentCacheVersion = metaTag.getAttribute('content');
    const storedCacheVersion = localStorage.getItem('wpiko_chatbot_cache_version');

    // If cache version has changed, clear stored data and refresh
    if (storedCacheVersion && storedCacheVersion !== currentCacheVersion) {
        // Clear relevant localStorage items
        localStorage.removeItem('wpiko_chatbot_user_email');
        localStorage.removeItem('wpiko_chatbot_user_name');
        sessionStorage.removeItem('wpiko_chatbot_messages');
        sessionStorage.removeItem('wpiko_chatbot_thread_id');
        sessionStorage.removeItem('wpiko_chatbot_is_open');

        // Update stored cache version
        localStorage.setItem('wpiko_chatbot_cache_version', currentCacheVersion);

        // Optional: Show a subtle notification that content has been refreshed
        if (typeof console !== 'undefined') {
            console.log('WPiko Chatbot: Cache refreshed');
        }
    } else if (!storedCacheVersion) {
        // First time visit, store the cache version
        localStorage.setItem('wpiko_chatbot_cache_version', currentCacheVersion);
    }
}

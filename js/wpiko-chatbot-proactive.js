/**
 * Proactive Greeting for WPiko Chatbot
 *
 * Shows a greeting card next to the floating chat icon based on the
 * configured trigger (immediate / delay / scroll) and frequency
 * (once per session / only once / every visit). Visitors can open the
 * chat from the card or type a message directly in the quick message box.
 *
 * When the Pro plugin's Email Capture is enabled and the visitor has not
 * provided their details yet, the quick message is stored as pending and
 * sent automatically after the email capture step completes.
 */
document.addEventListener('DOMContentLoaded', function () {
    var greeting = document.getElementById('wpiko-chatbot-proactive-greeting');
    var floatingWrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
    var floatingContainer = document.getElementById('wpiko-chatbot-floating-container');
    var config = (typeof wpikoChatbot !== 'undefined' && wpikoChatbot.proactive) ? wpikoChatbot.proactive : null;

    var PENDING_KEY = 'wpiko_chatbot_proactive_pending';
    var DONE_SESSION_KEY = 'wpiko_chatbot_proactive_done';
    var DONE_ONCE_KEY = 'wpiko_chatbot_proactive_done_once';
    var SETTINGS_VERSION_KEY = 'wpiko_chatbot_proactive_settings_version';

    syncSettingsVersion();

    // Handle a pending quick message left over from the email capture flow
    // (email-capture.js reloads the page after the visitor submits details).
    // Runs even if the greeting card itself is suppressed on this load.
    setTimeout(processPendingMessage, 600);

    if (!greeting || !floatingWrapper || !floatingContainer || !config) {
        return;
    }

    var closeButton = document.getElementById('wpiko-chatbot-proactive-close');
    var bubble = document.getElementById('wpiko-chatbot-proactive-bubble');
    var quickInput = document.getElementById('wpiko-chatbot-proactive-input');
    var quickSend = document.getElementById('wpiko-chatbot-proactive-send');
    var scrollHandler = null;
    var showTimeout = null;

    function syncSettingsVersion() {
        if (!config || !config.settings_version) {
            return;
        }

        var storedVersion = localStorage.getItem(SETTINGS_VERSION_KEY);

        if (storedVersion !== config.settings_version) {
            localStorage.removeItem(DONE_ONCE_KEY);
            sessionStorage.removeItem(DONE_SESSION_KEY);
        }

        localStorage.setItem(SETTINGS_VERSION_KEY, config.settings_version);
    }

    function isChatOpen() {
        return floatingContainer.style.display !== 'none';
    }

    function hasExistingConversation() {
        return !!sessionStorage.getItem('wpiko_chatbot_thread_id');
    }

    function isDone() {
        if (config.frequency === 'once') {
            return !!(localStorage.getItem(DONE_ONCE_KEY) || sessionStorage.getItem(DONE_SESSION_KEY));
        }
        // 'session' and 'always' both respect the per-session marker
        // ('always' only sets it on explicit dismissal or engagement).
        return !!sessionStorage.getItem(DONE_SESSION_KEY);
    }

    function markShown() {
        if (config.frequency === 'once') {
            localStorage.setItem(DONE_ONCE_KEY, '1');
        } else if (config.frequency === 'session') {
            sessionStorage.setItem(DONE_SESSION_KEY, '1');
        }
        // 'always': no marker on show, so it reappears on the next page view.
    }

    function markEngaged() {
        // The visitor dismissed the greeting or opened the chat:
        // don't show it again this session, regardless of frequency.
        sessionStorage.setItem(DONE_SESSION_KEY, '1');
        if (config.frequency === 'once') {
            localStorage.setItem(DONE_ONCE_KEY, '1');
        }
    }

    function showGreeting() {
        // The visitor may have opened the chat while a delay/scroll trigger
        // was still waiting.
        if (isChatOpen()) {
            return;
        }
        greeting.style.display = 'block';
        // Force a reflow so the entrance transition plays
        greeting.offsetHeight;
        greeting.classList.add('wpiko-proactive-visible');
        // Hide the floating text pill while the greeting card is visible
        floatingWrapper.classList.add('wpiko-proactive-active');
        markShown();
    }

    function hideGreeting() {
        greeting.classList.remove('wpiko-proactive-visible');
        greeting.style.display = 'none';
        floatingWrapper.classList.remove('wpiko-proactive-active');
        if (showTimeout) {
            clearTimeout(showTimeout);
            showTimeout = null;
        }
        if (scrollHandler) {
            window.removeEventListener('scroll', scrollHandler);
            scrollHandler = null;
        }
    }

    function openChat() {
        hideGreeting();
        markEngaged();
        if (!isChatOpen()) {
            // Reuse the main plugin's open/close logic
            floatingWrapper.click();
        }
    }

    function emailCaptureBlocks() {
        return typeof wpikoChatbot !== 'undefined' &&
            wpikoChatbot.enable_email_capture === '1' &&
            !wpikoChatbot.is_user_logged_in &&
            !localStorage.getItem('wpiko_chatbot_user_email');
    }

    function sendQuickMessage() {
        var message = quickInput ? quickInput.value.trim() : '';

        if (!message) {
            openChat();
            return;
        }

        if (emailCaptureBlocks()) {
            // Store the message; it is sent automatically once the visitor
            // completes the email capture form (which reloads the page).
            sessionStorage.setItem(PENDING_KEY, message);
            openChat();
            prefillChatInput(message);
            return;
        }

        openChat();
        setTimeout(function () {
            submitToChat(message);
        }, 150);
    }

    function prefillChatInput(message) {
        var inputField = document.getElementById('chatbot-input');
        if (inputField) {
            inputField.value = message;
        }
    }

    function submitToChat(message) {
        var inputField = document.getElementById('chatbot-input');
        var sendButton = document.getElementById('chatbot-send');
        if (inputField && sendButton) {
            inputField.value = message;
            sendButton.click();
        }
    }

    function processPendingMessage() {
        var pending = sessionStorage.getItem(PENDING_KEY);
        if (!pending) {
            return;
        }

        if (typeof wpikoChatbot !== 'undefined' &&
            wpikoChatbot.enable_email_capture === '1' &&
            !wpikoChatbot.is_user_logged_in &&
            !localStorage.getItem('wpiko_chatbot_user_email')) {
            // Still waiting for the visitor's details: keep the message ready.
            var inputField = document.getElementById('chatbot-input');
            if (inputField && !inputField.value) {
                inputField.value = pending;
            }
            return;
        }

        sessionStorage.removeItem(PENDING_KEY);

        var container = document.getElementById('wpiko-chatbot-floating-container');
        var wrapper = document.getElementById('wpiko-chatbot-floating-wrapper');
        if (container && wrapper && container.style.display === 'none') {
            wrapper.click();
        }

        setTimeout(function () {
            var inputField = document.getElementById('chatbot-input');
            var sendButton = document.getElementById('chatbot-send');
            if (inputField && sendButton) {
                inputField.value = pending;
                sendButton.click();
            }
        }, 200);
    }

    // --- Wire up card interactions ---

    if (closeButton) {
        closeButton.addEventListener('click', function (e) {
            e.stopPropagation();
            hideGreeting();
            markEngaged();
        });
    }

    if (bubble) {
        bubble.addEventListener('click', function () {
            openChat();
        });
    }

    if (quickSend) {
        quickSend.addEventListener('click', function (e) {
            e.stopPropagation();
            sendQuickMessage();
        });
    }

    if (quickInput) {
        quickInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendQuickMessage();
            }
        });
    }

    // Hide the greeting whenever the visitor opens the chat via the icon
    floatingWrapper.addEventListener('click', function () {
        if (greeting.style.display !== 'none') {
            hideGreeting();
            markEngaged();
        }
    });

    // Dismiss with Escape while visible
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && greeting.style.display !== 'none') {
            hideGreeting();
            markEngaged();
        }
    });

    // --- Decide whether / when to show ---

    if (isChatOpen() || hasExistingConversation() || isDone()) {
        return;
    }

    if (config.trigger === 'scroll') {
        var depth = parseInt(config.scroll_depth, 10) || 30;
        scrollHandler = function () {
            var scrollable = document.documentElement.scrollHeight - window.innerHeight;
            var scrolled = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 100;
            if (scrolled >= depth) {
                window.removeEventListener('scroll', scrollHandler);
                scrollHandler = null;
                showGreeting();
            }
        };
        window.addEventListener('scroll', scrollHandler, { passive: true });
        // Pages too short to scroll would otherwise never trigger
        scrollHandler();
    } else if (config.trigger === 'immediate') {
        showTimeout = setTimeout(showGreeting, 400);
    } else {
        var delaySeconds = parseInt(config.delay, 10);
        if (isNaN(delaySeconds) || delaySeconds < 0) {
            delaySeconds = 5;
        }
        showTimeout = setTimeout(showGreeting, delaySeconds * 1000);
    }
});

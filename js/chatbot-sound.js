document.addEventListener('DOMContentLoaded', function () {
    /**
     * Playback volume for each sound (0 to 1).
     *
     * The sound files are loudness-matched, so these values only set how
     * prominent each sound is:
     * - Message: the main notification, clearly audible but gentle (0.6)
     * - Error: slightly quieter so it informs without startling (0.45)
     * - Clear chat: a short, subtle confirmation (0.5)
     */
    const VOLUME_LEVELS = {
        message: 0.6,
        error: 0.45,
        clearChat: 0.5
    };

    // Sounds are created on first use instead of on page load, so visitors who
    // never open the chat do not download the audio files.
    const sounds = {};

    function getSound(key, src, volume) {
        if (!sounds[key]) {
            sounds[key] = new Howl({
                src: [src],
                volume: volume,
                preload: true
            });
        }
        return sounds[key];
    }

    // Warm the cache once the visitor interacts with the chat, so the first
    // reply chime plays without a delay.
    let warmed = false;
    function warmSounds() {
        if (warmed) {
            return;
        }
        warmed = true;
        getSound('message', wpikoChatbotSound.messageReceivedSound, VOLUME_LEVELS.message);
    }
    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('#wpiko-chatbot-floating-icon, #wpiko-chatbot-container, #wpiko-chatbot-proactive-greeting')) {
            warmSounds();
        }
    }, { passive: true });

    // Event listeners for sound triggers
    document.addEventListener('wpiko-chatbot-message-received', function (event) {
        if (event.detail.isSoundEnabled) {
            getSound('message', wpikoChatbotSound.messageReceivedSound, VOLUME_LEVELS.message).play();
        }
    });

    document.addEventListener('wpiko-chatbot-error', function (event) {
        if (event.detail.isSoundEnabled) {
            getSound('error', wpikoChatbotSound.errorSound, VOLUME_LEVELS.error).play();
        }
    });

    document.addEventListener('wpiko-chatbot-clear', function (event) {
        if (event.detail.isSoundEnabled) {
            getSound('clearChat', wpikoChatbotSound.clearChatSound, VOLUME_LEVELS.clearChat).play();
        }
    });
});
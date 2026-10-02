document.addEventListener('DOMContentLoaded', function () {
    /**
     * Calibrated volume levels for consistent perceived loudness
     * 
     * These values are tuned to balance the different audio files:
     * - Message notification: Base reference volume (0.5)
     * - Error notification: Reduced to match message notification level (0.35)
     * - Clear chat notification: Reduced significantly as this file is inherently louder (0.25)
     * 
     * Adjust these values if sounds still seem inconsistent on different devices.
     */
    const VOLUME_LEVELS = {
        message: 0.8,      // Base reference - gentle notification chime
        error: 0.6,       // Error alert - calibrated to match message volume
        clearChat: 0.9    // Clear chat confirmation - calibrated to match message volume
    };

    // Initialize all sound instances with calibrated volumes
    const messageReceivedSound = new Howl({
        src: [wpikoChatbotSound.messageReceivedSound],
        volume: VOLUME_LEVELS.message,
        preload: true
    });

    const errorSound = new Howl({
        src: [wpikoChatbotSound.errorSound],
        volume: VOLUME_LEVELS.error,
        preload: true
    });

    const clearChatSound = new Howl({
        src: [wpikoChatbotSound.clearChatSound],
        volume: VOLUME_LEVELS.clearChat,
        preload: true
    });

    // Event listeners for sound triggers
    document.addEventListener('wpiko-chatbot-message-received', function (event) {
        if (event.detail.isSoundEnabled) {
            messageReceivedSound.play();
        }
    });

    document.addEventListener('wpiko-chatbot-error', function (event) {
        if (event.detail.isSoundEnabled) {
            errorSound.play();
        }
    });

    document.addEventListener('wpiko-chatbot-clear', function (event) {
        if (event.detail.isSoundEnabled) {
            clearChatSound.play();
        }
    });
});
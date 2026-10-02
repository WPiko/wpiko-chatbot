jQuery(document).ready(function($) {
    // Loading HTML to each modal container
    $('.wpiko-modal-content').each(function() {
        $(this).prepend(`
            <div class="wpiko-modal-loading">
                <div class="loading-spinner"></div>
                <p>Loading content...</p>
            </div>
        `);
    });

    // File Management Modal
    $(document).on('click', '.wpiko-file-management-button', function() {
        $('body').addClass('body-scroll-lock');
        $('#file-management-modal').fadeIn();
        $('#file-management-container').hide();
        $('#file-management-modal .wpiko-modal-loading').show();
        loadFileManagementContent();
    });

    // Responses Scan Website Modal
    $(document).on('click', '#responses-scan-website-button', function() {
        $('body').addClass('body-scroll-lock');
        $('#responses-scan-website-modal').fadeIn();
        $('#responses-scan-website-container').hide();
        $('#responses-scan-website-modal .wpiko-modal-loading').show();
        loadResponsesScanWebsiteContent();
    });

    // Responses QA Management Modal
    $(document).on('click', '#responses-qa-management-button', function() {
        $('body').addClass('body-scroll-lock');
        $('#responses-qa-management-modal').fadeIn();
        $('#responses-qa-management-container').hide();
        $('#responses-qa-management-modal .wpiko-modal-loading').show();
        loadResponsesQaManagementContent();
    });

    // Responses Woocommerce Integration Modal
    $(document).on('click', '#responses-woocommerce-integration-button', function() {
        $('body').addClass('body-scroll-lock');
        $('#responses-woocommerce-integration-modal').fadeIn();
        $('#responses-woocommerce-integration-container').hide();
        $('#responses-woocommerce-integration-modal .wpiko-modal-loading').show();
        loadResponsesWoocommerceIntegrationContent();
    });

    // Load File Management Content
    function loadFileManagementContent() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_load_file_management',
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#file-management-modal .wpiko-modal-loading').hide();
                    $('#file-management-container').html(response.data).fadeIn();
                    $(document).trigger('fileManagementLoaded');
                    if (typeof wpikoChatbotFileManagement !== 'undefined') {
                        wpikoChatbotFileManagement.refreshFileList();
                    }
                }
            },
            error: function() {
                $('#file-management-modal .wpiko-modal-loading').hide();
                $('#file-management-container').html('<p class="error-message">Error loading content. Please try again.</p>').fadeIn();
            }
        });
    }

    // Load Responses Scan Website Content
    function loadResponsesScanWebsiteContent() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_load_responses_scan_website',
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#responses-scan-website-modal .wpiko-modal-loading').hide();
                    $('#responses-scan-website-container').html(response.data).fadeIn();
                    $(document).trigger('scanWebsiteContentLoaded');
                    if (typeof wpikoChatbotFileManagement !== 'undefined') {
                        wpikoChatbotFileManagement.refreshUrlProcessingFileList();
                    }
                }
            },
            error: function() {
                $('#responses-scan-website-modal .wpiko-modal-loading').hide();
                $('#responses-scan-website-container').html('<p class="error-message">Error loading content. Please try again.</p>').fadeIn();
            }
        });
    }

    // Load Responses QA Management Content
    function loadResponsesQaManagementContent() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_load_responses_qa_management',
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#responses-qa-management-modal .wpiko-modal-loading').hide();
                    $('#responses-qa-management-container').html(response.data).fadeIn();
                    
                    // Trigger initialization after content is loaded
                    if (typeof window.wpikoChatbotQaManagement !== 'undefined') {
                        window.wpikoChatbotQaManagement.initializeQaManagement();
                    }
                    
                    // Initialize file list
                    if (typeof wpikoChatbotFileManagement !== 'undefined') {
                        wpikoChatbotFileManagement.refreshQAFileList();
                    }
                }
            },
            error: function() {
                $('#responses-qa-management-modal .wpiko-modal-loading').hide();
                $('#responses-qa-management-container').html('<p class="error-message">Error loading content. Please try again.</p>').fadeIn();
            }
        });
    }

    // Load Responses Woocommerce Integration Content
    function loadResponsesWoocommerceIntegrationContent() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_load_responses_woocommerce_integration',
                security: wpikoChatbotAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#responses-woocommerce-integration-modal .wpiko-modal-loading').hide();
                    $('#responses-woocommerce-integration-container').html(response.data).fadeIn();
                    $(document).trigger('woocommerceIntegrationLoaded');
                    if (typeof wpikoChatbotFileManagement !== 'undefined') {
                        wpikoChatbotFileManagement.refreshWooCommerceFileList();
                    }
                }
            },
            error: function() {
                $('#responses-woocommerce-integration-modal .wpiko-modal-loading').hide();
                $('#responses-woocommerce-integration-container').html('<p class="error-message">Error loading content. Please try again.</p>').fadeIn();
            }
        });
    }

    // Modal close handlers
    function removeBodyScrollLock() {
        // Only remove the class if no modals are visible
        if (!$('.wpiko-modal:visible').length) {
            $('body').removeClass('body-scroll-lock');
        }
    }

    $('.wpiko-modal-close').click(function() {
        $(this).closest('.wpiko-modal').fadeOut(400, removeBodyScrollLock);
    });

    $('.wpiko-modal').click(function(e) {
        if (e.target === this) {
            $(this).fadeOut(400, removeBodyScrollLock);
        }
    });

    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('.wpiko-modal').fadeOut(400, removeBodyScrollLock);
        }
    });
});

jQuery(document).ready(function($) {
    console.log('Admin script loaded');

    // Grouped sidebar navigation.
    var navGroupStorageKey = 'wpikoChatbotOpenNavGroups';
    var $navGroups = $('.wpiko-nav-group');
    var $toggleAllNavGroups = $('#wpiko-nav-toggle-all');
    var storedNavGroups = null;

    try {
        var storedValue = window.localStorage.getItem(navGroupStorageKey);
        if (storedValue !== null) {
            var parsedGroups = JSON.parse(storedValue);
            if (Array.isArray(parsedGroups)) {
                storedNavGroups = parsedGroups;
            }
        } else {
            // Carry over the open group saved by older plugin versions.
            var legacyGroup = window.localStorage.getItem('wpikoChatbotOpenNavGroup');
            if (legacyGroup) {
                storedNavGroups = [legacyGroup];
            }
        }
    } catch (error) {
        storedNavGroups = null;
    }

    function setNavGroupState($group, isOpen) {
        $group.toggleClass('is-open', isOpen);
        $group.children('.wpiko-nav-group-toggle').attr('aria-expanded', isOpen ? 'true' : 'false');
    }

    function updateToggleAllLabel() {
        var allOpen = $navGroups.length > 0 && $navGroups.filter('.is-open').length === $navGroups.length;
        $toggleAllNavGroups.text($toggleAllNavGroups.attr(allOpen ? 'data-collapse-label' : 'data-expand-label'));
    }

    function saveOpenNavGroups() {
        var openGroups = [];
        $navGroups.each(function() {
            if ($(this).hasClass('is-open')) {
                openGroups.push($(this).attr('data-nav-group'));
            }
        });

        try {
            window.localStorage.setItem(navGroupStorageKey, JSON.stringify(openGroups));
        } catch (error) {
            // Navigation remains functional when browser storage is unavailable.
        }
    }

    if (storedNavGroups !== null) {
        $navGroups.each(function() {
            var $group = $(this);
            var isStoredOpen = $.inArray($group.attr('data-nav-group'), storedNavGroups) !== -1;
            setNavGroupState($group, isStoredOpen || $group.hasClass('has-active-tab'));
        });
    }
    updateToggleAllLabel();

    $('.wpiko-nav-group-toggle').on('click', function() {
        var $group = $(this).closest('.wpiko-nav-group');
        setNavGroupState($group, !$group.hasClass('is-open'));
        saveOpenNavGroups();
        updateToggleAllLabel();
    });

    $toggleAllNavGroups.on('click', function() {
        var shouldExpand = $navGroups.filter('.is-open').length !== $navGroups.length;
        $navGroups.each(function() {
            var $group = $(this);
            setNavGroupState($group, shouldExpand || $group.hasClass('has-active-tab'));
        });
        saveOpenNavGroups();
        updateToggleAllLabel();
    });
    
    // Mobile Navigation Toggle
    $('#wpiko-mobile-nav-toggle').on('click', function(e) {
        e.preventDefault();
        toggleMobileNav();
    });
    
    // Handle keyboard navigation for mobile toggle
    $('#wpiko-mobile-nav-toggle').on('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleMobileNav();
        }
        if (e.key === 'Escape') {
            closeMobileNav();
        }
    });
    
    // Function to toggle mobile navigation
    function toggleMobileNav() {
        var $nav = $('.wpiko-chatbot-nav');
        var $button = $('#wpiko-mobile-nav-toggle');
        
        $nav.toggleClass('nav-open');
        $button.toggleClass('active');
        
        // Update button attributes for accessibility
        var isOpen = $nav.hasClass('nav-open');
        $button.attr('aria-expanded', isOpen ? 'true' : 'false');
        
        if (isOpen) {
            // Focus first nav item when opened
            setTimeout(function() {
                $('#wpiko-nav-menu a:first').focus();
            }, 100);
        }
    }
    
    // Function to close mobile navigation
    function closeMobileNav() {
        $('.wpiko-chatbot-nav').removeClass('nav-open');
        $('#wpiko-mobile-nav-toggle').removeClass('active').attr('aria-expanded', 'false');
    }
    
    // Close mobile nav when clicking on a nav item
    $('.wpiko-chatbot-nav a').on('click', function() {
        if ($(window).width() <= 782) {
            closeMobileNav();
        }
    });
    
    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() > 782) {
            closeMobileNav();
        }
    });
    
    // Handle escape key to close mobile nav
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('.wpiko-chatbot-nav').hasClass('nav-open')) {
            closeMobileNav();
            $('#wpiko-mobile-nav-toggle').focus();
        }
    });
    
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
                    alert('API key is valid and has been saved.');
                    location.reload();
                } else {
                    alert('Invalid API key. Please check and try again.');
                }
            },
            error: function() {
                alert('An error occurred while validating the API key.');
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
        $addQuestionButton.click(function() {
            $questionsContainer.append('<div class="question-row"><input type="text" name="questions[]" value="" class="regular-text"><button type="button" class="button remove-question">Remove</button></div>');
        });

        $(document).on('click', '.remove-question', function() {
            $(this).parent().remove();
        });
    }
});

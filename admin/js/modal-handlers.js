/**
 * Shared popup controller for the Train AI tools.
 *
 * Markup comes from wpiko_chatbot_render_modal() (admin/includes/modal-shell.php).
 * Public API (also used by Pro):
 *   wpikoModal.open(id, opener)        Show a modal.
 *   wpikoModal.close(id, {force})      Hide it (asks first when it has unsaved changes).
 *   wpikoModal.load(id, ajaxAction)    Load content into it via admin-ajax.
 *   wpikoModal.setDirty(target, bool)  Mark unsaved changes (target = id or element inside).
 *   wpikoModal.setGuard(target, opts)  Ask before closing while work is running
 *                                      (opts = confirm() options, or null to clear).
 *                                      Fires 'wpiko:modal-guard-confirmed' if the user closes anyway.
 *   wpikoModal.confirm(options)        Promise<boolean>; styled replacement for confirm().
 */
(function ($) {
    'use strict';

    var FOCUSABLE = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var stack = [];      // Open modal IDs, topmost last.
    var openers = {};    // Element to return focus to, per modal.
    var dirty = {};      // Unsaved-changes flag, per modal.
    var guards = {};     // Custom close confirmation while work runs, per modal.
    var requests = {};   // In-flight content requests, per modal.
    var confirmOpen = false;

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function getModal(target) {
        if (!target) {
            return $();
        }
        if (typeof target === 'string') {
            return $('#' + target.replace(/^#/, '')).filter('.wpiko-modal');
        }
        return $(target).closest('.wpiko-modal');
    }

    function visibleFocusables($scope) {
        return $scope.find(FOCUSABLE).filter(function () {
            return this.offsetWidth > 0 || this.offsetHeight > 0 || this.getClientRects().length > 0;
        });
    }

    function lockScroll() {
        var $body = $('body');
        if ($body.hasClass('wpiko-modal-open')) {
            return;
        }
        var scrollbar = window.innerWidth - document.documentElement.clientWidth;
        $body.addClass('wpiko-modal-open');
        if (scrollbar > 0) {
            $body.css('padding-right', scrollbar + 'px');
        }
    }

    function unlockScroll() {
        if (stack.length) {
            return;
        }
        $('body').removeClass('wpiko-modal-open').css('padding-right', '');
    }

    function updateFooter($modal) {
        var $footer = $modal.find('.wpiko-modal-footer');
        var hasActions = $footer.find('.wpiko-modal-footer-actions').children().length > 0;
        var isDirty = !!dirty[$modal.attr('id')];
        $footer.find('.wpiko-modal-dirty').prop('hidden', !isDirty);
        $footer.prop('hidden', !hasActions && !isDirty);
    }

    function setDirty(target, value) {
        var $modal = getModal(target);
        if (!$modal.length) {
            return;
        }
        dirty[$modal.attr('id')] = !!value;
        $modal.toggleClass('is-dirty', !!value);
        updateFooter($modal);
        $modal.trigger('wpiko:modal-dirty', [!!value]);
    }

    function setGuard(target, options) {
        var $modal = getModal(target);
        if (!$modal.length) {
            return;
        }
        if (options) {
            guards[$modal.attr('id')] = options;
        } else {
            delete guards[$modal.attr('id')];
        }
    }

    function isDirty(target) {
        var $modal = getModal(target);
        return !!dirty[$modal.attr('id')];
    }

    function open(id, opener) {
        var $modal = getModal(id);
        if (!$modal.length) {
            return $modal;
        }
        var modalId = $modal.attr('id');

        if (stack.indexOf(modalId) === -1) {
            openers[modalId] = opener || document.activeElement;
            stack.push(modalId);
        }

        lockScroll();
        $modal.prop('hidden', false).addClass('is-visible');
        // Force a reflow so the opening transition runs.
        void $modal[0].offsetWidth;
        $modal.addClass('is-open');
        $modal.find('.wpiko-modal-content').trigger('focus');
        $modal.trigger('wpiko:modal-open');
        return $modal;
    }

    function finishClose($modal) {
        var modalId = $modal.attr('id');
        $modal.prop('hidden', true).removeClass('is-visible');
        // Content is reloaded on every open; drop the old copy so IDs stay unique.
        if (requests[modalId]) {
            requests[modalId].abort();
        }
        $modal.find('.wpiko-modal-body-content').prop('hidden', true).empty();
        $modal.find('.wpiko-modal-footer-actions').empty();
        updateFooter($modal);
        stack = stack.filter(function (item) { return item !== modalId; });
        unlockScroll();

        var opener = openers[modalId];
        delete openers[modalId];
        if (opener && document.contains(opener) && typeof opener.focus === 'function') {
            opener.focus();
        }
        $modal.trigger('wpiko:modal-close');
    }

    function close(id, options) {
        var $modal = getModal(id);
        options = options || {};
        if (!$modal.length || $modal.prop('hidden')) {
            return $.Deferred().resolve(false).promise();
        }
        var modalId = $modal.attr('id');

        var guard = guards[modalId] || (dirty[modalId] ? {
            title: 'Discard unsaved changes?',
            message: 'You have changes in this window that are not saved yet. If you close it now, they will be lost.',
            confirmLabel: 'Discard changes',
            cancelLabel: 'Keep editing',
            danger: true
        } : null);

        if (guard && !options.force) {
            return confirmDialog(guard).then(function (ok) {
                if (ok) {
                    if (guards[modalId]) {
                        $modal.trigger('wpiko:modal-guard-confirmed');
                    }
                    return close(modalId, { force: true });
                }
                return false;
            });
        }

        delete guards[modalId];
        setDirty($modal, false);
        $modal.removeClass('is-open');
        if (prefersReducedMotion()) {
            finishClose($modal);
        } else {
            setTimeout(function () { finishClose($modal); }, 180);
        }
        return $.Deferred().resolve(true).promise();
    }

    function moveFooterSlot($modal, $content) {
        var $actions = $modal.find('.wpiko-modal-footer-actions').empty();
        $content.find('.wpiko-modal-footer-slot').each(function () {
            $actions.append($(this).children());
            $(this).remove();
        });
        updateFooter($modal);
    }

    /**
     * Load a template into a modal. Returns a promise resolved after content
     * is in place (and footer actions have been moved), before onLoad runs.
     */
    function load(id, action, onLoad) {
        var $modal = getModal(id);
        var modalId = $modal.attr('id');
        var $loading = $modal.find('.wpiko-modal-loading');
        var $content = $modal.find('.wpiko-modal-body-content');
        var deferred = $.Deferred();

        if (requests[modalId]) {
            requests[modalId].abort();
        }

        setDirty($modal, false);
        $modal.find('.wpiko-modal-footer-actions').empty();
        updateFooter($modal);
        $content.prop('hidden', true).empty();
        $loading.prop('hidden', false);
        $modal.attr('aria-busy', 'true');

        requests[modalId] = $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            data: {
                action: action,
                security: window.wpikoChatbotAdmin ? window.wpikoChatbotAdmin.nonce : ''
            }
        }).done(function (response) {
            if (response && response.success) {
                $content.html(response.data);
                moveFooterSlot($modal, $content);
            } else {
                var message = response && response.data && response.data.message ? response.data.message : 'This window could not be loaded.';
                $content.empty().append($('<p class="error-message"></p>').text(message));
            }
            $loading.prop('hidden', true);
            $content.prop('hidden', false);
            $modal.removeAttr('aria-busy');
            if (response && response.success && typeof onLoad === 'function') {
                onLoad($modal, $content);
            }
            deferred.resolve($modal, $content);
        }).fail(function (xhr, status) {
            if (status === 'abort') {
                return;
            }
            $loading.prop('hidden', true);
            $content.empty()
                .append($('<p class="error-message"></p>').text('This window could not be loaded. Close it and try again.'))
                .prop('hidden', false);
            $modal.removeAttr('aria-busy');
            deferred.reject();
        }).always(function () {
            delete requests[modalId];
        });

        return deferred.promise();
    }

    /* ------------------------------------------------------------------
     * Confirm dialog
     * ------------------------------------------------------------------ */
    function confirmDialog(options) {
        options = $.extend({
            title: 'Are you sure?',
            message: '',
            confirmLabel: 'Confirm',
            cancelLabel: 'Cancel',
            danger: false
        }, options || {});

        var deferred = $.Deferred();
        var previousFocus = document.activeElement;
        var uid = 'wpiko-confirm-' + Date.now();

        var $dialog = $(
            '<div class="wpiko-confirm wpiko-chatbot-settings-page">' +
                '<div class="wpiko-confirm-backdrop"></div>' +
                '<div class="wpiko-confirm-panel" role="alertdialog" aria-modal="true" tabindex="-1">' +
                    '<h2 class="wpiko-confirm-title"></h2>' +
                    '<p class="wpiko-confirm-message"></p>' +
                    '<div class="wpiko-confirm-actions">' +
                        '<button type="button" class="button wpiko-confirm-cancel"></button>' +
                        '<button type="button" class="button wpiko-confirm-ok"></button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        var $panel = $dialog.find('.wpiko-confirm-panel')
            .attr('aria-labelledby', uid + '-title')
            .attr('aria-describedby', uid + '-message');
        $dialog.find('.wpiko-confirm-title').attr('id', uid + '-title').text(options.title);
        $dialog.find('.wpiko-confirm-message').attr('id', uid + '-message').text(options.message).prop('hidden', !options.message);
        $dialog.find('.wpiko-confirm-cancel').text(options.cancelLabel);
        $dialog.find('.wpiko-confirm-ok')
            .text(options.confirmLabel)
            .addClass(options.danger ? 'wpiko-confirm-danger' : 'wpiko-chatbot-settings-save');

        function finish(result) {
            confirmOpen = false;
            $(document).off('keydown.wpikoConfirm');
            $dialog.remove();
            if (!stack.length) {
                unlockScroll();
            }
            if (previousFocus && document.contains(previousFocus) && typeof previousFocus.focus === 'function') {
                previousFocus.focus();
            }
            deferred.resolve(result);
        }

        $dialog.on('click', '.wpiko-confirm-cancel, .wpiko-confirm-backdrop', function () { finish(false); });
        $dialog.on('click', '.wpiko-confirm-ok', function () { finish(true); });

        $(document).on('keydown.wpikoConfirm', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopImmediatePropagation();
                finish(false);
            } else if (event.key === 'Tab') {
                var $items = visibleFocusables($panel);
                if (!$items.length) {
                    return;
                }
                var first = $items[0];
                var last = $items[$items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        confirmOpen = true;
        lockScroll();
        $('body').append($dialog);
        // Destructive actions start on Cancel; others on the confirm button.
        (options.danger ? $dialog.find('.wpiko-confirm-cancel') : $dialog.find('.wpiko-confirm-ok')).trigger('focus');

        return deferred.promise();
    }

    /* ------------------------------------------------------------------
     * Global behaviour
     * ------------------------------------------------------------------ */

    // Close buttons and backdrop.
    $(document).on('click', '.wpiko-modal [data-wpiko-modal-dismiss]', function (event) {
        event.preventDefault();
        close($(this).closest('.wpiko-modal'));
    });

    // Escape closes the topmost modal; components can claim Escape with preventDefault().
    $(document).on('keydown', function (event) {
        if (!stack.length || confirmOpen) {
            return;
        }
        var $top = getModal(stack[stack.length - 1]);

        if (event.key === 'Escape' && !event.isDefaultPrevented()) {
            event.preventDefault();
            close($top);
            return;
        }

        if (event.key === 'Tab') {
            var $panel = $top.find('.wpiko-modal-content');
            var $items = visibleFocusables($panel);
            if (!$items.length) {
                event.preventDefault();
                $panel.trigger('focus');
                return;
            }
            var first = $items[0];
            var last = $items[$items.length - 1];
            var active = document.activeElement;
            if (!$panel[0].contains(active)) {
                event.preventDefault();
                first.focus();
            } else if (event.shiftKey && (active === first || active === $panel[0])) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    // The modals sit inside the settings form: Enter in a field must not submit it.
    $(document).on('keydown', '.wpiko-modal input', function (event) {
        if (event.key === 'Enter' && !/^(submit|button|checkbox|radio|file)$/i.test(this.type)) {
            event.preventDefault();
        }
    });

    // Warn before leaving the page with unsaved changes in an open modal.
    window.addEventListener('beforeunload', function (event) {
        var hasDirty = stack.some(function (id) { return dirty[id] || guards[id]; });
        if (hasDirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    window.wpikoModal = {
        open: open,
        close: close,
        load: load,
        setDirty: setDirty,
        setGuard: setGuard,
        isDirty: isDirty,
        confirm: confirmDialog
    };
    window.wpikoConfirm = confirmDialog;

    /* ------------------------------------------------------------------
     * Train AI popups
     * ------------------------------------------------------------------ */
    function fileManagement(method) {
        if (window.wpikoChatbotFileManagement && typeof window.wpikoChatbotFileManagement[method] === 'function') {
            window.wpikoChatbotFileManagement[method]();
        }
    }

    var trainingModals = [
        {
            trigger: '.wpiko-file-management-button',
            modal: 'file-management-modal',
            title: 'File Management',
            icon: 'dashicons-upload',
            container: 'file-management-container',
            action: 'wpiko_chatbot_load_file_management',
            onLoad: function () {
                $(document).trigger('fileManagementLoaded');
            }
        },
        {
            trigger: '#responses-scan-website-button',
            modal: 'responses-scan-website-modal',
            title: 'Scan Website',
            icon: 'dashicons-admin-site-alt3',
            container: 'responses-scan-website-container',
            action: 'wpiko_chatbot_load_responses_scan_website',
            onLoad: function () {
                $(document).trigger('scanWebsiteContentLoaded');
                fileManagement('refreshUrlProcessingFileList');
            }
        },
        {
            trigger: '#responses-qa-management-button',
            modal: 'responses-qa-management-modal',
            title: 'Q&A Builder',
            icon: 'dashicons-insert',
            container: 'responses-qa-management-container',
            action: 'wpiko_chatbot_load_responses_qa_management',
            onLoad: function () {
                if (window.wpikoChatbotQaManagement) {
                    window.wpikoChatbotQaManagement.initializeQaManagement();
                }
            }
        },
        {
            trigger: '#responses-woocommerce-integration-button',
            modal: 'responses-woocommerce-integration-modal',
            title: 'WooCommerce Integration',
            icon: 'dashicons-cart',
            container: 'responses-woocommerce-integration-container',
            action: 'wpiko_chatbot_load_responses_woocommerce_integration',
            onLoad: function () {
                $(document).trigger('woocommerceIntegrationLoaded');
            }
        }
    ];

    /**
     * WPiko AI Chatbot Pro 2.1.1 and older print the previous popup markup
     * (a close <span> and a bare content container). Rebuild it into the shared
     * shell so the popup still works until Pro is updated.
     */
    function upgradeLegacyModal($modal, config) {
        if ($modal.find('.wpiko-modal-body-content').length) {
            return;
        }
        var $content = $modal.children('.wpiko-modal-content');
        var $container = $modal.find('#' + config.container);
        if (!$content.length || !$container.length) {
            return;
        }
        var titleId = $modal.attr('id') + '-title';

        $modal.addClass('wpiko-chatbot-settings-page wpiko-modal--legacy');
        $modal.prepend('<div class="wpiko-modal-backdrop" data-wpiko-modal-dismiss></div>');
        $content.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId, tabindex: '-1' });

        var $header = $('<header class="wpiko-modal-header"></header>')
            .append($('<span class="wpiko-modal-icon" aria-hidden="true"></span>').append($('<span class="dashicons"></span>').addClass(config.icon)))
            .append($('<div class="wpiko-modal-heading"></div>').append($('<h2 class="wpiko-modal-title"></h2>').attr('id', titleId).text(config.title)))
            .append(
                '<button type="button" class="wpiko-modal-close" data-wpiko-modal-dismiss aria-label="Close">' +
                    '<svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false"><path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4z" fill="currentColor"/></svg>' +
                '</button>'
            );
        var $body = $('<div class="wpiko-modal-body"></div>')
            .append(
                '<div class="wpiko-modal-loading" role="status" aria-live="polite">' +
                    '<span class="screen-reader-text">Loading…</span>' +
                    '<span class="wpiko-skeleton wpiko-skeleton--title" aria-hidden="true"></span>' +
                    '<span class="wpiko-skeleton wpiko-skeleton--line" aria-hidden="true"></span>' +
                    '<span class="wpiko-skeleton wpiko-skeleton--line wpiko-skeleton--short" aria-hidden="true"></span>' +
                    '<span class="wpiko-skeleton wpiko-skeleton--block" aria-hidden="true"></span>' +
                '</div>'
            )
            .append($container.detach().addClass('wpiko-modal-body-content').prop('hidden', true));
        var $footer = $(
            '<footer class="wpiko-modal-footer" hidden>' +
                '<span class="wpiko-modal-dirty" hidden>Unsaved changes</span>' +
                '<div class="wpiko-modal-footer-actions"></div>' +
            '</footer>'
        );
        $content.empty().append($header, $body, $footer);
    }

    $.each(trainingModals, function (index, config) {
        $(document).on('click', config.trigger, function (event) {
            event.preventDefault();
            var $modal = getModal(config.modal);
            if (!$modal.length) {
                return;
            }
            upgradeLegacyModal($modal, config);
            open(config.modal, this);
            load(config.modal, config.action, config.onLoad);
        });
    });
})(jQuery);

/**
 * In-page success, error, warning and info messages for wpiko Chatbot screens.
 *
 * Replaces browser alert() popups with the same tinted notice panel wpiko SEO
 * uses (styles in admin/css/notices.css). Also moves the notices WordPress
 * places after the first heading so they sit below the page header.
 *
 * Usage:
 *   wpikoChatbotNotices.show('Settings saved.', 'success');
 *   wpikoChatbotNotices.show('Could not delete the file.', 'error', { before: $list });
 *
 * Options:
 *   before | after | prepend : element or selector to place the notice against.
 *                              Without one, the notice goes below the page header.
 *   dismissible             : show a close button (default true).
 *   scroll                  : scroll the notice into view if it is off screen (default true).
 */
(function (window, document) {
    'use strict';

    var TYPES = ['success', 'error', 'warning', 'info'];
    var HEADER_LIKE = 'p, .description, .wpiko-chatbot-settings-subtitle';
    var NOTICE_SELECTOR = 'div.notice, div.updated, div.error';

    function toElement(target) {
        if (!target) {
            return null;
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        if (target.jquery) {
            return target[0] || null;
        }
        return target.nodeType === 1 ? target : null;
    }

    function isNotice(el) {
        return el.matches(NOTICE_SELECTOR) || el.classList.contains('wpiko-notice-js');
    }

    /**
     * Where page-level messages go: directly after the screen's header block
     * (title and description), inside the screen's own container.
     *
     * @return {{parent: Element, after: (Element|null)}|null}
     */
    function pageSlot() {
        var content = document.querySelector('.wpiko-chatbot-content');
        if (!content) {
            return null;
        }

        var root = null;
        Array.prototype.some.call(content.children, function (child) {
            if (child.matches('.wpiko-chatbot-subnav, hr, script, style, template') || isNotice(child)) {
                return false;
            }
            root = child;
            return true;
        });
        if (!root) {
            return { parent: content, after: content.lastElementChild };
        }

        var heading = root.querySelector('h1, h2');
        if (!heading) {
            return { parent: root, after: null };
        }

        var block = heading;
        while (block.parentElement && block.parentElement !== root) {
            block = block.parentElement;
        }
        if (block.parentElement !== root) {
            return { parent: root, after: null };
        }

        if (block === heading) {
            // No header wrapper: keep the title and its description together.
            while (block.nextElementSibling && block.nextElementSibling.matches(HEADER_LIKE) && !isNotice(block.nextElementSibling)) {
                block = block.nextElementSibling;
            }
        }

        // Skip notices already placed there so new ones follow them.
        while (block.nextElementSibling && isNotice(block.nextElementSibling)) {
            block = block.nextElementSibling;
        }

        return { parent: root, after: block };
    }

    function insertInSlot(notice, slot) {
        if (slot.after) {
            slot.parent.insertBefore(notice, slot.after.nextSibling);
        } else {
            slot.parent.insertBefore(notice, slot.parent.firstChild);
        }
    }

    function dismiss(notice) {
        if (!notice || !notice.parentNode) {
            return;
        }
        notice.style.transition = 'opacity 0.15s ease';
        notice.style.opacity = '0';
        window.setTimeout(function () {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 150);
    }

    function addDismissButton(notice) {
        if (notice.querySelector(':scope > .notice-dismiss')) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'notice-dismiss';
        var label = document.createElement('span');
        label.className = 'screen-reader-text';
        label.textContent = 'Dismiss this notice.';
        button.appendChild(label);
        button.addEventListener('click', function () {
            dismiss(notice);
        });
        notice.classList.add('is-dismissible');
        notice.appendChild(button);
    }

    function isInView(el) {
        var rect = el.getBoundingClientRect();
        return rect.top >= 40 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight);
    }

    function scrollIntoView(el) {
        if (isInView(el)) {
            return;
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    }

    /**
     * Remove earlier script-added notices at the same spot, so repeated
     * actions replace the message instead of stacking them.
     */
    function clearAt(parent, reference, position) {
        var candidates = [];
        if (position === 'before' && reference) {
            var prev = reference.previousElementSibling;
            while (prev && prev.classList.contains('wpiko-notice-js')) {
                candidates.push(prev);
                prev = prev.previousElementSibling;
            }
        } else if (position === 'after' && reference) {
            var next = reference.nextElementSibling;
            while (next && next.classList.contains('wpiko-notice-js')) {
                candidates.push(next);
                next = next.nextElementSibling;
            }
        } else if (parent) {
            Array.prototype.forEach.call(parent.children, function (child) {
                if (child.classList.contains('wpiko-notice-js')) {
                    candidates.push(child);
                }
            });
        }
        candidates.forEach(function (el) {
            el.parentNode.removeChild(el);
        });
    }

    /**
     * Show a message.
     *
     * @param {string} message Plain text (never parsed as HTML).
     * @param {string} [type]  success | error | warning | info. Default success.
     * @param {Object} [options]
     * @return {Element|null} The notice element.
     */
    function show(message, type, options) {
        options = options || {};
        type = TYPES.indexOf(type) === -1 ? 'success' : type;

        var notice = document.createElement('div');
        notice.className = 'notice notice-' + type + ' inline wpiko-notice-js';
        notice.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var paragraph = document.createElement('p');
        paragraph.textContent = String(message == null ? '' : message);
        notice.appendChild(paragraph);

        if (options.dismissible !== false) {
            addDismissButton(notice);
        }

        var before = toElement(options.before);
        var after = toElement(options.after);
        var prepend = toElement(options.prepend);

        if (before && before.parentNode) {
            clearAt(before.parentNode, before, 'before');
            before.parentNode.insertBefore(notice, before);
        } else if (after && after.parentNode) {
            clearAt(after.parentNode, after, 'after');
            after.parentNode.insertBefore(notice, after.nextSibling);
        } else if (prepend) {
            clearAt(prepend, null, 'prepend');
            prepend.insertBefore(notice, prepend.firstChild);
        } else {
            var slot = pageSlot();
            if (!slot) {
                // Not on a chatbot screen: fall back to a browser message.
                window.alert(paragraph.textContent);
                return null;
            }
            clearAt(slot.parent, null, 'slot');
            slot = pageSlot();
            insertInSlot(notice, slot);
        }

        if (options.scroll !== false) {
            scrollIntoView(notice);
        }

        return notice;
    }

    /**
     * Move notices WordPress placed right after the first heading (between
     * the title and its description) to below the page header.
     */
    function placeServerNotices() {
        var content = document.querySelector('.wpiko-chatbot-content');
        if (!content) {
            return;
        }
        var heading = content.querySelector('h1, h2');
        if (!heading) {
            return;
        }

        var moved = [];
        var next = heading.nextElementSibling;
        while (next && next.matches(NOTICE_SELECTOR) && !next.classList.contains('inline')) {
            moved.push(next);
            next = next.nextElementSibling;
        }
        if (!moved.length) {
            return;
        }

        moved.forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        var slot = pageSlot();
        if (!slot) {
            return;
        }
        moved.forEach(function (el) {
            insertInSlot(el, slot);
            slot = { parent: slot.parent, after: el };
        });
    }

    window.wpikoChatbotNotices = {
        show: show,
        success: function (message, options) { return show(message, 'success', options); },
        error: function (message, options) { return show(message, 'error', options); },
        warning: function (message, options) { return show(message, 'warning', options); },
        info: function (message, options) { return show(message, 'info', options); },
        dismiss: dismiss
    };

    function onReady() {
        // Run after WordPress (common.js) has moved its notices.
        window.setTimeout(placeServerNotices, 0);
    }

    if (window.jQuery) {
        window.jQuery(onReady);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})(window, document);

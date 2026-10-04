/* global DOMPurify */
(function () {
    'use strict';
    let purifier;

    // Mirrors the server message allowlist. Re-sanitize cached replies as well as
    // new ones: browser storage can contain HTML saved by older plugin versions.
    window.wpikoChatbotSanitizeMessage = function (value) {
        const html = String(value || '');
        if (typeof DOMPurify === 'undefined' || !DOMPurify.isSupported) {
            // Fail closed if the dependency is unavailable.
            return html.replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        if (!purifier) {
            purifier = DOMPurify();
            purifier.addHook('uponSanitizeAttribute', function (node, data) {
                if (data.attrName !== 'href' && data.attrName !== 'src') return;
                try {
                    const protocol = new URL(data.attrValue, document.baseURI).protocol;
                    if (!['http:', 'https:', 'mailto:', 'tel:'].includes(protocol)) data.keepAttr = false;
                } catch (error) {
                    data.keepAttr = false;
                }
            });
            purifier.addHook('afterSanitizeAttributes', function (node) {
                if (node.tagName === 'A' && node.getAttribute('target') === '_blank') {
                    node.setAttribute('rel', 'noopener noreferrer');
                }
            });
        }
        return purifier.sanitize(html, {
            ALLOWED_TAGS: ['a', 'b', 'bdi', 'blockquote', 'br', 'code', 'del', 'div', 'em',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'ol',
                'p', 'pre', 's', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td',
                'th', 'thead', 'tr', 'u', 'ul'],
            ALLOWED_ATTR: ['href', 'title', 'target', 'rel', 'class', 'data-wpiko-prefill',
                'src', 'alt', 'width', 'height', 'loading'],
            ALLOW_DATA_ATTR: false,
            ALLOW_ARIA_ATTR: false,
            // Relative URLs and the same explicit protocols as PHP; never data:.
            ALLOWED_URI_REGEXP: /^(?:(?:https?|mailto|tel):|[^a-z]|[a-z+.-]+(?:[^a-z+.:\-]|$))/i
        });
    };
}());

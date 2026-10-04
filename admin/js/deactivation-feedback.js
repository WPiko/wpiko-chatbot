/**
 * Optional feedback form shown when WPiko Chatbot is deactivated.
 */
(function () {
    'use strict';

    if (typeof wpikoDeactivation === 'undefined') {
        return;
    }

    var cfg = wpikoDeactivation;
    var i18n = cfg.i18n;

    function findDeactivateLink() {
        var row = document.querySelector('tr[data-plugin="' + cfg.pluginBasename + '"]');
        return row ? row.querySelector('.deactivate a') : null;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildModal() {
        var reasonsHtml = cfg.reasons.map(function (reason) {
            return '<li>' +
                '<label><input type="radio" name="wpiko-deactivation-reason" value="' + escapeHtml(reason.key) + '"> ' + escapeHtml(reason.label) + '</label>' +
                (reason.prompt ? '<textarea class="wpiko-deactivation-details" data-for="' + escapeHtml(reason.key) + '" rows="2" placeholder="' + escapeHtml(reason.prompt) + '" hidden></textarea>' : '') +
                (reason.key === 'setup_failed' ? '<p class="wpiko-deactivation-help" hidden>' + escapeHtml(i18n.setupHelp) + ' <a href="' + escapeHtml(cfg.supportUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(i18n.setupHelpLink) + '</a></p>' : '') +
                '</li>';
        }).join('');

        var overlay = document.createElement('div');
        overlay.className = 'wpiko-deactivation-overlay';
        overlay.innerHTML =
            '<div class="wpiko-deactivation-modal" role="dialog" aria-modal="true" aria-labelledby="wpiko-deactivation-title">' +
            '<button type="button" class="wpiko-deactivation-close" aria-label="' + escapeHtml(i18n.close) + '">&times;</button>' +
            '<h2 id="wpiko-deactivation-title">' + escapeHtml(i18n.title) + '</h2>' +
            '<p>' + escapeHtml(i18n.intro) + '</p>' +
            '<ul class="wpiko-deactivation-reasons">' + reasonsHtml + '</ul>' +
            '<label class="wpiko-deactivation-contact"><input type="checkbox" value="1"> ' + escapeHtml(i18n.contact) + '</label>' +
            '<div class="wpiko-deactivation-email" hidden>' +
            '<label for="wpiko-deactivation-email-input">' + escapeHtml(i18n.emailLabel) + '</label>' +
            '<input type="email" id="wpiko-deactivation-email-input" class="regular-text" value="' + escapeHtml(cfg.contactEmail) + '" autocomplete="email">' +
            '<p>' + escapeHtml(i18n.emailNote.replace('%s', cfg.siteHost)) + '</p>' +
            '</div>' +
            '<p class="wpiko-deactivation-privacy">' + escapeHtml(i18n.privacy) + '</p>' +
            '<div class="wpiko-deactivation-actions">' +
            '<button type="button" class="button-link wpiko-deactivation-skip">' + escapeHtml(i18n.skip) + '</button>' +
            '<span>' +
            '<button type="button" class="button wpiko-deactivation-cancel">' + escapeHtml(i18n.cancel) + '</button> ' +
            '<button type="button" class="button button-primary wpiko-deactivation-submit" disabled>' + escapeHtml(i18n.submit) + '</button>' +
            '</span>' +
            '</div>' +
            '</div>';
        return overlay;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var link = findDeactivateLink();
        if (!link) {
            return;
        }

        var overlay = null;
        var deactivateUrl = link.getAttribute('href');

        function close() {
            if (overlay) {
                overlay.remove();
                overlay = null;
            }
            link.focus();
        }

        function goDeactivate() {
            window.location.href = deactivateUrl;
        }

        function open() {
            overlay = buildModal();
            document.body.appendChild(overlay);

            var submit = overlay.querySelector('.wpiko-deactivation-submit');

            overlay.addEventListener('change', function (event) {
                if (event.target.closest('.wpiko-deactivation-contact')) {
                    var emailBox = overlay.querySelector('.wpiko-deactivation-email');
                    emailBox.hidden = !event.target.checked;
                    if (event.target.checked) {
                        emailBox.querySelector('input').focus();
                    }
                    return;
                }
                if (event.target.name !== 'wpiko-deactivation-reason') {
                    return;
                }
                var chosen = event.target.value;
                submit.disabled = false;
                overlay.querySelectorAll('.wpiko-deactivation-details').forEach(function (field) {
                    field.hidden = field.getAttribute('data-for') !== chosen;
                });
                overlay.querySelectorAll('.wpiko-deactivation-help').forEach(function (help) {
                    help.hidden = chosen !== 'setup_failed';
                });
                var details = overlay.querySelector('.wpiko-deactivation-details[data-for="' + chosen + '"]');
                if (details) {
                    details.focus();
                }
            });

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay || event.target.closest('.wpiko-deactivation-close, .wpiko-deactivation-cancel')) {
                    close();
                }
            });

            overlay.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    close();
                }
            });

            overlay.querySelector('.wpiko-deactivation-skip').addEventListener('click', goDeactivate);

            submit.addEventListener('click', function () {
                var chosen = overlay.querySelector('input[name="wpiko-deactivation-reason"]:checked');
                if (!chosen) {
                    return;
                }
                submit.disabled = true;
                var details = overlay.querySelector('.wpiko-deactivation-details[data-for="' + chosen.value + '"]');
                var body = new URLSearchParams();
                body.append('action', 'wpiko_chatbot_deactivation_feedback');
                body.append('security', cfg.nonce);
                body.append('reason', chosen.value);
                body.append('details', details ? details.value : '');
                body.append('contact', overlay.querySelector('.wpiko-deactivation-contact input').checked ? '1' : '0');
                body.append('contact_email', overlay.querySelector('#wpiko-deactivation-email-input').value);

                // Never hold up deactivation for more than a few seconds.
                var fallback = setTimeout(goDeactivate, 6000);
                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .catch(function () {})
                    .then(function () {
                        clearTimeout(fallback);
                        goDeactivate();
                    });
            });

            var first = overlay.querySelector('input[type="radio"]');
            if (first) {
                first.focus();
            }
        }

        link.addEventListener('click', function (event) {
            event.preventDefault();
            open();
        });
    });
})();

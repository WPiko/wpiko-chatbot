/**
 * Chatbot Style tab: appearance presets, hex inputs and live preview.
 *
 * Presets only fill in the form fields — nothing is stored until the form is
 * submitted, so a preset can be previewed and then abandoned by leaving the page.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('chatbot-style-form');
        var previewWindow = document.getElementById('wpiko-style-preview');

        if (!form) {
            return;
        }

        // Custom properties live on the wrapper so the floating-button strip
        // outside the chat window picks them up too.
        var preview = previewWindow ? previewWindow.closest('.wpiko-style-preview-wrap') || previewWindow : null;

        var data = window.wpikoChatbotStyle || {};
        var presets = data.presets || {};
        var presetField = document.getElementById('style_preset');
        var borderToggle = document.getElementById('show_user_border');
        var dirtyBadge = form.querySelector('.wpiko-style-dirty');
        var customNote = document.querySelector('.wpiko-preset-custom-note');
        var presetCards = Array.prototype.slice.call(document.querySelectorAll('.wpiko-preset-card'));
        var colorInputs = Array.prototype.slice.call(form.querySelectorAll('.wpiko-color-input'));

        // CSS custom property each color field feeds in the preview.
        var previewVars = {
            primary_color: '--p-primary',
            primary_text_color: '--p-primary-text',
            chatbot_background_color: '--p-bg',
            chatbot_header_color: '--p-header',
            chatbot_border_color: '--p-border',
            chatbot_name_color: '--p-name',
            user_background_color: '--p-user-bg',
            user_text_color: '--p-user-text',
            bot_background_color: '--p-bot-bg',
            bot_text_color: '--p-bot-text',
            admin_message_label_color: '--p-admin-label',
            admin_message_background_color: '--p-admin-bg',
            admin_message_border_color: '--p-admin-border',
            admin_message_text_color: '--p-admin-text',
            icon_color: '--p-icon',
            input_background_color: '--p-input-bg',
            floating_text_bg_color: '--p-floating-bg'
        };

        /* ------------------------------------------------------------------
           Live preview
           ------------------------------------------------------------------ */

        function syncPreviewColors() {
            if (!preview) {
                return;
            }

            colorInputs.forEach(function (input) {
                var cssVar = previewVars[input.id];
                if (cssVar) {
                    preview.style.setProperty(cssVar, input.value);
                }
            });

            preview.style.setProperty('--p-user-border', borderToggle && borderToggle.checked ? 'solid' : 'none');
        }

        function setPreviewText(elementId, value, fallback) {
            var el = document.getElementById(elementId);
            if (el) {
                el.textContent = value !== '' ? value : (fallback || '');
            }
        }

        function syncPreviewContent() {
            var name = document.getElementById('chatbot_name');
            var subtitle = document.getElementById('subtitle_text');
            var placeholder = document.getElementById('input_placeholder');
            var welcome = document.getElementById('welcome_message');
            var welcomeEl = document.getElementById('wpiko-preview-welcome');
            var welcomeRow = document.getElementById('wpiko-preview-welcome-row');

            if (name) {
                setPreviewText('wpiko-preview-name', name.value.trim(), 'My Chatbot');
            }
            if (subtitle) {
                setPreviewText('wpiko-preview-subtitle', subtitle.value.trim());
            }
            if (placeholder) {
                setPreviewText('wpiko-preview-placeholder', placeholder.value.trim(), 'Type your message...');
            }
            // An empty welcome message hides the whole message row, matching the
            // front end where the bubble is not rendered at all.
            if (welcome && welcomeEl && welcomeRow) {
                var text = welcome.value.trim();
                welcomeEl.textContent = text;
                welcomeRow.hidden = text === '';
            }
        }

        function syncPreviewImage() {
            var source = document.getElementById('chatbot_image');
            if (!source) {
                return;
            }

            ['wpiko-preview-image', 'wpiko-preview-msg-image', 'wpiko-preview-msg-image-2'].forEach(function (id) {
                var img = document.getElementById(id);
                if (img && source.value) {
                    img.src = source.value;
                }
            });
        }

        /* ------------------------------------------------------------------
           Hex text fields
           ------------------------------------------------------------------ */

        function normalizeHex(value) {
            var hex = String(value).trim().replace(/^#/, '');

            if (/^[0-9a-fA-F]{3}$/.test(hex)) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }

            return /^[0-9a-fA-F]{6}$/.test(hex) ? '#' + hex.toLowerCase() : null;
        }

        function syncHexField(input) {
            var hexField = form.querySelector('.wpiko-color-hex[data-color-for="' + input.id + '"]');
            if (hexField) {
                hexField.value = input.value.toUpperCase();
                hexField.classList.remove('is-invalid');
            }
        }

        /* ------------------------------------------------------------------
           Presets
           ------------------------------------------------------------------ */

        function markDirty() {
            if (dirtyBadge) {
                dirtyBadge.hidden = false;
            }
        }

        function setActivePreset(presetId) {
            if (presetField) {
                presetField.value = presetId;
            }

            presetCards.forEach(function (card) {
                var isActive = card.getAttribute('data-preset') === presetId;
                card.classList.toggle('is-active', isActive);
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            if (customNote) {
                customNote.hidden = presetId !== 'custom';
            }
        }

        function applyPreset(presetId) {
            var preset = presets[presetId];
            if (!preset) {
                return;
            }

            colorInputs.forEach(function (input) {
                var value = preset.colors[input.id];
                if (value) {
                    input.value = value;
                    syncHexField(input);
                }
            });

            if (borderToggle) {
                borderToggle.checked = preset.showUserBorder === '1';
            }

            setActivePreset(presetId);
            syncPreviewColors();
            markDirty();
        }

        presetCards.forEach(function (card) {
            card.addEventListener('click', function () {
                applyPreset(card.getAttribute('data-preset'));
            });
        });

        /* ------------------------------------------------------------------
           Field wiring
           ------------------------------------------------------------------ */

        colorInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                syncHexField(input);
                syncPreviewColors();
                setActivePreset('custom');
                markDirty();
            });
        });

        form.querySelectorAll('.wpiko-color-hex').forEach(function (hexField) {
            var target = document.getElementById(hexField.getAttribute('data-color-for'));
            if (!target) {
                return;
            }

            hexField.addEventListener('input', function () {
                var hex = normalizeHex(hexField.value);

                if (!hex) {
                    hexField.classList.add('is-invalid');
                    return;
                }

                hexField.classList.remove('is-invalid');
                target.value = hex;
                syncPreviewColors();
                setActivePreset('custom');
                markDirty();
            });

            // Restore a readable value if the field is left in a half-typed state.
            hexField.addEventListener('blur', function () {
                if (!normalizeHex(hexField.value)) {
                    hexField.value = target.value.toUpperCase();
                    hexField.classList.remove('is-invalid');
                }
            });
        });

        form.querySelectorAll('.wpiko-color-reset').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.getAttribute('data-color-for'));
                var fallback = button.getAttribute('data-default');

                if (!target || !fallback) {
                    return;
                }

                target.value = fallback;
                syncHexField(target);
                syncPreviewColors();
                setActivePreset('custom');
                markDirty();
            });
        });

        if (borderToggle) {
            borderToggle.addEventListener('change', function () {
                syncPreviewColors();
                setActivePreset('custom');
                markDirty();
            });
        }

        ['chatbot_name', 'subtitle_text', 'input_placeholder', 'welcome_message'].forEach(function (id) {
            var field = document.getElementById(id);
            if (field) {
                field.addEventListener('input', function () {
                    syncPreviewContent();
                    markDirty();
                });
            }
        });

        var imageField = document.getElementById('chatbot_image');
        if (imageField) {
            ['input', 'change'].forEach(function (eventName) {
                imageField.addEventListener(eventName, function () {
                    syncPreviewImage();
                    markDirty();
                });
            });
        }

        form.addEventListener('submit', function () {
            if (dirtyBadge) {
                dirtyBadge.hidden = true;
            }
        });

        // Initial paint.
        syncPreviewColors();
        syncPreviewContent();
        syncPreviewImage();
    });
})();

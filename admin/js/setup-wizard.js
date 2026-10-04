/**
 * Setup wizard: connect OpenAI, teach the chatbot, test and go live.
 */
(function ($) {
    'use strict';

    if (typeof wpikoSetupWizard === 'undefined') {
        return;
    }

    var i18n = wpikoSetupWizard.i18n;
    var threadId = null;
    var lastResponseId = null;
    var chatBusy = false;

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Keep only simple formatting from a bot reply (links, emphasis, lists).
     */
    function sanitizeReply(html) {
        var allowed = { A: 1, B: 1, STRONG: 1, EM: 1, I: 1, BR: 1, P: 1, UL: 1, OL: 1, LI: 1, CODE: 1 };
        var template = document.createElement('template');
        template.innerHTML = String(html || '');

        function clean(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                if (child.nodeType === 1) {
                    if (!allowed[child.tagName]) {
                        var text = document.createTextNode(child.textContent);
                        node.replaceChild(text, child);
                        return;
                    }
                    Array.prototype.slice.call(child.attributes).forEach(function (attr) {
                        var keep = child.tagName === 'A' && attr.name === 'href' && /^(https?:|mailto:|tel:)/i.test(attr.value);
                        if (!keep) {
                            child.removeAttribute(attr.name);
                        }
                    });
                    if (child.tagName === 'A') {
                        child.setAttribute('target', '_blank');
                        child.setAttribute('rel', 'noopener noreferrer');
                    }
                    clean(child);
                } else if (child.nodeType !== 3) {
                    node.removeChild(child);
                }
            });
        }

        clean(template.content);
        var wrapper = document.createElement('div');
        wrapper.appendChild(template.content.cloneNode(true));
        return wrapper.innerHTML;
    }

    function notice($target, type, message, link, linkLabel) {
        var html = '<div class="wpiko-wizard-notice is-' + type + '"><p>' + escapeHtml(message) + '</p>';
        if (link) {
            html += '<a class="button" href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(linkLabel || link) + '</a>';
        }
        html += '</div>';
        $target.html(html);
    }

    function goToStep(step) {
        $('.wpiko-wizard-step').each(function () {
            var isTarget = String($(this).data('step')) === String(step);
            $(this).toggleClass('is-active', isTarget).prop('hidden', !isTarget);
        });
        $('.wpiko-wizard-progress li').each(function () {
            var itemStep = Number($(this).data('step'));
            $(this).toggleClass('is-current', itemStep === Number(step));
            $(this).toggleClass('is-done', itemStep < Number(step));
        });
        var top = $('.wpiko-wizard').offset();
        if (top) {
            window.scrollTo({ top: Math.max(0, top.top - 60), behavior: 'smooth' });
        }
        if (Number(step) === 3) {
            $('#wpiko-wizard-chat-text').trigger('focus');
        }
    }

    function setConnected(connected) {
        $('.wpiko-wizard').attr('data-connected', connected ? '1' : '0');
        $('.wpiko-wizard-step[data-step="1"] .wpiko-wizard-next').prop('disabled', !connected);
        $('.wpiko-wizard-connected').prop('hidden', !connected);
        $('.wpiko-wizard-connect').prop('hidden', connected);
    }

    function handleKeyResponse(response) {
        var data = response && response.data ? response.data : {};
        var $message = $('.wpiko-wizard-message');

        if (response && response.success && data.connected) {
            setConnected(true);
            $message.empty();
            return;
        }

        if (data.saved) {
            // Key accepted, but the account needs attention (for example no credit).
            $('.wpiko-wizard-key-row').prop('hidden', true);
            $('.wpiko-wizard-retest-row').prop('hidden', false);
        }

        notice($message, 'error', data.message || i18n.genericError, data.link, data.link_label);
        setConnected(false);
    }

    function testKey(apiKey, $button) {
        var original = $button.text();
        $button.prop('disabled', true).text(i18n.testing);
        $('.wpiko-wizard-message').html('<p class="wpiko-wizard-working"><span class="spinner is-active"></span> ' + escapeHtml(i18n.testingLong) + '</p>');

        $.post(wpikoSetupWizard.ajaxUrl, {
            action: 'wpiko_chatbot_validate_api_key',
            security: wpikoSetupWizard.nonce,
            api_key: apiKey
        }).done(handleKeyResponse).fail(function () {
            notice($('.wpiko-wizard-message'), 'error', i18n.genericError);
        }).always(function () {
            $button.prop('disabled', false).text(original);
        });
    }

    function appendChat(role, content, isHtml) {
        var $messages = $('.wpiko-wizard-chat-messages');
        var body = isHtml ? sanitizeReply(content) : escapeHtml(content);
        $messages.append('<div class="wpiko-wizard-chat-msg is-' + role + '"><div>' + body + '</div></div>');
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function sendChat(text) {
        text = String(text || '').trim();
        if (!text || chatBusy) {
            return;
        }
        chatBusy = true;
        appendChat('user', text, false);
        $('#wpiko-wizard-chat-text').val('');
        var $typing = $('<div class="wpiko-wizard-chat-msg is-bot is-typing"><div>' + escapeHtml(i18n.thinking) + '</div></div>');
        $('.wpiko-wizard-chat-messages').append($typing);

        $.ajax({
            url: wpikoSetupWizard.ajaxUrl,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'wpiko_chatbot_send_message',
                security: wpikoSetupWizard.nonce,
                message: text,
                thread_id: threadId || '',
                previous_response_id: lastResponseId || ''
            }
        }).done(function (response) {
            $typing.remove();
            var data = response && response.data ? response.data : {};
            if (response && response.success) {
                if (data.thread_id) {
                    threadId = data.thread_id;
                }
                if (data.response_id) {
                    lastResponseId = data.response_id;
                }
                appendChat('bot', data.response || '', true);
            } else {
                appendChat('error', data.message || i18n.genericError, false);
                if (data.admin_link) {
                    $('.wpiko-wizard-chat-messages .wpiko-wizard-chat-msg.is-error').last().find('div')
                        .append(' <a href="' + escapeHtml(data.admin_link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(data.admin_link_label || data.admin_link) + '</a>');
                }
            }
        }).fail(function () {
            $typing.remove();
            appendChat('error', i18n.genericError, false);
        }).always(function () {
            chatBusy = false;
        });
    }

    $(function () {
        var $wizard = $('.wpiko-wizard');
        if (!$wizard.length) {
            return;
        }

        $('#wpiko-wizard-save-key').on('click', function () {
            var key = $('#wpiko-wizard-api-key').val().trim();
            if (!key) {
                notice($('.wpiko-wizard-message'), 'error', i18n.enterKey);
                return;
            }
            testKey(key, $(this));
        });

        $('#wpiko-wizard-api-key').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $('#wpiko-wizard-save-key').trigger('click');
            }
        });

        $('#wpiko-wizard-retest').on('click', function () {
            testKey('', $(this));
        });

        $('#wpiko-wizard-change-key').on('click', function () {
            $('.wpiko-wizard-retest-row').prop('hidden', true);
            $('.wpiko-wizard-key-row').prop('hidden', false);
            $('#wpiko-wizard-api-key').val('').trigger('focus');
        });

        $wizard.on('click', '.wpiko-wizard-next', function () {
            var next = $(this).data('next');
            var $button = $(this);

            if (Number(next) === 3) {
                $button.prop('disabled', true);
                $.post(wpikoSetupWizard.ajaxUrl, {
                    action: 'wpiko_chatbot_wizard_save_basics',
                    security: wpikoSetupWizard.nonce,
                    chatbot_name: $('#wpiko-wizard-name').val(),
                    business: $('#wpiko-wizard-business').val(),
                    tone: $('#wpiko-wizard-tone').val()
                }).always(function () {
                    $button.prop('disabled', false);
                    goToStep(3);
                });
                return;
            }

            goToStep(next);
        });

        $wizard.on('click', '.wpiko-wizard-back', function () {
            goToStep($(this).data('back'));
        });

        $('.wpiko-wizard-chat-suggestions').on('click', 'button', function () {
            sendChat($(this).text());
        });

        $('#wpiko-wizard-chat-send').on('click', function () {
            sendChat($('#wpiko-wizard-chat-text').val());
        });

        $('#wpiko-wizard-chat-text').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendChat($(this).val());
            }
        });

        $('#wpiko-wizard-finish').on('click', function () {
            var $button = $(this);
            var showFloating = $('#wpiko-wizard-show-floating').is(':checked');
            // Open the tab now (inside the click) so pop-up blockers allow it.
            var siteWindow = window.open('', '_blank');
            $button.prop('disabled', true);

            $.post(wpikoSetupWizard.ajaxUrl, {
                action: 'wpiko_chatbot_wizard_finish',
                security: wpikoSetupWizard.nonce,
                show_floating: showFloating ? '1' : '0'
            }).done(function (response) {
                var data = response && response.data ? response.data : {};
                if (siteWindow && data.site_url) {
                    siteWindow.location = data.site_url;
                } else if (siteWindow) {
                    siteWindow.close();
                }
                $('.wpiko-wizard-step[data-step="3"] .wpiko-wizard-nav').prop('hidden', true);
                $('.wpiko-wizard-done').prop('hidden', false);
                $('.wpiko-wizard-progress li').addClass('is-done').removeClass('is-current');
            }).fail(function () {
                if (siteWindow) {
                    siteWindow.close();
                }
                $button.prop('disabled', false);
                window.alert(i18n.genericError);
            });
        });
    });
})(jQuery);

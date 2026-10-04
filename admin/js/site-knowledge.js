/**
 * "Quick learn from your pages" panel: choose pages and teach them to the chatbot.
 */
(function ($) {
    'use strict';

    if (typeof wpikoSiteKnowledge === 'undefined') {
        return;
    }

    var i18n = wpikoSiteKnowledge.i18n;

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function pathOf(url) {
        try {
            var parsed = new URL(url);
            return parsed.pathname === '/' ? i18n.homepage : parsed.pathname;
        } catch (e) {
            return url;
        }
    }

    function Panel($root) {
        this.$root = $root;
        this.$list = $root.find('.wpiko-site-knowledge-list');
        this.$count = $root.find('.wpiko-site-knowledge-count');
        this.$status = $root.find('.wpiko-site-knowledge-status');
        this.$button = $root.find('.wpiko-site-knowledge-build');
        this.$result = $root.find('.wpiko-site-knowledge-result');
        this.$pending = $root.find('.wpiko-site-knowledge-pending');
        if (!this.$pending.length) {
            this.$pending = $('<span class="wpiko-site-knowledge-pending" aria-live="polite"></span>').insertAfter(this.$button);
        }
        this.limit = 10;
        this.builtAt = 0;
        this.loaded = false;
        this.dirty = false;
        this.covered = {};
        this.learned = {};
        this.bind();
        this.load();
    }

    Panel.prototype.bind = function () {
        var self = this;
        this.$list.on('change', 'input[type="checkbox"]', function () {
            self.dirty = true;
            self.$result.removeClass('is-error is-ok').empty();
            self.updateCount();
        });
        this.$button.on('click', function () {
            self.build();
        });
    };

    /**
     * Reload the list from the server, keeping ticks the user changed but has
     * not saved yet, and say which pages became covered by Scan Website.
     */
    Panel.prototype.refresh = function () {
        var self = this;
        if (!this.loaded || this.building) {
            return;
        }
        var keep = this.dirty ? this.$list.find('input[type="checkbox"]:checked').not('[data-covered]').map(function () {
            return Number(this.value);
        }).get() : null;
        this.load(keep);
    };

    Panel.prototype.load = function (keepSelection) {
        var self = this;
        $.post(wpikoSiteKnowledge.ajaxUrl, {
            action: 'wpiko_chatbot_site_knowledge_candidates',
            security: wpikoSiteKnowledge.nonce
        }).done(function (response) {
            if (!response || !response.success) {
                self.$list.html('<p class="wpiko-site-knowledge-empty">' + escapeHtml(i18n.loadError) + '</p>');
                return;
            }
            self.render(response.data, keepSelection);
        }).fail(function () {
            self.$list.html('<p class="wpiko-site-knowledge-empty">' + escapeHtml(i18n.loadError) + '</p>');
        });
    };

    Panel.prototype.render = function (data, keepSelection) {
        var self = this;
        this.limit = data.limit;
        // Ticks always start from what the chatbot has actually learned.
        var learnedIds = (data.learned || data.selected || []).map(Number);
        this.learned = {};
        learnedIds.forEach(function (id) {
            self.learned[id] = true;
        });
        var selected = (keepSelection || learnedIds).map(Number);

        // Tell the user which pages just became covered by another tool.
        var previouslyCovered = this.covered;
        var newlyCovered = [];
        this.covered = {};
        (data.candidates || []).forEach(function (page) {
            if (page.covered) {
                self.covered[page.id] = true;
                if (self.loaded && !previouslyCovered[page.id]) {
                    newlyCovered.push(page.title);
                }
            }
        });

        if (!data.candidates || !data.candidates.length) {
            this.$list.html('<p class="wpiko-site-knowledge-empty">' + escapeHtml(i18n.noPages) + '</p>');
            this.$button.prop('disabled', true);
            return;
        }

        var html = '<ul>';
        data.candidates.forEach(function (page) {
            var covered = !!page.covered;
            var checked = !covered && selected.indexOf(Number(page.id)) !== -1;
            var learned = !covered && !!self.learned[Number(page.id)];
            html += '<li' + (covered ? ' class="is-covered"' : '') + '><label>' +
                '<input type="checkbox" value="' + escapeHtml(page.id) + '"' + (checked ? ' checked' : '') + (covered ? ' disabled data-covered="1"' : '') + '>' +
                '<span class="wpiko-site-knowledge-title">' + escapeHtml(page.title) +
                (page.is_front_page ? ' <span class="wpiko-site-knowledge-badge">' + escapeHtml(i18n.homepage) + '</span>' : '') +
                (covered ? ' <span class="wpiko-site-knowledge-badge is-covered">' + escapeHtml(page.covered) + '</span>' : '') +
                (learned ? ' <span class="wpiko-site-knowledge-badge is-learned">' + escapeHtml(i18n.learnedBadge) + '</span>' : '') +
                '</span>' +
                '<span class="wpiko-site-knowledge-meta">' + escapeHtml(pathOf(page.url)) + ' · ' + escapeHtml(i18n.words.replace('%d', page.words)) + '</span>' +
                '</label></li>';
        });
        html += '</ul>';
        this.$list.html(html);
        this.loaded = true;
        this.dirty = !!keepSelection && this.dirty;
        this.updateCount();
        this.renderStatus(data);

        if (newlyCovered.length) {
            this.$result.removeClass('is-error').addClass('is-ok').text(i18n.nowCovered.replace('%s', newlyCovered.join(', ')));
            this.$root.addClass('is-updated');
            setTimeout(function () {
                self.$root.removeClass('is-updated');
            }, 1600);
        }
    };

    Panel.prototype.renderStatus = function (data) {
        this.builtAt = Number(data.built_at) || 0;
        if (data.stale) {
            this.$status.html('<div class="wpiko-site-knowledge-note is-warning">' + escapeHtml(i18n.stale) + '</div>');
        } else if (data.built_at) {
            this.$status.html('<div class="wpiko-site-knowledge-note is-ok">' +
                escapeHtml((Number(data.pages) === 1 ? i18n.builtStatusOne : i18n.builtStatus).replace('%1$s', data.pages).replace('%2$s', data.built_ago)) + '</div>');
        } else {
            // Nothing learned yet: say so plainly.
            this.$status.html('<div class="wpiko-site-knowledge-note is-empty">' + escapeHtml(i18n.notLearned) + '</div>');
        }
        this.updateCount();
    };

    /** True when the ticks differ from what the chatbot has learned. */
    Panel.prototype.hasPendingChanges = function () {
        var self = this;
        var checked = {};
        var changed = false;
        this.$list.find('input[type="checkbox"]:checked').not('[data-covered]').each(function () {
            checked[Number(this.value)] = true;
            if (!self.learned[Number(this.value)]) {
                changed = true;
            }
        });
        Object.keys(this.learned).forEach(function (id) {
            if (!checked[Number(id)] && !self.covered[id]) {
                changed = true;
            }
        });
        return changed;
    };

    Panel.prototype.updateCount = function () {
        var $boxes = this.$list.find('input[type="checkbox"]').not('[data-covered]');
        var checkedCount = $boxes.filter(':checked').length;
        var atLimit = checkedCount >= this.limit;

        $boxes.each(function () {
            var $box = $(this);
            var disable = atLimit && !$box.is(':checked');
            $box.prop('disabled', disable);
            $box.closest('li').toggleClass('is-disabled', disable);
        });

        this.$count.text(i18n.selected.replace('%1$d', checkedCount).replace('%2$d', this.limit));
        this.$count.toggleClass('is-full', atLimit);

        // Nothing ticked: offer to remove what the chatbot learned, if anything.
        var removing = checkedCount === 0 && this.builtAt > 0;
        this.$button.text(removing ? i18n.removeButton : (this.builtAt ? i18n.updateButton : i18n.buildButton));
        this.$button.toggleClass('is-remove', removing);
        this.$button.prop('disabled', checkedCount === 0 && !removing);

        // Make it obvious when ticks are not saved yet.
        var pending = this.loaded && !this.building && this.hasPendingChanges();
        this.$pending.text(pending ? (this.builtAt ? i18n.pending : i18n.pendingFirst) : '');
        this.$root.toggleClass('has-pending', pending);
    };

    Panel.prototype.build = function () {
        var self = this;
        var ids = this.$list.find('input[type="checkbox"]:checked').map(function () {
            return this.value;
        }).get();

        if (!ids.length && !this.builtAt) {
            return;
        }

        this.building = true;
        this.$button.prop('disabled', true);
        this.$result.removeClass('is-error is-ok').html('<span class="spinner is-active"></span> ' + escapeHtml(ids.length ? i18n.building : i18n.removing));

        $.ajax({
            url: wpikoSiteKnowledge.ajaxUrl,
            type: 'POST',
            timeout: 180000,
            data: {
                action: 'wpiko_chatbot_site_knowledge_build',
                security: wpikoSiteKnowledge.nonce,
                page_ids: ids
            }
        }).done(function (response) {
            var data = response && response.data ? response.data : {};
            if (response && response.success) {
                if (data.state) {
                    // Redraw from the saved state so ticks and labels match what was learned.
                    self.render(data.state);
                }
                self.$result.addClass('is-ok').text(data.message);
                self.$root.trigger('wpiko:siteKnowledgeBuilt', [data]);
            } else {
                self.$result.addClass('is-error').text(data.message || i18n.buildError);
            }
        }).fail(function () {
            self.$result.addClass('is-error').text(i18n.buildError);
        }).always(function () {
            self.building = false;
            self.dirty = false;
            self.updateCount();
        });
    };

    // Requests that add or remove knowledge files. When one succeeds anywhere on
    // the page (for example Pro's Scan Website or File Management), the list is
    // refreshed so it never shows outdated ticks.
    var KNOWLEDGE_ACTIONS = [
        'wpiko_chatbot_upload_qa_to_assistant',
        'wpiko_chatbot_delete_file',
        'wpiko_chatbot_delete_responses_vector_store',
        'wpiko_chatbot_upload_file_responses'
    ];

    function requestAction(settings) {
        var data = settings && settings.data;
        if (!data) {
            return '';
        }
        if (typeof FormData !== 'undefined' && data instanceof FormData) {
            return data.get('action') || '';
        }
        if (typeof data === 'string') {
            var match = data.match(/(?:^|&)action=([^&]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        }
        return data.action || '';
    }

    $(function () {
        $('.wpiko-site-knowledge').each(function () {
            $(this).data('wpikoPanel', new Panel($(this)));
        });

        var refreshTimer = null;
        $(document).ajaxSuccess(function (event, xhr, settings) {
            if (KNOWLEDGE_ACTIONS.indexOf(requestAction(settings)) === -1) {
                return;
            }
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(function () {
                $('.wpiko-site-knowledge').each(function () {
                    var panel = $(this).data('wpikoPanel');
                    if (panel) {
                        panel.refresh();
                    }
                });
            }, 400);
        });

        // "Open Scan Website": open Pro's Scan Website window when it is on this page.
        $(document).on('click', '.wpiko-open-scan-website', function (event) {
            var $scanButton = $('#responses-scan-website-button');
            if ($scanButton.length) {
                event.preventDefault();
                $scanButton.trigger('click');
            }
        });

        // Arriving from the wizard with ?open=scan-website.
        if (/[?&]open=scan-website(?:&|$)/.test(window.location.search)) {
            setTimeout(function () {
                $('#responses-scan-website-button').trigger('click');
            }, 300);
        }
    });
})(jQuery);

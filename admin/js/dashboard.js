/** WPiko AI Chatbot dashboard. */
jQuery(function($) {
    'use strict';

    if (!$('.wpiko-chatbot-dashboard').length) {
        return;
    }

    var $progress = $('.setup-progress');

    // Progress bar and "X of Y essentials done" follow the essentials list.
    function updateProgress() {
        if (!$progress.length) {
            return;
        }
        var total = parseInt($progress.data('total'), 10) || 0;
        var done = $('.setup-item.is-done[data-essential]').length;
        $progress.find('.setup-progress-done').text(done);
        $progress.find('.setup-progress-bar > span').css('width', (total ? Math.round(100 * done / total) : 0) + '%');
        $progress.toggleClass('is-incomplete', done < total);
    }

    function setEssential($item, state, value, action) {
        $item.removeClass('is-done is-todo is-warn is-error is-loading is-neutral').addClass('is-' + state);
        $item.find('.setup-value').text(value).attr('title', value);
        if (action) {
            $item.find('.setup-action').contents().first().replaceWith(document.createTextNode(action));
        }
    }

    // The knowledge base summary needs a request to OpenAI, so it loads after the page.
    var $knowledge = $('.setup-item[data-essential="knowledge"][data-async]');
    if ($knowledge.length && typeof wpikoChatbotAdmin !== 'undefined') {
        $.post(wpikoChatbotAdmin.ajax_url, {
            action: 'wpiko_chatbot_dashboard_knowledge',
            security: wpikoChatbotAdmin.nonce
        }).done(function(response) {
            var data = response && response.success ? response.data : null;
            if (!data || !data.ok) {
                setEssential($knowledge, 'neutral', 'Could not check right now', 'Open');
            } else if (data.total > 0) {
                setEssential($knowledge, 'done', data.label, 'Manage');
            } else {
                setEssential($knowledge, 'warn', 'Empty, so answers will be generic', 'Add content');
            }
        }).fail(function() {
            setEssential($knowledge, 'neutral', 'Could not check right now', 'Open');
        }).always(updateProgress);
    }

    updateProgress();

    // Recent activity: always the latest five conversations, plus as many more as fit
    // beside AI usage & cost, so both cards end at the same height. On narrow screens,
    // where the cards are stacked, only the five are shown.
    var $overviewRow = $('.dashboard-overview-row');
    var $activityWidget = $overviewRow.find('.activity-widget');
    var $usageWidget = $overviewRow.find('.usage-widget');

    function fitActivity() {
        var $extra = $activityWidget.find('.activity-extra');
        if (!$extra.length || !$usageWidget.length) {
            return;
        }
        $extra.prop('hidden', true);
        if (Math.abs($activityWidget.offset().top - $usageWidget.offset().top) > 2) {
            return;
        }
        // Measure both cards at their natural height, not stretched to the row.
        $overviewRow.addClass('is-measuring');
        var target = $usageWidget.outerHeight();
        $extra.each(function() {
            this.hidden = false;
            if ($activityWidget.outerHeight() > target) {
                this.hidden = true;
                return false;
            }
        });
        $overviewRow.removeClass('is-measuring');
    }

    if ($activityWidget.length) {
        var fitTimer = null;
        fitActivity();
        $(window).on('load', fitActivity).on('resize', function() {
            clearTimeout(fitTimer);
            fitTimer = setTimeout(fitActivity, 150);
        });
        // Opening "Tokens and models" makes the cost card taller.
        $usageWidget.find('details').on('toggle', fitActivity);
    }

    // Refresh the dashboard data every five minutes, but only while the tab is
    // visible: a background tab waits and refreshes when the admin comes back.
    var refreshDue = false;
    var dashboardRefreshInterval = setInterval(function() {
        if (document.hidden) {
            refreshDue = true;
        } else {
            location.reload();
        }
    }, 300000);

    $(document).on('visibilitychange', function() {
        if (refreshDue && !document.hidden) {
            location.reload();
        }
    });

    $(window).on('beforeunload', function() {
        clearInterval(dashboardRefreshInterval);
    });
});

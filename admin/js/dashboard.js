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

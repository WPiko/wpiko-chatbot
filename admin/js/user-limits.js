/**
 * User Limits: keep the "Cost check" amounts in step with the limit fields,
 * so admins see what a limit costs before saving it.
 */
(function () {
    'use strict';

    function init() {
        var box = document.getElementById('wpiko-cost-check');
        if (!box || !box.dataset.replyCost) {
            return;
        }
        var replyCost = parseFloat(box.dataset.replyCost);
        if (!(replyCost > 0)) {
            return;
        }

        var decimalPoint = box.dataset.decimal || '.';
        var thousandsSep = box.dataset.thousands || ',';
        var enabled = document.getElementById('user_limits_enabled');
        var daily = document.getElementById('user_limits_daily');
        var site = document.getElementById('user_limits_site_daily');

        // Same output as number_format_i18n() and wpiko_chatbot_format_usage_cost() in PHP.
        function formatNumber(value, decimals) {
            var parts = value.toFixed(decimals).split('.');
            var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
            return decimals ? whole + decimalPoint + parts[1] : whole;
        }

        function formatCost(usd) {
            if (usd <= 0) {
                return '$' + formatNumber(0, 2);
            }
            if (usd >= 0.01) {
                return '$' + formatNumber(usd, 2);
            }
            if (Number(usd.toFixed(4)) <= 0) {
                return '< $' + formatNumber(0.0001, 4);
            }
            var text = formatNumber(usd, 4).replace(/0+$/, '');
            if (text.slice(-decimalPoint.length) === decimalPoint) {
                text = text.slice(0, -decimalPoint.length);
            }
            return '$' + text;
        }

        function show(key, requests) {
            var cost = box.querySelector('[data-cost-for="' + key + '"]');
            var count = box.querySelector('[data-requests-for="' + key + '"]');
            if (cost) {
                cost.textContent = formatCost(replyCost * requests);
            }
            if (count) {
                count.textContent = formatNumber(requests, 0);
            }
        }

        // Mirrors wpiko_chatbot_user_limits_check_and_record(): safety cap 300 per visitor,
        // the custom daily limit applies only when the switch is on; site-wide 0 means 1,000.
        function update() {
            var visitorCap = 300;
            var customDaily = parseInt(daily ? daily.value : '', 10);
            if (enabled && enabled.checked && customDaily > 0) {
                visitorCap = Math.min(visitorCap, customDaily);
            }
            var siteCap = parseInt(site ? site.value : '', 10);
            if (!(siteCap > 0)) {
                siteCap = 1000;
            }
            show('visitor', visitorCap);
            show('site', siteCap);
        }

        [enabled, daily, site].forEach(function (field) {
            if (field) {
                field.addEventListener('input', update);
                field.addEventListener('change', update);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

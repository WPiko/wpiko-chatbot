/** wpiko Chatbot responsive screen navigation and resource links. */
(function () {
    'use strict';

    var header = document.querySelector('[data-wpiko-chatbot-header]');
    var toggle = header && header.querySelector('[data-wpiko-chatbot-header-toggle]');
    if (!toggle) {
        return;
    }

    var setOpen = function (open) {
        header.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', function () {
        setOpen(!header.classList.contains('is-open'));
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && header.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', function (event) {
        if (header.classList.contains('is-open') && !header.contains(event.target)) {
            setOpen(false);
        }
    });
    if (window.matchMedia) {
        var sizeSidebar = function () {
            var menu = document.getElementById('wpiko-chatbot-sidebar-menu');
            if (menu && window.matchMedia('(min-width: 783px)').matches) {
                menu.style.setProperty('--wpiko-sidebar-height', Math.max(0, window.innerHeight - menu.getBoundingClientRect().top - 16) + 'px');
            }
        };
        var revealCurrentScreen = function () {
            var menu = document.getElementById('wpiko-chatbot-sidebar-menu');
            var current = menu && menu.querySelector('[aria-current="page"]');
            if (current && window.matchMedia('(min-width: 783px)').matches) {
                var bounds = menu.getBoundingClientRect();
                var item = current.getBoundingClientRect();
                if (item.top < bounds.top || item.bottom > bounds.bottom) {
                    menu.scrollTop += item.top - bounds.top - (menu.clientHeight - item.height) / 2;
                }
            }
        };
        sizeSidebar();
        revealCurrentScreen();
        window.addEventListener('resize', sizeSidebar);
        window.addEventListener('scroll', sizeSidebar, {passive: true});
        window.matchMedia('(min-width: 783px)').addEventListener('change', function (event) {
            if (event.matches) {
                setOpen(false);
                sizeSidebar();
                revealCurrentScreen();
            }
        });
    }
}());

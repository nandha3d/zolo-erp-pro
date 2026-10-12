/* Keeps the left sidebar where the user left it across full page loads:
 * scroll offset and which submenus are open. Per-tab (sessionStorage). */
(function ($) {
    'use strict';
    var KEY = 'zolo_sidebar_nav_state';
    var $nav = $('nav.side-navbar');
    if (!$nav.length) return;

    function read() { try { return JSON.parse(sessionStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
    function write(state) { try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }

    function scrollOffset() {
        var $container = $nav.find('.mCSB_container').first();
        return $container.length ? Math.abs($container.position().top) : $nav.scrollTop();
    }
    function openMenus() {
        return $nav.find('ul.collapse.show').map(function () { return this.id; }).get().filter(Boolean);
    }
    function save() { write({ scroll: scrollOffset(), open: openMenus() }); }

    function restore() {
        var state = read();
        (state.open || []).forEach(function (id) {
            var $menu = $nav.find('ul.collapse').filter(function () { return this.id === id; });
            $menu.addClass('show').prev('a').attr('aria-expanded', 'true').removeClass('collapsed');
        });
        if (!state.scroll) return;
        if ($nav.find('.mCSB_container').length && $.fn.mCustomScrollbar) {
            $nav.mCustomScrollbar('update');
            $nav.mCustomScrollbar('scrollTo', state.scroll, { scrollInertia: 0, timeout: 0 });
        } else {
            $nav.scrollTop(state.scroll);
        }
    }

    $nav.on('click', 'a', save);
    $nav.on('shown.bs.collapse hidden.bs.collapse', save);
    $(window).on('pagehide beforeunload', save);
    $(restore);
    $(window).on('load', restore);
})(jQuery);

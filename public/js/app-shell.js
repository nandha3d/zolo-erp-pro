/* One-screen app shell (see css/app-shell.css). Pages fit the screen without a page scrollbar:
   - DataTables lists show as many rows as fit and page through the rest (a length picked from the
     menu is respected);
   - plain grids that are still too tall scroll inside their own box under a sticky header;
   - long forms marked data-shell-steps are split into labelled steps; Enter on a step's last field
     moves to the next step, and saving reveals the step holding any invalid field. */
(function ($) {
    'use strict';
    var content = document.getElementById('content');
    if (!$ || !content || !document.documentElement.classList.contains('app-shell')) return;

    /* ---------- Steps ---------- */
    var STEP = '[data-step-title]';

    function stepsOf(container) {
        return Array.prototype.filter.call(container.querySelectorAll(STEP), function (step) {
            return step.closest('[data-shell-steps]') === container;
        });
    }

    function showStep(container, index) {
        var steps = stepsOf(container);
        steps.forEach(function (step, i) { step.classList.toggle('is-active', i === index); });
        container.querySelectorAll('.shell-steps-nav [role="tab"]').forEach(function (tab, i) {
            tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
            tab.tabIndex = i === index ? 0 : -1;
        });
        $(window).trigger('resize');
    }

    // Reveal the step holding this element (used by Enter navigation and validation).
    function revealStep(element) {
        var step = element && element.closest && element.closest(STEP);
        if (!step || step.classList.contains('is-active')) return false;
        var container = step.closest('[data-shell-steps]');
        showStep(container, stepsOf(container).indexOf(step));
        return true;
    }
    window.zoloSteps = { reveal: revealStep };

    function initSteps(container) {
        var steps = stepsOf(container);
        if (steps.length < 2) return;
        container.classList.add('shell-steps');
        var nav = document.createElement('div');
        nav.className = 'shell-steps-nav';
        nav.setAttribute('role', 'tablist');
        steps.forEach(function (step, i) {
            var tab = document.createElement('button');
            tab.type = 'button';
            tab.setAttribute('role', 'tab');
            tab.innerHTML = '<span class="shell-step-no">' + (i + 1) + '</span>';
            tab.appendChild(document.createTextNode(step.getAttribute('data-step-title')));
            tab.addEventListener('click', function () { showStep(container, i); });
            nav.appendChild(tab);
        });
        nav.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            var tabs = Array.prototype.slice.call(nav.children);
            var next = (tabs.indexOf(document.activeElement) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            showStep(container, next);
            tabs[next].focus();
            event.preventDefault();
        });
        container.insertBefore(nav, container.firstChild);
        showStep(container, 0);

        // Validate with every step laid out, so hidden-step fields are checked too, then open the
        // step that holds the first error.
        container.addEventListener('click', function (event) {
            if (!event.target.closest('button[type="submit"], input[type="submit"], [data-step-submit]')) return;
            container.classList.add('shell-steps-all');
            setTimeout(function () {
                container.classList.remove('shell-steps-all');
                var selector = 'input.error, select.error, textarea.error, .is-invalid, [aria-invalid="true"], .has-error, .validation-msg:not(:empty)';
                var tabs = container.querySelectorAll('.shell-steps-nav [role="tab"]');
                stepsOf(container).forEach(function (step, i) { tabs[i].classList.toggle('has-errors', !!step.querySelector(selector)); });
                var invalid = container.querySelector(selector);
                if (invalid) revealStep(invalid);
            }, 0);
        }, true);
        container.addEventListener('invalid', function (event) { revealStep(event.target); }, true);
    }

    document.querySelectorAll('[data-shell-steps]').forEach(initSteps);

    /* ---------- Page wrappers ---------- */
    // Wrappers styled min-height: calc(100vh - 40px) sit under a command bar and so overflowed by
    // that bar; inside the shell they grow to fill #content instead.
    function relaxFullHeightWrappers() {
        var limit = content.clientHeight - 80;
        Array.prototype.forEach.call(content.querySelectorAll(':scope > *, :scope > * > *'), function (el) {
            if (parseFloat(getComputedStyle(el).minHeight) >= limit) {
                el.style.minHeight = '0px';
                if (el.parentElement === content) el.style.flexGrow = '1';
            }
        });
    }
    relaxFullHeightWrappers();

    /* ---------- Wide grids ---------- */
    // Tables wider than their box get compact type and wrapped headers so every column is in view.
    function fitWideTables() {
        Array.prototype.forEach.call(content.querySelectorAll('table'), function (table) {
            if (table.offsetParent === null || table.closest('.modal, .desk-grid-table, [data-no-fit]')) return;
            var box = table.parentElement;
            if (table.classList.contains('shell-fit-wide')) return;
            if (table.scrollWidth > box.clientWidth + 2) table.classList.add('shell-fit-wide');
        });
    }

    /* ---------- Plain grids ---------- */
    // When the page is still taller than the screen, the tallest plain grid gives up the difference
    // and scrolls inside its own box under a sticky header.
    function fitPlainTables() {
        content.querySelectorAll('.shell-fit-scroll').forEach(function (box) {
            box.style.maxHeight = '';
            box.classList.remove('shell-fit-scroll');
        });
        for (var pass = 0; pass < 3; pass++) {
            var over = content.scrollHeight - content.clientHeight;
            if (over <= 1) return;
            var tables = Array.prototype.filter.call(content.querySelectorAll('table'), function (table) {
                return table.offsetParent !== null && !table.closest('.modal, .dataTables_wrapper, .shell-fit-scroll, .desk-grid-table, [data-no-fit]')
                    && !table.classList.contains('dataTable') && table.tBodies[0] && table.tBodies[0].rows.length > 2;
            }).sort(function (a, b) { return b.offsetHeight - a.offsetHeight; });
            if (!tables.length) return;
            var box = tables[0].parentElement;
            var height = box.getBoundingClientRect().height;
            var target = Math.max(160, height - over);
            if (target >= height - 1) return;
            box.style.maxHeight = target + 'px';
            box.classList.add('shell-fit-scroll');
        }
    }

    /* ---------- DataTables ---------- */
    var MIN_ROWS = 5;
    var fitting = false;
    var hasDataTables = !!($.fn && $.fn.dataTable);

    // The element whose height the list must fit in: the nearest scroll container up to #content.
    function regionOf(node) {
        for (var el = node.parentElement; el && el !== content; el = el.parentElement) {
            var oy = getComputedStyle(el).overflowY;
            if ((oy === 'auto' || oy === 'scroll') && el.clientHeight > 0) return el;
        }
        return content;
    }

    // Free vertical space in the region (negative when its content overflows).
    function freeSpace(region) {
        var style = getComputedStyle(region);
        var inner = region.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
        var used = 0;
        Array.prototype.forEach.call(region.children, function (child) {
            var cs = getComputedStyle(child);
            if (cs.display === 'none' || cs.position === 'absolute' || cs.position === 'fixed') return;
            used += child.getBoundingClientRect().height + parseFloat(cs.marginTop) + parseFloat(cs.marginBottom);
        });
        return inner - used;
    }

    function fit(api) {
        var node = api.table().node();
        if (!node.isConnected || node.offsetParent === null || node.dataset.lengthChosen === '1') return;
        var body = node.tBodies[0];
        if (!body || !body.rows.length || body.rows[0].querySelector('.dataTables_empty')) return;
        var rowH = body.rows[0].getBoundingClientRect().height || 32;
        var info = api.page.info();
        var len = api.page.len();
        if (len < 0) return;
        var shown = info.end - info.start;
        var free = freeSpace(regionOf(node));
        var target = len;
        if (free < -1) {
            target = Math.max(MIN_ROWS, shown - Math.ceil(-free / rowH));
        } else if (shown >= len && info.recordsDisplay > shown && free >= rowH) {
            target = Math.min(info.recordsDisplay, len + Math.floor(free / rowH));
        }
        if (target === len) return;
        var attempts = +(node.dataset.fitAttempts || 0);
        if (attempts > 6) return;
        node.dataset.fitAttempts = attempts + 1;
        var $select = $(api.table().container()).find('.dataTables_length select');
        if ($select.length && !$select.find('option[value="' + target + '"]').length) {
            $select.find('option.fit-option').remove();
            $select.prepend($('<option class="fit-option">').val(target).text(target));
        }
        fitting = true;
        try { api.page.len(target).draw(false); } finally { fitting = false; }
    }

    function fitAll() {
        if (!hasDataTables) return;
        $.each($.fn.dataTable.tables(true), function (_, node) {
            node.dataset.fitAttempts = 0;
            fit($(node).DataTable());
        });
    }

    if (hasDataTables) {
        $(document)
            .on('draw.dt', function (event, settings) {
                if (event.namespace !== 'dt') return;
                var api = new $.fn.dataTable.Api(settings);
                // Let layout settle (fixed headers, column widths) before measuring.
                requestAnimationFrame(function () { fit(api); });
            })
            .on('length.dt', function (event, settings) {
                if (!fitting) settings.nTable.dataset.lengthChosen = '1';
            });
    }

    function refit() { fitWideTables(); fitAll(); fitPlainTables(); }
    $(window).on('load', function () { setTimeout(function () { fitWideTables(); fitPlainTables(); }, 50); });
    $(document).on('draw.dt', function () { requestAnimationFrame(fitWideTables); });
    $(document).on('shown.bs.tab shown.bs.collapse hidden.bs.collapse shown.bs.modal', function () { setTimeout(refit, 50); });
    var timer;
    $(window).on('resize', function () { clearTimeout(timer); timer = setTimeout(refit, 150); });
})(window.jQuery);

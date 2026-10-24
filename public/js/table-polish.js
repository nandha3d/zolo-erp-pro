/*
 * One behaviour for every DataTable in the app:
 *  - rows stay one line and the table fits its card, so the page never scrolls sideways;
 *  - when the columns do not fit, the least useful ones are hidden (real DataTables visibility, so they appear unticked
 *    under "Columns" and can be switched back on) instead of the Responsive plugin's hidden "+" child rows;
 *  - every table gets a labelled "Columns" picker, and a viewer's own choice is remembered per page and table.
 */
(() => {
    'use strict';
    const $ = window.jQuery;
    if (!$ || !$.fn.dataTable) return;

    // Responsive stays loaded for pages that pass `responsive: true`, but never hides columns itself.
    const Responsive = $.fn.dataTable.Responsive;
    if (Responsive) {
        Responsive.prototype._columnsVisiblity = function () { return this.s.dt.columns().indexes().toArray().map(() => true); };
        Responsive.defaults.details = false;
    }

    // Hidden first when space runs out (matched against the header text).
    const LOW_VALUE = /image|created by|currency|exchange|returned|delivery status|biller|staff|note|stock worth|alert/i;
    const KEEP = /^(action|actions|options)$/i;
    // Hidden last: the figures a register exists to show.
    const MONEY = /total|amount|paid|due|balance|qty|quantity|price|rate|value|closing/i;
    const storageKey = table => 'zolo.columns.' + location.pathname + '#' + (table.id || 'table');
    const read = key => { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (error) { return null; } };
    const write = (key, value) => { try { localStorage.setItem(key, JSON.stringify(value)); } catch (error) { /* private mode */ } };

    function headerText(dt, index) { return $(dt.column(index).header()).text().replace(/\s+/g, ' ').trim(); }

    function polish(settings) {
        const dt = new $.fn.dataTable.Api(settings);
        const table = dt.table().node();
        const wrapper = $(table).closest('.dataTables_wrapper');
        if (!wrapper.length || table.dataset.polished || $(table).closest('.modal').length) return;
        table.dataset.polished = '1';
        wrapper.addClass('zolo-polished');
        const key = storageKey(table);
        const autoHidden = new Set();
        let busy = false;

        const setVisible = (index, visible) => { busy = true; dt.column(index).visible(visible, false); busy = false; };
        const overflowing = () => table.scrollWidth > wrapper[0].clientWidth + 2;

        function fit() {
            if (read(key) || !wrapper.is(':visible')) return;
            autoHidden.forEach(index => setVisible(index, true));
            autoHidden.clear();
            dt.columns.adjust();
            const count = dt.columns().count();
            const all = [...Array(count).keys()].filter(i => dt.column(i).visible() && i > 1 && !KEEP.test(headerText(dt, i)) && headerText(dt, i) !== '');
            // A column showing the same value on every row on screen (all "N/A", one warehouse) tells the viewer nothing.
            const flat = i => { const cells = dt.column(i, {page: 'current'}).nodes().toArray().map(td => td.textContent.trim()); return cells.length > 1 && cells.every(text => text === cells[0]); };
            const rank = i => (flat(i) ? 0 : LOW_VALUE.test(headerText(dt, i)) ? 1 : MONEY.test(headerText(dt, i)) ? 3 : 2);
            const order = all.reverse().sort((a, b) => rank(a) - rank(b));
            for (const index of order) {
                if (!overflowing() || dt.columns(':visible').count() <= 4) break;
                setVisible(index, false);
                autoHidden.add(index);
            }
            // Hiding a wide column can free more room than needed: bring back the most useful ones that still fit.
            [...autoHidden].sort((a, b) => rank(b) - rank(a)).forEach(index => {
                setVisible(index, true);
                dt.columns.adjust();
                if (overflowing()) setVisible(index, false); else autoHidden.delete(index);
            });
            dt.columns.adjust();
            label();
        }

        function label() {
            const hidden = dt.columns().count() - dt.columns(':visible').count();
            wrapper.find('.buttons-colvis').html('<i class="fa fa-columns" aria-hidden="true"></i> Columns' + (hidden ? ' <span class="zolo-hidden-count">' + hidden + ' hidden</span>' : ''))
                .attr('title', 'Show or hide columns');
        }

        // Pages without a column picker get one next to their other table buttons (or beside the search box).
        if (!wrapper.find('.buttons-colvis').length) {
            try {
                const picker = new $.fn.dataTable.Buttons(dt, {buttons: [{extend: 'colvis', columns: ':not(:first-child):not(.not-exported)'}]});
                const host = wrapper.find('.dt-buttons').first();
                if (host.length) host.append(picker.container().children()); else wrapper.find('.dataTables_filter').first().before(picker.container());
            } catch (error) { /* Buttons not loaded on this page */ }
        }

        const stored = read(key);
        if (stored) {
            busy = true;
            dt.columns().every(function (index) { const name = headerText(dt, index); if (name && name in stored) this.visible(stored[name], false); });
            busy = false;
            dt.columns.adjust();
        }

        dt.on('column-visibility.dt', () => {
            if (busy) return;
            // A viewer's own toggle wins from now on, including over automatic fitting.
            const choice = {};
            dt.columns().every(function (index) { const name = headerText(dt, index); if (name) choice[name] = this.visible(); });
            write(key, choice);
            autoHidden.clear();
            label();
        });

        // Refit whenever the card changes width: window resize, split/full register switch, side panel docking.
        let timer = null, lastWidth = 0;
        const refit = () => { clearTimeout(timer); timer = setTimeout(fit, 150); };
        if (window.ResizeObserver) {
            new ResizeObserver(() => { const width = wrapper[0].clientWidth; if (width && Math.abs(width - lastWidth) > 4) { lastWidth = width; refit(); } }).observe(wrapper[0]);
        } else $(window).on('resize', refit);
        dt.on('draw.dt', () => { if (!read(key) && overflowing()) fit(); });
        fit();
        label();
    }

    $(document).on('init.dt', (event, settings) => { if (event.namespace === 'dt') polish(settings); });
})();

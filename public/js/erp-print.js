/**
 * Report and list printing: every report prints as a document, not a screenshot of the screen.
 *
 * - DataTables' Print button (and every page action that calls it) prints all matching rows, not just the
 *   page on screen, as a clean table under the company letterhead, report title and the filters used.
 * - Command-bar buttons marked [data-erp-print] print the page's table the same way; pages without a
 *   table fall back to window.print(), where erp-print.css lays the page out under the same letterhead.
 * Output goes through a hidden iframe, so no popup window is needed.
 */
(function ($) {
    'use strict';

    var source = document.currentScript;
    var styles = (source.dataset.css || '').split(' ').filter(Boolean);
    var print = $.fn.dataTable && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.print;
    var pending = new WeakSet();
    var NUMBER = /^[-+(]?\s*(₹|Rs\.?|INR)?\s*[-+]?[\d,]*\.?\d+\s*(%|Dr|Cr)?\)?$/i;
    var SKIP_HEADER = /^(action|actions|option|options)?$/i;
    var IDENTIFIER = /phone|mobile|code|\bno\b|number|\bid\b|year|pin|gst|hsn|sac|ref/i;

    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    function clean(text) {
        return String(text == null ? '' : text).replace(/\s+/g, ' ').trim();
    }

    /** Cell HTML as text, with line breaks inside a cell ("Name<br>Code") kept apart by a dot. */
    function text(html) {
        var holder = document.createElement('template');
        holder.innerHTML = String(html == null ? '' : html).replace(/<br\s*\/?>|<\/(div|p|li)>/gi, ' · ');
        return clean(holder.content.textContent).replace(/^(·\s*)+|(\s*·)+$/g, '').replace(/·(\s*·)+/g, '·');
    }

    function title() {
        // Workspace bars (Settings, Products, Purchase) name the workspace in the heading and the screen in the current tab.
        var current = document.querySelector('#content .comm-nav-pills [aria-current="page"]');
        if (current && clean(current.textContent)) return clean(current.textContent);
        var heading = document.querySelector('#content .comm-title-group h1, #content h1, #content .page-title');
        var text = clean(heading ? heading.textContent : document.title).replace(/\s*command\s*cent(er|re)\s*/i, ' ');
        return clean(text) || document.title;
    }

    /** "Label: value" for each filter the report was run with, read from the page's filter card. */
    function filters(ledgerScope, search) {
        var seen = {}, out = [];
        $('#content .desk-filter-card, #content .report-filter-card').find('select, input').each(function () {
            if (/^(hidden|submit|button|checkbox|radio|search)$/i.test(this.type) || $(this).closest('.bootstrap-select .dropdown-menu').length) return;
            // Label text without its help icon, and "Choose your date" reads "Date".
            var label = $(this).closest('.form-group, [class*="col-"]').find('label').first().clone();
            label.find('i, svg, button, [class*="help"]').remove();
            label = clean(label.text() || $(this).attr('placeholder') || '').replace(/^choose\s+(your\s+)?/i, '');
            label = label.charAt(0).toUpperCase() + label.slice(1);
            var value;
            if (ledgerScope && this.name === 'transaction_type') {
                value = ledgerScope;
            } else if (this.tagName === 'SELECT') {
                var options = ledgerScope ? Array.from(this.options).filter(function (option) { return option.defaultSelected; }) : Array.from(this.selectedOptions);
                value = options.map(function (option) { return clean(option.textContent); }).join(', ');
            } else {
                value = clean(ledgerScope ? this.defaultValue : this.value);
            }
            if (!label || !value || seen[label]) return;
            seen[label] = true;
            out.push('<span><b>' + esc(label.replace(/[:*]+$/, '')) + ':</b> ' + esc(value) + '</span>');
        });
        if (search) out.push('<span><b>Search:</b> ' + esc(search) + '</span>');
        return out.join('');
    }

    /** The letterhead, filled in for this print. */
    function header(name, options) {
        var head = document.querySelector('.page .erp-print-header');
        if (!head) return '';
        $(head).find('[data-erp-print-title]').text(name || title());
        $(head).find('[data-erp-print-meta]').html(filters(options && options.scope, options && options.search));
        $(head).find('[data-erp-print-time]').text(new Date().toLocaleString('en-IN', {
            day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
        }));
        return head.outerHTML;
    }

    function printHtml(body, wide, name) {
        if (document.querySelector('.erp-print-frame')) return;
        var frame = document.createElement('iframe');
        frame.className = 'erp-print-frame';
        frame.setAttribute('aria-hidden', 'true');
        frame.addEventListener('load', function () {
            var printWindow = frame.contentWindow;
            printWindow.addEventListener('afterprint', function () { frame.remove(); }, {once: true});
            printWindow.focus();
            printWindow.print();
        }, {once: true});
        frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><base href="' + esc(document.baseURI) + '"><title>' + esc(name || title()) + '</title>'
            + styles.map(function (href) { return '<link rel="stylesheet" href="' + esc(href) + '">'; }).join('')
            + '</head><body class="erp-print-doc' + (wide ? ' erp-print-wide' : '') + '">' + body + '</body></html>';
        document.body.appendChild(frame);
    }

    function render(dt, config, options) {
        var exportOptions = $.extend({columns: ':visible:not(.not-exported):not(.not-exported-col)'}, config.exportOptions, {stripHtml: false, decodeEntities: false});
        // Detached client-side rows are also part of the filtered result.
        if (exportOptions.rows === ':visible') exportOptions.rows = null;
        var data = dt.buttons.exportData(exportOptions);
        var rows = function (list) { return list.map(function (value) { return text(value); }); };
        var footer = config.footer !== false && data.footer ? rows(data.footer) : null;
        options = options || {};
        if (footer) {
            options.footerCells = dt.columns(exportOptions.columns).indexes().toArray().map(function (index) {
                return dt.column(index).footer();
            });
            if (options.filteredLedger) {
                footer = footer.map(function (value) {
                    return value === 'Total Activity & Closing Balance:' ? 'Full Ledger Activity & Closing Balance:' : value;
                });
            }
        }
        table(rows(data.header), data.body.map(rows), footer, options.name, options);
    }

    /** Prints rows of plain text under the letterhead; name overrides the page's report title. */
    function table(head, body, footer, name, options) {
        options = options || {};
        // Drop the checkbox and action columns: no header text, or "Action".
        var cols = head.map(function (title, i) { return i; }).filter(function (i) { return !SKIP_HEADER.test(head[i]); });
        var numeric = cols.map(function (i) {
            var cells = body.map(function (row) { return row[i]; }).filter(function (value) { return value && value !== '—' && value !== '-'; });
            return cells.length > 0 && cells.every(function (value) { return NUMBER.test(value); });
        });
        // Amounts and quantities get Indian digit grouping; codes, phone numbers and the like stay as typed.
        var grouped = cols.map(function (i, n) { return numeric[n] && !IDENTIFIER.test(head[i]); });
        var cell = function (tag, value, n, span) {
            if (grouped[n] && /^-?\d+(\.\d+)?$/.test(value)) {
                var decimals = (value.split('.')[1] || '').length;
                value = Number(value).toLocaleString('en-IN', {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
            }
            return '<' + tag + (span > 1 ? ' colspan="' + span + '"' : '')
                + (numeric[n] ? ' class="num"' : '') + '>' + esc(value) + '</' + tag + '>';
        };
        var html = '<table class="erp-print-table' + (options.ledger ? ' erp-print-ledger-table' : '') + '"><thead><tr>'
            + cols.map(function (i, n) { return cell('th', head[i], n); }).join('') + '</tr></thead><tbody>';
        html += body.length
            ? body.map(function (row) { return '<tr>' + cols.map(function (i, n) { return cell('td', row[i], n); }).join('') + '</tr>'; }).join('')
            : '<tr><td class="erp-print-empty" colspan="' + cols.length + '">No records for the selected filters.</td></tr>';
        html += '</tbody>';
        var end = '<div class="erp-print-end"><span>' + body.length + ' record' + (body.length === 1 ? '' : 's') + '</span><span>— End of report —</span></div>';
        if (footer && footer.some(Boolean)) {
            html += '<tfoot><tr>';
            for (var n = 0; n < cols.length;) {
                var i = cols[n];
                var footerCell = options.footerCells && options.footerCells[i];
                var span = 1;
                while (footerCell && options.footerCells[cols[n + span]] === footerCell && footer[cols[n + span]] === footer[i]) span++;
                html += cell('td', footer[i], n, span);
                n += span;
            }
            html += '</tr>';
            if (options.signatures) {
                html += '<tr><td class="erp-print-close" colspan="' + cols.length + '">' + end + options.signatures + '</td></tr>';
                end = '';
            }
            html += '</tfoot>';
        }
        html += '</table>' + end;
        printHtml(header(name, options) + (options.before || '') + html, cols.length > 7, name);
    }

    function ledger(dt, scope) {
        var node = dt.table().node();
        var section = node.closest('.report-workspace-view');
        var current = scope === 'current';
        var active = current ? section.querySelector('.btn-filter-pill.active') : null;
        var type = active ? active.dataset.filter : scope;
        var transactionFilter = section.querySelector('select[name="transaction_type"]');
        var option = Array.from(transactionFilter.options).find(function (option) { return option.value === type; });
        var party = node.dataset.erpPrintLedger;
        var partyCard = section.querySelector('.erp-print-party-card');
        var signatures = section.querySelector('.erp-print-signatures');
        var search = current ? dt.search() : '';
        var filtered = (type !== 'all' && type !== 'current') || !!search;
        render(dt, {
            footer: true,
            exportOptions: {
                columns: ':not(.not-exported-col)',
                modifier: {search: current ? 'applied' : 'none', order: current ? 'applied' : 'index', selected: null},
                rows: !current && type !== 'all' ? function (index, data, row) {
                    var rowType = row.dataset.type;
                    return rowType === 'opening' || rowType === type || (type === 'payment' && rowType === 'deposit');
                } : null
            }
        }, {
            ledger: true,
            name: (filtered ? '' : 'Complete ') + party + ' Account Statement / Ledger',
            scope: option ? clean(option.textContent) : 'Current filtered view',
            search: search,
            filteredLedger: filtered,
            before: partyCard ? partyCard.outerHTML : '',
            signatures: signatures ? signatures.outerHTML : ''
        });
    }

    window.ErpPrint = {table: table};

    // Server-side tables only hold one page. Fetch every matching row before opening the print dialog.
    function printDataTable(dt, config) {
        var node = dt.table().node();
        if (pending.has(node)) return;
        var info = dt.page.info();
        var modifier = config.exportOptions && config.exportOptions.modifier;
        var selected = dt.select && typeof dt.select.info === 'function'
            && (modifier == null || modifier.selected !== null) && dt.rows({selected: true}).any();
        if (!info.serverSide || selected || info.recordsDisplay <= dt.rows().count()) {
            render(dt, config);
            return;
        }

        pending.add(node);
        function restore() {
            dt.off('.erpPrint');
            pending.delete(node);
            dt.page.len(info.length).page(info.page).draw('page');
        }
        function failed() {
            window.alert('Could not load all report rows for printing. Please try again.');
        }
        dt.on('xhr.dt.erpPrint', function (event, settings, json) {
            if (json && !json.error && !json.sError) return;
            dt.off('.erpPrint');
            setTimeout(function () {
                restore();
                failed();
            }, 0);
            return true;
        });
        dt.one('draw.dt.erpPrint', function () {
            try {
                if (dt.rows().count() < dt.page.info().recordsDisplay) failed();
                else render(dt, config);
            } finally {
                // The server draw is still rendering. Restore after DataTables finishes it.
                setTimeout(restore, 0);
            }
        });
        dt.page.len(-1).draw('page');
    }

    if (print) print.action = function (event, dt, button, config) { printDataTable(dt, config); };

    $(document).on('click', '[data-erp-print]', function (event) {
        event.preventDefault();
        var target = this.getAttribute('data-erp-print');
        var tables = $.fn.dataTable ? $.fn.dataTable.tables({visible: true}).filter(function (table) {
            return target ? table.matches(target) : table.closest('#content') && !table.closest('.modal');
        }) : [];
        if (tables.length) {
            var dt = $(tables[0]).DataTable();
            if (tables[0].hasAttribute('data-erp-print-ledger')) {
                ledger(dt, this.dataset.erpPrintScope || 'all');
                return;
            }
            var button = dt.buttons ? dt.buttons('.buttons-print') : null;
            if (button && button.length) button.trigger();
            else printDataTable(dt, {footer: true});
            return;
        }
        header();
        window.print();
    });

    window.addEventListener('beforeprint', function () { header(); });
})(jQuery);

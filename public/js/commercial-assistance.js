/* Read-only commercial assistance and source selection on the existing bill form. */
(() => {
    'use strict';
    const $ = window.jQuery, workspace = window.zoloDocumentWorkspace;
    if (!$ || !workspace?.config.shared) return;
    const config = workspace.config, purchase = config.kind === 'purchase';
    const form = document.getElementById(config.kind + '-entry-form');
    if (!form) return;

    const partySelector = purchase ? '#supplier_id' : '#customer_id';
    const partyNameSelector = purchase ? '[name="supplier_id"]' : '[name="customer_id"]';

    const party = () => $(partySelector).val() || $(partyNameSelector).val() || '';
    const partyLabel = () => $(partySelector + ' option:selected').text().trim() || $(partyNameSelector + ' option:selected').text().trim() || '';

    const read = (path, data) => $.getJSON(config.baseUrl + path, data);
    const money = value => '₹ ' + Number(value || 0).toFixed(2);
    const element = (tag, label, className) => {
        const node = document.createElement(tag);
        if (label) node.textContent = label;
        if (className) node.className = className;
        return node;
    };
    const button = (label, click, icon = '') => {
        const node = element('button', '', 'btn btn-sm btn-outline-primary');
        if (icon) {
            const i = element('i', '', 'fa ' + icon + ' mr-1');
            node.append(i);
        }
        node.append(document.createTextNode(label));
        node.type = 'button';
        node.addEventListener('click', click);
        return node;
    };

    let activeRow = null, balanceData = null, balanceRequest = null;

    // Track active row on any interaction (focus, click, or value change)
    $(form).on('focusin click change', '.order-item-row', function () {
        activeRow = this;
    });

    $(document).on('command-center-row-added', (event, tr) => {
        if (tr && tr.length) activeRow = tr[0];
    });

    function getTargetRow() {
        if (activeRow && activeRow.isConnected) return activeRow;
        const focused = $(document.activeElement).closest('tr.order-item-row')[0];
        if (focused && focused.isConnected) return focused;

        // Check for any rows that have a product selected
        const rowsWithProduct = $(form).find('#order-table-body tr.order-item-row').filter(function () {
            return Number($(this).find('.row-product-id').val()) > 0;
        });
        if (rowsWithProduct.length) return rowsWithProduct.last()[0];

        // Fallback to any order-item-row
        const allRows = $(form).find('#order-table-body tr.order-item-row');
        if (allRows.length) return allRows.last()[0];

        return null;
    }

    function notify(message, type = 'info') {
        $('.commercial-aid-toast').remove();
        const toast = $('<div class="commercial-aid-toast toast-' + type + '" role="alert">')
            .html('<i class="fa fa-' + (type === 'warning' ? 'exclamation-circle' : 'info-circle') + '"></i> <span>' + message + '</span>')
            .appendTo(document.body);
        setTimeout(() => toast.fadeOut(300, function () { $(this).remove(); }), 3500);
    }

    function dialog(title) {
        const opener = document.activeElement;
        const node = element('dialog', '', 'commercial-aid-dialog');
        node.setAttribute('aria-label', title);
        node.setAttribute('data-owns-keys', '');

        // Header
        const header = element('div', '', 'commercial-aid-header');
        header.append(element('h3', title));
        const closeBtn = element('button', '×', 'btn-close-dialog');
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close dialog');
        closeBtn.addEventListener('click', () => node.close());
        header.append(closeBtn);
        node.append(header);

        // Body
        const content = element('div', '', 'commercial-aid-body');
        node.append(content);

        // Footer
        const footer = element('div', '', 'commercial-aid-footer');
        const footerClose = button('Close', () => node.close());
        footer.append(footerClose);
        node.append(footer);

        node.addEventListener('close', () => {
            node.remove();
            if (opener && opener.isConnected) opener.focus();
        });
        document.body.append(node);
        node.showModal();
        return { node, content, footer };
    }

    function table(parent, headers) {
        const node = element('table'), head = node.createTHead().insertRow();
        headers.forEach(label => head.append(element('th', label)));
        parent.append(node);
        return node.createTBody();
    }

    const fail = (content, xhr) => {
        content.append(element('p', xhr.responseJSON?.message || 'Unable to load. Close and try again.', 'commercial-aid-error'));
    };

    function history() {
        if (!$(form).is(':visible')) return false;

        const selectedParty = party();
        if (!selectedParty) {
            notify('Please select a ' + (purchase ? 'Supplier' : 'Customer') + ' first to view rate history.', 'warning');
            $(partySelector).focus();
            return false;
        }

        const row = getTargetRow();
        if (!row) {
            notify('Please add an item line to the voucher to view its rate history.', 'info');
            return false;
        }
        activeRow = row;

        const product = $(row).find('.row-product-id').val();
        if (!Number(product)) {
            const rawName = $(row).find('.row-item-name').val() || '';
            notify('Please select or search a product for this row' + (rawName ? ' ("' + rawName + '")' : '') + ' first.', 'warning');
            $(row).find('.row-item-name').focus();
            return false;
        }

        const rawItemName = $(row).find('.row-item-name').val() || 'Selected Item';
        const partyText = partyLabel() || 'Selected Party';
        const { node, content } = dialog((purchase ? 'Purchase Rate History: ' : 'Sale Rate History: ') + rawItemName + ' (' + partyText + ')');
        const status = element('p', 'Loading rate history…', 'text-muted');
        content.append(status);
        const body = table(content, ['Date', 'Document', 'Party', 'Item', 'Quantity', 'Unit', 'Rate', 'Action']);

        const load = page => read('/previous-rates', { party_id: selectedParty, product_id: product, page }).done(result => {
            if (!node.isConnected || selectedParty !== party() || !row.isConnected || $(row).find('.row-product-id').val() !== product) {
                node.close();
                return;
            }
            status.textContent = result.data.length ? '' : 'No posted transaction history found for this party and item.';
            (result.data || []).forEach(line => {
                const tr = body.insertRow();
                [String(line.document_date).slice(0, 10), line.reference_no, line.party_name, line.product_name, line.qty, line.unit_code || 'Base', money(line.rate)]
                    .forEach(value => tr.append(element('td', value)));
                const td = tr.insertCell();
                td.append(button('Use rate', () => {
                    const unit = config.units.find(u => u.unit_name === $(row).find('.row-unit-select').val());
                    const factor = Number(result.unit_factors?.[unit?.id]);
                    if (!(factor > 0) || !(Number(line.base_factor) > 0) || !result.currency || line.currency !== result.currency) {
                        status.textContent = 'Rate unavailable: compatible units and company currency are required.';
                        return;
                    }
                    $(row).find('.row-rate').val((Number(line.rate) / Number(line.base_factor) * factor).toFixed(4)).trigger('input');
                    node.close();
                }, 'fa-check'));
            });
            content.querySelector('[data-more]')?.remove();
            if (result.next_page) {
                const next = button('More history', () => load(result.next_page), 'fa-arrow-down');
                next.dataset.more = '';
                content.append(next);
            }
            if (page === 1) {
                const cost = result.last_purchase;
                const note = element('p', '', 'mt-2 text-muted small');
                note.textContent = !result.cost_visible ? 'Purchase cost requires purchasing access.' : cost
                    ? 'Last Purchase: ' + cost.party_name + ' · ' + String(cost.document_date).slice(0, 10) + ' · ' + money(cost.rate) + ' / ' + (cost.unit_code || 'base unit') : 'No posted purchase history.';
                content.append(note);
                const currentUnit = config.units.find(u => u.unit_name === $(row).find('.row-unit-select').val());
                const factor = Number(result.unit_factors?.[currentUnit?.id]);
                if (!purchase && Number(cost?.rate) > 0 && Number(cost.base_factor) > 0 && factor > 0 && result.currency && cost.currency === result.currency && cost.rate_basis === result.rate_basis) {
                    const normalizedCost = Number(cost.rate) / Number(cost.base_factor) * factor;
                    const margin = Number($(row).find('.row-rate').val()) - normalizedCost;
                    content.append(element('p', 'Margin on net purchase cost (excluding tax): ' + money(margin) + ' / ' + (margin / normalizedCost * 100).toFixed(2) + '%', 'small text-success'));
                } else if (!purchase && cost) {
                    content.append(element('p', 'Margin unavailable: compatible units, currency, net tax basis and positive cost are required.', 'small text-muted'));
                }
            }
        }).fail(xhr => {
            status.remove();
            fail(content, xhr);
        });

        load(1);
        return true;
    }

    function partyItems(statement) {
        if (!$(form).is(':visible')) return false;

        const selectedParty = party();
        if (!selectedParty) {
            notify('Please select a ' + (purchase ? 'Supplier' : 'Customer') + ' first to view ' + (statement ? 'party statement.' : 'pending bills.'), 'warning');
            $(partySelector).focus();
            return false;
        }

        const partyText = partyLabel() || 'Selected Party';
        const { node, content } = dialog((statement ? 'Party Statement: ' : 'Pending Bills: ') + partyText);
        const status = element('p', 'Loading entries…', 'text-muted');
        content.append(status);
        const body = table(content, statement ? ['Date', 'Document', 'Movement', 'Running balance'] : ['Date', 'Document', 'Due Date', 'Original Amount', 'Outstanding Due']);

        const load = page => read('/party/' + selectedParty, { page, ...(statement ? { statement: 1 } : { pending: 1 }) }).done(result => {
            if (!node.isConnected || selectedParty !== party()) {
                node.close();
                return;
            }
            const data = result.data;
            let balance = Number(data.opening_before_page || 0);
            status.textContent = data.balance_kind + ': ' + money(data.outstanding) + (data.items.length ? '' : ' · No open records found.');
            data.items.forEach(item => {
                balance += Number(item.original_amount);
                const row = body.insertRow();
                const values = statement ? [item.document_date, item.document_no, money(item.original_amount), money(balance)]
                    : [item.document_date, item.document_no, item.due_date || '—', money(item.original_amount), money(item.open_amount)];
                values.forEach(value => row.append(element('td', value)));
            });
            content.querySelector('[data-more]')?.remove();
            if (data.next_page) {
                const next = button('More entries', () => load(data.next_page), 'fa-arrow-down');
                next.dataset.more = '';
                content.append(next);
            }
        }).fail(xhr => {
            status.remove();
            fail(content, xhr);
        });

        load(1);
        return true;
    }

    const tools = element('span', '', 'commercial-aid-toolbar');
    tools.append(
        button('Rate history', history, 'fa-history'),
        button('Pending Bills', () => partyItems(false), 'fa-clock-o'),
        button('Party Statement', () => partyItems(true), 'fa-file-text-o')
    );
    document.querySelector('.items-controls-group')?.append(tools);

    const balances = element('div', '', 'commercial-balance');
    balances.id = 'party-balance-breakdown';
    balances.setAttribute('role', 'status');
    form.querySelector('.doc-primary-fields')?.append(balances);

    function paintBalance() {
        if (!balanceData) {
            balances.textContent = '';
            return;
        }
        const method = $('#input-paying-method').val(), net = Number($('#hidden-grand-total').val() || 0), paid = Number($('#hidden-paid-amount').val() || 0);
        const isOrder = Number($('#purchase-status-val').val()) === 4 || (workspace.config.context || {}).document_mode === 'order';
        const current = isOrder || (Number($(form).find('[name="sale_status"]').val()) === 2) ? 0 : net - paid;
        const supported = ['Credit', 'Cash', 'Bank', 'Cheque', 'Credit Card', undefined, null, ''].includes(method);
        balances.textContent = balanceData.balance_kind + ' · Previous ' + money(balanceData.previous)
            + (supported && current >= 0 ? ' · Current ' + money(current) + ' · Total ' + money(Number(balanceData.previous) + current) : ' · Current / Total unavailable for this settlement');
    }

    function balance() {
        balanceRequest?.abort();
        balanceData = null;
        paintBalance();
        if (!party()) return;
        const id = party(), exclude = $(form).find(purchase ? '#edit-purchase-id' : '#edit-sale-id').val();
        balanceRequest = read('/party/' + id, exclude ? { exclude_id: exclude } : {}).done(result => {
            if (id !== party()) return;
            balanceData = result.data;
            paintBalance();
        }).fail(xhr => {
            if (xhr.statusText !== 'abort') balances.textContent = 'Balance unavailable.';
        });
    }

    $(partySelector).on('change', balance);
    $(form).on('input change', paintBalance);
    $(document).on('input change', '#charges-drawer input, #charges-drawer select', () => setTimeout(paintBalance, 0));
    $(document).on('command-center-loaded command-center-reset', () => {
        activeRow = null;
        setTimeout(balance, 0);
    });

    const total = document.getElementById('display-grand-total');
    if (total) new MutationObserver(paintBalance).observe(total, { childList: true, subtree: true, characterData: true });

    function sources() {
        if (!$(form).is(':visible')) return false;

        const selectedParty = party();
        if (!selectedParty) {
            notify('Please select a ' + (purchase ? 'Supplier' : 'Customer') + ' first to load ' + (purchase ? 'GRNs.' : 'Delivery Challans.'), 'warning');
            $(partySelector).focus();
            return false;
        }

        const warehouse = $('#form_warehouse_id').val();
        if (!warehouse) {
            notify('Please select a Warehouse first to load ' + (purchase ? 'GRNs.' : 'Delivery Challans.'), 'warning');
            $('#form_warehouse_id').focus();
            return false;
        }

        const selectedWarehouse = warehouse;
        const partyText = partyLabel() || 'Selected Party';
        const { node, content, footer } = dialog((purchase ? 'Load Supplier GRNs: ' : 'Load Customer DCs: ') + partyText);
        const selected = new Map(), list = element('div');
        content.append(list);

        const load = page => read('/material-sources', { party_id: selectedParty, warehouse_id: selectedWarehouse, page }).done(result => {
            if (!node.isConnected) return;
            if (!result.data.length) list.append(element('p', 'No pending material documents found for this party and warehouse.', 'text-muted'));
            result.data.forEach(source => {
                const label = element('label', '', 'commercial-source-line'), check = document.createElement('input');
                check.type = 'checkbox';
                check.addEventListener('change', () => check.checked ? selected.set(source.id, source) : selected.delete(source.id));
                label.append(check, document.createTextNode(' ' + source.reference_no + ' · ' + source.date + ' · ' + source.items.filter(item => item.qty > 0).map(item => item.name + ' ' + item.qty + ' ' + (item.unit || '')).join(', ')));
                list.append(label);
            });
            content.querySelector('[data-more]')?.remove();
            if (result.next_page) {
                const next = button('More sources', () => load(result.next_page), 'fa-arrow-down');
                next.dataset.more = '';
                content.append(next);
            }
        }).fail(xhr => fail(content, xhr));

        footer.prepend(button('Load selected lines', () => {
            if (!selected.size) {
                alert('Please select at least one material document line.');
                return;
            }
            if (selectedParty !== party() || selectedWarehouse !== $('#form_warehouse_id').val()) {
                node.close();
                return;
            }
            if ($('#order-table-body tr.order-item-row').length && !window.confirm('Replace current item lines with selected material lines?')) return;
            $('#order-table-body tr.order-item-row').remove();
            selected.forEach(source => source.items.filter(item => item.qty > 0).forEach(item => {
                window.commandCenterGrid.addProductRow({ ...item, id: item.product_id, cost: item.rate, price: item.rate, preserve_line: true });
                const r = $('#order-table-body tr.order-item-row').last();
                r.attr({ 'data-material-document-id': source.id, 'data-material-item-id': item.id, 'data-material-base-factor': item.base_factor, 'data-material-unit-id': item.unit_id });
                if (item.variant_id) r.attr('data-variant-id', item.variant_id);
            }));
            $(form).find('[name="delivery_challan_id"], [name="goods_received_note_id"]').remove();
            window.commandCenterGrid.switchWorkspaceMode('voucher');
            window.commandCenterGrid.recalcTableSummary();
            $(form).trigger('change');
            node.close();
        }, 'fa-check'));

        load(1);
        return true;
    }

    const sourceButton = button(purchase ? 'Load GRNs' : 'Load DCs', sources, 'fa-download');
    tools.append(sourceButton);

    $(function () {
        const shortcuts = window.zoloDocumentShortcuts;
        if (!shortcuts) return;
        shortcuts.registerAction('Ctrl+B', 'Pending Bills', () => partyItems(false));
        shortcuts.registerAction('Ctrl+S', purchase ? 'Previous purchase history' : 'Last Sale / previous sale history', history);
        shortcuts.registerAction('Alt+Y', 'Party Statement', () => partyItems(true));
        shortcuts.registerAction('Ctrl+Enter', 'Save / Post document', () => {
            if (!$(form).is(':visible') || $(form).find('[type="submit"]').prop('disabled')) return false;
            form.requestSubmit();
            return true;
        });
    });

    balance();

    const shownBill = Number(new URLSearchParams(location.search).get('show_bill'));
    if (shownBill > 0) {
        const trigger = $('<button type="button" class="btn-side-view" hidden>').attr('data-id', shownBill).appendTo(document.body);
        trigger.trigger('click').remove();
    }
})();

(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const entryWorkspace = $('commercial-entry-workspace');
    const kind = entryWorkspace.dataset.kind;
    const base = entryWorkspace.dataset.base;
    const priceField = kind === 'sale' ? 'net_unit_price' : 'net_unit_cost';
    const unitField = kind === 'sale' ? 'sale_unit_id' : 'purchase_unit_id';
    const quantityStep = String(10 ** -Number(entryWorkspace.dataset.quantityScale || 4));
    let items = [], party = null, key = crypto.randomUUID(), draft = null, busy = false, dirty = false, revision = 0, previewTimer;
    let previewSequence = 0, searchSequence = {parties: 0, products: 0}, lastFocus = null, trackingIndex = null, inlineResource = null, inlineKey = null, inlineBusy = false;
    const text = (node, value) => { if (node) node.textContent = value; };
    const message = (value, error = false) => { text($('status'), value); $('status')?.classList.toggle('error', error); };

    async function api(path, data) {
        const response = await fetch(path === '' && entryWorkspace.dataset.postUrl ? entryWorkspace.dataset.postUrl : base + path, {
            method: data ? 'POST' : 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            ...(data ? {body: JSON.stringify(data)} : {})
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Request failed (${response.status}).`);
        return result.data;
    }

    function payload() {
        const data = {
            warehouse_id: Number($('warehouse')?.value || 1),
            business_date: $('business-date')?.value || new Date().toISOString().slice(0, 10),
            [kind === 'sale' ? 'customer_id' : 'supplier_id']: party?.id,
            items: items.map(({name, code, ...line}) => line),
            paid_amount: Number($('paid')?.value || 0),
            paying_method: $('method')?.value || 'Credit',
            account_id: Number($('account')?.value || 1),
            order_discount: Number($('discount')?.value || 0),
            shipping_cost: Number($('freight')?.value || 0),
            transport_name: $('transport')?.value || '',
            lr_no: $('lr-number')?.value || '',
            lr_number: $('lr-number')?.value || '',
            bale_no: $('bale-no')?.value || '',
            no_of_bales: $('no-of-bales')?.value ? Number($('no-of-bales').value) : null,
            lr_date: $('lr-date')?.value || null,
            station_to: $('station-to')?.value || '',
            order_no: $('order-no')?.value || '',
            credit_days: $('credit-days')?.value ? Number($('credit-days').value) : null,
            [kind === 'sale' ? 'sale_note' : 'note']: $('note')?.value || '',
            idempotency_key: key
        };
        if ($('series')?.value) data.series_id = Number($('series').value);
        if (kind === 'sale' && $('sale-type')?.value) data.sale_type_id = Number($('sale-type').value);
        if (kind === 'purchase' && $('purchase-type')?.value) data.purchase_type_id = Number($('purchase-type').value);
        if ($('agent')?.value) data.agent_id = Number($('agent').value);
        if ($('area')?.value) data.area_id = Number($('area').value);
        if (kind === 'sale' && $('override')?.value) data.credit_override_reason = $('override').value;
        if (entryWorkspace.dataset.projectId) data.project_id = Number(entryWorkspace.dataset.projectId);
        
        for (const [id, field] of [['lr-date','lr_date'],['bale-count','bale_count'],['bundle-count','bundle_count']]) {
            if ($(id)?.value) data[field] = $(id).value;
        }
        if (entryWorkspace.dataset.compliance === '1' && ($('reverse-charge')?.checked || $('place-of-supply')?.value)) {
            data.gst = {reverse_charge: $('reverse-charge')?.checked || false};
            if ($('place-of-supply')?.value) data.gst.place_of_supply = $('place-of-supply').value;
        }
        if (kind === 'purchase') {
            data.status = Number($('receipt-status')?.value || 1);
            data.landed_cost_method = $('landed-method')?.value || 'value';
            data.update_item_cost = !!$('update-cost')?.checked;
            data.update_item_hsn = !!$('update-hsn')?.checked;
            data.goods_receipt_no = $('receipt-number')?.value || '';
            if ($('supplier-invoice-no')?.value) data.supplier_invoice_no = $('supplier-invoice-no').value;
            if ($('supplier-invoice-date')?.value) data.supplier_invoice_date = $('supplier-invoice-date').value;
            if ($('purchase-order')?.value) data.purchase_order_id = Number($('purchase-order').value);
            if (data.status !== 2) data.items.forEach(line => delete line.received_qty);
        }
        return data;
    }

    function updateChargesBadge() {
        let count = 0;
        if (Number($('discount')?.value || 0) > 0) count++;
        if (Number($('freight')?.value || 0) > 0) count++;
        if ($('bill-sundry-select')?.value) count++;
        if ($('bale-no')?.value || $('no-of-bales')?.value) count++;
        if ($('lr-number')?.value || $('transport')?.value) count++;
        if ($('standard-remark')?.value || $('note')?.value) count++;
        text($('charges-count-badge'), String(count));
    }

    async function preview() {
        const sequence = ++previewSequence;
        try {
            const totals = await api('/preview', payload());
            if (sequence !== previewSequence) return;
            const grand = Number(totals.grand_total || 0);
            text($('current'), '₹ ' + grand.toFixed(2));
            text($('summary-grand'), '₹ ' + grand.toFixed(2));
            
            // Calculate taxable net & tax
            let net = items.reduce((acc, it) => acc + (it.qty * it[priceField]), 0);
            let discountVal = Number($('discount')?.value || 0);
            let freightVal = Number($('freight')?.value || 0);
            let taxVal = Math.max(0, grand - (net - discountVal + freightVal));
            text($('summary-net'), '₹ ' + net.toFixed(2));
            text($('summary-tax'), '₹ ' + taxVal.toFixed(2));

            if (party) {
                const combined = (Number(party.outstanding || 0) + grand - Number($('paid')?.value || 0)).toFixed(2);
                text($('combined'), '₹ ' + combined);
            }
            updateChargesBadge();
        } catch (error) {
            if (sequence === previewSequence) message(error.message, true);
        }
    }

    function changed() {
        dirty = true;
        revision++;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(preview, 120);
        updateChargesBadge();
    }

    function showDialog(dialog) {
        lastFocus = document.activeElement;
        dialog.showModal();
    }

    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => {
        button.closest('dialog')?.close();
    }));

    document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('close', () => {
        const target = lastFocus?.isConnected && !lastFocus.disabled && !lastFocus.closest('[inert]')
            && lastFocus.matches('input, select, textarea, button, a[href], [tabindex]') ? lastFocus : $('product-search');
        target?.focus();
    }));

    function info(title, records, columns) {
        text($('info-title'), title);
        const table = document.createElement('table'), head = document.createElement('thead'), heading = document.createElement('tr'), body = document.createElement('tbody');
        columns.forEach(([label]) => { const cell = document.createElement('th'); cell.scope = 'col'; text(cell, label); heading.append(cell); });
        head.append(heading);
        records.forEach(record => {
            const row = document.createElement('tr');
            columns.forEach(([, value]) => { const cell = document.createElement('td'); text(cell, value(record)); row.append(cell); });
            body.append(row);
        });
        table.append(head, body);
        const scroll = document.createElement('div');
        scroll.className = 'table-scroll';
        scroll.append(table);
        $('info-content').replaceChildren(scroll);
        if (!records.length) {
            const empty = document.createElement('p');
            text(empty, 'No records found.');
            $('info-content').append(empty);
        }
        if (!$('info-dialog').open) showDialog($('info-dialog'));
    }

    const statementColumns = [
        ['Bill', row => row.document_no || row.reference_no || `${row.source_type} ${row.source_id}`],
        ['Date', row => String(row.document_date).slice(0, 10)],
        ['Due', row => String(row.due_date).slice(0, 10)],
        ['Open amount', row => Number(row.open_amount).toFixed(4)]
    ];

    async function selectParty(record) {
        party = record;
        $('party-search').value = [record.city, record.name].filter(Boolean).join(' · ');
        $('party-results').replaceChildren();
        text($('tab-draft-party'), record.name || 'New bill');
        try {
            const summary = await api('/party/' + record.id);
            if (party?.id !== record.id) return;
            party.outstanding = summary.outstanding;
            text($('previous'), '₹ ' + Number(summary.outstanding).toFixed(2));
            if (kind === 'sale') {
                const limitText = summary.credit.credit_limit > 0 ? '₹ ' + Number(summary.credit.credit_limit).toFixed(2) : 'Unlimited';
                const availText = summary.credit.available_credit === null ? 'Unlimited' : '₹ ' + Number(summary.credit.available_credit).toFixed(2);
                text($('credit-summary'), `Overdue: ₹ ${Number(summary.credit.overdue).toFixed(2)} · Limit: ${limitText} · Available: ${availText}`);
                text($('header-credit-days'), String(record.credit_days || 30));
                if ($('credit-days')) $('credit-days').value = record.credit_days || 30;
            }
        } catch (error) {
            message(error.message, true);
        }
        changed();
    }

    function focusControl(control) {
        if (!control) return;
        control.focus();
        control.scrollIntoView({block: 'center', behavior: 'smooth'});
    }

    function addProduct(product) {
        items.push({
            product_id: product.id,
            name: product.name,
            code: product.code,
            qty: 1,
            tax_rate: 18,
            [priceField]: Number(kind === 'sale' ? product.price : product.cost),
            ...(product.unit_id ? {[unitField]: Number(product.unit_id)} : {}),
            ...(kind === 'purchase' ? {received_qty: 1} : {})
        });
        $('product-search').value = '';
        $('product-results').replaceChildren();
        render();
        changed();
        focusControl($('item-lines').lastElementChild?.querySelector('input[type="number"]'));
    }

    function renderEmptyPlaceholderRows(tbody) {
        for (let i = 1; i <= 6; i++) {
            const tr = document.createElement('tr');
            tr.className = 'placeholder-row';
            tr.innerHTML = `
                <td style="color:#94a3b8; font-weight:600;">${i}</td>
                <td><span style="color:#94a3b8; font-style:italic; cursor:pointer;" class="btn-focus-search">Select Item...</span></td>
                <td><span class="grid-type-pill" style="opacity:0.6;">Service</span></td>
                <td style="color:#94a3b8;">—</td>
                <td style="color:#94a3b8;">1</td>
                <td style="color:#94a3b8;">₹ 0.00</td>
                <td style="color:#94a3b8;">₹ 0.00</td>
                <td style="color:#94a3b8;">₹ 0.00</td>
                <td style="color:#94a3b8;">— ▾</td>
                <td style="color:#94a3b8;">₹ 0.00</td>
                <td style="color:#94a3b8; font-weight:600;">₹ 0.00</td>
                <td style="text-align:center; color:#cbd5e1;">—</td>
            `;
            tr.addEventListener('click', () => $('product-search')?.focus());
            tbody.append(tr);
        }
    }

    function render() {
        const tbody = $('item-lines');
        if (!tbody) return;
        tbody.replaceChildren();

        text($('items-meta-count'), `${items.length} line(s) • 7 per page`);

        if (items.length === 0) {
            $('empty-lines').hidden = false;
            renderEmptyPlaceholderRows(tbody);
            return;
        }

        $('empty-lines').hidden = true;

        items.forEach((item, index) => {
            const row = document.createElement('tr');

            // Col 1: S.NO
            const snoTd = document.createElement('td');
            snoTd.style.fontWeight = '700';
            snoTd.style.color = '#64748b';
            text(snoTd, String(index + 1));
            row.append(snoTd);

            // Col 2: ITEM
            const itemTd = document.createElement('td');
            itemTd.className = 'grid-item-cell';
            const nameSpan = document.createElement('span');
            nameSpan.className = 'grid-item-name';
            text(nameSpan, item.name);
            const codeSmall = document.createElement('small');
            codeSmall.className = 'grid-item-code';
            text(codeSmall, item.code || '');
            itemTd.append(nameSpan, codeSmall);
            row.append(itemTd);

            // Col 3: SALES / PURCHASE TYPE
            const typeTd = document.createElement('td');
            const typePill = document.createElement('span');
            typePill.className = 'grid-type-pill';
            text(typePill, kind === 'sale' ? ($('sale-type')?.selectedOptions[0]?.text || 'Local GST') : ($('purchase-type')?.selectedOptions[0]?.text || 'Inward GST'));
            typeTd.append(typePill);
            row.append(typeTd);

            // Col 4: UNIT
            const unitTd = document.createElement('td');
            if ($('line-unit-options')) {
                const unitSelect = $('line-unit-options').content.firstElementChild.cloneNode(true);
                unitSelect.setAttribute('aria-label', 'Unit for ' + item.name);
                unitSelect.value = item[unitField] || '';
                unitSelect.addEventListener('change', () => {
                    item[unitField] = Number(unitSelect.value);
                    changed();
                });
                unitTd.append(unitSelect);
            }
            row.append(unitTd);

            // Col 5: QTY
            const qtyTd = document.createElement('td');
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.min = quantityStep;
            qtyInput.step = quantityStep;
            qtyInput.value = item.qty ?? 1;
            qtyInput.addEventListener('input', () => {
                item.qty = Number(qtyInput.value);
                updateLineCalculations(row, item);
                changed();
            });
            qtyTd.append(qtyInput);
            row.append(qtyTd);

            // Col 6: RATE + TAX (Inclusive)
            const rateTaxTd = document.createElement('td');
            const rateTaxInput = document.createElement('input');
            rateTaxInput.type = 'number';
            rateTaxInput.step = '0.01';
            const curRate = Number(item[priceField] || 0);
            const curTax = Number(item.tax_rate || 18);
            rateTaxInput.value = (curRate * (1 + curTax / 100)).toFixed(2);
            rateTaxInput.addEventListener('input', () => {
                const inc = Number(rateTaxInput.value || 0);
                item[priceField] = inc / (1 + curTax / 100);
                rateInput.value = item[priceField].toFixed(2);
                updateLineCalculations(row, item);
                changed();
            });
            rateTaxTd.append(rateTaxInput);
            row.append(rateTaxTd);

            // Col 7: RATE (Exclusive)
            const rateTd = document.createElement('td');
            const rateInput = document.createElement('input');
            rateInput.type = 'number';
            rateInput.step = '0.0001';
            rateInput.value = Number(item[priceField] || 0).toFixed(2);
            rateInput.addEventListener('input', () => {
                item[priceField] = Number(rateInput.value);
                rateTaxInput.value = (item[priceField] * (1 + curTax / 100)).toFixed(2);
                updateLineCalculations(row, item);
                changed();
            });
            rateTd.append(rateInput);
            row.append(rateTd);

            // Col 8: TAXABLE AMOUNT
            const taxableTd = document.createElement('td');
            taxableTd.className = 'cell-taxable';
            const taxable = (item.qty * item[priceField]);
            text(taxableTd, '₹ ' + taxable.toFixed(2));
            row.append(taxableTd);

            // Col 9: GST / IGST %
            const taxRateTd = document.createElement('td');
            const taxSelect = document.createElement('select');
            [0, 5, 12, 18, 28].forEach(pct => {
                const opt = new Option(pct + '%', pct);
                if (pct === curTax) opt.selected = true;
                taxSelect.append(opt);
            });
            taxSelect.addEventListener('change', () => {
                item.tax_rate = Number(taxSelect.value);
                rateTaxInput.value = (item[priceField] * (1 + item.tax_rate / 100)).toFixed(2);
                updateLineCalculations(row, item);
                changed();
            });
            taxRateTd.append(taxSelect);
            row.append(taxRateTd);

            // Col 10: TAX AMOUNT
            const taxAmtTd = document.createElement('td');
            taxAmtTd.className = 'cell-tax-amt';
            const taxAmt = taxable * (curTax / 100);
            text(taxAmtTd, '₹ ' + taxAmt.toFixed(2));
            row.append(taxAmtTd);

            // Col 11: LINE TOTAL
            const lineTotalTd = document.createElement('td');
            lineTotalTd.className = 'cell-line-total';
            lineTotalTd.style.fontWeight = '700';
            lineTotalTd.style.color = '#7c3aed';
            text(lineTotalTd, '₹ ' + (taxable + taxAmt).toFixed(2));
            row.append(lineTotalTd);

            // Col 12: ACTIONS
            const actionTd = document.createElement('td');
            actionTd.className = 'grid-action-btns';

            const trackBtn = document.createElement('button');
            trackBtn.type = 'button';
            trackBtn.className = 'btn-grid-action';
            trackBtn.title = 'Stock Tracking & Batch (Ctrl+T)';
            trackBtn.textContent = '⚙';
            trackBtn.addEventListener('click', () => openTracking(index));

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'btn-grid-action danger';
            delBtn.title = 'Remove Item Row';
            delBtn.textContent = '🗑';
            delBtn.addEventListener('click', () => {
                items.splice(index, 1);
                render();
                changed();
                $('product-search')?.focus();
            });

            actionTd.append(trackBtn, delBtn);
            row.append(actionTd);

            tbody.append(row);
        });
    }

    function updateLineCalculations(row, item) {
        const curTax = Number(item.tax_rate || 18);
        const taxable = (item.qty * item[priceField]);
        const taxAmt = taxable * (curTax / 100);
        const lineTotal = taxable + taxAmt;

        const cellTaxable = row.querySelector('.cell-taxable');
        const cellTaxAmt = row.querySelector('.cell-tax-amt');
        const cellLineTotal = row.querySelector('.cell-line-total');

        if (cellTaxable) text(cellTaxable, '₹ ' + taxable.toFixed(2));
        if (cellTaxAmt) text(cellTaxAmt, '₹ ' + taxAmt.toFixed(2));
        if (cellLineTotal) text(cellLineTotal, '₹ ' + lineTotal.toFixed(2));
    }

    function wireSearch(resource, inputId, resultId, select) {
        let timer;
        async function search() {
            const sequence = ++searchSequence[resource];
            try {
                const results = await api('/search/' + resource + '?q=' + encodeURIComponent($(inputId).value));
                if (sequence !== searchSequence[resource]) return;
                $(resultId).replaceChildren();
                results.forEach(record => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    text(button, [record.code || record.city, record.name].filter(Boolean).join(' · '));
                    button.addEventListener('click', () => {
                        clearTimeout(timer);
                        ++searchSequence[resource];
                        select(record);
                    });
                    $(resultId).append(button);
                });
            } catch (error) {
                message(error.message, true);
            }
        }
        $(inputId)?.addEventListener('input', () => {
            clearTimeout(timer);
            ++searchSequence[resource];
            $(resultId).replaceChildren();
            timer = setTimeout(search, 150);
        });
        $(inputId)?.addEventListener('keydown', async event => {
            if ((event.key === 'Enter' && !event.ctrlKey) || event.key === 'ArrowDown') {
                event.preventDefault();
                clearTimeout(timer);
                await search();
                const first = $(resultId).querySelector('button');
                if (first) { event.key === 'Enter' ? first.click() : first.focus(); }
            }
        });
        $(resultId)?.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                (event.key === 'ArrowDown' ? event.target.nextElementSibling : event.target.previousElementSibling)?.focus();
            }
        });
    }

    wireSearch('parties', 'party-search', 'party-results', selectParty);
    wireSearch('products', 'product-search', 'product-results', addProduct);

    $('party-search')?.addEventListener('input', () => {
        party = null;
        text($('previous'), '—');
        text($('combined'), '—');
        text($('tab-draft-party'), 'New bill');
        changed();
    });

    async function saveDraft() {
        if (busy || !dirty) return;
        busy = true;
        const savedRevision = revision;
        try {
            draft = await api('/drafts', {
                id: draft?.id,
                version: draft?.version || 0,
                payload: {...payload(), items, party}
            });
            dirty = revision !== savedRevision;
            message(dirty ? 'Draft saved; newer edits pending' : 'Draft saved · #' + draft.id);
            refreshSideBillList();
        } catch (error) {
            message('Draft save failed: ' + error.message, true);
        } finally {
            busy = false;
        }
    }

    $('save-draft')?.addEventListener('click', saveDraft);
    $('btn-footer-save-draft')?.addEventListener('click', saveDraft);
    setInterval(saveDraft, 12000);

    async function loadPayload(data) {
        items = data.items || [];
        party = data.party || null;
        if ($('warehouse')) $('warehouse').value = data.warehouse_id || $('warehouse').value;
        if ($('business-date')) $('business-date').value = data.business_date || $('business-date').value;
        for (const [id, field] of [['paid','paid_amount'],['freight','shipping_cost'],['discount','order_discount'],['transport','transport_name'],['lr-number','lr_number']]) {
            if ($(id)) $(id).value = data[field] ?? (['paid','freight','discount'].includes(id) ? 0 : '');
        }
        if ($('note')) $('note').value = data[kind === 'sale' ? 'sale_note' : 'note'] || '';
        key = data.idempotency_key || crypto.randomUUID();
        if ($('method')) $('method').value = data.paying_method || 'Credit';
        if ($('account')) $('account').value = data.account_id || $('account').value;
        if (data.series_id && $('series')) $('series').value = data.series_id;
        if (kind === 'sale' && data.sale_type_id && $('sale-type')) $('sale-type').value = data.sale_type_id;
        if (kind === 'purchase' && data.purchase_type_id && $('purchase-type')) $('purchase-type').value = data.purchase_type_id;
        if (data.agent_id && $('agent')) $('agent').value = data.agent_id;
        if (data.area_id && $('area')) $('area').value = data.area_id;

        if (party) {
            await selectParty(party);
        } else if (data[kind === 'sale' ? 'customer_id' : 'supplier_id']) {
            const result = await api('/party/' + data[kind === 'sale' ? 'customer_id' : 'supplier_id']);
            if (result.party) await selectParty(result.party);
        }
        render();
        changed();
        updateSeriesPreview();
    }

    // Side panel bill list click loader
    async function refreshSideBillList() {
        try {
            const drafts = await api('/drafts');
            if (drafts && drafts.length && $('side-bill-list')) {
                const container = $('side-bill-list');
                const fragment = document.createDocumentFragment();
                drafts.forEach(d => {
                    const card = document.createElement('div');
                    card.className = 'side-bill-card';
                    card.innerHTML = `
                        <div class="side-card-top">
                            <strong class="side-card-ref">Draft #${d.id}</strong>
                            <span class="side-card-amount">Draft</span>
                        </div>
                        <div class="side-card-party">${d.payload_json ? (JSON.parse(d.payload_json).party?.name || 'In-Progress Bill') : 'In-Progress Bill'}</div>
                        <div class="side-card-bottom">
                            <span class="side-card-date">${String(d.updated_at).substring(0, 10)}</span>
                            <span class="side-card-status pending">Draft</span>
                        </div>
                    `;
                    card.addEventListener('click', async () => {
                        draft = d;
                        await loadPayload(JSON.parse(d.payload_json));
                    });
                    fragment.append(card);
                });
                container.prepend(fragment);
            }
        } catch (e) {}
    }

    // Side Panel Docking & Collapsing Logic
    const sidePanel = $('side-panel');
    const workspace = $('desk-workspace');
    const dockBtn = $('btn-dock-toggle');
    const dockLabel = $('dock-label');

    const savedDock = localStorage.getItem('zolo_panel_dock') || 'left';
    if (savedDock === 'right') {
        workspace?.classList.remove('panel-dock-left');
        workspace?.classList.add('panel-dock-right');
        if (dockLabel) dockLabel.textContent = 'Dock Left';
    } else {
        workspace?.classList.remove('panel-dock-right');
        workspace?.classList.add('panel-dock-left');
        if (dockLabel) dockLabel.textContent = 'Dock Right';
    }

    const savedOpen = localStorage.getItem('zolo_panel_open');
    if (savedOpen === 'false') {
        sidePanel?.classList.add('collapsed');
    }

    dockBtn?.addEventListener('click', () => {
        const isCurrentlyLeft = workspace.classList.contains('panel-dock-left');
        if (isCurrentlyLeft) {
            workspace.classList.remove('panel-dock-left');
            workspace.classList.add('panel-dock-right');
            if (dockLabel) dockLabel.textContent = 'Dock Left';
            localStorage.setItem('zolo_panel_dock', 'right');
        } else {
            workspace.classList.remove('panel-dock-right');
            workspace.classList.add('panel-dock-left');
            if (dockLabel) dockLabel.textContent = 'Dock Right';
            localStorage.setItem('zolo_panel_dock', 'left');
        }
    });

    function toggleSidePanel() {
        if (!sidePanel) return;
        const isCollapsed = sidePanel.classList.toggle('collapsed');
        localStorage.setItem('zolo_panel_open', isCollapsed ? 'false' : 'true');
    }

    $('btn-side-close')?.addEventListener('click', () => {
        sidePanel?.classList.add('collapsed');
        localStorage.setItem('zolo_panel_open', 'false');
    });

    $('btn-toggle-bill-list')?.addEventListener('click', toggleSidePanel);
    $('btn-browse-bills')?.addEventListener('click', () => {
        if (sidePanel?.classList.contains('collapsed')) {
            toggleSidePanel();
        }
        $('side-search-input')?.focus();
    });

    // Cash / Credit Mode Toggles
    $('pill-mode-cash')?.addEventListener('click', () => {
        $('pill-mode-cash')?.classList.add('active');
        $('pill-mode-credit')?.classList.remove('active');
        if ($('method')) $('method').value = 'Cash';
        changed();
    });

    $('pill-mode-credit')?.addEventListener('click', () => {
        $('pill-mode-credit')?.classList.add('active');
        $('pill-mode-cash')?.classList.remove('active');
        if ($('method')) $('method').value = 'Credit';
        changed();
    });

    // Table Density Switcher
    document.querySelectorAll('.density-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.density-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const density = btn.dataset.density;
            const table = $('grid-table');
            if (table) {
                table.classList.remove('compact', 'cozy', 'large');
                table.classList.add(density);
            }
        });
    });

    // Add item row button
    $('btn-add-item-row')?.addEventListener('click', () => {
        $('product-search')?.focus();
    });

    // Series preview updater
    function updateSeriesPreview() {
        const seriesSelect = $('series');
        const previewInput = $('bill-number-preview');
        if (seriesSelect && previewInput) {
            const opt = seriesSelect.selectedOptions[0];
            if (opt && opt.dataset.prefix) {
                previewInput.value = (opt.dataset.prefix || '') + (opt.dataset.next || '1');
            }
        }
    }
    $('series')?.addEventListener('change', updateSeriesPreview);
    updateSeriesPreview();

    // Charges & Remarks Drawer
    const chargesDrawer = $('charges-drawer');
    function openChargesDrawer() {
        if (!chargesDrawer) return;
        // Sync values to drawer inputs
        if ($('drawer-discount')) $('drawer-discount').value = $('discount')?.value || 0;
        if ($('drawer-freight')) $('drawer-freight').value = $('freight')?.value || 0;
        if ($('drawer-sundry-select')) $('drawer-sundry-select').value = $('bill-sundry-select')?.value || '';
        if ($('drawer-sundry-amount')) $('drawer-sundry-amount').value = $('sundry-amount')?.value || 0;
        if ($('drawer-bale-no')) $('drawer-bale-no').value = $('bale-no')?.value || '';
        if ($('drawer-no-of-bales')) $('drawer-no-of-bales').value = $('no-of-bales')?.value || '';
        if ($('drawer-lr-number')) $('drawer-lr-number').value = $('lr-number')?.value || '';
        if ($('drawer-lr-date')) $('drawer-lr-date').value = $('lr-date')?.value || '';
        if ($('drawer-transport')) $('drawer-transport').value = $('transport')?.value || '';
        if ($('drawer-station-to')) $('drawer-station-to').value = $('station-to')?.value || '';
        if ($('drawer-order-no')) $('drawer-order-no').value = $('order-no')?.value || '';
        if ($('drawer-credit-days')) $('drawer-credit-days').value = $('credit-days')?.value || '';
        if ($('drawer-note')) $('drawer-note').value = $('note')?.value || '';
        if ($('drawer-standard-remark')) $('drawer-standard-remark').value = $('standard-remark')?.value || '';
        if ($('drawer-paid')) $('drawer-paid').value = $('paid')?.value || 0;
        if ($('drawer-method')) $('drawer-method').value = $('method')?.value || 'Credit';
        if ($('drawer-account')) $('drawer-account').value = $('account')?.value || 1;
        showDialog(chargesDrawer);
    }

    $('btn-open-charges-drawer')?.addEventListener('click', openChargesDrawer);
    $('btn-open-details')?.addEventListener('click', openChargesDrawer);

    chargesDrawer?.addEventListener('close', () => {
        // Sync back to main form inputs
        if ($('discount') && $('drawer-discount')) $('discount').value = $('drawer-discount').value;
        if ($('freight') && $('drawer-freight')) $('freight').value = $('drawer-freight').value;
        if ($('bill-sundry-select') && $('drawer-sundry-select')) $('bill-sundry-select').value = $('drawer-sundry-select').value;
        if ($('sundry-amount') && $('drawer-sundry-amount')) $('sundry-amount').value = $('drawer-sundry-amount').value;
        if ($('bale-no') && $('drawer-bale-no')) $('bale-no').value = $('drawer-bale-no').value;
        if ($('no-of-bales') && $('drawer-no-of-bales')) $('no-of-bales').value = $('drawer-no-of-bales').value;
        if ($('lr-number') && $('drawer-lr-number')) $('lr-number').value = $('drawer-lr-number').value;
        if ($('lr-date') && $('drawer-lr-date')) $('lr-date').value = $('drawer-lr-date').value;
        if ($('transport') && $('drawer-transport')) $('transport').value = $('drawer-transport').value;
        if ($('station-to') && $('drawer-station-to')) $('station-to').value = $('drawer-station-to').value;
        if ($('order-no') && $('drawer-order-no')) $('order-no').value = $('drawer-order-no').value;
        if ($('credit-days') && $('drawer-credit-days')) $('credit-days').value = $('drawer-credit-days').value;
        if ($('note') && $('drawer-note')) $('note').value = $('drawer-note').value;
        if ($('standard-remark') && $('drawer-standard-remark')) $('standard-remark').value = $('drawer-standard-remark').value;
        if ($('paid') && $('drawer-paid')) $('paid').value = $('drawer-paid').value;
        if ($('method') && $('drawer-method')) $('method').value = $('drawer-method').value;
        if ($('account') && $('drawer-account')) $('account').value = $('drawer-account').value;
        changed();
    });

    // Drawer tabs
    document.querySelectorAll('.drawer-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.drawer-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.drawer-tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const target = $('dtab-' + btn.dataset.dtab);
            if (target) target.classList.add('active');
        });
    });

    // Footer Actions
    $('btn-discard-bill')?.addEventListener('click', () => {
        if (confirm('Discard current document and reset?')) {
            items = [];
            party = null;
            key = crypto.randomUUID();
            draft = null;
            if ($('party-search')) $('party-search').value = '';
            text($('previous'), '—');
            text($('combined'), '—');
            text($('tab-draft-party'), 'New bill');
            render();
            changed();
            $('party-search')?.focus();
        }
    });

    $('btn-new-bill')?.addEventListener('click', () => {
        items = [];
        party = null;
        key = crypto.randomUUID();
        draft = null;
        if ($('party-search')) $('party-search').value = '';
        text($('previous'), '—');
        text($('combined'), '—');
        text($('tab-draft-party'), 'New bill');
        render();
        changed();
        $('party-search')?.focus();
    });

    $('tab-add-new')?.addEventListener('click', () => {
        $('btn-new-bill')?.click();
    });

    $('btn-footer-post')?.addEventListener('click', () => {
        $('entry-form')?.requestSubmit();
    });

    $('btn-footer-review')?.addEventListener('click', () => {
        preview();
        message('Preview totals refreshed.');
    });

    // Master Inline Creation
    function openInline(resource) {
        inlineResource = resource;
        inlineKey = crypto.randomUUID();
        $('inline-form').reset();
        const titles = {
            products: 'New item',
            parties: kind === 'sale' ? 'New customer' : 'New supplier',
            agents: 'New agent / broker',
            areas: 'New area / route',
            'bill-sundries': 'New bill sundry',
            'sale-types': 'New sale type',
            'purchase-types': 'New purchase type',
            remarks: 'New predefined remark',
            series: 'New voucher series'
        };
        text($('inline-title'), titles[resource] || 'New master');
        ['party', 'product', 'agent', 'area', 'sundry', 'sale-type', 'purchase-type', 'remark', 'series'].forEach(s => {
            const el = $(s + '-fields');
            if (el) el.hidden = true;
        });
        const activeSection = {
            parties: 'party',
            products: 'product',
            agents: 'agent',
            areas: 'area',
            'bill-sundries': 'sundry',
            'sale-types': 'sale-type',
            'purchase-types': 'purchase-type',
            remarks: 'remark',
            series: 'series'
        }[resource];
        if (activeSection && $(activeSection + '-fields')) {
            $(activeSection + '-fields').hidden = false;
        }
        $('master-code').required = resource === 'products';
        text($('inline-error'), '');
        showDialog($('inline-dialog'));
        $('master-name')?.focus();
    }

    document.querySelectorAll('[data-inline]').forEach(button => button.addEventListener('click', () => openInline(button.dataset.inline)));

    $('inline-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        if (inlineBusy) return;
        inlineBusy = true;
        const data = {...Object.fromEntries(new FormData(event.target)), idempotency_key: inlineKey};
        try {
            const record = await api('/masters/' + inlineResource, data);
            $('inline-dialog').close();
            if (inlineResource === 'products') {
                addProduct(record);
                lastFocus = document.activeElement;
            } else if (inlineResource === 'parties') {
                selectParty(record);
                lastFocus = $('product-search');
            } else if (inlineResource === 'agents') {
                const opt = new Option(record.name + (record.commission_rate ? ` (${record.commission_rate}%)` : ''), record.id, true, true);
                $('agent')?.append(opt);
                changed();
            } else if (inlineResource === 'areas') {
                const opt = new Option(record.name, record.id, true, true);
                $('area')?.append(opt);
                changed();
            } else if (inlineResource === 'sale-types') {
                const opt = new Option(record.name, record.id, true, true);
                $('sale-type')?.append(opt);
                changed();
            } else if (inlineResource === 'purchase-types') {
                const opt = new Option(record.name, record.id, true, true);
                $('purchase-type')?.append(opt);
                changed();
            } else if (inlineResource === 'bill-sundries') {
                const opt = new Option(record.name + ` (${record.calculation_type})`, record.id, true, true);
                opt.dataset.calc = record.calculation_type;
                opt.dataset.val = record.default_value;
                $('bill-sundry-select')?.append(opt);
                $('bill-sundry-select')?.dispatchEvent(new Event('change'));
            } else if (inlineResource === 'remarks') {
                const opt = new Option(record.title, record.remark, true, true);
                $('standard-remark')?.append(opt);
                if ($('note')) $('note').value = record.remark;
                changed();
            } else if (inlineResource === 'series') {
                const opt = new Option(record.code + (record.prefix ? ` (${record.prefix}${record.next_number})` : ''), record.id, true, true);
                opt.dataset.prefix = record.prefix;
                opt.dataset.next = record.next_number;
                $('series')?.append(opt);
                updateSeriesPreview();
                changed();
            }
        } catch (error) {
            text($('inline-error'), error.message);
        } finally {
            inlineBusy = false;
        }
    });

    if ($('bill-sundry-select')) {
        $('bill-sundry-select').addEventListener('change', () => {
            const opt = $('bill-sundry-select').selectedOptions[0];
            if (opt && opt.value) {
                const defVal = Number(opt.dataset.val || 0);
                if ($('sundry-amount')) $('sundry-amount').value = defVal;
                if (opt.textContent.toLowerCase().includes('discount')) {
                    if (opt.dataset.calc === 'percentage') {
                        const subtotal = items.reduce((acc, it) => acc + (it.qty * it[priceField]), 0);
                        if ($('discount')) $('discount').value = (subtotal * (defVal / 100)).toFixed(4);
                    } else {
                        if ($('discount')) $('discount').value = defVal;
                    }
                } else if (opt.textContent.toLowerCase().includes('freight') || opt.textContent.toLowerCase().includes('transport') || opt.textContent.toLowerCase().includes('loading')) {
                    if ($('freight')) $('freight').value = defVal;
                }
                changed();
            }
        });
    }

    if ($('standard-remark')) {
        $('standard-remark').addEventListener('change', () => {
            if ($('standard-remark').value && $('note')) {
                $('note').value = $('standard-remark').value;
                changed();
            }
        });
    }

    function openTracking(index) {
        trackingIndex = index;
        const item = items[index];
        if (!item) return;
        if ($('serials')) $('serials').value = (item.serials || []).join(',');
        if ($('batch-id')) $('batch-id').value = item.product_batch_id || '';
        if ($('identity')) $('identity').value = item.stock_identity_id || '';
        if ($('variant')) $('variant').value = item.variant_id || '';
        if ($('quantity-scheme')) {
            [...$('quantity-scheme').options].forEach(option => {
                option.hidden = !!option.dataset.product && Number(option.dataset.product) !== item.product_id;
            });
            $('quantity-scheme').value = item.quantity_scheme_id || '';
        }
        if ($('piece-select')) $('piece-select').replaceChildren(new Option('Find pieces first', ''));
        if (kind === 'purchase') {
            if ($('batch-no')) $('batch-no').value = item.batch?.batch_no || '';
            if ($('expiry')) $('expiry').value = item.batch?.expired_date || '';
            if ($('mfg-date')) $('mfg-date').value = item.batch?.mfg_date || '';
            if ($('batch-mrp')) $('batch-mrp').value = item.batch?.mrp ?? '';
            if ($('hsn-code')) $('hsn-code').value = item.hsn_code || '';
            if ($('weight')) $('weight').value = item.weight || '';
            if ($('manual-freight')) $('manual-freight').value = item.landed_cost || '';
        }
        showDialog($('tracking-dialog'));
    }

    $('tracking-form')?.addEventListener('submit', event => {
        event.preventDefault();
        try {
            const item = items[trackingIndex];
            if (item) {
                item.serials = $('serials')?.value ? $('serials').value.split(',').map(v => v.trim()).filter(Boolean) : [];
                if ($('batch-id')?.value) item.product_batch_id = Number($('batch-id').value);
                if ($('identity')?.value) item.stock_identity_id = Number($('identity').value);
                if ($('variant')?.value) item.variant_id = Number($('variant').value);
            }
            $('tracking-dialog')?.close();
            changed();
        } catch (error) {
            message(error.message, true);
        }
    });

    $('entry-form')?.addEventListener('change', changed);

    $('entry-form')?.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        if (!party || !items.length) return message('Select a party and add at least one item.', true);
        busy = true;
        if ($('post')) $('post').disabled = true;
        if ($('btn-footer-post')) $('btn-footer-post').disabled = true;
        $('entry-form').inert = true;
        try {
            const data = payload();
            if (draft) data.draft_id = draft.id;
            const result = await api('', data);
            dirty = false;
            draft = null;
            message('Saved ' + result.reference_no);
            const button = document.createElement('button');
            button.className = 'primary';
            text(button, 'Print invoice');
            button.addEventListener('click', () => window.open(
                entryWorkspace.dataset.compliance === '1'
                    ? '/compliance/documents/' + kind + '/' + (result.replacement_sale_id || result.id)
                    : (kind === 'sale' ? '/sales/gen_invoice/' + result.id : '/purchases/' + result.id),
                '_blank', 'noopener'
            ));
            text($('info-title'), result.posted_at ? 'Invoice posted successfully' : 'Document saved');
            $('info-content').replaceChildren(button);
            showDialog($('info-dialog'));
            items = [];
            key = crypto.randomUUID();
            render();
            if ($('paid')) $('paid').value = '0';
            const summary = await api('/party/' + party.id);
            party.outstanding = summary.outstanding;
            text($('previous'), '₹ ' + Number(summary.outstanding).toFixed(2));
            await preview();
            refreshSideBillList();
        } catch (error) {
            message(error.message, true);
        } finally {
            busy = false;
            if ($('post')) $('post').disabled = false;
            if ($('btn-footer-post')) $('btn-footer-post').disabled = false;
            $('entry-form').inert = false;
        }
    });

    // Keyboard Shortcuts
    document.addEventListener('keydown', event => {
        const input = /INPUT|TEXTAREA|SELECT/.test(event.target.tagName);
        if (document.querySelector('dialog[open]')) return;
        if (event.key === 'F6') {
            event.preventDefault();
            openInline('products');
        } else if (event.altKey && event.key.toLowerCase() === 'c') {
            event.preventDefault();
            openInline('parties');
        } else if (event.altKey && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            if (party) {
                api('/party/' + party.id + '?page=1&pending=0').then(res => {
                    info('Party Statement', res.items || [], statementColumns);
                });
            } else {
                message('Select a party first.', true);
            }
        } else if (event.ctrlKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (party && items.length) {
                api('/previous-rates?party_id=' + party.id + '&product_id=' + items[items.length - 1].product_id).then(res => {
                    const rows = [];
                    if (res.last_sale) rows.push({type: 'Last Sale Bill', ref: res.last_sale.reference_no, rate: res.last_sale.rate, qty: res.last_sale.qty});
                    if (res.last_purchase) rows.push({type: 'Last Purchase Inward', ref: res.last_purchase.reference_no, rate: res.last_purchase.cost, qty: res.last_purchase.qty});
                    info('Rate History', rows, [
                        ['Type', r => r.type],
                        ['Bill #', r => r.ref || '—'],
                        ['Rate / Cost', r => Number(r.rate).toFixed(2)],
                        ['Qty', r => r.qty || '—']
                    ]);
                });
            } else {
                message('Select a party and item first.', true);
            }
        } else if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault();
            $('entry-form')?.requestSubmit();
        } else if (event.key === ' ' && !input) {
            event.preventDefault();
            $('party-search')?.focus();
        } else if (event.key === 'Escape') {
            $('party-results')?.replaceChildren();
            $('product-results')?.replaceChildren();
            $('product-search')?.focus();
        }
    });

    $('btn-show-shortcuts')?.addEventListener('click', () => {
        info('Keyboard Shortcuts', [
            {key: 'Space', desc: 'Jump to Party Search'},
            {key: 'F2', desc: 'Sales Command Center: Fast Entry'},
            {key: 'F12', desc: 'Purchase Command Center: Fast Entry'},
            {key: 'F6', desc: 'Create New Item Inline'},
            {key: 'Alt + C', desc: 'Create New Customer / Supplier Inline'},
            {key: 'Alt + Y', desc: 'View Party Statement'},
            {key: 'Ctrl + S', desc: 'Rate History Lookup (Party + Item)'},
            {key: 'Ctrl + Enter', desc: 'Post / Save Invoice'}
        ], [
            ['Shortcut', r => r.key],
            ['Action Description', r => r.desc]
        ]);
    });

    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });

    render();
    $('party-search')?.focus();
    refreshSideBillList();

    if (entryWorkspace.dataset.projectCustomer) {
        api('/party/' + entryWorkspace.dataset.projectCustomer)
            .then(result => selectParty(result.party)).catch(error => message(error.message, true));
    }
})();

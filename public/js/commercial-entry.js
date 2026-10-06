(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const kind = document.body.dataset.kind;
    const base = document.body.dataset.base;
    const priceField = kind === 'sale' ? 'net_unit_price' : 'net_unit_cost';
    const unitField = kind === 'sale' ? 'sale_unit_id' : 'purchase_unit_id';
    const quantityStep = String(10 ** -Number(document.body.dataset.quantityScale || 4));
    let items = [], party = null, key = crypto.randomUUID(), draft = null, busy = false, dirty = false, revision = 0, previewTimer;
    let previewSequence = 0, searchSequence = {parties: 0, products: 0}, lastFocus = null, trackingIndex = null, inlineResource = null, inlineKey = null, inlineBusy = false;
    const text = (node, value) => { node.textContent = value; };
    const message = (value, error = false) => { text($('status'), value); $('status').classList.toggle('error', error); };
    async function api(path, data) {
        const response = await fetch(path === '' && document.body.dataset.postUrl ? document.body.dataset.postUrl : base + path, {method: data ? 'POST' : 'GET', credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            ...(data ? {body: JSON.stringify(data)} : {})});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Request failed (${response.status}).`);
        return result.data;
    }
    function payload() {
        const data = {warehouse_id: Number($('warehouse').value), business_date: $('business-date').value,
            [kind === 'sale' ? 'customer_id' : 'supplier_id']: party?.id,
            items: items.map(({name, code, ...line}) => line), paid_amount: Number($('paid').value),
            paying_method: $('method').value, account_id: Number($('account').value),
            order_discount: Number($('discount').value), shipping_cost: Number($('freight').value),
            transport_name: $('transport')?.value || '', lr_no: $('lr-number')?.value || '', lr_number: $('lr-number')?.value || '',
            bale_no: $('bale-no')?.value || '', no_of_bales: $('no-of-bales')?.value ? Number($('no-of-bales').value) : null,
            lr_date: $('lr-date')?.value || null, station_to: $('station-to')?.value || '', order_no: $('order-no')?.value || '',
            credit_days: $('credit-days')?.value ? Number($('credit-days').value) : null,
            [kind === 'sale' ? 'sale_note' : 'note']: $('note').value, idempotency_key: key};
        if ($('series')?.value) data.series_id = Number($('series').value);
        if (kind === 'sale' && $('sale-type')?.value) data.sale_type_id = Number($('sale-type').value);
        if (kind === 'purchase' && $('purchase-type')?.value) data.purchase_type_id = Number($('purchase-type').value);
        if ($('agent')?.value) data.agent_id = Number($('agent').value);
        if ($('area')?.value) data.area_id = Number($('area').value);
        if (kind === 'sale' && $('override').value) data.credit_override_reason = $('override').value;
        if (document.body.dataset.projectId) data.project_id = Number(document.body.dataset.projectId);
        for (const [id, field] of [['lr-date','lr_date'],['bale-count','bale_count'],['bundle-count','bundle_count']]) {
            if ($(id)?.value) data[field] = $(id).value;
        }
        if (document.body.dataset.compliance === '1' && ($('reverse-charge').checked || $('place-of-supply').value)) {
            data.gst = {reverse_charge: $('reverse-charge').checked};
            if ($('place-of-supply').value) data.gst.place_of_supply = $('place-of-supply').value;
        }
        if (kind === 'purchase') {
            data.status = Number($('receipt-status').value);
            data.landed_cost_method = $('landed-method').value;
            data.update_item_cost = $('update-cost').checked;
            data.update_item_hsn = $('update-hsn').checked;
            data.goods_receipt_no = $('receipt-number').value;
            if ($('supplier-invoice-no')?.value) data.supplier_invoice_no = $('supplier-invoice-no').value;
            if ($('supplier-invoice-date')?.value) data.supplier_invoice_date = $('supplier-invoice-date').value;
            if ($('purchase-order').value) data.purchase_order_id = Number($('purchase-order').value);
            if (data.status !== 2) data.items.forEach(line => delete line.received_qty);
        }
        return data;
    }
    async function preview() {
        const sequence = ++previewSequence;
        try {
            const totals = await api('/preview', payload());
            if (sequence !== previewSequence) return;
            text($('current'), Number(totals.grand_total).toFixed(4));
            if (party) text($('combined'), (Number(party.outstanding || 0) + Number(totals.grand_total) - Number($('paid').value)).toFixed(4));
        } catch (error) { if (sequence === previewSequence) message(error.message, true); }
    }
    function changed() { dirty = true; revision++; clearTimeout(previewTimer); previewTimer = setTimeout(preview, 120); }
    function showDialog(dialog) { lastFocus = document.activeElement; dialog.showModal(); }
    document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('close', () => {
        const target = lastFocus?.isConnected && !lastFocus.disabled && !lastFocus.closest('[inert]')
            && lastFocus.matches('input, select, textarea, button, a[href], [tabindex]') ? lastFocus : $('product-search');
        target.focus();
    }));
    function info(title, records, columns) {
        text($('info-title'), title);
        const table = document.createElement('table'), head = document.createElement('thead'), heading = document.createElement('tr'), body = document.createElement('tbody');
        columns.forEach(([label]) => { const cell = document.createElement('th'); cell.scope = 'col'; text(cell, label); heading.append(cell); });
        head.append(heading);
        records.forEach(record => { const row = document.createElement('tr'); columns.forEach(([, value]) => { const cell = document.createElement('td'); text(cell, value(record)); row.append(cell); }); body.append(row); });
        table.append(head, body); const scroll = document.createElement('div'); scroll.className = 'table-scroll'; scroll.append(table); $('info-content').replaceChildren(scroll);
        if (!records.length) { const empty = document.createElement('p'); text(empty, 'No records found.'); $('info-content').append(empty); }
        if (!$('info-dialog').open) showDialog($('info-dialog'));
    }
    const statementColumns = [['Bill', row => row.document_no || row.reference_no || `${row.source_type} ${row.source_id}`], ['Date', row => String(row.document_date).slice(0, 10)],
        ['Due', row => String(row.due_date).slice(0, 10)], ['Open amount', row => Number(row.open_amount).toFixed(4)]];
    async function selectParty(record) {
        party = record; $('party-search').value = [record.city, record.name].filter(Boolean).join(' · '); $('party-results').replaceChildren();
        try { const summary = await api('/party/' + record.id); if (party?.id !== record.id) return; party.outstanding = summary.outstanding; text($('previous'), Number(summary.outstanding).toFixed(4));
            if (kind === 'sale') text($('credit-summary'), `Overdue: ${Number(summary.credit.overdue).toFixed(4)} · Credit limit: ${summary.credit.credit_limit > 0 ? Number(summary.credit.credit_limit).toFixed(4) : 'Unlimited'} · Available credit: ${summary.credit.available_credit === null ? 'Unlimited' : Number(summary.credit.available_credit).toFixed(4)}`);
        }
        catch (error) { message(error.message, true); }
        changed();
    }
    function focusControl(control) { control.focus(); control.scrollIntoView({block: 'center'}); }
    function addProduct(product) {
        items.push({product_id: product.id, name: product.name, code: product.code, qty: 1,
            [priceField]: Number(kind === 'sale' ? product.price : product.cost), ...(product.unit_id ? {[unitField]: Number(product.unit_id)} : {}), ...(kind === 'purchase' ? {received_qty: 1} : {})});
        $('product-search').value = ''; $('product-results').replaceChildren(); render(); changed();
        focusControl($('item-lines').lastElementChild.querySelector('input'));
    }
    function render() {
        $('item-lines').replaceChildren(); $('empty-lines').hidden = items.length > 0;
        items.forEach((item, index) => {
            const row = document.createElement('tr'), name = document.createElement('td'), label = document.createElement('span'), code = document.createElement('small');
            text(label, item.name); text(code, item.code); name.append(label, code); row.append(name);
            const fields = ['qty', priceField, ...(kind === 'purchase' ? ['received_qty'] : [])];
            fields.forEach(field => {
                const cell = document.createElement('td'), input = document.createElement('input');
                input.type = 'number'; input.min = field === 'qty' ? quantityStep : '0'; input.step = ['qty','received_qty'].includes(field) ? quantityStep : '0.0001'; input.value = item[field] ?? 0;
                input.setAttribute('aria-label', `${field === 'qty' ? 'Quantity' : field === 'received_qty' ? 'Received quantity' : 'Net rate'} for ${item.name}`);
                input.addEventListener('input', () => { item[field] = Number(input.value); changed(); });
                input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.ctrlKey) { event.preventDefault(); const next = input.closest('td').nextElementSibling?.querySelector('input'); focusControl(next || $('product-search')); } });
                cell.append(input);
                if (field === 'qty' && $('line-unit-options')) {
                    const unit = $('line-unit-options').content.firstElementChild.cloneNode(true);
                    unit.setAttribute('aria-label', 'Unit for ' + item.name);
                    if (item[unitField]) unit.value = item[unitField]; else unit.value = '';
                    unit.addEventListener('change', () => { item[unitField] = Number(unit.value); changed(); });
                    cell.append(unit);
                }
                row.append(cell);
            });
            const tracking = document.createElement('td'), button = document.createElement('button'); button.type = 'button'; text(button, 'Tracking');
            button.addEventListener('click', () => openTracking(index)); tracking.append(button); row.append(tracking);
            const action = document.createElement('td'), remove = document.createElement('button'); remove.type = 'button'; text(remove, 'Remove');
            remove.setAttribute('aria-label', 'Remove ' + item.name); remove.addEventListener('click', () => { items.splice(index, 1); render(); changed(); $('product-search').focus(); }); action.append(remove); row.append(action); $('item-lines').append(row);
        });
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
                    const button = document.createElement('button'); button.type = 'button';
                    text(button, [record.code || record.city, record.name].filter(Boolean).join(' · '));
                    button.addEventListener('click', () => { clearTimeout(timer); ++searchSequence[resource]; select(record); });
                    $(resultId).append(button);
                });
            } catch (error) { message(error.message, true); }
        }
        $(inputId).addEventListener('input', () => {
            clearTimeout(timer); ++searchSequence[resource]; $(resultId).replaceChildren(); timer = setTimeout(search, 150);
        });
        $(inputId).addEventListener('keydown', async event => {
            if ((event.key === 'Enter' && !event.ctrlKey) || event.key === 'ArrowDown') {
                event.preventDefault(); clearTimeout(timer); await search();
                const first = $(resultId).querySelector('button');
                if (first) { event.key === 'Enter' ? first.click() : first.focus(); }
            }
        });
        $(resultId).addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault(); (event.key === 'ArrowDown' ? event.target.nextElementSibling : event.target.previousElementSibling)?.focus();
            }
        });
    }
    wireSearch('parties', 'party-search', 'party-results', selectParty); wireSearch('products', 'product-search', 'product-results', addProduct);
    $('party-search').addEventListener('input', () => { party = null; text($('previous'), '—'); text($('combined'), '—'); changed(); });
    async function saveDraft() {
        if (busy || !dirty) return;
        busy = true;
        const savedRevision = revision;
        try { draft = await api('/drafts', {id: draft?.id, version: draft?.version || 0, payload: {...payload(), items, party}}); dirty = revision !== savedRevision; text($('draft-status'), dirty ? 'Draft saved; newer edits pending' : 'Draft saved · ' + draft.version); }
        catch (error) { text($('draft-status'), 'Draft save failed'); message(error.message, true); }
        finally { busy = false; }
    }
    $('save-draft').addEventListener('click', saveDraft); setInterval(saveDraft, 10000);
    async function loadPayload(data) {
        items = data.items || []; party = data.party || null;
        $('warehouse').value = data.warehouse_id || $('warehouse').value; $('business-date').value = data.business_date || $('business-date').value;
        for (const [id, field] of [['paid','paid_amount'],['freight','shipping_cost'],['discount','order_discount'],['transport','transport_name'],['lr-number','lr_number']]) $(id).value = data[field] ?? (['paid','freight','discount'].includes(id) ? 0 : '');
        $('note').value = data[kind === 'sale' ? 'sale_note' : 'note'] || ''; key = data.idempotency_key || crypto.randomUUID();
        $('method').value = data.paying_method || 'Cash'; $('account').value = data.account_id || $('account').value;
        if (data.series_id && $('series')) $('series').value = data.series_id;
        if (kind === 'sale' && data.sale_type_id && $('sale-type')) $('sale-type').value = data.sale_type_id;
        if (kind === 'purchase' && data.purchase_type_id && $('purchase-type')) $('purchase-type').value = data.purchase_type_id;
        if (data.agent_id && $('agent')) $('agent').value = data.agent_id;
        if (data.area_id && $('area')) $('area').value = data.area_id;
        if (kind === 'sale') $('override').value = data.credit_override_reason || '';
        if (kind === 'purchase') {
            $('receipt-status').value = data.status || 1;
            $('landed-method').value = data.landed_cost_method || 'value';
            $('purchase-order').value = data.purchase_order_id || '';
            $('receipt-number').value = data.goods_receipt_no || '';
            $('update-cost').checked = !!data.update_item_cost;
            $('update-hsn').checked = !!data.update_item_hsn;
            if (data.supplier_invoice_no && $('supplier-invoice-no')) $('supplier-invoice-no').value = data.supplier_invoice_no;
            if (data.supplier_invoice_date && $('supplier-invoice-date')) $('supplier-invoice-date').value = data.supplier_invoice_date;
        }
        if (!party && data[kind === 'sale' ? 'customer_id' : 'supplier_id']) { const result = await api('/party/' + data[kind === 'sale' ? 'customer_id' : 'supplier_id']); party = result.party; }
        if (party) await selectParty(party); else { $('party-search').value = ''; text($('previous'), '—'); text($('combined'), '—'); } render(); changed();
    }
    $('restore-draft').addEventListener('click', async () => { try { const drafts = await api('/drafts'); text($('info-title'), 'Saved drafts'); $('info-content').replaceChildren(); drafts.forEach(saved => { const button = document.createElement('button'); button.type='button'; text(button, `Draft ${saved.id} · ${saved.updated_at}`); button.addEventListener('click', async () => { draft = saved; await loadPayload(JSON.parse(saved.payload_json)); lastFocus=$('product-search'); $('info-dialog').close(); }); $('info-content').append(button); }); showDialog($('info-dialog')); } catch(error) { message(error.message,true); } });
    $('clone').addEventListener('click', () => { text($('info-title'), 'Clone prior bill'); const input = document.createElement('input'); input.type='number'; input.min='1'; input.setAttribute('aria-label','Prior bill ID'); const button=document.createElement('button'); text(button,'Load bill'); button.addEventListener('click',async()=>{try { await loadPayload(await api('/clone/'+input.value)); key=crypto.randomUUID(); draft=null; lastFocus=$('product-search'); $('info-dialog').close(); } catch(error){message(error.message,true);} }); $('info-content').replaceChildren(input,button); showDialog($('info-dialog')); });
    async function partyDetails(title, pendingOnly = false) {
        if (!party) return message('Select a party first.', true);
        const partyId = party.id;
        let records = [];
        async function load(page) {
            try {
                const result = await api('/party/' + partyId + '?page=' + page + '&pending=' + (pendingOnly ? '1' : '0'));
                records = records.concat(result.items); info(title, records, statementColumns);
                if (result.next_page) {
                    const more = document.createElement('button'); more.type = 'button'; text(more, 'Load more');
                    more.addEventListener('click', async () => { more.disabled = true; await load(result.next_page); });
                    $('info-content').append(more);
                }
            } catch (error) { message(error.message, true); }
        }
        await load(1);
    }
    $('pending').addEventListener('click', () => partyDetails('Pending bills', true));
    async function statement() { await partyDetails('Party statement'); }
    async function rates() {
        if (!party || !items.length) return message('Select a party and item first.', true);
        try {
            const res = await api('/previous-rates?party_id=' + party.id + '&product_id=' + items[items.length - 1].product_id);
            const rows = [];
            if (res.last_sale) {
                rows.push({
                    type: 'Last Sale Bill',
                    ref: res.last_sale.reference_no,
                    date: res.last_sale.created_at ? String(res.last_sale.created_at).substring(0, 10) : '—',
                    rate: res.last_sale.rate,
                    qty: res.last_sale.qty
                });
            }
            if (res.last_purchase) {
                rows.push({
                    type: 'Last Purchase Inward',
                    ref: res.last_purchase.reference_no,
                    date: res.last_purchase.created_at ? String(res.last_purchase.created_at).substring(0, 10) : '—',
                    rate: res.last_purchase.cost,
                    qty: res.last_purchase.qty
                });
            }
            if (!rows.length) {
                rows.push({ type: 'No previous transaction found', ref: '—', date: '—', rate: 0, qty: 0 });
            }
            info('Rate History (Optech Rate Details Lookup)', rows, [
                ['Voucher Nature', row => row.type],
                ['Bill #', row => row.ref],
                ['Date', row => row.date],
                ['Rate / Cost', row => Number(row.rate).toFixed(2)],
                ['Qty', row => row.qty || '—']
            ]);
        } catch(error) { message(error.message, true); }
    }
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
        $('master-name').focus();
    }
    document.querySelectorAll('[data-inline]').forEach(button => button.addEventListener('click', () => openInline(button.dataset.inline)));
    $('inline-form').addEventListener('submit', async event => {
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
                $('agent').append(opt);
                changed();
            } else if (inlineResource === 'areas') {
                const opt = new Option(record.name, record.id, true, true);
                $('area').append(opt);
                changed();
            } else if (inlineResource === 'sale-types') {
                const opt = new Option(record.name, record.id, true, true);
                $('sale-type').append(opt);
                changed();
            } else if (inlineResource === 'purchase-types') {
                const opt = new Option(record.name, record.id, true, true);
                $('purchase-type').append(opt);
                changed();
            } else if (inlineResource === 'bill-sundries') {
                const opt = new Option(record.name + ` (${record.calculation_type})`, record.id, true, true);
                opt.dataset.calc = record.calculation_type;
                opt.dataset.val = record.default_value;
                $('bill-sundry-select').append(opt);
                $('bill-sundry-select').dispatchEvent(new Event('change'));
            } else if (inlineResource === 'remarks') {
                const opt = new Option(record.title, record.remark, true, true);
                $('standard-remark').append(opt);
                $('note').value = record.remark;
                changed();
            } else if (inlineResource === 'series') {
                const opt = new Option(record.code + (record.prefix ? ` (${record.prefix}${record.next_number})` : ''), record.id, true, true);
                $('series').append(opt);
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
                $('sundry-amount').value = defVal;
                if (opt.textContent.toLowerCase().includes('discount')) {
                    if (opt.dataset.calc === 'percentage') {
                        const subtotal = items.reduce((acc, it) => acc + (it.qty * it[priceField]), 0);
                        $('discount').value = (subtotal * (defVal / 100)).toFixed(4);
                    } else {
                        $('discount').value = defVal;
                    }
                } else if (opt.textContent.toLowerCase().includes('freight') || opt.textContent.toLowerCase().includes('transport') || opt.textContent.toLowerCase().includes('loading')) {
                    $('freight').value = defVal;
                }
                changed();
            }
        });
        $('sundry-amount').addEventListener('input', () => {
            const opt = $('bill-sundry-select').selectedOptions[0];
            const val = Number($('sundry-amount').value || 0);
            if (opt && opt.value) {
                if (opt.textContent.toLowerCase().includes('discount')) {
                    if (opt.dataset.calc === 'percentage') {
                        const subtotal = items.reduce((acc, it) => acc + (it.qty * it[priceField]), 0);
                        $('discount').value = (subtotal * (val / 100)).toFixed(4);
                    } else {
                        $('discount').value = val;
                    }
                } else if (opt.textContent.toLowerCase().includes('freight')) {
                    $('freight').value = val;
                }
            }
            changed();
        });
    }
    if ($('standard-remark')) {
        $('standard-remark').addEventListener('change', () => {
            if ($('standard-remark').value) {
                $('note').value = $('standard-remark').value;
                changed();
            }
        });
    }
    function openTracking(index) {
        trackingIndex = index; const item = items[index];
        $('serials').value = (item.serials || []).join(','); $('batch-id').value = item.product_batch_id || '';
        $('identity').value = item.stock_identity_id || ''; $('variant').value = item.variant_id || '';
        if ($('quantity-scheme')) {
            [...$('quantity-scheme').options].forEach(option => { option.hidden = !!option.dataset.product && Number(option.dataset.product) !== item.product_id; });
            $('quantity-scheme').value = item.quantity_scheme_id || '';
        }
        if ($('piece-select')) $('piece-select').replaceChildren(new Option('Find pieces first', ''));
        if (kind === 'purchase') {
            $('batch-no').value = item.batch?.batch_no || ''; $('expiry').value = item.batch?.expired_date || '';
            if ($('mfg-date')) { $('mfg-date').value = item.batch?.mfg_date || ''; $('batch-mrp').value = item.batch?.mrp ?? ''; }
            $('hsn-code').value = item.hsn_code || ''; $('weight').value = item.weight || ''; $('manual-freight').value = item.landed_cost || '';
            if ($('piece-number')) {
                $('piece-number').value = item.dimensions?.identity_no || '';
                for (const field of ['length','width','thickness']) $('piece-' + field).value = item.dimensions?.[field] || '';
                if (item.dimensions) $('piece-uom').value = item.dimensions.dimension_uom;
                $('piece-count').value = item.dimensions?.pieces || 1; $('piece-grade').value = item.dimensions?.grade || '';
            }
        }
        showDialog($('tracking-dialog'));
    }
    $('tracking-form').addEventListener('submit', event => {
        event.preventDefault();
        try {
            const item = items[trackingIndex];
            item.serials = $('serials').value.split(',').map(value => value.trim()).filter(Boolean);
            for (const [id, field] of [['batch-id','product_batch_id'],['identity','stock_identity_id'],['variant','variant_id']]) {
                if ($(id).value) item[field] = Number($(id).value); else delete item[field];
            }
            if ($('quantity-scheme')) { if ($('quantity-scheme').value) item.quantity_scheme_id = Number($('quantity-scheme').value); else delete item.quantity_scheme_id; }
            if (kind === 'purchase') {
                item.hsn_code = $('hsn-code').value;
                if ($('batch-no').value) item.batch = {batch_no: $('batch-no').value, expired_date: $('expiry').value || null}; else delete item.batch;
                if (item.batch && $('mfg-date')) {
                    if ($('mfg-date').value) item.batch.mfg_date = $('mfg-date').value;
                    if ($('batch-mrp').value !== '') item.batch.mrp = Number($('batch-mrp').value);
                }
                for (const [id, field] of [['weight','weight'],['manual-freight','landed_cost']]) {
                    if ($(id).value) item[field] = Number($(id).value); else delete item[field];
                }
                if ($('piece-number')?.value) {
                    item.dimensions = {identity_no: $('piece-number').value, dimension_uom: $('piece-uom').value,
                        pieces: Number($('piece-count').value), grade: $('piece-grade').value};
                    for (const field of ['length','width','thickness']) {
                        const size = Number($('piece-' + field).value);
                        if (!(size > 0)) throw new Error('Enter positive length, width and thickness.');
                        item.dimensions[field] = size;
                    }
                } else delete item.dimensions;
            }
            $('tracking-dialog').close(); changed();
        } catch (error) { message(error.message, true); }
    });
    $('find-pieces')?.addEventListener('click', async () => {
        const index = trackingIndex, product = items[index].product_id;
        try {
            const response = await fetch('/operations/inventory/pieces?' + new URLSearchParams({product_id: product,
                warehouse_id: $('warehouse').value, q: $('piece-filter').value}), {headers: {'Accept':'application/json'}});
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Piece lookup failed.');
            if (index !== trackingIndex) return;
            $('piece-select').replaceChildren(new Option('Select piece', ''), ...result.data.map(piece => new Option(
                `${piece.identity_no} · ${piece.species || ''} ${piece.grade || ''} · ${piece.length} × ${piece.width} × ${piece.thickness} ${piece.dimension_uom} · Balance ${piece.qty_base}`, piece.id)));
        } catch (error) { message(error.message, true); }
    });
    $('piece-select')?.addEventListener('change', () => { $('identity').value = $('piece-select').value; });
    $('entry-form').addEventListener('change', changed);
    $('entry-form').addEventListener('submit',async event=>{event.preventDefault();if(busy)return;if(!party||!items.length)return message('Select a party and add at least one item.',true);busy=true;$('post').disabled=true;$('entry-form').inert=true;
        try {const data=payload();if(draft)data.draft_id=draft.id;const result=await api('',data);dirty=false;draft=null;message('Saved '+result.reference_no); const button=document.createElement('button');text(button,'Print invoice');button.addEventListener('click',()=>window.open(document.body.dataset.compliance === '1' ? '/compliance/documents/'+kind+'/'+(result.replacement_sale_id || result.id) : (kind==='sale'?'/sales/gen_invoice/'+result.id:'/purchases/'+result.id),'_blank','noopener'));text($('info-title'),result.posted_at?'Invoice posted':'Document saved without posting');$('info-content').replaceChildren(button);showDialog($('info-dialog'));items=[];key=crypto.randomUUID();render();$('paid').value='0';text($('draft-status'),'Draft cleared');const summary=await api('/party/'+party.id);party.outstanding=summary.outstanding;text($('previous'),Number(summary.outstanding).toFixed(4));await preview();}catch(error){message(error.message,true);}finally{busy=false;$('post').disabled=false;$('entry-form').inert=false;} });
    document.addEventListener('keydown',event=>{const input=/INPUT|TEXTAREA|SELECT/.test(event.target.tagName);if(document.querySelector('dialog[open]'))return;
        if(event.key==='F2'||event.key==='F12'){event.preventDefault();location.href=event.key==='F2'?'/commercial/sale/entry':'/commercial/purchase/entry';}
        else if(event.key==='F6'){event.preventDefault();openInline('products');}
        else if(event.altKey&&event.key.toLowerCase()==='c'){event.preventDefault();openInline('parties');}
        else if(event.altKey&&event.key.toLowerCase()==='y'){event.preventDefault();statement();}
        else if(event.ctrlKey&&event.key.toLowerCase()==='s'){event.preventDefault();rates();}
        else if(event.ctrlKey&&event.key.toLowerCase()==='b'){event.preventDefault();$('pending').click();}
        else if(event.ctrlKey&&event.key==='Enter'){event.preventDefault();$('entry-form').requestSubmit();}
        else if(event.key===' '&&!input){event.preventDefault();$('party-search').focus();}
        else if(event.key==='Escape'){ $('party-results').replaceChildren();$('product-results').replaceChildren();$('product-search').focus();}
        else if(event.key==='Enter'&&input&&event.target!==$('party-search')&&event.target!==$('product-search')&&!event.target.closest('tbody')&&event.target.tagName!=='TEXTAREA'){event.preventDefault();const controls=[...$('entry-form').querySelectorAll('input,select,button')].filter(node=>!node.disabled&&node.offsetParent!==null);controls[controls.indexOf(event.target)+1]?.focus();}
    });
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    $('party-search').focus();
    if (document.body.dataset.projectCustomer) api('/party/' + document.body.dataset.projectCustomer)
        .then(result => selectParty(result.party)).catch(error => message(error.message, true));
})();

(() => {
    'use strict';
    const $ = id => document.getElementById(id);
    const kind = document.body.dataset.kind;
    const base = document.body.dataset.base;
    const priceField = kind === 'sale' ? 'net_unit_price' : 'net_unit_cost';
    const unitField = kind === 'sale' ? 'sale_unit_id' : 'purchase_unit_id';
    let items = [], party = null, key = crypto.randomUUID(), draft = null, busy = false, dirty = false, revision = 0, previewTimer;
    let previewSequence = 0, searchSequence = {parties: 0, products: 0}, lastFocus = null, trackingIndex = null, inlineResource = null, inlineKey = null, inlineBusy = false;
    const text = (node, value) => { node.textContent = value; };
    const message = (value, error = false) => { text($('status'), value); $('status').classList.toggle('error', error); };
    async function api(path, data) {
        const response = await fetch(base + path, {method: data ? 'POST' : 'GET', credentials: 'same-origin',
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
            transport_name: $('transport').value, lr_number: $('lr-number').value,
            [kind === 'sale' ? 'sale_note' : 'note']: $('note').value, idempotency_key: key};
        if (kind === 'sale' && $('override').value) data.credit_override_reason = $('override').value;
        if (kind === 'purchase') {
            data.status = Number($('receipt-status').value);
            data.landed_cost_method = $('landed-method').value;
            data.update_item_cost = $('update-cost').checked;
            data.update_item_hsn = $('update-hsn').checked;
            data.goods_receipt_no = $('receipt-number').value;
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
    document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('close', () => lastFocus?.focus()));
    function info(title, records, columns) {
        text($('info-title'), title);
        const table = document.createElement('table'), head = document.createElement('thead'), heading = document.createElement('tr'), body = document.createElement('tbody');
        columns.forEach(([label]) => { const cell = document.createElement('th'); cell.scope = 'col'; text(cell, label); heading.append(cell); });
        head.append(heading);
        records.forEach(record => { const row = document.createElement('tr'); columns.forEach(([, value]) => { const cell = document.createElement('td'); text(cell, value(record)); row.append(cell); }); body.append(row); });
        table.append(head, body); const scroll = document.createElement('div'); scroll.className = 'table-scroll'; scroll.append(table); $('info-content').replaceChildren(scroll);
        if (!records.length) { const empty = document.createElement('p'); text(empty, 'No records found.'); $('info-content').append(empty); }
        showDialog($('info-dialog'));
    }
    const statementColumns = [['Bill', row => row.reference_no || `${row.source_type} ${row.source_id}`], ['Date', row => String(row.document_date).slice(0, 10)],
        ['Due', row => String(row.due_date).slice(0, 10)], ['Open amount', row => Number(row.open_amount).toFixed(4)]];
    async function selectParty(record) {
        party = record; $('party-search').value = [record.city, record.name].filter(Boolean).join(' · '); $('party-results').replaceChildren();
        try { const summary = await api('/party/' + record.id); if (party?.id !== record.id) return; party.outstanding = summary.outstanding; text($('previous'), Number(summary.outstanding).toFixed(4));
            if (kind === 'sale') text($('credit-summary'), `Overdue: ${Number(summary.credit.overdue).toFixed(4)} · Credit limit: ${summary.credit.credit_limit > 0 ? Number(summary.credit.credit_limit).toFixed(4) : 'Unlimited'} · Available credit: ${summary.credit.available_credit === null ? 'Unlimited' : Number(summary.credit.available_credit).toFixed(4)}`);
        }
        catch (error) { message(error.message, true); }
        changed(); $('product-search').focus();
    }
    function addProduct(product) {
        items.push({product_id: product.id, name: product.name, code: product.code, qty: 1,
            [priceField]: Number(kind === 'sale' ? product.price : product.cost), [unitField]: Number(product.unit_id), ...(kind === 'purchase' ? {received_qty: 1} : {})});
        $('product-search').value = ''; $('product-results').replaceChildren(); render(); changed();
        $('item-lines').lastElementChild.querySelector('input').focus();
    }
    function render() {
        $('item-lines').replaceChildren(); $('empty-lines').hidden = items.length > 0;
        items.forEach((item, index) => {
            const row = document.createElement('tr'), name = document.createElement('td'), label = document.createElement('span'), code = document.createElement('small');
            text(label, item.name); text(code, item.code); name.append(label, code); row.append(name);
            const fields = ['qty', priceField, ...(kind === 'purchase' ? ['received_qty'] : [])];
            fields.forEach(field => {
                const cell = document.createElement('td'), input = document.createElement('input');
                input.type = 'number'; input.min = field === 'qty' ? '0.0001' : '0'; input.step = '0.0001'; input.value = item[field] ?? 0;
                input.setAttribute('aria-label', `${field === 'qty' ? 'Quantity' : field === 'received_qty' ? 'Received quantity' : 'Net rate'} for ${item.name}`);
                input.addEventListener('input', () => { item[field] = Number(input.value); changed(); });
                input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.ctrlKey) { event.preventDefault(); const next = input.closest('td').nextElementSibling?.querySelector('input'); (next || $('product-search')).focus(); } });
                cell.append(input); row.append(cell);
            });
            const tracking = document.createElement('td'), button = document.createElement('button'); button.type = 'button'; text(button, 'Tracking');
            button.addEventListener('click', () => openTracking(index)); tracking.append(button); row.append(tracking);
            const action = document.createElement('td'), remove = document.createElement('button'); remove.type = 'button'; text(remove, 'Remove');
            remove.setAttribute('aria-label', 'Remove ' + item.name); remove.addEventListener('click', () => { items.splice(index, 1); render(); changed(); $('product-search').focus(); }); action.append(remove); row.append(action); $('item-lines').append(row);
        });
    }
    function wireSearch(resource, inputId, resultId, select) {
        let timer;
        $(inputId).addEventListener('input', () => { clearTimeout(timer); const sequence = ++searchSequence[resource]; timer = setTimeout(async () => {
            try { const results = await api('/search/' + resource + '?q=' + encodeURIComponent($(inputId).value)); if (sequence !== searchSequence[resource]) return;
                $(resultId).replaceChildren(); results.forEach(record => { const button = document.createElement('button'); button.type = 'button';
                    text(button, [record.code || record.city, record.name].filter(Boolean).join(' · ')); button.addEventListener('click', () => select(record)); $(resultId).append(button); });
            } catch (error) { message(error.message, true); }
        }, 150); });
        $(inputId).addEventListener('keydown', event => { if ((event.key === 'Enter' && !event.ctrlKey) || event.key === 'ArrowDown') { event.preventDefault(); const first = $(resultId).querySelector('button'); if (first) { event.key === 'Enter' ? first.click() : first.focus(); } } });
        $(resultId).addEventListener('keydown', event => { if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); (event.key === 'ArrowDown' ? event.target.nextElementSibling : event.target.previousElementSibling)?.focus(); } });
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
        if (kind === 'sale') $('override').value = data.credit_override_reason || '';
        if (kind === 'purchase') { $('receipt-status').value = data.status || 1; $('landed-method').value = data.landed_cost_method || 'value'; $('purchase-order').value = data.purchase_order_id || ''; $('receipt-number').value = data.goods_receipt_no || ''; $('update-cost').checked = !!data.update_item_cost; $('update-hsn').checked = !!data.update_item_hsn; }
        if (!party && data[kind === 'sale' ? 'customer_id' : 'supplier_id']) { const result = await api('/party/' + data[kind === 'sale' ? 'customer_id' : 'supplier_id']); party = result.party; }
        if (party) await selectParty(party); else { $('party-search').value = ''; text($('previous'), '—'); text($('combined'), '—'); } render(); changed();
    }
    $('restore-draft').addEventListener('click', async () => { try { const drafts = await api('/drafts'); text($('info-title'), 'Saved drafts'); $('info-content').replaceChildren(); drafts.forEach(saved => { const button = document.createElement('button'); button.type='button'; text(button, `Draft ${saved.id} · ${saved.updated_at}`); button.addEventListener('click', async () => { draft = saved; await loadPayload(JSON.parse(saved.payload_json)); $('info-dialog').close(); }); $('info-content').append(button); }); showDialog($('info-dialog')); } catch(error) { message(error.message,true); } });
    $('clone').addEventListener('click', () => { text($('info-title'), 'Clone prior bill'); const input = document.createElement('input'); input.type='number'; input.min='1'; input.setAttribute('aria-label','Prior bill ID'); const button=document.createElement('button'); text(button,'Load bill'); button.addEventListener('click',async()=>{try { await loadPayload(await api('/clone/'+input.value)); key=crypto.randomUUID(); draft=null; $('info-dialog').close(); } catch(error){message(error.message,true);} }); $('info-content').replaceChildren(input,button); showDialog($('info-dialog')); });
    $('pending').addEventListener('click', async () => { if (!party) return message('Select a party first.', true); try { const result = await api('/party/' + party.id); info('Pending bills', result.items.filter(item => Number(item.open_amount) !== 0), statementColumns); } catch (error) { message(error.message,true); } });
    async function statement() { if (!party) return message('Select a party first.',true); try { info('Party statement', (await api('/party/'+party.id)).items, statementColumns); } catch(error){message(error.message,true);} }
    async function rates() { if (!party || !items.length) return message('Select a party and item first.',true); try { info('Previous rates',await api('/previous-rates?party_id='+party.id+'&product_id='+items[items.length-1].product_id), [['Bill', row => row[kind+'_id']], ['Quantity', row => row.qty], ['Net rate', row => Number(row[priceField]).toFixed(4)]]); } catch(error){message(error.message,true);} }
    function openInline(resource) { inlineResource=resource; inlineKey=crypto.randomUUID(); $('inline-form').reset(); $('master-code').required=resource==='products'; text($('inline-title'),resource==='products'?'New item':'New party'); $('party-fields').hidden=resource==='products'; $('product-fields').hidden=resource!=='products'; text($('inline-error'),''); showDialog($('inline-dialog')); $('master-name').focus(); }
    document.querySelectorAll('[data-inline]').forEach(button=>button.addEventListener('click',()=>openInline(button.dataset.inline)));
    $('inline-form').addEventListener('submit',async event=>{event.preventDefault();if(inlineBusy)return;inlineBusy=true;const data={...Object.fromEntries(new FormData(event.target)),idempotency_key:inlineKey};try {const record=await api('/masters/'+inlineResource,data);$('inline-dialog').close();inlineResource==='products'?addProduct(record):selectParty(record);}catch(error){text($('inline-error'),error.message);}finally{inlineBusy=false;} });
    function openTracking(index) { trackingIndex=index; const item=items[index]; $('serials').value=(item.serials||[]).join(','); $('batch-id').value=item.product_batch_id||''; $('identity').value=item.stock_identity_id||''; $('variant').value=item.variant_id||''; if(kind==='purchase'){ $('batch-no').value=item.batch?.batch_no||''; $('expiry').value=item.batch?.expired_date||''; $('hsn-code').value=item.hsn_code||''; $('weight').value=item.weight||''; $('manual-freight').value=item.landed_cost||''; $('piece-number').value=item.dimensions?.identity_no||''; for(const field of ['length','width','thickness']) $('piece-'+field).value=item.dimensions?.[field]||'';} showDialog($('tracking-dialog')); }
    $('tracking-form').addEventListener('submit',event=>{event.preventDefault();try {const item=items[trackingIndex]; item.serials=$('serials').value.split(',').map(value=>value.trim()).filter(Boolean); for(const [id,field] of [['batch-id','product_batch_id'],['identity','stock_identity_id'],['variant','variant_id']]){ if($(id).value)item[field]=Number($(id).value);else delete item[field]; } if(kind==='purchase'){ item.hsn_code=$('hsn-code').value; if($('batch-no').value)item.batch={batch_no:$('batch-no').value,expired_date:$('expiry').value||null};else delete item.batch; for(const [id,field] of [['weight','weight'],['manual-freight','landed_cost']]){if($(id).value)item[field]=Number($(id).value);else delete item[field];}if($('piece-number').value){item.dimensions={identity_no:$('piece-number').value};for(const field of ['length','width','thickness']){const size=Number($('piece-'+field).value);if(!(size>0))throw new Error('Enter positive length, width and thickness.');item.dimensions[field]=size;}}else delete item.dimensions;} $('tracking-dialog').close();changed();}catch(error){message(error.message,true);} });
    $('entry-form').addEventListener('change', changed);
    $('entry-form').addEventListener('submit',async event=>{event.preventDefault();if(busy)return;if(!party||!items.length)return message('Select a party and add at least one item.',true);busy=true;$('post').disabled=true;$('entry-form').inert=true;
        try {const data=payload();if(draft)data.draft_id=draft.id;const result=await api('',data);dirty=false;draft=null;message('Saved '+result.reference_no); const button=document.createElement('button');text(button,'Print invoice');button.addEventListener('click',()=>window.open(kind==='sale'?'/sales/gen_invoice/'+result.id:'/purchases/'+result.id,'_blank','noopener'));text($('info-title'),result.posted_at?'Invoice posted':'Document saved without posting');$('info-content').replaceChildren(button);showDialog($('info-dialog'));items=[];key=crypto.randomUUID();render();$('paid').value='0';text($('draft-status'),'Draft cleared');const summary=await api('/party/'+party.id);party.outstanding=summary.outstanding;text($('previous'),Number(summary.outstanding).toFixed(4));await preview();}catch(error){message(error.message,true);}finally{busy=false;$('post').disabled=false;$('entry-form').inert=false;} });
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
})();

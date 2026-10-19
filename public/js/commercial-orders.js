/* Orders use the normal document grid and its draft/posting contract. */
(function ($) {
    'use strict';
    $(function () {
        const root = document.getElementById('commercial-orders');
        if (!root) return;
        $(root).removeClass('my-2').appendTo($('.items-controls-group').first());
        const kind = root.dataset.kind, base = root.dataset.url, form = document.getElementById(kind + '-entry-form');
        const modal = $('#commercial-orders-modal'), rows = modal.find('[data-orders-rows]');
        modal.appendTo(document.body);
        const status = message => modal.find('[data-orders-status]').text(message);
        const feedback = message => $(root).find('[data-order-feedback]').text(message);
        const error = xhr => xhr.responseJSON?.message || 'Order action failed. Try again.';
        const token = document.querySelector('meta[name="csrf-token"]')?.content || form?.querySelector('[name="_token"]')?.value;
        let page = 1, lastPage = 1, pending = false, generation = 0;
        const cell = value => $('<td>').text(value ?? '');
        function register() {
            const current = ++generation;
            status('Loading orders…'); rows.empty();
            $.getJSON(base, {page, party_id: form?.querySelector('[name="' + (kind === 'sale' ? 'customer_id' : 'supplier_id') + '"]')?.value || '', status: modal.find('[data-orders-all]').prop('checked') ? 'all' : 'pending'})
                .done(data => {
                    if (current !== generation) return;
                    lastPage = data.last_page; let count = 0;
                    data.data.forEach(order => {
                        if (!modal.find('[data-orders-all]').prop('checked') && (order.reversed || ['cancelled', 'fulfilled'].includes(order.status))) return;
                        count++;
                        const row = $('<tr>').append(cell(order.reference_no), cell(order.party), cell(order.status), cell(order.remaining_delivery_base), cell(order.remaining_billing_base));
                        const actions = $('<td>');
                        $('<button type="button" class="btn btn-sm btn-light mr-1">Details</button>').on('click', () => details(order.id)).appendTo(actions);
                        if (!order.reversed && order.remaining_billing_base > 0) $('<button type="button" class="btn btn-sm btn-outline-primary mr-1">Load to bill</button>').on('click', () => load(order.id)).appendTo(actions);
                        if (!order.reversed && (order.remaining_billing_base > 0 || order.remaining_delivery_base > 0)) $('<button type="button" class="btn btn-sm btn-outline-danger">Cancel remainder</button>').on('click', () => {
                            const cancel = modal.find('[data-order-cancel]').prop('hidden', false);
                            cancel.find('[name="order_id"]').val(order.id);
                            cancel.find('[name="business_date"]').val(form?.querySelector('[name="created_at"], [name="business_date"]')?.value || new Date().toLocaleDateString('en-CA'));
                            cancel.find('[name="reason"]').val('').trigger('focus');
                        }).appendTo(actions);
                        row.append(actions).appendTo(rows);
                    });
                    status(count ? 'Page ' + page + ' of ' + lastPage : 'No orders on this page.');
                    modal.find('[data-orders-prev]').prop('disabled', page <= 1);
                    modal.find('[data-orders-next]').prop('disabled', page >= lastPage);
                }).fail(xhr => current === generation && status(error(xhr)));
        }
        function details(id) {
            $.getJSON(base + '/' + id).done(({data}) => {
                const container = modal.find('[data-order-lines]').empty();
                const edit = $('<form>');
                const table = $('<table class="table table-sm">').append('<thead><tr><th>Item</th><th>Unit</th><th>Ordered</th><th>Rate</th><th>Delivered (base)</th><th>Billed (base)</th><th>Cancelled (base)</th></tr></thead>');
                const body = $('<tbody>');
                data.items.forEach(item => {
                    const quantity = $('<input type="number" class="form-control form-control-sm" step="0.0001" min="0.0001" required>').val(item.qty).attr('aria-label', 'Ordered quantity for ' + (item.product?.name || item.product_id));
                    const rate = $('<input type="number" class="form-control form-control-sm" step="0.0001" min="0" required>').val(item.unit_rate).attr('aria-label', 'Order rate for ' + (item.product?.name || item.product_id));
                    rate.prop('readOnly', item.delivered_qty_base > 0 || item.billed_qty_base > 0);
                    const row = $('<tr>').attr('data-order-line', item.id).append(cell(item.product?.name), cell(item.unit?.unit_name || item.uom_id), $('<td>').append(quantity), $('<td>').append(rate), cell(item.delivered_qty_base), cell(item.billed_qty_base), cell(item.cancelled_qty_base));
                    body.append(row);
                });
                table.append(body).appendTo(edit);
                if (data.items.length && !data.order.reversed_at && data.order.status !== 'cancelled') {
                    const date = $('<input type="date" name="business_date" class="form-control" required>').val(form?.querySelector('[name="created_at"], [name="business_date"]')?.value || new Date().toLocaleDateString('en-CA'));
                    const reason = $('<input name="reason" class="form-control" required minlength="3" maxlength="500">');
                    $('<label class="mr-2">Amendment date</label>').append(date).appendTo(edit);
                    $('<label class="mr-2">Reason</label>').append(reason).appendTo(edit);
                    $('<button type="submit" class="btn btn-sm btn-outline-primary">Save order changes</button>').appendTo(edit);
                    const key = crypto.randomUUID();
                    edit.on('submit', function (event) {
                        event.preventDefault(); if (pending || !this.reportValidity()) return;
                        const items = edit.find('[data-order-line]').toArray().map(row => ({order_item_id: Number(row.dataset.orderLine), qty: row.querySelectorAll('input')[0].value, unit_rate: row.querySelectorAll('input')[1].value}));
                        pending = true;
                        $.ajax({url: base + '/' + id, method: 'PUT', headers: {'X-CSRF-TOKEN': token}, data: {items, business_date: date.val(), reason: reason.val(), version: data.order.attributes_json?.order_version || 1, idempotency_key: key}})
                            .done(() => { register(); details(id); status('Order changes saved.'); }).fail(xhr => status(error(xhr))).always(() => { pending = false; });
                    });
                }
                edit.appendTo(container);
                data.events.forEach(event => $('<p class="small">').text(event.created_at + ' · ' + event.event + ' · ' + event.reason).appendTo(container));
                modal.find('[data-order-detail]').prop('hidden', false);
            }).fail(xhr => status(error(xhr)));
        }
        function load(id) {
            const grid = window.commandCenterGrid;
            if (!grid || !window.zoloDocumentWorkspace) { status('Document workspace is still loading.'); return; }
            $.getJSON(base + '/' + id).done(async ({data}) => {
                const open = data.items.filter(item => item.remaining_qty > 0);
                if (!open.length) { status('Order has no remaining billing quantity.'); return; }
                if (!(await window.zoloDocumentWorkspace.newDocument({}))) return;
                grid.switchWorkspaceMode?.('voucher');
                const order = data.order, field = kind === 'sale' ? 'sales_order_id' : 'purchase_order_id';
                $(form).find('[name="sales_order_id"], [name="purchase_order_id"]').remove();
                $('<input type="hidden">').attr('name', field).val(order.id).appendTo(form);
                const party = kind === 'sale' ? 'customer_id' : 'supplier_id';
                $(form).find('[name="' + party + '"]').val(order[party]).trigger('change');
                $(form).find('[name="warehouse_id"]').val(order.warehouse_id).trigger('change');
                $('#order-table-body .order-item-row, #order-table-body .empty-placeholder-row').remove();
                open.forEach(item => {
                    grid.addProductRow({preserve_line: true, product_id: item.product_id, product_name: item.product?.name,
                        product_code: item.product?.code, qty: item.remaining_qty, price: item.unit_rate, cost: item.unit_rate, rate: item.unit_rate,
                        unit: item.unit?.unit_name, variant_id: item.variant_id,
                        product_batch_id: item.product_batch_id, batch_no: item.batch_no});
                    $('#order-table-body tr.order-item-row').last().attr('data-order-item-id', item.id).attr('data-variant-id', item.variant_id || '').attr('data-order-batch-no', item.batch_no || '');
                });
                window.zoloDocumentWorkspace.config.context = {[field]: Number(order.id)};
                grid.recalcTableSummary?.(); $(form).trigger('change'); window.zoloDocumentWorkspace.touch(); modal.modal('hide'); feedback('Loaded ' + order.reference_no + '. Review quantities before saving.');
            }).fail(xhr => status(error(xhr)));
        }
        $('[data-order-register]').on('click', event => { event.preventDefault(); page = 1; modal.modal('show'); register(); });
        modal.find('[data-orders-all], [data-orders-refresh]').on('change click', register);
        modal.find('[data-orders-prev]').on('click', () => { if (page > 1) { page--; register(); } });
        modal.find('[data-orders-next]').on('click', () => { if (page < lastPage) { page++; register(); } });
        modal.find('[data-order-cancel]').on('submit', function (event) {
            event.preventDefault(); if (pending || !this.reportValidity()) return;
            pending = true;
            $.ajax({url: base + '/' + this.order_id.value + '/cancel', method: 'POST', headers: {'X-CSRF-TOKEN': token}, data: {business_date: this.business_date.value, reason: this.reason.value}})
                .done(() => { this.hidden = true; register(); }).fail(xhr => status(error(xhr))).always(() => { pending = false; });
        });
        $(root).find('[data-order-save]').on('click', function () {
            if (pending || !form) return;
            if (Number(form.querySelector('[name="paid_amount"]')?.value || 0) !== 0) { feedback('Orders cannot take payment. Set paid amount to zero.'); return; }
            if ($(form).find('[name="sale_id"], [name="purchase_id"], [name="sales_order_id"], [name="purchase_order_id"], [name="delivery_challan_id"], [name="goods_received_note_id"]').filter(function () { return !!this.value; }).length) { feedback('Start a new unlinked entry before creating an order.'); return; }
            if (kind === 'purchase') {
                const select = form.querySelector('[name="status"]');
                if (!select) { feedback('Purchase status control is unavailable.'); return; }
                $(select).val('4').trigger('change'); form.requestSubmit(); return;
            }
            if (!form.reportValidity()) return;
            const payload = new FormData(form);
            payload.set('paid_amount', '0'); payload.set('idempotency_key', form.dataset.orderKey ||= crypto.randomUUID());
            const draftId = window.zoloDocumentWorkspace?.activeDraftId?.();
            if (draftId) payload.set('draft_id', draftId);
            pending = true; $(this).prop('disabled', true); feedback('Saving order…');
            $.ajax({url: base, method: 'POST', data: payload, processData: false, contentType: false, headers: {'X-CSRF-TOKEN': token, Accept: 'application/json'}})
                .done(({data}) => { feedback('Saved ' + data.reference_no); window.zoloDocumentWorkspace?.leave?.(); window.location.href = form.action; })
                .fail(xhr => feedback(error(xhr))).always(() => { pending = false; $(this).prop('disabled', false); });
        });
        const linked = new URLSearchParams(window.location.search).get(kind === 'sale' ? 'sales_order_id' : 'purchase_order_id');
        if (linked) load(Number(linked));
        else if (new URLSearchParams(window.location.search).get('orders') === '1') $('[data-order-register]').first().trigger('click');
    });
})(window.jQuery);

(() => {
    'use strict';
    const form = document.getElementById('accounting-voucher-form');
    if (!form) return;
    const body = document.getElementById('voucher-lines');
    const button = document.getElementById('post-voucher');
    const mode = document.getElementById('reference-mode');
    const allocations = document.getElementById('voucher-allocations');
    const bills = [...document.querySelectorAll('[data-bill-id]')];
    // The shared layout enhances every select. Dynamic bill options and shortcuts use native controls.
    window.jQuery(() => {
        if (window.jQuery.fn.selectpicker) window.jQuery('#accounting-voucher-form select, #allocation-modal select').selectpicker('destroy');
    });
    const units = value => {
        const match = String(value || '0').match(/^(\d{1,14})(?:\.(\d{1,4}))?$/);
        return match ? BigInt(match[1]) * 10000n + BigInt((match[2] || '').padEnd(4, '0')) : null;
    };
    const decimal = value => `${value < 0n ? '-' : ''}${(value < 0n ? -value : value) / 10000n}.${String((value < 0n ? -value : value) % 10000n).padStart(4, '0')}`;
    let sequence = 0;

    function update() {
        let debit = 0n, credit = 0n, valid = true;
        [...body.rows].forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(field => {
                field.name = `items[${index}][${field.dataset.field}]`;
                field.setAttribute('aria-label', `${field.dataset.field.replaceAll('_', ' ')} for line ${index + 1}`);
            });
            const account = row.querySelector('[data-field="chart_of_account_id"]');
            const party = row.querySelector('[data-party]');
            party.setAttribute('aria-label', `Party for line ${index + 1}`);
            const control = account.selectedOptions[0]?.dataset.control;
            const expected = control === 'ar' ? 'customer' : control === 'ap' ? 'supplier' : '';
            if (expected && party.value && !party.value.startsWith(`${expected}:`)) party.value = '';
            party.required = !!expected;
            [...party.options].forEach(option => { option.hidden = !!option.value && !!expected && !option.value.startsWith(`${expected}:`); });
            const [partyType, partyId] = party.value.split(':');
            row.querySelector('[data-field="partner_type"]').value = partyType || '';
            row.querySelector('[data-field="partner_id"]').value = partyId || '';
            const d = units(row.querySelector('[data-field="debit"]').value);
            const c = units(row.querySelector('[data-field="credit"]').value);
            debit += d ?? 0n; credit += c ?? 0n;
            valid = valid && !!account.value && d !== null && c !== null && d >= 0 && c >= 0 && (d > 0 || c > 0) && !(d > 0 && c > 0);
            row.querySelector('[data-remove]').disabled = body.rows.length <= 2;
        });
        document.getElementById('voucher-balance').textContent = `Debit ${decimal(debit)} · Credit ${decimal(credit)} · Difference ${decimal(debit - credit)}`;
        button.disabled = !valid || debit <= 0 || debit !== credit || (mode.value === 'against_reference' && !allocations.children.length);
        document.getElementById('choose-bills').hidden = mode.value !== 'against_reference';
    }

    function addLine(data = {}) {
        const row = document.getElementById('voucher-line-template').content.cloneNode(true).querySelector('tr');
        row.dataset.rowId = String(++sequence);
        row.querySelectorAll('[data-field]').forEach(field => { if (data[field.dataset.field] != null) field.value = data[field.dataset.field]; });
        if (data.partner_type && data.partner_id) row.querySelector('[data-party]').value = `${data.partner_type}:${data.partner_id}`;
        body.append(row); update();
        return row;
    }

    function clearAllocations() {
        allocations.replaceChildren();
        document.getElementById('allocation-summary').textContent = '';
    }

    document.getElementById('add-voucher-line').addEventListener('click', () => { clearAllocations(); addLine().querySelector('select').focus(); });
    body.addEventListener('click', event => {
        if (event.target.matches('[data-remove]') && body.rows.length > 2) {
            event.target.closest('tr').remove(); clearAllocations(); update();
            body.rows[0].querySelector('select').focus();
        }
    });
    body.addEventListener('input', () => { clearAllocations(); update(); });
    mode.addEventListener('change', () => { clearAllocations(); update(); });
    document.getElementById('choose-bills').addEventListener('click', () => {
        bills.forEach(bill => {
            const select = bill.querySelector('[data-bill-line]');
            select.replaceChildren(new Option('Skip bill', ''));
            bill.querySelector('[data-bill-amount]').value = '0';
            [...body.rows].forEach((row, index) => {
                const account = row.querySelector('[data-field="chart_of_account_id"]');
                const control = account.selectedOptions[0]?.dataset.control;
                const signed = (control === 'ap' ? -1n : 1n) * ((units(row.querySelector('[data-field="debit"]').value) ?? 0n) - (units(row.querySelector('[data-field="credit"]').value) ?? 0n));
                if (account.value === bill.dataset.accountId && row.querySelector('[data-party]').value === bill.dataset.party && (signed > 0n) !== (Number(bill.dataset.open) > 0) && signed !== 0n) {
                    select.add(new Option(`Line ${index + 1}`, String(index)));
                }
            });
        });
    });
    document.getElementById('apply-allocations').addEventListener('click', () => {
        clearAllocations();
        let count = 0;
        bills.forEach(bill => {
            const line = bill.querySelector('[data-bill-line]').value;
            const amount = bill.querySelector('[data-bill-amount]');
            if (line !== '' && amount.checkValidity() && units(amount.value) > 0) {
                Object.entries({ open_item_id: bill.dataset.billId, line_index: line, amount: amount.value }).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden'; input.name = `allocations[${count}][${key}]`; input.value = value;
                    allocations.append(input);
                });
                count++;
            }
        });
        document.getElementById('allocation-summary').textContent = `${count} bill allocation(s) selected.`;
        update();
        window.jQuery('#allocation-modal').modal('hide');
        document.getElementById('choose-bills').focus();
    });
    document.getElementById('narration-template').addEventListener('change', event => {
        if (event.target.value) document.getElementById('voucher-description').value = event.target.value;
    });
    document.getElementById('cheque-no').addEventListener('input', event => { document.getElementById('cheque-date').required = !!event.target.value; });
    document.addEventListener('keydown', event => {
        const types = { F4: 'contra', F5: 'payment', F6: 'receipt', F7: 'journal' };
        if (types[event.key] && !document.querySelector('.modal.show')) {
            event.preventDefault(); document.getElementById('voucher-type').value = types[event.key];
            document.getElementById('voucher-description').focus();
        } else if (event.key === 'F9') {
            event.preventDefault(); document.getElementById('voucher-hub').focus();
        } else if (event.ctrlKey && event.key === 'Enter' && !button.disabled && !document.querySelector('.modal.show')) {
            event.preventDefault(); form.requestSubmit();
        }
    });
    const previous = JSON.parse(document.getElementById('previous-voucher-lines').textContent);
    (previous.length ? previous : [{}, {}]).forEach(addLine);
})();

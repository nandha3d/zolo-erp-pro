(() => {
    'use strict';
    const form = document.getElementById('accounting-voucher-form');
    if (!form) return;
    const body = document.getElementById('voucher-lines');
    const button = document.getElementById('post-voucher');
    const mode = document.getElementById('reference-mode');
    const allocations = document.getElementById('voucher-allocations');
    const type = document.getElementById('voucher-type');
    const from = document.getElementById('transfer-from');
    const to = document.getElementById('transfer-to');
    const amount = document.getElementById('transfer-amount');
    const advanced = document.getElementById('transfer-advanced');
    const transfer = () => type.value === 'contra';
    const simpleTransfer = () => transfer() && !advanced.checked;
    const bills = [...document.querySelectorAll('[data-bill-id]')];
    const guidedFields = document.getElementById('guided-transfer-fields');
    const advancedMessage = document.getElementById('advanced-transfer-help');
    const narrationTemplate = document.getElementById('narration-template');
    const units = value => {
        const match = String(value || '0').match(/^(\d{1,14})(?:\.(\d{1,4}))?$/);
        return match ? BigInt(match[1]) * 10000n + BigInt((match[2] || '').padEnd(4, '0')) : null;
    };
    const decimal = value => `${value < 0n ? '-' : ''}${(value < 0n ? -value : value) / 10000n}.${String((value < 0n ? -value : value) % 10000n).padStart(4, '0')}`;
    const currency = new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 4});
    let sequence = 0;

    function update() {
        let debit = 0n, credit = 0n, settlementDebit = 0n, settlementCredit = 0n, valid = true, hasPartyAccount = false;
        [...body.rows].forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(field => {
                field.name = `items[${index}][${field.dataset.field}]`;
                field.setAttribute('aria-label', `${field.dataset.field.replaceAll('_', ' ')} for line ${index + 1}`);
            });
            const account = row.querySelector('[data-field="chart_of_account_id"]');
            const party = row.querySelector('[data-party]');
            party.setAttribute('aria-label', `Party for line ${index + 1}`);
            const control = account.selectedOptions[0]?.dataset.control;
            [...account.options].forEach(option => {
                const settlement = ['cash', 'bank'].includes(option.dataset.control);
                const allowed = !option.value || (transfer() ? settlement && option.dataset.manual !== '0'
                    : option.dataset.manual !== '0' || ['ar', 'ap'].includes(option.dataset.control));
                option.hidden = option.disabled = !allowed;
            });
            const expected = !transfer() && (control === 'ar' ? 'customer' : control === 'ap' ? 'supplier' : '');
            hasPartyAccount ||= !!expected;
            if (!expected || !party.value.startsWith(`${expected}:`)) party.value = '';
            party.hidden = party.disabled = !expected;
            party.required = !!expected;
            party.options[0].textContent = expected ? `Choose ${expected}` : 'No party';
            row.querySelector('[data-party-hint]').hidden = !!expected;
            row.querySelector('[data-party-column]').hidden = transfer();
            [...party.options].slice(1).forEach(option => {
                option.hidden = option.disabled = !option.value.startsWith(`${expected}:`);
            });
            const [partyType, partyId] = party.value.split(':');
            row.querySelector('[data-field="partner_type"]').value = partyType || '';
            row.querySelector('[data-field="partner_id"]').value = partyId || '';
            const d = units(row.querySelector('[data-field="debit"]').value);
            const c = units(row.querySelector('[data-field="credit"]').value);
            debit += d ?? 0n; credit += c ?? 0n;
            if (['cash', 'bank'].includes(control)) { settlementDebit += d ?? 0n; settlementCredit += c ?? 0n; }
            valid = valid && !!account.value && !account.selectedOptions[0]?.disabled && (!expected || !!party.value) && d !== null && c !== null && d >= 0 && c >= 0 && (d > 0 || c > 0) && !(d > 0 && c > 0);
            row.querySelector('[data-remove]').disabled = body.rows.length <= 2;
        });
        let directionError = '';
        if (type.value === 'payment' && (settlementCredit <= 0n || settlementDebit > 0n)) directionError = 'A payment must credit cash or bank.';
        if (type.value === 'receipt' && (settlementDebit <= 0n || settlementCredit > 0n)) directionError = 'A receipt must debit cash or bank.';
        document.getElementById('voucher-balance').textContent = valid && debit > 0n && debit === credit && directionError
            ? directionError : `Debit ${decimal(debit)} · Credit ${decimal(credit)} · Difference ${decimal(debit - credit)}`;
        if (transfer()) {
            const distinct = new Set([...body.rows].map(row => row.querySelector('[data-field="chart_of_account_id"]').value));
            valid = valid && distinct.size >= 2;
            if (simpleTransfer()) valid = valid && !!from.value && !!to.value && from.value !== to.value && (units(amount.value) ?? 0n) > 0n;
        }
        if (!hasPartyAccount && !transfer() && mode.value === 'against_reference') { mode.value = 'new_reference'; clearAllocations(); }
        button.disabled = !valid || !!directionError || debit <= 0 || debit !== credit || (mode.value === 'against_reference' && !allocations.children.length);
        document.getElementById('voucher-allocation-mode').hidden = transfer() || !hasPartyAccount;
        document.getElementById('voucher-due-field').hidden = transfer() || !hasPartyAccount;
        document.getElementById('choose-bills').hidden = transfer() || mode.value !== 'against_reference' || !hasPartyAccount;
    }

    function transferPreview() {
        const same = from.value && from.value === to.value;
        to.setCustomValidity(same ? 'Choose a destination different from the source account.' : '');
        const value = units(amount.value);
        document.getElementById('transfer-preview').textContent = same ? 'Source and destination must be different accounts.'
            : from.value && to.value && value > 0n
                ? `${from.selectedOptions[0].textContent} decreases by ${currency.format(decimal(value))}. ${to.selectedOptions[0].textContent} increases by ${currency.format(decimal(value))}. Total money stays the same.`
                : 'Choose two different accounts and enter the amount.';
    }

    function syncTransfer() {
        if (!simpleTransfer()) return;
        while (body.rows.length < 2) addLine();
        const values = [
            {chart_of_account_id: from.value, debit: '0', credit: amount.value || '0'},
            {chart_of_account_id: to.value, debit: amount.value || '0', credit: '0'},
        ];
        values.forEach((values, index) => Object.entries(values).forEach(([field, value]) => {
            body.rows[index].querySelector(`[data-field="${field}"]`).value = value;
        }));
        clearAllocations(); transferPreview(); update();
    }

    function readTransferLines() {
        if (body.rows.length !== 2) return false;
        const rows = [...body.rows];
        const source = rows.find(row => (units(row.querySelector('[data-field="credit"]').value) ?? 0n) > 0n);
        const destination = rows.find(row => (units(row.querySelector('[data-field="debit"]').value) ?? 0n) > 0n);
        if (!source || !destination) return false;
        if (units(source.querySelector('[data-field="credit"]').value) !== units(destination.querySelector('[data-field="debit"]').value)) return false;
        const sourceAccount = source.querySelector('[data-field="chart_of_account_id"]').value;
        const destinationAccount = destination.querySelector('[data-field="chart_of_account_id"]').value;
        if (![...from.options].some(option => option.value === sourceAccount) || ![...to.options].some(option => option.value === destinationAccount)) return false;
        from.value = sourceAccount; to.value = destinationAccount;
        amount.value = source.querySelector('[data-field="credit"]').value;
        body.append(source, destination);
        return true;
    }

    function configureType() {
        document.querySelector('.accounting-command-bar h1').textContent = transfer() ? 'Money Transfer' : 'Voucher Hub';
        document.querySelector('.accounting-purpose').textContent = transfer()
            ? 'Move money between your cash and bank accounts. No income or expense is created.'
            : 'Record payments, receipts and adjustments. Use bill payment screens when settling a specific sales or purchase bill.';
        document.getElementById('transfer-entry').hidden = !transfer();
        guidedFields.hidden = !simpleTransfer();
        advancedMessage.hidden = !transfer() || !advanced.checked;
        advancedMessage.textContent = 'Credit the cash or bank account sending money and debit the account receiving it.';
        document.querySelector('#voucher-line-editor thead [data-party-column]').hidden = transfer();
        document.getElementById('voucher-type-help').hidden = transfer();
        document.getElementById('voucher-type-help').textContent = {
            payment: 'Money leaves cash or bank: credit that account and debit the expense or supplier account. Choose a supplier only for Payables.',
            receipt: 'Money enters cash or bank: debit that account and credit the income or customer account. Choose a customer only for Receivables.',
            journal: 'Record an adjustment with equal debits and credits. Assets and expenses increase with debits; liabilities, equity and income increase with credits.',
        }[type.value] || '';
        from.required = to.required = amount.required = simpleTransfer();
        from.disabled = to.disabled = amount.disabled = !simpleTransfer();
        document.getElementById('voucher-line-editor').hidden = simpleTransfer();
        document.getElementById('voucher-line-actions').hidden = simpleTransfer();
        button.textContent = transfer() ? 'Post Transfer (Ctrl+Enter)' : 'Post Voucher (Ctrl+Enter)';
        [...narrationTemplate.options].slice(1).forEach(option => {
            option.hidden = option.disabled = option.dataset.voucherType !== type.value;
        });
        if (narrationTemplate.selectedOptions[0]?.disabled) narrationTemplate.value = '';
        if (transfer()) { mode.value = 'on_account'; clearAllocations(); }
        else to.setCustomValidity('');
        transferPreview(); update();
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
    body.addEventListener('change', () => { clearAllocations(); update(); });
    [from, to, amount].forEach(field => {
        field.addEventListener('input', syncTransfer);
        field.addEventListener('change', syncTransfer);
    });
    function prepareGuidedTransfer() {
        if (readTransferLines()) return true;
        const hasDetails = [...body.rows].some(row => row.querySelector('[data-field="memo"]').value.trim()
            || [...row.querySelectorAll('[data-field="debit"], [data-field="credit"]')].some(field => units(field.value) !== 0n));
        if (hasDetails) return false;
        while (body.rows.length > 2) body.deleteRow(-1);
        from.value = to.value = amount.value = '';
        return true;
    }

    advanced.addEventListener('change', () => {
        const cannotSimplify = !advanced.checked && !prepareGuidedTransfer();
        if (cannotSimplify) advanced.checked = true;
        configureType();
        if (cannotSimplify) advancedMessage.textContent = 'Guided entry supports two lines with equal amounts. Finish these lines here, or clear their amounts and memos to switch.';
        else syncTransfer();
    });
    type.addEventListener('change', () => {
        if (transfer()) advanced.checked = !prepareGuidedTransfer();
        clearAllocations(); configureType();
        syncTransfer();
    });
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
            event.preventDefault(); type.value = types[event.key]; type.dispatchEvent(new Event('change'));
            document.getElementById('voucher-description').focus();
        } else if (event.key === 'F9' && !event.ctrlKey && !event.altKey && !event.shiftKey) {
            event.preventDefault(); type.focus();
        } else if (event.ctrlKey && event.key === 'Enter' && !button.disabled && !document.querySelector('.modal.show')) {
            event.preventDefault(); form.requestSubmit();
        }
    });
    const previous = JSON.parse(document.getElementById('previous-voucher-lines').textContent);
    (previous.length ? previous : [{}, {}]).forEach(addLine);
    if (transfer()) advanced.checked = !prepareGuidedTransfer();
    configureType();
    if (simpleTransfer() && previous.length) syncTransfer();
})();

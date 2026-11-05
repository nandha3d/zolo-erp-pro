(() => {
    'use strict';
    const form = document.getElementById('bill-payment-form');
    if (!form) return;
    const bill = form.querySelector('[name="bill_id"]'), amount = form.querySelector('[name="amount"]');
    const method = form.querySelector('[name="paying_method"]'), account = form.querySelector('[name="account_id"]');
    const receive = form.dataset.direction === 'receive';
    const cash = () => method.value === 'Cash';
    const refresh = select => {
        if (window.jQuery?.fn.selectpicker && window.jQuery(select).data('selectpicker')) window.jQuery(select).selectpicker('refresh');
    };
    const money = value => new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', maximumFractionDigits: 2}).format(value);
    const due = () => Number(bill.selectedOptions[0]?.dataset.due || 0);
    function preview() {
        const discount = Number(form.querySelector('[name="discount"]').value || 0);
        const tds = Number(form.querySelector('[name="tds_amount"]')?.value || 0);
        const paid = Number(amount.value || 0), remaining = due() - paid - discount - tds;
        const selected = account.selectedOptions[0];
        amount.setCustomValidity(remaining < -0.00005 ? 'Payment, discount and TDS exceed this bill’s due amount.' : '');
        document.getElementById('payment-due-preview').textContent = bill.value ? money(Math.max(0, remaining)) : '—';
        document.getElementById('payment-flow-preview').textContent = bill.value && account.value
            ? money(paid) + (receive ? ' received into ' : ' paid from ') + selected.dataset.name + '. ' + bill.selectedOptions[0].dataset.party + '’s due ' + 'reduces by ' + money(paid + discount + tds) + '.'
            : 'Choose a bill, method and account to preview the linked entry.';
        document.getElementById('payment-account-help').textContent = account.value
            ? 'Posts to ' + selected.dataset.ledger + (receive ? '. This account receives the money.' : '. Money leaves this account.')
            : 'Choose the actual ' + (cash() ? 'cash' : 'bank') + ' account holding this money.';
    }
    function filterAccounts() {
        const kind = cash() ? 'cash' : 'bank';
        const available = [...account.options].filter(option => {
            if (!option.value) return false;
            option.hidden = option.disabled = option.dataset.kind !== kind;
            return !option.disabled;
        });
        if (account.selectedOptions[0]?.disabled) account.value = '';
        if (!account.value && available.length === 1) account.value = available[0].value;
        account.options[0].textContent = 'Choose ' + (cash() ? 'cash' : 'bank') + ' account';
        document.getElementById('payment-account-label').textContent = (receive ? 'Receive into · ' : 'Pay from · ') + (cash() ? 'cash account *' : 'bank account *');
        document.getElementById('payment-account-empty').hidden = available.length > 0;
        document.getElementById('payment-generic-bank').hidden = kind !== 'bank' || available.length !== 1 || !/^bank(?: accounts?)?$/i.test(available[0].dataset.name);
        refresh(account);
        preview();
    }
    function selectBill(fill) {
        amount.max = due();
        if (fill) amount.value = due() > 0 ? due().toFixed(4).replace(/0{1,2}$/, '') : '';
        refresh(bill);
        document.getElementById('payment-bill-due').textContent = bill.value ? 'Outstanding: ' + money(due()) : '';
        document.querySelectorAll('[data-payment-bill-row]').forEach(row => row.classList.toggle('payment-bill-selected', row.dataset.paymentBillRow === bill.value));
        preview();
    }
    bill.addEventListener('change', () => selectBill(true));
    method.addEventListener('change', filterAccounts);
    form.addEventListener('input', preview);
    account.addEventListener('change', () => { refresh(account); preview(); });
    document.querySelectorAll('[data-pay-bill]').forEach(button => button.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); bill.value = button.dataset.payBill; selectBill(true);
        document.getElementById('payment-entry').scrollIntoView({behavior: 'smooth', block: 'nearest'});
        amount.focus(); amount.select();
    }));
    filterAccounts(); selectBill(!amount.value);
    form.addEventListener('submit', event => {
        preview();
        if (!form.reportValidity()) { event.preventDefault(); return; }
        form.querySelector('[type="submit"]').disabled = true;
    });

    const dialog = document.getElementById('payment-account-dialog');
    if (!dialog) return;
    const accountForm = document.getElementById('payment-account-form');
    document.getElementById('payment-add-account').addEventListener('click', () => {
        accountForm.reset(); accountForm.elements.kind.value = cash() ? 'cash' : 'bank';
        document.getElementById('payment-account-status').textContent = ''; dialog.showModal();
        accountForm.elements.name.focus();
    });
    dialog.querySelectorAll('[data-close-account]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => document.getElementById('payment-add-account').focus());
    accountForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!accountForm.reportValidity()) return;
        const save = accountForm.querySelector('[type="submit"]'), status = document.getElementById('payment-account-status');
        save.disabled = true; status.textContent = 'Creating linked account…';
        try {
            const response = await fetch(accountForm.action, {method: 'POST', headers: {Accept: 'application/json'}, body: new FormData(accountForm)});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Unable to create account.');
            const created = result.data;
            let option = [...account.options].find(option => option.value === String(created.id));
            if (!option) { option = new Option(created.name + (created.last_four ? ' · •••• ' + created.last_four : ''), created.id); account.add(option); }
            Object.assign(option.dataset, {name: created.name, kind: created.kind, ledger: created.ledger_name});
            method.value = created.kind === 'cash' ? 'Cash' : (cash() ? 'Bank' : method.value);
            refresh(method);
            account.value = String(created.id); filterAccounts(); dialog.close();
        } catch (error) { status.textContent = error.message; }
        finally { save.disabled = false; }
    });
})();

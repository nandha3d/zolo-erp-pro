(() => {
    'use strict';
    const groups = [...document.querySelectorAll('.accounting-nav-group')];
    const active = document.querySelector('[data-accounting-active]')?.dataset.accountingActive;
    const sidebarIds = { 'voucher-entry': 'voucher-entry-menu', vouchers: 'voucher-entry-menu', transfers: 'money-transfer-menu', coa: 'coa-menu', journals: 'journal-menu', ledger: 'general-ledger-menu', 'monthly-ledger': 'general-ledger-menu', 'trial-balance': 'trial-balance-menu', 'profit-loss': 'profit-loss-menu', 'balance-sheet': 'balance-sheet-menu', 'cash-flow': 'cash-flow-menu', 'inventory-close': 'inv-close-menu', mappings: 'semantic-map-menu', accounts: 'account-list-menu' };
    const sidebar = document.getElementById('account');
    if (sidebar) {
        sidebar.classList.add('show');
        sidebar.querySelectorAll('li.active').forEach(item => item.classList.remove('active'));
        document.getElementById(sidebarIds[active])?.classList.add('active');
        document.querySelector('a[href="#account"]')?.setAttribute('aria-expanded', 'true');
    }
    groups.forEach(group => group.addEventListener('toggle', () => {
        if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
    }));
    document.addEventListener('click', event => {
        if (!event.target.closest('.accounting-navigation')) groups.forEach(group => { group.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') groups.forEach(group => {
            if (group.open) { group.open = false; group.querySelector('summary').focus(); }
        });
    });
    function revealAnchor() {
        const id = window.location.hash.slice(1);
        const target = id && document.getElementById(id);
        if (target) {
            if (target.matches('details')) target.open = true;
            for (let parent = target.parentElement; parent; parent = parent.parentElement) {
                if (parent.matches('details')) parent.open = true;
            }
            target.scrollIntoView({block: 'start'});
        }
    }
    window.addEventListener('hashchange', revealAnchor);
    revealAnchor();
})();

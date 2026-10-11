/* Enter advances document entry; Tab and Shift+Tab keep the browser's normal focus order. */
(() => {
    'use strict';
    const forms = '#sale-entry-form, #purchase-entry-form, #challan-entry-form, #grn-entry-form, .classic-document-workspace form, #exchange-form, #return-form, #accounting-voucher-form, #voucherEntryForm';
    const controls = 'input, select, textarea, button, a[href], summary';
    const visible = element => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const editable = element => !element.disabled && !element.readOnly && element.tabIndex >= 0
        && !element.closest('[hidden], [inert]') && visible(element);

    if (window.jQuery) {
        const openers = new WeakMap();
        window.jQuery(document).on('show.bs.modal', '.modal', function (event) {
            if (document.querySelector(forms)) openers.set(this, event.relatedTarget || document.activeElement);
        }).on('hidden.bs.modal', '.modal', function () {
            const opener = openers.get(this);
            openers.delete(this);
            if (opener && opener.isConnected && visible(opener)) opener.focus();
        });
    }

    document.addEventListener('invalid', event => {
        if (!event.target.closest(forms)) return;
        const details = event.target.closest('details');
        if (details) details.open = true;
    }, true);

    document.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.defaultPrevented || event.isComposing || event.keyCode === 229
            || event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
        const target = event.target;
        const form = target.closest(forms);
        if (!form || document.querySelector('dialog[open], .modal.show, [data-owns-keys]')) return;
        if (!target.matches('input, select') || ['submit', 'button', 'reset', 'image', 'file'].includes(target.type)) return;
        if (target.closest('.bootstrap-select, .bs-container, .dropdown-menu, [role="combobox"], [role="listbox"]')
            || target.getAttribute('aria-expanded') === 'true') return;

        // Existing product autocomplete handlers run first and own selection, including Enter.
        event.preventDefault();
        const fields = Array.from(form.querySelectorAll(controls));
        const index = fields.indexOf(target);
        const next = index < 0 ? null : fields.slice(index + 1).find(editable);
        if (!next) return;
        // Bootstrap-select leaves its button at tabindex=-1 and forwards native select focus.
        // Focus that button directly for Enter while preserving native Tab through the select.
        const toggle = next.tagName === 'SELECT' && next.closest('.bootstrap-select')?.querySelector('.dropdown-toggle');
        (toggle || next).focus();
        if (next.tagName === 'INPUT' && ['text', 'number', 'search', 'email', 'tel', 'url', 'password'].includes(next.type)) next.select();
    });
})();

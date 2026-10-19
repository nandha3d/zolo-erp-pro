/* Enter advances to the next field on every entry form (pages and modals), ending on the form's
   Save/Submit button, so a form is filled and finished from the keyboard. Tab and Shift+Tab keep the
   browser's normal focus order. Single-field forms (search boxes) and forms marked
   data-enter="submit" keep the native Enter-submits behaviour. */
(() => {
    'use strict';
    const forms = '#sale-entry-form, #purchase-entry-form, #challan-entry-form, #grn-entry-form, .classic-document-workspace form, #exchange-form, #return-form, #accounting-voucher-form, #voucherEntryForm';
    const controls = 'input, select, textarea, button, a[href], summary';
    const textual = 'input:not([type]), input[type=text], input[type=number], input[type=email], input[type=tel], input[type=url], input[type=password], input[type=date], input[type=datetime-local], input[type=time], select, textarea';
    const visible = element => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden';
    const editable = element => !element.disabled && !element.readOnly && element.tabIndex >= 0
        && !element.closest('[hidden], [inert]') && visible(element);
    // The form (or open modal) that owns Enter for this field, or null to leave Enter alone.
    const scopeFor = target => {
        const documentForm = target.closest(forms);
        if (documentForm) return documentForm;
        const form = target.closest('form');
        if (!form || form.matches('[data-enter="submit"], [role="search"]') || form.closest('nav.navbar, .side-navbar, .dataTables_wrapper')) return null;
        // A lone search/filter box: Enter should submit it, as users expect.
        return Array.from(form.querySelectorAll(textual)).filter(field => !field.disabled && field.type !== 'hidden').length > 1 ? form : null;
    };

    // Helper controls (links, "+ New Party", "Generate code", pills) are skipped by Enter so they never
    // trap it; fields and primary action buttons (Save) are still reached. Tab still visits everything.
    const helper = element => element.tagName === 'A' || element.tagName === 'SUMMARY'
        || (element.tagName === 'BUTTON' && element.type === 'button' && !/\bbtn-(primary|success)\b/.test(element.className || ''));
    // Next field after target.
    const nextField = (scope, target) => {
        const fields = Array.from(scope.querySelectorAll(controls)).filter(field => !helper(field));
        let index = fields.indexOf(target);
        for (const field of index < 0 ? [] : fields.slice(index + 1)) {
            if (editable(field)) return field;
            // Long forms split into steps (app-shell.js): continue into the next step.
            if (window.zoloSteps && !field.disabled && field.type !== 'hidden' && field.closest('[data-step-title]:not(.is-active)')
                && window.zoloSteps.reveal(field) && editable(field)) return field;
        }
        return null;
    };
    const focusField = next => {
        // Bootstrap-select leaves its button at tabindex=-1 and forwards native select focus.
        // Focus that button directly for Enter while preserving native Tab through the select.
        const toggle = next.tagName === 'SELECT' && next.closest('.bootstrap-select')?.querySelector('.dropdown-toggle');
        (toggle || next).focus();
        if (next.tagName === 'INPUT' && ['text', 'number', 'search', 'email', 'tel', 'url', 'password'].includes(next.type)) next.select();
    };

    // jQuery loads at the end of the page, after this script: attach its hooks once it is there.
    const withJQuery = hook => {
        if (window.jQuery) hook(window.jQuery);
        else document.addEventListener('DOMContentLoaded', () => window.jQuery && hook(window.jQuery));
    };

    withJQuery(jQuery => {
        const openers = new WeakMap();
        jQuery(document).on('show.bs.modal', '.modal', function (event) {
            if (document.querySelector(forms)) openers.set(this, event.relatedTarget || document.activeElement);
        }).on('hidden.bs.modal', '.modal', function () {
            const opener = openers.get(this);
            openers.delete(this);
            if (opener && opener.isConnected && visible(opener)) opener.focus();
        });
    });

    document.addEventListener('invalid', event => {
        if (!event.target.closest(forms)) return;
        const details = event.target.closest('details');
        if (details) details.open = true;
    }, true);

    document.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.defaultPrevented || event.isComposing || event.keyCode === 229
            || event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
        const target = event.target;
        const form = scopeFor(target);
        if (!form || document.querySelector('[data-owns-keys]')) return;
        // With a dialog open, only fields inside that dialog advance.
        const dialog = document.querySelector('dialog[open], .modal.show');
        if (dialog && !dialog.contains(target)) return;
        if (!target.matches('input, select') || ['submit', 'button', 'reset', 'image', 'file'].includes(target.type)) return;
        if (target.closest('.bootstrap-select, .bs-container, .dropdown-menu, [role="combobox"], [role="listbox"]')
            || target.getAttribute('aria-expanded') === 'true') return;

        // Existing product autocomplete handlers run first and own selection, including Enter.
        const scope = dialog && form.contains(dialog) ? dialog : form;
        const next = nextField(scope, target);
        // Document forms never submit on a stray Enter; other forms do when nothing follows the last field.
        if (!next && !target.closest(forms)) return;
        event.preventDefault();
        if (next) focusField(next);
    });

    // Enter on a closed bootstrap-select moves on (typing or Space opens it), so the
    // picker does not trap Enter. Capture phase: the plugin would otherwise open its menu first.
    document.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.ctrlKey || event.altKey || event.metaKey || event.shiftKey) return;
        const toggle = event.target.closest && event.target.closest('.bootstrap-select > .dropdown-toggle');
        if (!toggle || toggle.getAttribute('aria-expanded') === 'true') return;
        const select = toggle.parentElement.querySelector('select');
        const form = select && scopeFor(select);
        if (!form || select.multiple) return;
        const dialog = document.querySelector('dialog[open], .modal.show');
        if (dialog && !dialog.contains(select)) return;
        const next = nextField(dialog && form.contains(dialog) ? dialog : form, select);
        if (!next) return;
        event.preventDefault();
        event.stopPropagation();
        focusField(next);
    }, true);

    // Choosing from a single bootstrap-select (opened with Enter) moves on to the next field.
    withJQuery(jQuery => {
        // clickedIndex is only set for a user pick, not for programmatic changes. If the page itself
        // moves focus after the pick (for example to the item search), that wins.
        jQuery(document).on('changed.bs.select', 'select:not([multiple])', function (event, clickedIndex) {
            if (clickedIndex === null || clickedIndex === undefined) return;
            const select = this;
            const picker = select.closest('.bootstrap-select');
            if (!picker) return;
            const advance = () => setTimeout(() => {
                const active = document.activeElement;
                if (active && active !== document.body && !picker.contains(active)) return;
                const form = scopeFor(select);
                if (!form) return;
                const dialog = document.querySelector('dialog[open], .modal.show');
                if (dialog && !dialog.contains(select)) return;
                const next = nextField(dialog && form.contains(dialog) ? dialog : form, select);
                if (!next) return;
                focusField(next);
                // Pages refresh pickers after a party change (AJAX), which can drop focus to <body>;
                // put it back on the next field once they settle.
                [300, 900].forEach(delay => setTimeout(() => {
                    if (document.activeElement !== document.body) return;
                    const again = nextField(dialog && form.contains(dialog) ? dialog : form, select);
                    if (again) focusField(again);
                }, delay));
            }, 0);
            // The menu closes after the change event; move on once it has.
            if (picker.classList.contains('show') || picker.classList.contains('open')) jQuery(select).one('hidden.bs.select', advance);
            else advance();
        });
    });
})();

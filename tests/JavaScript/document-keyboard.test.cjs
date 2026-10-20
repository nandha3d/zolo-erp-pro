const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');

const code = readFileSync(require('node:path').join(__dirname, '../../public/js/document-keyboard.js'), 'utf8');

function fixture(formSelector = '#sale-entry-form', withModals = false, windowExtras = {}) {
    let handler, captureHandler, invalid, owner = null;
    const fields = [];
    // formSelector null = an ordinary (non-document) form found through closest('form').
    const form = { querySelectorAll: () => fields, matches: () => false, closest: () => null, contains: () => true };
    const document = {
        addEventListener(name, callback, capture) {
            if (name === 'DOMContentLoaded') return;
            if (name === 'invalid') { assert.equal(capture, true); invalid = callback; }
            else if (capture) { assert.equal(name, 'keydown'); captureHandler = callback; }
            else { assert.equal(name, 'keydown'); handler = callback; }
        },
        querySelector: selector => selector.startsWith('#sale-entry-form,') ? form : owner,
    };
    const modalHooks = {};
    const jqueryDocument = { on(event, selector, callback) { if (selector === '.modal') modalHooks[event] = callback; return this; } };
    runInNewContext(code, { document, window: withModals ? { jQuery: () => jqueryDocument, ...windowExtras } : { ...windowExtras },
        getComputedStyle: element => ({ visibility: element.visibility || 'visible' }) });
    const control = (tagName = 'INPUT', options = {}) => {
        const element = {
            tagName, type: tagName === 'INPUT' ? 'text' : '', tabIndex: 0, isConnected: true,
            getClientRects() { return this.hidden ? [] : [{}]; },
            closest(selector) {
                if (formSelector && selector.includes(formSelector)) return this.outside ? null : form;
                if (!formSelector && selector === 'form') return this.outside ? null : form;
                if (selector === '[data-step-title]:not(.is-active)') return this.step && !this.step.active ? this.step : null;
                if (selector === '[hidden], [inert]') return this.hiddenAncestor ? {} : null;
                if (selector === '.bootstrap-select') return this.enhanced ? { querySelector: () => this.toggle || null } : null;
                if (selector === 'details') return this.details || null;
                return this.ownsKeys ? {} : null;
            },
            matches: selector => selector.split(', ').includes(tagName.toLowerCase()),
            getAttribute: name => name === 'aria-expanded' && element.expanded ? 'true' : null,
            focus() { document.activeElement = this; },
            select() { this.selected = true; },
            ...options,
        };
        fields.push(element);
        return element;
    };
    const press = (target, options = {}) => {
        const event = { key: 'Enter', target, defaultPrevented: false,
            preventDefault() { this.defaultPrevented = true; }, ...options };
        handler(event);
        return event;
    };
    return { document, control, press, modalHooks, invalid: target => invalid({ target }), ownKeys: value => { owner = value; } };
}

test('Enter advances every document form without submitting, including dynamically added fields', () => {
    for (const selector of ['#sale-entry-form', '#purchase-entry-form', '#challan-entry-form', '#grn-entry-form',
        '.classic-document-workspace form', '#exchange-form', '#return-form', '#accounting-voucher-form', '#voucherEntryForm']) {
        const f = fixture(selector);
        const first = f.control(), next = f.control('INPUT', { type: 'number' });
        assert.equal(f.press(first).defaultPrevented, true, selector);
        assert.equal(f.document.activeElement, next, selector);
        assert.equal(next.selected, true);
        const last = f.control('BUTTON', { type: 'submit' });
        f.press(next);
        assert.equal(f.document.activeElement, last);
        assert.equal(f.press(last).defaultPrevented, false, 'Save button retains explicit Enter activation');
    }
});

test('Enter skips hidden, disabled, readonly and inaccessible fields; focuses enhanced select toggle', () => {
    const f = fixture(), first = f.control();
    f.control('INPUT', { hidden: true });
    f.control('INPUT', { disabled: true });
    f.control('INPUT', { readOnly: true });
    f.control('INPUT', { hiddenAncestor: true });
    f.control('INPUT', { visibility: 'hidden' });
    f.control('INPUT', { tabIndex: -1 });
    f.control('SELECT', { enhanced: true, tabIndex: -1 });
    const toggle = f.control('BUTTON');
    f.press(first);
    assert.equal(f.document.activeElement, toggle);
});

test('Enter reaches bootstrap-select through its native tab stop even when its button has tabindex=-1', () => {
    const f = fixture(), first = f.control();
    const select = f.control('SELECT', { enhanced: true, ownsKeys: true });
    const toggle = f.control('BUTTON', { tabIndex: -1 });
    select.toggle = toggle;
    f.press(first);
    assert.equal(f.document.activeElement, toggle);
    assert.equal(f.press(select).defaultPrevented, false, 'select owns its Enter selection');
});

test('Tab, Shift+Tab, multiline entry, IME, shortcuts and existing autocomplete actions retain their keys', () => {
    const f = fixture(), first = f.control();
    f.control();
    for (const options of [{ key: 'Tab' }, { key: 'Tab', shiftKey: true }, { shiftKey: true },
        { isComposing: true }, { keyCode: 229 }, { ctrlKey: true }, { altKey: true }, { metaKey: true }]) {
        assert.equal(f.press(first, options).defaultPrevented, false);
        assert.equal(f.document.activeElement, undefined);
    }
    assert.equal(f.press(first, { defaultPrevented: true }).defaultPrevented, true);
    assert.equal(f.document.activeElement, undefined);
    for (const target of [f.control('TEXTAREA'), f.control('INPUT', { expanded: true }),
        f.control('SELECT', { ownsKeys: true }), f.control('INPUT', { type: 'file' }), f.control('INPUT', { outside: true })]) {
        assert.equal(f.press(target).defaultPrevented, false);
        assert.equal(f.document.activeElement, undefined);
    }
    f.ownKeys({});
    assert.equal(f.press(first).defaultPrevented, false);
});

test('Enter on final ordinary field cannot implicitly submit or wrap focus', () => {
    const f = fixture(), last = f.control();
    assert.equal(f.press(last).defaultPrevented, true);
    assert.equal(f.document.activeElement, undefined);
});

test('Enter can leave a readonly total reached through normal Tab navigation', () => {
    const f = fixture(), total = f.control('INPUT', { readOnly: true });
    const next = f.control('BUTTON');
    assert.equal(f.press(total).defaultPrevented, true);
    assert.equal(f.document.activeElement, next);
});

test('required secondary fields become visible for native validation', () => {
    const f = fixture('.classic-document-workspace form');
    const details = { open: false }, required = f.control('INPUT', { details });
    f.invalid(required);
    assert.equal(details.open, true);
    details.open = false; required.outside = true;
    f.invalid(required);
    assert.equal(details.open, false, 'unrelated forms retain their layout');
});

test('programmatic Bootstrap dialogs return focus to their opener unless it disappeared', () => {
    const f = fixture('#sale-entry-form', true), modal = {};
    const opener = f.control('BUTTON'), dialogField = f.control();
    opener.focus();
    f.modalHooks['show.bs.modal'].call(modal, {});
    dialogField.focus();
    f.modalHooks['hidden.bs.modal'].call(modal);
    assert.equal(f.document.activeElement, opener);

    f.modalHooks['show.bs.modal'].call(modal, { relatedTarget: opener });
    opener.isConnected = false;
    dialogField.focus();
    f.modalHooks['hidden.bs.modal'].call(modal);
    assert.equal(f.document.activeElement, dialogField);
});

test('Alt+I owns capture before bootstrap-select letter navigation on sales, purchases and material documents', () => {
    for (const [kind, file] of [
        ['sale', 'resources/views/backend/sale/index.blade.php'],
        ['purchase', 'resources/views/backend/purchase/index.blade.php'],
        ['material', 'public/js/material-document.js'],
    ]) {
        const view = readFileSync(require('node:path').join(__dirname, '../../', file), 'utf8');
        const shortcut = view.match(/\/\/ Capture Alt\+I before[\s\S]*?\}, true\);/)[0];
        let handler, modal = null, visible = true, active = 'party';
        const search = { is: () => visible, focus() { active = 'item'; return this; }, select() {} };
        runInNewContext(shortcut, {
            document: {
                addEventListener(name, callback, capture) { assert.equal(name, 'keydown'); assert.equal(capture, true); handler = callback; },
                querySelector: () => modal,
            },
            $productSearch: search,
            $: () => search,
        });
        const press = (options = {}) => {
            const event = { key: 'i', altKey: true, preventDefault() { this.defaultPrevented = true; }, stopPropagation() { this.stopped = true; }, ...options };
            handler(event);
            if (!event.stopped) active = 'bootstrap-search';
            return event;
        };
        assert.equal(press().defaultPrevented, true);
        assert.equal(active, 'item', kind);
        for (const options of [{ isComposing: true }, { shiftKey: true }, { ctrlKey: true }, { metaKey: true }]) {
            assert.equal(press(options).defaultPrevented, undefined, 'other key owners retain their shortcut');
        }
        modal = {};
        assert.equal(press().defaultPrevented, undefined, 'modal retains keyboard ownership');
        modal = null; visible = false;
        assert.equal(press().defaultPrevented, undefined, 'hidden entry never takes focus');
    }
});

test('ordinary multi-field forms advance on Enter, skip helper buttons and finish on Save', () => {
    const f = fixture(null), name = f.control();
    f.control('BUTTON', { type: 'button', className: 'btn btn-default' });
    f.control('A');
    const code = f.control();
    const save = f.control('BUTTON', { type: 'button', className: 'btn btn-primary' });
    assert.equal(f.press(name).defaultPrevented, true);
    assert.equal(f.document.activeElement, code, 'helper button and link are skipped');
    f.press(code);
    assert.equal(f.document.activeElement, save, 'primary action is still reached');
});

test('a lone search box keeps native Enter-to-submit; an ordinary form submits after its last field', () => {
    const search = fixture(null), box = search.control();
    assert.equal(search.press(box).defaultPrevented, false);
    const form = fixture(null), first = form.control(), last = form.control();
    assert.equal(form.press(last).defaultPrevented, false, 'nothing follows: native submit');
    assert.equal(form.press(first).defaultPrevented, true);
});

test('Enter continues into the next step of a stepped form', () => {
    const step = { active: false };
    const zoloSteps = { reveal(element) { element.step.active = true; element.hidden = false; return true; } };
    const f = fixture(null, false, { zoloSteps }), first = f.control();
    f.control();
    const later = f.control('INPUT', { step, hidden: true });
    f.press(f.document.activeElement = first);
    f.press(f.document.activeElement);
    assert.equal(f.document.activeElement, later);
    assert.equal(step.active, true);
});

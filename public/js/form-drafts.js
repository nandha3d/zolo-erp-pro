/*
 * Draft-tab adapter for classic document forms (returns, quotations) on the shared engine (document-drafts.js).
 *
 * Config (#form-draft-data): {kind, label, baseUrl, form (selector, absent on a register page), shortcut, source
 * ({id, reference} of the document a return is made from), createUrl (opens a source document's entry page; the
 * engine appends &draft=ID), newUrl (where a new document starts), party}.
 *
 * Every named control is kept by name and position, so a fixed-row form (a return of a posted bill) restores exactly.
 * A page with rows built in the browser supplies window.zoloFormDraftHooks = {captureLines(), restoreLines(lines)
 * -> Promise, reset()} before this script loads.
 */
(() => {
    'use strict';
    const node = document.getElementById('form-draft-data');
    const $ = window.jQuery;
    if (!node || !$ || !window.ZoloDocumentDrafts) return;
    const config = JSON.parse(node.textContent);
    const form = config.form ? document.querySelector(config.form) : null;
    const hooks = window.zoloFormDraftHooks || {};
    const SKIP = new Set(['_token', 'draft_id', '_method', 'idempotency_key']);

    const controls = () => Array.from(form.querySelectorAll('input[name], select[name], textarea[name]'))
        .filter(element => !SKIP.has(element.name) && !['file', 'button', 'submit', 'reset', 'image'].includes(element.type)
            && !(hooks.ownsControl && hooks.ownsControl(element)));
    const valueOf = element => (element.type === 'checkbox' || element.type === 'radio' ? (element.checked ? '1' : '') : element.value);
    /** name#position, so repeated row controls (qty[], is_return[]) keep their own values. */
    function keyed(fn) {
        const seen = {};
        controls().forEach(element => {
            const position = seen[element.name] = (seen[element.name] ?? -1) + 1;
            fn(element.name + '#' + position, element);
        });
    }

    function snapshot() {
        const fields = {};
        keyed((key, element) => { fields[key] = valueOf(element); });
        return {schema_version: 1, document_kind: config.kind,
            form: {fields, lines: hooks.captureLines ? hooks.captureLines() : [], ui: {source: config.source || null}, context: {}}};
    }

    function setFields(fields) {
        keyed((key, element) => {
            if (!Object.prototype.hasOwnProperty.call(fields, key) || valueOf(element) === fields[key]) return;
            if (element.type === 'checkbox' || element.type === 'radio') element.checked = fields[key] === '1';
            else element.value = fields[key];
            // The page's own handlers recalculate totals from what changed.
            $(element).trigger('input').trigger('change');
        });
        if ($.fn.selectpicker) $(form).find('.selectpicker').selectpicker('refresh');
    }

    async function restore(payload) {
        const state = payload.form || {};
        reset();
        setFields(state.fields || {});
        if (hooks.restoreLines) {
            await hooks.restoreLines(state.lines || []);
            setFields(state.fields || {});
        }
    }

    function reset() {
        if (hooks.reset) { hooks.reset(); return; }
        form.reset();
        if ($.fn.selectpicker) $(form).find('.selectpicker').selectpicker('refresh');
    }

    const sameSource = payload => {
        const source = ((payload.form || {}).ui || {}).source;
        return !config.source || !source || String(source.id) === String(config.source.id);
    };

    window.ZoloDocumentDrafts.attach({
        kind: config.kind, label: config.label, baseUrl: config.baseUrl, form, listOnly: !form,
        snapshot, restore, reset,
        party: () => (config.party ? {party_name: config.party} : (hooks.party ? hooks.party() : {})),
        focus: () => { const first = form && form.querySelector('input:not([type=hidden]), select, textarea'); if (first) first.focus(); },
        inDocument: target => !!form && !!target.closest && form.contains(target) && !(hooks.ownsControl && hooks.ownsControl(target)),
        rows: hooks.rows || null,
        handles: entry => entry.id === config.shortcut,
        // A return draft belongs to the bill it returns: open it on that bill's return page.
        foreign: payload => {
            if (form && sameSource(payload)) return null;
            const source = ((payload.form || {}).ui || {}).source;
            return source && config.createUrl ? config.createUrl + (config.createUrl.includes('?') ? '&' : '?') + 'reference_no=' + encodeURIComponent(source.reference) : null;
        },
        // A return starts from its bill reference prompt; a page without a source starts in place.
        startNew: config.newUrl ? (options, go) => {
            const trigger = !form && document.querySelector('[data-new-document]');
            if (trigger) trigger.click(); else go(config.newUrl);
        } : undefined,
    });
})();

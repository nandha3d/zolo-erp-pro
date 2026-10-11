/* Party details strip (GSTIN / address / credit) stays in sync with the party picker on /purchases and /sales,
   through real picks, draft tabs, tab switches, reload and reset. See README.md. */
import { check, context, errors, watch, login, openKind, resetDrafts, partyId, pickParty, SLOW, BASE, finish } from './support.mjs';

const page = await context.newPage();
watch(page);
await login(page);

// Picker value, picker label, strip contents and what the strip should say for that value.
const state = kind => page.evaluate(sel => {
    const select = document.querySelector(sel);
    const option = select.selectedOptions[0];
    const button = select.closest('.bootstrap-select')?.querySelector('.filter-option-inner-inner');
    const card = document.getElementById('party-info-card');
    const real = option && option.value && option.value !== '0' && !option.classList.contains('bs-title-option') ? option : null;
    return {
        value: select.value, label: (button?.textContent || '').trim(),
        expectedLabel: real ? real.textContent.trim() : '',
        gst: card.querySelector('.party-gst-badge').textContent.trim(),
        address: document.getElementById('party-address-text').textContent.trim(),
        credit: document.getElementById('party-credit-badge').textContent.trim(),
        expected: real ? {
            gst: real.dataset.taxNo || 'No GSTIN recorded',
            address: [real.dataset.address, real.dataset.city, real.dataset.state, real.dataset.postal].filter(Boolean).join(', ') || null,
            credit: 'Credit: ' + (real.dataset.creditDays || 0) + ' days',
        } : {gst: '—', address: 'Select a party to see GSTIN and address', credit: 'Credit: — days'},
        gstIds: document.querySelectorAll('[id="party-gst-badge"]').length,
    };
}, partyId(kind));

async function assertSync(kind, step) {
    const s = await state(kind);
    const labelOk = s.expectedLabel ? s.label === s.expectedLabel : /^Select/.test(s.label);
    const stripOk = s.gst === s.expected.gst && s.credit === s.expected.credit && (s.expected.address === null || s.address === s.expected.address);
    check(`${kind} [${step}] picker label matches value`, labelOk, JSON.stringify({ value: s.value, label: s.label, expectedLabel: s.expectedLabel }));
    check(`${kind} [${step}] strip matches picker`, stripOk, JSON.stringify({ got: { gst: s.gst, address: s.address, credit: s.credit }, expected: s.expected }));
    return s;
}

// Pick through the real bootstrap-select UI, like a user.
async function pickByUi(kind, text) {
    const wrapper = page.locator(`.bootstrap-select:has(${partyId(kind)})`);
    await wrapper.locator('button.dropdown-toggle').click();
    const search = page.locator('.bs-searchbox input:visible').first();
    await search.fill(text);
    await search.press('Enter');
}
const waitSaved = () => page.waitForFunction(() => window.zoloDocumentWorkspace.tabs.some(t => t.draftId && t.partyName), null, { timeout: SLOW });
const waitValue = (kind, filled) => page.waitForFunction(({ sel, filled }) => !!document.querySelector(sel).value === filled, { sel: partyId(kind), filled }, { timeout: SLOW });

for (const kind of ['purchase', 'sale']) {
    await openKind(page, kind);
    await resetDrafts(page, kind);
    await openKind(page, kind, '?new=1');
    await assertSync(kind, 'fresh page');
    { const ids = (await state(kind)).gstIds; check(`${kind}: only one element has id party-gst-badge`, ids === 1, 'count=' + ids); }

    const first = await page.evaluate(sel => [...document.querySelectorAll(sel + ' option')].find(o => o.value && o.value !== '0')?.textContent.trim(), partyId(kind));
    await pickByUi(kind, first.split(' (')[0]);
    await assertSync(kind, 'picked via UI');

    await page.locator('#order-table-body, body').first().press('Tab').catch(() => {});
    await page.evaluate(() => document.dispatchEvent(new Event('input', { bubbles: true })));
    await page.locator('#btn-add-item-row').click();
    await page.locator('#order-table-body tr.order-item-row .row-item-name').last().fill('PARTY-SYNC');
    await waitSaved().catch(() => {});

    await page.click('#document-tab-add');
    await page.waitForFunction(() => window.zoloDocumentWorkspace.tabs.length === 2, null, { timeout: SLOW });
    await assertSync(kind, 'new + tab');

    await page.locator('.document-tab-open').first().click();
    await waitValue(kind, true);
    await assertSync(kind, 'back to party tab');

    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.zoloDocumentWorkspace && window.zoloDocumentWorkspace.ready, null, { timeout: SLOW });
    await page.waitForTimeout(800);
    await assertSync(kind, 'after reload');
    await page.locator('.document-tab-open').first().click();
    await waitValue(kind, true);
    await assertSync(kind, 'reload then open party tab');
    await page.click('#document-tab-add');
    await waitValue(kind, false);
    await assertSync(kind, 'reload then + tab');

    await page.goto(page.url().split('?')[0] + '?new=1', { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => window.zoloDocumentWorkspace && window.zoloDocumentWorkspace.ready, null, { timeout: SLOW });
    await page.waitForTimeout(800);
    await assertSync(kind, '?new=1 with existing drafts');

    // The New Party modal validates its GSTIN in its own badge and never touches the strip.
    const before = await state(kind);
    await page.click('#btn-quick-new-party');
    await page.waitForSelector('#party-vat-number', { state: 'visible', timeout: SLOW });
    await page.fill('#party-vat-number', '33AIUPN6412D1ZA');
    await page.waitForFunction(() => (document.getElementById('party-modal-gst-badge')?.textContent || '').trim() !== '', null, { timeout: 5000 }).catch(() => {});
    const after = await state(kind);
    const badge = await page.evaluate(() => { const b = document.getElementById('party-modal-gst-badge'); return b && { text: b.textContent.trim(), visible: b.offsetParent !== null }; });
    check(`${kind}: GSTIN typed in New Party modal leaves the strip alone`, after.gst === before.gst && after.address === before.address, JSON.stringify({ before: before.gst, after: after.gst }));
    check(`${kind}: New Party modal shows its own GSTIN badge`, !!badge && badge.visible && badge.text !== '', JSON.stringify(badge));
    await page.keyboard.press('Escape');
    await resetDrafts(page, kind);
}

// The Supplier master form stores GSTIN in vat_number only; the purchase strip must still show it.
const setVat = async value => {
    await page.goto(BASE + '/supplier/1/edit');
    await page.fill('input[name=vat_number]', value);
    await Promise.all([page.waitForNavigation(), page.locator('input[name=vat_number]').evaluate(input => input.form.requestSubmit())]);
};
await setVat('33AIUPN6412D1ZA');
await openKind(page, 'purchase', '?new=1');
await pickParty(page, 'purchase', '1');
const masterGst = await page.evaluate(() => document.querySelector('#party-info-card .party-gst-badge').textContent.trim());
check('purchase: GSTIN saved from the Supplier master shows in the strip', masterGst === '33AIUPN6412D1ZA', masterGst);
await pickParty(page, 'purchase', '');
await setVat('');

check('no page errors', errors.length === 0, errors.join(' | '));
await finish();

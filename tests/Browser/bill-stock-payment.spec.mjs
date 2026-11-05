/* Rendered Blade fixtures and real browser scripts. Backend posting is covered by BillStockPaymentWorkflowTest.
 * Export with ERP_EXPORT_BILL_PAYMENT_BROWSER=1 before running. All requests stay inside this fixture harness. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {browser, context} from './support.mjs';

const base = 'http://erp-fixture.local';
const publicRoot = path.resolve('public');
const pages = {payments: 'payments', receipt: 'receipt', voucher: 'voucher', sales: 'sale', purchases: 'purchase', coa: 'coa', accounts: 'accounts'};
const mime = {'.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.json': 'application/json'};
let accountCreates = 0;
await context.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (url.pathname === '/payment-accounts' && request.method() === 'POST') {
        accountCreates++;
        return route.fulfill({status: 201, json: {data: {id: 999, name: 'ICICI Current', kind: 'bank', ledger_id: 999, ledger_name: 'ICICI Current', last_four: '2468'}}});
    }
    if (/^\/sales\/\d+\/json$/.test(url.pathname) || /^\/purchases\/\d+$/.test(url.pathname)) {
        const sale = url.pathname.startsWith('/sales/'), kind = sale ? 'sale' : 'purchase';
        const id = Number(url.pathname.split('/')[2]);
        return route.fulfill({json: {[kind]: {id, reference_no: 'PAYMENT-PREVIEW', created_at: '2026-11-04', posted_at: '2026-11-04',
            grand_total: 50400, paid_amount: 0, customer: {name: 'Fixture customer'}, supplier: {name: 'Fixture supplier'}}, items: []}});
    }
    const fixture = pages[url.pathname.slice(1)];
    if (fixture) {
        const html = fs.readFileSync('scratch/bill-payment-' + fixture + '.html', 'utf8').replace(/http:\/\/(?:localhost|127\.0\.0\.1)(?::\d+)?/g, base);
        return route.fulfill({contentType: 'text/html', body: html});
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (file.startsWith(publicRoot + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
        return route.fulfill({contentType: mime[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file)});
    }
    if (/\/drafts(?:\/\d+)?$/.test(url.pathname)) return route.fulfill({json: {data: []}});
    if (request.method() !== 'GET') throw new Error('Unexpected fixture write: ' + request.method() + ' ' + url.pathname);
    return route.fulfill({json: {data: []}});
});
const page = await context.newPage(), errors = [];
page.on('pageerror', error => errors.push(error.message));
try {
    await page.goto(base + '/payments', {waitUntil: 'networkidle'});
    assert.equal(await page.locator('#payment-amount').inputValue(), '13.00');
    await page.locator('#payment-amount').fill('14');
    assert.equal(await page.locator('#payment-amount').evaluate(input => input.validity.rangeOverflow), true);
    await page.locator('#payment-amount').fill('5');
    await page.locator('#payment-account').selectOption('1');
    assert.equal(await page.locator('#bill-payment-form').evaluate(form => form.checkValidity()), true);
    await page.locator('#payment-method').selectOption('Bank');
    assert.equal(await page.locator('#payment-account').inputValue(), '');
    assert.deepEqual(await page.locator('#payment-account option:not([disabled])').allTextContents(), ['Choose bank account', 'HDFC Current', 'IOB', 'SBI Current']);
    const hdfc = await page.locator('#payment-account option').filter({hasText: 'HDFC Current'}).getAttribute('value');
    await page.locator('#payment-account').selectOption(hdfc);
    assert.ok((await page.locator('#payment-flow-preview').innerText()).includes('received into HDFC Current'));
    assert.ok((await page.locator('#payment-account-help').innerText()).includes('Posts to HDFC Current'));
    assert.ok((await page.locator('#payment-due-preview').innerText()).includes('8.00'));
    await page.locator('#payment-add-account').click();
    await page.locator('#new-payment-account-name').fill('ICICI Current');
    await page.locator('#new-payment-account-number').fill('123456782468');
    await page.locator('#payment-account-form [type="submit"]').click();
    await page.waitForFunction(() => document.getElementById('payment-account').value === '999');
    assert.equal(accountCreates, 1);
    assert.equal(await page.locator('#payment-account-dialog').evaluate(dialog => dialog.open), false);
    assert.equal(await page.locator('#payment-account-dialog').isVisible(), false);
    await page.locator('#payment-account').selectOption(hdfc);
    const picker = page.locator('.bootstrap-select').filter({has: page.locator('#payment-account')});
    if (await picker.count()) assert.ok((await picker.locator('.filter-option').innerText()).includes('HDFC Current'));
    const action = page.locator('[data-pay-bill]').first();
    assert.ok(await action.evaluate(button => button.getBoundingClientRect().width >= 112 && button.scrollWidth <= button.clientWidth + 1));
    await action.click();
    assert.equal(await page.locator('#payment-amount').inputValue(), '13.00');
    await page.locator('#payment-amount').fill('5');
    const iob = await page.locator('#payment-account option').filter({hasText: /^IOB$/}).getAttribute('value');
    await page.locator('#payment-account').selectOption(iob);
    assert.ok((await page.locator('#payment-account-help').innerText()).includes('Posts to IOB'));

    await page.evaluate(() => {
        const group = document.createElement('div');
        group.id = 'required-marker-proof';
        group.innerHTML = '<label for="optional-proof">Optional *</label><input id="optional-proof"><label for="required-proof">Required *</label><input id="required-proof" required>';
        document.querySelector('#bill-payment-form').append(group);
    });
    await page.waitForFunction(() => !document.querySelector('label[for="optional-proof"]').textContent.includes('*'));
    assert.equal(await page.locator('label[for="required-proof"]').innerText(), 'Required *');
    await page.locator('#required-proof').evaluate(input => input.required = false);
    await page.waitForFunction(() => !document.querySelector('label[for="required-proof"]').textContent.includes('*'));
    await page.locator('#required-proof').evaluate(input => input.required = true);
    await page.waitForFunction(() => document.querySelector('label[for="required-proof"]').textContent.includes('*'));
    await page.locator('#required-marker-proof').evaluate(node => node.remove());
    console.log('PASS named-bank filtering/setup, linked preview, bill selection, text buttons and required markers');

    for (const width of [1440, 390]) {
        await page.setViewportSize({width, height: 900});
        if (width < 768) await page.waitForFunction(() => document.querySelector('.page').getBoundingClientRect().left < 1);
        assert.ok(await page.locator('#bill-payment-form').evaluate(form => form.getBoundingClientRect().width >= 300), 'Payment form is clipped');
        await page.screenshot({path: 'scratch/bill-payment-workspace-' + width + '.png', fullPage: true});
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Payment workspace overflows at ' + width);
    }
    await page.setViewportSize({width: 1440, height: 900});
    for (const kind of ['receipt', 'voucher']) {
        await page.goto(base + '/' + kind, {waitUntil: 'networkidle'});
        assert.equal(await page.locator('article').isVisible(), true);
        assert.equal(await page.locator('article').innerText().then(text => text.includes('Accounting voucher')), kind === 'voucher');
        await page.screenshot({path: 'scratch/bill-payment-' + kind + '.png', fullPage: true});
        await page.pdf({path: 'scratch/bill-payment-' + kind + '.pdf', format: 'A4', printBackground: true});
    }
    console.log('PASS separate customer receipt and internal voucher, A4 output');
    for (const kind of ['coa', 'accounts']) {
        await page.goto(base + '/' + kind, {waitUntil: 'networkidle'});
        const search = page.locator('#content .dataTables_filter input').first();
        if (await search.count()) { await search.fill('IOB'); await search.dispatchEvent('keyup'); }
        assert.ok((await page.locator('#content').innerText()).includes('IOB'), kind + ': IOB must remain linked and visible');
        await page.screenshot({path: 'scratch/bill-payment-' + kind + '.png', fullPage: true});
    }
    console.log('PASS COA-created IOB appears in payments and Account List');

    for (const kind of ['sales', 'purchases']) {
        await page.goto(base + '/' + kind, {waitUntil: 'networkidle'});
        await page.waitForFunction(() => !!window.commandCenterGrid);
        const paymentAction = page.locator('.side-payment-action').first();
        if (await paymentAction.count()) {
            assert.equal(await paymentAction.evaluate(button => button === button.parentElement.lastElementChild), true, kind + ': payment action belongs last');
            assert.ok(await paymentAction.evaluate(button => button.scrollWidth <= button.clientWidth + 1), kind + ': payment text is clipped');
            const loads = await paymentAction.evaluate(button => {
                let calls = 0;
                const ajax = window.jQuery.ajax;
                window.jQuery.ajax = () => { calls++; return {done() { return this; }, fail() { return this; }}; };
                button.addEventListener('click', event => event.preventDefault(), {once: true});
                button.click(); window.jQuery.ajax = ajax;
                return calls;
            });
            assert.equal(loads, 0, kind + ': payment link must not load bill into edit form');
        }
        const view = page.locator('.btn-side-view').first();
        if (await view.count()) {
            await view.click();
            const dialog = page.locator(kind === 'sales' ? '#sale-details' : '#purchase-details');
            await dialog.waitFor({state: 'visible'});
            assert.equal(await dialog.locator('.command-center-detail-actions .bill-payment-action').isVisible(), true);
            assert.equal(await dialog.locator(kind === 'sales' ? '#sale-content .bill-payment-action' : '#purchase-content .bill-payment-action').count(), 0);
            await page.waitForFunction(selector => !window.jQuery(selector).data('bs.modal')?._isTransitioning, kind === 'sales' ? '#sale-details' : '#purchase-details');
            await dialog.evaluate(node => window.jQuery(node).modal('hide'));
            await dialog.waitFor({state: 'hidden'});
        }
        const result = await page.evaluate(() => {
            const grid = window.commandCenterGrid, [first, second] = grid.products;
            if (!second) throw new Error('Need two fixture products.');
            grid.addProductRow({product_id: first.id, product_name: first.name, product_code: first.code, unit: first.unit, price: first.price, qty: 1});
            const row = window.jQuery('#order-table-body tr.order-item-row').last();
            row.attr('data-tracking', JSON.stringify({product_batch_id: 99999, stock_identity_id: 99999}));
            row.attr('data-batch-id', 99999).attr('data-variant-id', 99999).attr('data-material-item-id', 99999);
            row.find('.row-batch-val, .row-imei-val').val('OLD-STOCK');
            grid.applyProductToRow(row, second);
            return {id: row.find('.row-product-id').val(), expected: String(second.id), name: row.find('.row-item-name').val(), expectedName: second.name,
                tracking: row.attr('data-tracking'), batch: row.attr('data-batch-id'), variant: row.attr('data-variant-id'), source: row.attr('data-material-item-id'), serial: row.find('.row-imei-val').val()};
        });
        assert.equal(result.id, result.expected); assert.equal(result.name, result.expectedName);
        for (const field of ['tracking', 'batch', 'variant', 'source']) assert.equal(result[field], undefined, kind + ': stale ' + field);
        assert.equal(result.serial, '');
    }
    assert.deepEqual(errors, []);
    console.log('PASS sales and purchase product selection clears old stock references; no browser errors');
} finally {
    await browser.close();
}

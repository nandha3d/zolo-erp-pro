// Run against a seeded, disposable ERP instance. Quick-create requests are intercepted.
import assert from 'node:assert/strict';
import { BASE, browser, context, login, errors, watch } from './support.mjs';

const page = await context.newPage();
watch(page);
try {
    await login(page);
    await page.waitForURL('**/select-company');
    await page.locator('form.cp-card .cp-open').first().click();
    await page.waitForURL(url => url.pathname !== '/select-company');

    assert.equal((await page.goto(BASE + '/sales?new=1')).status(), 200);
    await page.waitForFunction(() => window.commandCenterGrid?.products.length > 0);

    const multi = page.locator('#multi-item-modal');
    await page.locator('#btn-multi-item').click();
    await multi.waitFor();
    const item = multi.locator('.multi-item-row').first();
    const productId = await item.getAttribute('data-id');
    await item.locator('.multi-item-check').check();
    await item.locator('.multi-item-qty').fill('2');
    await multi.locator('#btn-add-selected-items').click();
    await multi.waitFor({ state: 'hidden' });

    const row = page.locator('#order-table-body tr.order-item-row').filter({
        has: page.locator('.row-product-id[value="' + productId + '"]'),
    });
    assert.equal(await row.locator('.row-qty').inputValue(), '2');

    const detail = page.locator('#row-detail-modal');
    await row.locator('.btn-edit-row').click();
    await detail.waitFor();
    await detail.locator('#modal-row-discount').fill('1.25');
    await detail.locator('#btn-save-row-detail').click();
    await detail.waitFor({ state: 'hidden' });
    assert.equal(Number(await row.locator('.row-discount-val').inputValue()), 1.25);

    await page.route('**/parties/quick-store', route => {
        assert.equal(route.request().method(), 'POST');
        assert.equal(new URLSearchParams(route.request().postData()).get('name'), 'Dialog customer');
        return route.fulfill({ json: { success: true, customer: { id: 900001, name: 'Dialog customer' } } });
    });
    const party = page.locator('#quick-create-party-modal');
    await page.locator('#btn-quick-new-party').click();
    await party.waitFor();
    for (const [field, value] of Object.entries({
        name: 'Dialog customer', 'company-name': 'Dialog company', 'phone-number': '9000000000',
        address: 'Fixture street', city: 'Fixture city',
    })) {
        await party.locator('#party-' + field).fill(value);
    }
    await party.locator('#btn-save-party').click();
    await party.waitFor({ state: 'hidden' });
    assert.equal(await page.locator('#customer_id').inputValue(), '900001');

    await page.route('**/products/quick-store', route => {
        assert.equal(route.request().method(), 'POST');
        assert.match(route.request().postData(), /Dialog product/);
        return route.fulfill({ json: { product: {
            id: 900002, name: 'Dialog product', code: 'DIALOG-PRODUCT', cost: 5, price: 10,
            tax_rate: 0, unit: 'PCS', value: 'DIALOG-PRODUCT|Dialog product', label: 'Dialog product',
        } } });
    });
    const product = page.locator('#quick-create-item-modal');
    await page.locator('#btn-create-item-modal').click();
    await product.waitFor();
    await product.locator('#quick-item-name').fill('Dialog product');
    await product.locator('#quick-item-code').fill('DIALOG-PRODUCT');
    await product.locator('#quick-item-cost').fill('5');
    await product.locator('#quick-item-price').fill('10');
    for (const id of ['quick-item-category', 'quick-item-unit']) {
        const select = product.locator('#' + id);
        const value = await select.evaluate(el => Array.from(el.options).find(option => option.value && !option.disabled)?.value);
        assert.ok(value, id + ' needs seeded options');
        await select.selectOption(value);
    }
    await product.locator('#btn-quick-create-submit').click();
    await product.waitFor({ state: 'hidden' });
    assert.ok(await page.locator('#order-table-body .row-item-name').evaluateAll(
        inputs => inputs.some(input => input.value === 'Dialog product')
    ));
    assert.deepEqual(errors, []);
    console.log('Sales dialog checks passed: multi-item, row detail, quick customer and quick product.');
} finally {
    await browser.close();
}

/* Rendered Blade fixtures, with all requests intercepted. No live accounting writes. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {browser, context} from './support.mjs';

const base = 'http://erp-fixture.local', root = path.resolve('public');
const pages = {
    '/accounting/vouchers': 'vouchers', '/accounting/voucher/entry': 'voucher-entry',
    '/money-transfers': 'money-transfers', '/accounts': 'accounts',
    ...Object.fromEntries(['chart-of-accounts', 'semantic-account-mappings', 'journal-entries', 'general-ledger', 'trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow-statement', 'inventory-close', 'day-book', 'cash-book', 'ageing', 'collections', 'monthly-ledger/1'].map(key => ['/accounting/' + key, key.replaceAll('/', '-')])),
};
const mime = {'.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf'};
const writes = [], defaultRequests = [], errors = [], dialogs = [];
const journalDetail = JSON.parse(fs.readFileSync('scratch/accounting-journal-detail.json', 'utf8'));
await context.route('**/*', async route => {
    const request = route.request(), url = new URL(request.url());
    if (request.method() !== 'GET') {
        if (url.pathname === '/accounting/vouchers') {
            writes.push(new URLSearchParams(request.postData()));
            return route.fulfill({contentType: 'text/html', body: '<p>Transfer captured by browser fixture</p>'});
        }
        if (url.pathname.startsWith('/make-default/')) {
            defaultRequests.push({path: url.pathname, data: new URLSearchParams(request.postData())});
            return route.fulfill({body: 'Default account set in fixture'});
        }
        throw new Error('Unexpected write: ' + request.method() + ' ' + url.pathname);
    }
    let key = pages[url.pathname];
    if (url.pathname === '/accounting/vouchers' && url.searchParams.get('voucher_type') === 'contra') key = 'transfer-entry';
    if (url.pathname === '/accounting/vouchers' && url.searchParams.get('voucher_type') === 'receipt') key = 'receipt-entry';
    if (url.pathname === `/accounting/vouchers/${journalDetail.id}`) key = 'voucher-detail';
    if (url.pathname === `/accounting/journal-entries/${journalDetail.id}`) return route.fulfill({json: journalDetail});
    if (url.pathname === '/accounting/journal-entries' && url.searchParams.get('start_date') === '2026-10-03') key = 'journal-populated';
    if (url.pathname === '/money-transfers' && url.searchParams.has('posted')) key = 'money-transfers-posted';
    if (key) return route.fulfill({contentType: 'text/html', body: fs.readFileSync('scratch/accounting-' + key + '.html', 'utf8').replace(/http:\/\/(?:localhost|127\.0\.0\.1|zolo-erp-pro\.test)(?::\d+)?/g, base)});
    const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
    if (file.startsWith(root + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({contentType: mime[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file)});
    if (url.pathname.endsWith('/next-number')) return route.fulfill({json: {number: 'JE-FIXTURE', next_number: 'JE-FIXTURE'}});
    return route.fulfill({json: {data: []}});
});
const page = await context.newPage();
page.setDefaultTimeout(8000);
await page.addInitScript(() => { window.print = () => { window.accountingPrintCalls = (window.accountingPrintCalls || 0) + 1; }; });
page.on('pageerror', error => errors.push(error.message));
page.on('dialog', async dialog => { dialogs.push(dialog.message()); await dialog.dismiss(); });
const open = async url => { await page.goto(base + url, {waitUntil: 'networkidle'}); };
const choose = async (id, text) => {
    const option = page.locator(`${id} option`).filter({hasText: text}).first();
    await page.selectOption(id, await option.getAttribute('value'));
};
const fits = async label => {
    const size = await page.evaluate(() => ({width: document.documentElement.clientWidth, scroll: document.documentElement.scrollWidth}));
    assert.ok(size.scroll <= size.width + 2, `${label}: page overflow ${JSON.stringify(size)}`);
};
try {
    await page.setViewportSize({width: 1440, height: 1000});
    await open('/accounting/vouchers?voucher_type=contra');
    assert.equal(await page.locator('#voucher-line-editor').isVisible(), false);
    assert.equal(await page.locator('#voucher-allocation-mode').isVisible(), false);
    await choose('#transfer-from', 'IOB');
    await choose('#transfer-to', 'Cash');
    await page.fill('#transfer-amount', '5000');
    await page.fill('#voucher-description', 'Cash withdrawn from IOB');
    assert.match(await page.locator('#transfer-preview').innerText(), /IOB decreases.*5,000.*Cash.*increases/is);
    assert.equal(await page.locator('#post-voucher').isEnabled(), true);
    assert.ok((await page.locator('.voucher-history').boundingBox()).height < 80, 'Collapsed history must remain compact');
    await page.screenshot({path: 'scratch/accounting-transfer-1440.png', fullPage: true});
    await fits('transfer desktop');
    const sourceId = await page.inputValue('#transfer-from');
    await page.selectOption('#transfer-to', sourceId);
    assert.equal(await page.locator('#post-voucher').isDisabled(), true);
    assert.match(await page.locator('#transfer-preview').innerText(), /different accounts/);
    await choose('#transfer-to', 'SBI');
    await page.check('#transfer-advanced');
    assert.equal(await page.locator('#voucher-line-editor').isVisible(), true);
    assert.equal(await page.locator('#guided-transfer-fields').isVisible(), false);
    assert.equal(await page.locator('#voucher-line-editor [data-party-column]:visible').count(), 0);
    await page.screenshot({path: 'scratch/accounting-transfer-advanced.png', fullPage: true});
    assert.equal(await page.locator('#voucher-lines select[data-field="chart_of_account_id"] option:not([disabled])').filter({hasText: 'sales_revenue'}).count(), 0);
    await page.locator('#add-voucher-line').click();
    assert.equal(await page.locator('#voucher-lines tr').count(), 3);
    await page.locator('#voucher-lines [data-remove]').last().click();
    assert.equal(await page.locator('#voucher-lines tr').count(), 2);
    await page.selectOption('#narration-template', {label: 'Cash deposited in bank'});
    assert.equal(await page.inputValue('#voucher-description'), 'Cash deposited in bank');
    await page.uncheck('#transfer-advanced');
    await page.locator('#post-voucher').click();
    await page.waitForLoadState('networkidle');
    assert.equal(writes.length, 1);
    assert.equal(writes[0].get('voucher_type'), 'contra');
    assert.equal(writes[0].get('reference_mode'), 'on_account');
    assert.equal(writes[0].get('items[0][chart_of_account_id]'), sourceId);
    assert.equal(writes[0].get('items[0][credit]'), '5000');
    assert.equal(writes[0].get('items[1][debit]'), '5000');
    assert.equal(writes[0].get('items[0][partner_id]'), '');

    await open('/accounting/collections');
    await page.getByRole('link', {name: 'Post receipt in Voucher Hub'}).click();
    await page.waitForURL('**/accounting/vouchers?voucher_type=receipt');
    assert.equal(await page.inputValue('#voucher-type'), 'receipt');
    const lines = page.locator('#voucher-lines tr');
    const lineAccount = index => lines.nth(index).locator('[data-field="chart_of_account_id"]');
    const selectControl = async (index, control) => lineAccount(index).selectOption(await lineAccount(index).locator(`option[data-control="${control}"]`).first().getAttribute('value'));
    const party = lines.nth(1).locator('[data-party]');
    assert.equal(await party.isVisible(), false);
    await selectControl(0, 'bank');
    await selectControl(1, 'ar');
    assert.equal(await party.isVisible(), true);
    assert.equal(await party.locator('option[value^="supplier:"]:enabled').count(), 0);
    const bill = page.locator('[data-bill-id]').first();
    const customer = await bill.getAttribute('data-party');
    await party.selectOption(customer);
    await page.screenshot({path: 'scratch/accounting-receipt-party.png', fullPage: true});
    await lines.nth(0).locator('[data-field="debit"]').fill('25');
    await lines.nth(1).locator('[data-field="credit"]').fill('25');
    await page.fill('#voucher-description', 'Part payment received');
    await page.selectOption('#reference-mode', 'against_reference');
    assert.equal(await page.locator('#post-voucher').isDisabled(), true);
    await page.locator('#choose-bills').click();
    await bill.locator('[data-bill-line]').selectOption('1');
    await bill.locator('[data-bill-amount]').fill('25');
    await page.locator('#apply-allocations').click();
    assert.match(await page.locator('#allocation-summary').innerText(), /1 bill allocation/);
    await page.locator('#post-voucher').click();
    await page.getByText('Transfer captured by browser fixture').waitFor();
    assert.equal(writes[1].get('items[1][partner_type]'), 'customer');
    assert.equal(writes[1].get('items[1][partner_id]'), customer.split(':')[1]);
    assert.equal(writes[1].get('allocations[0][amount]'), '25');

    await open('/accounting/vouchers');
    await page.selectOption('#voucher-type', 'payment');
    await selectControl(0, 'bank');
    await selectControl(1, 'ap');
    assert.equal(await party.locator('option[value^="customer:"]:enabled').count(), 0);
    const supplier = await party.locator('option[value^="supplier:"]:enabled').first().getAttribute('value');
    await party.selectOption(supplier);
    await lines.nth(0).locator('[data-field="credit"]').fill('30');
    await lines.nth(1).locator('[data-field="debit"]').fill('30');
    await page.fill('#voucher-description', 'Supplier payment');
    await page.locator('#post-voucher').click();
    await page.getByText('Transfer captured by browser fixture').waitFor();
    assert.equal(writes[2].get('items[1][partner_type]'), 'supplier');
    assert.equal(writes[2].get('items[1][partner_id]'), supplier.split(':')[1]);

    await open('/accounting/vouchers');
    for (const type of ['payment', 'receipt', 'journal']) {
        await page.selectOption('#voucher-type', type);
        assert.equal(await page.locator('#transfer-entry').isVisible(), false);
        assert.equal(await page.locator('#voucher-line-editor').isVisible(), true);
        assert.ok((await page.locator('#voucher-type-help').innerText()).length > 30);
    }
    await page.keyboard.press('F4');
    assert.equal(await page.locator('#transfer-entry').isVisible(), true);
    await page.keyboard.press('F7');
    assert.equal(await page.inputValue('#voucher-type'), 'journal');
    await page.keyboard.press('F9');
    assert.equal(await page.locator('#voucher-type').evaluate(node => node === document.activeElement), true);
    await open('/accounting/vouchers#settlement-account-links');
    assert.equal(await page.locator('#settlement-account-links').getAttribute('open'), '');

    await open('/accounting/voucher/entry');
    assert.equal(await page.locator('#balancedBadge').innerText(), 'Awaiting amounts');
    assert.equal(await page.locator('#btnSaveVoucher').isDisabled(), true);
    await page.keyboard.press('F5');
    assert.equal(await page.locator('.vch-type-btn[data-type="payment"]').getAttribute('aria-pressed'), 'true');
    await page.keyboard.press('Control+F9');
    await page.waitForFunction(() => document.activeElement.id === 'modalInlineSeries');
    await page.keyboard.press('Escape');
    await page.waitForSelector('#modalInlineSeries', {state: 'hidden'});
    await page.keyboard.press('Control+Enter');
    assert.equal(await page.locator('#voucherTableBody tr').count(), 3);
    await page.locator('#voucherTableBody tr').last().getByRole('button', {name: 'Remove line'}).click();
    await page.locator('.debit-input').first().fill('1250');
    assert.equal(await page.locator('#balancedBadge').innerText(), 'Unbalanced');
    await page.locator('#voucherDescription').focus();
    assert.equal(await page.locator('.credit-input').nth(1).inputValue(), '1250.00');
    assert.equal(await page.locator('#balancedBadge').innerText(), 'Balanced');
    assert.equal(await page.locator('#btnSaveVoucher').isEnabled(), true);

    await open('/accounting/semantic-account-mappings');
    await page.fill('#mapping-search', 'default bank');
    assert.equal(await page.locator('[data-mapping-row]:visible').count(), 1);
    assert.match(await page.locator('[data-mapping-row]:visible').innerText(), /selected IOB or SBI/);
    const bankSelect = page.locator('[data-mapping-row]:visible select');
    const choices = await bankSelect.locator('option:not([disabled])').evaluateAll(options => options.map(option => option.value));
    if (choices.length > 1) {
        const current = await bankSelect.inputValue();
        await bankSelect.selectOption(choices.find(value => value !== current));
        assert.match(await page.locator('#mapping-changes').innerText(), /1 unsaved/);
    }
    await page.fill('#mapping-search', 'no matching role');
    assert.equal(await page.locator('#mapping-no-results').isVisible(), true);
    await page.fill('#mapping-search', '');
    await page.screenshot({path: 'scratch/accounting-mappings-1440.png', fullPage: true});

    await open('/accounting/chart-of-accounts?create=bank');
    await page.waitForSelector('#addAccountModal.show');
    assert.equal(await page.inputValue('#new-account-sub_type'), 'bank');
    await page.locator('#addAccountModal [data-dismiss="modal"]').first().click();
    await page.waitForSelector('#addAccountModal', {state: 'hidden'});
    await page.selectOption('#coa-category', 'asset');
    assert.equal(await page.locator('#coa-table tbody tr').count() > 0, true);
    await page.getByRole('button', {name: 'Clear filters', exact: true}).click();
    assert.equal(await page.inputValue('#coa-category'), '');
    await page.locator('#content [data-erp-print]').click();
    await page.waitForSelector('iframe.erp-print-frame', {state: 'attached'});

    await open('/accounts');
    await page.getByRole('radio', {name: 'Use IOB as the default account', exact: true}).check();
    await page.waitForFunction(() => document.getElementById('primary-default-account').textContent.trim() === 'IOB');
    assert.equal(defaultRequests.length, 1);
    assert.ok(defaultRequests[0].data.get('_token'));

    await page.getByRole('link', {name: 'Edit IOB in Chart of Accounts', exact: true}).click();
    await page.waitForSelector('#editAccountModal.show');
    assert.equal(await page.inputValue('#edit-account-name'), 'IOB');
    assert.match(await page.locator('#editAccountForm').getAttribute('action'), /chart-of-accounts\/\d+$/);
    await open('/accounts');
    await page.getByRole('link', {name: 'Statement for IOB', exact: true}).click();
    await page.waitForURL('**/accounting/general-ledger?account_id=*');
    await page.locator('#content a[href*="/monthly-ledger/"]').last().click();
    await page.waitForURL('**/accounting/monthly-ledger/*');

    await open('/accounting/journal-entries?start_date=2026-10-03&end_date=2026-10-03');
    await page.locator(`.view-entry-btn[data-url$="/${journalDetail.id}"]`).click();
    await page.waitForSelector('#viewEntryModal.show');
    assert.equal(await page.locator('#entryDetailBody tr').count(), 2);
    await page.locator('#viewEntryModal [data-dismiss="modal"]').first().click();
    await page.waitForSelector('#viewEntryModal', {state: 'hidden'});
    await page.locator('[data-target="#createVoucherModal"]').click();
    await page.locator('#addLineBtn').click();
    assert.equal(await page.locator('#voucherLinesTable tbody tr').count(), 3);
    await page.locator('#voucherLinesTable .remove-line-btn').last().click();
    assert.equal(await page.locator('#voucherLinesTable tbody tr').count(), 2);
    await page.locator('#createVoucherModal [data-dismiss="modal"]').first().click();
    await page.waitForSelector('#createVoucherModal', {state: 'hidden'});
    await page.locator('.reverse-entry-btn').first().click();
    assert.match(await page.locator('#reverseEntryForm').getAttribute('action'), /journal-entries\/\d+\/reverse$/);
    await page.locator('#reverseEntryModal [data-dismiss="modal"]').first().click();

    await open('/accounting/voucher/entry');
    for (const [button, modal] of [['#btnInlineSeries', '#modalInlineSeries'], ['#btnInlineAccount', '#modalInlineAccount'], ['#btnInlineRemark', '#modalInlineRemark']]) {
        await page.locator(button).click();
        await page.waitForSelector(`${modal}.show`);
        await page.waitForFunction(id => window.jQuery(id).data('bs.modal')?._isTransitioning === false, modal);
        await page.locator(`${modal} [data-dismiss="modal"]`).first().click();
        await page.waitForSelector(modal, {state: 'hidden'});
    }

    await open('/accounting/vouchers');
    const navigation = await page.locator('.accounting-nav-group').evaluateAll(groups => groups.map(group => ({
        label: group.querySelector('summary').textContent.trim(),
        links: [...group.querySelectorAll('a')].map(link => ({label: link.textContent.trim(), url: link.href}))
    })));
    for (const group of navigation) {
        for (const link of group.links) {
            await page.goto(base + '/accounting/vouchers', {waitUntil: 'domcontentloaded'});
            await page.locator('.accounting-nav-group summary').filter({hasText: group.label}).click();
            await page.getByRole('navigation', {name: 'Accounting screens'}).getByRole('link', {name: link.label, exact: true}).click();
            await page.waitForURL(link.url);
            assert.equal(await page.locator('.accounting-command-bar').count(), 1, link.url);
        }
    }
    console.log(`PASS: ${navigation.flatMap(group => group.links).length} Accounting menu links opened from the command bar.`);

    for (const url of ['/money-transfers', ...Object.keys(pages).filter(url => /ledger|balance|profit-loss|cash-flow|inventory-close|day-book|cash-book|ageing|collections|journal-entries/.test(url))]) {
        await open(url);
        const filterForm = page.locator('#content form[method="get" i]').first();
        if (await filterForm.count()) {
            await Promise.all([
                page.waitForNavigation({waitUntil: 'domcontentloaded'}),
                filterForm.locator('button[type="submit"]').click(),
            ]);
            assert.equal(new URL(page.url()).pathname, url);
            const reset = page.locator('#content').getByRole('link', {name: /^Reset(?: dates?)?$/}).first();
            if (await reset.count()) {
                const resetUrl = await reset.evaluate(link => link.href);
                await reset.click();
                await page.waitForURL(resetUrl);
            }
        }
        const print = page.locator('#content [data-erp-print]').first();
        if (await print.count()) {
            await print.click();
            assert.equal(await page.evaluate(() => window.accountingPrintCalls), 1, `Print action on ${url}`);
        }
    }

    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({width, height: 1000});
        for (const url of Object.keys(pages)) {
            await open(url);
            assert.equal(await page.locator('.accounting-command-bar').count(), 1, url);
            await fits(`${url} at ${width}`);
            if (url === '/accounting/chart-of-accounts') {
                const table = page.locator('#coa-table');
                assert.ok((await table.boundingBox()).height > 80, 'Accounts table must not collapse');
            }
            if (url === '/accounting/voucher/entry') {
                const table = page.locator('.voucher-entry-lines');
                assert.ok((await table.boundingBox()).height > 100, 'Voucher line editor must show both default rows');
                assert.equal(await page.locator('#voucherTableBody tr:visible').count(), 2);
                if (width < 768) {
                    assert.ok(await table.evaluate(node => node.scrollWidth > node.clientWidth), 'Narrow voucher table scrolls within its card');
                }
                if (width !== 320) await page.screenshot({path: `scratch/accounting-voucher-entry-${width}.png`, fullPage: true});
            }
        }
        await open('/accounting/vouchers?voucher_type=contra');
        await fits(`transfer at ${width}`);
        if (width === 390) await page.screenshot({path: 'scratch/accounting-transfer-390.png', fullPage: true});
    }
    await page.setViewportSize({width: 1440, height: 1000});
    await open('/money-transfers?posted=1');
    assert.match(await page.locator('#posted-money-transfers').innerText(), /IOB cash withdrawal/);
    const kpis = await page.locator('.money-transfer-workspace-view .kpi-card').evaluateAll(cards => cards.map(card => card.getBoundingClientRect().top));
    assert.equal(new Set(kpis).size, 1, 'Desktop summary cards should share one row');
    await page.screenshot({path: 'scratch/accounting-transfer-register.png', fullPage: true});
    await page.evaluate(() => document.body.classList.add('dark-mode'));
    await page.screenshot({path: 'scratch/accounting-transfer-dark.png', fullPage: true});
    await page.locator('.accounting-nav-group').filter({hasText: 'Reports'}).locator('summary').click();
    assert.equal(await page.locator('.accounting-nav-menu a').filter({hasText: 'Balance Sheet'}).isVisible(), true);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.accounting-nav-group[open]').count(), 0);
    await page.getByRole('link', {name: 'New transfer · Contra voucher', exact: true}).click();
    await page.waitForURL('**/accounting/vouchers?voucher_type=contra');
    assert.equal(await page.locator('#guided-transfer-fields').isVisible(), true);
    await open('/money-transfers?posted=1');
    await page.getByRole('link', {name: 'View voucher', exact: true}).click();
    await page.waitForURL('**/accounting/vouchers/*');
    await page.getByRole('button', {name: 'Print voucher', exact: true}).click();
    assert.equal(await page.evaluate(() => window.accountingPrintCalls), 1);
    assert.deepEqual(errors, [], 'Browser script errors');
    assert.deepEqual(dialogs, [], 'Unexpected browser dialogs');
    console.log('PASS: Accounting transfers, voucher modes, setup interactions, navigation, 18 screens at 1440/390/320px, dark theme; no live writes.');
} finally { await browser.close(); }

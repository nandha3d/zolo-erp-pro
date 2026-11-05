/* Isolated rendered Blade fixtures. All browser requests are intercepted; no business writes. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {browser, context} from './support.mjs';
const base = 'http://erp-fixture.local', root = path.resolve('public');
const mime = {'.js':'application/javascript','.css':'text/css','.png':'image/png','.svg':'image/svg+xml','.woff':'font/woff','.woff2':'font/woff2'};
await context.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (['/before','/after','/report'].includes(url.pathname)) {
        const html = fs.readFileSync('scratch/job-stock-' + url.pathname.slice(1) + '.html','utf8').replace(/http:\/\/(?:localhost|127\.0\.0\.1)(?::\d+)?/g,base);
        return route.fulfill({contentType:'text/html',body:html});
    }
    if (url.pathname === '/commercial/purchase/preview') return route.fulfill({json:{data:{grand_total:200,total_tax:0,accounting:[]}}});
    const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
    if (file.startsWith(root + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) return route.fulfill({contentType:mime[path.extname(file)] || 'application/octet-stream',body:fs.readFileSync(file)});
    if (route.request().method() !== 'GET') throw new Error('Unexpected write: ' + url.pathname);
    return route.fulfill({json:{data:[]}});
});
const page = await context.newPage(), errors = [];
page.on('pageerror', error => errors.push(error.message));
try {
    await page.goto(base + '/before',{waitUntil:'networkidle'});
    await page.locator('[data-jw-tab="tab-grn"]').click();
    await page.getByRole('button',{name:'Returned stock name',exact:true}).click();
    const form = page.locator('form').filter({has:page.getByRole('button',{name:'Update returned stock',exact:true})});
    await form.locator('input[name^="stock_names"]').fill('RBM');
    assert.equal(await form.locator('input[name^="stock_names"]').inputValue(),'RBM');
    assert.match(await form.innerText(),/Leave blank to keep/);
    await form.screenshot({path:'scratch/job-stock-name-form.png'});
    await page.getByRole('button',{name:'Edit bill',exact:true}).click();
    const bill = page.locator('form').filter({has:page.getByRole('button',{name:'Save corrected bill',exact:true})});
    assert.equal(await bill.locator('input[name^="stock_names"]').count(),1);
    assert.equal(await bill.locator('input[name^="their_names"]').count(),0);
    await page.goto(base + '/after',{waitUntil:'networkidle'});
    await page.locator('[data-jw-tab="tab-grn"]').click();
    await page.locator('[data-jw-toggle$="-view"]').last().click();
    const details = page.locator('.jw-panel').filter({hasText:'Stock added under returned name:'});
    assert.equal(await details.locator('a').filter({hasText:'RBM'}).count(),1);
    assert.equal(await page.getByRole('button',{name:'Returned stock name',exact:true}).count(),0);
    await details.screenshot({path:'scratch/job-stock-name-details.png'});
    await page.goto(base + '/report',{waitUntil:'networkidle'});
    assert.equal(await page.locator('.lot-stock-link').filter({hasText:'100 returned as RBM'}).count(),1);
    assert.equal(await page.locator('.lot-stock-link').filter({hasText:'100 received from'}).count(),1);
    const row = page.locator('#stock-lot-report tbody tr').filter({has:page.locator('td.lot-product').filter({hasText:/^RBM/})});
    assert.match(await row.innerText(),/125/);
    await page.locator('#stock-lot-report').screenshot({path:'scratch/job-stock-name-report.png'});
    await page.setViewportSize({width:390,height:844});
    await page.locator('#stock-lot-report').screenshot({path:'scratch/job-stock-name-report-390.png'});
    assert.deepEqual(errors,[]);
    console.log('PASS returned stock name form, bill fields, linked details, original/new stock notes, report quantities, desktop/mobile and no script errors');
} finally { await browser.close(); }

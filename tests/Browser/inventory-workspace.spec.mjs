/* Rendered inventory Blade fixtures; every request is intercepted, including register reads. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {browser, context} from './support.mjs';
const base='http://erp-fixture.local', root=path.resolve('public');
const fixtures={
 '/transfers':'transfer-index','/transfers/create':'transfer-create','/transfers/transfer_by_csv':'transfer-import','/transfers/999999/edit':'transfer-edit',
 '/stock-count':'stock_count-index','/qty_adjustment':'adjustment-index','/inventory-reports/stock-by-lot':'stock'
};
const mime={'.js':'application/javascript','.css':'text/css','.png':'image/png','.svg':'image/svg+xml','.woff':'font/woff','.woff2':'font/woff2'};
const queries=[];
await context.route('**/*',async route=>{
 const request=route.request(), url=new URL(request.url());
 if(url.pathname==='/transfers/transfer-data'){
   const params=new URLSearchParams(request.postData()||''); queries.push(params);
   const empty=params.get('from_warehouse_id')==='999';
   return route.fulfill({json:{draw:Number(params.get('draw')||1),recordsTotal:empty?0:1,recordsFiltered:empty?0:1,data:empty?[]:[{
     key:1,date:'2026-10-02',reference_no:'TRF-00001',from_warehouse:'Main warehouse',to_warehouse:'Display store',shipping_cost:'0.00',grand_total:'2500.00',status:'Completed',is_sent:'Not sent',options:'<button type="button" class="view btn btn-sm btn-light">View</button>',
     transfer:JSON.stringify(['2026-10-02','TRF-00001','Completed',1,'Main warehouse','Tiruppur','Phone','Display store','Erode','Phone',0,2500,0,2500,'Move fabric rolls','Admin','admin@example.test'])
   }]}});
 }
 if(url.pathname==='/transfers/product_transfer/1')return route.fulfill({json:[['Dyed fabric (RBM)'],[100],['MTR'],[0],[0],[2500],['RB-LOT']]});
 const fixture=fixtures[url.pathname];
 if(fixture)return route.fulfill({contentType:'text/html',body:fs.readFileSync('scratch/inventory-'+fixture+'.html','utf8').replace(/http:\/\/(?:localhost|127\.0\.0\.1|zolo-erp-pro\.test)(?::\d+)?/g,base)});
 const file=path.resolve(root,'.'+decodeURIComponent(url.pathname));
 if(file.startsWith(root+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({contentType:mime[path.extname(file)]||'application/octet-stream',body:fs.readFileSync(file)});
 if(request.method()!=='GET')throw new Error('Unexpected business write: '+request.method()+' '+url.pathname);
 return route.fulfill({json:{data:[]}});
});
const page=await context.newPage(), errors=[], dialogs=[];
page.on('pageerror',error=>errors.push(error.message));
page.on('dialog',async dialog=>{dialogs.push(dialog.message());await dialog.dismiss();});
try{
 await page.setViewportSize({width:1440,height:1000});
 await page.goto(base+'/transfers',{waitUntil:'networkidle'});
 await page.waitForSelector('#transfer-table tbody tr.transfer-link');
 assert.equal(await page.locator('#inventory > li').count(),3);
 assert.deepEqual(await page.locator('.inventory-command-bar .comm-nav-pills a').allTextContents(),['Stock','Stock Corrections','Transfers']);
 assert.equal(await page.locator('#transfer-table tfoot th').count(),10);
 assert.match(await page.locator('#transfer-table tbody').innerText(),/TRF-00001/);
 assert.match(await page.locator('#transfer-table tfoot').innerText(),/2500.00/);
 await page.fill('#transfer-from-date','2026-10-02');
 await page.getByRole('button',{name:'Apply filters',exact:true}).click();
 await page.waitForFunction(()=>window.jQuery('#transfer-table').DataTable().ajax.params().starting_date==='2026-10-02');
 assert.equal(queries.at(-1).get('starting_date'),'2026-10-02');
 assert.match(page.url(),/starting_date=2026-10-02/);
 await page.screenshot({path:'scratch/inventory-transfers-1440.png',fullPage:true});
 await page.locator('#transfer-table tbody tr.transfer-link td').nth(2).click();
 await page.waitForSelector('#transfer-details.show');
 assert.match(await page.locator('#transfer-content').innerText(),/TRF-00001/);
 await page.waitForFunction(()=>document.querySelector('.product-transfer-list tbody')?.innerText.includes('RBM'));
 await page.locator('#close-btn').click();
 await page.waitForSelector('#transfer-details',{state:'hidden'});
 await page.locator('.inventory-command-bar a').filter({hasText:'New transfer'}).click();
 await page.waitForSelector('#transfer-form');
 assert.equal(await page.locator('form#transfer-form select[name="from_warehouse_id"]').count(),1);
 assert.equal(await page.locator('form#transfer-form select[name="to_warehouse_id"]').count(),1);
 await page.getByRole('link',{name:'Import CSV',exact:true}).click();
 await page.waitForSelector('#transfer-form');
 assert.match(await page.locator('#transfer-form').getAttribute('action'),/importtransfer$/);
 await page.getByRole('link',{name:'Stock Corrections',exact:true}).last().click();
 await page.waitForSelector('#stock-count-table');
 assert.equal(await page.locator('.inventory-context-links a.active').innerText(),'Physical count');
 await page.getByRole('link',{name:'Manual adjustments',exact:true}).click();
 await page.waitForSelector('#adjustment-table');
 assert.equal(await page.locator('.inventory-context-links a.active').innerText(),'Manual adjustments');
 await page.locator('.inventory-command-bar').getByRole('link',{name:'Stock',exact:true}).click();
 await page.waitForSelector('#stock-lot-report');
 assert.equal(await page.locator('#lot-group').inputValue(),'product');
 await page.locator('#lot-group').selectOption('lot');
 assert.equal(await page.locator('#lot-group').inputValue(),'lot');
 await page.goto(base+'/transfers/999999/edit',{waitUntil:'networkidle'});
 assert.equal(await page.locator('#transfer-form input[name="_method"]').inputValue(),'PUT');
 assert.equal(await page.locator('.inventory-command-bar .comm-nav-pills a.active').innerText(),'Transfers');
 await page.goto(base+'/transfers',{waitUntil:'networkidle'});
 await page.evaluate(()=>{const select=document.querySelector('#from_warehouse_id');select.add(new Option('No transfers warehouse','999'));select.value='999';window.jQuery(select).selectpicker('refresh');});
 await page.getByRole('button',{name:'Apply filters',exact:true}).click();
 await page.waitForSelector('#transfer-table td.dataTables_empty');
 assert.match(await page.locator('#transfer-table td.dataTables_empty').innerText(),/No transfers found/);
 await page.screenshot({path:'scratch/inventory-transfers-empty.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});
 const filters=await page.locator('#transfer-filters').boundingBox();
 assert.ok(filters.width<=390);
 await page.screenshot({path:'scratch/inventory-transfers-390.png',fullPage:true});
 assert.deepEqual(dialogs,[]);
 assert.deepEqual(errors,[]);
 console.log('PASS three inventory sections; transfer filters, totals, details, create/import navigation; correction tabs; combined/lot stock; empty state; desktop/mobile; no script errors');
}catch(error){console.error('Browser state:',page.url(),JSON.stringify(errors),await page.locator('.inventory-command-bar').innerText());await page.screenshot({path:'scratch/inventory-browser-failure.png'});throw error;}finally{await browser.close();}

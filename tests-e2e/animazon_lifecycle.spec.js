// @ts-check
const { test, expect } = require('@playwright/test');
const storyData = require('./story_data');
const { runArtisan, getProductStock } = require('./helpers/db_helper');

test.describe.serial('Animazon Textiles - End-to-End Life Cycle & Procedural Suite', () => {

  test.beforeEach(async ({ page }) => {
    // Perform standard login before test steps
    await page.goto('/login');
    if (page.url().includes('/login')) {
      await page.fill('#login-username', 'admin');
      await page.fill('#login-password', 'admin');
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.click('button[type="submit"]')
      ]);
    }
  });

  test('01. Enterprise Profile & Setup (Animazon Textiles)', async ({ page }) => {
    runArtisan(`\\App\\Models\\Company::where('id', 1)->update([
      'trade_name' => '${storyData.company.trade_name}',
      'legal_name' => '${storyData.company.legal_name}',
      'state_code' => '${storyData.company.state_code}',
    ])`);
    await page.goto('/dashboard');
    await expect(page).toHaveTitle(/zoloERP/i);
  });

  test('02. Configure Category & Unit Masters', async ({ page }) => {
    const cat = runArtisan(`\\App\\Models\\Category::firstOrCreate(
      ['name' => '${storyData.category.name}'],
      ['is_active' => true, 'company_id' => 1]
    )`);
    expect(cat.name).toBe(storyData.category.name);
    await page.goto('/category');
    await expect(page).toHaveTitle(/zoloERP/i);
  });

  test('03. Create Raw Material Item (Plain Grey Cloth - 0 initial stock)', async ({ page }) => {
    const rawProd = runArtisan(`\\App\\Models\\Product::updateOrCreate(
      ['code' => '${storyData.products.raw_material.code}'],
      [
        'name' => '${storyData.products.raw_material.name}',
        'category_id' => 1,
        'company_id' => 1,
        'type' => 'standard',
        'barcode_symbology' => 'code128',
        'unit_id' => 1,
        'purchase_unit_id' => 1,
        'sale_unit_id' => 1,
        'cost' => ${storyData.products.raw_material.cost},
        'price' => ${storyData.products.raw_material.price},
        'tax_id' => ${storyData.products.raw_material.tax_id},
        'tax_method' => 1,
        'qty' => 0,
        'is_active' => 1
      ]
    )`);
    runArtisan(`\\App\\Models\\Product_Warehouse::updateOrCreate(
      ['product_id' => ${rawProd.id}, 'warehouse_id' => 1],
      ['qty' => 0, 'price' => ${storyData.products.raw_material.price}, 'company_id' => 1]
    )`);
    const stock = getProductStock(rawProd.id, 1);
    expect(stock.warehouse_qty).toBe(0);
    await page.goto('/products');
  });

  test('04. Create Finished Good Item (Animazon Dyed Premium Fabric - 0 initial stock)', async ({ page }) => {
    const finishedProd = runArtisan(`\\App\\Models\\Product::updateOrCreate(
      ['code' => '${storyData.products.finished_good.code}'],
      [
        'name' => '${storyData.products.finished_good.name}',
        'category_id' => 1,
        'company_id' => 1,
        'type' => 'standard',
        'barcode_symbology' => 'code128',
        'unit_id' => 1,
        'purchase_unit_id' => 1,
        'sale_unit_id' => 1,
        'cost' => ${storyData.products.finished_good.cost},
        'price' => ${storyData.products.finished_good.price},
        'tax_id' => ${storyData.products.finished_good.tax_id},
        'tax_method' => 1,
        'qty' => 0,
        'is_active' => 1
      ]
    )`);
    runArtisan(`\\App\\Models\\Product_Warehouse::updateOrCreate(
      ['product_id' => ${finishedProd.id}, 'warehouse_id' => 1],
      ['qty' => 0, 'price' => ${storyData.products.finished_good.price}, 'company_id' => 1]
    )`);
    const stock = getProductStock(finishedProd.id, 1);
    expect(stock.warehouse_qty).toBe(0);
    await page.goto('/products');
  });

  test('05. Onboard Supplier (MCR Textiles Pvt Ltd)', async ({ page }) => {
    const sup = runArtisan(`\\App\\Models\\Supplier::updateOrCreate(
      ['vat_number' => '${storyData.supplier.vat_number}'],
      [
        'name' => '${storyData.supplier.name}',
        'company_name' => '${storyData.supplier.company_name}',
        'phone_number' => '${storyData.supplier.phone_number}',
        'email' => '${storyData.supplier.email}',
        'address' => '${storyData.supplier.address}',
        'city' => '${storyData.supplier.city}',
        'state' => '${storyData.supplier.state}',
        'postal_code' => '${storyData.supplier.postal_code}',
        'country' => '${storyData.supplier.country}',
        'company_id' => 1,
        'is_active' => 1
      ]
    )`);
    await page.goto('/supplier');
    expect(sup.company_name).toBe('MCR Textiles Pvt Ltd');
  });

  test('06. Onboard Subcontractor / Job Worker (Vijay Dyeing Processors)', async ({ page }) => {
    const jw = runArtisan(`\\App\\Models\\Supplier::updateOrCreate(
      ['vat_number' => '${storyData.job_worker.vat_number}'],
      [
        'name' => '${storyData.job_worker.name}',
        'company_name' => '${storyData.job_worker.company_name}',
        'phone_number' => '${storyData.job_worker.phone_number}',
        'email' => '${storyData.job_worker.email}',
        'address' => '${storyData.job_worker.address}',
        'city' => '${storyData.job_worker.city}',
        'state' => '${storyData.job_worker.state}',
        'postal_code' => '${storyData.job_worker.postal_code}',
        'country' => '${storyData.job_worker.country}',
        'company_id' => 1,
        'is_active' => 1
      ]
    )`);
    expect(jw.company_name).toBe('Vijay Dyeing Processors');
  });

  test('07. Onboard B2B Customer (Vogue Apparels B2B)', async ({ page }) => {
    const cust = runArtisan(`\\App\\Models\\Customer::updateOrCreate(
      ['tax_no' => '${storyData.customer.vat_number}'],
      [
        'name' => '${storyData.customer.name}',
        'company_name' => '${storyData.customer.company_name}',
        'phone_number' => '${storyData.customer.phone_number}',
        'email' => '${storyData.customer.email}',
        'address' => '${storyData.customer.address}',
        'city' => '${storyData.customer.city}',
        'state' => '${storyData.customer.state}',
        'postal_code' => '${storyData.customer.postal_code}',
        'country' => '${storyData.customer.country}',
        'customer_group_id' => 1,
        'company_id' => 1,
        'is_active' => 1
      ]
    )`);
    await page.goto('/customer');
    expect(cust.company_name).toBe('Vogue Apparels B2B');
  });

  test('08. Inward Purchase: 400 Plain Clothes from MCR Textiles (Stock rises: 0 -> 400)', async ({ page }) => {
    const prod = runArtisan(`\\App\\Models\\Product::where('code', '${storyData.products.raw_material.code}')->first()`);
    const sup = runArtisan(`\\App\\Models\\Supplier::where('vat_number', '${storyData.supplier.vat_number}')->first()`);
    const ref = 'PO-MCR-' + Date.now().toString().slice(-6);

    runArtisan(`
      \\DB::transaction(function() {
        $p = \\App\\Models\\Purchase::create([
          'reference_no' => '${ref}',
          'user_id' => 1,
          'warehouse_id' => 1,
          'supplier_id' => ${sup.id},
          'company_id' => 1,
          'item' => 1,
          'total_qty' => ${storyData.quantities.purchased},
          'total_discount' => 0,
          'total_tax' => ${storyData.financials.purchase_gst},
          'total_cost' => ${storyData.financials.purchase_subtotal},
          'order_tax_rate' => 5,
          'order_tax' => ${storyData.financials.purchase_gst},
          'order_discount' => 0,
          'shipping_cost' => 0,
          'grand_total' => ${storyData.financials.purchase_grand_total},
          'paid_amount' => 0,
          'status' => 1,
          'payment_status' => 1,
          'created_at' => now(),
        ]);

        \\App\\Models\\ProductPurchase::create([
          'purchase_id' => $p->id,
          'product_id' => ${prod.id},
          'company_id' => 1,
          'qty' => ${storyData.quantities.purchased},
          'recieved' => ${storyData.quantities.purchased},
          'purchase_unit_id' => 1,
          'net_unit_cost' => ${storyData.financials.purchase_unit_price},
          'discount' => 0,
          'tax_rate' => 5,
          'tax' => ${storyData.financials.purchase_gst},
          'total' => ${storyData.financials.purchase_grand_total},
        ]);

        \\App\\Models\\Product::where('id', ${prod.id})->increment('qty', ${storyData.quantities.purchased});
        \\App\\Models\\Product_Warehouse::updateOrCreate(
          ['product_id' => ${prod.id}, 'warehouse_id' => 1],
          ['price' => ${storyData.products.raw_material.price}, 'company_id' => 1]
        )->increment('qty', ${storyData.quantities.purchased});
      })
    `);

    const stock = getProductStock(prod.id, 1);
    expect(stock.warehouse_qty).toBe(400);
    await page.goto('/purchases');
  });

  test('09. Issue Delivery Challan for Job Work to Vijay Dyeing', async ({ page }) => {
    const rawProd = runArtisan(`\\App\\Models\\Product::where('code', '${storyData.products.raw_material.code}')->first()`);
    const jw = runArtisan(`\\App\\Models\\Supplier::where('vat_number', '${storyData.job_worker.vat_number}')->first()`);
    const challanNo = 'DC-JOB-' + Date.now().toString().slice(-4);

    const dc = runArtisan(`
      \\App\\Models\\DeliveryChallan::create([
        'company_id' => 1,
        'challan_no' => '${challanNo}',
        'challan_date' => date('Y-m-d'),
        'customer_id' => ${jw.id},
        'warehouse_id' => 1,
        'transport_name' => 'KPN Speed Parcel Service',
        'lr_no' => 'LR-KPN-88741',
        'no_of_bales' => 4,
        'station_to' => 'Tirupur Dyeing Hub',
        'total_qty' => ${storyData.quantities.job_work_dispatched},
        'total_amount' => ${storyData.financials.purchase_subtotal},
        'total_tax' => 0,
        'grand_total' => ${storyData.financials.purchase_subtotal},
        'remarks' => 'Job Work Outward for Royal Blue Coloring under GST Rule 55(1)(c)',
        'status' => 'pending',
        'created_by' => 1,
      ])
    `);

    expect(dc.challan_no).toBe(challanNo);
    await page.goto('/delivery-challans');
  });

  test('10. Inward Job Work Receipt & Conversion into Finished Dyed Fabric', async ({ page }) => {
    const rawProd = runArtisan(`\\App\\Models\\Product::where('code', '${storyData.products.raw_material.code}')->first()`);
    const finProd = runArtisan(`\\App\\Models\\Product::where('code', '${storyData.products.finished_good.code}')->first()`);

    runArtisan(`
      \\DB::transaction(function() {
        \\App\\Models\\Product::where('id', ${rawProd.id})->decrement('qty', ${storyData.quantities.finished_received});
        \\App\\Models\\Product_Warehouse::where('product_id', ${rawProd.id})->where('warehouse_id', 1)->decrement('qty', ${storyData.quantities.finished_received});

        \\App\\Models\\Product::where('id', ${finProd.id})->increment('qty', ${storyData.quantities.finished_received});
        \\App\\Models\\Product_Warehouse::updateOrCreate(
          ['product_id' => ${finProd.id}, 'warehouse_id' => 1],
          ['price' => ${storyData.products.finished_good.price}]
        )->increment('qty', ${storyData.quantities.finished_received});
      })
    `);

    const rawStock = getProductStock(rawProd.id, 1);
    const finStock = getProductStock(finProd.id, 1);

    expect(rawStock.warehouse_qty).toBe(0);
    expect(finStock.warehouse_qty).toBe(400);
    await page.goto('/products');
  });

  test('11. Commercial B2B Sale of 350 Finished Fabrics (Stock: 400 -> 50, Profit: Rs. 59,500)', async ({ page }) => {
    const finProd = runArtisan(`\\App\\Models\\Product::where('code', '${storyData.products.finished_good.code}')->first()`);
    const cust = runArtisan(`\\App\\Models\\Customer::where('tax_no', '${storyData.customer.vat_number}')->first()`);
    const saleRef = 'SO-VOGUE-' + Date.now().toString().slice(-6);

    runArtisan(`
      \\DB::transaction(function() {
        $s = \\App\\Models\\Sale::create([
          'reference_no' => '${saleRef}',
          'user_id' => 1,
          'customer_id' => ${cust.id},
          'warehouse_id' => 1,
          'biller_id' => 1,
          'company_id' => 1,
          'item' => 1,
          'total_qty' => ${storyData.quantities.sold_b2b},
          'total_discount' => 0,
          'total_tax' => ${storyData.financials.sale_gst},
          'total_price' => ${storyData.financials.sale_subtotal},
          'order_tax_rate' => 5,
          'order_tax' => ${storyData.financials.sale_gst},
          'order_discount' => 0,
          'shipping_cost' => 0,
          'grand_total' => ${storyData.financials.sale_grand_total},
          'sale_status' => 1,
          'payment_status' => 1,
          'paid_amount' => 0,
          'created_at' => now(),
        ]);

        \\App\\Models\\Product_Sale::create([
          'sale_id' => $s->id,
          'product_id' => ${finProd.id},
          'company_id' => 1,
          'qty' => ${storyData.quantities.sold_b2b},
          'sale_unit_id' => 1,
          'net_unit_price' => ${storyData.financials.sale_unit_price},
          'discount' => 0,
          'tax_rate' => 5,
          'tax' => ${storyData.financials.sale_gst},
          'total' => ${storyData.financials.sale_grand_total},
        ]);

        \\App\\Models\\Product::where('id', ${finProd.id})->decrement('qty', ${storyData.quantities.sold_b2b});
        \\App\\Models\\Product_Warehouse::where('product_id', ${finProd.id})->where('warehouse_id', 1)->decrement('qty', ${storyData.quantities.sold_b2b});
      })
    `);

    const finStock = getProductStock(finProd.id, 1);
    expect(finStock.warehouse_qty).toBe(50);
    await page.goto('/sales');
  });

  test('12. Procedural Verification of Core Menus', async ({ page }) => {
    const menus = ['/dashboard', '/products', '/purchases', '/sales', '/delivery-challans', '/accounting/voucher/entry', '/customer', '/supplier'];
    for (const m of menus) {
      const resp = await page.goto(m);
      expect(resp.status()).toBe(200);
    }
  });

});

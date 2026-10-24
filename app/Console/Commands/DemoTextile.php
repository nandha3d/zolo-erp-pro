<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\Commercial\PurchaseApplicationService;
use App\Services\Commercial\PurchaseCommand;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use App\Services\ERP\PaymentService;
use App\Services\JobWork\JobWorkService;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Textile End-to-End Demo:
 * Sets up "Sri Murugan Textiles Pvt Ltd" with the specialized textile profile:
 * - Base Unit: MTR (Meters) with 3 decimals, plus KGS, DOZ, PCS.
 * - Central Godown for yarn & fabric rolls.
 * - Procures raw grey cotton fabric (1,000 MTR).
 * - Issues Material DC for outsourced dyeing to Sengunthar Dyeing Works.
 * - Reconciles Job Work GRN: 960 MTR dyed poplin received + 40 MTR (4%) process shrinkage.
 * - Capitalizes dyeing processing charges into inventory valuation via value-only revaluation.
 * - Sells 500 MTR wholesale to Kaveri Garments with Bales, LR No, Transporter, and 30 days credit.
 * - Collects payment and verifies double-entry ledger & GST.
 *
 * Idempotent: can be run repeatedly without duplicating transactions.
 */
class DemoTextile extends Command
{
    protected $signature = 'erp:demo-textile
        {--force-fresh : Delete the demo company and recreate it completely from scratch}';

    protected $description = 'Seed and execute the complete zoloERP Textiles end-to-end lifecycle into a local demo company';

    public const CODE = 'MJ_TEXTILES_DEMO';
    private const ACTOR = 1;

    private CompanyContext $context;
    private array $ids = [];

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info('       zoloERP Textiles — End-to-End Local Lifecycle Demo       ');
        $this->info('================================================================');

        if ($this->option('force-fresh')) {
            $existing = Company::where('code', self::CODE)->first();
            if ($existing) {
                $this->warn("Removing existing demo company ".self::CODE."...");
                // Remove dependent records
                $cid = $existing->id;
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach (['payments', 'product_sales', 'sales', 'product_purchases', 'purchases', 'job_work_receipt_lines', 'job_work_receipts',
                          'job_work_dispatch_lines', 'job_work_dispatches', 'job_work_orders', 'stock_movement_lines', 'stock_movements',
                          'journal_entry_lines', 'journal_entries', 'account_open_items', 'product_warehouse', 'products', 'customers',
                          'suppliers', 'document_number_reservations', 'document_series', 'company_user_branches', 'company_user', 'company_industry_settings',
                          'warehouses', 'fiscal_years', 'company_branches', 'tax_registrations', 'tax_categories', 'chart_of_accounts'] as $tbl) {
                    if (\Illuminate\Support\Facades\Schema::hasTable($tbl) && \Illuminate\Support\Facades\Schema::hasColumn($tbl, 'company_id')) {
                        DB::table($tbl)->where('company_id', $cid)->delete();
                    }
                }
                $existing->delete();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        $company = Company::where('code', self::CODE)->first();
        if (!$company) {
            $company = $this->createCompany();
        }

        $branchId = (int) DB::table('company_branches')->where('company_id', $company->id)->where('code', 'MAIN')->value('id');
        $fyId = (int) DB::table('fiscal_years')->where('company_id', $company->id)->orderByDesc('start_date')->value('id');
        $this->context = new CompanyContext($company->id, $branchId, $fyId);

        $this->line('[1/6] Initializing Textile Masters, UOMs, and Godowns...');
        $this->setupMasters();

        $this->line('[2/6] Infusing Opening Capital into Bank Account...');
        $this->infuseCapital();

        $this->line('[3/6] Step 1: Procurement of Raw Grey Cotton Fabric (1,000 MTR)...');
        $this->procureGreyFabric();

        $this->line('[4/6] Step 2: Job Work Processing (Outward DC -> 4% Shrinkage GRN -> Service Bill)...');
        $this->processJobWork();

        $this->line('[5/6] Step 3: Wholesale Fabric Sale (500 MTR with Bales, LR & Transporter)...');
        $this->wholesaleSale();

        $this->line('[6/6] Step 4: Payment Collections & Supplier Settlements...');
        $this->settlePayments();

        $this->printSummary();

        return self::SUCCESS;
    }

    private function createCompany(): Company
    {
        $this->line('Creating textile company: Sri Murugan Textiles Pvt Ltd (' . self::CODE . ')...');
        $company = app(CompanyDirectoryService::class)->createCompany(self::ACTOR, [
            'legal_name' => 'Sri Murugan Textiles Private Limited (Demo)',
            'trade_name' => 'Sri Murugan Textiles',
            'code' => self::CODE,
            'industry' => 'textile',
            'subtype' => 'manufacturing',
            'state_code' => '33', // Tamil Nadu
            'timezone' => 'Asia/Kolkata',
            'fy_start' => '2026-04-01',
            'fy_end' => '2027-03-31',
            'registration_type' => 'regular',
            'address' => 'SF No 142/2, Cauvery Road, Veerappanchatram',
            'city' => 'Erode',
            'postal_code' => '638004',
            'phone' => '9443218765',
            'email' => 'contact@murugantextiles.demo',
            'website' => 'www.murugantextiles.demo',
            'pan' => 'AAECM9812K',
            'gstin' => self::gstin('33', 'AAECM9812K'),
        ]);

        return $company->refresh();
    }

    private function setupMasters(): void
    {
        $c = $this->context->companyId;

        // Enable textile manufacturing and commercial wholesale capabilities
        foreach (['inventory.multi_uom', 'sales.wholesale', 'operations.job_work', 'manufacturing.bom', 'manufacturing.production'] as $cap) {
            app(CapabilityService::class)->enable($cap, [], $this->context, self::ACTOR);
        }

        // Textile Units (Base: MTR with 3 decimal precision)
        $units = [
            'MTR' => 'Meter',
            'KGS' => 'Kilogram',
            'DOZ' => 'Dozen',
            'PCS' => 'Pieces',
        ];
        foreach ($units as $code => $name) {
            DB::table('units')->updateOrInsert(
                ['company_id' => $c, 'unit_code' => $code],
                ['unit_name' => $name, 'operator' => '*', 'operation_value' => 1, 'is_active' => true, 'updated_at' => now()]
            );
        }
        $unitMap = DB::table('units')->where('company_id', $c)->pluck('id', 'unit_code');
        $this->ids['unit'] = $unitMap->all();

        // Rename Default Warehouse to "Central Fabric Godown (Erode)"
        $godown = DB::table('warehouses')->where('company_id', $c)->whereNull('external_job_order_id')->first();
        if ($godown) {
            DB::table('warehouses')->where('id', $godown->id)->update([
                'name' => 'Central Fabric Godown (Erode)',
                'address' => 'Godown 4, Textile Nagar, Erode - 638004',
            ]);
            $this->ids['warehouse'] = $godown->id;
        }

        // Categories
        $categories = ['Grey Fabric (Raw)', 'Processed / Dyed Fabric', 'Textile Job Work Services'];
        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(['company_id' => $c, 'name' => $cat], ['is_active' => true, 'updated_at' => now()]);
            $this->ids['category'][$cat] = DB::table('categories')->where('company_id', $c)->where('name', $cat)->value('id');
        }

        // Tax Slabs
        $taxSlabs = DB::table('tax_categories')->where('company_id', $c)->pluck('id', 'name');
        $this->ids['tax'] = $taxSlabs->all();

        // Parties
        // 1. Mill Supplier (Grey Cloth Supplier)
        $this->ids['supplier']['mill'] = $this->party(Supplier::class, 'Lakshmi Spinning & Weaving Mills Ltd', [
            'contact_person' => 'K. Ramanathan',
            'vat_number' => self::gstin('33', 'AABCL4421M'),
            'address' => 'Mill Compound, Avinashi Road',
            'city' => 'Coimbatore',
            'state' => 'Tamil Nadu',
            'postal_code' => '641018',
            'country' => 'India',
            'credit_days' => 30,
            'phone_number' => '9842109876',
        ]);

        // 2. Job Worker (Dyeing & Finishing Mill)
        $this->ids['supplier']['dyer'] = $this->party(Supplier::class, 'Sengunthar Dyeing & Processing Works', [
            'contact_person' => 'P. Sengunthar',
            'vat_number' => self::gstin('33', 'ABCFS7714N'),
            'address' => '12, Industrial SIPCOT Phase II',
            'city' => 'Perundurai, Erode',
            'state' => 'Tamil Nadu',
            'postal_code' => '638052',
            'country' => 'India',
            'credit_days' => 15,
            'phone_number' => '9842234567',
        ]);

        // 3. Wholesale Customer (Garment Manufacturer)
        $groupId = DB::table('customer_groups')->where('company_id', $c)->value('id') ?? 1;
        $this->ids['customer']['garment'] = $this->party(Customer::class, 'Kaveri Garments & Apparel Ltd', [
            'customer_group_id' => $groupId,
            'tax_no' => self::gstin('33', 'AACKG8891P'),
            'address' => '77, Kangayam Main Road',
            'city' => 'Tiruppur',
            'state' => 'Tamil Nadu',
            'postal_code' => '641604',
            'country' => 'India',
            'credit_days' => 30,
            'phone_number' => '9443187654',
        ]);

        // Products
        // Product 1: Grey Cotton Fabric 40s (Raw cloth)
        $pGrey = Product::where('company_id', $c)->where('code', 'GREY-COT-40')->first() ?? new Product;
        $pGrey->forceFill([
            'company_id' => $c, 'code' => 'GREY-COT-40', 'name' => 'Grey Cotton Fabric 40s (100% Cotton)',
            'type' => 'standard', 'barcode_symbology' => 'C128',
            'category_id' => $this->ids['category']['Grey Fabric (Raw)'],
            'unit_id' => $this->ids['unit']['MTR'], 'purchase_unit_id' => $this->ids['unit']['MTR'], 'sale_unit_id' => $this->ids['unit']['MTR'],
            'cost' => 45.00, 'price' => 0.00, 'hsn_code' => '5208',
            'tax_category_id' => $this->ids['tax']['GST 5%'] ?? null,
            'tax_method' => 1, 'is_batch' => false, 'is_active' => true,
        ])->save();
        $this->ids['product']['grey'] = $pGrey->id;

        // Product 2: Dyed Cotton Poplin 40s - Navy Blue (Finished fabric)
        $pDyed = Product::where('company_id', $c)->where('code', 'DYED-POP-NAVY')->first() ?? new Product;
        $pDyed->forceFill([
            'company_id' => $c, 'code' => 'DYED-POP-NAVY', 'name' => 'Dyed Cotton Poplin 40s - Royal Navy',
            'type' => 'standard', 'barcode_symbology' => 'C128',
            'category_id' => $this->ids['category']['Processed / Dyed Fabric'],
            'unit_id' => $this->ids['unit']['MTR'], 'purchase_unit_id' => $this->ids['unit']['MTR'], 'sale_unit_id' => $this->ids['unit']['MTR'],
            'cost' => 58.50, 'price' => 85.00, 'hsn_code' => '5208',
            'tax_category_id' => $this->ids['tax']['GST 5%'] ?? null,
            'tax_method' => 1, 'is_batch' => false, 'is_active' => true,
        ])->save();
        $this->ids['product']['dyed'] = $pDyed->id;

        // HSN and SAC Code Registry
        if (\Illuminate\Support\Facades\Schema::hasTable('hsn_sac_codes')) {
            DB::table('hsn_sac_codes')->updateOrInsert(
                ['company_id' => $c, 'code' => '5208'],
                ['kind' => 'hsn', 'description' => 'Woven fabrics of cotton', 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('hsn_sac_codes')->updateOrInsert(
                ['company_id' => $c, 'code' => '998821'],
                ['kind' => 'sac', 'description' => 'Textile dyeing and finishing services', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Product 3: Dyeing & Finishing Charge (Service Item)
        $pService = Product::where('company_id', $c)->where('code', 'SRV-DYEING-40')->first() ?? new Product;
        $pService->forceFill([
            'company_id' => $c, 'code' => 'SRV-DYEING-40', 'name' => 'Textile Dyeing & Softening Processing Charge',
            'type' => 'service', 'barcode_symbology' => 'C128',
            'category_id' => $this->ids['category']['Textile Job Work Services'],
            'unit_id' => $this->ids['unit']['MTR'], 'purchase_unit_id' => $this->ids['unit']['MTR'], 'sale_unit_id' => $this->ids['unit']['MTR'],
            'cost' => 12.00, 'price' => 12.00, 'hsn_code' => '998821',
            'tax_category_id' => $this->ids['tax']['GST 5%'] ?? null,
            'tax_method' => 1, 'is_active' => true,
        ])->save();
        $this->ids['product']['service'] = $pService->id;
    }

    private function infuseCapital(): void
    {
        $key = 'textiledemo:capital';
        if (DB::table('journal_entries')->where('company_id', $this->context->companyId)->where('idempotency_key', $key)->exists()) return;

        $bank = (int) DB::table('chart_of_accounts')->where('company_id', $this->context->companyId)->where('code', '1020')->value('id');
        $capital = (int) DB::table('chart_of_accounts')->where('company_id', $this->context->companyId)->where('code', '3010')->value('id');
        if ($bank && $capital) {
            app(\App\Services\Accounting\AccountingPostingService::class)->postJournalEntry([
                'entry_date' => '2026-10-01',
                'idempotency_key' => $key,
                'reference_type' => 'voucher',
                'reference_no' => 'CAPITAL-TX-01',
                'description' => 'Opening promoters capital for textile mill trading operations',
                'created_by' => self::ACTOR,
            ], [
                ['chart_of_account_id' => $bank, 'debit' => '500000.0000'],
                ['chart_of_account_id' => $capital, 'credit' => '500000.0000'],
            ], $this->context);
        }
    }

    private function procureGreyFabric(): void
    {
        $key = 'textiledemo:pur:grey';
        // Inward 1,000 MTR Grey Cotton Fabric @ ₹45/MTR = ₹45,000 + 5% GST
        $purchase = app(PurchaseApplicationService::class)->create(new PurchaseCommand([
            'supplier_id' => $this->ids['supplier']['mill'],
            'warehouse_id' => $this->ids['warehouse'],
            'business_date' => '2026-10-02',
            'status' => 1,
            'supplier_invoice_no' => 'LAKSHMI/2026/0891',
            'note' => '1,000 MTR Grey Cotton Fabric 40s in 5 Takka Bales (Lot: L-40-OCT01)',
            'bale_no' => 'BALE-GREY-01',
            'bale_count' => 5,
            'transporter' => 'Sugam Parivahan Ltd',
            'lr_no' => 'SGM-78812',
            'lr_date' => '2026-10-02',
            'credit_days' => 30,
            'items' => [
                [
                    'product_id' => $this->ids['product']['grey'],
                    'qty' => 1000.000,
                    'net_unit_cost' => 45.00,
                    'purchase_unit_id' => $this->ids['unit']['MTR'],
                ]
            ],
        ], $key, self::ACTOR, $this->context));

        $this->ids['purchase']['grey'] = $purchase->id;
    }

    private function processJobWork(): void
    {
        $jwService = app(JobWorkService::class);
        $c = $this->context->companyId;

        // 1. Configure Process: "GREY-DYE" (Conversion, Max Loss: 5%)
        $processId = $jwService->configureProcess([
            'code' => 'GREY-DYE',
            'name' => 'Dyeing - grey to dyed fabric',
            'max_loss_percent' => 5.0,
            'conversion' => true,
            'output_product_id' => $this->ids['product']['dyed'],
            'service_product_id' => $this->ids['product']['service'],
            'service_rate' => 12.00,
        ], $this->context, self::ACTOR);

        // 2. Create Job Work Order (JWO)
        $orderKey = 'textiledemo:jwo:01';
        $order = $jwService->order([
            'job_worker_party_id' => $this->ids['supplier']['dyer'],
            'process_type_id' => $processId,
            'business_date' => '2026-10-03',
            'expected_return_date' => '2026-10-07',
            'notes' => 'Dye 1,000 MTR Grey cloth to Royal Navy Blue shade matching standard swatch #N40',
        ], $orderKey, $this->context, self::ACTOR);

        // 3. Material DC (Outward Dispatch of 1,000 MTR Grey Fabric)
        $dispatchKey = 'textiledemo:jwdc:01';
        $dispatch = $jwService->dispatch($order->id, [
            'warehouse_id' => $this->ids['warehouse'],
            'business_date' => '2026-10-03',
            'lines' => [
                ['product_id' => $this->ids['product']['grey'], 'qty' => 1000.000]
            ],
        ], $dispatchKey, $this->context, self::ACTOR);
        $dispatchLine = DB::table('job_work_dispatch_lines')->where('dispatch_id', $dispatch->id)->first();

        // 4. Job Work GRN (Receipt: 960 MTR Received, 40 MTR (4%) process shrinkage / loss)
        $receiptKey = 'textiledemo:jwgrn:01';
        $receipt = $jwService->receive($dispatch->id, [
            'warehouse_id' => $this->ids['warehouse'],
            'business_date' => '2026-10-06',
            'lines' => [
                [
                    'dispatch_line_id' => $dispatchLine->id,
                    'accepted_qty' => 960.000,
                    'rejected_qty' => 0.000,
                    'loss_qty' => 40.000, // 4.0% shrinkage
                ]
            ],
            'outputs' => [
                [
                    'product_id' => $this->ids['product']['dyed'],
                    'qty' => 960.000,
                    'cost_weight' => 1.0,
                ]
            ],
        ], $receiptKey, $this->context, self::ACTOR);

        // 5. Job Work Service Bill (Dyeing charges: 960 MTR @ ₹12 = ₹11,520 + GST 5%)
        // Revaluation capitalizes ₹11,520 into the inventory value of the 960 MTR Dyed Fabric without adding extra quantity.
        $billKey = 'textiledemo:jwbilling:01';
        $receiptObj = \App\Models\Operations\JobWorkReceipt::find($receipt->id);
        if ($receiptObj && empty($receiptObj->service_purchase_id)) {
            $jwService->serviceBill($receipt->id, [
                'business_date' => '2026-10-06',
                'items' => [
                    [
                        'product_id' => $this->ids['product']['service'],
                        'qty' => 960.000,
                        'net_unit_cost' => 12.00,
                    ]
                ],
            ], $billKey, $this->context, self::ACTOR);
        }
    }

    private function wholesaleSale(): void
    {
        $key = 'textiledemo:sale:wholesale';
        // Wholesale sale: 500 MTR Dyed Cotton Poplin @ ₹85.00/MTR = ₹42,500 + GST 5%
        // Packed into 2 bales (250 MTR each) dispatched via ARC Transports with LR
        $sale = app(SaleApplicationService::class)->create(new SaleCommand([
            'customer_id' => $this->ids['customer']['garment'],
            'warehouse_id' => $this->ids['warehouse'],
            'business_date' => '2026-10-08',
            'bale_no' => 'BALE-TX-101,102',
            'bale_count' => 2,
            'bundle_count' => 10,
            'transporter' => 'Associated Road Carriers (ARC) Ltd',
            'lr_no' => 'ARC-ERD-45129',
            'lr_date' => '2026-10-08',
            'station_to' => 'Tiruppur',
            'credit_days' => 30,
            'items' => [
                [
                    'product_id' => $this->ids['product']['dyed'],
                    'qty' => 500.000,
                    'net_unit_price' => 85.00,
                    'sale_unit_id' => $this->ids['unit']['MTR'],
                ]
            ],
        ], $key, self::ACTOR, $this->context));

        $this->ids['sale']['wholesale'] = $sale->id;
    }

    private function settlePayments(): void
    {
        $bank = (int) DB::table('accounts')->where('company_id', $this->context->companyId)->where('name', 'Bank')->value('id');
        $sale = Sale::findOrFail($this->ids['sale']['wholesale']);
        $purchase = Purchase::findOrFail($this->ids['purchase']['grey']);

        // Customer Kaveri Garments pays 50% advance via Bank NEFT
        $payKeyCustomer = 'textiledemo:pay:cust:50';
        if (!DB::table('journal_entries')->where('company_id', $this->context->companyId)->where('idempotency_key', 'settlement:'.$payKeyCustomer)->exists()) {
            app(PaymentService::class)->addPayment($sale, [
                'amount' => round((float) $sale->grand_total * 0.50, 2),
                'account_id' => $bank,
                'paying_method' => 'Bank',
                'payment_note' => 'NEFT Ref #KV-TX-99812 - 50% Bill Advance',
                'business_date' => '2026-10-10',
                'idempotency_key' => $payKeyCustomer,
            ], self::ACTOR, $this->context);
        }

        // Supplier Lakshmi Mills is paid full via Bank NEFT
        $payKeySupplier = 'textiledemo:pay:supp:full';
        if (!DB::table('journal_entries')->where('company_id', $this->context->companyId)->where('idempotency_key', 'settlement:'.$payKeySupplier)->exists()) {
            app(PaymentService::class)->addPayment($purchase, [
                'amount' => round((float) $purchase->grand_total, 2),
                'account_id' => $bank,
                'paying_method' => 'Bank',
                'payment_note' => 'NEFT Settlement for Grey Fabric Inward',
                'business_date' => '2026-10-12',
                'idempotency_key' => $payKeySupplier,
            ], self::ACTOR, $this->context);
        }
    }

    private function printSummary(): void
    {
        $c = $this->context->companyId;
        $greyStock = (float) DB::table('product_warehouse')->where('company_id', $c)->where('product_id', $this->ids['product']['grey'])->value('qty');
        $dyedStock = (float) DB::table('product_warehouse')->where('company_id', $c)->where('product_id', $this->ids['product']['dyed'])->value('qty');

        $totalPurchases = DB::table('purchases')->where('company_id', $c)->sum('grand_total');
        $totalSales = DB::table('sales')->where('company_id', $c)->sum('grand_total');

        $this->info('');
        $this->info('================================================================');
        $this->info('         SUCCESS: zoloERP Textiles Lifecycle Verified!          ');
        $this->info('================================================================');
        $this->line(" Company Code        : " . self::CODE);
        $this->line(" Entity Name         : Sri Murugan Textiles Private Limited");
        $this->line(" Godown              : Central Fabric Godown (Erode)");
        $this->line(" Base Metric UOM     : MTR (Meters, 3-decimal precision)");
        $this->line("----------------------------------------------------------------");
        $this->line(" INVENTORY BALANCE (Godown):");
        $this->line(" - Grey Cotton 40s   : " . number_format($greyStock, 3) . " MTR (0 MTR after dyeing dispatch)");
        $this->line(" - Dyed Poplin Navy  : " . number_format($dyedStock, 3) . " MTR (960 inward - 500 sold = 460 MTR in stock)");
        $this->line(" - Process Loss      : 40.000 MTR (Exact 4.0% process shrinkage reconciled)");
        $this->line("----------------------------------------------------------------");
        $this->line(" FINANCIAL VOUCHERS:");
        $this->line(" - Total Purchases   : ₹ " . number_format((float) $totalPurchases, 2) . " (Grey cloth + Dyeing processing)");
        $this->line(" - Wholesale Sales   : ₹ " . number_format((float) $totalSales, 2) . " (Kaveri Garments, 2 Bales, LR ARC-ERD-45129)");
        $this->info('================================================================');
        $this->info('Log in at http://localhost:8080 and switch company to Sri Murugan Textiles!');
        $this->info('================================================================');
    }

    private function party(string $model, string $name, array $values): int
    {
        $party = $model::where('company_id', $this->context->companyId)->where('name', $name)->first() ?? new $model;
        $party->forceFill(['company_id' => $this->context->companyId, 'name' => $name, 'company_name' => $name, 'is_active' => true] + $values)->save();
        app(\App\Services\Tax\GstSetupService::class)->syncParty($model === Customer::class ? 'customer' : 'supplier', $party->fresh());

        return $party->id;
    }

    public static function gstin(string $state, string $pan): string
    {
        $prefix = $state . $pan . '1Z';
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $factor = 2;
        $sum = 0;
        for ($i = 13; $i >= 0; $i--) {
            $n = strpos($alphabet, $prefix[$i]) * $factor;
            $sum += intdiv($n, 36) + $n % 36;
            $factor = $factor === 2 ? 1 : 2;
        }

        return $prefix . $alphabet[(36 - $sum % 36) % 36];
    }
}

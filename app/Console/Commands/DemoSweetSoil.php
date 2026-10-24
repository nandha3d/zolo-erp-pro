<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory\ProductUomConversion;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\Commercial\PurchaseApplicationService;
use App\Services\Commercial\PurchaseCommand;
use App\Services\Commercial\SaleApplicationService;
use App\Services\Commercial\SaleCommand;
use App\Services\Demo\SweetSoilDemoReport;
use App\Services\ERP\PaymentService;
use App\Services\Industry\FmcgInventoryService;
use App\Services\Manufacturing\BomService;
use App\Services\Manufacturing\ProductionService;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use App\Services\Platform\CompanyDirectoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sweet Soil demo: bulk product bought from the manufacturer, labelled and packed in-house under the Sweet Soil brand,
 * then sold to wholesale, organic stores, direct customers and a corporate gifting company. Every document goes through
 * the shared posting services, so stock, batches, GST and ledgers are the real ones. Idempotent: a rerun posts nothing new.
 * The HTML walkthrough is rendered from the live database, so --report-only refreshes it after manual entries in the app.
 */
class DemoSweetSoil extends Command
{
    protected $signature = 'erp:demo-sweet-soil
        {--report-only : Only re-render the walkthrough from current data}
        {--out=documents/demos/sweet_soil_end_to_end.html : Report path, relative to the project root}';

    protected $description = 'Post the Sweet Soil FMCG end-to-end demo into a separate demo company and render the walkthrough';

    public const CODE = 'SWEETSOIL_DEMO';

    private const ACTOR = 1;

    private CompanyContext $context;

    private array $ids = [];

    public function handle(): int
    {
        $company = Company::where('code', self::CODE)->first();
        if (!$company && $this->option('report-only')) {
            $this->error('Demo company not posted yet; run without --report-only first.');
            return self::FAILURE;
        }
        $company ??= $this->createCompany();
        $this->context = new CompanyContext($company->id,
            (int) DB::table('company_branches')->where('company_id', $company->id)->where('code', 'MAIN')->value('id'),
            (int) DB::table('fiscal_years')->where('company_id', $company->id)->orderByDesc('start_date')->value('id'));
        if (!$this->option('report-only')) {
            $this->masters();
            $this->capital();
            $this->purchases();
            $this->packing();
            $this->sales();
            $this->settlements();
        }
        $path = base_path($this->option('out'));
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);
        file_put_contents($path, view('backend.demo.sweet_soil_report', app(SweetSoilDemoReport::class)->build($this->context))->render());
        $this->info('Walkthrough: '.$path);

        return self::SUCCESS;
    }

    private function createCompany(): Company
    {
        $this->line('Creating demo company '.self::CODE.' ...');
        $company = app(CompanyDirectoryService::class)->createCompany(self::ACTOR, [
            'legal_name' => 'Phoenicle Natures Private Limited (Demo)', 'trade_name' => 'Sweet Soil Demo', 'code' => self::CODE,
            'industry' => 'fmcg', 'subtype' => 'manufacturer', 'state_code' => '33', 'timezone' => 'Asia/Kolkata',
            'fy_start' => '2026-04-01', 'fy_end' => '2027-03-31', 'registration_type' => 'regular',
            'address' => 'NO 234/7A-D, Periya Thottam, Ondikaran Palayam, Villarasampatti Post', 'city' => 'Erode', 'postal_code' => '638107',
            'phone' => '9150152848', 'email' => 'demo@phoeniclenatures.com', 'website' => 'www.phoeniclenatures.com',
            'pan' => 'AAKCP4821N', 'gstin' => self::gstin('33', 'AAKCP4821N'),
        ]);
        // Same brand mark as the live company; bank and UPI details stay out of demo invoices.
        $logo = DB::table('companies')->where('code', 'SWEETSOIL')->value('logo');
        if ($logo) DB::table('companies')->where('id', $company->id)->update(['logo' => $logo]);

        return $company->refresh();
    }

    private function masters(): void
    {
        $this->line('Masters: channels, parties, items, units, BOMs ...');
        $c = $this->context->companyId;
        foreach (['inventory.batch_expiry', 'sales.wholesale', 'manufacturing.bom', 'manufacturing.production'] as $capability) {
            app(CapabilityService::class)->enable($capability, [], $this->context, self::ACTOR);
        }
        foreach (['KG' => 'Kilogram', 'LTR' => 'Litre'] as $code => $name) {
            DB::table('units')->updateOrInsert(['company_id' => $c, 'unit_code' => $code],
                ['unit_name' => $name, 'operator' => '*', 'operation_value' => 1, 'is_active' => true, 'updated_at' => now()]);
        }
        $unit = DB::table('units')->where('company_id', $c)->pluck('id', 'unit_code');
        $tax = DB::table('tax_categories')->where('company_id', $c)->pluck('id', 'name');
        $groups = [];
        foreach (['Wholesale / Distributor', 'Organic Store', 'Direct Customer', 'Corporate Gifting'] as $name) {
            DB::table('customer_groups')->updateOrInsert(['company_id' => $c, 'name' => $name], ['percentage' => 0, 'is_active' => true, 'updated_at' => now()]);
            $groups[$name] = DB::table('customer_groups')->where('company_id', $c)->where('name', $name)->value('id');
        }
        $category = [];
        foreach (['Bulk product (from manufacturer)', 'Packing material', 'Sweet Soil finished goods'] as $name) {
            DB::table('categories')->updateOrInsert(['company_id' => $c, 'name' => $name], ['is_active' => true, 'updated_at' => now()]);
            $category[$name] = DB::table('categories')->where('company_id', $c)->where('name', $name)->value('id');
        }

        $suppliers = [
            'manufacturer' => ['Kongu Palm Products', 'R. Senthil Kumar', 'AAGFK3345K', 'SF 112, Palm Jaggery Cluster, Perundurai Road', 'Erode', '638052', 30],
            'packing' => ['Sri Murugan Flexi Packs', 'M. Prakash', 'AAPFS9902L', '18, SIDCO Industrial Estate, Avinashi Road', 'Tiruppur', '641603', 15],
            'labels' => ['Sivakasi Label Printers', 'K. Ganesan', 'BQZPS1124H', '7, Offset Printers Street', 'Sivakasi', '626123', 15],
        ];
        foreach ($suppliers as $key => [$name, $contact, $pan, $address, $city, $pin, $days]) {
            $this->ids['supplier'][$key] = $this->party(Supplier::class, $name, ['contact_person' => $contact, 'vat_number' => self::gstin('33', $pan),
                'address' => $address, 'city' => $city, 'state' => 'Tamil Nadu', 'postal_code' => $pin, 'country' => 'India', 'credit_days' => $days,
                'phone_number' => '98'.substr(crc32($name), 0, 8)]);
        }
        $customers = [
            'wholesale' => ['Kongu Natural Foods Distributors', 'Wholesale / Distributor', '33', 'AAHFK2231M', '45, Raja Street, Town Hall', 'Coimbatore', 'Tamil Nadu', '641001', 30],
            'organic' => ['Thulasi Organic Store', 'Organic Store', '33', 'ABCPT5567R', '12, 2nd Avenue, Anna Nagar', 'Chennai', 'Tamil Nadu', '600040', 15],
            'direct' => ['Priya Raman (website order)', 'Direct Customer', '33', null, '4, Lake View Road, West Mambalam', 'Chennai', 'Tamil Nadu', '600033', 0],
            'gifting' => ['Celebr8 Corporate Gifting Pvt Ltd', 'Corporate Gifting', '29', 'AAJCC7781E', '3rd Floor, 80 Feet Road, Koramangala', 'Bengaluru', 'Karnataka', '560034', 21],
        ];
        foreach ($customers as $key => [$name, $group, $state, $pan, $address, $city, $stateName, $pin, $days]) {
            $this->ids['customer'][$key] = $this->party(Customer::class, $name, ['customer_group_id' => $groups[$group],
                'tax_no' => $pan ? self::gstin($state, $pan) : null, 'address' => $address, 'city' => $city, 'state' => $stateName,
                'postal_code' => $pin, 'country' => 'India', 'credit_days' => $days, 'phone_number' => '94'.substr(crc32($name), 0, 8)]);
        }

        $bulk = $category['Bulk product (from manufacturer)']; $pack = $category['Packing material']; $fg = $category['Sweet Soil finished goods'];
        $items = [
            // code => [name, category, unit, HSN, GST, cost, price, batch-tracked]
            'BULK-PS' => ['Palm Sugar - bulk', $bulk, 'KG', '17029010', 'GST 5%', 300, 0, false],
            'BULK-RNM' => ['Rose Neera Mix - bulk', $bulk, 'KG', '21069059', 'GST 5%', 600, 0, false],
            'BULK-PSY' => ['Palm Syrup - bulk', $bulk, 'LTR', '17029090', 'GST 5%', 250, 0, false],
            'PM-POUCH-100' => ['Kraft zip pouch 100g', $pack, 'PCS', '39232990', 'GST 18%', 3.5, 0, false],
            'PM-POUCH-150' => ['Kraft zip pouch 150g', $pack, 'PCS', '39232990', 'GST 18%', 4.2, 0, false],
            'PM-BOTTLE-135' => ['Glass bottle 135ml with cap', $pack, 'PCS', '70109000', 'GST 18%', 14, 0, false],
            'PM-LABEL' => ['Sweet Soil label (FSSAI, batch, MRP)', $pack, 'PCS', '48211020', 'GST 18%', 1.2, 0, false],
            'PM-GIFTBOX' => ['Festive gift box', $pack, 'PCS', '48191010', 'GST 18%', 45, 0, false],
            'PS-100' => ['Palm Sugar 100g', $fg, 'PCS', '17029010', 'GST 5%', 0, 80, true],
            'RNM-150' => ['Rose Neera Mix 150g', $fg, 'PCS', '21069059', 'GST 5%', 0, 239, true],
            'RNM-100' => ['Rose Neera Mix 100g', $fg, 'PCS', '21069059', 'GST 5%', 0, 150, true],
            'PSY-135' => ['Palm Syrup 135ml', $fg, 'PCS', '17029090', 'GST 5%', 0, 99, true],
            'PC-8' => ['Palm Chocolate 8g', $fg, 'PCS', '18069010', 'GST 5%', 9, 20, false],
            'HAMPER-FEST' => ['Sweet Soil Festive Hamper', $fg, 'PCS', '21069099', 'GST 5%', 0, 699, true],
        ];
        foreach ($items as $code => [$name, $cat, $uom, $hsn, $gst, $cost, $price, $batch]) {
            $product = Product::where('company_id', $c)->where('code', $code)->first() ?? new Product;
            $product->forceFill(['company_id' => $c, 'code' => $code, 'name' => $name, 'type' => 'standard', 'barcode_symbology' => 'C128',
                'category_id' => $cat, 'unit_id' => $unit[$uom], 'purchase_unit_id' => $unit[$uom], 'sale_unit_id' => $unit[$uom],
                'cost' => $product->exists ? $product->cost : $cost, 'price' => $price, 'qty' => $product->qty ?? 0, 'tax_method' => 1,
                'hsn_code' => $hsn, 'tax_category_id' => $tax[$gst], 'is_batch' => $batch, 'is_imei' => false, 'is_active' => true,
                'neg_stock_allowed' => false, 'allow_negative_stock' => false])->save();
            $this->ids['product'][$code] = $product->id;
        }
        // Distributors buy Palm Sugar 100g by the case of 50 packs.
        ProductUomConversion::updateOrCreate(['company_id' => $c, 'product_id' => $this->id('PS-100'), 'from_uom_id' => $unit['CASE']],
            ['to_uom_id' => $unit['PCS'], 'factor' => 50, 'rounding_scale' => 4]);
        $this->ids['unit'] = $unit->all();

        // Packing recipes: bulk kg/litre + pouch/bottle + label = one branded pack. Scrap % covers spillage at the filling table.
        $boms = [
            'PS-100' => [10, [['BULK-PS', 1, 'KG', 1], ['PM-POUCH-100', 10, 'PCS', 0], ['PM-LABEL', 10, 'PCS', 0]]],
            'RNM-150' => [20, [['BULK-RNM', 3, 'KG', 1], ['PM-POUCH-150', 20, 'PCS', 0], ['PM-LABEL', 20, 'PCS', 0]]],
            'RNM-100' => [10, [['BULK-RNM', 1, 'KG', 1], ['PM-POUCH-100', 10, 'PCS', 0], ['PM-LABEL', 10, 'PCS', 0]]],
            'PSY-135' => [100, [['BULK-PSY', 13.5, 'LTR', 1], ['PM-BOTTLE-135', 100, 'PCS', 0], ['PM-LABEL', 100, 'PCS', 0]]],
            'HAMPER-FEST' => [1, [['PS-100', 1, 'PCS', 0], ['RNM-150', 1, 'PCS', 0], ['PSY-135', 1, 'PCS', 0], ['PC-8', 5, 'PCS', 0], ['PM-GIFTBOX', 1, 'PCS', 0]]],
        ];
        foreach ($boms as $output => [$qty, $lines]) {
            $this->ids['bom'][$output] = app(BomService::class)->create(['product_id' => $this->id($output), 'code' => 'SS-'.$output,
                'effective_from' => '2026-10-01', 'output_qty' => $qty, 'output_uom_id' => $unit['PCS'], 'is_default' => true,
                'lines' => array_map(fn ($l) => ['component_product_id' => $this->id($l[0]), 'qty' => $l[1], 'uom_id' => $unit[$l[2]], 'scrap_percent' => $l[3]], $lines)],
                'ssdemo:bom:'.$output, $this->context, self::ACTOR)->id;
        }
        $scheme = DB::table('sales_quantity_schemes')->where('company_id', $c)->where('product_id', $this->id('PS-100'))->value('id');
        $this->ids['scheme'] = $scheme ?: app(FmcgInventoryService::class)->configureScheme(['product_id' => $this->id('PS-100'),
            'name' => 'Organic store launch 12+1', 'buy_qty' => 12, 'free_qty' => 1, 'valid_from' => '2026-10-01', 'valid_to' => '2026-12-31'], $this->context, self::ACTOR);
    }

    /** The promoters' opening capital in the bank, so the month's payments do not overdraw it. */
    private function capital(): void
    {
        $key = 'ssdemo:opening-capital';
        if (DB::table('journal_entries')->where('company_id', $this->context->companyId)->where('idempotency_key', $key)->exists()) return;
        $account = fn (string $code) => (int) DB::table('chart_of_accounts')->where('company_id', $this->context->companyId)->where('code', $code)->value('id');
        app(\App\Services\Accounting\AccountingPostingService::class)->postJournalEntry(['entry_date' => '2026-10-01', 'idempotency_key' => $key,
            'reference_type' => 'voucher', 'reference_no' => 'CAPITAL-01', 'description' => 'Capital introduced by promoters', 'created_by' => self::ACTOR],
            [['chart_of_account_id' => $account('1020'), 'debit' => '200000.0000'], ['chart_of_account_id' => $account('3010'), 'credit' => '200000.0000']], $this->context);
    }

    private function purchases(): void
    {
        $this->line('Purchases: bulk product, packing material, labels ...');
        $bills = [
            'manufacturer' => ['2026-10-05', 'KPP/26-27/0418', [['BULK-PS', 100, 300], ['BULK-RNM', 60, 600], ['BULK-PSY', 30, 250], ['PC-8', 2000, 9]]],
            'packing' => ['2026-10-05', 'SMF/1187', [['PM-POUCH-100', 1200, 3.5], ['PM-POUCH-150', 400, 4.2], ['PM-BOTTLE-135', 250, 14], ['PM-GIFTBOX', 100, 45]]],
            'labels' => ['2026-10-06', 'SLP-2291', [['PM-LABEL', 1600, 1.2]]],
        ];
        foreach ($bills as $supplier => [$date, $invoice, $lines]) {
            $this->ids['purchase'][$supplier] = app(PurchaseApplicationService::class)->create(new PurchaseCommand([
                'supplier_id' => $this->ids['supplier'][$supplier], 'warehouse_id' => $this->warehouse(), 'business_date' => $date, 'status' => 1,
                'supplier_invoice_no' => $invoice, 'note' => 'Supplier invoice '.$invoice,
                'items' => array_map(fn ($l) => ['product_id' => $this->id($l[0]), 'qty' => $l[1], 'net_unit_cost' => $l[2]], $lines),
            ], 'ssdemo:purchase:'.$supplier, self::ACTOR, $this->context))->id;
        }
    }

    private function packing(): void
    {
        $this->line('Packing runs: label + pack under the Sweet Soil brand ...');
        // [output, packs, packing labour per pack, batch, mfg, expiry, MRP]
        $runs = [
            ['PS-100', 900, 0.80, 'SS-PS-2610A', '2026-10-07', '2027-10-06', 80],
            ['RNM-150', 300, 1.00, 'SS-RNM150-2610A', '2026-10-07', '2027-07-06', 239],
            ['RNM-100', 100, 0.80, 'SS-RNM100-2610A', '2026-10-07', '2027-07-06', 150],
            ['PSY-135', 200, 1.50, 'SS-PSY-2610A', '2026-10-08', '2027-04-07', 99],
            // Hampers carry the earliest component expiry (the syrup).
            ['HAMPER-FEST', 60, 15.00, 'SS-HMP-2610A', '2026-10-09', '2027-04-07', 699],
        ];
        foreach ($runs as [$output, $qty, $labour, $batch, $mfg, $expiry, $mrp]) {
            $order = app(ProductionService::class)->plan(['bom_id' => $this->ids['bom'][$output], 'warehouse_id' => $this->warehouse(),
                'planned_qty' => $qty, 'business_date' => $mfg], 'ssdemo:plan:'.$output, $this->context, self::ACTOR);
            if ($order->status === 'completed') continue;
            app(ProductionService::class)->complete($order->id, ['completed_qty' => $qty, 'business_date' => $mfg, 'direct_cost' => round($qty * $labour, 2),
                'outputs' => [['product_id' => $this->id($output), 'uom_id' => $this->ids['unit']['PCS'], 'qty' => $qty, 'cost_weight' => 1,
                    'batch' => ['batch_no' => $batch, 'mfg_date' => $mfg, 'expired_date' => $expiry, 'mrp' => $mrp]]]]
                    + $this->components($this->ids['bom'][$output], $qty, $mfg),
                'ssdemo:complete:'.$output, $this->context, self::ACTOR);
        }
    }

    /** Batch-tracked packs (hamper contents) are consumed first-expiry-first-out; plain BOMs need no component override. */
    private function components(int $bomId, float $qty, string $date): array
    {
        $bom = DB::table('boms')->find($bomId);
        if (!Product::whereIn('id', DB::table('bom_lines')->where('bom_id', $bomId)->pluck('component_product_id'))->where('is_batch', true)->exists()) return [];

        return ['components' => DB::table('bom_lines')->where('bom_id', $bomId)->orderBy('line_no')->get()->map(function ($line) use ($bom, $qty, $date) {
            $need = round($line->qty * $qty / $bom->output_qty * (1 + $line->scrap_percent / 100), 4);
            $component = ['bom_line_id' => $line->id, 'qty' => $need];
            if (Product::find($line->component_product_id)->is_batch) {
                $picks = app(FmcgInventoryService::class)->suggest($line->component_product_id, $this->warehouse(), $need, $date, $this->context, self::ACTOR);
                $component['product_batch_id'] = $picks[0]['product_batch_id'];
            }

            return $component;
        })->all()];
    }

    private function sales(): void
    {
        $this->line('Sales: wholesale, organic store, direct customer, corporate gifting ...');
        $case = $this->ids['unit']['CASE'];
        $invoices = [
            // Distributor: ~37% off MRP, Palm Sugar by the case of 50.
            'wholesale' => ['2026-10-10', [['PS-100', 10, 2500, $case], ['RNM-150', 100, 150], ['PSY-135', 60, 62]]],
            // Organic store: ~25% off MRP plus the 12+1 launch scheme on Palm Sugar.
            'organic' => ['2026-10-12', [['PS-100', 120, 60, null, true], ['RNM-150', 60, 175], ['RNM-100', 50, 110], ['PSY-135', 40, 72], ['PC-8', 200, 14]]],
            // Website order at MRP; MRP includes 5% GST, so the taxable rate is MRP / 1.05.
            'direct' => ['2026-10-14', [['PS-100', 5, 76.19], ['RNM-150', 2, 227.62], ['PSY-135', 2, 94.29], ['PC-8', 10, 19.05]]],
            // Bengaluru gifting company: inter-state, so IGST.
            'gifting' => ['2026-10-16', [['HAMPER-FEST', 50, 520]]],
        ];
        foreach ($invoices as $customer => [$date, $lines]) {
            $this->ids['sale'][$customer] = app(SaleApplicationService::class)->create(new SaleCommand([
                'customer_id' => $this->ids['customer'][$customer], 'warehouse_id' => $this->warehouse(), 'business_date' => $date,
                'items' => array_map(fn ($l) => array_filter(['product_id' => $this->id($l[0]), 'qty' => $l[1], 'net_unit_price' => $l[2],
                    'sale_unit_id' => $l[3] ?? null, 'quantity_scheme_id' => !empty($l[4]) ? $this->ids['scheme'] : null], fn ($v) => $v !== null), $lines),
            ], 'ssdemo:sale:'.$customer, self::ACTOR, $this->context))->id;
        }
    }

    private function settlements(): void
    {
        $this->line('Receipts and supplier payments ...');
        $bank = (int) DB::table('accounts')->where('company_id', $this->context->companyId)->where('name', 'Bank')->value('id');
        $cash = (int) DB::table('accounts')->where('company_id', $this->context->companyId)->where('name', 'Cash')->value('id');
        $sale = fn ($k) => Sale::findOrFail($this->ids['sale'][$k]);
        $purchase = fn ($k) => Purchase::findOrFail($this->ids['purchase'][$k]);
        // [document, share of total, date, account, method, note]; UPI and NEFT post through the Bank method.
        $settle = [
            ['direct', $sale('direct'), 1, '2026-10-14', $bank, 'Bank', 'UPI ref 4417'],
            ['organic', $sale('organic'), 1, '2026-10-22', $bank, 'Cheque', 'Cheque 000231'],
            ['wholesale', $sale('wholesale'), 0.5, '2026-10-20', $bank, 'Bank', 'NEFT'],
            ['gifting', $sale('gifting'), 0.5, '2026-10-16', $bank, 'Bank', 'NEFT'],
            ['manufacturer', $purchase('manufacturer'), 1, '2026-10-18', $bank, 'Bank', 'NEFT'],
            ['packing', $purchase('packing'), 0.6, '2026-10-18', $bank, 'Bank', 'NEFT'],
            ['labels', $purchase('labels'), 1, '2026-10-08', $cash, 'Cash', 'Cash'],
        ];
        foreach ($settle as [$key, $document, $share, $date, $account, $method, $note]) {
            app(PaymentService::class)->addPayment($document, ['amount' => round((float) $document->grand_total * $share, 2), 'account_id' => $account,
                'paying_method' => $method, 'payment_note' => $note, 'business_date' => $date, 'idempotency_key' => 'ssdemo:pay:'.$key], self::ACTOR, $this->context);
        }
    }

    private function party(string $model, string $name, array $values): int
    {
        $party = $model::where('company_id', $this->context->companyId)->where('name', $name)->first() ?? new $model;
        $party->forceFill(['company_id' => $this->context->companyId, 'name' => $name, 'company_name' => $name, 'is_active' => true] + $values)->save();
        app(\App\Services\Tax\GstSetupService::class)->syncParty($model === Customer::class ? 'customer' : 'supplier', $party->fresh());

        return $party->id;
    }

    private function id(string $code): int
    {
        return $this->ids['product'][$code];
    }

    private function warehouse(): int
    {
        return $this->ids['warehouse'] ??= (int) DB::table('warehouses')->where('company_id', $this->context->companyId)
            ->whereNull('external_job_order_id')->orderBy('id')->value('id');
    }

    /** A checksum-valid GSTIN for the demo parties. */
    public static function gstin(string $state, string $pan): string
    {
        $prefix = $state.$pan.'1Z'; $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $factor = 2; $sum = 0;
        for ($i = 13; $i >= 0; $i--) { $n = strpos($alphabet, $prefix[$i]) * $factor; $sum += intdiv($n, 36) + $n % 36; $factor = $factor === 2 ? 1 : 2; }

        return $prefix.$alphabet[(36 - $sum % 36) % 36];
    }
}

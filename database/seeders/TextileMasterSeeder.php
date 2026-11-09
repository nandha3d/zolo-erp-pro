<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TextileMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->seedCompany(null);
            return;
        }

        foreach ($companies as $company) {
            $this->seedCompany($company);
        }
    }

    /**
     * Seed textile suppliers, categories, units, brands, and products for a given company.
     */
    public function seedCompany(?Company $company, bool $catalogOnly = false): void
    {
        $companyId = $company ? $company->id : 1;

        // 1. Ensure Textile Units of Measure (MTR, KGS, DOZ, PCS)
        $units = $this->ensureUnits($companyId);

        // 2. Ensure Textile Brands
        $brands = $this->ensureBrands($companyId);

        // 3. Ensure Textile Categories Hierarchy
        $categories = $this->ensureCategories($companyId);

        // 4. Seed Textile Suppliers
        $suppliers = $this->seedSuppliers($companyId, $catalogOnly);

        // 5. Seed Textile Products & Inventory Stock
        $products = $this->seedProducts($companyId, $units, $brands, $categories, $catalogOnly);
    }

    /**
     * Ensure textile UOMs exist for the company.
     */
    private function ensureUnits(int $companyId): array
    {
        $unitMap = [];

        // Base Pieces
        $pcs = Unit::where('unit_code', 'PCS')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'Pc')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'PCS')->first()
            ?? Unit::where('unit_code', 'Pc')->first();

        if (!$pcs) {
            $pcs = Unit::create([
                'company_id' => $companyId,
                'unit_code' => 'PCS',
                'unit_name' => 'Pieces',
                'base_unit' => null,
                'operator' => '*',
                'operation_value' => 1,
                'is_active' => true,
            ]);
        }
        $unitMap['PCS'] = $pcs->id;

        // Meter (MTR)
        $mtr = Unit::where('unit_code', 'MTR')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'MTR')->first();

        if (!$mtr) {
            $mtr = Unit::create([
                'company_id' => $companyId,
                'unit_code' => 'MTR',
                'unit_name' => 'Meter',
                'base_unit' => null,
                'operator' => '*',
                'operation_value' => 1,
                'is_active' => true,
            ]);
        }
        $unitMap['MTR'] = $mtr->id;

        // Kilogram (KGS)
        $kgs = Unit::where('unit_code', 'KGS')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'Kg')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'KGS')->first()
            ?? Unit::where('unit_code', 'Kg')->first();

        if (!$kgs) {
            $kgs = Unit::create([
                'company_id' => $companyId,
                'unit_code' => 'KGS',
                'unit_name' => 'Kilogram',
                'base_unit' => null,
                'operator' => '*',
                'operation_value' => 1,
                'is_active' => true,
            ]);
        }
        $unitMap['KGS'] = $kgs->id;

        // Dozen (DOZ)
        $doz = Unit::where('unit_code', 'DOZ')->where('company_id', $companyId)->first()
            ?? Unit::where('unit_code', 'DOZ')->first();

        if (!$doz) {
            $doz = Unit::create([
                'company_id' => $companyId,
                'unit_code' => 'DOZ',
                'unit_name' => 'Dozen',
                'base_unit' => $pcs->id,
                'operator' => '*',
                'operation_value' => 12,
                'is_active' => true,
            ]);
        }
        $unitMap['DOZ'] = $doz->id;

        return $unitMap;
    }

    /**
     * Ensure standard textile brands exist.
     */
    private function ensureBrands(int $companyId): array
    {
        $brands = [
            'Ramraj Cotton',
            'Liva (Aditya Birla)',
            'Coats India',
            'Vardhman Textiles',
            'Arvind Mills',
        ];

        $brandMap = [];
        foreach ($brands as $title) {
            $brand = Brand::firstOrCreate(
                ['company_id' => $companyId, 'title' => $title],
                ['is_active' => true]
            );
            $brandMap[$title] = $brand->id;
        }

        return $brandMap;
    }

    /**
     * Ensure textile categories hierarchy.
     */
    private function ensureCategories(int $companyId): array
    {
        // Main Root Category
        $root = Category::firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Textiles & Handlooms'],
            ['is_active' => true, 'default_hsn_code' => '5208', 'parent_id' => null]
        );

        $catMap = ['Textiles & Handlooms' => $root->id];

        $subcategories = [
            ['name' => 'Yarn & Spinning', 'hsn' => '5205'],
            ['name' => 'Grey & Woven Fabrics', 'hsn' => '5208'],
            ['name' => 'Dyed & Finished Fabrics', 'hsn' => '5208'],
            ['name' => 'Apparel & Made-ups', 'hsn' => '6109'],
            ['name' => 'Textile Trims & Accessories', 'hsn' => '5401'],
        ];

        foreach ($subcategories as $sub) {
            $cat = Category::firstOrCreate(
                ['company_id' => $companyId, 'name' => $sub['name']],
                ['is_active' => true, 'default_hsn_code' => $sub['hsn'], 'parent_id' => $root->id]
            );
            $catMap[$sub['name']] = $cat->id;
        }

        return $catMap;
    }

    /**
     * Seed realistic textile suppliers across spinning, weaving, processing and trims.
     */
    private function seedSuppliers(int $companyId, bool $catalogOnly): array
    {
        $supplierData = [
            [
                'name' => 'R. Venkatachalam',
                'company_name' => 'Lakshmi Spinning Mills Ltd',
                'vat_number' => '33AAACL2890A1ZB',
                'tax_no' => '33AAACL2890A1ZB',
                'phone_number' => '9842211330',
                'wa_number' => '9842211330',
                'email' => 'yarnsales@lakshmispinning.com',
                'address' => '142, Cotton Mill Road, Peelamedu',
                'city' => 'Coimbatore',
                'state' => 'Tamil Nadu',
                'postal_code' => '641004',
                'country' => 'India',
                'credit_days' => 30,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Credit',
                    'nature' => 'product',
                    'place_of_supply' => '33',
                    'note' => 'Spun cotton yarn procurement in bags/kg',
                ],
            ],
            [
                'name' => 'S. Murugesan',
                'company_name' => 'Sri Balaji Weaving & Powerloom Works',
                'vat_number' => '33AAHFS4455K1Z4',
                'tax_no' => '33AAHFS4455K1Z4',
                'phone_number' => '9443355771',
                'wa_number' => '9443355771',
                'email' => 'orders@sribalajiweaves.in',
                'address' => '78, Weavers Colony, Sathy Road',
                'city' => 'Erode',
                'state' => 'Tamil Nadu',
                'postal_code' => '638003',
                'country' => 'India',
                'credit_days' => 21,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Credit',
                    'nature' => 'product',
                    'place_of_supply' => '33',
                    'note' => 'Grey woven cloth procurement in bales/meters',
                ],
            ],
            [
                'name' => 'Rajeshbhai Patel',
                'company_name' => 'Surat Synthetic & Rayon Weaves Pvt Ltd',
                'vat_number' => '24AAACS9988P1Z6',
                'tax_no' => '24AAACS9988P1Z6',
                'phone_number' => '9825123456',
                'wa_number' => '9825123456',
                'email' => 'sales@suratsyntheticweaves.com',
                'address' => '415, Millennium Textile Market, Ring Road',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'postal_code' => '395002',
                'country' => 'India',
                'credit_days' => 45,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Credit',
                    'nature' => 'product',
                    'place_of_supply' => '24',
                    'note' => 'Interstate Rayon & synthetic fabrics (IGST)',
                ],
            ],
            [
                'name' => 'P. Dharmaraj',
                'company_name' => 'Rainbow Textile Processors & Dyers',
                'vat_number' => '33AAACR5566M1Z2',
                'tax_no' => '33AAACR5566M1Z2',
                'phone_number' => '9843098765',
                'wa_number' => '9843098765',
                'email' => 'contact@rainbowtextileproc.com',
                'address' => '12, SIPCOT Industrial Growth Estate, Processing Zone',
                'city' => 'Perundurai',
                'state' => 'Tamil Nadu',
                'postal_code' => '638052',
                'country' => 'India',
                'credit_days' => 15,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Credit',
                    'nature' => 'service',
                    'place_of_supply' => '33',
                    'note' => 'Job work bleaching, dyeing, mercerizing and printing charges',
                ],
            ],
            [
                'name' => 'N. Jayakumar',
                'company_name' => 'Coats India Threads & Accessories Ltd',
                'vat_number' => '33AAACC0123L1Z9',
                'tax_no' => '33AAACC0123L1Z9',
                'phone_number' => '9894411223',
                'wa_number' => '9894411223',
                'email' => 'corporate@coatsthreads.in',
                'address' => '56, Garment Trims Hub, Kumaran Road',
                'city' => 'Tiruppur',
                'state' => 'Tamil Nadu',
                'postal_code' => '641601',
                'country' => 'India',
                'credit_days' => 30,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Credit',
                    'nature' => 'product',
                    'place_of_supply' => '33',
                    'note' => 'High-tenacity sewing threads and trims',
                ],
            ],
            [
                'name' => 'V. Soundararajan',
                'company_name' => 'Tirupur Knit Knots Garment Trims',
                'vat_number' => '33AABCT7788Q1Z1',
                'tax_no' => '33AABCT7788Q1Z1',
                'phone_number' => '9842765432',
                'wa_number' => '9842765432',
                'email' => 'support@knitknotstrims.com',
                'address' => '23, Rayapuram Main Road',
                'city' => 'Tiruppur',
                'state' => 'Tamil Nadu',
                'postal_code' => '641601',
                'country' => 'India',
                'credit_days' => 15,
                'opening_balance' => 0,
                'bill_by_bill' => 1,
                'purchase_defaults' => [
                    'payment_mode' => 'Cash',
                    'nature' => 'product',
                    'place_of_supply' => '33',
                    'note' => 'Elastic tapes, buttons, zip fasteners and hang tags',
                ],
            ],
        ];

        $createdSuppliers = [];
        foreach ($supplierData as $data) {
            $supplier = Supplier::withoutGlobalScope('request_company')
                ->where('company_id', $companyId)
                ->where(function ($q) use ($data) {
                    $q->where('company_name', $data['company_name'])
                        ->orWhere('vat_number', $data['vat_number']);
                })->first();

            if (!$supplier) {
                $supplier = Supplier::create(array_merge($data, [
                    'company_id' => $companyId,
                    'is_active' => true,
                ]));
            } elseif (!$catalogOnly) {
                $supplier->forceFill(array_merge($data, [
                    'company_id' => $companyId,
                    'is_active' => true,
                ]))->save();
            }

            $createdSuppliers[] = $supplier;
        }

        return $createdSuppliers;
    }

    /**
     * Seed comprehensive textile products across raw yarn, grey fabrics, dyed fabrics, garments, and trims.
     */
    private function seedProducts(int $companyId, array $units, array $brands, array $categories, bool $catalogOnly): array
    {
        // Taxes
        $gst5 = Tax::where('rate', 5)->first();
        $gst12 = Tax::where('rate', 12)->first();
        $tax5Id = $gst5 ? $gst5->id : 2;
        $tax12Id = $gst12 ? $gst12->id : 4;

        // Warehouse for initial stock
        $warehouse = Warehouse::where('company_id', $companyId)->where('is_active', true)->first()
            ?? Warehouse::where('is_active', true)->first()
            ?? Warehouse::first();
        $warehouseId = $warehouse ? $warehouse->id : 1;

        $items = [
            // 1. Yarn Raw Material
            [
                'name' => 'Cotton Combed Yarn 40s (Auto-Coner)',
                'code' => 'TEX-YRN-40C',
                'category' => 'Yarn & Spinning',
                'brand' => 'Vardhman Textiles',
                'unit' => 'KGS',
                'hsn' => '5205',
                'tax_id' => $tax5Id,
                'cost' => 280.00,
                'price' => 325.00,
                'stock' => 500,
                'alert_qty' => 50,
            ],
            // 2. Carded Hosiery Yarn
            [
                'name' => 'Cotton Carded Hosiery Yarn 30s',
                'code' => 'TEX-YRN-30H',
                'category' => 'Yarn & Spinning',
                'brand' => 'Vardhman Textiles',
                'unit' => 'KGS',
                'hsn' => '5205',
                'tax_id' => $tax5Id,
                'cost' => 250.00,
                'price' => 295.00,
                'stock' => 400,
                'alert_qty' => 40,
            ],
            // 3. Raw Grey Knitted Fabric
            [
                'name' => 'Single Jersey Cotton Knit Fabric 180 GSM (Raw Grey)',
                'code' => 'TEX-KNT-180G',
                'category' => 'Grey & Woven Fabrics',
                'brand' => null,
                'unit' => 'KGS',
                'hsn' => '6006',
                'tax_id' => $tax5Id,
                'cost' => 260.00,
                'price' => 310.00,
                'stock' => 350,
                'alert_qty' => 50,
            ],
            // 4. Grey Woven Fabric (Meters)
            [
                'name' => '100% Cotton Grey Fabric 40x40 / 92x88 (63 inch Width)',
                'code' => 'TEX-GRY-63W',
                'category' => 'Grey & Woven Fabrics',
                'brand' => null,
                'unit' => 'MTR',
                'hsn' => '5208',
                'tax_id' => $tax5Id,
                'cost' => 48.00,
                'price' => 62.00,
                'stock' => 1200,
                'alert_qty' => 200,
            ],
            // 5. Poly-Cotton Grey Sheeting
            [
                'name' => 'Poly-Cotton Grey Sheeting Fabric (58 inch)',
                'code' => 'TEX-PC-SHT58',
                'category' => 'Grey & Woven Fabrics',
                'brand' => null,
                'unit' => 'MTR',
                'hsn' => '5513',
                'tax_id' => $tax5Id,
                'cost' => 38.00,
                'price' => 50.00,
                'stock' => 800,
                'alert_qty' => 150,
            ],
            // 6. Bleached / Finished Poplin
            [
                'name' => 'Pure Cotton Poplin Dyed Fabric - Super White (58 inch)',
                'code' => 'TEX-POP-WHT',
                'category' => 'Dyed & Finished Fabrics',
                'brand' => 'Arvind Mills',
                'unit' => 'MTR',
                'hsn' => '5208',
                'tax_id' => $tax5Id,
                'cost' => 72.00,
                'price' => 95.00,
                'stock' => 800,
                'alert_qty' => 150,
            ],
            // 7. Dyed Knit Fabric
            [
                'name' => 'Bio-Washed Combed Cotton Single Jersey (Navy Blue)',
                'code' => 'TEX-KNT-NVY',
                'category' => 'Dyed & Finished Fabrics',
                'brand' => null,
                'unit' => 'KGS',
                'hsn' => '6006',
                'tax_id' => $tax5Id,
                'cost' => 340.00,
                'price' => 420.00,
                'stock' => 250,
                'alert_qty' => 30,
            ],
            // 8. Rayon / Viscose Dyed Fabric
            [
                'name' => 'Rayon / Viscose Liva Fabric 30s Dyed (Jet Black)',
                'code' => 'TEX-RAY-BLK',
                'category' => 'Dyed & Finished Fabrics',
                'brand' => 'Liva (Aditya Birla)',
                'unit' => 'MTR',
                'hsn' => '5408',
                'tax_id' => $tax5Id,
                'cost' => 65.00,
                'price' => 88.00,
                'stock' => 600,
                'alert_qty' => 100,
            ],
            // 9. Finished Traditional Dhoti
            [
                'name' => 'Classic Gold Jari Border Cotton Dhoti (Double / 8 Muzham)',
                'code' => 'TEX-DHT-GOLD8',
                'category' => 'Apparel & Made-ups',
                'brand' => 'Ramraj Cotton',
                'unit' => 'PCS',
                'hsn' => '5208',
                'tax_id' => $tax5Id,
                'cost' => 320.00,
                'price' => 550.00,
                'stock' => 150,
                'alert_qty' => 20,
            ],
            // 10. Round Neck T-Shirt Pack
            [
                'name' => "Men's Combed Cotton Round Neck T-Shirt (Pack of 3)",
                'code' => 'TEX-TSH-PK3',
                'category' => 'Apparel & Made-ups',
                'brand' => null,
                'unit' => 'PCS',
                'hsn' => '6109',
                'tax_id' => $tax5Id,
                'cost' => 290.00,
                'price' => 499.00,
                'stock' => 200,
                'alert_qty' => 25,
            ],
            // 11. Terry Bath Towel
            [
                'name' => 'Premium Cotton Terry Bath Towel 500 GSM (75x150 cm)',
                'code' => 'TEX-TWL-500G',
                'category' => 'Apparel & Made-ups',
                'brand' => null,
                'unit' => 'PCS',
                'hsn' => '6302',
                'tax_id' => $tax12Id,
                'cost' => 180.00,
                'price' => 320.00,
                'stock' => 180,
                'alert_qty' => 30,
            ],
            // 12. Sewing Thread
            [
                'name' => 'High Tenacity Spun Polyester Sewing Thread 40/2 (Cone 1000m)',
                'code' => 'TEX-THD-40C',
                'category' => 'Textile Trims & Accessories',
                'brand' => 'Coats India',
                'unit' => 'PCS',
                'hsn' => '5401',
                'tax_id' => $tax12Id,
                'cost' => 35.00,
                'price' => 55.00,
                'stock' => 300,
                'alert_qty' => 50,
            ],
        ];

        $createdProducts = [];
        foreach ($items as $item) {
            $unitId = $units[$item['unit']] ?? $units['PCS'];
            $brandId = !empty($item['brand']) && isset($brands[$item['brand']]) ? $brands[$item['brand']] : null;
            $categoryId = $categories[$item['category']] ?? $categories['Textiles & Handlooms'];

            $cost = (float) $item['cost'];
            $price = (float) $item['price'];
            $margin = $cost > 0 ? round(($price - $cost) / $cost * 100, 2) : 0;

            $product = Product::withoutGlobalScope('request_company')
                ->where('company_id', $companyId)
                ->where('code', $item['code'])
                ->first();
            if ($catalogOnly && $product) {
                continue;
            }

            // Opening quantities must enter production through the stock and accounting ledgers.
            $quantity = $catalogOnly ? 0 : $item['stock'];

            $data = [
                'name' => $item['name'],
                'code' => $item['code'],
                'type' => 'standard',
                'barcode_symbology' => 'C128',
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'purchase_unit_id' => $unitId,
                'sale_unit_id' => $unitId,
                'cost' => $cost,
                'price' => $price,
                'profit_margin_type' => 'percentage',
                'profit_margin' => $margin,
                'qty' => $quantity,
                'alert_quantity' => $item['alert_qty'],
                'tax_id' => $item['tax_id'],
                'tax_method' => 1,
                'hsn_code' => $item['hsn'],
                'is_active' => 1,
                'neg_stock_allowed' => 1,
                'allow_negative_stock' => 1,
                'company_id' => $companyId,
            ];

            if (!$product) {
                $product = Product::forceCreate($data);
            } else {
                $product->forceFill($data)->save();
            }

            // Sync with warehouse stock
            Product_Warehouse::updateOrCreate([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
            ], [
                'qty' => $quantity,
                'price' => $price,
                'company_id' => $companyId,
            ]);

            $createdProducts[] = $product;
        }

        return $createdProducts;
    }
}

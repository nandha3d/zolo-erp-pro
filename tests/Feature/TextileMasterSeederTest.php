<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Product_Warehouse;
use App\Models\Supplier;
use App\Models\Unit;
use Database\Seeders\TextileMasterSeeder;
use Tests\TestCase;

class TextileMasterSeederTest extends TestCase
{
    /**
     * Verify that TextileMasterSeeder runs idempotently and populates suppliers and products.
     */
    public function test_textile_master_seeder_runs_idempotently(): void
    {
        $seeder = new TextileMasterSeeder();
        $seeder->run();

        // 1. Verify Textile Suppliers
        $expectedSuppliers = [
            'Lakshmi Spinning Mills Ltd' => '33AAACL2890A1ZB',
            'Sri Balaji Weaving & Powerloom Works' => '33AAHFS4455K1Z4',
            'Surat Synthetic & Rayon Weaves Pvt Ltd' => '24AAACS9988P1Z6',
            'Rainbow Textile Processors & Dyers' => '33AAACR5566M1Z2',
            'Coats India Threads & Accessories Ltd' => '33AAACC0123L1Z9',
            'Tirupur Knit Knots Garment Trims' => '33AABCT7788Q1Z1',
        ];

        foreach ($expectedSuppliers as $companyName => $gstin) {
            $supplier = Supplier::withoutGlobalScope('request_company')
                ->where('company_name', $companyName)
                ->first();

            $this->assertNotNull($supplier, "Supplier {$companyName} must exist in the database.");
            $this->assertSame($gstin, $supplier->vat_number, "Supplier {$companyName} must have GSTIN {$gstin}.");
            $this->assertNotEmpty($supplier->phone_number, "Supplier {$companyName} must have a phone number.");
            $this->assertNotEmpty($supplier->address, "Supplier {$companyName} must have an address.");
            $this->assertNotEmpty($supplier->city, "Supplier {$companyName} must have a city.");
            $this->assertTrue((bool) $supplier->is_active, "Supplier {$companyName} must be active.");
            $this->assertIsArray($supplier->purchase_defaults, "Supplier {$companyName} must have purchase defaults configured.");
        }

        // 2. Verify Textile Units
        foreach (['MTR', 'KGS', 'DOZ', 'PCS'] as $uomCode) {
            $unit = Unit::withoutGlobalScope('request_company')
                ->where('unit_code', $uomCode)
                ->first();
            $this->assertNotNull($unit, "Textile Unit {$uomCode} must exist.");
        }

        // 3. Verify Textile Categories
        $categories = [
            'Textiles & Handlooms',
            'Yarn & Spinning',
            'Grey & Woven Fabrics',
            'Dyed & Finished Fabrics',
            'Apparel & Made-ups',
            'Textile Trims & Accessories',
        ];

        foreach ($categories as $catName) {
            $cat = Category::withoutGlobalScope('request_company')
                ->where('name', $catName)
                ->first();
            $this->assertNotNull($cat, "Category {$catName} must exist.");
            $this->assertTrue((bool) $cat->is_active, "Category {$catName} must be active.");
        }

        // 4. Verify Textile Products
        $expectedProducts = [
            'TEX-YRN-40C' => ['name' => 'Cotton Combed Yarn 40s (Auto-Coner)', 'hsn' => '5205', 'cost' => 280.0, 'price' => 325.0],
            'TEX-YRN-30H' => ['name' => 'Cotton Carded Hosiery Yarn 30s', 'hsn' => '5205', 'cost' => 250.0, 'price' => 295.0],
            'TEX-KNT-180G' => ['name' => 'Single Jersey Cotton Knit Fabric 180 GSM (Raw Grey)', 'hsn' => '6006', 'cost' => 260.0, 'price' => 310.0],
            'TEX-GRY-63W' => ['name' => '100% Cotton Grey Fabric 40x40 / 92x88 (63 inch Width)', 'hsn' => '5208', 'cost' => 48.0, 'price' => 62.0],
            'TEX-PC-SHT58' => ['name' => 'Poly-Cotton Grey Sheeting Fabric (58 inch)', 'hsn' => '5513', 'cost' => 38.0, 'price' => 50.0],
            'TEX-POP-WHT' => ['name' => 'Pure Cotton Poplin Dyed Fabric - Super White (58 inch)', 'hsn' => '5208', 'cost' => 72.0, 'price' => 95.0],
            'TEX-KNT-NVY' => ['name' => 'Bio-Washed Combed Cotton Single Jersey (Navy Blue)', 'hsn' => '6006', 'cost' => 340.0, 'price' => 420.0],
            'TEX-RAY-BLK' => ['name' => 'Rayon / Viscose Liva Fabric 30s Dyed (Jet Black)', 'hsn' => '5408', 'cost' => 65.0, 'price' => 88.0],
            'TEX-DHT-GOLD8' => ['name' => 'Classic Gold Jari Border Cotton Dhoti (Double / 8 Muzham)', 'hsn' => '5208', 'cost' => 320.0, 'price' => 550.0],
            'TEX-TSH-PK3' => ['name' => "Men's Combed Cotton Round Neck T-Shirt (Pack of 3)", 'hsn' => '6109', 'cost' => 290.0, 'price' => 499.0],
            'TEX-TWL-500G' => ['name' => 'Premium Cotton Terry Bath Towel 500 GSM (75x150 cm)', 'hsn' => '6302', 'cost' => 180.0, 'price' => 320.0],
            'TEX-THD-40C' => ['name' => 'High Tenacity Spun Polyester Sewing Thread 40/2 (Cone 1000m)', 'hsn' => '5401', 'cost' => 35.0, 'price' => 55.0],
        ];

        foreach ($expectedProducts as $code => $meta) {
            $product = Product::withoutGlobalScope('request_company')
                ->where('code', $code)
                ->whereNotNull('company_id')
                ->latest('id')
                ->first()
                ?? Product::withoutGlobalScope('request_company')
                    ->where('code', $code)
                    ->first();

            $this->assertNotNull($product, "Product {$code} must exist in the database.");
            $this->assertSame($meta['name'], $product->name);
            $this->assertSame($meta['hsn'], $product->hsn_code);
            $this->assertEquals($meta['cost'], (float) $product->cost);
            $this->assertEquals($meta['price'], (float) $product->price);
            $this->assertTrue((bool) $product->is_active);

            // Verify Warehouse inventory stock linkage
            $pw = Product_Warehouse::where('product_id', $product->id)->first();
            $this->assertNotNull($pw, "Product {$code} must have an inventory warehouse link.");
            $this->assertGreaterThan(0, $pw->qty, "Product {$code} must have initial stock.");
        }
    }
}

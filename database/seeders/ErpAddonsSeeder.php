<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ErpAddonsSeeder extends Seeder
{
    public function run()
    {
        // 1. Semantic Account Mappings
        $mappings = [
            ['semantic_role' => 'sales_revenue', 'account_id' => 11, 'account_code' => '4010', 'account_name' => 'Sales Revenue', 'category' => 'core', 'description' => 'Default credit account for customer sales revenue'],
            ['semantic_role' => 'inventory_asset', 'account_id' => 4, 'account_code' => '1040', 'account_name' => 'Inventory Asset', 'category' => 'inventory', 'description' => 'Current inventory asset holding balance'],
            ['semantic_role' => 'cogs', 'account_id' => 14, 'account_code' => '5010', 'account_name' => 'Cost of Goods Sold', 'category' => 'core', 'description' => 'Expense account debited on cost of sold stock'],
            ['semantic_role' => 'accounts_receivable', 'account_id' => 3, 'account_code' => '1030', 'account_name' => 'Accounts Receivable', 'category' => 'core', 'description' => 'Unpaid customer receivables'],
            ['semantic_role' => 'accounts_payable', 'account_id' => 5, 'account_code' => '2010', 'account_name' => 'Accounts Payable', 'category' => 'core', 'description' => 'Supplier liability payable balance'],
            ['semantic_role' => 'petty_cash', 'account_id' => 1, 'account_code' => '1010', 'account_name' => 'Petty Cash', 'category' => 'banking', 'description' => 'Cash drawer & counter float'],
            ['semantic_role' => 'main_bank', 'account_id' => 2, 'account_code' => '1020', 'account_name' => 'Main Bank Account', 'category' => 'banking', 'description' => 'Primary commercial bank account'],
            ['semantic_role' => 'sales_tax_payable', 'account_id' => 6, 'account_code' => '2020', 'account_name' => 'Sales Tax / GST Payable', 'category' => 'tax', 'description' => 'Collected tax liability for government remittance'],
            ['semantic_role' => 'discounts_given', 'account_id' => 13, 'account_code' => '4030', 'account_name' => 'Discounts Given', 'category' => 'core', 'description' => 'Sales discount contra-revenue'],
            ['semantic_role' => 'damage_loss', 'account_id' => 14, 'account_code' => '5010', 'account_name' => 'Cost of Goods Sold', 'category' => 'inventory', 'description' => 'Expense write-off for damaged or expired stock'],
        ];

        foreach ($mappings as $map) {
            DB::table('semantic_account_mappings')->updateOrInsert(
                ['semantic_role' => $map['semantic_role']],
                array_merge($map, ['is_active' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }

        // 2. Water Tankers Fleet
        $tankers = [
            ['vehicle_number' => 'TN-74-AA-1001', 'model_type' => 'Ashok Leyland 16KL', 'capacity_kl' => 16, 'capacity_liters' => 16000, 'driver_name' => 'Murugan', 'driver_phone' => '+91 98421 11001', 'is_active' => true],
            ['vehicle_number' => 'TN-74-BB-2002', 'model_type' => 'BharatBenz 24KL', 'capacity_kl' => 24, 'capacity_liters' => 24000, 'driver_name' => 'Selvam', 'driver_phone' => '+91 98421 22002', 'is_active' => true],
            ['vehicle_number' => 'TN-74-CC-3003', 'model_type' => 'Eicher 9KL', 'capacity_kl' => 9, 'capacity_liters' => 9000, 'driver_name' => 'Dinesh (Manager / Driver)', 'driver_phone' => '+91 98421 33003', 'is_active' => true],
        ];

        foreach ($tankers as $t) {
            DB::table('water_tankers')->updateOrInsert(
                ['vehicle_number' => $t['vehicle_number']],
                array_merge($t, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 3. Water Can Routes (Tata Ace)
        $routes = [
            ['route_name' => 'Route A — Industrial Estate & IT Parks', 'vehicle_no' => 'Tata Ace TN-74-E-4545', 'driver_name' => 'Ramesh', 'driver_phone' => '+91 98421 44545', 'daily_avg_cans' => 70, 'is_active' => true],
            ['route_name' => 'Route B — Town & Residential Complexes', 'vehicle_no' => 'Tata Ace TN-74-F-5555', 'driver_name' => 'Kumar', 'driver_phone' => '+91 98421 55555', 'daily_avg_cans' => 60, 'is_active' => true],
            ['route_name' => 'Route C — Textile Mills & Colleges', 'vehicle_no' => 'Tata Ace TN-74-G-6565', 'driver_name' => 'Rajan', 'driver_phone' => '+91 98421 66565', 'daily_avg_cans' => 80, 'is_active' => true],
        ];

        foreach ($routes as $r) {
            DB::table('water_can_routes')->updateOrInsert(
                ['route_name' => $r['route_name']],
                array_merge($r, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 4. Repair Device Types
        $devices = [
            ['name' => 'Smartphones & Handhelds', 'description' => 'Android & iOS Mobile phones and POS handheld scanners', 'icon' => 'dripicons-device-mobile'],
            ['name' => 'Laptops & Workstations', 'description' => 'Business notebooks, desktops, and accounting terminals', 'icon' => 'dripicons-monitor'],
            ['name' => 'Thermal Receipt Printers', 'description' => '58mm and 80mm ESC/POS thermal receipt and barcode printers', 'icon' => 'dripicons-print'],
            ['name' => 'Water Dispensers & Coolers', 'description' => 'Commercial RO hot/cold water dispensers and bulk cooling units', 'icon' => 'dripicons-drop'],
        ];

        foreach ($devices as $d) {
            DB::table('repair_device_types')->updateOrInsert(
                ['name' => $d['name']],
                array_merge($d, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        // 5. Project Categories
        $categories = [
            ['name' => 'Enterprise ERP & Software', 'description' => 'Digital transformation and software installations'],
            ['name' => 'Fleet Logistics & Infrastructure', 'description' => 'Logistics fleet operations, tanker overhauls, and delivery expansion'],
            ['name' => 'Retail Store Fitout & Branding', 'description' => 'Bakery counter design, signage, and touch POS setup'],
        ];

        foreach ($categories as $c) {
            DB::table('project_categories')->updateOrInsert(
                ['name' => $c['name']],
                array_merge($c, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}

<?php

use App\Models\Company;
use App\Services\Accounting\SemanticAccountResolver;
use App\Services\Tax\GstSetupService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(fn () => $this->install());
    }

    private function install(): void
    {
        if (!Schema::hasTable('companies') || !Schema::hasTable('company_branches')) {
            return;
        }

        // 1. Resolve INR base currency
        $inrId = Schema::hasTable('currencies')
            ? (DB::table('currencies')->where('code', 'INR')->value('id') ?? 2)
            : 2;

        // 2. Ensure zoloERP Textiles (ZTEX) company exists
        $company = Company::withoutGlobalScopes()->where('code', 'ZTEX')->first();
        if (!$company) {
            $company = Company::forceCreate([
                'code' => 'ZTEX',
                'legal_name' => 'zoloERP Textiles',
                'trade_name' => 'zoloERP Textiles',
                'country_code' => 'IN',
                'base_currency_id' => $inrId ?: 2,
                'state_code' => '33',
                'timezone' => 'Asia/Kolkata',
                'status' => 'active',
                'address' => 'No. 45, Avinashi Road, Kumar Nagar',
                'city' => 'Tiruppur',
                'postal_code' => '641603',
                'phone' => '+91 421 400 2000',
                'email' => 'textiles@zoloerp.example',
                'pan' => 'ZOLOT1234K',
                'logo' => 'images/company/company-14-20261012171634-c927a3aa.png',
            ]);
        } else {
            $company->forceFill([
                'status' => 'active',
                'country_code' => 'IN',
                'state_code' => '33',
            ])->save();
        }

        // 3. Ensure Main Branch
        $branch = DB::table('company_branches')
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if (!$branch) {
            $branchId = DB::table('company_branches')->insertGetId([
                'company_id' => $company->id,
                'code' => 'MAIN',
                'name' => 'Main branch',
                'branch_type' => 'main',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $branchId = $branch->id;
        }

        // 4. Ensure Open Fiscal Year
        $fyId = null;
        if (Schema::hasTable('fiscal_years')) {
            $fy = DB::table('fiscal_years')
                ->where('company_id', $company->id)
                ->where('start_date', '2026-04-01')
                ->first()
                ?? DB::table('fiscal_years')
                    ->where('company_id', $company->id)
                    ->orderByDesc('start_date')
                    ->first();

            if (!$fy) {
                $fyId = DB::table('fiscal_years')->insertGetId([
                    'company_id' => $company->id,
                    'name' => 'FY 2026-27',
                    'start_date' => '2026-04-01',
                    'end_date' => '2027-03-31',
                    'status' => 'open',
                    'is_closed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $fyId = $fy->id;
            }
        }

        // 5. Ensure Main Warehouse
        if (Schema::hasTable('warehouses')) {
            $warehouse = DB::table('warehouses')
                ->where('company_id', $company->id)
                ->whereNull('external_job_order_id')
                ->first();

            if (!$warehouse) {
                DB::table('warehouses')->insert([
                    'name' => 'zoloERP Textiles - Main',
                    'address' => '-',
                    'is_active' => true,
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'is_quarantine' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 6. Ensure Tax Registration
        if (Schema::hasTable('tax_registrations')) {
            $taxReg = DB::table('tax_registrations')
                ->where('company_id', $company->id)
                ->where('gstin', '33ZOLOT1234K1Z5')
                ->first();

            if (!$taxReg) {
                DB::table('tax_registrations')->insert([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'gstin' => '33ZOLOT1234K1Z5',
                    'legal_name' => 'zoloERP Textiles',
                    'trade_name' => 'zoloERP Textiles',
                    'state_code' => '33',
                    'address' => 'No. 45, Avinashi Road, Kumar Nagar, Tiruppur, Tamil Nadu 641603',
                    'registration_type' => 'regular',
                    'status' => 'active',
                    'effective_from' => '2026-04-01',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 7. Ensure Textile Industry Profile Settings
        if (Schema::hasTable('company_industry_settings')) {
            $settingsJson = json_encode([
                'quantity_scale' => 3,
                'units' => ['MTR', 'KGS', 'DOZ', 'PCS'],
                'uqc' => 'MTR',
                'labels' => [
                    'dispatch' => 'Material DC',
                    'receipt' => 'Job Work GRN',
                    'warehouse' => 'Godown',
                ],
                'document_fields' => ['transporter', 'lr_no', 'lr_date', 'bale_count', 'bundle_count'],
                'print' => ['lines' => 68, 'columns' => 80],
                'entry_aids' => [
                    'previous_rates' => true,
                    'outstanding' => true,
                    'clone_invoice' => true,
                    'inline_party' => true,
                    'inline_item' => true,
                    'tracking' => true,
                ],
                'reports' => ['job_work_pending', 'job_work_shrinkage', 'stock_lot', 'previous_rates'],
            ]);

            $existing = DB::table('company_industry_settings')->where('company_id', $company->id)->first();
            if (!$existing) {
                DB::table('company_industry_settings')->insert([
                    'company_id' => $company->id,
                    'profile_key' => 'textile',
                    'subtype' => 'manufacturing',
                    'settings_json' => $settingsJson,
                    'updated_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 8. Grant Administrators Membership & Branch Access
        if (Schema::hasTable('company_user') && Schema::hasTable('company_user_branches') && Schema::hasTable('users')) {
            $admins = DB::table('users')->whereIn('role_id', [1, 2])->where('is_active', true)->where('is_deleted', false)->pluck('id');
            foreach ($admins as $userId) {
                DB::table('company_user')->insertOrIgnore([
                    'company_id' => $company->id,
                    'user_id' => $userId,
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('company_user_branches')->insertOrIgnore([
                    'company_id' => $company->id,
                    'user_id' => $userId,
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 9. Standard Chart of Accounts & Starter Masters
        if (class_exists(\Database\Seeders\ChartOfAccountsSeeder::class) && Schema::hasTable('chart_of_accounts')) {
            $ids = [];
            foreach (\Database\Seeders\ChartOfAccountsSeeder::accounts() as $account) {
                $existingAccount = DB::table('chart_of_accounts')
                    ->where('company_id', $company->id)
                    ->where('code', $account['code'])
                    ->first();

                if (!$existingAccount) {
                    $newId = DB::table('chart_of_accounts')->insertGetId([
                        'company_id' => $company->id,
                        'code' => $account['code'],
                        'name' => $account['name'],
                        'type' => $account['type'],
                        'sub_type' => $account['sub_type'],
                        'parent_id' => $ids[$account['parent_code']] ?? null,
                        'is_system' => $account['is_system'],
                        'description' => $account['description'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $ids[$account['code']] = $newId;
                } else {
                    $ids[$account['code']] = $existingAccount->id;
                }
            }

            // Parent link pass
            $allAccounts = DB::table('chart_of_accounts')->where('company_id', $company->id)->pluck('id', 'code');
            foreach (\Database\Seeders\ChartOfAccountsSeeder::accounts() as $account) {
                if (!empty($account['parent_code']) && isset($allAccounts[$account['parent_code']], $allAccounts[$account['code']])) {
                    DB::table('chart_of_accounts')
                        ->where('id', $allAccounts[$account['code']])
                        ->whereNull('parent_id')
                        ->update(['parent_id' => $allAccounts[$account['parent_code']]]);
                }
            }

            app(SemanticAccountResolver::class)->seedCompany($company->id);
            app(GstSetupService::class)->ensureLedgers($company->id);
        }

        if (class_exists(\App\Services\Platform\CompanyDirectoryService::class)) {
            app(\App\Services\Platform\CompanyDirectoryService::class)->installStarterMasters($company);
        }

        if ($fyId && class_exists(\Database\Seeders\OptechMasterSeeder::class)) {
            \Database\Seeders\OptechMasterSeeder::seedSeries($company->id, $branchId, $fyId);
        }

        // 10. Seed Textile Masters (Units, Brands, Categories, Suppliers, Products)
        if (class_exists(\Database\Seeders\TextileMasterSeeder::class)) {
            (new \Database\Seeders\TextileMasterSeeder)->seedCompany($company, catalogOnly: true);
        }

        // 11. Seed Demo Customers for Textile Trading / Manufacturing
        if (Schema::hasTable('customers')) {
            $customerGroup = DB::table('customer_groups')->where('company_id', $company->id)->first();
            $groupId = $customerGroup ? $customerGroup->id : 1;
            $demoCustomers = [
                ['name' => 'Demo Tiruppur Garment Works', 'company_name' => 'Demo Tiruppur Garment Works', 'state' => 'Tamil Nadu', 'city' => 'Tiruppur', 'credit_days' => 30, 'tax_no' => '33AAAFL4104K1Z0'],
                ['name' => 'Demo Mumbai Fabric Distributors', 'company_name' => 'Demo Mumbai Fabric Distributors', 'state' => 'Maharashtra', 'city' => 'Mumbai', 'credit_days' => 21, 'tax_no' => '27AAAFL4105K1Z8'],
                ['name' => 'Demo Erode Counter Buyer', 'company_name' => 'Demo Erode Counter Buyer', 'state' => 'Tamil Nadu', 'city' => 'Erode', 'credit_days' => 0, 'tax_no' => null],
            ];
            foreach ($demoCustomers as $c) {
                $exists = DB::table('customers')->where('company_id', $company->id)->where('name', $c['name'])->first();
                if (!$exists) {
                    DB::table('customers')->insert(array_merge($c, [
                        'company_id' => $company->id,
                        'customer_group_id' => $groupId,
                        'is_active' => true,
                        'phone_number' => '9842000001',
                        'country' => 'India',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }

        // 12. Seed Demo Suppliers
        if (Schema::hasTable('suppliers')) {
            $demoSuppliers = [
                ['name' => 'Demo Erode Grey Fabric Mill', 'company_name' => 'Demo Erode Grey Fabric Mill', 'state' => 'Tamil Nadu', 'city' => 'Erode', 'credit_days' => 30, 'vat_number' => '33AAAFL4101K1Z6'],
                ['name' => 'Demo Perundurai Dyeing and Finishing', 'company_name' => 'Demo Perundurai Dyeing and Finishing', 'state' => 'Tamil Nadu', 'city' => 'Perundurai', 'credit_days' => 15, 'vat_number' => '33AAAFL4102K1Z4'],
                ['name' => 'Demo Surat Finished Fabric Mill', 'company_name' => 'Demo Surat Finished Fabric Mill', 'state' => 'Gujarat', 'city' => 'Surat', 'credit_days' => 21, 'vat_number' => '24AAAFL4103K1Z2'],
            ];
            foreach ($demoSuppliers as $s) {
                $exists = DB::table('suppliers')->where('company_id', $company->id)->where('company_name', $s['company_name'])->first();
                if (!$exists) {
                    DB::table('suppliers')->insert(array_merge($s, [
                        'company_id' => $company->id,
                        'is_active' => true,
                        'phone_number' => '9842000002',
                        'country' => 'India',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }

        // 13. Seed Demo Products
        if (Schema::hasTable('products')) {
            $mtrUnit = DB::table('units')->where('company_id', $company->id)->where('unit_code', 'MTR')->value('id')
                ?? DB::table('units')->where('unit_code', 'MTR')->value('id') ?? 1;
            $category = DB::table('categories')->where('company_id', $company->id)->value('id')
                ?? DB::table('categories')->value('id') ?? 1;
            $taxCat = DB::table('tax_categories')->where('company_id', $company->id)->value('id') ?? null;

            $demoProducts = [
                ['name' => 'Demo Grey Cotton 40s', 'code' => 'ZT-DEMO-GREY', 'type' => 'standard', 'hsn_code' => '5208', 'cost' => 40.00, 'price' => 65.00],
                ['name' => 'Demo Dyed Cotton Poplin', 'code' => 'ZT-DEMO-POPLIN', 'type' => 'standard', 'hsn_code' => '5208', 'cost' => 80.00, 'price' => 120.00],
                ['name' => 'Demo Textile Processing Charge', 'code' => 'ZT-DEMO-PROCESS', 'type' => 'service', 'hsn_code' => '998821', 'cost' => 12.00, 'price' => 20.00],
            ];
            foreach ($demoProducts as $p) {
                $exists = DB::table('products')->where('company_id', $company->id)->where('code', $p['code'])->first();
                if (!$exists) {
                    DB::table('products')->insert(array_merge($p, [
                        'company_id' => $company->id,
                        'category_id' => $category,
                        'unit_id' => $mtrUnit,
                        'purchase_unit_id' => $mtrUnit,
                        'sale_unit_id' => $mtrUnit,
                        'tax_category_id' => $taxCat,
                        'barcode_symbology' => 'C128',
                        'is_active' => true,
                        'qty' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        // Production data is retained to protect audit history
    }
};


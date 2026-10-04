<?php

namespace Tests\Support;

use App\Models\Accounting\ChartOfAccount;
use App\Models\Product;
use App\Services\Commercial\{SaleApplicationService, SaleCommand};
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class ComplianceTestCase extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->company->update(['country_code' => 'IN', 'state_code' => '33']);
        if (!Schema::hasColumn('products', 'hsn_code')) Schema::table('products', fn (Blueprint $t) => $t->string('hsn_code')->nullable());
        (require database_path('migrations/2026_10_06_000001_create_tax_compliance_contracts.php'))->up();
        config(['compliance.enabled' => true]);
        DB::table('tax_registrations')->insert(['company_id' => $this->company->id, 'branch_id' => $this->branch->id,
            'gstin' => $this->gstin('33'), 'legal_name' => 'Fixture Company', 'state_code' => '33', 'effective_from' => '2026-01-01']);
        foreach (['customer', 'supplier'] as $type) {
            DB::table('party_tax_profiles')->insert(['company_id' => $this->company->id, 'party_type' => $type, 'party_id' => 1,
                'gstin' => $this->gstin('33'), 'state_code' => '33', 'registration_type' => 'regular']);
        }
        $category = DB::table('tax_categories')->insertGetId(['company_id' => $this->company->id, 'name' => 'Domestic goods',
            'supply_type' => 'taxable', 'input_credit_allowed' => true]);
        DB::table('tax_rates')->insert(['company_id' => $this->company->id, 'tax_category_id' => $category,
            'rate' => 18, 'effective_from' => '2026-01-01']);
        DB::table('hsn_sac_codes')->insert(['company_id' => $this->company->id, 'code' => '1001', 'kind' => 'hsn', 'description' => 'Fixture goods']);
        DB::table('hsn_sac_codes')->insert(['company_id' => $this->company->id, 'code' => '998313', 'kind' => 'sac', 'description' => 'Fixture service']);
        foreach (['output_tax_cgst', 'output_tax_sgst', 'output_tax_igst', 'output_tax_cess', 'input_tax_cgst', 'input_tax_sgst', 'input_tax_igst', 'input_tax_cess',
            'sales_returns', 'variance', 'damage'] as $role) {
            $type = str_starts_with($role, 'output') ? 'liability' : (str_starts_with($role, 'input') ? 'asset' : ($role === 'sales_returns' ? 'revenue' : 'expense'));
            $account = ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => strtoupper($role), 'name' => $role, 'type' => $type, 'sub_type' => $role]);
            DB::table('semantic_account_mappings')->updateOrInsert(['company_id' => $this->company->id, 'semantic_role' => $role],
                ['account_id' => $account->id, 'is_active' => true, 'account_code' => $account->code, 'account_name' => $role, 'category' => 'core']);
        }
    }

    protected function gstProduct(string $type = 'standard'): Product
    {
        $product = $this->stock();
        $product->forceFill(['tax_category_id' => DB::table('tax_categories')->value('id'), 'hsn_code' => $type === 'service' ? '998313' : '1001', 'type' => $type])->save();
        return $product;
    }

    protected function gstSale(array $extra = [], string $key = 'gst-sale'): \App\Models\Sale
    {
        $product = $this->gstProduct();
        return app(SaleApplicationService::class)->create(new SaleCommand($this->saleData($product, $extra), $key, 1, $this->context()));
    }

    protected function gstin(string $state): string
    {
        $prefix = $state.'ABCDE1234F1Z'; $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $factor = 2; $sum = 0;
        for ($i = 13; $i >= 0; $i--) { $n = strpos($alphabet, $prefix[$i]) * $factor; $sum += intdiv($n, 36) + $n % 36; $factor = $factor === 2 ? 1 : 2; }
        return $prefix.$alphabet[(36 - $sum % 36) % 36];
    }
}

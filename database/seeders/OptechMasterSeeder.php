<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Area;
use App\Models\BillSundry;
use App\Models\Company;
use App\Models\PurchaseType;
use App\Models\SaleType;
use App\Models\StandardRemark;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OptechMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            self::seedCompany($company);
        }
    }

    /** Idempotent starter masters for one company; also run when a company is created from the picker. */
    public static function seedCompany(Company $company): void
    {
        foreach (['agents', 'areas', 'sale_types', 'purchase_types', 'bill_sundries', 'standard_remarks', 'document_series'] as $table) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return;
        }
        {
            $companyId = $company->id;

            // 1. Default Agent: DIRECT
            Agent::firstOrCreate(
                ['company_id' => $companyId, 'name' => 'DIRECT'],
                ['code' => 'DIR', 'commission_rate' => 0, 'is_active' => true]
            );

            // 2. Default Area: Primary Market
            Area::firstOrCreate(
                ['company_id' => $companyId, 'name' => 'Local / Head Office'],
                ['code' => 'HQ', 'city' => $company->city ?? 'Chennai', 'state' => $company->state ?? 'Tamil Nadu', 'is_active' => true]
            );

            // 3. Sale Types
            $saleTypes = [
                ['name' => '5%-GST', 'code' => 'SGST5', 'tax_nature' => 'local', 'tax_rate' => 5],
                ['name' => '12%-GST', 'code' => 'SGST12', 'tax_nature' => 'local', 'tax_rate' => 12],
                ['name' => '18%-GST', 'code' => 'SGST18', 'tax_nature' => 'local', 'tax_rate' => 18],
                ['name' => '28%-GST', 'code' => 'SGST28', 'tax_nature' => 'local', 'tax_rate' => 28],
                ['name' => '5%-IGST', 'code' => 'SIGST5', 'tax_nature' => 'interstate', 'tax_rate' => 5],
                ['name' => '12%-IGST', 'code' => 'SIGST12', 'tax_nature' => 'interstate', 'tax_rate' => 12],
                ['name' => '18%-IGST', 'code' => 'SIGST18', 'tax_nature' => 'interstate', 'tax_rate' => 18],
                ['name' => '28%-IGST', 'code' => 'SIGST28', 'tax_nature' => 'interstate', 'tax_rate' => 28],
                ['name' => 'L/MultiTax', 'code' => 'SMULTI', 'tax_nature' => 'local', 'tax_rate' => 0],
                ['name' => 'Interstate MultiTax', 'code' => 'SIMULTI', 'tax_nature' => 'interstate', 'tax_rate' => 0],
                ['name' => 'Export', 'code' => 'SEXP', 'tax_nature' => 'export', 'tax_rate' => 0],
                ['name' => 'SEZ Supply', 'code' => 'SSEZ', 'tax_nature' => 'sez', 'tax_rate' => 0],
                ['name' => 'Exempted', 'code' => 'SEXM', 'tax_nature' => 'exempted', 'tax_rate' => 0],
            ];
            foreach ($saleTypes as $st) {
                SaleType::firstOrCreate(
                    ['company_id' => $companyId, 'name' => $st['name']],
                    $st + ['is_active' => true]
                );
            }

            // 4. Purchase Types
            $purchaseTypes = [
                ['name' => 'L/MultiTax', 'code' => 'PMULTI', 'tax_nature' => 'local', 'tax_rate' => 0],
                ['name' => 'Interstate MultiTax', 'code' => 'PIMULTI', 'tax_nature' => 'interstate', 'tax_rate' => 0],
                ['name' => '5%-GST Inward', 'code' => 'PGST5', 'tax_nature' => 'local', 'tax_rate' => 5],
                ['name' => '12%-GST Inward', 'code' => 'PGST12', 'tax_nature' => 'local', 'tax_rate' => 12],
                ['name' => '18%-GST Inward', 'code' => 'PGST18', 'tax_nature' => 'local', 'tax_rate' => 18],
                ['name' => '28%-GST Inward', 'code' => 'PGST28', 'tax_nature' => 'local', 'tax_rate' => 28],
                ['name' => '5%-IGST Inward', 'code' => 'PIGST5', 'tax_nature' => 'interstate', 'tax_rate' => 5],
                ['name' => '12%-IGST Inward', 'code' => 'PIGST12', 'tax_nature' => 'interstate', 'tax_rate' => 12],
                ['name' => '18%-IGST Inward', 'code' => 'PIGST18', 'tax_nature' => 'interstate', 'tax_rate' => 18],
                ['name' => '28%-IGST Inward', 'code' => 'PIGST28', 'tax_nature' => 'interstate', 'tax_rate' => 28],
                ['name' => 'Import', 'code' => 'PIMP', 'tax_nature' => 'import', 'tax_rate' => 0],
                ['name' => 'Exempted Inward', 'code' => 'PEXM', 'tax_nature' => 'exempted', 'tax_rate' => 0],
            ];
            foreach ($purchaseTypes as $pt) {
                PurchaseType::firstOrCreate(
                    ['company_id' => $companyId, 'name' => $pt['name']],
                    $pt + ['is_active' => true]
                );
            }

            // 5. Bill Sundries
            $billSundries = [
                ['name' => 'Freight Charges', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => true, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Loading & Unloading', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => true, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Insurance Charges', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => true, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Trade Discount', 'nature' => 'both', 'calculation_type' => 'percentage', 'affect_cost' => false, 'calculate_before_tax' => true, 'tax_rate' => 0],
                ['name' => 'Cash Discount', 'nature' => 'both', 'calculation_type' => 'percentage', 'affect_cost' => false, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Packaging Charges', 'nature' => 'sales', 'calculation_type' => 'amount', 'affect_cost' => false, 'calculate_before_tax' => true, 'tax_rate' => 0],
                ['name' => 'Rounded Off (Add)', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => false, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Rounded Off (Sub)', 'nature' => 'both', 'calculation_type' => 'amount', 'affect_cost' => false, 'calculate_before_tax' => false, 'tax_rate' => 0],
                ['name' => 'Surcharge', 'nature' => 'sales', 'calculation_type' => 'amount', 'affect_cost' => false, 'calculate_before_tax' => false, 'tax_rate' => 0],
            ];
            foreach ($billSundries as $bs) {
                BillSundry::firstOrCreate(
                    ['company_id' => $companyId, 'name' => $bs['name']],
                    $bs + ['default_value' => 0, 'is_active' => true]
                );
            }

            // 6. Standard Remarks
            $remarks = [
                ['title' => 'Standard Terms', 'type' => 'sale', 'remark' => 'Goods once sold will not be taken back.', 'is_default' => true],
                ['title' => 'Interest Clause', 'type' => 'sale', 'remark' => 'Interest @ 18% p.a. will be charged if payment is delayed beyond credit days.', 'is_default' => false],
                ['title' => 'Jurisdiction', 'type' => 'all', 'remark' => 'Subject to local court jurisdiction only.', 'is_default' => false],
            ];
            foreach ($remarks as $rm) {
                StandardRemark::firstOrCreate(
                    ['company_id' => $companyId, 'title' => $rm['title']],
                    $rm + ['is_active' => true]
                );
            }

            // 7. Standard Document Series (SERIES_04) for the first branch and financial year
            $branch = DB::table('company_branches')->where('company_id', $companyId)->orderBy('id')->first();
            $fy = DB::table('fiscal_years')->where('company_id', $companyId)->orderBy('start_date')->first();
            if ($branch && $fy) {
                self::seedSeries($companyId, (int) $branch->id, (int) $fy->id);
            }
        }
    }

    /**
     * Readable default numbering for one branch and financial year. Idempotent; existing series are never changed.
     * Numbers are unique across the whole company, so a later branch adds its code and a later financial year its
     * starting year (MJ-00001, MJ-CBE-00001, MJ-27-00001) - at most 16 characters for GST invoices.
     */
    public static function seedSeries(int $companyId, int $branchId, int $fyId): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('document_series')) return;
        // Tag only when the plain prefix is already used elsewhere in the company (another branch / another year).
        $elsewhere = DB::table('document_series')->where('company_id', $companyId)
            ->where(fn ($q) => $q->where('branch_id', '<>', $branchId)->orWhere('financial_year_id', '<>', $fyId));
        $tag = '';
        $firstBranch = (int) DB::table('company_branches')->where('company_id', $companyId)->orderBy('id')->value('id');
        if ($firstBranch !== $branchId && (clone $elsewhere)->where('branch_id', '<>', $branchId)->exists()) {
            $code = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) DB::table('company_branches')->where('id', $branchId)->value('code'))), 0, 4);
            $tag .= ($code !== '' ? $code : 'B'.$branchId).'-';
        }
        if ((clone $elsewhere)->where('branch_id', $branchId)->where('financial_year_id', '<>', $fyId)->exists()
            || ($tag !== '' && (clone $elsewhere)->where('prefix', 'like', '%-'.$tag)->exists())) {
            $tag .= substr((string) DB::table('fiscal_years')->where('id', $fyId)->value('start_date'), 2, 2).'-';
        }
        $series = [
            ['sale', 'SALES', 'MJ-', 1], ['sale', 'Service', 'MJC-', 0], ['purchase', 'PURCHASE', 'PUR-', 1],
            ['purchase', 'SERVICE PURCHASE', 'SPUR-', 0], ['purchase_order', 'PURCHASE ORDER', 'PO-', 1], ['sale_payment', 'RECEIPT', 'REC-', 1], ['purchase_payment', 'PAYMENT', 'PAY-', 1],
            ['journal', 'JOURNAL', 'JE-', 1], ['quotation', 'QUOTATION', 'QUO-', 1], ['sale_credit_note', 'SALES RETURN', 'SR-', 1],
            ['purchase_debit_note', 'PURCHASE RETURN', 'PR-', 1], ['delivery_challan', 'DELIVERY CHALLAN', 'DC-', 1],
            ['grn', 'GOODS RECEIVED NOTE', 'GRN-', 1], ['production', 'PRODUCTION', 'PRD-', 1],
            ['adjustment', 'STOCK ADJUSTMENT', 'ADJ-', 1], ['transfer', 'STOCK TRANSFER', 'TRF-', 1],
            ['damage', 'STOCK LOSS', 'LOSS-', 1], ['expense', 'EXPENSE', 'EXP-', 1],
            // Job work numbers travel with the goods, so they stay short.
            ['job_work_order', 'JOB WORK ORDER', 'JWO-', 1], ['job_work_dispatch', 'JOB WORK DC', 'JWDC-', 1], ['job_work_receipt', 'JOB WORK GRN', 'JWGRN-', 1],
        ];
        $scope = ['company_id' => $companyId, 'branch_id' => $branchId, 'financial_year_id' => $fyId];
        foreach ($series as [$type, $code, $prefix, $default]) {
            if (DB::table('document_series')->where($scope)->where('code', $code)->exists()) continue;
            // Job work and production keep a company's existing numbering of that type.
            if (in_array($type, ['production', 'job_work_order', 'job_work_dispatch', 'job_work_receipt'], true)
                && DB::table('document_series')->where($scope)->where('document_type', $type)->exists()) continue;
            // One default per document type: an existing default (for example an automatic series) stays the default.
            $default = $default && !DB::table('document_series')->where($scope)->where('document_type', $type)->where('is_default', true)->exists();
            DB::table('document_series')->insert($scope + ['document_type' => $type, 'code' => $code, 'prefix' => $prefix.$tag,
                'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => $default, 'reset_policy' => 'financial_year',
                'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

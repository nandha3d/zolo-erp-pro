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
        $companies = Company::all();

        foreach ($companies as $company) {
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

            // 7. Standard Document Series (SERIES_04)
            $branch = DB::table('company_branches')->where('company_id', $companyId)->first();
            $fy = DB::table('fiscal_years')->where('company_id', $companyId)->first();

            if ($branch && $fy) {
                $seriesList = [
                    ['document_type' => 'sale', 'code' => 'SALES', 'prefix' => 'MJ-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'sale', 'code' => 'Service', 'prefix' => 'MJC-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 0],
                    ['document_type' => 'purchase', 'code' => 'PURCHASE', 'prefix' => 'PUR-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'purchase', 'code' => 'SERVICE PURCHASE', 'prefix' => 'SPUR-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 0],
                    ['document_type' => 'sale_payment', 'code' => 'RECEIPT', 'prefix' => 'REC-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'purchase_payment', 'code' => 'PAYMENT', 'prefix' => 'PAY-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'journal', 'code' => 'JOURNAL', 'prefix' => 'JE-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'quotation', 'code' => 'QUOTATION', 'prefix' => 'QUO-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'sale_credit_note', 'code' => 'SALES RETURN', 'prefix' => 'SR-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'purchase_debit_note', 'code' => 'PURCHASE RETURN', 'prefix' => 'PR-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'delivery_challan', 'code' => 'DELIVERY CHALLAN', 'prefix' => 'DC-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                    ['document_type' => 'grn', 'code' => 'GOODS RECEIVED NOTE', 'prefix' => 'GRN-', 'suffix' => '', 'next_number' => 1, 'padding' => 5, 'is_default' => 1],
                ];

                foreach ($seriesList as $ser) {
                    $exists = DB::table('document_series')->where([
                        'company_id' => $companyId,
                        'branch_id' => $branch->id,
                        'financial_year_id' => $fy->id,
                        'code' => $ser['code'],
                    ])->exists();

                    if (!$exists) {
                        DB::table('document_series')->insert($ser + [
                            'company_id' => $companyId,
                            'branch_id' => $branch->id,
                            'financial_year_id' => $fy->id,
                            'reset_policy' => 'financial_year',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}

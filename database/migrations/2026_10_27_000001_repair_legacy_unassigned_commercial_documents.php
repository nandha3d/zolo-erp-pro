<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Backfill master tables where company_id is NULL to Company 1 (DEFAULT)
        foreach (['categories', 'units', 'products'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => 1]);
            }
        }

        // 2. Backfill purchases where company_id or branch_id is NULL
        if (Schema::hasTable('purchases')) {
            $hasBranch = Schema::hasColumn('purchases', 'branch_id');
            $hasYear = Schema::hasColumn('purchases', 'financial_year_id');
            $hasCompany = Schema::hasColumn('purchases', 'company_id');

            if ($hasBranch || $hasCompany) {
                DB::table('purchases')
                    ->where(function ($q) use ($hasBranch, $hasCompany) {
                        if ($hasCompany) $q->whereNull('company_id');
                        if ($hasBranch) $q->orWhereNull('branch_id');
                    })
                    ->lazyById()
                    ->each(function ($row) use ($hasBranch, $hasYear, $hasCompany) {
                        $warehouse = DB::table('warehouses')->where('id', $row->warehouse_id)->first();
                        $companyId = $row->company_id ?? $warehouse?->company_id ?? 1;
                        $branchId = ($hasBranch ? $row->branch_id : null) ?? $warehouse?->branch_id ?? 1;
                        $yearId = null;
                        if ($hasYear) {
                            $date = $row->created_at ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
                            $yearId = $row->financial_year_id ?? DB::table('fiscal_years')
                                ->where('company_id', $companyId)
                                ->whereDate('start_date', '<=', $date)
                                ->whereDate('end_date', '>=', $date)
                                ->value('id') ?? 1;
                        }

                        $updates = [];
                        if ($hasCompany && !$row->company_id) $updates['company_id'] = $companyId;
                        if ($hasBranch && !$row->branch_id) $updates['branch_id'] = $branchId;
                        if ($hasYear && !$row->financial_year_id) $updates['financial_year_id'] = $yearId;

                        if (!empty($updates)) {
                            DB::table('purchases')->where('id', $row->id)->update($updates);
                        }
                    });
            }

            // Sync product_purchases company_id from parent purchase
            if (Schema::hasTable('product_purchases') && Schema::hasColumn('product_purchases', 'company_id')) {
                DB::statement("
                    UPDATE product_purchases pp
                    JOIN purchases p ON p.id = pp.purchase_id
                    SET pp.company_id = p.company_id
                    WHERE pp.company_id IS NULL AND p.company_id IS NOT NULL
                ");
            }
        }

        // 3. Backfill sales where company_id or branch_id is NULL
        if (Schema::hasTable('sales')) {
            $hasBranch = Schema::hasColumn('sales', 'branch_id');
            $hasYear = Schema::hasColumn('sales', 'financial_year_id');
            $hasCompany = Schema::hasColumn('sales', 'company_id');

            if ($hasBranch || $hasCompany) {
                DB::table('sales')
                    ->where(function ($q) use ($hasBranch, $hasCompany) {
                        if ($hasCompany) $q->whereNull('company_id');
                        if ($hasBranch) $q->orWhereNull('branch_id');
                    })
                    ->lazyById()
                    ->each(function ($row) use ($hasBranch, $hasYear, $hasCompany) {
                        $warehouse = DB::table('warehouses')->where('id', $row->warehouse_id)->first();
                        $companyId = $row->company_id ?? $warehouse?->company_id ?? 1;
                        $branchId = ($hasBranch ? $row->branch_id : null) ?? $warehouse?->branch_id ?? 1;
                        $yearId = null;
                        if ($hasYear) {
                            $date = $row->created_at ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
                            $yearId = $row->financial_year_id ?? DB::table('fiscal_years')
                                ->where('company_id', $companyId)
                                ->whereDate('start_date', '<=', $date)
                                ->whereDate('end_date', '>=', $date)
                                ->value('id') ?? 1;
                        }

                        $updates = [];
                        if ($hasCompany && !$row->company_id) $updates['company_id'] = $companyId;
                        if ($hasBranch && !$row->branch_id) $updates['branch_id'] = $branchId;
                        if ($hasYear && !$row->financial_year_id) $updates['financial_year_id'] = $yearId;

                        if (!empty($updates)) {
                            DB::table('sales')->where('id', $row->id)->update($updates);
                        }
                    });
            }

            // Sync product_sales company_id from parent sale
            if (Schema::hasTable('product_sales') && Schema::hasColumn('product_sales', 'company_id')) {
                DB::statement("
                    UPDATE product_sales ps
                    JOIN sales s ON s.id = ps.sale_id
                    SET ps.company_id = s.company_id
                    WHERE ps.company_id IS NULL AND s.company_id IS NOT NULL
                ");
            }
        }

        // 4. Backfill returns and return_purchases
        foreach (['returns' => 'product_returns', 'return_purchases' => 'purchase_product_return'] as $header => $line) {
            if (Schema::hasTable($header)) {
                $hasBranch = Schema::hasColumn($header, 'branch_id');
                $hasYear = Schema::hasColumn($header, 'financial_year_id');
                $hasCompany = Schema::hasColumn($header, 'company_id');

                if ($hasBranch || $hasCompany) {
                    DB::table($header)
                        ->where(function ($q) use ($hasBranch, $hasCompany) {
                            if ($hasCompany) $q->whereNull('company_id');
                            if ($hasBranch) $q->orWhereNull('branch_id');
                        })
                        ->lazyById()
                        ->each(function ($row) use ($header, $hasBranch, $hasYear, $hasCompany) {
                            $warehouse = DB::table('warehouses')->where('id', $row->warehouse_id)->first();
                            $companyId = $row->company_id ?? $warehouse?->company_id ?? 1;
                            $branchId = ($hasBranch ? $row->branch_id : null) ?? $warehouse?->branch_id ?? 1;
                            $yearId = null;
                            if ($hasYear) {
                                $date = $row->created_at ? substr((string) $row->created_at, 0, 10) : date('Y-m-d');
                                $yearId = $row->financial_year_id ?? DB::table('fiscal_years')
                                    ->where('company_id', $companyId)
                                    ->whereDate('start_date', '<=', $date)
                                    ->whereDate('end_date', '>=', $date)
                                    ->value('id') ?? 1;
                            }

                            $updates = [];
                            if ($hasCompany && !$row->company_id) $updates['company_id'] = $companyId;
                            if ($hasBranch && !$row->branch_id) $updates['branch_id'] = $branchId;
                            if ($hasYear && !$row->financial_year_id) $updates['financial_year_id'] = $yearId;

                            if (!empty($updates)) {
                                DB::table($header)->where('id', $row->id)->update($updates);
                            }
                        });
                }

                if (Schema::hasTable($line) && Schema::hasColumn($line, 'company_id')) {
                    $foreignKey = 'return_id';
                    DB::statement("
                        UPDATE {$line} l
                        JOIN {$header} h ON h.id = l.{$foreignKey}
                        SET l.company_id = h.company_id
                        WHERE l.company_id IS NULL AND h.company_id IS NOT NULL
                    ");
                }
            }
        }

        // 5. Ensure products with company 1 have a tax category
        if (Schema::hasTable('tax_categories')) {
            $defaultTaxCatId = DB::table('tax_categories')->where('company_id', 1)->where('name', 'like', '%5%')->value('id')
                ?? DB::table('tax_categories')->where('company_id', 1)->value('id');
            if ($defaultTaxCatId) {
                DB::table('products')->where('company_id', 1)->whereNull('tax_category_id')->update(['tax_category_id' => $defaultTaxCatId]);
            }
        }
    }

    public function down(): void
    {
        // Data repair migration; nothing to undo.
    }
};

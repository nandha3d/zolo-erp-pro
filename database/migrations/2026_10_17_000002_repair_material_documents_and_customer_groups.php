<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data repair found by the production test matrix:
 * - GRNs and delivery challans were saved without branch / financial year, so branch isolation could not apply.
 *   They take the branch of their warehouse and the company financial year that contains their date.
 * - Quick-created customers pointed at customer group 1 (seed data, not the company's group). They move to the
 *   company's first active group when their group belongs to no company or to another company.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['goods_received_notes' => 'grn_date', 'delivery_challans' => 'challan_date'] as $table => $dateColumn) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id') || !Schema::hasColumn($table, 'financial_year_id')) {
                continue;
            }
            DB::table($table)->whereNull('branch_id')->lazyById()->each(function ($row) use ($table, $dateColumn) {
                $branch = DB::table('warehouses')->where('id', $row->warehouse_id)->where('company_id', $row->company_id)->value('branch_id');
                $year = DB::table('fiscal_years')->where('company_id', $row->company_id)
                    ->whereDate('start_date', '<=', $row->{$dateColumn})->whereDate('end_date', '>=', $row->{$dateColumn})->value('id');
                DB::table($table)->where('id', $row->id)->update(array_filter(['branch_id' => $branch,
                    'financial_year_id' => $row->financial_year_id ?? $year]));
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customer_groups', 'company_id')) {
            DB::table('customers as c')->leftJoin('customer_groups as g', 'g.id', '=', 'c.customer_group_id')
                ->whereNotNull('c.company_id')
                ->where(fn ($q) => $q->whereNull('g.id')->orWhereNull('g.company_id')->orWhereColumn('g.company_id', '<>', 'c.company_id'))
                ->select('c.id', 'c.company_id')->orderBy('c.id')->get()
                ->each(function ($customer) {
                    $group = DB::table('customer_groups')->where('company_id', $customer->company_id)->where('is_active', true)->orderBy('id')->value('id');
                    if ($group) {
                        DB::table('customers')->where('id', $customer->id)->update(['customer_group_id' => $group]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Repairs only fill missing ownership; nothing to undo.
    }
};

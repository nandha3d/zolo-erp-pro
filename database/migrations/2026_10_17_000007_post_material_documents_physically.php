<?php

use App\Models\Accounting\ChartOfAccount;
use App\Services\Accounting\SemanticAccountResolver;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Supplier GRN and Customer DC move stock when the goods move; the later bill or invoice only settles them commercially.
 * Headers keep their own stock movement and accrual journal, items track what has been billed or returned, and
 * material_settlements records which bill line (or return) settled which document line.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['goods_received_notes', 'delivery_challans'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'posted_at')) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if ($table === 'delivery_challans') $t->string('purpose', 30)->default('approval')->after('status');
                $t->unsignedBigInteger('stock_movement_id')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->unsignedInteger('posting_version')->default(0);
                $t->timestamp('posted_at')->nullable();
            });
        }
        foreach (['goods_received_note_items', 'delivery_challan_items'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'qty_base')) continue;
            Schema::table($table, function (Blueprint $t) {
                $t->string('batch_no', 100)->nullable();
                $t->decimal('qty_base', 18, 4)->nullable();
                $t->decimal('value', 18, 4)->nullable();
                $t->decimal('billed_qty_base', 18, 4)->default(0);
                $t->decimal('returned_qty_base', 18, 4)->default(0);
            });
        }
        if (!Schema::hasTable('material_settlements')) {
            Schema::create('material_settlements', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->string('document_type', 20); // grn | dc
                $t->unsignedBigInteger('document_id');
                $t->unsignedBigInteger('document_item_id');
                $t->string('settled_by_type', 20); // purchase | sale | return
                $t->unsignedBigInteger('settled_by_id');
                $t->unsignedBigInteger('settled_by_line_id')->nullable();
                $t->unsignedInteger('product_id');
                $t->decimal('qty_base', 18, 4);
                $t->decimal('value', 18, 4);
                $t->timestamp('released_at')->nullable();
                $t->timestamps();
                $t->index(['company_id', 'document_type', 'document_id'], 'material_settlements_document');
                $t->index(['company_id', 'settled_by_type', 'settled_by_id'], 'material_settlements_settled_by');
            });
        }
        $this->addAccrualAccounts();
    }

    /** Standard-chart companies get the two accrual accounts and their posting roles. */
    private function addAccrualAccounts(): void
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('semantic_account_mappings')) return;
        $template = collect(ChartOfAccountsSeeder::accounts())->keyBy('code')->only(['1260', '2060']);
        foreach (DB::table('chart_of_accounts')->whereNotNull('company_id')->distinct()->pluck('company_id') as $companyId) {
            $codes = ChartOfAccount::where('company_id', $companyId)->pluck('id', 'code')->all();
            if (!isset($codes['1000'], $codes['2000'])) continue; // not built from the standard chart
            foreach ($template as $code => $account) {
                if (isset($codes[$code])) continue;
                (new ChartOfAccount)->forceFill(['company_id' => $companyId, 'code' => $code, 'name' => $account['name'],
                    'type' => $account['type'], 'sub_type' => $account['sub_type'], 'parent_id' => $codes[$account['parent_code']] ?? null,
                    'is_system' => $account['is_system'], 'description' => $account['description'], 'is_active' => true])->save();
            }
            app(SemanticAccountResolver::class)->seedCompany((int) $companyId);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_settlements');
        foreach (['goods_received_note_items', 'delivery_challan_items'] as $table) {
            if (Schema::hasColumn($table, 'qty_base')) Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['batch_no', 'qty_base', 'value', 'billed_qty_base', 'returned_qty_base']));
        }
        foreach (['goods_received_notes', 'delivery_challans'] as $table) {
            if (!Schema::hasColumn($table, 'posted_at')) continue;
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_merge(['stock_movement_id', 'journal_entry_id', 'posting_version', 'posted_at'],
                $table === 'delivery_challans' ? ['purpose'] : [])));
        }
    }
};

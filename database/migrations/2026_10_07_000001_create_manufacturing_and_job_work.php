<?php

use App\Support\MigrationConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            'boms' => [['product_id', 'code', 'version', 'effective_from', 'output_qty', 'output_uom_id', 'status'], function (Blueprint $t) {
                $this->document($t);
                $t->unsignedInteger('product_id');
                $t->string('code', 60);
                $t->unsignedInteger('version');
                $t->date('effective_from');
                $t->date('effective_to')->nullable();
                $t->decimal('output_qty', 18, 4);
                $t->unsignedInteger('output_uom_id');
                $t->string('status', 20)->default('draft');
                $t->boolean('is_default')->default(false);
                $t->boolean('legacy_import')->default(false);
                $t->unique(['company_id', 'code', 'version']);
                $t->index(['company_id', 'product_id', 'effective_from']);
            }],
            'bom_lines' => [['bom_id', 'component_product_id', 'qty', 'uom_id', 'scrap_percent'], function (Blueprint $t) {
                $t->id(); $t->foreignId('bom_id')->constrained('boms')->restrictOnDelete();
                $t->unsignedInteger('line_no'); $t->unsignedInteger('component_product_id');
                $t->unsignedInteger('variant_id')->nullable(); $t->decimal('qty', 18, 4);
                $t->unsignedInteger('uom_id'); $t->decimal('scrap_percent', 8, 4)->default(0);
                $t->unique(['bom_id', 'line_no']);
            }],
            'production_orders' => [['bom_id', 'warehouse_id', 'reference_no', 'planned_qty', 'completed_qty', 'status'], function (Blueprint $t) {
                $this->document($t);
                $t->foreignId('bom_id')->constrained('boms')->restrictOnDelete();
                $t->unsignedInteger('warehouse_id'); $t->string('reference_no', 100);
                $t->date('business_date'); $t->date('completed_date')->nullable();
                $t->decimal('planned_qty', 18, 4); $t->decimal('completed_qty', 18, 4)->default(0);
                $t->string('status', 20)->default('planned'); $t->string('cost_method', 30)->default('weighted_average');
                $t->decimal('direct_cost', 18, 4)->default(0); $t->decimal('overhead_cost', 18, 4)->default(0);
                $t->decimal('total_cost', 18, 4)->default(0);
                $t->unsignedBigInteger('consume_movement_id')->nullable();
                $t->unsignedBigInteger('output_movement_id')->nullable();
                $t->unsignedBigInteger('scrap_movement_id')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->timestamp('reversed_at')->nullable(); $t->string('reversal_reason', 500)->nullable();
                $t->json('details_json')->nullable(); $t->unique(['company_id', 'reference_no']);
            }],
            'production_outputs' => [['production_order_id', 'product_id', 'qty', 'value'], function (Blueprint $t) {
                $t->id(); $t->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
                $t->unsignedInteger('line_no'); $t->unsignedInteger('product_id');
                $t->decimal('qty', 18, 4); $t->unsignedInteger('uom_id');
                $t->decimal('value', 18, 4); $t->json('stock_details_json')->nullable();
                $t->unique(['production_order_id', 'line_no']);
            }],
            'process_types' => [['company_id', 'code', 'name', 'max_loss_percent', 'conversion'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->string('code', 60); $t->string('name', 100); $t->string('quantity_basis', 20)->default('base_qty');
                $t->decimal('max_loss_percent', 8, 4)->default(0); $t->boolean('conversion')->default(false);
                $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique(['company_id', 'code']);
            }],
            'job_worker_roles' => [['company_id', 'supplier_id'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->unsignedInteger('supplier_id'); $t->timestamps(); $t->unique(['company_id', 'supplier_id']);
            }],
            'job_work_orders' => [['process_type_id', 'job_worker_party_id', 'external_warehouse_id', 'reference_no', 'status'], function (Blueprint $t) {
                $this->document($t);
                $t->foreignId('process_type_id')->constrained('process_types')->restrictOnDelete();
                $t->unsignedInteger('job_worker_party_id'); $t->unsignedInteger('external_warehouse_id');
                $t->string('reference_no', 100); $t->date('business_date'); $t->date('expected_return_date')->nullable();
                $t->string('status', 20)->default('open'); $t->text('notes')->nullable();
                $t->json('details_json'); $t->unique(['company_id', 'reference_no']);
            }],
            'job_work_dispatches' => [['job_work_order_id', 'warehouse_id', 'movement_id', 'reference_no'], function (Blueprint $t) {
                $this->document($t);
                $t->foreignId('job_work_order_id')->constrained('job_work_orders')->restrictOnDelete();
                $t->unsignedInteger('warehouse_id'); $t->unsignedBigInteger('movement_id')->nullable();
                $t->string('reference_no', 100); $t->date('business_date');
                $t->timestamp('reversed_at')->nullable(); $t->string('reversal_reason', 500)->nullable();
                $t->unique(['company_id', 'reference_no']);
            }],
            'job_work_dispatch_lines' => [['dispatch_id', 'product_id', 'qty_base', 'value', 'stock_details_json'], function (Blueprint $t) {
                $t->id(); $t->foreignId('dispatch_id')->constrained('job_work_dispatches')->restrictOnDelete();
                $t->unsignedInteger('line_no'); $t->unsignedInteger('product_id');
                $t->decimal('qty_base', 18, 4); $t->decimal('value', 18, 4); $t->json('stock_details_json');
                $t->unique(['dispatch_id', 'line_no']);
            }],
            'job_work_receipts' => [['dispatch_id', 'warehouse_id', 'reference_no', 'details_json'], function (Blueprint $t) {
                $this->document($t);
                $t->foreignId('dispatch_id')->constrained('job_work_dispatches')->restrictOnDelete();
                $t->unsignedInteger('warehouse_id'); $t->string('reference_no', 100); $t->date('business_date');
                $t->unsignedBigInteger('material_movement_id')->nullable();
                $t->unsignedBigInteger('output_movement_id')->nullable();
                $t->unsignedBigInteger('loss_movement_id')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->unsignedInteger('service_purchase_id')->nullable();
                $t->timestamp('reversed_at')->nullable(); $t->string('reversal_reason', 500)->nullable();
                $t->json('details_json'); $t->unique(['company_id', 'reference_no']);
            }],
            'job_work_receipt_lines' => [['receipt_id', 'dispatch_line_id', 'received_qty', 'accepted_qty', 'rejected_qty', 'loss_qty'], function (Blueprint $t) {
                $t->id(); $t->foreignId('receipt_id')->constrained('job_work_receipts')->restrictOnDelete();
                $t->foreignId('dispatch_line_id')->constrained('job_work_dispatch_lines')->restrictOnDelete();
                $t->decimal('received_qty', 18, 4); $t->decimal('accepted_qty', 18, 4);
                $t->decimal('rejected_qty', 18, 4); $t->decimal('loss_qty', 18, 4);
                $t->unique(['receipt_id', 'dispatch_line_id']);
            }],
            'operation_requests' => [['company_id', 'key', 'request_hash', 'source_type', 'source_id'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->string('key', 150); $t->string('request_hash', 64); $t->string('source_type', 40);
                $t->unsignedBigInteger('source_id'); $t->timestamp('created_at'); $t->unique(['company_id', 'key']);
            }],
            'operation_audit_events' => [['company_id', 'branch_id', 'event', 'source_id', 'details_json'], function (Blueprint $t) {
                $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
                $t->unsignedBigInteger('branch_id'); $t->unsignedInteger('user_id');
                $t->string('event', 40); $t->unsignedBigInteger('source_id'); $t->json('details_json');
                $t->timestamp('created_at'); $t->index(['company_id', 'branch_id', 'source_id']);
            }],
        ];
        // Preflight existing artifacts before any DDL; reruns resume after committed MySQL CREATEs.
        foreach ($definitions as $table => [$columns]) {
            if (Schema::hasTable($table)) MigrationConstraints::requireColumns($table, ['id', ...$columns]);
        }
        foreach ($definitions as $table => [, $create]) {
            if (!Schema::hasTable($table)) Schema::create($table, $create);
        }
        foreach (['warehouses' => ['external_job_order_id'], 'productions' => ['company_id', 'branch_id', 'financial_year_id', 'production_order_id']] as $table => $columns) {
            if (!Schema::hasTable($table)) continue;
            foreach ($columns as $column) if (!Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger($column)->nullable()->index());
            }
        }
        if (Schema::hasTable('permissions')) foreach (['manufacturing.read', 'manufacturing.bom.manage', 'manufacturing.production.post', 'manufacturing.production.reverse',
            'job_work.read', 'job_work.manage', 'job_work.dispatch', 'job_work.receive', 'job_work.reverse', 'profiles.manage', 'projects.read', 'projects.manage', 'projects.install', 'projects.warranty'] as $permission) {
            DB::table('permissions')->updateOrInsert(['name' => $permission, 'guard_name' => 'web'], ['updated_at' => now()]);
        }
    }

    private function document(Blueprint $t): void
    {
        $t->id(); $t->foreignId('company_id')->constrained('companies')->restrictOnDelete();
        $t->unsignedBigInteger('branch_id'); $t->unsignedBigInteger('financial_year_id');
        $t->unsignedInteger('created_by'); $t->timestamp('posted_at')->nullable(); $t->timestamps();
        $t->index(['company_id', 'branch_id', 'financial_year_id']);
    }

    public function down(): void
    {
        throw new RuntimeException('Operational history requires a reviewed, data-preserving forward rollback.');
    }
};

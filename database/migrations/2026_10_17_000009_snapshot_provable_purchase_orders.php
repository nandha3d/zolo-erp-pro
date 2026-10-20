<?php

use App\Models\Purchase;
use App\Services\Commercial\OrderLifecycleService;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('commercial_order_items') || !Schema::hasColumn('purchases', 'attributes_json')) return;
        Purchase::withoutGlobalScopes()->whereNull('deleted_at')->where('status', 4)->whereNotNull('company_id')->whereNull('reversed_at')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                try {
                    DB::transaction(function () use ($order) {
                        $warehouse = DB::table('warehouses')->where('company_id', $order->company_id)->where('id', $order->warehouse_id)->first();
                        $yearIds = DB::table('fiscal_years')->where('company_id', $order->company_id)->where('start_date', '<=', $order->created_at->toDateString())
                            ->where('end_date', '>=', $order->created_at->toDateString())->pluck('id');
                        if (!$warehouse?->branch_id || $yearIds->count() !== 1 || ($order->branch_id && (int) $order->branch_id !== (int) $warehouse->branch_id)
                            || ($order->financial_year_id && (int) $order->financial_year_id !== (int) $yearIds[0])) throw new RuntimeException('Legacy order context is ambiguous.');
                        $order->forceFill(['branch_id' => $warehouse->branch_id, 'financial_year_id' => $yearIds[0]])->save();
                        app(OrderLifecycleService::class)->backfillPurchase($order, new CompanyContext((int) $order->company_id, (int) $warehouse->branch_id, (int) $yearIds[0]));
                    });
                } catch (\Illuminate\Validation\ValidationException|\InvalidArgumentException|\RuntimeException $error) {
                    // No stock, journal, quantity or document number is changed. A failed snapshot rolls back in full.
                    $attributes = json_decode((string) DB::table('purchases')->where('id', $order->id)->value('attributes_json'), true) ?: [];
                    $attributes['order_migration_review_required'] = true;
                    DB::table('purchases')->where('id', $order->id)->update(['attributes_json' => json_encode($attributes, JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    public function down(): void
    {
        // Snapshots are audit history. Retain them; the schema migration refuses destructive rollback once used.
    }
};

<?php

namespace Tests\Feature;

use App\Models\{DeliveryChallan, GoodsReceivedNote, Product};
use App\Services\Commercial\{CommercialApplicationService, ReturnService};
use App\Services\Inventory\{InventoryMovementService, StockLine, StockMovementCommand};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\Support\ReturnsDocumentTestCase;

class MaterialReturnAllocationTest extends ReturnsDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('warehouses', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        (require database_path('migrations/2026_10_18_000011_add_material_item_variants.php'))->up();
        foreach (['goods_delivered_not_invoiced' => 'asset', 'goods_received_not_invoiced' => 'liability'] as $role => $type) {
            $account = \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id,
                'code' => strtoupper($role), 'name' => $role, 'type' => $type, 'sub_type' => $role]);
            DB::table('semantic_account_mappings')->updateOrInsert(['company_id' => $this->company->id, 'semantic_role' => $role],
                ['account_id' => $account->id, 'is_active' => true, 'account_code' => $account->code, 'account_name' => $role, 'category' => 'core']);
        }
    }

    public function test_merged_grn_bill_returns_only_its_settled_quantities_at_each_source_cost(): void
    {
        $this->assertSettlementReturns('purchase');
    }

    public function test_merged_dc_invoice_returns_only_its_settled_quantities_at_each_source_cost(): void
    {
        $this->assertSettlementReturns('sale');
    }

    private function assertSettlementReturns(string $kind): void
    {
        $product = $this->gstProduct();
        $product->forceFill(['qty' => 0, 'is_batch' => true])->save();
        DB::table('product_warehouse')->where('product_id', $product->id)->update(['qty' => 0]);
        DB::table('product_batches')->insert(['company_id' => $this->company->id, 'product_id' => $product->id,
            'batch_no' => 'SAME-LOT', 'qty' => 0, 'expired_date' => '2027-12-31', 'status' => 'active']);
        $sources = [];
        foreach ([5, 9] as $cost) {
            if ($kind === 'sale') {
                app(InventoryMovementService::class)->receive(new StockMovementCommand('2026-10-03',
                    [new StockLine($product->id, 10, unitCost: $cost, batch: ['batch_no' => 'SAME-LOT', 'expired_date' => '2027-12-31'])],
                    1, context: $this->context(), userId: 1));
            }
            $sources[] = $this->material($kind, $product, $cost);
        }
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'SAME-LOT')->value('id');
        $sourceField = $kind === 'sale' ? 'delivery_challan_ids' : 'goods_received_note_ids';
        $data = [$kind === 'sale' ? 'customer_id' : 'supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            $sourceField => array_map(fn ($source) => $source->id, $sources), 'items' => [[
                'product_id' => $product->id, 'product_batch_id' => $batch, 'qty' => 10,
                $kind === 'sale' ? 'net_unit_price' : 'net_unit_cost' => $kind === 'sale' ? 10 : 8.2,
                'material_sources' => array_map(fn ($source, $qty) => ['document_id' => $source->id,
                    'item_id' => $source->items()->first()->id, 'qty_base' => $qty], $sources, [2, 8]),
            ]]];
        $bill = app(CommercialApplicationService::class)->create($kind, $data, 'merged-'.$kind, 1, $this->context());
        $line = ($kind === 'sale' ? $bill->productSales() : $bill->productPurchases())->first();
        $this->assertEquals([2, 8], DB::table('material_settlements')->where('settled_by_type', $kind)
            ->where('settled_by_id', $bill->id)->orderBy('id')->pluck('qty_base')->map(fn ($qty) => (float) $qty)->all());
        $service = app(ReturnService::class);
        $returnData = ['business_date' => '2026-10-04', 'reason' => 'Merged source quality return',
            'note_type' => $kind === 'sale' ? 'credit' : 'debit', 'adjustment_type' => 'quantity'];
        $allChunks = [];
        foreach ([6, 4] as $index => $qty) {
            $pending = $service->create($kind, $bill->id, $returnData + ['items' => [['line_id' => $line->id, 'qty' => $qty]]],
                'merged-return-'.$kind.'-'.$index, $this->context(), 1);
            $note = $service->approve($kind, $pending->id, $this->context(), 1)->load('products');
            $chunks = $note->products->first()->stock_details_json['chunks'];
            $this->assertEquals($index === 0 ? [2, 4] : [4], array_column($chunks, 'qty'));
            $this->assertEquals($index === 0 ? [5, 9] : [9], array_column($chunks, 'unit_cost'));
            $this->assertEquals([$batch], array_values(array_unique(array_column($chunks, 'batch_id'))));
            $allChunks = array_merge($allChunks, $chunks);
            $this->assertSame($note->id, $service->approve($kind, $note->id, $this->context(), 1)->id);
        }
        $this->assertEquals(82, array_sum(array_map(fn ($chunk) => $chunk['qty'] * $chunk['unit_cost'], $allChunks)));
        $this->assertEquals([2, 8], collect($allChunks)->groupBy('source_stock_line_id')->map(fn ($chunks) => $chunks->sum('qty'))->values()->all());
        if ($kind === 'sale') {
            $this->assertEquals(82, DB::table('stock_movement_lines as line')->join('stock_movements as movement', 'movement.id', '=', 'line.stock_movement_id')
                ->where('movement.source_type', 'sale_credit_note')->sum('line.value'));
        }
        $count = DB::table('stock_movements')->count();
        try {
            $service->create($kind, $bill->id, $returnData + ['items' => [['line_id' => $line->id, 'qty' => 1]]],
                'over-return-'.$kind, $this->context(), 1);
            $this->fail('A fully returned settlement was reused.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('qty', $error->errors());
        }
        $this->assertSame($count, DB::table('stock_movements')->count());
    }

    private function material(string $kind, Product $product, float $cost): DeliveryChallan|GoodsReceivedNote
    {
        $sale = $kind === 'sale';
        $response = $this->postJson($sale ? '/delivery-challans' : '/goods-received-notes', [
            $sale ? 'challan_date' : 'grn_date' => '2026-10-03', $sale ? 'customer_id' : 'supplier_id' => 1,
            'warehouse_id' => 1, 'purpose' => 'approval', 'product_id' => [$product->id], 'unit_id' => [1],
            'qty' => [10], $sale ? 'rate' : 'cost' => [$sale ? 10 : $cost], 'batch_no' => ['SAME-LOT'],
        ]);
        $this->assertSame(201, $response->status(), $response->getContent());
        return $sale ? DeliveryChallan::findOrFail($response->json('challan.id')) : GoodsReceivedNote::findOrFail($response->json('grn.id'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\DeliveryChallan;
use App\Models\GoodsReceivedNote;
use App\Models\Product;
use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\CommercialReversalService;
use App\Services\Commercial\MaterialDocumentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommercialTestCase;

class MaterialDocumentIdentityTest extends CommercialTestCase
{
    public function test_existing_lot_orders_are_delivered_through_material_documents_with_exact_identity(): void
    {
        $product = $this->variantProduct(); $this->grn($product);
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        foreach (['sale', 'purchase'] as $kind) {
            $data = [$kind === 'sale' ? 'customer_id' : 'supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
                'items' => [['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $batch, 'qty' => 2,
                    $kind === 'sale' ? 'sale_unit_id' : 'purchase_unit_id' => 1, $kind === 'sale' ? 'net_unit_price' : 'net_unit_cost' => 10]]];
            $order = $kind === 'sale' ? app(\App\Services\Commercial\OrderLifecycleService::class)->createSale($data, 'lot-sale-order', $this->context(), 1)
                : app(CommercialApplicationService::class)->create('purchase', ['status' => 4] + $data, 'lot-purchase-order', 1, $this->context());
            $item = \App\Models\CommercialOrderItem::where('order_kind', $kind)->where('order_id', $order->id)->firstOrFail();
            $response = $this->postJson($kind === 'sale' ? '/delivery-challans' : '/goods-received-notes', [
                $kind === 'sale' ? 'customer_id' : 'supplier_id' => 1, 'warehouse_id' => 1, 'purpose' => 'approval',
                $kind === 'sale' ? 'challan_date' : 'grn_date' => '2026-10-03',
                $kind === 'sale' ? 'sales_order_id' : 'purchase_order_id' => $order->id,
                'order_item_id' => [$item->id], 'product_id' => [$product->id], 'variant_id' => [101], 'batch_no' => ['RED-LOT'],
                'unit_id' => [1], 'qty' => [2], $kind === 'sale' ? 'rate' : 'cost' => [10]]);
            $this->assertSame(201, $response->status(), $response->getContent());
            $remaining = app(\App\Services\Commercial\OrderLifecycleService::class)->remaining($kind, $order->id, $this->context())['items'][0];
            $this->assertEquals(2, $remaining['delivered_qty_base']); $this->assertEquals(0, $remaining['billed_qty_base']);
        }
    }

    public function test_material_purchase_history_uses_full_frozen_factor_when_half_remains_in_transit(): void
    {
        $product = $this->variantProduct(); $grn = $this->grn($product); $red = $grn->items()->orderBy('id')->first();
        $bill = app(CommercialApplicationService::class)->create('purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'status' => 2, 'goods_received_note_id' => $grn->id, 'items' => [['product_id' => $product->id, 'variant_id' => 101,
                'batch' => ['batch_no' => 'RED-LOT'], 'purchase_unit_id' => 2, 'qty' => 2, 'received_qty' => 1, 'net_unit_cost' => 60,
                'material_sources' => [['document_id' => $grn->id, 'item_id' => $red->id, 'qty_base' => 12]]]]], 'partial-material-history', 1, $this->context());
        $this->getJson('/commercial/purchase/previous-rates?party_id=1&product_id='.$product->id)->assertOk()->assertJsonPath('data.0.base_factor', 12);
        $line = $bill->productPurchases()->first(); $details = $line->stock_details_json; unset($details['base_factor']);
        DB::table('product_purchases')->where('id', $line->id)->update(['stock_details_json' => json_encode($details)]);
        $this->getJson('/commercial/purchase/previous-rates?party_id=1&product_id='.$product->id)->assertOk()->assertJsonPath('data.0.base_factor', 12);
    }

    public function test_material_billing_refuses_contradictory_lot_id_and_name_before_settlement(): void
    {
        $product = $this->variantProduct(); $grn = $this->grn($product); $red = $grn->items()->orderBy('id')->first();
        $blue = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'BLUE-LOT')->value('id');
        $this->postJson('/commercial/purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'goods_received_note_id' => $grn->id,
            'items' => [['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $blue, 'batch' => ['batch_no' => 'RED-LOT'],
                'purchase_unit_id' => 1, 'qty' => 1, 'net_unit_cost' => 5, 'material_sources' => [['document_id' => $grn->id, 'item_id' => $red->id, 'qty_base' => 1]]]]],
            ['Idempotency-Key' => 'conflicting-lot'])->assertUnprocessable();
        $this->assertSame(0, DB::table('material_settlements')->count()); $this->assertSame(0, DB::table('purchases')->count());
    }

    public function test_receipt_and_order_refuse_changed_unit_conversion_before_any_effect(): void
    {
        $product = $this->variantProduct(); $this->grn($product);
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        $line = ['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $batch, 'qty' => 2];
        $bill = app(CommercialApplicationService::class)->create('purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'status' => 2,
            'items' => [$line + ['purchase_unit_id' => 2, 'received_qty' => 0, 'net_unit_cost' => 60]]], 'conversion-pending', 1, $this->context());
        $order = app(\App\Services\Commercial\OrderLifecycleService::class)->createSale(['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03',
            'items' => [$line + ['sale_unit_id' => 2, 'net_unit_price' => 120]]], 'conversion-order', $this->context(), 1);
        DB::table('product_uom_conversions')->where('product_id', $product->id)->update(['factor' => 10]);
        $stored = $bill->productPurchases()->first();
        $this->postJson('/commercial/purchase/'.$bill->id.'/receipts', ['business_date' => '2026-10-03', 'items' => [['line_id' => $stored->id, 'qty' => 0.5]]],
            ['Idempotency-Key' => 'changed-conversion-receipt'])->assertUnprocessable();
        $this->postJson('/commercial/sale', ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'sales_order_id' => $order->id,
            'items' => [['qty' => 1, 'order_item_id' => $order->items()->first()->id] + $line + ['sale_unit_id' => 2, 'net_unit_price' => 120]]],
            ['Idempotency-Key' => 'changed-conversion-sale'])->assertUnprocessable();
        $this->assertEquals(0, $stored->fresh()->recieved); $this->assertSame(0, DB::table('sales')->count());
        $this->assertEquals(12, $this->quantity($product, 101));
    }

    public function test_later_receipt_rejects_variant_or_lot_change_and_keeps_the_bill_identity(): void
    {
        $product = $this->variantProduct(); $this->grn($product);
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        $bill = app(CommercialApplicationService::class)->create('purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'status' => 2,
            'items' => [['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $batch, 'purchase_unit_id' => 1, 'qty' => 2, 'received_qty' => 0, 'net_unit_cost' => 5]]], 'frozen-receipt', 1, $this->context());
        $line = $bill->productPurchases()->first();
        foreach ([['variant_id' => 102], ['batch' => ['batch_no' => 'BLUE-LOT']]] as $index => $changed) {
            $this->postJson('/commercial/purchase/'.$bill->id.'/receipts', ['business_date' => '2026-10-03', 'items' => [['line_id' => $line->id, 'qty' => 1] + $changed]],
                ['Idempotency-Key' => 'wrong-receipt-'.$index])->assertUnprocessable();
        }
        $this->assertEquals(0, $line->fresh()->recieved);
        $this->postJson('/commercial/purchase/'.$bill->id.'/receipts', ['business_date' => '2026-10-03', 'items' => [['line_id' => $line->id, 'qty' => 1]]],
            ['Idempotency-Key' => 'right-receipt'])->assertOk();
        $this->assertEquals(13, $this->quantity($product, 101)); $this->assertEquals(2, $this->quantity($product, 102));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('warehouses', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        Schema::create('variants', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->string('name'); $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedBigInteger('company_id'); $t->unsignedInteger('product_id'); $t->unsignedInteger('variant_id');
            $t->integer('position')->default(1); $t->string('item_code'); $t->decimal('qty', 18, 4)->default(0);
            $t->decimal('additional_cost', 18, 4)->nullable(); $t->decimal('additional_price', 18, 4)->nullable(); $t->timestamps();
        });
        Schema::create('product_batches', function (Blueprint $t) {
            $t->increments('id'); $t->unsignedInteger('product_id'); $t->unsignedBigInteger('company_id');
            $t->string('batch_no'); $t->decimal('qty', 18, 4)->default(0); $t->date('expired_date')->nullable();
            $t->date('mfg_date')->nullable(); $t->decimal('mrp', 18, 4)->nullable(); $t->string('status')->default('active'); $t->timestamps();
        });
        (require database_path('migrations/2026_10_13_000004_create_optech_dc_and_grn_tables.php'))->up();
        (require database_path('migrations/2026_10_17_000007_post_material_documents_physically.php'))->up();
        (require database_path('migrations/2026_10_18_000011_add_material_item_variants.php'))->up();
        foreach ([['GRNI', 'liability', 'goods_received_not_invoiced'], ['GDNI', 'asset', 'goods_delivered_not_invoiced'], ['VAR', 'expense', 'inventory_variance']] as [$code, $type, $subType]) {
            \App\Models\Accounting\ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => $code, 'name' => $subType, 'type' => $type, 'sub_type' => $subType]);
        }
        app(\App\Services\Accounting\SemanticAccountResolver::class)->seedCompany($this->company->id);
        DB::table('units')->insert(['id' => 2, 'company_id' => $this->company->id, 'name' => 'Dozen', 'unit_name' => 'DOZ', 'unit_code' => 'DOZ']);
    }

    private function variantProduct(): Product
    {
        $product = $this->stock(0); $product->forceFill(['unit_id' => 1, 'is_variant' => true])->save();
        foreach ([101 => 'Red', 102 => 'Blue'] as $id => $name) {
            DB::table('variants')->insert(['id' => $id, 'company_id' => $this->company->id, 'name' => $name]);
            DB::table('product_variants')->insert(['company_id' => $this->company->id, 'product_id' => $product->id, 'variant_id' => $id, 'item_code' => 'TEXTILE-'.$id]);
        }
        DB::table('product_uom_conversions')->insert(['company_id' => $this->company->id, 'product_id' => $product->id, 'from_uom_id' => 2, 'to_uom_id' => 1, 'factor' => 12, 'rounding_scale' => 4]);
        return $product;
    }

    private function grn(Product $product): GoodsReceivedNote
    {
        $response = $this->postJson('/goods-received-notes', ['grn_date' => '2026-10-03', 'supplier_id' => 1, 'warehouse_id' => 1,
            'product_id' => [$product->id, $product->id], 'variant_id' => [101, 102], 'unit_id' => [2, 1], 'qty' => [1, 2], 'cost' => [60, 5], 'batch_no' => ['RED-LOT', 'BLUE-LOT']]);
        $this->assertSame(201, $response->status(), $response->getContent());
        return GoodsReceivedNote::findOrFail($response->json('grn.id'));
    }

    private function quantity(Product $product, int $variant): float
    {
        return (float) DB::table('stock_movement_lines')->where('product_id', $product->id)->where('variant_id', $variant)->sum('qty_base');
    }

    public function test_grn_variant_lot_and_unit_survive_billing_partial_return_and_reversal(): void
    {
        $product = $this->variantProduct(); $grn = $this->grn($product); $red = $grn->items()->orderBy('id')->first();
        $this->getJson('/goods-received-notes/'.$grn->id)->assertOk()->assertJsonPath('items.0.variant_id', 101)->assertJsonPath('items.0.unit_id', 2)->assertJsonPath('items.0.batch_no', 'RED-LOT');
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        $bill = app(CommercialApplicationService::class)->create('purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'goods_received_note_ids' => [$grn->id],
            'items' => [['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $batch, 'purchase_unit_id' => 1, 'qty' => 6, 'net_unit_cost' => 5,
                'material_sources' => [['document_id' => $grn->id, 'item_id' => $red->id, 'qty_base' => 6]]]]], 'variant-grn-bill', 1, $this->context());
        $this->assertEquals(12, $this->quantity($product, 101)); $this->assertEquals(2, $this->quantity($product, 102));
        DB::transaction(fn () => app(MaterialDocumentService::class)->returnOpen($grn, [$red->id => 0.25], '2026-10-03', 'Partial red lot return', $this->context(), 1, 'variant-return'));
        $this->assertEquals(9, $this->quantity($product, 101)); $this->assertEquals(2, $this->quantity($product, 102));
        $this->assertEquals(0.25, app(MaterialDocumentService::class)->openQuantities($grn->fresh())[$red->id]);
        app(CommercialReversalService::class)->reverse($bill, '2026-10-03', 'Incorrect variant bill', $this->context());
        $this->assertEquals(9, $this->quantity($product, 101));
        $this->assertEquals(0.75, app(MaterialDocumentService::class)->openQuantities($grn->fresh())[$red->id]);
    }

    public function test_dc_variant_stock_is_issued_once_and_return_restores_only_the_same_variant(): void
    {
        $product = $this->variantProduct(); $this->grn($product);
        $response = $this->postJson('/delivery-challans', ['challan_date' => '2026-10-03', 'customer_id' => 1, 'warehouse_id' => 1, 'purpose' => 'approval',
            'product_id' => [$product->id], 'variant_id' => [101], 'unit_id' => [1], 'qty' => [4], 'rate' => [10], 'batch_no' => ['RED-LOT']]);
        $this->assertSame(201, $response->status(), $response->getContent());
        $dc = DeliveryChallan::findOrFail($response->json('challan.id')); $item = $dc->items()->first();
        $this->assertEquals(8, $this->quantity($product, 101));
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        app(CommercialApplicationService::class)->create('sale', ['customer_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'delivery_challan_id' => $dc->id,
            'items' => [['product_id' => $product->id, 'variant_id' => 101, 'product_batch_id' => $batch, 'sale_unit_id' => 1, 'qty' => 3, 'net_unit_price' => 10]]], 'variant-dc-invoice', 1, $this->context());
        $this->assertEquals(8, $this->quantity($product, 101));
        DB::transaction(fn () => app(MaterialDocumentService::class)->returnOpen($dc, [$item->id => 1], '2026-10-03', 'One red piece returned', $this->context(), 1, 'variant-dc-return'));
        $this->assertEquals(9, $this->quantity($product, 101)); $this->assertEquals(2, $this->quantity($product, 102));
    }

    public function test_cross_variant_source_allocation_fails_atomically_even_for_the_same_product_and_lot(): void
    {
        $product = $this->variantProduct(); $grn = $this->grn($product); $red = $grn->items()->orderBy('id')->first();
        $batch = DB::table('product_batches')->where('product_id', $product->id)->where('batch_no', 'RED-LOT')->value('id');
        $this->postJson('/commercial/purchase', ['supplier_id' => 1, 'warehouse_id' => 1, 'business_date' => '2026-10-03', 'goods_received_note_id' => $grn->id,
            'items' => [['product_id' => $product->id, 'variant_id' => 102, 'product_batch_id' => $batch, 'purchase_unit_id' => 1, 'qty' => 1, 'net_unit_cost' => 5,
                'material_sources' => [['document_id' => $grn->id, 'item_id' => $red->id, 'qty_base' => 1]]]]], ['Idempotency-Key' => 'wrong-variant'])->assertUnprocessable();
        $this->assertSame(0, DB::table('purchases')->count()); $this->assertSame(0, DB::table('material_settlements')->count());
        $this->assertEquals(12, $this->quantity($product, 101)); $this->assertEquals(2, $this->quantity($product, 102));
    }

    public function test_variant_is_required_and_must_belong_to_the_selected_product_and_migration_is_replay_safe(): void
    {
        $product = $this->variantProduct();
        $base = ['grn_date' => '2026-10-03', 'supplier_id' => 1, 'warehouse_id' => 1, 'product_id' => [$product->id], 'unit_id' => [1], 'qty' => [1], 'cost' => [5]];
        $this->postJson('/goods-received-notes', $base)->assertUnprocessable()->assertJsonValidationErrors('variant_id.0');
        $this->postJson('/goods-received-notes', ['variant_id' => [999]] + $base)->assertUnprocessable();
        $this->assertSame(0, DB::table('goods_received_notes')->count());
        $this->grn($product);
        (require database_path('migrations/2026_10_18_000011_add_material_item_variants.php'))->up();
        $this->assertEquals(101, DB::table('goods_received_note_items')->orderBy('id')->value('variant_id'));
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_18_000011_add_material_item_variants.php'))->down();
    }
}

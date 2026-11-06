<?php

namespace Tests\Feature;

use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommercialTestCase;

class ProductUsageTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_06_000001_add_product_usage_to_products.php'))->up();
    }

    public function test_bill_search_includes_its_own_usage_and_composite(): void
    {
        $purchase = $this->stock();
        $purchase->update(['product_usage' => 'purchase']);
        $sale = $this->stock();
        $sale->update(['product_usage' => 'sale']);
        $composite = $this->stock();
        $this->assertSame('composite', $composite->fresh()->product_usage);

        foreach (['sale' => $sale, 'purchase' => $purchase] as $kind => $ownProduct) {
            $expected = [$ownProduct->id, $composite->id];
            $response = $this->getJson("/commercial/{$kind}/search/products")->assertOk();
            $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'id'));
            $controller = app($kind === 'sale' ? SaleController::class : PurchaseController::class);
            $this->assertEqualsCanonicalizing($expected, $controller->productWithoutVariant()->modelKeys());
        }
    }

    public function test_shared_and_legacy_bill_submission_reject_incompatible_items(): void
    {
        $purchase = $this->stock();
        $purchase->update(['product_usage' => 'purchase']);
        $sale = $this->stock();
        $sale->update(['product_usage' => 'sale']);

        $this->postJson('/commercial/sale/preview', $this->saleData($purchase))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/commercial/purchase/preview', $this->purchaseData($sale))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/sales', $this->saleData($purchase), ['Idempotency-Key' => 'blocked-sale'])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/purchases', $this->purchaseData($sale), ['Idempotency-Key' => 'blocked-purchase'])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/sales', $this->saleData($sale), ['Idempotency-Key' => 'allowed-sale'])->assertCreated();
        $this->postJson('/purchases', $this->purchaseData($purchase), ['Idempotency-Key' => 'allowed-purchase'])->assertCreated();

        config(['commercial.enabled' => false]);
        $this->postJson('/sales', ['products' => [['id' => $purchase->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->postJson('/purchases', ['product_id' => [$sale->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_quick_create_persists_usage_and_rejects_unknown_values(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['brand_id', 'profit_margin_type', 'profit_margin', 'wholesale_price', 'daily_sale_objective',
                'warranty', 'warranty_type', 'guarantee', 'guarantee_type', 'featured', 'is_embeded', 'product_details'] as $column) {
                $table->string($column)->nullable();
            }
        });
        $data = ['name' => 'Purchase input', 'code' => 'PURCHASE-INPUT', 'unit_id' => 1, 'category_id' => 1];
        $this->postJson('/products/quick-store', $data + ['product_usage' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('product_usage');

        $id = $this->postJson('/products/quick-store', $data + ['product_usage' => 'purchase'])
            ->assertSuccessful()->json('product.id');
        $this->assertSame('purchase', Product::findOrFail($id)->product_usage);
    }
}

<?php

namespace Tests\Feature;

use App\Services\Commercial\CommercialApplicationService;
use App\Services\Commercial\CommercialReversalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommercialTestCase;

class TextileEntryContractTest extends CommercialTestCase
{
    public function test_bale_default_uses_allocated_reference_and_preserves_override_on_replacement(): void
    {
        Schema::create('company_industry_settings', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('profile_key'); $t->string('subtype'); $t->json('settings_json'); });
        DB::table('company_industry_settings')->insert(['company_id' => $this->company->id, 'profile_key' => 'textile', 'subtype' => 'trading', 'settings_json' => '{}']);
        $product = $this->stock(100); $app = app(CommercialApplicationService::class);
        $default = $app->create('sale', $this->saleData($product), 'bale-default', 1, $this->context());
        $this->assertSame($default->reference_no, $default->attributes_json['bale_no']);
        $explicit = $app->create('sale', $this->saleData($product, ['bale_no' => 'OPERATOR-BALE', 'transport_name' => 'Textile carrier']), 'bale-override', 1, $this->context());
        $this->assertSame('OPERATOR-BALE', $explicit->attributes_json['bale_no']);
        $replacement = app(CommercialReversalService::class)->replace($explicit, $this->saleData($product, ['bale_no' => 'OPERATOR-BALE', 'transport_name' => 'Textile carrier']), 'bale-replace', '2026-10-03', 'Correct details', $this->context());
        $this->assertSame('OPERATOR-BALE', $replacement->attributes_json['bale_no']);
        $this->assertSame('Textile carrier', $replacement->attributes_json['transport_name']);
        $copy = $this->getJson('/commercial/sale/clone/'.$replacement->id)->assertOk()->json('data');
        $this->assertArrayNotHasKey('bale_no', $copy, 'A copy derives a new reference default.');
        $new = $app->create('sale', $this->saleData($product), 'bale-copy', 1, $this->context());
        $this->assertNotSame($default->attributes_json['bale_no'], $new->attributes_json['bale_no']);
        $this->assertSame($new->reference_no, $new->attributes_json['bale_no']);
    }

    public function test_statement_keeps_cash_movements_while_pending_only_shows_open_bills_and_replacement_excludes_itself(): void
    {
        $product = $this->stock(100); $app = app(CommercialApplicationService::class);
        $first = $app->create('sale', $this->saleData($product), 'balance-first', 1, $this->context());
        $app->create('sale', $this->saleData($product, ['items' => [['product_id' => $product->id, 'qty' => 2, 'net_unit_price' => 10]],
            'paid_amount' => 5, 'paying_method' => 'Cash', 'account_id' => 1]), 'balance-partial', 1, $this->context());
        $app->create('sale', $this->saleData($product, ['paid_amount' => 20, 'paying_method' => 'Cash', 'account_id' => 1]), 'balance-cash', 1, $this->context());
        $this->getJson('/commercial/sale/party/1?pending=1&exclude_id='.$first->id)->assertOk()
            ->assertJsonPath('data.previous', 15)->assertJsonPath('data.outstanding', 35)->assertJsonCount(2, 'data.items')->assertJsonPath('data.balance_kind', 'Receivable');
        $this->postJson('/commercial/sale/'.$first->id.'/payments', ['amount' => 3, 'paying_method' => 'Cash',
            'account_id' => 1, 'business_date' => '2026-10-03'], ['Idempotency-Key' => 'statement-payment'])->assertCreated();
        $statement = $this->getJson('/commercial/sale/party/1?statement=1')->assertOk()->json('data');
        $this->assertCount(3, $statement['items']);
        $this->assertEquals(32, array_sum(array_column($statement['items'], 'original_amount')));
        app(CommercialReversalService::class)->reverse($first, '2026-10-03', 'Cancel first bill', $this->context());
        $this->getJson('/commercial/sale/party/1?pending=1')->assertOk()->assertJsonPath('data.previous', 15)->assertJsonCount(1, 'data.items');
    }
}

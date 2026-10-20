<?php

namespace Tests\Feature;

use App\Http\Controllers\QuotationController;
use App\Models\Quotation;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommercialTestCase;

class QuotationFollowUpTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('quotations', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedBigInteger('company_id');
            foreach (['user_id', 'biller_id', 'supplier_id', 'customer_id', 'warehouse_id'] as $column) $table->unsignedInteger($column)->nullable();
            $table->string('reference_no'); $table->integer('quotation_status')->default(1);
            foreach (['total_qty', 'total_tax', 'total_discount', 'total_price', 'order_tax', 'order_tax_rate', 'order_discount', 'shipping_cost', 'grand_total'] as $column) $table->decimal($column, 18, 4)->default(0);
            $table->text('note')->nullable(); $table->string('document')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_10_19_000013_add_quotation_follow_up.php'))->up();
        (require database_path('migrations/2026_10_19_000016_track_quotation_conversion.php'))->up();
    }

    private function quote(string $reference, ?string $followUp, string $created = '2026-10-03'): Quotation
    {
        return Quotation::forceCreate(['company_id' => $this->company->id, 'user_id' => 1, 'biller_id' => 1,
            'customer_id' => 1, 'warehouse_id' => 1, 'reference_no' => $reference, 'next_follow_up' => $followUp,
            'note' => '', 'created_at' => $created.' 10:00:00']);
    }

    private function register(array $filters): array
    {
        $request = Request::create('/quotations/quotation-data', 'POST', $filters + ['all_permission' => [], 'length' => 25]);
        $request->attributes->set(CompanyContext::class, $this->context());
        $this->app->instance('request', $request);
        return app(QuotationController::class)->quotationData($request)->getData(true);
    }

    public function test_follow_up_changes_are_audited_and_conversion_preserves_the_date(): void
    {
        $quote = $this->quote('Q-AUDIT', '2026-10-05');
        DB::transaction(fn () => $quote->update(['next_follow_up' => '2026-10-09']));
        $quote->update(['quotation_status' => 2]);
        $this->assertSame('2026-10-09', $quote->fresh()->next_follow_up->toDateString());
        $this->assertSame(2, DB::table('quotation_follow_ups')->count());
        DB::transaction(fn () => $quote->update(['next_follow_up' => null]));
        $last = DB::table('quotation_follow_ups')->orderByDesc('id')->first();
        $this->assertSame('2026-10-09', $last->previous_date);
        $this->assertNull($last->next_date);
        $this->assertSame($this->company->id, $last->company_id);
        $this->assertSame(1, $last->actor_id);
    }

    public function test_follow_up_search_remains_inside_dates_company_and_staff_filters(): void
    {
        $this->quote('Q-MATCH', '2026-10-02');
        $this->quote('Q-MATCH-OLD', '2026-10-02', '2026-09-01');
        $future = $this->quote('Q-MATCH-FUTURE', '2026-11-01');
        $foreign = $future->replicate(); $foreign->company_id = $this->other->id; $foreign->reference_no = 'Q-MATCH-FOREIGN'; $foreign->save();
        $result = $this->register(['starting_date' => '2026-10-01', 'ending_date' => '2026-10-31',
            'follow_up_to' => '2026-10-03', 'search' => ['value' => 'Q-MATCH'], 'order' => [['column' => 999, 'dir' => 'desc']]]);
        $this->assertSame(1, $result['recordsTotal']);
        $this->assertSame(1, $result['recordsFiltered']);
        $this->assertSame('Q-MATCH', $result['data'][0]['reference_no']);
        $this->assertSame('2026-10-02', $result['data'][0]['next_follow_up']);
        $this->assertSame(0, $this->register(['follow_up_due' => 1, 'search' => ['value' => 'FOREIGN']])['recordsFiltered']);
    }

    public function test_follow_up_date_is_validated_before_quotation_writes(): void
    {
        $request = Request::create('/quotations', 'POST', ['next_follow_up' => 'invalid']);
        try { app(QuotationController::class)->store($request); $this->fail('Expected invalid follow-up date rejection.'); }
        catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('next_follow_up', $error->errors()); }
        $this->assertSame(0, DB::table('quotations')->count());
    }

    public function test_register_escapes_names_and_returns_structured_modal_details(): void
    {
        $quote = $this->quote('<img src=x onerror=alert(1)>', '2026-10-03');
        $quote->update(['note' => 'Quoted "text" and <script>alert(1)</script>']);
        $row = $this->register([])['data'][0];
        $this->assertStringStartsWith('&lt;img', $row['reference_no']);
        $this->assertIsArray($row['quotation']);
        $this->assertSame($quote->id, $row['quotation'][13]);
        $this->assertStringContainsString('&lt;script&gt;', $row['quotation'][22]);
    }

    public function test_stale_editors_preserve_actual_follow_up_transition_history(): void
    {
        $quote = $this->quote('Q-RACE', '2026-10-05');
        $first = $quote->fresh(); $second = $quote->fresh();
        $first->revise(['next_follow_up' => '2026-10-09']);
        $second->revise(['next_follow_up' => '2026-10-11']);
        $last = DB::table('quotation_follow_ups')->orderByDesc('id')->first();
        $this->assertSame('2026-10-09', $last->previous_date);
        $this->assertSame('2026-10-11', $last->next_date);
        $this->assertSame('2026-10-11', $quote->fresh()->next_follow_up->toDateString());
    }

    public function test_staff_search_cannot_escape_own_document_filter(): void
    {
        $this->quote('Q-OWN', '2026-10-03');
        $other = $this->quote('Q-OTHER', '2026-10-03');
        $other->update(['user_id' => 2]);
        auth()->user()->role_id = 3;
        config(['staff_access' => 'own']);
        $result = $this->register(['search' => ['value' => 'Q-OTHER']]);
        $this->assertSame(1, $result['recordsTotal']);
        $this->assertSame(0, $result['recordsFiltered']);
    }

    public function test_actual_invoice_conversion_retains_follow_up_and_audit(): void
    {
        $quote = $this->quote('Q-CONVERT', '2026-10-20');
        $product = $this->stock(20);
        app(\App\Services\Commercial\CommercialApplicationService::class)->create('sale',
            $this->saleData($product) + ['quotation_id' => $quote->id], 'quote-follow-up-conversion', 1, $this->context());
        $this->assertSame(2, $quote->fresh()->quotation_status);
        $this->assertSame('sale', $quote->fresh()->converted_kind);
        $this->assertNotNull($quote->fresh()->converted_document_id);
        $this->assertFalse($quote->fresh()->canConvert());
        $this->assertSame('2026-10-20', $quote->fresh()->next_follow_up->toDateString());
        $this->assertSame(1, DB::table('quotation_follow_ups')->where('quotation_id', $quote->id)->count());
    }

    public function test_all_rows_request_does_not_silently_truncate_follow_up_export(): void
    {
        $quote = $this->quote('Q-MANY', '2026-10-03');
        $attributes = $quote->getAttributes(); unset($attributes['id']);
        DB::table('quotations')->insert(array_fill(0, 501, $attributes));
        $result = $this->register(['length' => -1]);
        $this->assertSame(502, $result['recordsFiltered']);
        $this->assertCount(502, $result['data']);
    }

    public function test_new_sent_quote_converts_once_and_migration_replay_does_not_reclassify_it(): void
    {
        $quote = $this->quote('Q-SENT', '2026-10-25'); $quote->update(['quotation_status' => 2]);
        (require database_path('migrations/2026_10_19_000016_track_quotation_conversion.php'))->up();
        $this->assertTrue($quote->fresh()->canConvert());
        $product = $this->stock(20); $service = app(\App\Services\Commercial\CommercialApplicationService::class);
        $sale = $service->create('sale', $this->saleData($product) + ['quotation_id' => $quote->id], 'new-sent-quote', 1, $this->context());
        $this->assertSame($sale->id, (int) $quote->fresh()->converted_document_id);
        $this->assertSame($sale->id, $service->create('sale', $this->saleData($product) + ['quotation_id' => $quote->id], 'new-sent-quote', 1, $this->context())->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->create('sale', $this->saleData($product) + ['quotation_id' => $quote->id], 'duplicate-sent-quote', 1, $this->context());
    }

    public function test_ambiguous_historical_sent_quote_remains_blocked(): void
    {
        $quote = $this->quote('Q-OLD-SENT', '2026-10-25');
        $quote->forceFill(['quotation_status' => 2, 'conversion_review_required' => true])->save();
        $this->assertFalse($quote->fresh()->canConvert());
        $result = $this->register(['search' => ['value' => 'Q-OLD-SENT']]);
        $this->assertStringContainsString('conversion review required', $result['data'][0]['status']);
        $this->assertStringNotContainsString('create_sale', $result['data'][0]['options']);
    }

    public function test_deletion_preserves_converted_and_review_records_and_batch_is_atomic(): void
    {
        Schema::create('product_quotation', function (Blueprint $table) {
            $table->increments('id'); $table->unsignedInteger('quotation_id');
        });
        $pending = $this->quote('Q-DELETE-PENDING', null);
        $converted = $this->quote('Q-DELETE-CONVERTED', '2026-10-25');
        $converted->forceFill(['converted_kind' => 'sale', 'converted_document_id' => 123])->save();
        $review = $this->quote('Q-DELETE-REVIEW', null);
        $review->forceFill(['conversion_review_required' => true])->save();
        DB::table('product_quotation')->insert([['quotation_id' => $pending->id], ['quotation_id' => $converted->id]]);
        $controller = app(QuotationController::class);
        foreach ([$converted, $review] as $quote) {
            try { $controller->destroy($quote->id); $this->fail('Expected protected quotation rejection.'); }
            catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('quotation', $error->errors()); }
        }
        try {
            $controller->deleteBySelection(Request::create('/quotations/deletebyselection', 'POST', ['quotationIdArray' => [$pending->id, $converted->id]]));
            $this->fail('Expected atomic batch rejection.');
        } catch (\Illuminate\Validation\ValidationException $error) { $this->assertArrayHasKey('quotation', $error->errors()); }
        $this->assertSame(3, Quotation::count());
        $this->assertSame(2, DB::table('product_quotation')->count());
        $this->assertSame(1, DB::table('quotation_follow_ups')->count());
        $this->assertSame(123, (int) $converted->fresh()->converted_document_id);
        $this->assertSame('Quotation deleted successfully!', $controller->deleteBySelection(Request::create('/quotations/deletebyselection', 'POST', ['quotationIdArray' => [$pending->id]])));
        $this->assertNull($pending->fresh());
        $this->assertSame(1, DB::table('product_quotation')->count());
    }

    public function test_migration_marks_only_ambiguous_old_sent_records_for_review(): void
    {
        $pending = $this->quote('Q-LEGACY-PENDING', null);
        $sent = $this->quote('Q-LEGACY-SENT', null); $sent->update(['quotation_status' => 2]);
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['converted_kind', 'converted_document_id', 'converted_at', 'conversion_review_required']));
        $migration = require database_path('migrations/2026_10_19_000016_track_quotation_conversion.php');
        $migration->up();
        $this->assertFalse($pending->fresh()->conversion_review_required);
        $this->assertTrue($sent->fresh()->conversion_review_required);
        $newSent = $this->quote('Q-NEW-SENT', null); $newSent->update(['quotation_status' => 2]);
        $migration->up();
        $this->assertFalse($newSent->fresh()->conversion_review_required);
        $this->assertTrue($sent->fresh()->conversion_review_required);
        $sent->update(['quotation_status' => 1]);
        $migration->up();
        $this->assertTrue($sent->fresh()->conversion_review_required);
        $this->assertFalse($sent->fresh()->canConvert());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ERP\ReviewedProductImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\Support\CommercialTestCase;

class ReviewedProductImportTest extends CommercialTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable(); $table->string('profit_margin_type')->nullable(); $table->decimal('profit_margin', 15, 4)->default(0);
            $table->boolean('is_variant')->nullable()->change();
        });
        (require database_path('migrations/2026_10_19_000015_create_reviewed_product_imports.php'))->up();
    }

    private function file(array $rows): UploadedFile
    {
        $stream = fopen('php://temp', 'w+'); fputcsv($stream, ['name', 'code', 'type', 'category_id', 'unit_id', 'cost', 'price']);
        foreach ($rows as $row) fputcsv($stream, $row);
        rewind($stream); $contents = stream_get_contents($stream); fclose($stream);
        return UploadedFile::fake()->createWithContent('items.csv', $contents);
    }

    private function row(string $code, int $unit = 1): array { return ['Imported '.$code, $code, 'standard', 1, $unit, '10', '20']; }

    public function test_review_and_retry_create_only_once_without_stock_or_accounting_effects(): void
    {
        $before = Product::count(); $stock = DB::table('stock_movements')->count(); $journals = DB::table('journal_entries')->count();
        $service = app(ReviewedProductImport::class);
        $record = $service->preview($this->file([$this->row('BULK-A'), $this->row('BULK-B')]), $this->context(), 1);
        $this->assertTrue($record->can_commit); $this->assertSame($before, Product::count());
        $created = $service->commit($record->id, $this->context(), 1);
        $this->assertSame($created, $service->commit($record->id, $this->context(), 1));
        $this->assertSame($before + 2, Product::count());
        $this->assertSame(2, Product::whereIn('id', array_column($created, 'id'))->whereNull('is_variant')->count());
        $this->assertEquals(0, Product::whereIn('id', array_column($created, 'id'))->sum('qty'));
        $this->assertSame($stock, DB::table('stock_movements')->count());
        $this->assertSame($journals, DB::table('journal_entries')->count());
    }

    public function test_invalid_row_blocks_the_whole_file_and_duplicate_codes_are_visible(): void
    {
        $before = Product::count(); $service = app(ReviewedProductImport::class);
        $record = $service->preview($this->file([$this->row('GOOD'), $this->row('BAD', 999)]), $this->context(), 1);
        $this->assertFalse($record->can_commit); $this->assertNotEmpty($record->rows[1]['errors']);
        try { $service->commit($record->id, $this->context(), 1); $this->fail('Invalid file accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('preview', $error->errors()); }
        $this->assertSame($before, Product::count());
        $duplicate = $service->preview($this->file([$this->row('DUPLICATE'), $this->row('duplicate')]), $this->context(), 1);
        $this->assertArrayHasKey('code', $duplicate->rows[1]['errors']);
    }

    public function test_changed_master_and_expired_preview_require_another_review(): void
    {
        $before = Product::count(); $service = app(ReviewedProductImport::class);
        $record = $service->preview($this->file([$this->row('MASTER-CHANGED')]), $this->context(), 1);
        DB::table('categories')->where('id', 1)->update(['name' => 'Changed after review']);
        try { $service->commit($record->id, $this->context(), 1); $this->fail('Changed master accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('preview', $error->errors()); }
        $expired = $service->preview($this->file([$this->row('EXPIRED')]), $this->context(), 1);
        DB::table('product_imports')->where('id', $expired->id)->update(['expires_at' => now()->subMinute()]);
        try { $service->commit($expired->id, $this->context(), 1); $this->fail('Expired preview accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('preview', $error->errors()); }
        $this->assertSame($before, Product::count());
    }

    public function test_existing_inactive_codes_and_foreign_references_never_upsert(): void
    {
        $product = $this->stock()->forceFill(['code' => 'OLD-CODE', 'is_active' => false]); $product->save();
        $record = app(ReviewedProductImport::class)->preview($this->file([$this->row('OLD-CODE')]), $this->context(), 1);
        $this->assertFalse($record->can_commit);
        $this->assertSame($product->name, $product->fresh()->name);
        DB::table('units')->insert(['id' => 99, 'company_id' => $this->other->id, 'name' => 'Foreign', 'unit_name' => 'Foreign', 'unit_code' => 'FOREIGN']);
        $foreign = app(ReviewedProductImport::class)->preview($this->file([$this->row('FOREIGN-UNIT', 99)]), $this->context(), 1);
        $this->assertFalse($foreign->can_commit);
    }

    public function test_legacy_import_route_requires_permission_and_confirmation_uses_server_rows(): void
    {
        $response = $this->postJson('/importproduct', ['file' => $this->file([$this->row('SERVER-ROWS')])])->assertOk();
        $id = $response->json('data.id');
        $this->postJson('/products/import/'.$id.'/commit', ['confirm' => false])->assertUnprocessable();
        $created = $this->postJson('/products/import/'.$id.'/commit', ['confirm' => true, 'rows' => [['code' => 'TAMPERED']]])->assertOk()->json('data');
        $this->assertSame('SERVER-ROWS', $created[0]['code']);
        $this->assertFalse(Product::where('code', 'TAMPERED')->exists());
        DB::table('users')->where('id', 1)->update(['role_id' => 4]);
        Schema::create('permissions', function (Blueprint $table) { $table->increments('id'); $table->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $table) { $table->unsignedInteger('role_id'); $table->unsignedInteger('permission_id'); });
        DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', 1)->update(['role_id_override' => 4]);
        $this->actingAs(\App\Models\User::findOrFail(1));
        $this->postJson('/importproduct', ['file' => $this->file([$this->row('DENIED')])])->assertForbidden();
    }

    public function test_names_cannot_introduce_stored_markup_and_codes_reject_markup(): void
    {
        $row = $this->row('SAFE-CODE'); $row[0] = '<img src=x onerror=alert(1)>';
        $service = app(ReviewedProductImport::class);
        $preview = $service->preview($this->file([$row]), $this->context(), 1);
        $created = $service->commit($preview->id, $this->context(), 1);
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', Product::findOrFail($created[0]['id'])->name);
        $bad = $service->preview($this->file([$this->row('<img src=x>')]), $this->context(), 1);
        $this->assertFalse($bad->can_commit);
        $this->assertArrayHasKey('code', $bad->rows[0]['errors']);
    }

    public function test_gst_rate_change_invalidates_reviewed_master_snapshot(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) { $table->increments('id'); $table->unsignedBigInteger('company_id'); $table->string('name'); });
        Schema::create('tax_rates', function (Blueprint $table) { $table->increments('id'); $table->unsignedBigInteger('company_id'); $table->unsignedInteger('tax_category_id'); $table->decimal('rate', 8, 4); });
        DB::table('tax_categories')->insert(['id' => 1, 'company_id' => $this->company->id, 'name' => 'GST']);
        DB::table('tax_rates')->insert(['id' => 1, 'company_id' => $this->company->id, 'tax_category_id' => 1, 'rate' => 18]);
        $file = UploadedFile::fake()->createWithContent('gst.csv', "name,code,type,category_id,unit_id,tax_category_id\nGST item,GST-CHANGE,standard,1,1,1\n");
        $service = app(ReviewedProductImport::class); $record = $service->preview($file, $this->context(), 1);
        $this->assertTrue($record->can_commit);
        DB::table('tax_rates')->where('id', 1)->update(['rate' => 12]);
        try { $service->commit($record->id, $this->context(), 1); $this->fail('Changed GST rate accepted.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('preview', $error->errors()); }
        $this->assertFalse(Product::where('code', 'GST-CHANGE')->exists());
    }
}

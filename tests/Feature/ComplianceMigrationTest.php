<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\Support\ReturnsDocumentTestCase;

class ComplianceMigrationTest extends ReturnsDocumentTestCase
{
    public function test_committed_ddl_resumes_missing_columns_and_constraints_preserving_data(): void
    {
        $before = DB::table('tax_rates')->first();
        Schema::table('tax_rates', fn (Blueprint $t) => $t->dropIndex('tax_rate_period'));
        Schema::table('gst_transaction_projections', fn (Blueprint $t) => $t->dropUnique('gst_projection_source'));
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('tax_snapshot_json'));
        Schema::table('returns', fn (Blueprint $t) => $t->dropColumn('approved_at'));
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('document_dispatch_logs', fn (Blueprint $t) => $t->dropForeign('document_dispatch_logs_company_id_foreign'));
        }
        Schema::table('document_dispatch_logs', fn (Blueprint $t) => $t->dropUnique('dispatch_retry'));
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('tax_rates', fn (Blueprint $t) => $t->dropForeign('tax_rates_tax_category_id_company_id_foreign'));
        }
        (require database_path('migrations/2026_10_06_000001_create_tax_compliance_contracts.php'))->up();
        (require database_path('migrations/2026_10_06_000002_extend_returns_and_document_delivery.php'))->up();
        $this->assertEquals($before, DB::table('tax_rates')->first());
        $this->assertTrue(Schema::hasColumn('sales', 'tax_snapshot_json'));
        $this->assertTrue(Schema::hasColumn('returns', 'approved_at'));
        $this->assertTrue(collect(Schema::getIndexes('document_dispatch_logs'))->firstWhere('name', 'dispatch_retry')['unique']);
    }

    public function test_empty_rollback_preserves_legacy_tables_and_does_not_narrow_precision(): void
    {
        (require database_path('migrations/2026_10_06_000002_extend_returns_and_document_delivery.php'))->down();
        (require database_path('migrations/2026_10_06_000001_create_tax_compliance_contracts.php'))->down();
        $this->assertTrue(Schema::hasTable('returns')); $this->assertTrue(Schema::hasTable('damage_stocks'));
        $this->assertFalse(Schema::hasTable('document_dispatch_logs')); $this->assertFalse(Schema::hasTable('tax_rates'));
        $this->assertFalse(Schema::hasColumn('returns', 'posted_at'));
        $this->assertFalse(Schema::hasColumn('sales', 'tax_snapshot_json'));
    }

    public function test_posted_tax_and_saved_documents_prevent_destructive_rollback(): void
    {
        $this->gstSale();
        foreach (['2026_10_06_000001_create_tax_compliance_contracts.php', '2026_10_06_000002_extend_returns_and_document_delivery.php'] as $file) {
            try { (require database_path('migrations/'.$file))->down(); $this->fail('Posted history was discarded.'); }
            catch (\RuntimeException $error) { $this->assertStringContainsString('history', $error->getMessage()); }
        }
        $this->assertSame(1, DB::table('gst_transaction_projections')->count());
        $this->assertNotNull(DB::table('sales')->value('document_snapshot_json'));
    }

    public function test_incompatible_existing_contract_fails_before_additive_ddl(): void
    {
        Schema::drop('gst_lookup_audits');
        Schema::create('gst_lookup_audits', fn (Blueprint $t) => $t->id());
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('tax_snapshot_json'));
        try { (require database_path('migrations/2026_10_06_000001_create_tax_compliance_contracts.php'))->up(); $this->fail('Incompatible table accepted.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Incompatible existing table', $error->getMessage()); }
        $this->assertFalse(Schema::hasColumn('sales', 'tax_snapshot_json'));
    }
}

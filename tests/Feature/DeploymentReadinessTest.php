<?php

namespace Tests\Feature;

use App\Models\Accounting\ChartOfAccount;
use App\Services\Deployment\BackupService;
use App\Services\Deployment\ErpHealthService;
use App\Services\Deployment\OpeningImportService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\OperationsTestCase;

class DeploymentReadinessTest extends OperationsTestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();
        if (!is_dir(storage_path('app'))) mkdir(storage_path('app'), 0700, true);
        (require database_path('migrations/2026_10_10_000001_create_reviewed_opening_imports.php'))->up();
        $this->fixtureRoot = storage_path('framework/testing/delivery-'.bin2hex(random_bytes(6)));
        mkdir($this->fixtureRoot.'/uploads', 0700, true);
        file_put_contents($this->fixtureRoot.'/uploads/receipt.txt', "Fixture 'receipt' & document\n");
        config(['deployment.backup_root' => $this->fixtureRoot.'/backups', 'deployment.restore_root' => $this->fixtureRoot.'/restore',
            'deployment.backup_password' => 'fixture-backup-password-never-use-in-production', 'deployment.upload_roots' => [$this->fixtureRoot.'/uploads']]);
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot) && str_starts_with(str_replace('\\', '/', $this->fixtureRoot), str_replace('\\', '/', storage_path('framework/testing')).'/delivery-')) File::deleteDirectory($this->fixtureRoot);
        parent::tearDown();
    }

    private function source(): array
    {
        $product = $this->material(0);
        $equity = ChartOfAccount::forceCreate(['company_id' => $this->company->id, 'code' => 'OPENING', 'name' => 'Opening equity', 'type' => 'equity', 'sub_type' => 'capital']);
        return ['batch_key' => 'source-opening-1', 'source_name' => 'Reviewed fixture', 'business_date' => '2026-10-03',
            'mappings' => [['entity' => 'product', 'source_key' => 'PRODUCT', 'target_id' => $product->id],
                ['entity' => 'warehouse', 'source_key' => 'MAIN', 'target_id' => 1], ['entity' => 'customer', 'source_key' => 'CUSTOMER', 'target_id' => 1],
                ['entity' => 'supplier', 'source_key' => 'SUPPLIER', 'target_id' => 1], ['entity' => 'account', 'source_key' => 'EQUITY', 'target_id' => $equity->id]],
            'expected' => ['stock_qty' => 10, 'stock_value' => 30, 'ar' => 100, 'ap' => 40],
            'rows' => [['source_key' => 'STOCK-1', 'kind' => 'stock', 'payload' => ['product_key' => 'PRODUCT', 'warehouse_key' => 'MAIN', 'qty' => 10, 'unit_cost' => 3, 'offset_account_key' => 'EQUITY']],
                ['source_key' => 'OLD-CUSTOMER-INVOICE', 'kind' => 'receivable', 'payload' => ['party_key' => 'CUSTOMER', 'amount' => 100, 'offset_account_key' => 'EQUITY', 'document_date' => '2025-10-01', 'due_date' => '2025-11-01']],
                ['source_key' => 'OLD-SUPPLIER-INVOICE', 'kind' => 'payable', 'payload' => ['party_key' => 'SUPPLIER', 'amount' => 40, 'offset_account_key' => 'EQUITY', 'document_date' => '2026-09-01', 'due_date' => '2026-09-30']]]];
    }

    public function test_opening_stage_preview_commit_retry_and_reconciliation_preserve_original_dates(): void
    {
        $imports = app(OpeningImportService::class); $source = $this->source();
        $batch = $imports->stage($source, $this->context(), 1);
        $this->assertSame($batch->id, $imports->stage($source, $this->context(), 1)->id);
        $preview = $imports->validate($batch->id, $this->context(), 1);
        $this->assertSame([], $preview->validation_errors);
        $this->assertSame('validated', $preview->status);
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, DB::table('document_number_reservations')->count());
        $posted = $imports->commit($batch->id, $this->context(), 1);
        $this->assertSame('committed', $posted->status);
        $this->assertTrue($posted->summary_json['reconciled']);
        $this->assertSame('30.0000', $posted->summary_json['stock_value']);
        $this->assertSame(1, DB::table('stock_movements')->count());
        $this->assertSame(3, DB::table('journal_entries')->count());
        $this->assertSame(2, DB::table('account_open_items')->count());
        $invoice = DB::table('account_open_items')->where('document_no', 'OLD-CUSTOMER-INVOICE')->first();
        $this->assertSame('2025-10-01', substr($invoice->document_date, 0, 10)); $this->assertSame('2025-11-01', substr($invoice->due_date, 0, 10));
        $this->year->update(['is_closed' => true]);
        $this->assertSame($posted->id, $imports->commit($batch->id, $this->context(), 1)->id);
        $this->assertSame(3, DB::table('journal_entries')->count());
    }

    public function test_invalid_source_total_rolls_back_every_effect_and_keeps_validation_evidence(): void
    {
        $source = $this->source(); $source['expected']['stock_value'] = 31;
        $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $result = $imports->validate($batch->id, $this->context(), 1);
        $this->assertSame('staged', $result->status); $this->assertNotEmpty($result->validation_errors);
        $this->assertSame(0, DB::table('stock_movements')->count()); $this->assertSame(0, DB::table('journal_entries')->count());
        $this->expectException(ValidationException::class); $imports->commit($batch->id, $this->context(), 1);
    }

    public function test_source_mapping_and_lock_boundaries_prevent_foreign_or_changed_imports(): void
    {
        $source = $this->source(); $imports = app(OpeningImportService::class);
        $batch = $imports->stage($source, $this->context(), 1);
        DB::table('import_rows')->where('batch_id', $batch->id)->where('kind', 'stock')->update(['payload_json' => '{}']);
        $this->assertNotEmpty($imports->validate($batch->id, $this->context(), 1)->validation_errors);
        $source['batch_key'] = 'locked-source'; $batch = $imports->stage($source, $this->context(), 1);
        $this->year->update(['lock_date' => '2026-10-03']);
        $this->assertNotEmpty($imports->validate($batch->id, $this->context(), 1)->validation_errors);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_clean_target_and_repeated_commit_are_required(): void
    {
        $source = $this->source(); $this->material(1);
        $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $this->assertArrayHasKey('target', $imports->validate($batch->id, $this->context(), 1)->validation_errors);
        $this->assertSame(1, Artisan::call('erp:import-opening', ['file' => 'missing.json', '--commit' => true]));
        $this->assertSame(1, Artisan::call('erp:health'));
    }

    public function test_encrypted_database_and_upload_backup_restores_into_an_isolated_fixture(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') $this->markTestSkipped('MySQL archive/restore is proved by the dedicated restricted-user rehearsal.');
        $service = app(BackupService::class); $receipt = $service->create(true);
        $this->assertFalse($receipt['offsite_verified']); $this->assertFileExists($receipt['path']);
        $archive = new \ZipArchive(); $archive->open($receipt['path']);
        $this->assertFalse($archive->getFromName('database')); $archive->close();
        $proof = $service->restoreRehearsal($receipt['path']);
        $this->assertSame($receipt['archive_sha256'], $proof['archive_sha256']);
        $this->assertSame(file_get_contents($this->fixtureRoot.'/uploads/receipt.txt'), file_get_contents($proof['root'].'/uploads/0/receipt.txt'));
        $this->assertTrue($service->latestReceipt()['restore_verified']);
        $restored = new \PDO('sqlite:'.$proof['root'].'/database');
        $this->assertSame('Company A', $restored->query('SELECT legal_name FROM companies WHERE id=1')->fetchColumn());
    }

    public function test_backup_password_failure_and_public_destination_fail_before_success_receipts(): void
    {
        config(['deployment.backup_password' => 'short']);
        $this->assertSame(1, Artisan::call('erp:backup', ['--local-only' => true]));
        $this->assertStringNotContainsString('short', Artisan::output());
        $this->assertSame(1, Artisan::call('erp:restore-rehearsal', ['archive' => 'missing.zip']));
    }

    public function test_public_backup_destination_is_rejected_before_any_directory_is_created(): void
    {
        foreach ([public_path('uploads'), storage_path('app/public')] as $publicRoot) {
            $destination = $publicRoot.'/delivery-backup-'.bin2hex(random_bytes(6));
            config(['deployment.backup_root' => $destination]);
            try { app(BackupService::class)->create(true); $this->fail('Public backup destination must fail.'); }
            catch (\RuntimeException $error) { $this->assertStringContainsString('outside public', $error->getMessage()); }
            $this->assertDirectoryDoesNotExist($destination);
        }
    }

    public function test_opening_commit_revalidates_target_after_preview_without_partial_postings(): void
    {
        $imports = app(OpeningImportService::class); $batch = $imports->stage($this->source(), $this->context(), 1);
        $this->assertSame('validated', $imports->validate($batch->id, $this->context(), 1)->status);
        $this->material(1); $movements = DB::table('stock_movements')->count();
        try { $imports->commit($batch->id, $this->context(), 1); $this->fail('Changed target must fail.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('target', $error->errors()); }
        $this->assertSame('validated', $batch->fresh()->status);
        $this->assertSame($movements, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('journal_entries')->count());
    }

    public function test_import_schema_rejects_untracked_fields_before_staging(): void
    {
        $source = $this->source(); $source['mappings'][0]['company_id'] = $this->other->id;
        try { app(OpeningImportService::class)->stage($source, $this->context(), 1); $this->fail('Untracked mapping fields must fail.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('mappings.0', $error->errors()); }
        $this->assertSame(0, DB::table('import_batches')->count());
    }

    public function test_additive_import_migration_can_resume_and_refuses_an_incompatible_partial_table(): void
    {
        $migration = require database_path('migrations/2026_10_10_000001_create_reviewed_opening_imports.php');
        $migration->up(); $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('import_batches', ['source_hash', 'summary_json', 'validation_errors']));
        \Illuminate\Support\Facades\Schema::drop('import_rows');
        \Illuminate\Support\Facades\Schema::create('import_rows', fn ($table) => $table->id());
        try { $migration->up(); $this->fail('Incompatible partial table must fail before changes.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('import_rows', $error->getMessage()); }
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('import_rows', 'batch_id'));
    }

    public function test_health_reports_missing_series_mappings_and_closed_deployment_gates(): void
    {
        $health = app(ErpHealthService::class)->inspect($this->context(), 1, true);
        $this->assertFalse($health['ok']); $this->assertFalse($health['checks']['document_series']['ok']);
        $this->assertFalse($health['checks']['foundation_acceptance']['ok']); $this->assertFalse($health['checks']['backup_and_restore']['ok']);
        DB::table('semantic_account_mappings')->where('company_id', $this->company->id)->where('semantic_role', 'ar')->update(['account_id' => null]);
        $health = app(ErpHealthService::class)->inspect($this->context(), 1);
        $this->assertFalse($health['checks']['account_mappings']['ok']);
        $this->assertTrue($health['checks']['stock_reconciliation']['ok']);
        $this->assertTrue($health['checks']['accounting_reconciliation']['ok']);
    }

    public function test_mapping_ambiguity_and_foreign_targets_fail_without_staging_rows(): void
    {
        $source = $this->source(); $imports = app(OpeningImportService::class);
        $source['mappings'][] = $source['mappings'][0];
        try { $imports->stage($source, $this->context(), 1); $this->fail('Ambiguous mapping must fail.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('mappings', $error->errors()); }
        $this->assertSame(0, DB::table('import_batches')->count());
        array_pop($source['mappings']);
        DB::table('products')->where('id', $source['mappings'][0]['target_id'])->update(['company_id' => $this->other->id]);
        try { $imports->stage($source, $this->context(), 1); $this->fail('Foreign target must fail.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('mappings', $error->errors()); }
        $this->assertSame(0, DB::table('import_rows')->count());
    }

    public function test_balanced_cash_and_bank_openings_use_owned_leaf_accounts_and_create_no_party_items(): void
    {
        $source = $this->source(); $cash = app(\App\Services\Accounting\SemanticAccountResolver::class)->resolve('cash', $this->context());
        $bank = ChartOfAccount::forCompany($this->context())->where('code', 'BANK')->firstOrFail();
        $source['mappings'][] = ['entity' => 'account', 'source_key' => 'CASH', 'target_id' => $cash->id];
        $source['mappings'][] = ['entity' => 'account', 'source_key' => 'BANK', 'target_id' => $bank->id];
        $source['rows'][] = ['source_key' => 'CASH-BANK-OPENING', 'kind' => 'journal', 'payload' => ['items' => [
            ['account_key' => 'CASH', 'debit' => 25], ['account_key' => 'BANK', 'debit' => 20], ['account_key' => 'EQUITY', 'credit' => 45],
        ]]];
        $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $this->assertSame([], $imports->validate($batch->id, $this->context(), 1)->validation_errors);
        $this->assertTrue($imports->commit($batch->id, $this->context(), 1)->summary_json['reconciled']);
        $this->assertEquals(25, $cash->fresh()->current_balance); $this->assertEquals(20, $bank->fresh()->current_balance);
        $this->assertSame(2, DB::table('account_open_items')->count());
        $this->assertSame(4, DB::table('journal_entries')->count());
    }

    public function test_customer_advance_import_preserves_a_negative_open_reference(): void
    {
        $source = $this->source(); $source['rows'][1]['payload']['amount'] = -100; $source['expected']['ar'] = -100;
        $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $this->assertSame([], $imports->validate($batch->id, $this->context(), 1)->validation_errors);
        $posted = $imports->commit($batch->id, $this->context(), 1);
        $this->assertSame('-100.0000', $posted->summary_json['ar']);
        $this->assertEquals(-100, DB::table('account_open_items')->where('party_type', 'customer')->value('open_amount'));
    }

    public function test_health_passes_after_owned_setup_and_opening_reconciliation(): void
    {
        $source = $this->source(); $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $imports->validate($batch->id, $this->context(), 1); $imports->commit($batch->id, $this->context(), 1);
        foreach (['sale', 'purchase', 'journal', 'sale_payment', 'purchase_payment', 'production', 'job_work_order', 'job_work_dispatch', 'job_work_receipt'] as $type) {
            app(\App\Services\Platform\DocumentNumberService::class)->configure($this->context(), ['document_type' => $type, 'code' => 'DEFAULT', 'is_default' => true], 1);
        }
        $health = app(ErpHealthService::class)->inspect($this->context(), 1);
        $this->assertTrue($health['ok'], json_encode($health));
        $this->assertTrue($health['checks']['inventory_value']['ok']);
        $this->assertSame(0, Artisan::call('erp:health', ['--company' => $this->company->id, '--branch' => $this->branch->id, '--year' => $this->year->id, '--actor' => 1]));
    }

    public function test_offsite_copy_is_verified_and_wrong_password_cannot_restore(): void
    {
        Storage::fake('erp_offsite'); config(['deployment.backup_disk' => 'erp_offsite']);
        $receipt = app(BackupService::class)->create();
        $this->assertTrue($receipt['offsite_verified']);
        Storage::disk('erp_offsite')->assertExists($receipt['offsite_path']);
        $this->assertSame($receipt['archive_sha256'], hash('sha256', Storage::disk('erp_offsite')->get($receipt['offsite_path'])));
        config(['deployment.backup_password' => 'another-fixture-password-that-is-at-least-32']);
        try { app(BackupService::class)->restoreRehearsal($receipt['path']); $this->fail('Wrong password must fail.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('manifest', $error->getMessage()); }
        $this->assertDirectoryDoesNotExist($this->fixtureRoot.'/restore');
    }

    public function test_legacy_global_jobs_are_retired_after_company_foundation(): void
    {
        $movements = DB::table('stock_movements')->count();
        foreach (['purchase:auto', 'dsoalert:find', 'quote:daily'] as $command) {
            $this->assertSame(1, Artisan::call($command));
        }
        $this->assertSame($movements, DB::table('stock_movements')->count());
    }

    public function test_opening_cli_stages_previews_then_commits_only_with_review_confirmation(): void
    {
        $file = $this->fixtureRoot.'/opening.json'; file_put_contents($file, json_encode($this->source(), JSON_THROW_ON_ERROR));
        $args = ['file' => $file, '--company' => $this->company->id, '--branch' => $this->branch->id, '--year' => $this->year->id, '--actor' => 1];
        $this->assertSame(0, Artisan::call('erp:import-opening', $args));
        $output = Artisan::output(); $this->assertSame('staged', json_decode($output, true)['status'] ?? null, $output);
        $this->assertSame(1, Artisan::call('erp:import-opening', $args + ['--commit' => true]));
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, Artisan::call('erp:import-opening', $args + ['--validate' => true]));
        $this->assertSame('validated', json_decode(Artisan::output(), true)['status']);
        $this->assertSame(0, DB::table('journal_entries')->count());
        $this->assertSame(0, Artisan::call('erp:import-opening', $args + ['--commit' => true, '--confirm-reviewed-opening' => true]));
        $this->assertSame('committed', json_decode(Artisan::output(), true)['status']);
        $this->assertSame(3, DB::table('journal_entries')->count());
    }

    public function test_native_mysql_archive_and_restricted_restore_rehearsal(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || getenv('ERP_DELIVERY_MYSQL_BACKUP') !== '1') $this->markTestSkipped('Opt in with a fresh restricted zolo_test_restore_* target for native MySQL restore proof.');
        $source = $this->source(); $imports = app(OpeningImportService::class); $batch = $imports->stage($source, $this->context(), 1);
        $imports->validate($batch->id, $this->context(), 1); $imports->commit($batch->id, $this->context(), 1);
        $receipt = app(BackupService::class)->create(true); $proof = app(BackupService::class)->restoreRehearsal($receipt['path']);
        $this->assertSame('mysql', $proof['driver']);
        $this->assertSame($receipt['archive_sha256'], $proof['archive_sha256']);
        $this->assertSame(3, DB::connection('erp_restore')->table('journal_entries')->count());
        $this->assertSame(2, DB::connection('erp_restore')->table('account_open_items')->count());
        $this->assertEquals(10, DB::connection('erp_restore')->table('stock_movement_lines')->sum('qty_base'));
        $this->assertTrue(app(BackupService::class)->latestReceipt()['restore_verified']);
    }
}

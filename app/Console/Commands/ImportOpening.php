<?php

namespace App\Console\Commands;

use App\Services\Deployment\OpeningImportService;
use App\Services\Platform\CompanyContextResolver;
use Illuminate\Console\Command;

class ImportOpening extends Command
{
    protected $signature = 'erp:import-opening {file : Reviewed UTF-8 JSON source} {--company=} {--branch=} {--year=} {--actor=}
        {--validate : Preview exact posting effects and roll them back} {--commit : Commit a previously validated batch}
        {--confirm-reviewed-opening : Confirm clean target, mapping review and source opening scope}';
    protected $description = 'Stage, validate and reconcile an opening-only source through shared stock/accounting owners';
    public function handle(OpeningImportService $imports): int
    {
        foreach (['company', 'branch', 'year', 'actor'] as $key) if (!filter_var($this->option($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            $this->error('Supply positive --company, --branch, --year and --actor IDs.'); return self::FAILURE;
        }
        if ($this->option('commit') && (!$this->option('confirm-reviewed-opening') || $this->option('validate'))) {
            $this->error('Commit requires --confirm-reviewed-opening and a separate successful validation.'); return self::FAILURE;
        }
        $file = $this->argument('file');
        if (!is_file($file) || filesize($file) > 25 * 1024 * 1024) { $this->error('Use a reviewed JSON source file of at most 25 MiB.'); return self::FAILURE; }
        try {
            $actor = (int) $this->option('actor');
            $context = app(CompanyContextResolver::class)->resolve($actor, (int) $this->option('company'), (int) $this->option('branch'), (int) $this->option('year'));
            $source = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $batch = $imports->stage($source, $context, $actor);
            if ($this->option('validate')) $batch = $imports->validate($batch->id, $context, $actor);
            if ($this->option('commit')) $batch = $imports->commit($batch->id, $context, $actor);
            $this->line(json_encode(['batch_id' => $batch->id, 'status' => $batch->status, 'source_hash' => $batch->source_hash,
                'summary' => $batch->summary_json, 'validation_errors' => $batch->validation_errors], JSON_THROW_ON_ERROR));
            return $batch->validation_errors ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) { $this->error('Opening import failed. Check reviewed source, ownership, dates, mappings and target cleanliness.'); return self::FAILURE; }
    }
}

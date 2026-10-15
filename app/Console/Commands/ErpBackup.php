<?php

namespace App\Console\Commands;

use App\Services\Deployment\BackupService;
use Illuminate\Console\Command;

class ErpBackup extends Command
{
    protected $signature = 'erp:backup {--local-only : Explicit local rehearsal without off-site retention}';
    protected $description = 'Create and verify an encrypted private database/uploads backup using operator credentials';
    public function handle(BackupService $backups): int
    {
        try { $this->line(json_encode($backups->create((bool) $this->option('local-only')), JSON_THROW_ON_ERROR)); return self::SUCCESS; }
        catch (\Throwable $error) {
            // The reason is shown to the operator and logged; secrets are never part of these messages.
            \Illuminate\Support\Facades\Log::error('erp.backup_failed', ['error' => $error->getMessage(), 'class' => get_class($error)]);
            $this->error('Backup failed: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}

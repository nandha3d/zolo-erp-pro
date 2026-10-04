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
        catch (\Throwable $error) { $this->error('Backup failed. Check private storage, encryption, database tools and off-site configuration.'); return self::FAILURE; }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Deployment\BackupService;
use Illuminate\Console\Command;

class RestoreRehearsal extends Command
{
    protected $signature = 'erp:restore-rehearsal {archive : Encrypted archive path}';
    protected $description = 'Verify and restore a private backup into a fresh SQLite fixture or empty restricted MySQL rehearsal database';
    public function handle(BackupService $backups): int
    {
        try { $this->line(json_encode($backups->restoreRehearsal($this->argument('archive')), JSON_THROW_ON_ERROR)); return self::SUCCESS; }
        catch (\Throwable $error) { $this->error('Restore rehearsal failed. Check archive integrity, password and empty restricted rehearsal target.'); return self::FAILURE; }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchDocuments extends Command
{
    protected $signature = 'erp:dispatch-documents {--limit=100} {--recover-stale : Mark interrupted sends as unknown; never resend them}';
    protected $description = 'Deliver pending document outbox entries without reposting source documents';

    public function handle(): int
    {
        if (!config('commercial.enabled') || !config('compliance.enabled')) { $this->error('Commercial or compliance activation is disabled.'); return self::FAILURE; }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if (!$limit || $limit < 1 || $limit > 1000) { $this->error('Use a limit from 1 to 1000.'); return self::FAILURE; }
        if ($this->option('recover-stale')) DB::table('document_dispatch_logs')->where('status', 'sending')->where('attempted_at', '<', now()->subMinutes(10))
            ->update(['status' => 'delivery_unknown', 'failure_category' => 'worker_interrupted', 'updated_at' => now()]);
        $ids = DB::table('document_dispatch_logs')->where('status', 'pending')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) app(\App\Services\Documents\CommunicationDispatchService::class)->deliver($id);
        $this->info('Processed '.$ids->count().' pending deliveries.'); return self::SUCCESS;
    }
}

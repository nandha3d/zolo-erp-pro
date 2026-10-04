<?php

namespace App\Services\Documents;

use App\Services\Commercial\CommercialPermission;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\{DB, Crypt, Http, Mail};
use Illuminate\Validation\ValidationException;

/** Durable outbox created inside a transaction; sending occurs after commit and never changes the invoice. */
class CommunicationDispatchService
{
    public function request(string $kind, int $id, string $channel, string $recipient, string $key, CompanyContext $context, int $actor): object
    {
        abort_unless(config('commercial.enabled') && config('compliance.enabled'), 503, 'Document delivery remains gated.');
        $context = app(CompanyWriteGuard::class)->context($context, $actor);
        app(CommercialPermission::class)->assert('documents-dispatch', $context, $actor);
        validator(compact('channel', 'recipient', 'key'), ['channel' => 'required|in:email,sms,whatsapp',
            'recipient' => $channel === 'email' ? 'required|email:rfc|max:191' : 'required|regex:/^\+[1-9][0-9]{7,14}$/|max:16',
            'key' => 'required|string|max:150'])->validate();
        if ($channel === 'whatsapp') app(\App\Services\Platform\CapabilityService::class)->assertEnabled('communications.whatsapp', $context);
        return DB::transaction(function () use ($kind, $id, $channel, $recipient, $key, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode([$kind, $id, $channel, $recipient, $context->branchId, $context->financialYearId], JSON_THROW_ON_ERROR));
            $existing = DB::table('document_dispatch_logs')->where('company_id', $context->companyId)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) throw ValidationException::withMessages(['key' => 'Dispatch key already used for a different request.']);
                return $existing;
            }
            $document = app(DocumentRenderingService::class)->dto($kind, $id, $context);
            $payload = ['document' => $document, 'subject' => $document['title'].' '.$document['number'],
                'message' => $document['company_name'].' '.$document['title'].' '.$document['number'].' '.$document['currency'].' '.DocumentRenderingService::amount($document['grand_total'])];
            $logId = DB::table('document_dispatch_logs')->insertGetId([
                'company_id' => $context->companyId, 'branch_id' => $context->branchId, 'financial_year_id' => $context->financialYearId,
                'document_type' => $kind, 'document_id' => $id, 'channel' => $channel, 'recipient' => $recipient,
                'template' => 'commercial-v1', 'idempotency_key' => $key, 'request_hash' => $hash, 'status' => 'pending',
                'created_by' => $actor, 'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            // Outbox remains pending when the queue is unavailable; erp:dispatch-documents recovers it.
            DB::afterCommit(function () use ($logId) {
                try { \App\Jobs\DispatchDocument::dispatch($logId); }
                catch (\Throwable $error) { report($error); }
            });
            return DB::table('document_dispatch_logs')->find($logId);
        });
    }

    public function deliver(int $id): void
    {
        if (!config('commercial.enabled') || !config('compliance.enabled')) return;
        $log = DB::transaction(function () use ($id) {
            $row = DB::table('document_dispatch_logs')->where('id', $id)->lockForUpdate()->first();
            if (!$row) throw new \RuntimeException('Dispatch outbox entry does not exist.');
            if ($row->status !== 'pending') return null;
            DB::table('document_dispatch_logs')->where('id', $id)->update(['status' => 'sending', 'attempts' => $row->attempts + 1,
                'attempted_at' => now(), 'updated_at' => now(), 'failure_category' => null]);
            return $row;
        });
        if (!$log) return;
        $status = 'failed'; $category = 'configuration'; $providerId = null;
        $channel = DB::table('document_channels')->where('company_id', $log->company_id)->where('channel', $log->channel)->where('is_active', true)->first();
        if ($channel) {
            try {
                $settings = json_decode(Crypt::decryptString($channel->configuration_encrypted), true, 512, JSON_THROW_ON_ERROR);
                $payload = json_decode($log->payload_json, true, 512, JSON_THROW_ON_ERROR);
                if ($log->channel === 'email') {
                    $html = app(DocumentRenderingService::class)->html($payload['document'], ['format' => 'a4', 'width' => 210, 'copies' => ['Original']]);
                    // Uses the configured application mail transport, with a company-owned sender.
                    Mail::html($html, fn ($message) => $message->to($log->recipient)->from($settings['from'], $payload['document']['company_name'])->subject($payload['subject']));
                    $status = 'sent'; $category = null;
                } else {
                    $account = $settings['account_sid'];
                    if (!preg_match('/^AC[0-9a-fA-F]{32}$/D', $account)) throw new \InvalidArgumentException('Invalid messaging account.');
                    $prefix = $log->channel === 'whatsapp' ? 'whatsapp:' : '';
                    $parameters = ['From' => $prefix.$settings['from'], 'To' => $prefix.$log->recipient, 'Body' => $payload['message']];
                    if ($log->channel === 'whatsapp') {
                        unset($parameters['Body']);
                        $parameters['ContentSid'] = $settings['content_sid'];
                        $parameters['ContentVariables'] = json_encode(['1' => $payload['document']['number'],
                            '2' => $payload['document']['currency'].' '.DocumentRenderingService::amount($payload['document']['grand_total']),
                            '3' => $payload['document']['company_name']], JSON_THROW_ON_ERROR);
                    }
                    $response = Http::withBasicAuth($account, $settings['auth_token'])->asForm()->timeout(10)
                        ->post('https://api.twilio.com/2010-04-01/Accounts/'.$account.'/Messages.json',
                            $parameters);
                    if ($response->serverError()) { $status = 'delivery_unknown'; $category = 'provider_server_error'; }
                    elseif (!$response->successful()) { $category = 'provider_rejected'; }
                    elseif (is_string($response->json('sid')) && preg_match('/^SM[0-9a-fA-F]{32}$/D', $response->json('sid'))) {
                        $status = 'sent'; $providerId = $response->json('sid'); $category = null;
                    } else { $status = 'delivery_unknown'; $category = 'invalid_acknowledgement'; }
                }
            } catch (\Illuminate\Http\Client\ConnectionException $error) {
                $status = 'delivery_unknown'; $category = 'connection';
            } catch (\Throwable $error) {
                // SMTP or provider errors can occur after external acceptance. Never automatically duplicate delivery.
                $status = 'delivery_unknown'; $category = 'transport';
            }
        }
        DB::table('document_dispatch_logs')->where('id', $id)->where('status', 'sending')->update([
            'status' => $status, 'failure_category' => $category, 'provider_message_id' => $providerId, 'updated_at' => now()]);
    }

    public function retry(int $id, CompanyContext $context, int $actor): object
    {
        abort_unless(config('commercial.enabled') && config('compliance.enabled'), 503, 'Document delivery remains gated.');
        $context = app(CompanyWriteGuard::class)->context($context, $actor);
        app(CommercialPermission::class)->assert('documents-dispatch', $context, $actor);
        $row = DB::table('document_dispatch_logs')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)->find($id);
        abort_unless($row, 404);
        if ($row->status !== 'failed') throw ValidationException::withMessages(['dispatch' => 'Only confirmed failed deliveries can retry. Unknown delivery needs provider reconciliation.']);
        DB::table('document_dispatch_logs')->where('id', $id)->where('status', 'failed')->update(['status' => 'pending', 'updated_at' => now()]);
        DB::afterCommit(function () use ($id) { try { \App\Jobs\DispatchDocument::dispatch($id); } catch (\Throwable $error) { report($error); } });
        return DB::table('document_dispatch_logs')->find($id);
    }
}

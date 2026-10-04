<?php

namespace App\Services\Operations;

use App\Services\Commercial\CommercialPermission;
use App\Services\ERP\CompanyWriteGuard;
use App\Services\Platform\CapabilityService;
use App\Services\Platform\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One authorization, company lock, retry and audit boundary for operational documents. */
class OperationPosting
{
    public function authorize(string $capability, string $permission, CompanyContext $context, int $actor): void
    {
        if (!config('operations.enabled')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Operations await company and shared-engine acceptance.');
        }
        app(CompanyWriteGuard::class)->context($context, $actor);
        app(CapabilityService::class)->assertEnabled($capability, $context);
        app(CommercialPermission::class)->assert($permission, $context, $actor);
    }

    public function run(string $type, string $model, string $capability, string $permission, array $data,
        string $key, CompanyContext $context, int $actor, callable $write): Model
    {
        $this->authorize($capability, $permission, $context, $actor);
        validator(['key' => $key], ['key' => 'required|string|max:150'])->validate();
        unset($data['_token'], $data['_method'], $data['company_id'], $data['branch_id'], $data['financial_year_id'], $data['user_id'], $data['idempotency_key']);
        $hash = hash('sha256', json_encode([$type, $context->branchId, $context->financialYearId, $this->canonical($data)], JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($type, $model, $data, $key, $hash, $context, $actor, $write) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $retry = DB::table('operation_requests')->where('company_id', $context->companyId)->where('key', $key)->first();
            if ($retry) {
                if ($retry->request_hash !== $hash || $retry->source_type !== $type) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key already used for a different operation.']);
                }
                $query = $model === \App\Models\Inventory\StockMovement::class
                    ? $model::forCompany($context)->where('branch_id', $context->branchId) : $model::visibleIn($context);
                return $query->findOrFail($retry->source_id);
            }
            $date = app(CompanyWriteGuard::class)->begin($context, $data['business_date'] ?? null);
            $record = $write($data, $date);
            DB::table('operation_requests')->insert(['company_id' => $context->companyId, 'key' => $key,
                'request_hash' => $hash, 'source_type' => $type, 'source_id' => $record->id, 'created_at' => now()]);
            $this->audit($type, $record->id, $context, $actor, $data);
            return $record->fresh();
        }, 3);
    }

    public function audit(string $event, int $id, CompanyContext $context, int $actor, array $details): void
    {
        DB::table('operation_audit_events')->insert(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'user_id' => $actor, 'event' => $event, 'source_id' => $id,
            'details_json' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function header(CompanyContext $context, int $actor): array
    {
        return ['company_id' => $context->companyId, 'branch_id' => $context->branchId,
            'financial_year_id' => $context->financialYearId, 'created_by' => $actor];
    }

    private function canonical(array $data): array
    {
        if (!array_is_list($data)) ksort($data);
        foreach ($data as &$value) if (is_array($value)) $value = $this->canonical($value);
        return $data;
    }
}

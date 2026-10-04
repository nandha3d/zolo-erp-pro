<?php

namespace App\Services\Commercial;

use App\Services\Platform\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialDraftService
{
    public function save(string $kind, array $payload, ?int $id, int $version, CompanyContext $context, int $actor): object
    {
        app(CommercialPermission::class)->assert($kind === 'sale' ? 'sales-add' : 'purchases-add', $context, $actor);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($json) > 500000) {
            throw ValidationException::withMessages(['draft' => 'Draft exceeds 500 KB.']);
        }
        return DB::transaction(function () use ($kind, $json, $id, $version, $context, $actor) {
            \App\Models\Company::whereKey($context->companyId)->lockForUpdate()->firstOrFail();
            $query = $this->query($kind, $context, $actor);
            if ($id) {
                $draft = (clone $query)->where('id', $id)->lockForUpdate()->first();
                abort_unless($draft, 404);
                abort_unless($draft->version === $version, 409, 'Draft changed in another window. Reload before saving.');
                DB::table('sale_drafts')->where('id', $id)->update(['version' => $version + 1, 'payload_json' => $json, 'updated_at' => now()]);
            } else {
                $id = DB::table('sale_drafts')->insertGetId(['company_id' => $context->companyId, 'branch_id' => $context->branchId,
                    'financial_year_id' => $context->financialYearId, 'user_id' => $actor, 'kind' => $kind,
                    'version' => 1, 'payload_json' => $json, 'created_at' => now(), 'updated_at' => now()]);
            }
            return $query->where('id', $id)->first();
        });
    }

    public function query(string $kind, CompanyContext $context, int $actor): \Illuminate\Database\Query\Builder
    {
        return DB::table('sale_drafts')->where('company_id', $context->companyId)->where('branch_id', $context->branchId)
            ->where('financial_year_id', $context->financialYearId)->where('user_id', $actor)->where('kind', $kind);
    }
}

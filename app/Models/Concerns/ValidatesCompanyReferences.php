<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validate legacy references whose representation cannot be protected by a composite FK. */
trait ValidatesCompanyReferences
{
    public static function bootValidatesCompanyReferences(): void
    {
        static::saving(function (Model $model) {
            // Keep reviewed pre-migration deployments and isolated legacy fixtures compatible.
            if (!\Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'company_id')) return;
            $company = $model->company_id ?? static::requestCompanyContext()?->companyId;
            if (!$company && !static::requestCompanyContext() && !DB::table('companies')->exists()) return;
            if (!$company) {
                throw ValidationException::withMessages(['company_id' => 'Select a reviewed company context.']);
            }
            foreach ($model->companyReferences() as $field => $table) {
                $id = $model->getAttribute($field);
                if ($id === null || $id === '') continue;
                if (!DB::table($table)->where('company_id', $company)->where('id', $id)->exists()) {
                    throw ValidationException::withMessages([$field => 'The reference must belong to the same company.']);
                }
            }
        });
    }
}

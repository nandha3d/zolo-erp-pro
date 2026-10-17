<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/** Material documents (GRN, delivery challan) belong to the branch and financial year they were entered in. */
trait StampsBranchAndYear
{
    public static function bootStampsBranchAndYear(): void
    {
        static::creating(function (Model $model) {
            $context = static::requestCompanyContext();
            if (!$context) {
                return;
            }
            if ($model->getAttribute('branch_id') === null) {
                $model->setAttribute('branch_id', $context->branchId);
            }
            if ($model->getAttribute('financial_year_id') === null) {
                $model->setAttribute('financial_year_id', $context->financialYearId);
            }
        });
    }
}

<?php

namespace App\Services\Platform;

use App\Models\Biller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Shift;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validate staff references before creating a global user or storing an uploaded portrait. */
final class EmployeeReferenceValidator
{
    public function validate(Request $request): void
    {
        $context = $request->attributes->get(CompanyContext::class);
        abort_unless($context instanceof CompanyContext, 403);
        foreach (['warehouse_id' => Warehouse::class, 'biller_id' => Biller::class,
            'department_id' => Department::class, 'designation_id' => Designation::class, 'shift_id' => Shift::class] as $field => $model) {
            if (!$request->filled($field)) continue;
            if (!$model::query()->where('company_id', $context->companyId)->whereKey($request->input($field))->exists()) {
                throw ValidationException::withMessages([$field => 'Select an authorized company-owned record.']);
            }
        }
        if ($request->filled('warehouse_id')) app(BranchAccess::class)->assertWarehouse((int) $request->input('warehouse_id'), $context, null, true);
        if ($request->filled('user_id') && !DB::table('company_user')
            ->where('company_id', $context->companyId)->where('user_id', $request->input('user_id'))->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Select a company member.']);
        }
    }
}

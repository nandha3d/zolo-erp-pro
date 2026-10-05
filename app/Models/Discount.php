<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    use HasFactory;

    protected $fillable= ['name', 'applicable_for', 'product_list', 'valid_from', 'valid_till', 'type', 'value', 'minimum_qty', 'maximum_qty', 'days', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (self $discount) {
            $company = $discount->company_id ?? static::requestCompanyContext()?->companyId;
            if (!$company && !static::requestCompanyContext() && !\Illuminate\Support\Facades\DB::table('companies')->exists()) return;
            if (!$company) throw \Illuminate\Validation\ValidationException::withMessages(['company_id' => 'Select a reviewed company context.']);
            $ids = trim((string) $discount->product_list) === '' ? [] : explode(',', $discount->product_list);
            foreach ($ids as $id) {
                if (filter_var(trim($id), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || !Product::withoutGlobalScopes()->where('company_id', $company)->whereKey(trim($id))->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['product_list' => 'Every discount product must belong to the same company.']);
                }
            }
        });
    }

    public function discountPlans()
    {
        return $this->belongsToMany('App\Models\DiscountPlan', 'discount_plan_discounts');
    }
}

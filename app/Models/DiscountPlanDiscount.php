<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPlanDiscount extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ValidatesCompanyReferences;

    protected function companyReferences(): array
    {
        return ['discount_plan_id' => 'discount_plans', 'discount_id' => 'discounts'];
    }

    use HasFactory;

    protected $fillable =['discount_plan_id', 'discount_id'];
}

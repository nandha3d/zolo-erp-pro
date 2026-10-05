<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPlanCustomer extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ValidatesCompanyReferences;

    protected function companyReferences(): array
    {
        return ['discount_plan_id' => 'discount_plans', 'customer_id' => 'customers'];
    }

    use HasFactory;

    protected $fillable = ['discount_plan_id', 'customer_id'];
}

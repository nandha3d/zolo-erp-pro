<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAdjustment extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $table = 'product_adjustments';
    protected $fillable =[
        "adjustment_id", "product_id", "variant_id", "unit_cost", "qty", "action"
    ];
}

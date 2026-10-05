<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackingSlipProduct extends Model
{
    use \App\Models\Concerns\ScopesBranchQueries;
    use HasFactory;
    protected $fillable = ["packing_slip_id", "product_id", "variant_id"];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class CustomerGroup extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[

        "name", "percentage", "is_active"
    ];
}

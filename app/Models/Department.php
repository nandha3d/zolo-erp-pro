<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $fillable =[
        "name", "is_active"
    ];
}


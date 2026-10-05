<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $table = "leave_types";
    protected $fillable = [
        'name',
        'annual_quota',
        'encashable',
        'carry_forward_limit'
    ];
}

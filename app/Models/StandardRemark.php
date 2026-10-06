<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class StandardRemark extends Model
{
    use ScopesCompanyQueries;

    protected $fillable = [
        'company_id',
        'title',
        'type',
        'remark',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}

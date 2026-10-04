<?php

namespace App\Models\Accounting;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class SemanticAccountMapping extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id', 'company_id'];
    protected $casts = ['is_active' => 'boolean'];
}

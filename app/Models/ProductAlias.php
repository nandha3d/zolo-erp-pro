<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class ProductAlias extends Model
{
    use ScopesCompanyQueries;

    protected $guarded = ['id'];
}

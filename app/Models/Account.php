<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Account extends Model
{
    use ScopesCompanyQueries;

    protected $fillable =[
        "account_no", "name", "initial_balance", "total_balance", "note", "is_default", "is_active", "code", "type", "parent_account_id", "is_payment"
    ];
}

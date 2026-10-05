<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ValidatesCompanyReferences;

    protected function companyReferences(): array
    {
        return ['employee_id' => 'employees', 'account_id' => 'accounts'];
    }

    public function setAccountIdAttribute($value): void
    {
        // Legacy draft/cash payrolls use zero to mean no settlement account.
        $this->attributes['account_id'] = ($value === 0 || $value === '0') ? null : $value;
    }


    protected $fillable = [
        "reference_no", "employee_id", "account_id", "user_id",
        "amount", "paying_method", "note", "created_at",
        "status", "amount_array","month"
    ];

   

    public function employee()
    {
    	return $this->belongsTo('App\Models\Employee');
    }
}

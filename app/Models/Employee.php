<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        "name",
        "image",
        "department_id",
        "designation_id",
        "shift_id",
        "basic_salary",
        "email",
        "phone_number",
        "user_id",
        "staff_id",
        "address",
        "city",
        "country",
        "is_active",
        "is_sale_agent",
        "sale_commission_percent",
        "sales_target",
    ];

    protected $casts = [
        'sales_target' => 'array',
    ];

    public function payroll()
    {
        return $this->hasMany('App\Models\Payroll');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
}

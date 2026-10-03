<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    protected $table = 'fiscal_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_closed',
        'company_id',
        'status',
        'lock_date',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'lock_date' => 'date',
        'closed_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use App\Models\Concerns\ScopesCompanyQueries;
use Illuminate\Database\Eloquent\Model;

class DocumentSeries extends Model
{
    use ScopesCompanyQueries;

    protected $table = 'document_series';

    protected $fillable = [
        'company_id',
        'branch_id',
        'financial_year_id',
        'document_type',
        'code',
        'prefix',
        'suffix',
        'next_number',
        'padding',
        'reset_policy',
        'is_default',
        'default_slot',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'padding' => 'integer',
        'is_default' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

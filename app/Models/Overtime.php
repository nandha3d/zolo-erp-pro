<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ValidatesCompanyReferences;

    protected function companyReferences(): array
    {
        return ['employee_id' => 'employees'];
    }


    use HasFactory;
    protected $table = "overtimes";
    protected $fillable = [
        'employee_id',
        'date',
        'hours',
        'rate',
        'amount',
        'status'
    ];

    // Relation to Employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Calculate amount automatically when setting hours or rate
    public static function boot()
    {
        parent::boot();

        static::saving(function($overtime) {
            $overtime->amount = $overtime->hours * $overtime->rate;
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTransaction extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;
    use \App\Models\Concerns\ValidatesCompanyReferences;

    protected function companyReferences(): array
    {
        return ['employee_id' => 'employees'];
    }


    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date',
        'amount',
        'type',
        'description',
        'created_by',
    ];

    // Relation with Employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Relation with User (created_by)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

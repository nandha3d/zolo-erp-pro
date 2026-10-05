<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\ScopesCompanyQueries;

class Warehouse extends Model
{
    use ScopesCompanyQueries;

    /** Within a request, a user only sees warehouses of the branches they are authorized for. */
    protected static function booted(): void
    {
        static::addGlobalScope('authorized_branches', function ($query) {
            if (static::requestCompanyContext() && auth()->id()) {
                $query->whereIn($query->getModel()->qualifyColumn('branch_id'), \Illuminate\Support\Facades\DB::table('company_user_branches')
                    ->where('company_id', static::requestCompanyContext()->companyId)->where('user_id', auth()->id())->select('branch_id'));
            }
        });
    }

    protected $fillable =[

        "name", "phone", "email", "address", "is_active"
    ];

    public function product()
    {
    	return $this->hasMany('App\Models\Product');

    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('qty');
    }

    public function printers()
    {
        return $this->hasMany(Printer::class, 'warehouse_id');
    }

    /**
     * Deactivate warehouse and delete related printers
     */
    public function deactivate()
    {
        // set warehouse inactive
        $this->is_active = false;
        $this->save();
        // HARD delete related printers
        $this->printers()->delete();
    }
}

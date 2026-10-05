<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    use \App\Models\Concerns\ScopesCompanyQueries;

    protected $fillable = ['name'];

    public function product()
    {
        return $this->belongsToMany(Product::class, 'product_variants');
    }
}

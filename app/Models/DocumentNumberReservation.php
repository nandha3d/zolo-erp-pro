<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberReservation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['reserved_number' => 'integer', 'series_id' => 'integer', 'company_id' => 'integer'];
}

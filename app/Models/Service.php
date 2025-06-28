<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory,SoftDeletes;

    

    protected $fillable = [
        'image',
        'price',
        'unit_per_minute',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}

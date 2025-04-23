<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'price',
        'unit_per_minute',
    ];

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}

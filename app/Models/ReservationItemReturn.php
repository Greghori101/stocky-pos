<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationItemReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_return_id',
        'product_id',
        'price',
        'qte',
        'unit_id',
        'total',
        'price',
        'tax_net',
        'tax_method',
        'discount',
        'discount_method',
    ];

    public function reservationReturn()
    {
        return $this->belongsTo(ReservationReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

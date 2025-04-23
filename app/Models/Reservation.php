<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'started_at',
        'ended_at',
        'total_price',
        'service_id',
        'post_id',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'reservation_items', 'product_id', 'reservation_id')->withPivot([
            'price',
            'qte',
        ]);
    }

    public function reservationItem()
    {
        return $this->hasMany(ReservationItem::class);
    }

}

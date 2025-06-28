<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'ref',
        'started_at',
        'ended_at',
        'status',
        'total_price',
        'discount',

        'paid_amount',
        'payment_status',
        
        'date',
        'tax_net',
        'tax_rate',
        'notes',

        'qte_return',
        'total_return',

        'user_id',
        'client_id',
        'warehouse_id',

        'service_id',
        'post_id',

        'created_at',
        'updated_at',
        'deleted_at',
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
        return $this->belongsToMany(Product::class, 'reservation_draft_items', 'product_id', 'reservation_draft_id')->withPivot([
            'price',
            'qte',
        ]);
    }

    public function reservationItemDraft()
    {
        return $this->hasMany(ReservationItemDraft::class);
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function facture()
    {
        return $this->hasMany('App\Models\PaymentSale');
    }

    public function warehouse()
    {
        return $this->belongsTo('App\Models\Warehouse');
    }
}

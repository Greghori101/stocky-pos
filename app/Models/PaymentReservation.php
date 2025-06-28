<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentReservation extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'reservation_id', 'date', 'amount', 'ref','change', 'discount', 'user_id', 'notes','account_id'
    ];

    protected $casts = [
        'amount' => 'double',
        'change'  => 'double',
        'reservation_id' => 'integer',
        'user_id' => 'integer',
        'account_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function reservation()
    {
        return $this->belongsTo('App\Models\Reservation');
    }
}

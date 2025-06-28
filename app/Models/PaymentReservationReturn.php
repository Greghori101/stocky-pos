<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentReservationReturn extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'reservation_return_id', 'date', 'amount','change', 'ref', 'discount', 'user_id', 'notes','account_id'
    ];

    protected $casts = [
        'amount' => 'double',
        'change'  => 'double',
        'reservation_return_id' => 'integer',
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

    public function ReservationReturn()
    {
        return $this->belongsTo('App\Models\ReservationReturn');
    }
}

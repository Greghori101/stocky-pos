<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $table = 'store_settings';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'logo'
    ];

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'reason',
        'note',
        'status'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function refund()
    {
        return $this->hasOne(Refund::class);
    }
}

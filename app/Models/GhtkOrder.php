<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GhtkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'order_code', 'label_id', 'partner_id',
        'status', 'fee', 'tracking_url', 'response'
    ];

    public function order() {
        return $this->belongsTo(Order::class);
    }
}

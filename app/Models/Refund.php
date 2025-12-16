<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'return_request_id',
        'amount',
        'status',
        'method',
        'note'
    ];

    public function returnRequest()
    {
        return $this->belongsTo(ReturnRequest::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'sender_type',
        'content',
        'meta',
        'read_at'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}

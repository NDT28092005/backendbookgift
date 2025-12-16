<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnniversary extends Model
{
    use HasFactory;

    protected $primaryKey = 'anniversary_id'; // vì id trong migration là anniversary_id
    protected $fillable = [
        'user_id',
        'event_name',
        'event_date',
    ];

    // Relationship: một anniversary thuộc về 1 user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
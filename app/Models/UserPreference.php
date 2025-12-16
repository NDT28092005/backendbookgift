<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $primaryKey = 'preference_id';

    protected $fillable = [
        'user_id',
        'preferred_occasion',
        'favorite_category',
        'budget_range_min',
        'budget_range_max',
    ];

    // Nếu dùng id khác default (preference_id), thêm:
    public $incrementing = true;
    protected $keyType = 'int';

    // Quan hệ với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

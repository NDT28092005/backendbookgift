<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'is_active',
        'google_id',
        'email_verified_at',
        'loyalty_points',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];
    public function anniversaries()
    {
        return $this->hasMany(UserAnniversary::class, 'user_id', 'id');
    }
    
    public function preferences()
    {
        return $this->hasOne(UserPreference::class, 'user_id');
    }

    /**
     * Relationship: User có referral code (là người giới thiệu)
     */
    public function referral()
    {
        return $this->hasOne(Referral::class, 'referrer_id', 'id');
    }

    /**
     * Relationship: User được giới thiệu bởi ai
     */
    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id', 'id');
    }
}

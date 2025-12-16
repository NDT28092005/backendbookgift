<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'code',
        'discount_percent', 'discount_amount',
        'start_date', 'end_date',
        'usage_limit', 'is_active'
    ];

    public function usages()
    {
        return $this->hasMany(PromotionUsage::class, 'promotion_id');
    }
}
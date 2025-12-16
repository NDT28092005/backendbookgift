<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'occasion_id',
        'name',
        'short_description',
        'full_description',
        'price',
        'stock_quantity',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function occasion()
    {
        return $this->belongsTo(Occasion::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
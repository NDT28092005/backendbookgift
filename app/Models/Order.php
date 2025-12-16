<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_amount',
        'shipping_fee',
        'status',
        'delivery_address',
        'expires_at',
        'customer_name',
        'customer_phone',
        'customer_province',
        'customer_district',
        'customer_ward',
        'cancelled_at',
        'cancellation_reason',
        'wrapping_paper_id',
        'wrapping_paper',
        'decorative_accessory_id',
        'decorative_accessories',
        'card_type_id',
        'card_type',
        'card_note',
        'loyalty_points_used',
        'print_label',
        'tracking_code',
        'payment_method',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'print_label' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function ghtkOrder()
    {
        return $this->hasOne(GhtkOrder::class);
    }
    public function wrappingPaper()
    {
        return $this->belongsTo(WrappingPaper::class);
    }

    public function decorativeAccessory()
    {
        return $this->belongsTo(DecorativeAccessory::class);
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * Sync tracking_code từ ghtk_orders.label_id
     */
    public function syncTrackingCode()
    {
        $ghtkOrder = $this->ghtkOrder;
        if ($ghtkOrder && $ghtkOrder->label_id && !$this->tracking_code) {
            $this->update(['tracking_code' => $ghtkOrder->label_id]);
            \Log::info("Synced tracking_code for order {$this->id}: {$ghtkOrder->label_id}");
            return true;
        }
        return false;
    }
}

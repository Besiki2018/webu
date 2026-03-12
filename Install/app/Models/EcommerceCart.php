<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCart extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id',
        'converted_order_id',
        'status',
        'currency',
        'customer_email',
        'customer_phone',
        'customer_name',
        'subtotal',
        'tax_total',
        'shipping_total',
        'discount_total',
        'grand_total',
        'meta_json',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'meta_json' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceCartItem::class, 'cart_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryReservation::class, 'cart_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'cart_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'converted_order_id');
    }
}

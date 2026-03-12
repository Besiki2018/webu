<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceInventoryReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'inventory_item_id',
        'product_id',
        'variant_id',
        'cart_id',
        'cart_item_id',
        'quantity',
        'reserved_until',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_until' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(EcommerceInventoryItem::class, 'inventory_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(EcommerceProductVariant::class, 'variant_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'cart_id');
    }

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(EcommerceCartItem::class, 'cart_item_id');
    }
}

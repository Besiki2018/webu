<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EcommerceCartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'cart_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'line_total',
        'options_json',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'options_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcommerceCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(EcommerceProductVariant::class, 'variant_id');
    }

    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(EcommerceInventoryReservation::class, 'cart_item_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'cart_item_id');
    }
}

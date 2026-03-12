<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'product_id',
        'name',
        'sku',
        'options_json',
        'price',
        'compare_at_price',
        'stock_tracking',
        'stock_quantity',
        'allow_backorder',
        'is_default',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_tracking' => 'boolean',
            'stock_quantity' => 'integer',
            'allow_backorder' => 'boolean',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'product_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(EcommerceInventoryItem::class, 'variant_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryReservation::class, 'variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'variant_id');
    }
}

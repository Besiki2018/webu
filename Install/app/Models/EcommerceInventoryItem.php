<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'product_id',
        'variant_id',
        'location_id',
        'sku',
        'quantity_on_hand',
        'quantity_reserved',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'low_stock_threshold' => 'integer',
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

    public function variant(): BelongsTo
    {
        return $this->belongsTo(EcommerceProductVariant::class, 'variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(EcommerceInventoryLocation::class, 'location_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryReservation::class, 'inventory_item_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'inventory_item_id');
    }
}

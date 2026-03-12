<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcommerceProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'currency',
        'status',
        'stock_tracking',
        'stock_quantity',
        'allow_backorder',
        'is_digital',
        'weight_grams',
        'attributes_json',
        'seo_title',
        'seo_description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_tracking' => 'boolean',
            'stock_quantity' => 'integer',
            'allow_backorder' => 'boolean',
            'is_digital' => 'boolean',
            'weight_grams' => 'integer',
            'attributes_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EcommerceCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(EcommerceProductImage::class, 'product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(EcommerceProductVariant::class, 'product_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(EcommerceInventoryItem::class, 'product_id');
    }

    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryReservation::class, 'product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'product_id');
    }
}

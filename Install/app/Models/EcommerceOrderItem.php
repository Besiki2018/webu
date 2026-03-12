<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'order_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'tax_amount',
        'discount_amount',
        'line_total',
        'options_json',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'options_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(EcommerceProductVariant::class, 'variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'order_item_id');
    }
}

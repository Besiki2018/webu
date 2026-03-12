<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceStockMovement extends Model
{
    use HasFactory;

    public const TYPE_INITIALIZE = 'initialize';

    public const TYPE_ADJUST = 'adjust';

    public const TYPE_STOCKTAKE = 'stocktake';

    public const TYPE_RESERVE = 'reserve';

    public const TYPE_RELEASE = 'release';

    public const TYPE_COMMIT = 'commit';

    protected $fillable = [
        'site_id',
        'inventory_item_id',
        'product_id',
        'variant_id',
        'cart_id',
        'cart_item_id',
        'order_id',
        'order_item_id',
        'movement_type',
        'reason',
        'quantity_delta',
        'reserved_delta',
        'quantity_on_hand_before',
        'quantity_on_hand_after',
        'quantity_reserved_before',
        'quantity_reserved_after',
        'meta_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'reserved_delta' => 'integer',
            'quantity_on_hand_before' => 'integer',
            'quantity_on_hand_after' => 'integer',
            'quantity_reserved_before' => 'integer',
            'quantity_reserved_after' => 'integer',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderItem::class, 'order_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

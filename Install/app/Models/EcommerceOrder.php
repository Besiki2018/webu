<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'order_number',
        'status',
        'payment_status',
        'fulfillment_status',
        'currency',
        'customer_email',
        'customer_phone',
        'customer_name',
        'billing_address_json',
        'shipping_address_json',
        'subtotal',
        'tax_total',
        'shipping_total',
        'discount_total',
        'grand_total',
        'paid_total',
        'outstanding_total',
        'placed_at',
        'paid_at',
        'cancelled_at',
        'notes',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'billing_address_json' => 'array',
            'shipping_address_json' => 'array',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'outstanding_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EcommerceOrderPayment::class, 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(EcommerceShipment::class, 'order_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(EcommerceCart::class, 'converted_order_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class, 'order_id');
    }

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntry::class, 'order_id');
    }

    public function accountingEntryLines(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntryLine::class, 'order_id');
    }

    public function rsExports(): HasMany
    {
        return $this->hasMany(EcommerceRsExport::class, 'order_id');
    }

    public function rsSyncs(): HasMany
    {
        return $this->hasMany(EcommerceRsSync::class, 'order_id');
    }

    public function rsSyncAttempts(): HasMany
    {
        return $this->hasMany(EcommerceRsSyncAttempt::class, 'order_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceShipment extends Model
{
    use HasFactory;

    public const STATUS_CREATED = 'created';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'site_id',
        'order_id',
        'provider_slug',
        'shipment_reference',
        'tracking_number',
        'tracking_url',
        'status',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'last_tracked_at',
        'meta_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_CREATED,
            self::STATUS_DISPATCHED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_RETURNED,
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

    public function events(): HasMany
    {
        return $this->hasMany(EcommerceShipmentEvent::class, 'shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}


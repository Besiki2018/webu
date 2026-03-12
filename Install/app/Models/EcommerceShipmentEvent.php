<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceShipmentEvent extends Model
{
    use HasFactory;

    public const TYPE_CREATED = 'created';

    public const TYPE_TRACKING_UPDATE = 'tracking_update';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_PUBLIC_TRACK = 'public_track';

    protected $fillable = [
        'site_id',
        'shipment_id',
        'event_type',
        'status',
        'message',
        'payload_json',
        'occurred_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(EcommerceShipment::class, 'shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


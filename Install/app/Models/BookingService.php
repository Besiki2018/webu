<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingService extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'status',
        'description',
        'duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'slot_step_minutes',
        'max_parallel_bookings',
        'requires_staff',
        'allow_online_payment',
        'price',
        'currency',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'slot_step_minutes' => 'integer',
            'max_parallel_bookings' => 'integer',
            'requires_staff' => 'boolean',
            'allow_online_payment' => 'boolean',
            'price' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(BookingAvailabilityRule::class, 'service_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'service_id');
    }
}

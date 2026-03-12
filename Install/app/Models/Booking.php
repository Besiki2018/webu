<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_IN_PROGRESS,
    ];

    protected $fillable = [
        'site_id',
        'service_id',
        'staff_resource_id',
        'booking_number',
        'status',
        'source',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_notes',
        'internal_notes',
        'starts_at',
        'ends_at',
        'collision_starts_at',
        'collision_ends_at',
        'duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'timezone',
        'service_fee',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_total',
        'outstanding_total',
        'currency',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'meta_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'collision_starts_at' => 'datetime',
            'collision_ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'service_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'outstanding_total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function scopeActiveForCollision(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'service_id');
    }

    public function staffResource(): BelongsTo
    {
        return $this->belongsTo(BookingStaffResource::class, 'staff_resource_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class, 'booking_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class, 'booking_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BookingInvoice::class, 'booking_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'booking_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'booking_id');
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class, 'booking_id');
    }

    public function financialEntryLines(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryLine::class, 'booking_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

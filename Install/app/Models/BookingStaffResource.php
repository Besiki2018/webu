<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingStaffResource extends Model
{
    use HasFactory;

    public const TYPE_STAFF = 'staff';

    public const TYPE_RESOURCE = 'resource';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'type',
        'status',
        'email',
        'phone',
        'timezone',
        'max_parallel_bookings',
        'buffer_minutes',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'max_parallel_bookings' => 'integer',
            'buffer_minutes' => 'integer',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(BookingStaffWorkSchedule::class, 'staff_resource_id');
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(BookingStaffTimeOff::class, 'staff_resource_id');
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(BookingAvailabilityRule::class, 'staff_resource_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'staff_resource_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class, 'staff_resource_id');
    }
}

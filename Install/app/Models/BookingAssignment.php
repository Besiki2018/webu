<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'booking_id',
        'staff_resource_id',
        'assignment_type',
        'status',
        'starts_at',
        'ends_at',
        'meta_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function staffResource(): BelongsTo
    {
        return $this->belongsTo(BookingStaffResource::class, 'staff_resource_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

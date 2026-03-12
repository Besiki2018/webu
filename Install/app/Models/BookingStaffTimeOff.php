<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStaffTimeOff extends Model
{
    use HasFactory;

    protected $table = 'booking_staff_time_off';

    protected $fillable = [
        'site_id',
        'staff_resource_id',
        'starts_at',
        'ends_at',
        'status',
        'reason',
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

    public function staffResource(): BelongsTo
    {
        return $this->belongsTo(BookingStaffResource::class, 'staff_resource_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

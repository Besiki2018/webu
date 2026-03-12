<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStaffWorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'staff_resource_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
        'timezone',
        'effective_from',
        'effective_to',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'booking_id',
        'event_type',
        'event_key',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

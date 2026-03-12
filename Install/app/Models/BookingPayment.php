<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'booking_id',
        'invoice_id',
        'provider',
        'status',
        'method',
        'transaction_reference',
        'amount',
        'currency',
        'is_prepayment',
        'processed_at',
        'raw_payload_json',
        'meta_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_prepayment' => 'boolean',
            'processed_at' => 'datetime',
            'raw_payload_json' => 'array',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BookingInvoice::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'payment_id');
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class, 'payment_id');
    }

    public function financialEntryLines(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryLine::class, 'payment_id');
    }
}

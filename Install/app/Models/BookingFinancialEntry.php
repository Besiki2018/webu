<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingFinancialEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'booking_id',
        'invoice_id',
        'payment_id',
        'refund_id',
        'event_type',
        'event_key',
        'currency',
        'total_debit',
        'total_credit',
        'description',
        'meta_json',
        'created_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'meta_json' => 'array',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BookingInvoice::class, 'invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BookingPayment::class, 'payment_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(BookingRefund::class, 'refund_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryLine::class, 'entry_id');
    }
}

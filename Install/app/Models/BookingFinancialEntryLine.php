<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFinancialEntryLine extends Model
{
    use HasFactory;

    public const SIDE_DEBIT = 'debit';

    public const SIDE_CREDIT = 'credit';

    protected $fillable = [
        'site_id',
        'entry_id',
        'booking_id',
        'invoice_id',
        'payment_id',
        'refund_id',
        'line_no',
        'account_code',
        'account_name',
        'side',
        'amount',
        'currency',
        'description',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'amount' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(BookingFinancialEntry::class, 'entry_id');
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
}

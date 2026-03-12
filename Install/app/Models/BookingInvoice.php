<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'booking_id',
        'invoice_number',
        'status',
        'currency',
        'subtotal',
        'tax_total',
        'discount_total',
        'grand_total',
        'paid_total',
        'outstanding_total',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'meta_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'outstanding_total' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class, 'invoice_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class, 'invoice_id');
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class, 'invoice_id');
    }

    public function financialEntryLines(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryLine::class, 'invoice_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrderPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'order_id',
        'provider',
        'status',
        'method',
        'transaction_reference',
        'amount',
        'currency',
        'is_installment',
        'installment_plan_json',
        'raw_payload_json',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_installment' => 'boolean',
            'installment_plan_json' => 'array',
            'raw_payload_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntry::class, 'order_payment_id');
    }

    public function accountingEntryLines(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntryLine::class, 'order_payment_id');
    }
}

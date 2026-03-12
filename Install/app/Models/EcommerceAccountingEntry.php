<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceAccountingEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'order_id',
        'order_payment_id',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderPayment::class, 'order_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntryLine::class, 'entry_id');
    }
}

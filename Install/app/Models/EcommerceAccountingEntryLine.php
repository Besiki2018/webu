<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceAccountingEntryLine extends Model
{
    use HasFactory;

    public const SIDE_DEBIT = 'debit';

    public const SIDE_CREDIT = 'credit';

    protected $fillable = [
        'site_id',
        'entry_id',
        'order_id',
        'order_payment_id',
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
        return $this->belongsTo(EcommerceAccountingEntry::class, 'entry_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrderPayment::class, 'order_payment_id');
    }
}

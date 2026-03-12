<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanVersion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'plan_id',
        'version_number',
        'status',
        'base_price',
        'billing_period',
        'currency',
        'effective_from',
        'effective_to',
        'notes',
        'metadata',
        'created_by',
        'activated_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'activated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function moduleAddons(): HasMany
    {
        return $this->hasMany(ModuleAddon::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PlanVersionAudit::class);
    }
}

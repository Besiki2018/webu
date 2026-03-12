<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_version_id',
        'code',
        'name',
        'rule_type',
        'adjustment_type',
        'amount',
        'conditions_json',
        'priority',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'conditions_json' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}

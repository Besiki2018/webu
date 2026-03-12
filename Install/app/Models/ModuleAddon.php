<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_version_id',
        'code',
        'name',
        'addon_group',
        'pricing_mode',
        'amount',
        'currency',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }
}

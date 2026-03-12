<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceInventoryLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'key',
        'name',
        'status',
        'is_default',
        'notes',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(EcommerceInventoryItem::class, 'location_id');
    }
}

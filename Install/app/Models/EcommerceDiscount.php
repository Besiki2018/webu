<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'code',
        'type',
        'value',
        'status',
        'scope',
        'product_ids_json',
        'category_ids_json',
        'starts_at',
        'ends_at',
        'notes',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'product_ids_json' => 'array',
            'category_ids_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}


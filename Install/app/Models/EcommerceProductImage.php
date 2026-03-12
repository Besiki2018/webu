<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'product_id',
        'media_id',
        'path',
        'alt_text',
        'sort_order',
        'is_primary',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(EcommerceProduct::class, 'product_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}


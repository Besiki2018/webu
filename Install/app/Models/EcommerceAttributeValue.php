<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'ecommerce_attribute_id',
        'label',
        'slug',
        'color_hex',
        'sort_order',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'ecommerce_attribute_id' => 'integer',
            'sort_order' => 'integer',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(EcommerceAttribute::class, 'ecommerce_attribute_id');
    }
}


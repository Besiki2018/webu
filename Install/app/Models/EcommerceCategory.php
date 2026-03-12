<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(EcommerceProduct::class, 'category_id');
    }
}


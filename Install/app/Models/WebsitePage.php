<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Universal CMS: page under a website (maps to Page for build).
 */
class WebsitePage extends Model
{
    use HasFactory;

    protected $table = 'website_pages';

    protected $fillable = [
        'website_id',
        'tenant_id',
        'slug',
        'title',
        'order',
        'page_id',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'page_id')->orderBy('order');
    }

    public function seo(): HasMany
    {
        return $this->hasMany(WebsiteSeo::class, 'website_page_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'status',
        'seo_title',
        'seo_description',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    public function publishedRevision(): ?PageRevision
    {
        return $this->revisions()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();
    }
}


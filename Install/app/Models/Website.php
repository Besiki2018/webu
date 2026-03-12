<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Universal CMS: user-facing website (maps to Site for build).
 * Every AI-generated site is editable via Websites → Pages → Sections.
 */
class Website extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'domain',
        'theme',
        'site_id',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function websitePages(): HasMany
    {
        return $this->hasMany(WebsitePage::class, 'website_id');
    }

    public function pages(): HasMany
    {
        return $this->websitePages();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsiteRevision::class, 'website_id');
    }
}


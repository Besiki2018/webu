<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSeo extends Model
{
    protected $table = 'website_seo';

    protected $fillable = [
        'tenant_id',
        'website_id',
        'website_page_id',
        'seo_title',
        'meta_description',
        'og_title',
        'og_image',
        'locale',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function websitePage(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }
}

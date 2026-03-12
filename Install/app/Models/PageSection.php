<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Universal CMS: one section on a page (section_type + settings_json).
 * Content is stored in DB so CMS can edit text, images, buttons without code.
 */
class PageSection extends Model
{
    use HasFactory;

    protected $table = 'page_sections';

    protected $fillable = [
        'page_id',
        'tenant_id',
        'website_id',
        'section_type',
        'order',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'settings_json' => 'array',
        ];
    }

    public function websitePage(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'page_id');
    }

    /**
     * Get settings for editing (title, subtitle, button_text, image, etc.).
     */
    public function getSettings(): array
    {
        return $this->settings_json ?? [];
    }

    /**
     * Set settings (from section editor form).
     */
    public function setSettings(array $settings): void
    {
        $this->settings_json = $settings;
        $this->save();
    }
}

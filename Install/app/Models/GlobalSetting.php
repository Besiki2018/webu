<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalSetting extends Model
{
    use HasFactory;

    protected $table = 'global_settings';

    protected $fillable = [
        'site_id',
        'logo_media_id',
        'contact_json',
        'social_links_json',
        'analytics_ids_json',
    ];

    protected function casts(): array
    {
        return [
            'contact_json' => 'array',
            'social_links_json' => 'array',
            'analytics_ids_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }
}


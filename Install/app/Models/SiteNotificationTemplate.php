<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteNotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'key',
        'name',
        'channel',
        'event_key',
        'locale',
        'status',
        'subject_template',
        'body_template',
        'variables_json',
        'meta_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'variables_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SiteNotificationLog::class, 'site_notification_template_id');
    }
}

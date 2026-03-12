<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteNotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'site_notification_template_id',
        'channel',
        'event_key',
        'status',
        'recipient',
        'subject_snapshot',
        'body_snapshot',
        'payload_json',
        'meta_json',
        'provider',
        'provider_message_id',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'meta_json' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SiteNotificationTemplate::class, 'site_notification_template_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsTelemetryEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'project_id',
        'channel',
        'source',
        'event_name',
        'occurred_at',
        'page_id',
        'page_slug',
        'route_path',
        'route_slug',
        'route_params_json',
        'context_json',
        'meta_json',
        'session_hash',
        'client_ip_hash',
        'user_agent_family',
        'actor_scope',
        'actor_hash',
        'retention_expires_at',
        'anonymized_at',
    ];

    protected function casts(): array
    {
        return [
            'route_params_json' => 'array',
            'context_json' => 'array',
            'meta_json' => 'array',
            'occurred_at' => 'datetime',
            'retention_expires_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

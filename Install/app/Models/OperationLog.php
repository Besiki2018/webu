<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenantProject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationLog extends Model
{
    use BelongsToTenantProject, HasFactory;

    public const CHANNEL_BUILD = 'build';

    public const CHANNEL_PUBLISH = 'publish';

    public const CHANNEL_PAYMENT = 'payment';

    public const CHANNEL_SUBSCRIPTION = 'subscription';

    public const CHANNEL_BOOKING = 'booking';

    public const CHANNEL_SYSTEM = 'system';

    public const STATUS_INFO = 'info';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_WARNING = 'warning';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'project_id',
        'user_id',
        'channel',
        'event',
        'status',
        'source',
        'domain',
        'identifier',
        'message',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeOfChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

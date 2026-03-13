<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGenerationRun extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_FINALIZING = 'finalizing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'requested_prompt',
        'requested_language',
        'requested_style',
        'requested_website_type',
        'requested_input',
        'progress_message',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at',
        'result_payload',
    ];

    protected function casts(): array
    {
        return [
            'requested_input' => 'array',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_QUEUED,
            self::STATUS_PLANNING,
            self::STATUS_GENERATING,
            self::STATUS_FINALIZING,
        ], true);
    }
}

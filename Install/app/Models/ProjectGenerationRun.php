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

    public const STATUS_ANALYZING_PROMPT = 'analyzing_prompt';

    public const STATUS_PLANNING_STRUCTURE = 'planning_structure';

    public const STATUS_SELECTING_COMPONENTS = 'selecting_components';

    public const STATUS_GENERATING_CONTENT = 'generating_content';

    public const STATUS_ASSEMBLING_PAGE = 'assembling_page';

    public const STATUS_VALIDATING_RESULT = 'validating_result';

    public const STATUS_RENDERING_PREVIEW = 'rendering_preview';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_FINALIZING = 'finalizing';

    public const STATUS_SCAFFOLDING = 'scaffolding';

    public const STATUS_WRITING_FILES = 'writing_files';

    public const STATUS_BUILDING_PREVIEW = 'building_preview';

    public const STATUS_READY = 'ready';

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
        return in_array($this->status, self::activeStatuses(), true);
    }

    public function isReady(): bool
    {
        return in_array($this->status, self::readyStatuses(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function activeStatuses(): array
    {
        return [
            self::STATUS_QUEUED,
            self::STATUS_ANALYZING_PROMPT,
            self::STATUS_PLANNING_STRUCTURE,
            self::STATUS_SELECTING_COMPONENTS,
            self::STATUS_GENERATING_CONTENT,
            self::STATUS_ASSEMBLING_PAGE,
            self::STATUS_VALIDATING_RESULT,
            self::STATUS_RENDERING_PREVIEW,
            self::STATUS_PLANNING,
            self::STATUS_GENERATING,
            self::STATUS_FINALIZING,
            self::STATUS_SCAFFOLDING,
            self::STATUS_WRITING_FILES,
            self::STATUS_BUILDING_PREVIEW,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function readyStatuses(): array
    {
        return [
            self::STATUS_READY,
            self::STATUS_COMPLETED,
        ];
    }
}

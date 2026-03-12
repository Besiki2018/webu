<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of state before a chat patch (theme or section) for rollback.
 *
 * @see new tasks.txt — AI Design Director PART 6 (Director for Chat Editing, rollback entry for every patch)
 */
class ChatPatchRollback extends Model
{
    protected $fillable = [
        'project_id',
        'site_id',
        'patch_type',
        'snapshot_json',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isRolledBack(): bool
    {
        return $this->rolled_back_at !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRevision extends Model
{
    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'site_id',
        'page_id',
        'user_id',
        'prompt_text',
        'ai_raw_output',
        'applied_patch',
        'snapshot_before',
        'snapshot_after',
        'snapshot_hash',
        'page_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'ai_raw_output' => 'array',
            'applied_patch' => 'array',
            'snapshot_before' => 'array',
            'snapshot_after' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'page_revision_id');
    }
}

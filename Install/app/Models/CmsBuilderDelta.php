<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsBuilderDelta extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'project_id',
        'page_id',
        'baseline_revision_id',
        'target_revision_id',
        'generation_id',
        'locale',
        'captured_from',
        'patch_ops',
        'patch_stats_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'patch_ops' => 'array',
            'patch_stats_json' => 'array',
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

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function baselineRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'baseline_revision_id');
    }

    public function targetRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'target_revision_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

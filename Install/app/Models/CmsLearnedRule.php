<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsLearnedRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope',
        'project_id',
        'site_id',
        'rule_key',
        'status',
        'active',
        'source',
        'conditions_json',
        'patch_json',
        'evidence_json',
        'confidence',
        'sample_size',
        'delta_count',
        'last_learned_at',
        'promoted_at',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'conditions_json' => 'array',
            'patch_json' => 'array',
            'evidence_json' => 'array',
            'confidence' => 'decimal:4',
            'sample_size' => 'integer',
            'delta_count' => 'integer',
            'last_learned_at' => 'datetime',
            'promoted_at' => 'datetime',
            'disabled_at' => 'datetime',
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

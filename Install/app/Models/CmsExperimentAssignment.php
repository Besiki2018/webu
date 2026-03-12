<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsExperimentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'experiment_id',
        'site_id',
        'project_id',
        'variant_key',
        'assignment_basis',
        'subject_hash',
        'session_id_hash',
        'device_id_hash',
        'context_json',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'assigned_at' => 'datetime',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(CmsExperiment::class, 'experiment_id');
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

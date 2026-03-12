<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsExperiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'project_id',
        'key',
        'name',
        'status',
        'assignment_unit',
        'traffic_percent',
        'starts_at',
        'ends_at',
        'targeting_json',
        'meta_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'traffic_percent' => 'integer',
            'targeting_json' => 'array',
            'meta_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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

    public function variants(): HasMany
    {
        return $this->hasMany(CmsExperimentVariant::class, 'experiment_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CmsExperimentAssignment::class, 'experiment_id');
    }
}

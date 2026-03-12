<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsTelemetryDailyAggregate extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_date',
        'site_id',
        'project_id',
        'total_events',
        'builder_events',
        'runtime_events',
        'unique_sessions_total',
        'unique_sessions_builder',
        'unique_sessions_runtime',
        'builder_open_count',
        'builder_save_draft_count',
        'builder_publish_page_count',
        'builder_save_warning_total',
        'runtime_route_hydrated_count',
        'runtime_hydrate_failed_count',
        'metrics_json',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'metrics_json' => 'array',
            'generated_at' => 'datetime',
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

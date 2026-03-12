<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSqlExport extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id',
        'requested_by',
        'status',
        'storage_disk',
        'sql_path',
        'manifest_path',
        'checksum',
        'file_size_bytes',
        'tables_json',
        'meta_json',
        'error_message',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'tables_json' => 'array',
            'meta_json' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}


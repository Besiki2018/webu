<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceRsSync extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'export_id',
        'order_id',
        'connector',
        'idempotency_key',
        'status',
        'attempts_count',
        'max_attempts',
        'next_retry_at',
        'last_attempt_at',
        'remote_reference',
        'last_error',
        'response_snapshot_json',
        'meta_json',
        'requested_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts_count' => 'integer',
            'max_attempts' => 'integer',
            'response_snapshot_json' => 'array',
            'meta_json' => 'array',
            'next_retry_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(EcommerceRsExport::class, 'export_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EcommerceRsSyncAttempt::class, 'sync_id');
    }
}

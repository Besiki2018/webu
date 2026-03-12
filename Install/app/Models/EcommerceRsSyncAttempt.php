<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceRsSyncAttempt extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'sync_id',
        'export_id',
        'order_id',
        'attempt_no',
        'status',
        'request_payload_json',
        'response_payload_json',
        'error_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'request_payload_json' => 'array',
            'response_payload_json' => 'array',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function sync(): BelongsTo
    {
        return $this->belongsTo(EcommerceRsSync::class, 'sync_id');
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(EcommerceRsExport::class, 'export_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }
}

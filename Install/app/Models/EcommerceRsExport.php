<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceRsExport extends Model
{
    use HasFactory;

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'site_id',
        'order_id',
        'schema_version',
        'status',
        'export_hash',
        'payload_json',
        'validation_errors_json',
        'validation_warnings_json',
        'totals_json',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'validation_errors_json' => 'array',
            'validation_warnings_json' => 'array',
            'totals_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(EcommerceRsSync::class, 'export_id');
    }
}

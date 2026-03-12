<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDatabaseBinding extends Model
{
    use HasFactory;

    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id',
        'status',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'options_json',
        'provisioned_at',
        'disabled_at',
        'last_health_check_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'options_json' => 'array',
            'provisioned_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->disabled_at === null;
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteRevision extends Model
{
    protected $table = 'website_revisions';

    protected $fillable = [
        'tenant_id',
        'website_id',
        'version',
        'snapshot_json',
        'change_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot_json' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

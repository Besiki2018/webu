<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsExperimentVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'experiment_id',
        'variant_key',
        'status',
        'weight',
        'sort_order',
        'payload_json',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
            'payload_json' => 'array',
            'meta_json' => 'array',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(CmsExperiment::class, 'experiment_id');
    }
}

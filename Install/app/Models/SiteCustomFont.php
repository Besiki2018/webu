<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCustomFont extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'key',
        'label',
        'font_family',
        'storage_path',
        'mime',
        'format',
        'size',
        'font_weight',
        'font_style',
        'font_display',
        'uploaded_by',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'font_weight' => 'integer',
            'uploaded_by' => 'integer',
            'meta_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

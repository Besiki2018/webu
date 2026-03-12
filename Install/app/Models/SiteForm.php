<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'key',
        'name',
        'status',
        'schema_json',
        'settings_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'settings_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(SiteFormLead::class, 'site_form_id');
    }
}

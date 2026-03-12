<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteFormLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'site_form_id',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'payload_json',
        'fields_json',
        'source_json',
        'meta_json',
        'ip_hash',
        'user_agent',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'fields_json' => 'array',
            'source_json' => 'array',
            'meta_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(SiteForm::class, 'site_form_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteCourierSetting extends Model
{
    use HasFactory;

    public const AVAILABILITY_INHERIT = 'inherit';

    public const AVAILABILITY_ENABLED = 'enabled';

    public const AVAILABILITY_DISABLED = 'disabled';

    protected $fillable = [
        'site_id',
        'courier_slug',
        'availability',
        'config',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

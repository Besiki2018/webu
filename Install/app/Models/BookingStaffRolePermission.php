<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStaffRolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'role_id',
        'permission_key',
        'allowed',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(BookingStaffRole::class, 'role_id');
    }
}

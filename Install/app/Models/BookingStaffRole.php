<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingStaffRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'key',
        'label',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(BookingStaffRolePermission::class, 'role_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingStaffRoleAssignment::class, 'role_id');
    }
}

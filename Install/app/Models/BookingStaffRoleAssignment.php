<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStaffRoleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'role_id',
        'user_id',
        'assigned_by',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(BookingStaffRole::class, 'role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

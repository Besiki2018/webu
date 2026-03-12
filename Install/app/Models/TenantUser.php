<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantUser extends Model
{
    use HasFactory;

    protected $table = 'tenant_users';

    protected $fillable = [
        'tenant_id',
        'platform_user_id',
        'name',
        'email',
        'phone',
        'status',
        'role_legacy',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'platform_user_id');
    }

    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'status', 'invited_by_user_id'])
            ->withTimestamps();
    }
}

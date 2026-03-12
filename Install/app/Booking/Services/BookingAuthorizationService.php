<?php

namespace App\Booking\Services;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Booking\Support\BookingPermissions;
use App\Models\BookingStaffRole;
use App\Models\BookingStaffRoleAssignment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingAuthorizationService implements BookingAuthorizationServiceContract
{
    /**
     * @var array<string,bool>
     */
    private array $ensuredSiteIds = [];

    public function ensureSiteRoles(Site $site): void
    {
        $siteId = (string) $site->id;
        if (isset($this->ensuredSiteIds[$siteId])) {
            return;
        }

        $roleBlueprint = BookingPermissions::systemRoleBlueprint();
        $permissionMatrix = BookingPermissions::systemRolePermissionMatrix();
        $now = now();

        DB::transaction(function () use ($site, $roleBlueprint, $permissionMatrix, $now): void {
            $roleIds = BookingStaffRole::query()
                ->where('site_id', $site->id)
                ->whereIn('key', array_keys($roleBlueprint))
                ->pluck('id', 'key')
                ->all();

            foreach ($roleBlueprint as $roleKey => $meta) {
                if (! array_key_exists($roleKey, $roleIds)) {
                    $roleIds[$roleKey] = BookingStaffRole::query()->insertGetId([
                        'site_id' => $site->id,
                        'key' => $roleKey,
                        'label' => $meta['label'],
                        'description' => $meta['description'],
                        'is_system' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $roleId = (int) $roleIds[$roleKey];
                foreach ($permissionMatrix[$roleKey] as $permissionKey => $allowed) {
                    DB::table('booking_staff_role_permissions')->updateOrInsert(
                        [
                            'site_id' => $site->id,
                            'role_id' => $roleId,
                            'permission_key' => $permissionKey,
                        ],
                        [
                            'allowed' => (bool) $allowed,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            $site->loadMissing('project');
            $ownerId = (int) ($site->project?->user_id ?? 0);
            if ($ownerId > 0 && isset($roleIds['owner'])) {
                BookingStaffRoleAssignment::query()->updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'user_id' => $ownerId,
                    ],
                    [
                        'role_id' => (int) $roleIds['owner'],
                        'assigned_by' => $ownerId,
                    ]
                );
            }
        }, 3);

        $this->ensuredSiteIds[$siteId] = true;
    }

    public function authorize(?User $user, Site $site, string $permission): void
    {
        if ($this->allows($user, $site, $permission)) {
            return;
        }

        throw new AuthorizationException("Booking permission denied: {$permission}");
    }

    public function allows(?User $user, Site $site, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (! in_array($permission, BookingPermissions::all(), true)) {
            return false;
        }

        // Platform admins can operate booking endpoints across tenant workspaces.
        if (method_exists($user, 'hasAdminBypass') && $user->hasAdminBypass()) {
            return true;
        }

        $this->ensureSiteRoles($site);
        $role = $this->resolveRole($site, $user);
        if (! $role) {
            return false;
        }

        $role->loadMissing('permissions');
        $permissionRow = $role->permissions->firstWhere('permission_key', $permission);

        return (bool) ($permissionRow?->allowed ?? false);
    }

    public function permissionSnapshot(?User $user, Site $site): array
    {
        $permissions = array_fill_keys(BookingPermissions::all(), false);
        $resolvedRoleKey = null;

        if ($user) {
            if (method_exists($user, 'hasAdminBypass') && $user->hasAdminBypass()) {
                $permissions = array_fill_keys(BookingPermissions::all(), true);
                $resolvedRoleKey = 'platform_admin';

                return [
                    'site_id' => $site->id,
                    'user_id' => $user->id,
                    'role_key' => $resolvedRoleKey,
                    'permissions' => $permissions,
                ];
            }

            $this->ensureSiteRoles($site);
            $role = $this->resolveRole($site, $user);
            if ($role) {
                $resolvedRoleKey = $role->key;
                $role->loadMissing('permissions');
                foreach ($role->permissions as $permission) {
                    if (! array_key_exists($permission->permission_key, $permissions)) {
                        continue;
                    }
                    $permissions[$permission->permission_key] = (bool) $permission->allowed;
                }
            }
        }

        return [
            'site_id' => $site->id,
            'user_id' => $user?->id,
            'role_key' => $resolvedRoleKey,
            'permissions' => $permissions,
        ];
    }

    public function assignRole(
        Site $site,
        User $user,
        string $roleKey,
        ?User $actor = null
    ): BookingStaffRoleAssignment {
        if (! array_key_exists($roleKey, BookingPermissions::systemRoleBlueprint())) {
            throw new \InvalidArgumentException("Unsupported booking role key: {$roleKey}");
        }

        $site->loadMissing('project');
        if ($actor && ! Gate::forUser($actor)->allows('update', $site->project)) {
            throw new AuthorizationException('You are not allowed to assign booking roles for this site.');
        }

        $this->ensureSiteRoles($site);
        $role = BookingStaffRole::query()
            ->where('site_id', $site->id)
            ->where('key', $roleKey)
            ->firstOrFail();

        $assignment = BookingStaffRoleAssignment::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $role->id,
                'assigned_by' => $actor?->id,
            ]
        );

        return $assignment->fresh(['role.permissions']);
    }

    private function resolveRole(Site $site, User $user): ?BookingStaffRole
    {
        $site->loadMissing('project');
        if ((int) ($site->project?->user_id ?? 0) === (int) $user->id) {
            return $this->resolveRoleByKey($site, 'owner');
        }

        $assignment = BookingStaffRoleAssignment::query()
            ->where('site_id', $site->id)
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->first();

        if ($assignment?->role) {
            return $assignment->role;
        }

        $fallbackRole = $this->fallbackRoleFromProjectShare($site, $user);
        if (! $fallbackRole) {
            return null;
        }

        return $this->resolveRoleByKey($site, $fallbackRole);
    }

    private function fallbackRoleFromProjectShare(Site $site, User $user): ?string
    {
        $projectId = (string) $site->project_id;
        if ($projectId === '') {
            return null;
        }

        $sharePermission = DB::table('project_shares')
            ->where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->value('permission');

        return match ((string) $sharePermission) {
            'admin', 'edit' => 'manager',
            'view' => 'staff',
            default => null,
        };
    }

    private function resolveRoleByKey(Site $site, string $roleKey): ?BookingStaffRole
    {
        return BookingStaffRole::query()
            ->where('site_id', $site->id)
            ->where('key', $roleKey)
            ->with('permissions')
            ->first();
    }
}

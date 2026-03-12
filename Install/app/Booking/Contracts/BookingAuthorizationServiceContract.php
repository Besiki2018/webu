<?php

namespace App\Booking\Contracts;

use App\Models\BookingStaffRoleAssignment;
use App\Models\Site;
use App\Models\User;

interface BookingAuthorizationServiceContract
{
    public function ensureSiteRoles(Site $site): void;

    public function authorize(?User $user, Site $site, string $permission): void;

    public function allows(?User $user, Site $site, string $permission): bool;

    /**
     * @return array<string,mixed>
     */
    public function permissionSnapshot(?User $user, Site $site): array;

    public function assignRole(
        Site $site,
        User $user,
        string $roleKey,
        ?User $actor = null
    ): BookingStaffRoleAssignment;
}

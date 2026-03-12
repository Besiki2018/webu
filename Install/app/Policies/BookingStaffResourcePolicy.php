<?php

namespace App\Policies;

use App\Models\BookingStaffResource;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BookingStaffResourcePolicy
{
    public function view(User $user, BookingStaffResource $resource): bool
    {
        return $this->canAccessSiteProject($user, $resource->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, BookingStaffResource $resource): bool
    {
        return $this->canAccessSiteProject($user, $resource->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}

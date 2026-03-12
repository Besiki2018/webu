<?php

namespace App\Policies;

use App\Models\BookingService;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BookingServicePolicy
{
    public function view(User $user, BookingService $service): bool
    {
        return $this->canAccessSiteProject($user, $service->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, BookingService $service): bool
    {
        return $this->canAccessSiteProject($user, $service->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}

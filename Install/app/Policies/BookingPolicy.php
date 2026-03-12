<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $this->canAccessSiteProject($user, $booking->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->canAccessSiteProject($user, $booking->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}

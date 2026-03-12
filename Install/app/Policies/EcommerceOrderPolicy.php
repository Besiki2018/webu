<?php

namespace App\Policies;

use App\Models\EcommerceOrder;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EcommerceOrderPolicy
{
    public function view(User $user, EcommerceOrder $order): bool
    {
        return $this->canAccessSiteProject($user, $order->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, EcommerceOrder $order): bool
    {
        return $this->canAccessSiteProject($user, $order->site, 'update');
    }

    public function delete(User $user, EcommerceOrder $order): bool
    {
        return $this->canAccessSiteProject($user, $order->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}

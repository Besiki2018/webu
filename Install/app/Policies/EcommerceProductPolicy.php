<?php

namespace App\Policies;

use App\Models\EcommerceProduct;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EcommerceProductPolicy
{
    public function view(User $user, EcommerceProduct $product): bool
    {
        return $this->canAccessSiteProject($user, $product->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, EcommerceProduct $product): bool
    {
        return $this->canAccessSiteProject($user, $product->site, 'update');
    }

    public function delete(User $user, EcommerceProduct $product): bool
    {
        return $this->canAccessSiteProject($user, $product->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}


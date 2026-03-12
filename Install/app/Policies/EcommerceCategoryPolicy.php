<?php

namespace App\Policies;

use App\Models\EcommerceCategory;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EcommerceCategoryPolicy
{
    public function view(User $user, EcommerceCategory $category): bool
    {
        return $this->canAccessSiteProject($user, $category->site, 'view');
    }

    public function create(User $user, Site $site): bool
    {
        return $this->canAccessSiteProject($user, $site, 'update');
    }

    public function update(User $user, EcommerceCategory $category): bool
    {
        return $this->canAccessSiteProject($user, $category->site, 'update');
    }

    public function delete(User $user, EcommerceCategory $category): bool
    {
        return $this->canAccessSiteProject($user, $category->site, 'update');
    }

    private function canAccessSiteProject(User $user, ?Site $site, string $ability): bool
    {
        if (! $site || ! $site->project) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $site->project);
    }
}


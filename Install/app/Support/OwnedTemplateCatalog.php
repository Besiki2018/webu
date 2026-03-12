<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class OwnedTemplateCatalog
{
    /**
     * Only templates built with the new Webu components (unified design-system).
     * Old component-based templates have been removed from the catalog.
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return [
            'ecommerce',
            'default',
        ];
    }

    public static function apply(Builder $query): Builder
    {
        return $query->whereIn('slug', self::slugs());
    }

    public static function contains(string $slug): bool
    {
        return in_array(trim(strtolower($slug)), self::slugs(), true);
    }
}


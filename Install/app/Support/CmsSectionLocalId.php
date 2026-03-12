<?php

namespace App\Support;

class CmsSectionLocalId
{
    public static function fallbackForIndex(int $index): string
    {
        return 'section-'.$index;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    public static function resolve(array $section, int $index): string
    {
        $localId = trim((string) ($section['localId'] ?? $section['id'] ?? ''));

        return $localId !== '' ? $localId : self::fallbackForIndex($index);
    }

    /**
     * @param  array<int, mixed>  $sections
     * @return array<int, mixed>
     */
    public static function materialize(array $sections): array
    {
        $normalized = [];

        foreach (array_values($sections) as $index => $section) {
            if (! is_array($section)) {
                $normalized[] = $section;
                continue;
            }

            $section['localId'] = self::resolve($section, $index);
            $normalized[] = $section;
        }

        return $normalized;
    }
}

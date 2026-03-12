<?php

namespace App\Services;

use Illuminate\Support\Str;

class FixedLayoutComponentService
{
    /**
     * @return array<int, string>
     */
    public function editableFields(string $sectionKey, array $props): array
    {
        $normalizedSectionKey = Str::lower(trim($sectionKey));
        $kind = $this->resolveKind($normalizedSectionKey);
        if ($kind === null) {
            return array_values(array_unique(array_keys($props)));
        }

        $normalizedProps = $this->normalizeProps($sectionKey, $props);
        $layoutVariant = $this->resolveLayoutVariant($kind, $normalizedProps);

        $fields = array_keys($normalizedProps);

        if ($kind === 'header') {
            $fields = array_merge($fields, $this->headerEditableFieldsForVariant($layoutVariant));
        }

        if ($kind === 'footer') {
            $fields = array_merge($fields, [
                'headline',
                'subtitle',
                'copyright_text',
                'contact_title',
                'links_title',
                'account_title',
            ]);
        }

        return array_values(array_unique(array_filter(array_map(static fn ($field) => is_string($field) ? trim($field) : '', $fields))));
    }

    /**
     * Normalize stored props so preview/analyze/apply paths all use the same visible keys.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function normalizeProps(string $sectionKey, array $props, ?string $instruction = null): array
    {
        $normalizedSectionKey = Str::lower(trim($sectionKey));
        $kind = $this->resolveKind($normalizedSectionKey);
        if ($kind === null) {
            return $props;
        }

        $next = $props;
        $layoutVariant = $this->resolveLayoutVariant($kind, $next);

        foreach (['text', 'message', 'content'] as $genericTextKey) {
            $genericTextValue = $this->stringValue($next[$genericTextKey] ?? null);
            if ($genericTextValue === null) {
                continue;
            }

            $targetKey = $this->inferPrimaryTextKey($kind, $layoutVariant, $next, $instruction, $genericTextValue);
            if ($targetKey !== null && $targetKey !== $genericTextKey) {
                $currentTargetValue = $this->stringValue($next[$targetKey] ?? null);
                if ($currentTargetValue === null || $currentTargetValue === '' || $currentTargetValue === $this->defaultValueForKey($layoutVariant, $targetKey)) {
                    $next[$targetKey] = $genericTextValue;
                }
                unset($next[$genericTextKey]);
            }
        }

        return $next;
    }

    private function resolveKind(string $normalizedSectionKey): ?string
    {
        if (str_starts_with($normalizedSectionKey, 'webu_header_') || $normalizedSectionKey === 'header') {
            return 'header';
        }

        if (str_starts_with($normalizedSectionKey, 'webu_footer_') || $normalizedSectionKey === 'footer') {
            return 'footer';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function resolveLayoutVariant(string $kind, array $props): string
    {
        $fallback = $kind === 'header' ? 'header-1' : 'footer-1';

        return Str::lower(trim((string) ($props['layout_variant'] ?? $props['variant'] ?? $fallback)));
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function inferPrimaryTextKey(string $kind, string $layoutVariant, array $props, ?string $instruction, string $genericValue): ?string
    {
        if ($kind === 'footer') {
            return 'copyright_text';
        }

        $instructionNeedle = Str::lower(trim((string) $instruction));
        $preferredKeys = $this->headerPrimaryTextKeysForVariant($layoutVariant);

        if ($instructionNeedle !== '') {
            if ($this->instructionMentionsShippingLikeText($instructionNeedle)) {
                foreach (['top_bar_left_text', 'top_strip_text', 'promo_label'] as $candidate) {
                    if (in_array($candidate, $preferredKeys, true)) {
                        return $candidate;
                    }
                }
            }

            if ($this->instructionMentionsBrandLikeText($instructionNeedle)) {
                foreach (['brand_text', 'logo_text'] as $candidate) {
                    if (in_array($candidate, $preferredKeys, true)) {
                        return $candidate;
                    }
                }
            }

            foreach ($preferredKeys as $candidate) {
                $candidateValue = $this->stringValue($props[$candidate] ?? $this->defaultValueForKey($layoutVariant, $candidate));
                if ($candidateValue !== null && $candidateValue !== '' && Str::contains($instructionNeedle, Str::lower($candidateValue))) {
                    return $candidate;
                }
            }
        }

        foreach ($preferredKeys as $candidate) {
            $candidateValue = $this->stringValue($props[$candidate] ?? $this->defaultValueForKey($layoutVariant, $candidate));
            if ($candidateValue !== null && $candidateValue !== '' && Str::lower($candidateValue) !== Str::lower($genericValue)) {
                return $candidate;
            }
        }

        return $preferredKeys[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function headerPrimaryTextKeysForVariant(string $layoutVariant): array
    {
        return match ($layoutVariant) {
            'header-3' => ['top_bar_location_text', 'top_bar_email_text', 'hotline_label', 'top_bar_login_label', 'logo_text'],
            'header-4' => ['promo_label', 'top_bar_right_tracking', 'department_label', 'account_label', 'logo_text'],
            'header-5' => ['top_strip_text', 'announcement_cta_label', 'logo_text', 'brand_text'],
            'header-6' => ['top_bar_left_text', 'top_strip_text', 'top_bar_right_tracking', 'logo_text', 'brand_text'],
            'header-7' => ['top_strip_text', 'logo_text', 'brand_text'],
            default => ['top_strip_text', 'brand_text', 'logo_text', 'cta_label'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function headerEditableFieldsForVariant(string $layoutVariant): array
    {
        return match ($layoutVariant) {
            'header-3' => [
                'logo_text',
                'top_bar_login_label',
                'top_bar_location_text',
                'top_bar_email_text',
                'hotline_eyebrow',
                'hotline_label',
            ],
            'header-4' => [
                'logo_text',
                'top_bar_right_tracking',
                'top_bar_right_lang',
                'top_bar_right_currency',
                'department_label',
                'promo_eyebrow',
                'promo_label',
                'account_eyebrow',
                'account_label',
                'cart_label',
                'search_placeholder',
                'search_category_label',
                'search_button_label',
            ],
            'header-5' => [
                'logo_text',
                'top_strip_text',
                'announcement_cta_label',
            ],
            'header-6' => [
                'logo_text',
                'top_strip_text',
                'announcement_cta_label',
                'top_bar_left_text',
                'top_bar_left_cta',
                'social_followers',
                'top_bar_right_tracking',
                'top_bar_right_lang',
                'top_bar_right_currency',
            ],
            'header-7' => [
                'logo_text',
                'top_strip_text',
            ],
            default => [
                'logo_text',
                'cta_label',
                'cta_url',
            ],
        };
    }

    private function defaultValueForKey(string $layoutVariant, string $key): ?string
    {
        return match ($layoutVariant) {
            'header-3' => match ($key) {
                'top_bar_location_text' => 'Location: 57 Park Ave, New York',
                'top_bar_email_text' => 'Mail: info@gmail.com',
                'hotline_label' => '+123-7767-8989',
                'top_bar_login_label' => 'Log In',
                'logo_text' => 'Finwave',
                default => null,
            },
            'header-4' => match ($key) {
                'top_bar_right_tracking' => 'Order Tracking',
                'top_bar_right_lang' => 'English',
                'top_bar_right_currency' => 'USD',
                'department_label' => 'All Departments',
                'promo_label' => 'Super Discount',
                'account_label' => 'Account',
                'logo_text' => 'machic®',
                'search_placeholder' => 'Search your favorite product...',
                default => null,
            },
            'header-5' => match ($key) {
                'top_strip_text' => 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!',
                'announcement_cta_label' => 'SHOP NOW',
                'logo_text' => 'Clotya®',
                default => null,
            },
            'header-6' => match ($key) {
                'top_strip_text' => 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!',
                'announcement_cta_label' => 'SHOP NOW',
                'top_bar_left_text' => 'Free Shipping World wide for all orders over $199.',
                'top_bar_left_cta' => 'Click and Shop Now.',
                'top_bar_right_tracking' => 'Order Tracking',
                'top_bar_right_lang' => 'English',
                'top_bar_right_currency' => 'USD',
                'logo_text' => 'Clotya®',
                default => null,
            },
            'header-7' => match ($key) {
                'top_strip_text' => 'FREE SHIPPING ON ALL U.S. ORDERS $50+',
                'logo_text' => 'GLOWING',
                default => null,
            },
            default => null,
        };
    }

    private function instructionMentionsShippingLikeText(string $instruction): bool
    {
        return Str::contains($instruction, [
            'shipping',
            'delivery',
            'free shipping',
            'order over',
            'მიწოდ',
            'უფასო მიწოდ',
            'გზავნ',
        ]);
    }

    private function instructionMentionsBrandLikeText(string $instruction): bool
    {
        return Str::contains($instruction, [
            'logo',
            'brand',
            'ბრენდ',
            'ლოგო',
            'name',
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

<?php

namespace App\Services;

use App\Models\User;

class EntitlementService
{
    public const FEATURE_SUBDOMAINS = 'subdomains';

    public const FEATURE_CUSTOM_DOMAINS = 'custom_domains';

    public const FEATURE_PRIVATE_VISIBILITY = 'private_visibility';

    public const FEATURE_FIREBASE = 'firebase';

    public const FEATURE_FIREBASE_CUSTOM_CONFIG = 'firebase_custom_config';

    public const FEATURE_FILE_STORAGE = 'file_storage';

    public const FEATURE_OWN_AI_API_KEY = 'own_ai_api_key';

    public const FEATURE_BOOKING_PREPAYMENT = 'booking_prepayment';

    public const FEATURE_ECOMMERCE = 'ecommerce';

    public const FEATURE_BOOKING = 'booking';

    public const FEATURE_SHIPPING = 'shipping';

    public const FEATURE_ECOMMERCE_INVENTORY = 'ecommerce_inventory';

    public const FEATURE_ECOMMERCE_ACCOUNTING = 'ecommerce_accounting';

    public const FEATURE_ECOMMERCE_RS = 'ecommerce_rs';

    public const FEATURE_BOOKING_TEAM_SCHEDULING = 'booking_team_scheduling';

    public const FEATURE_BOOKING_FINANCE = 'booking_finance';

    public const FEATURE_BOOKING_ADVANCED_CALENDAR = 'booking_advanced_calendar';

    /**
     * Build UI-friendly entitlement payload for shared props.
     */
    public function getForUser(?User $user): array
    {
        return [
            'features' => [
                self::FEATURE_SUBDOMAINS => $this->allows($user, self::FEATURE_SUBDOMAINS),
                self::FEATURE_CUSTOM_DOMAINS => $this->allows($user, self::FEATURE_CUSTOM_DOMAINS),
                self::FEATURE_PRIVATE_VISIBILITY => $this->allows($user, self::FEATURE_PRIVATE_VISIBILITY),
                self::FEATURE_FIREBASE => $this->allows($user, self::FEATURE_FIREBASE),
                self::FEATURE_FIREBASE_CUSTOM_CONFIG => $this->allows($user, self::FEATURE_FIREBASE_CUSTOM_CONFIG),
                self::FEATURE_FILE_STORAGE => $this->allows($user, self::FEATURE_FILE_STORAGE),
                self::FEATURE_OWN_AI_API_KEY => $this->allows($user, self::FEATURE_OWN_AI_API_KEY),
                self::FEATURE_BOOKING_PREPAYMENT => $this->allows($user, self::FEATURE_BOOKING_PREPAYMENT),
                self::FEATURE_ECOMMERCE => $this->allows($user, self::FEATURE_ECOMMERCE),
                self::FEATURE_BOOKING => $this->allows($user, self::FEATURE_BOOKING),
                self::FEATURE_SHIPPING => $this->allows($user, self::FEATURE_SHIPPING),
                self::FEATURE_ECOMMERCE_INVENTORY => $this->allows($user, self::FEATURE_ECOMMERCE_INVENTORY),
                self::FEATURE_ECOMMERCE_ACCOUNTING => $this->allows($user, self::FEATURE_ECOMMERCE_ACCOUNTING),
                self::FEATURE_ECOMMERCE_RS => $this->allows($user, self::FEATURE_ECOMMERCE_RS),
                self::FEATURE_BOOKING_TEAM_SCHEDULING => $this->allows($user, self::FEATURE_BOOKING_TEAM_SCHEDULING),
                self::FEATURE_BOOKING_FINANCE => $this->allows($user, self::FEATURE_BOOKING_FINANCE),
                self::FEATURE_BOOKING_ADVANCED_CALENDAR => $this->allows($user, self::FEATURE_BOOKING_ADVANCED_CALENDAR),
            ],
        ];
    }

    /**
     * Check if user has a given feature entitlement.
     */
    public function allows(?User $user, string $feature): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAdminBypass()) {
            return true;
        }

        return match ($feature) {
            self::FEATURE_SUBDOMAINS => $user->canUseSubdomains(),
            self::FEATURE_CUSTOM_DOMAINS => $user->canUseCustomDomains(),
            self::FEATURE_PRIVATE_VISIBILITY => $user->canUsePrivateVisibility(),
            self::FEATURE_FIREBASE => $user->canUseFirebase(),
            self::FEATURE_FIREBASE_CUSTOM_CONFIG => $user->canUseOwnFirebaseConfig(),
            self::FEATURE_FILE_STORAGE => $user->canUseFileStorage(),
            self::FEATURE_OWN_AI_API_KEY => $user->canUseOwnAiApiKey(),
            self::FEATURE_BOOKING_PREPAYMENT => $user->canUseBookingPrepayment(),
            self::FEATURE_ECOMMERCE => $user->canUseEcommerce(),
            self::FEATURE_BOOKING => $user->canUseBooking(),
            self::FEATURE_SHIPPING => $user->canUseShipping(),
            self::FEATURE_ECOMMERCE_INVENTORY => $user->canUseEcommerceInventory(),
            self::FEATURE_ECOMMERCE_ACCOUNTING => $user->canUseEcommerceAccounting(),
            self::FEATURE_ECOMMERCE_RS => $user->canUseEcommerceRsIntegration(),
            self::FEATURE_BOOKING_TEAM_SCHEDULING => $user->canUseBookingTeamScheduling(),
            self::FEATURE_BOOKING_FINANCE => $user->canUseBookingFinance(),
            self::FEATURE_BOOKING_ADVANCED_CALENDAR => $user->canUseBookingAdvancedCalendar(),
            default => false,
        };
    }

    /**
     * Return the first missing entitlement from a list.
     */
    public function firstMissing(?User $user, array $features): ?string
    {
        foreach ($features as $feature) {
            if (! $this->allows($user, $feature)) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * Normalize middleware feature parameters.
     */
    public function normalizeFeatures(array $features): array
    {
        $normalized = [];

        foreach ($features as $group) {
            foreach (explode(',', $group) as $feature) {
                $feature = trim($feature);
                if ($feature === '' || in_array($feature, $normalized, true)) {
                    continue;
                }

                $normalized[] = $feature;
            }
        }

        return $normalized;
    }

    /**
     * Get user-facing fallback message for a missing feature.
     */
    public function messageFor(string $feature): string
    {
        return match ($feature) {
            self::FEATURE_SUBDOMAINS => 'Your plan does not include subdomain publishing.',
            self::FEATURE_CUSTOM_DOMAINS => 'Your plan does not include custom domain publishing.',
            self::FEATURE_PRIVATE_VISIBILITY => 'Your plan does not include private visibility.',
            self::FEATURE_FIREBASE => 'Your plan does not include database module access.',
            self::FEATURE_FIREBASE_CUSTOM_CONFIG => 'Your plan does not include custom Firebase configurations.',
            self::FEATURE_FILE_STORAGE => 'Your plan does not include file storage.',
            self::FEATURE_OWN_AI_API_KEY => 'Your plan does not include custom AI API keys.',
            self::FEATURE_BOOKING_PREPAYMENT => 'Your plan does not include booking prepayment.',
            self::FEATURE_ECOMMERCE => 'Your plan does not include ecommerce module access.',
            self::FEATURE_BOOKING => 'Your plan does not include booking module access.',
            self::FEATURE_SHIPPING => 'Your plan does not include shipping module access.',
            self::FEATURE_ECOMMERCE_INVENTORY => 'Your plan does not include ecommerce inventory module.',
            self::FEATURE_ECOMMERCE_ACCOUNTING => 'Your plan does not include ecommerce accounting module.',
            self::FEATURE_ECOMMERCE_RS => 'Your plan does not include ecommerce RS integration module.',
            self::FEATURE_BOOKING_TEAM_SCHEDULING => 'Your plan does not include booking team scheduling module.',
            self::FEATURE_BOOKING_FINANCE => 'Your plan does not include booking finance module.',
            self::FEATURE_BOOKING_ADVANCED_CALENDAR => 'Your plan does not include booking advanced calendar module.',
            default => 'Your current plan does not allow this action.',
        };
    }
}

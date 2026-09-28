<?php

namespace App\Domain\Billing;

/**
 * Feature flags, limits and the seeded plans (saas-plans-usage "Plans with
 * features and limits"). Limits: null = unlimited.
 */
final class PlanCatalog
{
    public const FEATURES = [
        'appointments' => 'Appointment booking',
        'signage' => 'Digital signage',
        'feedback' => 'Customer feedback',
        'white_label' => 'White-label branding',
        'advanced_analytics' => 'Advanced analytics & reports',
        'api_access' => 'API access',
    ];

    /** Resource (gauge) limits and metered (per-period) limits. */
    public const LIMITS = [
        'locations' => 'Locations',
        'staff_users' => 'Staff users',
        'displays' => 'Paired kiosks & displays',
        'sms_monthly' => 'SMS segments per month',
        'media_upload_mb' => 'Max signage upload (MB)',
        'media_storage_mb' => 'Signage storage (MB)',
    ];

    /** When the monthly SMS allowance is used up: block sending, or allow and flag overage. */
    public const SMS_POLICIES = ['block', 'overage'];

    /** @return array<string, array{name: string, is_internal: bool, features: array<string, bool>, limits: array<string, int|string|null>}> */
    public static function seeded(): array
    {
        $all = array_fill_keys(array_keys(self::FEATURES), true);

        return [
            'internal' => ['name' => 'Internal (unlimited)', 'is_internal' => true, 'features' => $all, 'limits' => []],
            'starter' => [
                'name' => 'Starter', 'is_internal' => false,
                'features' => ['appointments' => false, 'signage' => false, 'feedback' => true, 'white_label' => false, 'advanced_analytics' => false, 'api_access' => false],
                'limits' => ['locations' => 1, 'staff_users' => 10, 'displays' => 2, 'sms_monthly' => 1000, 'media_upload_mb' => 20, 'media_storage_mb' => 200, 'sms_policy' => 'block'],
            ],
            'standard' => [
                'name' => 'Standard', 'is_internal' => false,
                'features' => ['appointments' => true, 'signage' => true, 'feedback' => true, 'white_label' => false, 'advanced_analytics' => false, 'api_access' => false],
                'limits' => ['locations' => 5, 'staff_users' => 50, 'displays' => 10, 'sms_monthly' => 5000, 'media_upload_mb' => 100, 'media_storage_mb' => 2000, 'sms_policy' => 'overage'],
            ],
            'pro' => [
                'name' => 'Pro', 'is_internal' => false,
                'features' => $all,
                'limits' => ['locations' => 25, 'staff_users' => 300, 'displays' => 60, 'sms_monthly' => 25000, 'media_upload_mb' => 500, 'media_storage_mb' => 20000, 'sms_policy' => 'overage'],
            ],
        ];
    }
}

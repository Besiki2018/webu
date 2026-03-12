<?php

namespace App\Booking\Support;

class BookingPermissions
{
    public const READ = 'booking.read';

    public const CALENDAR_VIEW = 'booking.calendar.view';

    public const CREATE = 'booking.create';

    public const STATUS_UPDATE = 'booking.status.update';

    public const RESCHEDULE = 'booking.reschedule';

    public const CANCEL = 'booking.cancel';

    public const ASSIGN = 'booking.assign';

    public const MANAGE_SERVICES = 'booking.manage_services';

    public const MANAGE_STAFF = 'booking.manage_staff';

    public const MANAGE_STAFF_SCHEDULE = 'booking.manage_staff_schedule';

    public const MANAGE_STAFF_TIME_OFF = 'booking.manage_staff_time_off';

    public const FINANCE_VIEW = 'booking.finance.view';

    public const FINANCE_MANAGE = 'booking.finance.manage';

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::READ,
            self::CALENDAR_VIEW,
            self::CREATE,
            self::STATUS_UPDATE,
            self::RESCHEDULE,
            self::CANCEL,
            self::ASSIGN,
            self::MANAGE_SERVICES,
            self::MANAGE_STAFF,
            self::MANAGE_STAFF_SCHEDULE,
            self::MANAGE_STAFF_TIME_OFF,
            self::FINANCE_VIEW,
            self::FINANCE_MANAGE,
        ];
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    public static function systemRoleBlueprint(): array
    {
        return [
            'owner' => [
                'label' => 'Owner',
                'description' => 'Full booking access including finance and configuration.',
            ],
            'manager' => [
                'label' => 'Manager',
                'description' => 'Operationally complete booking access for team management.',
            ],
            'receptionist' => [
                'label' => 'Receptionist',
                'description' => 'Booking lifecycle handling without structural configuration.',
            ],
            'staff' => [
                'label' => 'Staff',
                'description' => 'Read-only booking access for calendar and appointment visibility.',
            ],
        ];
    }

    /**
     * @return array<string,array<string,bool>>
     */
    public static function systemRolePermissionMatrix(): array
    {
        return [
            'owner' => array_fill_keys(self::all(), true),
            'manager' => array_fill_keys(self::all(), true),
            'receptionist' => [
                self::READ => true,
                self::CALENDAR_VIEW => true,
                self::CREATE => true,
                self::STATUS_UPDATE => true,
                self::RESCHEDULE => true,
                self::CANCEL => true,
                self::ASSIGN => true,
                self::MANAGE_SERVICES => false,
                self::MANAGE_STAFF => false,
                self::MANAGE_STAFF_SCHEDULE => false,
                self::MANAGE_STAFF_TIME_OFF => false,
                self::FINANCE_VIEW => true,
                self::FINANCE_MANAGE => false,
            ],
            'staff' => [
                self::READ => true,
                self::CALENDAR_VIEW => true,
                self::CREATE => false,
                self::STATUS_UPDATE => false,
                self::RESCHEDULE => false,
                self::CANCEL => false,
                self::ASSIGN => false,
                self::MANAGE_SERVICES => false,
                self::MANAGE_STAFF => false,
                self::MANAGE_STAFF_SCHEDULE => false,
                self::MANAGE_STAFF_TIME_OFF => false,
                self::FINANCE_VIEW => false,
                self::FINANCE_MANAGE => false,
            ],
        ];
    }
}

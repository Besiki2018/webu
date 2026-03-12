<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_staff_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('site_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'user_id']);
            $table->index(['site_id', 'role_id']);
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('booking_staff_roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });

        $this->bootstrapSystemRolesAndAssignments();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_staff_role_assignments');
    }

    private function bootstrapSystemRolesAndAssignments(): void
    {
        if (
            ! Schema::hasTable('sites')
            || ! Schema::hasTable('projects')
            || ! Schema::hasTable('booking_staff_roles')
            || ! Schema::hasTable('booking_staff_role_permissions')
            || ! Schema::hasTable('booking_staff_role_assignments')
        ) {
            return;
        }

        $roles = $this->roleBlueprint();
        $matrix = $this->permissionMatrix();
        $now = now();

        $sites = DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->select([
                'sites.id as site_id',
                'sites.project_id as project_id',
                'projects.user_id as owner_id',
            ])
            ->get();

        foreach ($sites as $site) {
            $siteRoleIds = DB::table('booking_staff_roles')
                ->where('site_id', $site->site_id)
                ->whereIn('key', array_keys($roles))
                ->pluck('id', 'key')
                ->all();

            foreach ($roles as $roleKey => $roleMeta) {
                if (! array_key_exists($roleKey, $siteRoleIds)) {
                    $siteRoleIds[$roleKey] = DB::table('booking_staff_roles')->insertGetId([
                        'site_id' => $site->site_id,
                        'key' => $roleKey,
                        'label' => $roleMeta['label'],
                        'description' => $roleMeta['description'],
                        'is_system' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $roleId = (int) $siteRoleIds[$roleKey];
                foreach ($matrix[$roleKey] as $permissionKey => $allowed) {
                    DB::table('booking_staff_role_permissions')->updateOrInsert(
                        [
                            'site_id' => $site->site_id,
                            'role_id' => $roleId,
                            'permission_key' => $permissionKey,
                        ],
                        [
                            'allowed' => (bool) $allowed,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            if (isset($siteRoleIds['owner']) && ! empty($site->owner_id)) {
                DB::table('booking_staff_role_assignments')->updateOrInsert(
                    [
                        'site_id' => $site->site_id,
                        'user_id' => (int) $site->owner_id,
                    ],
                    [
                        'role_id' => (int) $siteRoleIds['owner'],
                        'assigned_by' => (int) $site->owner_id,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            if (! Schema::hasTable('project_shares')) {
                continue;
            }

            $shares = DB::table('project_shares')
                ->where('project_id', $site->project_id)
                ->get(['user_id', 'permission']);

            foreach ($shares as $share) {
                $roleKey = match ((string) $share->permission) {
                    'admin', 'edit' => 'manager',
                    default => 'staff',
                };

                if (! isset($siteRoleIds[$roleKey])) {
                    continue;
                }

                DB::table('booking_staff_role_assignments')->updateOrInsert(
                    [
                        'site_id' => $site->site_id,
                        'user_id' => (int) $share->user_id,
                    ],
                    [
                        'role_id' => (int) $siteRoleIds[$roleKey],
                        'assigned_by' => ! empty($site->owner_id) ? (int) $site->owner_id : null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * @return array<string,array{label:string,description:string}>
     */
    private function roleBlueprint(): array
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
    private function permissionMatrix(): array
    {
        return [
            'owner' => [
                'booking.read' => true,
                'booking.calendar.view' => true,
                'booking.create' => true,
                'booking.status.update' => true,
                'booking.reschedule' => true,
                'booking.cancel' => true,
                'booking.assign' => true,
                'booking.manage_services' => true,
                'booking.manage_staff' => true,
                'booking.manage_staff_schedule' => true,
                'booking.manage_staff_time_off' => true,
                'booking.finance.view' => true,
                'booking.finance.manage' => true,
            ],
            'manager' => [
                'booking.read' => true,
                'booking.calendar.view' => true,
                'booking.create' => true,
                'booking.status.update' => true,
                'booking.reschedule' => true,
                'booking.cancel' => true,
                'booking.assign' => true,
                'booking.manage_services' => true,
                'booking.manage_staff' => true,
                'booking.manage_staff_schedule' => true,
                'booking.manage_staff_time_off' => true,
                'booking.finance.view' => true,
                'booking.finance.manage' => true,
            ],
            'receptionist' => [
                'booking.read' => true,
                'booking.calendar.view' => true,
                'booking.create' => true,
                'booking.status.update' => true,
                'booking.reschedule' => true,
                'booking.cancel' => true,
                'booking.assign' => true,
                'booking.manage_services' => false,
                'booking.manage_staff' => false,
                'booking.manage_staff_schedule' => false,
                'booking.manage_staff_time_off' => false,
                'booking.finance.view' => true,
                'booking.finance.manage' => false,
            ],
            'staff' => [
                'booking.read' => true,
                'booking.calendar.view' => true,
                'booking.create' => false,
                'booking.status.update' => false,
                'booking.reschedule' => false,
                'booking.cancel' => false,
                'booking.assign' => false,
                'booking.manage_services' => false,
                'booking.manage_staff' => false,
                'booking.manage_staff_schedule' => false,
                'booking.manage_staff_time_off' => false,
                'booking.finance.view' => false,
                'booking.finance.manage' => false,
            ],
        ];
    }
};

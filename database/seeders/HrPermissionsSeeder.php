<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * HRMS Implementation Plan - Section 9.1
 * HR Permissions Seeder
 *
 * Two-Level Leave Approval Workflow:
 * 1. First Level: Unit Head (same department) OR Dept Head (same user category)
 * 2. Second Level: HR Manager (only after first level approved)
 */
class HrPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Leave Management
            'leave-type.view',
            'leave-type.create',
            'leave-type.edit',
            'leave-type.delete',

            'leave-request.view',
            'leave-request.view-own',
            'leave-request.create',
            'leave-request.create-own',
            'leave-request.edit',
            'leave-request.delete',
            // Two-Level Approval Permissions
            'leave-request.supervisor-approve',  // First level: Unit Head / Dept Head
            'leave-request.hr-approve',          // Second level: HR Manager
            'leave-request.approve',             // Legacy - can approve at any level
            'leave-request.reject',
            'leave-request.recall',

            'leave-balance.view',
            'leave-balance.manage',

            // Disciplinary Management
            'disciplinary.view',
            'disciplinary.create',
            'disciplinary.edit',
            'disciplinary.delete',
            'disciplinary.respond',
            'disciplinary.decide',

            // Suspension Management
            'suspension.view',
            'suspension.create',
            'suspension.lift',

            // Termination Management
            'termination.view',
            'termination.create',
            'termination.edit',

            // Payroll Management
            'pay-head.view',
            'pay-head.create',
            'pay-head.edit',
            'pay-head.delete',

            'salary-profile.view',
            'salary-profile.create',
            'salary-profile.edit',
            'salary-profile.delete',

            'payroll-batch.view',
            'payroll-batch.create',
            'payroll-batch.edit',
            'payroll-batch.delete',
            'payroll-batch.submit',
            'payroll-batch.approve',
            'payroll-batch.reject',

            // HR Reports
            'hr-report.view',
            'hr-report.export',

            // HR Workbench Access
            'hr-workbench.access',

            // Employee Self Service
            'ess.access',
            'ess.view-payslips',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin - Full access
        $adminRole = Role::where('name', 'ADMIN')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(Permission::all());
        }

        // Superadmin - Full access
        $superadminRole = Role::where('name', 'SUPERADMIN')->first();
        if ($superadminRole) {
            $superadminRole->givePermissionTo(Permission::all());
        }

        // HR Manager role - Second Level Approval + Full HR Management
        $hrManagerRole = Role::firstOrCreate(['name' => 'HR MANAGER', 'guard_name' => 'web']);
        $hrManagerRole->givePermissionTo([
            // Leave Management - Full
            'leave-type.view',
            'leave-type.create',
            'leave-type.edit',
            'leave-type.delete',
            'leave-request.view',
            'leave-request.view-own',
            'leave-request.create',
            'leave-request.create-own',
            'leave-request.edit',
            'leave-request.delete',
            'leave-request.hr-approve',      // Second level approval
            'leave-request.approve',
            'leave-request.reject',
            'leave-request.recall',
            'leave-balance.view',
            'leave-balance.manage',

            // Disciplinary - Full
            'disciplinary.view',
            'disciplinary.create',
            'disciplinary.edit',
            'disciplinary.delete',
            'disciplinary.decide',

            // Suspensions & Terminations
            'suspension.view',
            'suspension.create',
            'suspension.lift',
            'termination.view',
            'termination.create',
            'termination.edit',

            // Payroll - Full
            'pay-head.view',
            'pay-head.create',
            'pay-head.edit',
            'pay-head.delete',
            'salary-profile.view',
            'salary-profile.create',
            'salary-profile.edit',
            'salary-profile.delete',
            'payroll-batch.view',
            'payroll-batch.create',
            'payroll-batch.edit',
            'payroll-batch.delete',
            'payroll-batch.submit',

            // Reports & Workbench
            'hr-report.view',
            'hr-report.export',
            'hr-workbench.access',
            'ess.access',
            'ess.view-payslips',
        ]);

        // Payroll Approver role
        $payrollApproverRole = Role::firstOrCreate(['name' => 'PAYROLL APPROVER', 'guard_name' => 'web']);
        $payrollApproverRole->givePermissionTo([
            'payroll-batch.view',
            'payroll-batch.approve',
            'payroll-batch.reject',
            'hr-report.view',
        ]);

        // Note: Unit Head and Dept Head permissions are granted based on is_unit_head and is_dept_head flags
        // in the Staff model, not by role. The canBeApprovedBySupervisor() method in LeaveRequest model
        // checks these flags to determine if a user can approve as first level.
        // However, we still need the permission to access the approval routes.

        // Grant supervisor-approve permission to any staff who might be unit/dept head
        // This will be checked against the is_unit_head/is_dept_head flags at runtime
        $staffRoles = Role::whereIn('name', [
            'DOCTOR', 'NURSE', 'PHARMACIST', 'LAB SCIENTIST', 'RADIOLOGIST',
            'RECEPTIONIST', 'BILLER', 'ACCOUNTS', 'HR MANAGER'
        ])->get();

        foreach ($staffRoles as $role) {
            $role->givePermissionTo([
                // ESS permissions for all staff
                'ess.access',
                'ess.view-payslips',
                'leave-request.view-own',
                'leave-request.create-own',
                'disciplinary.respond',
                // First-level approval permission (actual approval checked against flags)
                'leave-request.supervisor-approve',
                'leave-request.reject',
            ]);
        }
    }
}

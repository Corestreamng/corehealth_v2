<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the CoreHealth native client's workspaces.
 *
 * Mirrors resources/views/admin/partials/sidebar.blade.php (and its _nav-* partials): same
 * section order, same role guards (@hasanyrole), same nested permission checks (@can / @canany)
 * and the same computed conditions. Tests/Feature/NativeClient/WorkspaceRegistryParityTest
 * fails if a section's role guard drifts from the sidebar, so change both together.
 *
 * Guard keys (all present keys must pass):
 *   roles          => user has any of these roles (Spatie hasAnyRole)
 *   permission     => user can() this permission
 *   any_permission => user can() at least one of these
 *   condition      => named rule from conditionPasses() (sidebar @if blocks)
 *
 * Item `key` is what the native client maps to a native screen; items with no native screen
 * open their `route` on the web.
 */
class WorkspaceRegistry
{
    public const ADMINS = ['SUPERADMIN', 'ADMIN', 'super-admin'];

    /**
     * Sections in sidebar order. `partial` names the sidebar file the section mirrors.
     */
    public static function sections(): array
    {
        $svc = fn (string $setting, int $default) => ['category' => appsettings($setting, $default)];

        return [
            [
                'key' => 'global', 'partial' => '_nav-global', 'label' => 'sidebar.dashboard', 'icon' => 'dashboard',
                'guard' => [],
                'items' => [
                    ['key' => 'global.dashboard', 'label' => 'sidebar.dashboard', 'route' => 'home'],
                    ['key' => 'global.messenger', 'label' => 'sidebar.messenger', 'route' => 'chat.index', 'guard' => ['condition' => 'chat_enabled']],
                ],
            ],
            [
                'key' => 'staff_workspace', 'partial' => '_nav-ess', 'label' => 'sidebar.staff_workspace', 'icon' => 'badge',
                'guard' => [],
                'items' => [
                    ['key' => 'ess.my_profile', 'label' => 'sidebar.my_profile', 'route' => 'hr.ess.my-profile', 'guard' => ['condition' => 'ess_access']],
                    ['key' => 'ess.dashboard', 'label' => 'sidebar.ess_dashboard', 'route' => 'hr.ess.index', 'guard' => ['condition' => 'ess_access']],
                    ['key' => 'ess.my_leave', 'label' => 'sidebar.my_leave', 'route' => 'hr.ess.my-leave', 'guard' => ['condition' => 'ess_access']],
                    ['key' => 'ess.my_payslips', 'label' => 'sidebar.my_payslips', 'route' => 'hr.ess.my-payslips', 'guard' => ['condition' => 'ess_access', 'permission' => 'ess.view-payslips']],
                    ['key' => 'ess.my_disciplinary', 'label' => 'sidebar.my_disciplinary', 'route' => 'hr.ess.my-disciplinary', 'guard' => ['condition' => 'ess_access']],
                    ['key' => 'ess.requisitions', 'label' => 'sidebar.requisitions', 'route' => 'inventory.requisitions.index'],
                    ['key' => 'ess.my_stock', 'label' => 'sidebar.my_stock', 'route' => 'inventory.requisitions.my-stock'],
                ],
            ],
            [
                'key' => 'reception', 'partial' => '_nav-reception', 'label' => 'sidebar.health_records', 'icon' => 'how_to_reg',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'RECEPTIONIST']],
                'items' => [
                    ['key' => 'reception.workbench', 'label' => 'sidebar.health_records_workbench', 'route' => 'reception.workbench'],
                    ['key' => 'reception.new_patient', 'label' => 'New Patient', 'route' => 'reception.workbench', 'params' => ['action' => 'new-patient']],
                    ['key' => 'reception.queue', 'label' => 'Triage & Intake Queue', 'route' => 'reception.workbench', 'params' => ['queue_filter' => 'waiting']],
                    ['key' => 'reception.all_encounters', 'label' => 'sidebar.all_encounters', 'route' => 'allPrevEncounters'],
                    ['key' => 'reception.nhmis', 'label' => 'sidebar.nhmis_monthly_report', 'route' => 'nhmis.workbench', 'guard' => ['condition' => 'nhmis_report']],
                ],
            ],
            [
                'key' => 'billing', 'partial' => '_nav-billing', 'label' => 'sidebar.billing', 'icon' => 'point_of_sale',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER']],
                'items' => [
                    ['key' => 'billing.workbench', 'label' => 'sidebar.billing_workbench', 'route' => 'billing.workbench'],
                ],
            ],
            [
                'key' => 'pharmacy', 'partial' => '_nav-pharmacy', 'label' => 'sidebar.pharmacy', 'icon' => 'medication',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'PHARMACIST']],
                'items' => [
                    ['key' => 'pharmacy.workbench', 'label' => 'sidebar.pharmacy_workbench', 'route' => 'pharmacy.workbench'],
                    ['key' => 'pharmacy.categories', 'label' => 'Categories', 'route' => 'product-category.index'],
                    ['key' => 'pharmacy.stores', 'label' => 'Stores', 'route' => 'stores.index'],
                    ['key' => 'pharmacy.products', 'label' => 'Products', 'route' => 'products.index'],
                ],
            ],
            [
                'key' => 'store', 'partial' => '_nav-store', 'label' => 'sidebar.store_inventory', 'icon' => 'inventory_2',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'STORE']],
                'items' => [
                    ['key' => 'store.workbench', 'label' => 'sidebar.store_workbench', 'route' => 'inventory.store-workbench.index'],
                    ['key' => 'store.categories', 'label' => 'Categories', 'route' => 'product-category.index'],
                    ['key' => 'store.stores', 'label' => 'Stores', 'route' => 'stores.index'],
                    ['key' => 'store.products', 'label' => 'Products', 'route' => 'products.index'],
                    ['key' => 'store.purchase_orders', 'label' => 'sidebar.purchase_orders', 'route' => 'inventory.purchase-orders.index'],
                    ['key' => 'store.po_returns', 'label' => 'PO Returns', 'route' => 'inventory.po-returns.index'],
                    ['key' => 'store.requisitions', 'label' => 'sidebar.requisitions', 'route' => 'inventory.requisitions.index'],
                    ['key' => 'store.my_stock', 'label' => 'sidebar.my_stock', 'route' => 'inventory.requisitions.my-stock'],
                    ['key' => 'store.expenses', 'label' => 'sidebar.expenses', 'route' => 'inventory.expenses.index'],
                    ['key' => 'store.governance', 'label' => 'sidebar.store_governance', 'route' => 'inventory.config.store-governance.index', 'guard' => ['permission' => 'store-governance.view']],
                ],
            ],
            [
                'key' => 'nursing', 'partial' => '_nav-nursing', 'label' => 'sidebar.nursing', 'icon' => 'vaccines',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'NURSE']],
                'items' => [
                    ['key' => 'nursing.workbench', 'label' => 'sidebar.nursing_workbench', 'route' => 'nursing-workbench.index'],
                    ['key' => 'nursing.maternity', 'label' => 'sidebar.maternity_workbench', 'route' => 'maternity-workbench.index', 'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'MATERNITY']]],
                    ['key' => 'nursing.beds', 'label' => 'sidebar.manage_beds', 'route' => 'beds.index'],
                ],
            ],
            [
                'key' => 'morgue', 'partial' => '_nav-morgue', 'label' => 'sidebar.morgue', 'icon' => 'airline_seat_flat',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'MORGUE']],
                'items' => [
                    ['key' => 'morgue.workbench', 'label' => 'sidebar.morgue_workbench', 'route' => 'morgue.workbench'],
                    ['key' => 'morgue.services', 'label' => 'sidebar.morgue_services', 'route' => 'services.index', 'params' => $svc('morgue_category_id', 9)],
                ],
            ],
            [
                'key' => 'maternity', 'partial' => '_nav-maternity', 'label' => 'sidebar.maternity', 'icon' => 'pregnant_woman',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'MATERNITY']],
                'items' => [
                    ['key' => 'maternity.workbench', 'label' => 'sidebar.maternity_workbench', 'route' => 'maternity-workbench.index'],
                ],
            ],
            [
                'key' => 'surgery', 'partial' => '_nav-surgery', 'label' => 'sidebar.surgery', 'icon' => 'medical_services',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'SURGERY', 'DOCTOR', 'NURSE']],
                'items' => [
                    ['key' => 'surgery.workbench', 'label' => 'sidebar.surgery_workbench', 'route' => 'surgery-workbench.index'],
                ],
            ],
            [
                'key' => 'lab', 'partial' => '_nav-lab', 'label' => 'sidebar.medical_laboratory', 'icon' => 'biotech',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'LAB SCIENTIST']],
                'items' => [
                    ['key' => 'lab.workbench', 'label' => 'sidebar.lab_workbench', 'route' => 'lab.workbench'],
                    ['key' => 'lab.services', 'label' => 'sidebar.lab_services', 'route' => 'services.index', 'params' => $svc('lab_category_id', 2)],
                ],
            ],
            [
                'key' => 'radiology', 'partial' => '_nav-radiology', 'label' => 'sidebar.imaging_radiology', 'icon' => 'radiology',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'RADIOLOGIST']],
                'items' => [
                    ['key' => 'radiology.workbench', 'label' => 'sidebar.imaging_workbench', 'route' => 'imaging.workbench'],
                    ['key' => 'radiology.services', 'label' => 'Imaging Services', 'route' => 'services.index', 'params' => $svc('imaging_category_id', 6)],
                ],
            ],
            [
                'key' => 'doctor', 'partial' => '_nav-doctor', 'label' => 'sidebar.doctors', 'icon' => 'stethoscope',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'DOCTOR']],
                'items' => [
                    ['key' => 'doctor.my_queues', 'label' => 'sidebar.all_encounters', 'route' => 'encounters.index'],
                    ['key' => 'doctor.waiting_queue', 'label' => 'Waiting Queue', 'route' => 'encounters.index', 'params' => ['status' => 'waiting']],
                    ['key' => 'doctor.maternity', 'label' => 'sidebar.maternity_workbench', 'route' => 'maternity-workbench.index', 'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'MATERNITY']]],
                ],
            ],
            [
                'key' => 'hmo', 'partial' => '_nav-hmo', 'label' => 'sidebar.hmo_executive', 'icon' => 'health_and_safety',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'HMO Executive']],
                'items' => [
                    ['key' => 'hmo.workbench', 'label' => 'sidebar.hmo_workbench', 'route' => 'hmo.workbench'],
                    ['key' => 'hmo.reports', 'label' => 'sidebar.hmo_reports', 'route' => 'hmo.reports'],
                    ['key' => 'hmo.patients', 'label' => 'sidebar.patients', 'route' => 'patient.index', 'params' => ['hmo_only' => 1]],
                    ['key' => 'hmo.tariffs', 'label' => 'HMO Tariffs', 'route' => 'hmo-tariffs.index', 'guard' => ['condition' => 'hmo_mgmt']],
                    ['key' => 'hmo.hmos', 'label' => 'HMOs', 'route' => 'hmo.index', 'guard' => ['condition' => 'hmo_mgmt']],
                    ['key' => 'hmo.organizations', 'label' => 'sidebar.billing_organizations', 'route' => 'organizations.index', 'guard' => ['condition' => 'hmo_mgmt']],
                ],
            ],
            [
                'key' => 'accounting', 'partial' => '_nav-accounting', 'label' => 'sidebar.accounting', 'icon' => 'account_balance',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'super-admin', 'ACCOUNTS']],
                'items' => self::simpleItems('accounting', [
                    ['dashboard', 'sidebar.dashboard', 'accounting.dashboard'],
                    ['journal_entries', 'sidebar.journal_entries', 'accounting.journal-entries.index'],
                    ['chart_of_accounts', 'sidebar.chart_of_accounts', 'accounting.chart-of-accounts.index'],
                    ['reports', 'sidebar.reports', 'accounting.reports.index'],
                    ['credit_notes', 'sidebar.credit_notes', 'accounting.credit-notes.index'],
                    ['fiscal_periods', 'sidebar.fiscal_periods', 'accounting.periods'],
                    ['accounts_payable', 'sidebar.accounts_payable', 'inventory.purchase-orders.accounts-payable'],
                    ['expenses', 'sidebar.expenses', 'inventory.expenses.index'],
                    ['banks', 'sidebar.banks', 'banks.index'],
                    ['petty_cash', 'sidebar.petty_cash', 'accounting.petty-cash.index'],
                    ['bank_transfers', 'sidebar.bank_transfers', 'accounting.transfers.index'],
                    ['bank_reconciliation', 'sidebar.bank_reconciliation', 'accounting.bank-reconciliation.index'],
                    ['patient_deposits', 'sidebar.patient_deposits', 'accounting.patient-deposits.index'],
                    ['fixed_assets', 'sidebar.fixed_assets', 'accounting.fixed-assets.index'],
                    ['liabilities', 'sidebar.liabilities', 'accounting.liabilities.index'],
                    ['leases', 'Leases', 'accounting.leases.index'],
                    ['cost_centers', 'sidebar.cost_centers', 'accounting.cost-centers.index'],
                    ['capex_projects', 'sidebar.capex_projects', 'accounting.capex.index'],
                    ['budgets', 'sidebar.budgets', 'accounting.budgets.index'],
                    ['cash_flow_forecast', 'sidebar.cash_flow_forecast', 'accounting.cash-flow-forecast.index'],
                    ['financial_kpis', 'sidebar.financial_kpis', 'accounting.kpi.dashboard'],
                ]),
            ],
            [
                'key' => 'hr', 'partial' => '_nav-hr', 'label' => 'sidebar.human_resources', 'icon' => 'groups',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'super-admin', 'HR MANAGER']],
                'items' => [
                    ['key' => 'hr.workbench', 'label' => 'sidebar.hr_workbench', 'route' => 'hr.workbench.index', 'guard' => ['permission' => 'hr-workbench.access']],
                    ['key' => 'hr.leave_calendar', 'label' => 'sidebar.leave_calendar', 'route' => 'hr.leave-calendar.index', 'guard' => ['permission' => 'leave-request.view']],
                    ['key' => 'hr.leave_types', 'label' => 'Leave Types', 'route' => 'hr.leave-types.index', 'guard' => ['permission' => 'leave-type.view']],
                    ['key' => 'hr.leave_requests', 'label' => 'Leave Requests', 'route' => 'hr.leave-requests.index', 'guard' => ['permission' => 'leave-request.view']],
                    ['key' => 'hr.leave_balances', 'label' => 'Leave Balances', 'route' => 'hr.leave-balances.index', 'guard' => ['permission' => 'leave-balance.view']],
                    ['key' => 'hr.disciplinary', 'label' => 'sidebar.disciplinary', 'route' => 'hr.disciplinary.index', 'guard' => ['permission' => 'disciplinary.view']],
                    ['key' => 'hr.suspensions', 'label' => 'Suspensions', 'route' => 'hr.suspensions.index', 'guard' => ['permission' => 'suspension.view']],
                    ['key' => 'hr.terminations', 'label' => 'Terminations', 'route' => 'hr.terminations.index', 'guard' => ['permission' => 'termination.view']],
                    ['key' => 'hr.pay_heads', 'label' => 'Pay Heads', 'route' => 'hr.pay-heads.index', 'guard' => ['permission' => 'pay-head.view']],
                    ['key' => 'hr.salary_profiles', 'label' => 'Salary Profiles', 'route' => 'hr.salary-profiles.index', 'guard' => ['permission' => 'salary-profile.view']],
                    ['key' => 'hr.payroll', 'label' => 'sidebar.payroll', 'route' => 'hr.payroll.index', 'guard' => ['permission' => 'payroll-batch.view']],
                    ['key' => 'hr.staff_registry', 'label' => 'sidebar.staff_registry', 'route' => 'hr.staff-registry.index', 'guard' => ['permission' => 'hr-staff-registry.view']],
                    ['key' => 'hr.promotions', 'label' => 'Promotions', 'route' => 'hr.promotions.index', 'guard' => ['permission' => 'hr-promotions.view']],
                    ['key' => 'hr.qualifications', 'label' => 'Qualifications', 'route' => 'hr.qualifications.index', 'guard' => ['permission' => 'hr-qualifications.view']],
                    ['key' => 'hr.trainings', 'label' => 'Trainings', 'route' => 'hr.trainings.index', 'guard' => ['permission' => 'hr-trainings.view']],
                    ['key' => 'hr.medical_exams', 'label' => 'Medical Exams', 'route' => 'hr.medical-exams.index', 'guard' => ['permission' => 'hr-medical-exams.view']],
                    ['key' => 'hr.follow_ups', 'label' => 'Follow-ups', 'route' => 'hr.follow-ups.index', 'guard' => ['permission' => 'hr-follow-ups.view']],
                    ['key' => 'hr.units', 'label' => 'Units', 'route' => 'hr.units.index', 'guard' => ['permission' => 'hr-units.view']],
                    ['key' => 'hr.cadres', 'label' => 'Cadres', 'route' => 'hr.cadres.index', 'guard' => ['permission' => 'hr-cadres.view']],
                    ['key' => 'hr.grade_levels', 'label' => 'Grade Levels', 'route' => 'hr.grade-levels.index', 'guard' => ['permission' => 'hr-grade-levels.view']],
                ],
            ],
            [
                'key' => 'audit', 'partial' => '_nav-audit', 'label' => 'sidebar.internal_audit', 'icon' => 'fact_check',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'super-admin', 'AUDITOR', 'AUDIT']],
                'items' => [
                    ['key' => 'audit.workbench', 'label' => 'sidebar.audit_workbench', 'route' => 'ops-audit.reception'],
                ],
            ],
            [
                'key' => 'admin', 'partial' => '_nav-admin', 'label' => 'sidebar.administration', 'icon' => 'admin_panel_settings',
                'guard' => ['roles' => ['SUPERADMIN', 'ADMIN', 'super-admin']],
                'items' => array_merge(self::simpleItems('admin', [
                    ['roles', 'Roles', 'roles.index'],
                    ['permissions', 'Permissions', 'permissions.index'],
                    ['hospital_config', 'sidebar.hospital_config', 'hospital-config.index'],
                    ['note_templates', 'sidebar.note_templates', 'clinic-note-templates.index'],
                    ['result_templates', 'sidebar.result_templates', 'v1-result-templates.index'],
                    ['wards', 'sidebar.wards', 'wards.index'],
                    ['beds', 'sidebar.beds', 'beds.index'],
                    ['checklist_templates', 'sidebar.checklist_templates', 'checklist-templates.index'],
                    ['vaccine_schedule', 'sidebar.vaccine_schedule', 'vaccine-schedule.index'],
                    ['bank_config', 'sidebar.bank_config', 'banks.index'],
                    ['hmo_tariffs', 'HMO Tariffs', 'hmo-tariffs.index'],
                    ['hmos', 'HMOs', 'hmo.index'],
                    ['organizations', 'sidebar.billing_organizations', 'organizations.index'],
                ]), [
                    ['key' => 'admin.store_governance', 'label' => 'sidebar.store_governance', 'route' => 'inventory.config.store-governance.index', 'guard' => ['permission' => 'store-governance.manage']],
                ], self::simpleItems('admin', [
                    ['audit_logs', 'sidebar.audit_logs', 'audit-logs.index'],
                    ['import_export', 'sidebar.data_import_export', 'import-export.index'],
                    ['backups', 'sidebar.database_backups', 'backups.index'],
                    ['slow_queries', 'sidebar.slow_query_monitor', 'admin.slow_queries.index'],
                    ['staff', 'sidebar.staff_management', 'staff.index'],
                    ['locums', 'Locums', 'locum.index'],
                    ['specializations', 'sidebar.specializations', 'specializations.index'],
                    ['clinics', 'sidebar.clinics_management', 'clinics.index'],
                    ['clinic_schedules', 'sidebar.clinic_schedules', 'clinic-schedules.index'],
                    ['departments', 'sidebar.departments', 'departments.index'],
                    ['all_encounters', 'sidebar.all_encounters', 'allPrevEncounters'],
                    ['nhmis', 'sidebar.nhmis_monthly_report', 'nhmis.workbench'],
                    ['all_patients', 'sidebar.all_patients', 'patient.index'],
                    ['add_patient', 'sidebar.add_patient', 'reception.workbench', ['action' => 'new-patient']],
                    ['transactions', 'Transactions', 'transactions'],
                    ['accounts_payable', 'sidebar.accounts_payable', 'inventory.purchase-orders.accounts-payable'],
                    ['all_services', 'sidebar.all_services', 'services.index'],
                    ['service_categories', 'sidebar.categories', 'services-category.index'],
                    ['lab_services', 'Investigation Services', 'services.index', $svc('investigation_category_id', 2)],
                    ['imaging_services', 'Imaging Services', 'services.index', $svc('imaging_category_id', 6)],
                ]), [
                    ['key' => 'admin.procedure_services', 'label' => 'Procedure Services', 'route' => 'services.index', 'params' => ['category' => appsettings('procedure_category_id')], 'guard' => ['condition' => 'procedure_category_set']],
                    ['key' => 'admin.procedure_categories', 'label' => 'sidebar.procedure_categories', 'route' => 'procedure-categories.index'],
                ]),
            ],
        ];
    }

    /** @param array<int, array{0:string,1:string,2:string,3?:array}> $rows */
    protected static function simpleItems(string $section, array $rows): array
    {
        return array_map(fn ($r) => ['key' => "{$section}.{$r[0]}", 'label' => $r[1], 'route' => $r[2], 'params' => $r[3] ?? []], $rows);
    }

    public static function section(string $key): ?array
    {
        foreach (self::sections() as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }

        return null;
    }

    /** Can this user use the section (the same check as the sidebar's outer guard)? */
    public static function allowsSection(?User $user, string $key): bool
    {
        $section = self::section($key);

        return $user && $section && self::passes($user, $section['guard']);
    }

    /**
     * Sections and items visible to the user, labels translated and web URLs resolved.
     * Sections whose items are all hidden are omitted, as on the web.
     */
    public static function visibleFor(User $user): array
    {
        $out = [];
        foreach (self::sections() as $section) {
            if (!self::passes($user, $section['guard'])) {
                continue;
            }
            $items = [];
            foreach ($section['items'] as $item) {
                if (!self::passes($user, $item['guard'] ?? [])) {
                    continue;
                }
                $items[] = [
                    'key' => $item['key'],
                    'label' => self::label($item['label']),
                    'web_url' => Route::has($item['route']) ? route($item['route'], $item['params'] ?? []) : null,
                ];
            }
            if ($items) {
                $out[] = [
                    'key' => $section['key'],
                    'label' => self::label($section['label']),
                    'icon' => $section['icon'],
                    'items' => $items,
                ];
            }
        }

        return $out;
    }

    public static function passes(User $user, array $guard): bool
    {
        if (!empty($guard['roles']) && !$user->hasAnyRole($guard['roles'])) {
            return false;
        }
        if (!empty($guard['permission']) && !$user->can($guard['permission'])) {
            return false;
        }
        if (!empty($guard['any_permission']) && !collect($guard['any_permission'])->contains(fn ($p) => $user->can($p))) {
            return false;
        }
        if (!empty($guard['condition']) && !self::conditionPasses($user, $guard['condition'])) {
            return false;
        }

        return true;
    }

    /**
     * The sidebar's computed @if conditions, reproduced exactly.
     */
    public static function conditionPasses(User $user, string $condition): bool
    {
        $staff = $user->staff_profile;
        $isHead = $staff && ($staff->is_unit_head || $staff->is_dept_head);

        return match ($condition) {
            'chat_enabled' => (bool) (appsettings('chat_enabled') ?? true),
            'ess_access' => !empty($staff) && $user->can('ess.access'),
            'nhmis_report' => $user->hasAnyRole(self::ADMINS) || ($user->hasAnyRole(['RECEPTIONIST', 'Receptionist']) && $isHead),
            'hmo_mgmt' => $user->hasAnyRole(['SUPERADMIN', 'ADMIN']) || ($user->hasRole('HMO Executive') && $isHead),
            'procedure_category_set' => (bool) appsettings('procedure_category_id'),
            default => false,
        };
    }

    protected static function label(string $label): string
    {
        return str_starts_with($label, 'sidebar.') ? __($label) : $label;
    }
}

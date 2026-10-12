{{-- ========================================
     HUMAN RESOURCES SECTION
     ======================================== --}}
@hasanyrole('SUPERADMIN|ADMIN|super-admin|HR MANAGER')
<li class="ch-sidebar-section-header pt-2 pb-1">
    <span class="nav-item-head">{{ __('sidebar.human_resources') }}</span>
</li>
@can('hr-workbench.access')
<li class="nav-item {{ request()->routeIs('hr.workbench.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.workbench.*') ? 'active' : '' }}" href="{{ route('hr.workbench.index') }}" id="sidebar-hr-workbench">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.hr_workbench') }}</span>
    </a>
</li>
@endcan
@canany(['leave-type.view', 'leave-request.view', 'leave-balance.view'])
<li class="nav-item {{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-leave" data-bs-target="#sidebar-hr-leave" aria-expanded="{{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-leave" id="sidebar-hr-leave-toggle">
        <i class="mdi mdi-calendar-clock menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.leave_management') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.leave-types.*', 'hr.leave-requests.*', 'hr.leave-balances.*', 'hr.leave-calendar.*') ? 'show' : '' }}" id="sidebar-hr-leave">
        <ul class="nav flex-column sub-menu">
            @can('leave-request.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave-calendar.*') ? 'active' : '' }}" href="{{ route('hr.leave-calendar.index') }}" id="sidebar-hr-leave-calendar">
                    <i class="mdi mdi-calendar-month mr-1"></i> {{ __('sidebar.leave_calendar') }}</a>
            </li>
            @endcan
            @can('leave-type.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave-types.*') ? 'active' : '' }}" href="{{ route('hr.leave-types.index') }}" id="sidebar-hr-leave-types">
                    Leave Types
                </a>
            </li>
            @endcan
            @can('leave-request.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave-requests.*') ? 'active' : '' }}" href="{{ route('hr.leave-requests.index') }}" id="sidebar-hr-leave-requests">
                    Leave Requests
                </a>
            </li>
            @endcan
            @can('leave-balance.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.leave-balances.*') ? 'active' : '' }}" href="{{ route('hr.leave-balances.index') }}" id="sidebar-hr-leave-balances">
                    Leave Balances
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
@canany(['disciplinary.view', 'suspension.view', 'termination.view'])
<li class="nav-item {{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-disciplinary" data-bs-target="#sidebar-hr-disciplinary" aria-expanded="{{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-disciplinary" id="sidebar-hr-disciplinary-toggle">
        <i class="mdi mdi-gavel menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.disciplinary') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.disciplinary.*', 'hr.suspensions.*', 'hr.terminations.*') ? 'show' : '' }}" id="sidebar-hr-disciplinary">
        <ul class="nav flex-column sub-menu">
            @can('disciplinary.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.disciplinary.*') ? 'active' : '' }}" href="{{ route('hr.disciplinary.index') }}" id="sidebar-hr-disciplinary-queries">
                    Queries
                </a>
            </li>
            @endcan
            @can('suspension.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.suspensions.*') ? 'active' : '' }}" href="{{ route('hr.suspensions.index') }}" id="sidebar-hr-suspensions">
                    Suspensions
                </a>
            </li>
            @endcan
            @can('termination.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.terminations.*') ? 'active' : '' }}" href="{{ route('hr.terminations.index') }}" id="sidebar-hr-terminations">
                    Terminations
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
@canany(['pay-head.view', 'salary-profile.view', 'payroll-batch.view'])
<li class="nav-item {{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-payroll" data-bs-target="#sidebar-hr-payroll" aria-expanded="{{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-payroll" id="sidebar-hr-payroll-toggle">
        <i class="mdi mdi-cash-multiple menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.payroll') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.pay-heads.*', 'hr.salary-profiles.*', 'hr.payroll.*') ? 'show' : '' }}" id="sidebar-hr-payroll">
        <ul class="nav flex-column sub-menu">
            @can('pay-head.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.pay-heads.*') ? 'active' : '' }}" href="{{ route('hr.pay-heads.index') }}" id="sidebar-hr-pay-heads">
                    Pay Heads
                </a>
            </li>
            @endcan
            @can('salary-profile.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.salary-profiles.*') ? 'active' : '' }}" href="{{ route('hr.salary-profiles.index') }}" id="sidebar-hr-salary-profiles">
                    Salary Profiles
                </a>
            </li>
            @endcan
            @can('payroll-batch.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.payroll.*') ? 'active' : '' }}" href="{{ route('hr.payroll.index') }}" id="sidebar-hr-payroll-batches">
                    Payroll Batches
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
@can('hr-staff-registry.view')
<li class="nav-item {{ request()->routeIs('hr.staff-registry.*') ? 'active' : '' }}">
    <a class="nav-link {{ request()->routeIs('hr.staff-registry.*') ? 'active' : '' }}" href="{{ route('hr.staff-registry.index') }}" id="sidebar-hr-staff-registry">
        <i class="mdi mdi-clipboard-list-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.staff_registry') }}</span>
    </a>
</li>
@endcan
@canany(['hr-promotions.view', 'hr-qualifications.view', 'hr-trainings.view', 'hr-medical-exams.view', 'hr-follow-ups.view'])
<li class="nav-item {{ request()->routeIs('hr.promotions.*', 'hr.qualifications.*', 'hr.trainings.*', 'hr.medical-exams.*', 'hr.follow-ups.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-tracking" data-bs-target="#sidebar-hr-tracking" aria-expanded="{{ request()->routeIs('hr.promotions.*', 'hr.qualifications.*', 'hr.trainings.*', 'hr.medical-exams.*', 'hr.follow-ups.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-tracking" id="sidebar-hr-tracking-toggle">
        <i class="mdi mdi-account-clock-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.staff_tracking') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.promotions.*', 'hr.qualifications.*', 'hr.trainings.*', 'hr.medical-exams.*', 'hr.follow-ups.*') ? 'show' : '' }}" id="sidebar-hr-tracking">
        <ul class="nav flex-column sub-menu">
            @can('hr-promotions.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.promotions.*') ? 'active' : '' }}" href="{{ route('hr.promotions.index') }}" id="sidebar-hr-promotions">
                    Promotions
                </a>
            </li>
            @endcan
            @can('hr-qualifications.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.qualifications.*') ? 'active' : '' }}" href="{{ route('hr.qualifications.index') }}" id="sidebar-hr-qualifications">
                    Qualifications
                </a>
            </li>
            @endcan
            @can('hr-trainings.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.trainings.*') ? 'active' : '' }}" href="{{ route('hr.trainings.index') }}" id="sidebar-hr-trainings">
                    Trainings
                </a>
            </li>
            @endcan
            @can('hr-medical-exams.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.medical-exams.*') ? 'active' : '' }}" href="{{ route('hr.medical-exams.index') }}" id="sidebar-hr-medical-exams">
                    Medical Exams
                </a>
            </li>
            @endcan
            @can('hr-follow-ups.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.follow-ups.*') ? 'active' : '' }}" href="{{ route('hr.follow-ups.index') }}" id="sidebar-hr-follow-ups">
                    Follow-ups
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
@canany(['hr-units.view', 'hr-cadres.view', 'hr-grade-levels.view'])
<li class="nav-item {{ request()->routeIs('hr.units.*', 'hr.cadres.*', 'hr.grade-levels.*') ? 'active' : '' }}">
    <a class="nav-link" data-toggle="collapse" data-bs-toggle="collapse" href="javascript:void(0);" data-target="#sidebar-hr-config" data-bs-target="#sidebar-hr-config" aria-expanded="{{ request()->routeIs('hr.units.*', 'hr.cadres.*', 'hr.grade-levels.*') ? 'true' : 'false' }}" aria-controls="sidebar-hr-config" id="sidebar-hr-config-toggle">
        <i class="mdi mdi-cog-outline menu-icon"></i>
        <span class="menu-title">{{ __('sidebar.hr_configuration') }}</span>
        <i class="mdi mdi-chevron-right menu-arrow"></i>
    </a>
    <div class="collapse {{ request()->routeIs('hr.units.*', 'hr.cadres.*', 'hr.grade-levels.*') ? 'show' : '' }}" id="sidebar-hr-config">
        <ul class="nav flex-column sub-menu">
            @can('hr-units.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.units.*') ? 'active' : '' }}" href="{{ route('hr.units.index') }}" id="sidebar-hr-units">
                    Units
                </a>
            </li>
            @endcan
            @can('hr-cadres.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.cadres.*') ? 'active' : '' }}" href="{{ route('hr.cadres.index') }}" id="sidebar-hr-cadres">
                    Cadres
                </a>
            </li>
            @endcan
            @can('hr-grade-levels.view')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('hr.grade-levels.*') ? 'active' : '' }}" href="{{ route('hr.grade-levels.index') }}" id="sidebar-hr-grade-levels">
                    Grade Levels
                </a>
            </li>
            @endcan
        </ul>
    </div>
</li>
@endcanany
@endhasanyrole

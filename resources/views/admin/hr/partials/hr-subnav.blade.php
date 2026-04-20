{{-- HR Sub-Navigation Bar - Include in all HR module pages --}}
@php
    $navBtn = 'border-radius:6px;font-size:0.78rem;padding:0.35rem 0.7rem;font-weight:500;';
    $navDd  = 'border-radius:6px;font-size:0.78rem;padding:0.35rem 0.55rem 0.35rem 0.7rem;font-weight:500;';
    $ddItem = 'font-size:0.82rem;padding:0.4rem 1rem;';

    $hrGroups = [
        'leave' => ['match' => ['hr.leave-types.*','hr.leave-requests.*','hr.leave-balances.*','hr.leave-calendar.*'], 'icon' => 'mdi-calendar-clock', 'label' => 'Leave', 'items' => [
            ['route' => 'hr.leave-requests.index', 'icon' => 'mdi-file-document-edit', 'label' => 'Requests'],
            ['route' => 'hr.leave-balances.index', 'icon' => 'mdi-scale-balance', 'label' => 'Balances'],
            ['route' => 'hr.leave-calendar.index', 'icon' => 'mdi-calendar-month', 'label' => 'Calendar'],
            ['route' => 'hr.leave-types.index', 'icon' => 'mdi-format-list-bulleted-type', 'label' => 'Leave Types'],
        ]],
        'discipline' => ['match' => ['hr.disciplinary.*','hr.suspensions.*','hr.terminations.*'], 'icon' => 'mdi-gavel', 'label' => 'Discipline', 'items' => [
            ['route' => 'hr.disciplinary.index', 'icon' => 'mdi-alert-circle', 'label' => 'Queries'],
            ['route' => 'hr.suspensions.index', 'icon' => 'mdi-account-cancel', 'label' => 'Suspensions'],
            ['route' => 'hr.terminations.index', 'icon' => 'mdi-account-remove', 'label' => 'Terminations'],
        ]],
        'payroll' => ['match' => ['hr.pay-heads.*','hr.salary-profiles.*','hr.payroll.*'], 'icon' => 'mdi-cash-multiple', 'label' => 'Payroll', 'items' => [
            ['route' => 'hr.payroll.index', 'icon' => 'mdi-file-table', 'label' => 'Batches'],
            ['route' => 'hr.salary-profiles.index', 'icon' => 'mdi-account-cash', 'label' => 'Salary Profiles'],
            ['route' => 'hr.pay-heads.index', 'icon' => 'mdi-format-list-numbered', 'label' => 'Pay Heads'],
        ]],
        'tracking' => ['match' => ['hr.promotions.*','hr.qualifications.*','hr.trainings.*','hr.medical-exams.*','hr.follow-ups.*','hr.tracking.*'], 'icon' => 'mdi-account-search', 'label' => 'Tracking', 'items' => [
            ['route' => 'hr.promotions.index', 'icon' => 'mdi-arrow-up-bold-circle', 'label' => 'Promotions'],
            ['route' => 'hr.qualifications.index', 'icon' => 'mdi-school', 'label' => 'Qualifications'],
            ['route' => 'hr.trainings.index', 'icon' => 'mdi-certificate', 'label' => 'Trainings'],
            ['route' => 'hr.medical-exams.index', 'icon' => 'mdi-stethoscope', 'label' => 'Medical Exams'],
            ['route' => 'hr.follow-ups.index', 'icon' => 'mdi-clipboard-check-outline', 'label' => 'Follow-ups'],
            ['route' => 'hr.tracking.calendar', 'icon' => 'mdi-calendar-month', 'label' => 'Calendar'],
        ]],
        'config' => ['match' => ['hr.units.*','hr.cadres.*','hr.grade-levels.*'], 'icon' => 'mdi-cog', 'label' => 'Config', 'items' => [
            ['route' => 'hr.units.index', 'icon' => 'mdi-office-building', 'label' => 'Units'],
            ['route' => 'hr.cadres.index', 'icon' => 'mdi-account-group', 'label' => 'Cadres'],
            ['route' => 'hr.grade-levels.index', 'icon' => 'mdi-stairs', 'label' => 'Grade Levels'],
        ]],
    ];
@endphp
<style>
.hr-subnav .dropdown-menu{border:none;box-shadow:0 4px 15px rgba(0,0,0,.12);border-radius:8px;padding:.35rem 0;min-width:11rem;}
.hr-subnav .dropdown-item{font-size:.82rem;padding:.4rem 1rem;border-radius:4px;margin:0 .25rem;width:auto;}
.hr-subnav .dropdown-item:hover{background:var(--primary-light,#f0f4ff);color:var(--primary-color,#011b33);}
.hr-subnav .dropdown-item i{width:20px;text-align:center;}
</style>
<div class="hr-subnav mb-3" style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.05);padding:.5rem 1rem;overflow:visible;white-space:nowrap;position:relative;z-index:10;">
    <div class="d-flex align-items-center" style="gap:.25rem;">
        {{-- Dashboard --}}
        <a href="{{ route('hr.workbench.index') }}" class="btn btn-sm {{ request()->routeIs('hr.workbench.*') ? 'btn-primary' : 'btn-light' }}" style="{{ $navBtn }}">
            <i class="mdi mdi-view-dashboard-outline mr-1"></i>Dashboard
        </a>
        {{-- Registry --}}
        <a href="{{ route('hr.staff-registry.index') }}" class="btn btn-sm {{ request()->routeIs('hr.staff-registry.*') ? 'btn-primary' : 'btn-light' }}" style="{{ $navBtn }}">
            <i class="mdi mdi-clipboard-list-outline mr-1"></i>Registry
        </a>
        {{-- Master Import --}}
        <a href="{{ route('hr.master-import.index') }}" class="btn btn-sm {{ request()->routeIs('hr.master-import.*') ? 'btn-primary' : 'btn-light' }}" style="{{ $navBtn }}">
            <i class="mdi mdi-file-import mr-1"></i>Import
        </a>
        {{-- Dropdown groups --}}
        @foreach($hrGroups as $key => $group)
        @php $groupActive = collect($group['match'])->contains(fn($m) => request()->routeIs($m)); @endphp
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm {{ $groupActive ? 'btn-primary' : 'btn-light' }} dropdown-toggle" data-bs-toggle="dropdown" style="{{ $navDd }}">
                <i class="mdi {{ $group['icon'] }} mr-1"></i>{{ $group['label'] }}
            </button>
            <div class="dropdown-menu">
                @foreach($group['items'] as $item)
                <a class="dropdown-item {{ request()->routeIs(str_replace('.index','.', $item['route']).'*') ? 'active font-weight-bold' : '' }}" href="{{ route($item['route']) }}">
                    <i class="mdi {{ $item['icon'] }} mr-2"></i>{{ $item['label'] }}
                </a>
                @endforeach
            </div>
        </div>
        @endforeach
        {{-- ESS (for staff) --}}
        @can('ess.access')
        @php $essActive = request()->routeIs('hr.ess.*'); @endphp
        <div class="dropdown d-inline-block ml-auto">
            <button class="btn btn-sm {{ $essActive ? 'btn-primary' : 'btn-outline-primary' }} dropdown-toggle" data-bs-toggle="dropdown" style="{{ $navDd }}">
                <i class="mdi mdi-account-circle mr-1"></i>My ESS
            </button>
            <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item" href="{{ route('hr.ess.index') }}"><i class="mdi mdi-view-dashboard mr-2"></i>Dashboard</a>
                <a class="dropdown-item" href="{{ route('hr.ess.my-leave') }}"><i class="mdi mdi-calendar-clock mr-2"></i>My Leave</a>
                <a class="dropdown-item" href="{{ route('hr.ess.my-payslips') }}"><i class="mdi mdi-file-document mr-2"></i>My Payslips</a>
                <a class="dropdown-item" href="{{ route('hr.ess.my-disciplinary') }}"><i class="mdi mdi-alert mr-2"></i>My Disciplinary</a>
                <a class="dropdown-item" href="{{ route('hr.ess.my-profile') }}"><i class="mdi mdi-account-edit mr-2"></i>My Profile</a>
                <a class="dropdown-item" href="{{ route('hr.ess.my-calendar') }}"><i class="mdi mdi-calendar mr-2"></i>My Calendar</a>
                @can('leave-request.supervisor-approve')
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('hr.ess.team-approvals.index') }}"><i class="mdi mdi-check-decagram mr-2"></i>Team Approvals</a>
                <a class="dropdown-item" href="{{ route('hr.ess.team-calendar.index') }}"><i class="mdi mdi-calendar-account mr-2"></i>Team Calendar</a>
                @endcan
            </div>
        </div>
        @endcan
    </div>
</div>

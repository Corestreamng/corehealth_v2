@extends('admin.layouts.app')
@section('title', 'Classic Dashboard')
@section('page_name', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/dashboard-classic.css') }}">
@endpush

@section('content')
    @php
        $user = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();
        $isAdmin = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);

        // Define role tabs mapping based on sidebar structure
        $roleTabs = [];

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'RECEPTIONIST'])) {
            $roleTabs['receptionist'] = [
                'name' => __('dashboard.tab_receptionist'),
                'icon' => 'mdi-account-tie',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.receptionist-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])) {
            $roleTabs['biller'] = [
                'name' => __('dashboard.tab_biller'),
                'icon' => 'mdi-cash-multiple',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.biller-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN'])) {
            $roleTabs['admin'] = [
                'name' => __('dashboard.tab_admin'),
                'icon' => 'mdi-shield-account',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.admin-tab'
            ];

            $roleTabs['hr'] = [
                'name' => __('dashboard.tab_hr'),
                'icon' => 'mdi-account-group',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.hr-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'PHARMACIST'])) {
            $roleTabs['pharmacy'] = [
                'name' => __('dashboard.tab_pharmacy'),
                'icon' => 'mdi-pill',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.pharmacy-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'STORE'])) {
            $roleTabs['store'] = [
                'name' => __('dashboard.tab_store'),
                'icon' => 'mdi-store-warehouse',
                'color' => 'warning',
                'partial' => 'admin.dashboards.tabs.store-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'MATERNITY'])) {
            $roleTabs['maternity'] = [
                'name' => __('dashboard.tab_maternity'),
                'icon' => 'mdi-mother-nurse',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.maternity-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'MORGUE'])) {
            $roleTabs['morgue'] = [
                'name' => __('dashboard.tab_morgue'),
                'icon' => 'mdi-emoticon-dead',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.morgue-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'NURSE'])) {
            $roleTabs['nursing'] = [
                'name' => __('dashboard.tab_nursing'),
                'icon' => 'mdi-heart-pulse',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.nursing-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'NURSE', 'PED-NURSE'])) {
            $roleTabs['child_health'] = [
                'name' => __('dashboard.tab_child_health'),
                'icon' => 'mdi-baby-face-outline',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.child-health-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'LAB SCIENTIST'])) {
            $roleTabs['lab'] = [
                'name' => __('dashboard.tab_lab'),
                'icon' => 'mdi-flask',
                'color' => 'info',
                'partial' => 'admin.dashboards.tabs.lab-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'RADIOLOGIST'])) {
            $roleTabs['imaging'] = [
                'name' => __('dashboard.tab_imaging'),
                'icon' => 'mdi-radiobox-marked',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.imaging-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'SURGEON', 'THEATRE-NURSE'])) {
            $roleTabs['theatre'] = [
                'name' => __('dashboard.tab_theatre'),
                'icon' => 'mdi-pulse',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.theatre-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR'])) {
            $roleTabs['doctor'] = [
                'name' => __('dashboard.tab_doctor'),
                'icon' => 'mdi-stethoscope',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.doctor-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HMO Executive'])) {
            $roleTabs['hmo'] = [
                'name' => __('dashboard.tab_hmo'),
                'icon' => 'mdi-medical-bag',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.hmo-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])) {
            $roleTabs['accounts'] = [
                'name' => __('dashboard.tab_accounts'),
                'icon' => 'mdi-chart-areaspline',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.accounts-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'AUDITOR'])) {
            $roleTabs['audit'] = [
                'name' => __('dashboard.tab_audit'),
                'icon' => 'mdi-shield-check',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.audit-tab'
            ];
        }

        $roleTabs['ess'] = [
            'name' => __('dashboard.tab_my_portal'),
            'icon' => 'mdi-account-circle',
            'color' => 'info',
            'partial' => 'admin.dashboards.tabs.ess-tab'
        ];

        // Get first tab as default
        $firstTabKey = array_key_first($roleTabs);
    @endphp

    {{-- Top Mode Switcher Header Bar --}}
    <div class="dash-classic-topbar">
        <div class="dash-classic-topbar-title">
            <i class="mdi mdi-view-dashboard-variant-outline text-primary"></i>
            <span>Classic Operations Dashboard</span>
            <span class="badge bg-light text-muted border ms-1" style="font-size: 0.7rem; font-weight: 500;">Condensed</span>
        </div>
        <div class="dash-classic-topbar-actions">
            <a href="{{ route('home', ['mode' => 'cockpit']) }}" class="dash-classic-mode-pill" title="Switch to Operational Cockpit View">
                <i class="mdi mdi-view-dashboard-outline me-1"></i> Switch to Operational Cockpit
            </a>
            @if($isAdmin)
                <a href="{{ route('hospital-config.index') }}" class="dash-classic-mode-pill" title="Configure Default Dashboard Experience in Hospital Settings">
                    <i class="mdi mdi-cog-outline me-1"></i> Hospital Config
                </a>
            @endif
        </div>
    </div>

    @if(count($roleTabs) > 0)
        @if(count($roleTabs) > 1)
            {{-- Multi-Role Tab Layout --}}
            <div class="row mb-2">
                <div class="col-12">
                    <div class="dash-tab-bar">
                        <ul class="nav nav-pills nav-fill mb-0" id="dashboardTabs" role="tablist">
                            @foreach($roleTabs as $key => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }} d-flex align-items-center justify-content-center"
                                            id="{{ $key }}-tab"
                                            data-toggle="pill"
                                            data-bs-toggle="pill"
                                            data-target="#{{ $key }}-content"
                                            data-bs-target="#{{ $key }}-content"
                                            type="button"
                                            role="tab"
                                            aria-controls="{{ $key }}-content"
                                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="mdi {{ $tab['icon'] }} me-1"></i>
                                        <span>{{ $tab['name'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="dashboardTabContent">
                @foreach($roleTabs as $key => $tab)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                         id="{{ $key }}-content"
                         role="tabpanel"
                         aria-labelledby="{{ $key }}-tab">
                        @include($tab['partial'])
                    </div>
                @endforeach
            </div>
        @else
            {{-- Single Role - No Tabs --}}
            @include($roleTabs[$firstTabKey]['partial'])
        @endif
    @else
        {{-- Fallback for users with no recognized roles --}}
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    {{ __('dashboard.no_dashboard_available') }}
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>
    <script>
        window.DASHBOARD_CLASSIC_CONFIG = {
            firstTabKey: @json($firstTabKey ?? ''),
            routes: {
                receptionistStats: @json(Route::has('dashboard.receptionist-stats') ? route('dashboard.receptionist-stats') : null),
                billerStats: @json(Route::has('dashboard.biller-stats') ? route('dashboard.biller-stats') : null),
                adminStats: @json(Route::has('dashboard.admin-stats') ? route('dashboard.admin-stats') : null),
                pharmacyStats: @json(Route::has('dashboard.pharmacy-stats') ? route('dashboard.pharmacy-stats') : null),
                nursingStats: @json(Route::has('dashboard.nursing-stats') ? route('dashboard.nursing-stats') : null),
                labStats: @json(Route::has('dashboard.lab-stats') ? route('dashboard.lab-stats') : null),
                doctorStats: @json(Route::has('dashboard.doctor-stats') ? route('dashboard.doctor-stats') : null),
                hmoStats: @json(Route::has('dashboard.hmo-stats') ? route('dashboard.hmo-stats') : null),
                dataReception: @json(Route::has('dashboard.data.reception') ? route('dashboard.data.reception') : null),
                dataBilling: @json(Route::has('dashboard.data.billing') ? route('dashboard.data.billing') : null),
                dataPharmacy: @json(Route::has('dashboard.data.pharmacy') ? route('dashboard.data.pharmacy') : null),
                dataStore: @json(Route::has('dashboard.data.store') ? route('dashboard.data.store') : null),
                dataMaternity: @json(Route::has('dashboard.data.maternity') ? route('dashboard.data.maternity') : null),
                dataMorgue: @json(Route::has('dashboard.data.morgue') ? route('dashboard.data.morgue') : null),
                dataEss: @json(Route::has('dashboard.data.ess') ? route('dashboard.data.ess') : null),
                dataChildHealth: @json(Route::has('dashboard.data.child_health') ? route('dashboard.data.child_health') : null),
                dataHr: @json(Route::has('dashboard.data.hr') ? route('dashboard.data.hr') : null),
                dataTheatre: @json(Route::has('dashboard.data.theatre') ? route('dashboard.data.theatre') : null),
                dataNursing: @json(Route::has('dashboard.data.nursing') ? route('dashboard.data.nursing') : null),
                dataLab: @json(Route::has('dashboard.data.lab') ? route('dashboard.data.lab') : null),
                dataImaging: @json(Route::has('dashboard.data.imaging') ? route('dashboard.data.imaging') : null),
                dataDoctor: @json(Route::has('dashboard.data.doctor') ? route('dashboard.data.doctor') : null),
                dataHmo: @json(Route::has('dashboard.data.hmo') ? route('dashboard.data.hmo') : null),
                dataAccounts: @json(Route::has('dashboard.data.accounts') ? route('dashboard.data.accounts') : null),
                dataAuditLog: @json(Route::has('dashboard.data.audit-log') ? route('dashboard.data.audit-log') : null),
                dataAudit: @json(Route::has('dashboard.data.audit') ? route('dashboard.data.audit') : null),
                chartRegistrations: @json(Route::has('dashboard.chart.registrations') ? route('dashboard.chart.registrations') : null),
                chartRevenue: @json(Route::has('dashboard.chart.revenue') ? route('dashboard.chart.revenue') : null),
                clinicTimeline: @json(Route::has('api.chart.clinic.timeline') ? route('api.chart.clinic.timeline') : null),
                clinicStatus: @json(Route::has('api.chart.clinic.status') ? route('api.chart.clinic.status') : null)
            },
            queueWorkbenchMap: {
                'recep-queues': { url: @json(Route::has('reception.workbench') ? route('reception.workbench') : null), filterMap: null },
                'biller-queues': { url: @json(Route::has('billing.workbench') ? route('billing.workbench') : null), filterMap: null },
                'pharm-queues': { url: @json(Route::has('pharmacy.workbench') ? route('pharmacy.workbench') : null), filterMap: null },
                'nurs-queues': { url: @json(Route::has('nursing-workbench.index') ? route('nursing-workbench.index') : null), filterMap: null },
                'lab-queues': { url: @json(Route::has('lab.workbench') ? route('lab.workbench') : null), filterMap: null },
                'imaging-queues': { url: @json(Route::has('imaging.workbench') ? route('imaging.workbench') : null), filterMap: null },
                'hmo-queues': { url: @json(Route::has('hmo.workbench') ? route('hmo.workbench') : null), filterMap: null },
                'doc-queues': {
                    url: @json(url('encounters')),
                    filterMap: {
                        'queue': { url: @json(url('encounters')), param: 'status', filter: 'waiting' },
                        'in-progress': { url: @json(url('encounters')), param: 'status', filter: 'in-progress' },
                        'completed': { url: @json(url('encounters')), param: 'status', filter: 'completed' },
                        'results': { url: @json(Route::has('lab.workbench') ? route('lab.workbench') : url('encounters')), param: 'tab', filter: 'results' }
                    }
                },
                'hr-queues': { url: @json(Route::has('hr.workbench.index') ? route('hr.workbench.index') : null), filterMap: null },
                'theatre-queues': { url: @json(Route::has('surgery-workbench.index') ? route('surgery-workbench.index') : (Route::has('patient-procedures.index') ? route('patient-procedures.index') : url('surgery-workbench'))), filterMap: null },
                'child-queues': { url: @json(Route::has('patient.index') ? route('patient.index') : null), filterMap: null },
                'ess-queues': { url: @json(Route::has('hr.ess.index') ? route('hr.ess.index') : null), filterMap: null },
                'store-queues': {
                    url: null,
                    filterMap: {
                        'requisitions': { url: @json(Route::has('inventory.requisitions.index') ? route('inventory.requisitions.index') : '#'), filter: 'pending' },
                        'pos': { url: @json(Route::has('inventory.purchase-orders.index') ? route('inventory.purchase-orders.index') : '#'), filter: 'submitted' },
                        'approved-pos': { url: @json(Route::has('inventory.purchase-orders.index') ? route('inventory.purchase-orders.index') : '#'), filter: 'approved' },
                        'fulfillment': { url: @json(Route::has('inventory.requisitions.pending-fulfillment') ? route('inventory.requisitions.pending-fulfillment') : '#'), filter: 'all' }
                    }
                },
                'mat-queues': { url: @json(Route::has('maternity-workbench.index') ? route('maternity-workbench.index') : null), filterMap: null },
                'morgue-queues': { url: @json(Route::has('morgue.workbench') ? route('morgue.workbench') : null), filterMap: null }
            },
            activityWorkbenchMap: {
                'recep-activity': @json(Route::has('reception.workbench') ? route('reception.workbench') : null),
                'biller-activity': @json(Route::has('billing.workbench') ? route('billing.workbench') : null),
                'pharm-activity': @json(Route::has('pharmacy.workbench') ? route('pharmacy.workbench') : null),
                'nurs-activity': @json(Route::has('nursing-workbench.index') ? route('nursing-workbench.index') : null),
                'lab-activity': @json(Route::has('lab.workbench') ? route('lab.workbench') : null),
                'imaging-activity': @json(Route::has('imaging.workbench') ? route('imaging.workbench') : null),
                'doc-activity': @json(url('encounters/create')),
                'hmo-activity': @json(Route::has('hmo.workbench') ? route('hmo.workbench') : null),
                'store-activity': @json(Route::has('inventory.store-workbench.index') ? route('inventory.store-workbench.index') : null),
                'mat-activity': @json(Route::has('maternity-workbench.index') ? route('maternity-workbench.index') : null),
                'morgue-activity': @json(Route::has('morgue.workbench') ? route('morgue.workbench') : null),
                'theatre-activity': @json(Route::has('surgery-workbench.index') ? route('surgery-workbench.index') : url('encounters/create')),
                'hr-activity': @json(Route::has('hr.workbench.index') ? route('hr.workbench.index') : null),
                'child-activity': @json(Route::has('patient.index') ? route('patient.index') : null)
            }
        };
    </script>
    <script src="{{ versioned_asset('js/dashboard-classic.js') }}"></script>
@endpush

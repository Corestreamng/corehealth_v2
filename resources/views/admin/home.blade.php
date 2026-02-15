@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_name', 'Dashboard')
@section('content')
    @php
        $user = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();

        // Define role tabs mapping based on sidebar structure
        $roleTabs = [];

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'RECEPTIONIST'])) {
            $roleTabs['receptionist'] = [
                'name' => 'Receptionist',
                'icon' => 'mdi-account-tie',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.receptionist-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])) {
            $roleTabs['biller'] = [
                'name' => 'Biller',
                'icon' => 'mdi-cash-multiple',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.biller-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN'])) {
            $roleTabs['admin'] = [
                'name' => 'Administration',
                'icon' => 'mdi-shield-account',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.admin-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'STORE', 'PHARMACIST'])) {
            $roleTabs['pharmacy'] = [
                'name' => 'Pharmacy/Store',
                'icon' => 'mdi-pill',
                'color' => 'warning',
                'partial' => 'admin.dashboards.tabs.pharmacy-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'NURSE'])) {
            $roleTabs['nursing'] = [
                'name' => 'Nursing',
                'icon' => 'mdi-heart-pulse',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.nursing-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'LAB SCIENTIST', 'RADIOLOGIST'])) {
            $roleTabs['lab'] = [
                'name' => 'Lab/Imaging',
                'icon' => 'mdi-flask',
                'color' => 'info',
                'partial' => 'admin.dashboards.tabs.lab-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR'])) {
            $roleTabs['doctor'] = [
                'name' => 'Doctor',
                'icon' => 'mdi-stethoscope',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.doctor-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HMO Executive'])) {
            $roleTabs['hmo'] = [
                'name' => 'HMO Executive',
                'icon' => 'mdi-medical-bag',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.hmo-tab'
            ];
        }

        // Get first tab as default
        $firstTabKey = array_key_first($roleTabs);
    @endphp

    @if(count($roleTabs) > 0)
        @if(count($roleTabs) > 1)
            {{-- Multi-Role Tab Layout --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dash-tab-bar">
                        <ul class="nav nav-pills nav-fill mb-0" id="dashboardTabs" role="tablist">
                            @foreach($roleTabs as $key => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }} d-flex align-items-center justify-content-center"
                                            id="{{ $key }}-tab"
                                            data-bs-toggle="pill"
                                            data-bs-target="#{{ $key }}-content"
                                            type="button"
                                            role="tab"
                                            aria-controls="{{ $key }}-content"
                                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="mdi {{ $tab['icon'] }} me-2"></i>
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
                    No dashboard available for your role. Please contact your administrator.
                </div>
            </div>
        </div>
    @endif
@endsection

@section('style')
<style>
    /* =========================================
       DASHBOARD DESIGN SYSTEM
       Mature · Spacious · High-Contrast
       ========================================= */

    /* Tab Bar */
    .dash-tab-bar {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    .dash-tab-bar .nav-link {
        border-radius: 0 !important;
        padding: 16px 20px;
        font-weight: 600;
        font-size: 0.875rem;
        color: #6b7280;
        letter-spacing: 0.01em;
        transition: all 0.25s ease;
        border-bottom: 3px solid transparent;
        position: relative;
    }

    .dash-tab-bar .nav-link i {
        font-size: 1.35rem;
    }

    .dash-tab-bar .nav-link:hover {
        color: #374151;
        background: #f9fafb;
    }

    .dash-tab-bar .nav-link.active {
        color: #1d4ed8 !important;
        background: #eff6ff !important;
        border-bottom: 3px solid #1d4ed8 !important;
    }

    /* Welcome Card */
    .dash-welcome {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }

    .dash-welcome-body {
        padding: 32px 36px;
    }

    .dash-welcome-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        flex-shrink: 0;
    }

    .dash-welcome-avatar i {
        font-size: 1.75rem;
        color: #fff;
    }

    .dash-welcome-title {
        font-weight: 700;
        font-size: 1.5rem;
        color: #fff;
        margin-bottom: 4px;
        line-height: 1.3;
    }

    .dash-welcome-sub {
        display: flex;
        align-items: center;
        color: rgba(255,255,255,0.7);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .dash-welcome-badge {
        padding: 8px 18px;
        background: rgba(255,255,255,0.15);
        border-radius: 10px;
        color: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        backdrop-filter: blur(8px);
        white-space: nowrap;
    }

    /* Stat Cards */
    .dash-stat-card {
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .dash-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(0,0,0,0.15);
    }

    .dash-stat-body {
        padding: 28px 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .dash-stat-label {
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }

    .dash-stat-value {
        font-weight: 800;
        color: #fff;
        font-size: 2.25rem;
        margin-bottom: 8px;
        line-height: 1;
    }

    .dash-stat-hint {
        color: rgba(255,255,255,0.65);
        font-size: 0.8rem;
        font-weight: 500;
    }

    .dash-stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: rgba(255,255,255,0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .dash-stat-icon i {
        font-size: 1.75rem;
        color: #fff;
    }

    /* Section Cards (shortcuts, tables) */
    .dash-section-card {
        background: #fff;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        border: 1px solid #f0f0f0;
    }

    .dash-section-header {
        display: flex;
        align-items: center;
        margin-bottom: 24px;
        gap: 14px;
    }

    .dash-section-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .dash-section-icon i {
        font-size: 1.4rem;
    }

    .dash-section-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: #1a1a2e;
        margin-bottom: 0;
        line-height: 1.3;
    }

    /* Shortcut Cards */
    .dash-shortcut {
        padding: 22px 16px;
        border-radius: 16px;
        text-align: center;
        height: 100%;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 130px;
    }

    .dash-shortcut:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }

    .dash-shortcut-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .dash-shortcut-title {
        font-weight: 700;
        font-size: 0.85rem;
        margin-bottom: 2px;
    }

    .dash-shortcut small {
        font-size: 0.75rem;
        font-weight: 500;
    }

    /* Chart Cards */
    .dash-chart-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        border: 1px solid #f0f0f0;
        height: 100%;
    }

    .dash-chart-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 28px 32px 0;
    }

    .dash-chart-body {
        padding: 24px 32px 28px;
        height: 300px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dash-welcome-body { padding: 24px 20px; }
        .dash-welcome-title { font-size: 1.15rem; }
        .dash-stat-body { padding: 20px 18px; }
        .dash-stat-value { font-size: 1.75rem; }
        .dash-section-card { padding: 20px; }
        .dash-shortcut { padding: 16px 12px; min-height: 110px; }
        .dash-shortcut-icon { font-size: 1.6rem; }
        .dash-chart-body { padding: 16px 20px 20px; height: 240px; }
        .dash-chart-header { padding: 20px 20px 0; }
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>
<script>
$(document).ready(function () {

    // ========================================
    // Date/Time Display
    // ========================================
    function updateDateTime() {
        var now = new Date();
        var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        $('[id="currentDateTime"]').text(now.toLocaleDateString('en-US', options));
    }
    updateDateTime();
    setInterval(updateDateTime, 30000);

    // ========================================
    // Stats Fetch Functions for Each Role
    // ========================================
    function fetchReceptionistStats() {
        $.get("{{ route('dashboard.receptionist-stats') }}", function(data) {
            $('#recep-stat-new-patients').text(data.new_patients || '0');
            $('#recep-stat-returning-patients').text(data.returning_patients || '0');
            $('#recep-stat-admissions').text(data.admissions || '0');
            $('#recep-stat-bookings').text(data.bookings || '0');
        }).fail(function() { console.log('Failed to load receptionist stats'); });
    }

    function fetchBillerStats() {
        $.get("{{ route('dashboard.biller-stats') }}", function(data) {
            $('#biller-stat-revenue').text('\u20A6' + (data.today_revenue || '0.00'));
            $('#biller-stat-payment-requests').text(data.payment_requests || '0');
            $('#biller-stat-my-payments').text(data.my_payments || '0');
            $('#biller-stat-consultations').text(data.consultations || '0');
        }).fail(function() { console.log('Failed to load biller stats'); });
    }

    function fetchAdminStats() {
        $.get("{{ route('dashboard.admin-stats') }}", function(data) {
            $('#admin-stat-staff').text(data.total_staff || '0');
            $('#admin-stat-patients').text(data.total_patients || '0');
            $('#admin-stat-clinics').text(data.total_clinics || '0');
            $('#admin-stat-revenue').text('\u20A6' + (data.total_revenue || '0.00'));
        }).fail(function() { console.log('Failed to load admin stats'); });
    }

    function fetchPharmacyStats() {
        $.get("{{ route('dashboard.pharmacy-stats') }}", function(data) {
            $('#pharm-stat-queue').text(data.queue_today || '0');
            $('#pharm-stat-dispensed').text(data.dispensed_today || '0');
            $('#pharm-stat-products').text(data.total_products || '0');
            $('#pharm-stat-low-stock').text(data.low_stock || '0');
        }).fail(function() { console.log('Failed to load pharmacy stats'); });
    }

    function fetchNursingStats() {
        $.get("{{ route('dashboard.nursing-stats') }}", function(data) {
            $('#nurs-stat-vitals-queue').text(data.vitals_queue || '0');
            $('#nurs-stat-bed-requests').text(data.bed_requests || '0');
            $('#nurs-stat-medication-due').text(data.medication_due || '0');
            $('#nurs-stat-admitted').text(data.admitted_patients || '0');
        }).fail(function() { console.log('Failed to load nursing stats'); });
    }

    function fetchLabStats() {
        $.get("{{ route('dashboard.lab-stats') }}", function(data) {
            $('#lab-stat-queue').text(data.lab_queue || '0');
            $('#lab-stat-imaging').text(data.imaging_queue || '0');
            $('#lab-stat-completed').text(data.completed_today || '0');
            $('#lab-stat-services').text(data.total_services || '0');
        }).fail(function() { console.log('Failed to load lab stats'); });
    }

    function fetchDoctorStats() {
        $.get("{{ route('dashboard.doctor-stats') }}", function(data) {
            $('#doc-stat-consultations').text(data.consultations_today || '0');
            $('#doc-stat-ward-rounds').text(data.ward_rounds || '0');
            $('#doc-stat-patients').text(data.my_patients || '0');
            $('#doc-stat-appointments').text(data.appointments_today || '0');
        }).fail(function() { console.log('Failed to load doctor stats'); });
    }

    function fetchHmoStats() {
        $.get("{{ route('dashboard.hmo-stats') }}", function(data) {
            $('#hmo-stat-patients').text(data.hmo_patients || '0');
            $('#hmo-stat-pending-claims').text(data.pending_claims || '0');
            $('#hmo-stat-approved-claims').text(data.approved_claims || '0');
            $('#hmo-stat-total-hmos').text(data.total_hmos || '0');
        }).fail(function() { console.log('Failed to load HMO stats'); });
    }

    // ========================================
    // Chart Rendering Functions
    // ========================================
    var chartInstances = {};

    var chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { size: 13, weight: '600' },
                bodyFont: { size: 12 },
                padding: 12,
                cornerRadius: 8,
                displayColors: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9', drawBorder: false },
                ticks: { color: '#94a3b8', font: { size: 11, weight: '500' }, padding: 8 }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#94a3b8', font: { size: 11 }, maxRotation: 45, padding: 6 }
            }
        }
    };

    function renderLineChart(canvasId, data, label, color) {
        if (!document.getElementById(canvasId)) return;
        if (chartInstances[canvasId]) chartInstances[canvasId].destroy();

        var ctx = document.getElementById(canvasId).getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, color + '30');
        gradient.addColorStop(1, color + '05');

        chartInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(function(d) { return d.date; }),
                datasets: [{
                    label: label,
                    data: data.map(function(d) { return parseFloat(d.total); }),
                    borderColor: color,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color,
                    pointBorderWidth: 2
                }]
            },
            options: chartDefaults
        });
    }

    function renderDoughnutChart(canvasId, data, colors) {
        if (!document.getElementById(canvasId)) return;
        if (chartInstances[canvasId]) chartInstances[canvasId].destroy();

        var ctx = document.getElementById(canvasId).getContext('2d');
        chartInstances[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(function(d) { return d.label || d.name || d.status; }),
                datasets: [{
                    data: data.map(function(d) { return parseFloat(d.total || d.count || d.value); }),
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyleWidth: 10,
                            font: { size: 12, weight: '500' },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true
                    }
                }
            }
        });
    }

    // ========================================
    // Chart Data Fetchers
    // ========================================
    function renderRevenueChart(targetId) {
        $.get("{{ route('dashboard.chart.revenue') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart(targetId, data, 'Revenue (\u20A6)', '#10b981');
        });
    }

    function renderRegistrationsChart() {
        $.get("{{ route('dashboard.chart.registrations') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('adminRegistrationsChart', data, 'Registrations', '#3b82f6');
            renderLineChart('recepRegistrationsChart', data, 'Registrations', '#3b82f6');
        });
    }

    function renderAppointmentsChart() {
        $.get("{{ route('api.chart.clinic.timeline') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('recepAppointmentsChart', data, 'Appointments', '#10b981');
        });
    }

    function renderPharmacyCharts() {
        $.get("{{ route('dashboard.chart.registrations') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('pharmacyDispensingChart', data, 'Prescriptions', '#16a34a');
        });
        if (document.getElementById('pharmacyStockChart')) {
            renderDoughnutChart('pharmacyStockChart', [
                { label: 'In Stock', value: 80 },
                { label: 'Low Stock', value: 15 },
                { label: 'Out of Stock', value: 5 }
            ], ['#10b981', '#f59e0b', '#ef4444']);
        }
    }

    function renderDoctorCharts() {
        $.get("{{ route('api.chart.clinic.timeline') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('doctorConsultationsChart', data, 'Consultations', '#3b82f6');
        });
        $.get("{{ route('api.chart.clinic.status') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            if (data && data.length) {
                renderDoughnutChart('doctorOutcomesChart', data.map(function(d) {
                    return { label: d.status, value: d.total };
                }), ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']);
            }
        });
    }

    function renderNursingCharts() {
        $.get("{{ route('dashboard.chart.registrations') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('nursingVitalsChart', data, 'Vitals Recorded', '#ef4444');
        });
        if (document.getElementById('nursingBedChart')) {
            renderDoughnutChart('nursingBedChart', [
                { label: 'Occupied', value: 60 },
                { label: 'Available', value: 30 },
                { label: 'Reserved', value: 10 }
            ], ['#ef4444', '#10b981', '#f59e0b']);
        }
    }

    function renderLabCharts() {
        $.get("{{ route('api.chart.clinic.timeline') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('labRequestsChart', data, 'Lab Requests', '#0891b2');
        });
        if (document.getElementById('labCategoriesChart')) {
            renderDoughnutChart('labCategoriesChart', [
                { label: 'Lab Tests', value: 65 },
                { label: 'Imaging', value: 25 },
                { label: 'Pathology', value: 10 }
            ], ['#0891b2', '#6366f1', '#a855f7']);
        }
    }

    function renderHmoCharts() {
        $.get("{{ route('dashboard.chart.revenue') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('hmoClaimsChart', data, 'Claims Value', '#7c3aed');
        });
        if (document.getElementById('hmoDistributionChart')) {
            renderDoughnutChart('hmoDistributionChart', [
                { label: 'NHIS', value: 40 },
                { label: 'Private HMO', value: 35 },
                { label: 'Employer Plans', value: 25 }
            ], ['#3b82f6', '#8b5cf6', '#06b6d4']);
        }
    }

    // ========================================
    // Helper Functions
    // ========================================
    function getToday() {
        return new Date().toISOString().slice(0, 10);
    }

    function getThisMonthStart() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    }

    // ========================================
    // Initialize & Tab Switching
    // ========================================
    function loadTabData(tabName) {
        switch(tabName) {
            case 'receptionist':
                fetchReceptionistStats(); renderRegistrationsChart(); renderAppointmentsChart(); break;
            case 'biller':
                fetchBillerStats(); renderRevenueChart('billerRevenueChart'); break;
            case 'admin':
                fetchAdminStats(); renderRevenueChart('adminRevenueChart'); renderRegistrationsChart(); break;
            case 'pharmacy':
                fetchPharmacyStats(); renderPharmacyCharts(); break;
            case 'nursing':
                fetchNursingStats(); renderNursingCharts(); break;
            case 'lab':
                fetchLabStats(); renderLabCharts(); break;
            case 'doctor':
                fetchDoctorStats(); renderDoctorCharts(); break;
            case 'hmo':
                fetchHmoStats(); renderHmoCharts(); break;
        }
    }

    function initializeAllStats() {
        var activeTab = $('#dashboardTabs .nav-link.active').attr('id');
        if (activeTab) {
            loadTabData(activeTab.replace('-tab', ''));
        } else {
            // Single-role layout — load the first available tab
            var firstKey = '{{ $firstTabKey ?? "" }}';
            if (firstKey) loadTabData(firstKey);
        }
    }

    initializeAllStats();

    // Refresh stats when switching tabs
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        var targetTab = $(e.target).attr('id').replace('-tab', '');
        loadTabData(targetTab);
    });

    // Auto-refresh every 60 seconds
    setInterval(function() {
        var activeTab = $('#dashboardTabs .nav-link.active').attr('id');
        if (activeTab) {
            loadTabData(activeTab.replace('-tab', ''));
        } else {
            initializeAllStats();
        }
    }, 60000);
});
</script>
@endsection

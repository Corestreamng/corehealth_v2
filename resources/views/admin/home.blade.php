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
                    <div class="card-modern border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="card-body p-0">
                            <ul class="nav nav-pills nav-fill mb-0" id="dashboardTabs" role="tablist" style="border-bottom: 2px solid #f0f0f0;">
                                @foreach($roleTabs as $key => $tab)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link {{ $loop->first ? 'active' : '' }} d-flex align-items-center justify-content-center py-3"
                                                id="{{ $key }}-tab"
                                                data-bs-toggle="pill"
                                                data-bs-target="#{{ $key }}-content"
                                                type="button"
                                                role="tab"
                                                aria-controls="{{ $key }}-content"
                                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                                style="border-radius: 0; font-weight: 600; transition: all 0.3s;">
                                            <i class="mdi {{ $tab['icon'] }} me-2" style="font-size: 1.5rem;"></i>
                                            <span>{{ $tab['name'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
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

@section('styles')
<style>
    /* Tab styling */
    #dashboardTabs .nav-link {
        color: #6c757d;
        position: relative;
    }

    #dashboardTabs .nav-link:hover {
        color: #495057;
        background: rgba(0, 0, 0, 0.02);
    }

    #dashboardTabs .nav-link.active {
        color: #007bff !important;
        background: transparent !important;
        border-bottom: 3px solid #007bff !important;
    }

    #dashboardTabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 50%;
        height: 3px;
        background: linear-gradient(90deg, transparent, #007bff, transparent);
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>
<script>
$(document).ready(function () {
    // ========================================
    // Stats Fetch Functions for Each Role
    // ========================================

    function fetchReceptionistStats() {
        $.get("{{ route('dashboard.receptionist-stats') }}", function(data) {
            $('#recep-stat-new-patients').text(data.new_patients || '0');
            $('#recep-stat-returning-patients').text(data.returning_patients || '0');
            $('#recep-stat-admissions').text(data.admissions || '0');
            $('#recep-stat-bookings').text(data.bookings || '0');
        }).fail(function() {
            console.log('Failed to load receptionist stats');
        });
    }

    function fetchBillerStats() {
        $.get("{{ route('dashboard.biller-stats') }}", function(data) {
            $('#biller-stat-revenue').text('₦' + (data.today_revenue || '0.00'));
            $('#biller-stat-payment-requests').text(data.payment_requests || '0');
            $('#biller-stat-my-payments').text(data.my_payments || '0');
            $('#biller-stat-consultations').text(data.consultations || '0');
        }).fail(function() {
            console.log('Failed to load biller stats');
        });
    }

    function renderBillerRevenueChart() {
        $.get("{{ route('dashboard.chart.revenue') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('billerRevenueChart', data, 'Revenue', '#10b981');
        });
    }

    function fetchAdminStats() {
        $.get("{{ route('dashboard.admin-stats') }}", function(data) {
            $('#admin-stat-staff').text(data.total_staff || '0');
            $('#admin-stat-patients').text(data.total_patients || '0');
            $('#admin-stat-clinics').text(data.total_clinics || '0');
            $('#admin-stat-revenue').text('₦' + (data.total_revenue || '0.00'));
        }).fail(function() {
            console.log('Failed to load admin stats');
        });
    }

    function fetchPharmacyStats() {
        $.get("{{ route('dashboard.pharmacy-stats') }}", function(data) {
            $('#pharm-stat-queue').text(data.queue_today || '0');
            $('#pharm-stat-dispensed').text(data.dispensed_today || '0');
            $('#pharm-stat-products').text(data.total_products || '0');
            $('#pharm-stat-low-stock').text(data.low_stock || '0');
        }).fail(function() {
            console.log('Failed to load pharmacy stats');
        });
    }

    function fetchNursingStats() {
        $.get("{{ route('dashboard.nursing-stats') }}", function(data) {
            $('#nurs-stat-vitals-queue').text(data.vitals_queue || '0');
            $('#nurs-stat-bed-requests').text(data.bed_requests || '0');
            $('#nurs-stat-medication-due').text(data.medication_due || '0');
            $('#nurs-stat-admitted').text(data.admitted_patients || '0');
        }).fail(function() {
            console.log('Failed to load nursing stats');
        });
    }

    function fetchLabStats() {
        $.get("{{ route('dashboard.lab-stats') }}", function(data) {
            $('#lab-stat-queue').text(data.lab_queue || '0');
            $('#lab-stat-imaging').text(data.imaging_queue || '0');
            $('#lab-stat-completed').text(data.completed_today || '0');
            $('#lab-stat-services').text(data.total_services || '0');
        }).fail(function() {
            console.log('Failed to load lab stats');
        });
    }

    function fetchDoctorStats() {
        $.get("{{ route('dashboard.doctor-stats') }}", function(data) {
            $('#doc-stat-consultations').text(data.consultations_today || '0');
            $('#doc-stat-ward-rounds').text(data.ward_rounds || '0');
            $('#doc-stat-patients').text(data.my_patients || '0');
            $('#doc-stat-appointments').text(data.appointments_today || '0');
        }).fail(function() {
            console.log('Failed to load doctor stats');
        });
    }

    function fetchHmoStats() {
        $.get("{{ route('dashboard.hmo-stats') }}", function(data) {
            $('#hmo-stat-patients').text(data.hmo_patients || '0');
            $('#hmo-stat-pending-claims').text(data.pending_claims || '0');
            $('#hmo-stat-approved-claims').text(data.approved_claims || '0');
            $('#hmo-stat-total-hmos').text(data.total_hmos || '0');
        }).fail(function() {
            console.log('Failed to load HMO stats');
        });
    }

    // ========================================
    // Chart Rendering Functions
    // ========================================
    const chartInstances = {};

    function renderLineChart(canvasId, data, label, color) {
        if (!document.getElementById(canvasId)) return;

        if (chartInstances[canvasId]) {
            chartInstances[canvasId].destroy();
        }

        const ctx = document.getElementById(canvasId).getContext('2d');
        chartInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [{
                    label: label,
                    data: data.map(d => parseFloat(d.total)),
                    borderColor: color,
                    backgroundColor: color + '20',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    function renderRevenueChart() {
        $.get("{{ route('dashboard.chart.revenue') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('adminRevenueChart', data, 'Revenue', '#10b981');
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

    // ========================================
    // Helper Functions
    // ========================================
    function getToday() {
        return new Date().toISOString().slice(0, 10);
    }

    function getThisMonthStart() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    }

    // ========================================
    // Initialize Stats on Page Load
    // ========================================
    function initializeAllStats() {
        // Check which tabs exist and load their stats
        if ($('#receptionist-content').length || $('.tab-pane').length === 0) {
            fetchReceptionistStats();
            renderRegistrationsChart();
            renderAppointmentsChart();
        }
        if ($('#biller-content').length) {
            fetchBillerStats();
            renderBillerRevenueChart();
        }
        if ($('#admin-content').length) {
            fetchAdminStats();
            renderRevenueChart();
            renderRegistrationsChart();
        }
        if ($('#pharmacy-content').length) {
            fetchPharmacyStats();
        }
        if ($('#nursing-content').length) {
            fetchNursingStats();
        }
        if ($('#lab-content').length) {
            fetchLabStats();
        }
        if ($('#doctor-content').length) {
            fetchDoctorStats();
        }
        if ($('#hmo-content').length) {
            fetchHmoStats();
        }
    }

    // Run on page load
    initializeAllStats();

    // Refresh stats when switching tabs
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        const targetTab = $(e.target).attr('id').replace('-tab', '');

        switch(targetTab) {
            case 'receptionist':
                fetchReceptionistStats();
                renderRegistrationsChart();
                renderAppointmentsChart();
                break;
            case 'biller':
                fetchBillerStats();
                renderBillerRevenueChart();
                break;
            case 'admin':
                fetchAdminStats();
                renderRevenueChart();
                renderRegistrationsChart();
                break;
            case 'pharmacy':
                fetchPharmacyStats();
                break;
            case 'nursing':
                fetchNursingStats();
                break;
            case 'lab':
                fetchLabStats();
                break;
            case 'doctor':
                fetchDoctorStats();
                break;
            case 'hmo':
                fetchHmoStats();
                break;
        }
    });

    // Auto-refresh stats every 60 seconds
    setInterval(function() {
        const activeTab = $('#dashboardTabs .nav-link.active').attr('id');
        if (activeTab) {
            const tabName = activeTab.replace('-tab', '');
            $('button[data-bs-toggle="pill"][id="' + activeTab + '"]').trigger('shown.bs.tab');
        } else {
            initializeAllStats();
        }
    }, 60000);
});
</script>
@endsection

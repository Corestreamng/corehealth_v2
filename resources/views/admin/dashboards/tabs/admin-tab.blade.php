{{-- Administration Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(145deg, #141E30 0%, #243B55 50%, #2C5364 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-shield-account text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Administrator</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-chart-arc text-white me-1"></i>
                            <span class="fw-semibold text-white">System Admin</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                12
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats Cards -- Modern Redesign --}}
<div class="row g-4 mb-4">
    {{-- Total Staff --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #2c3e50, #3498db);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-account-plus me-1"></i>+5 this month
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL STAFF</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="admin-stat-staff">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-outline me-1"></i>Active: 42
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-account-group text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #833ab4, #fd1d1d);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-arrow-up-bold-circle-outline me-1"></i>+12.8%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL PATIENTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="admin-stat-patients">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-account-plus me-1"></i>New: 128
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-account-multiple text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total Clinics --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #1e3c72, #2a5298);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-hospital-building me-1"></i>6 departments
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL CLINICS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="admin-stat-clinics">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-doctor me-1"></i>Active clinics
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-hospital-building text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total Revenue --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #134e5e, #71b280);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-trending-up me-1"></i>+23.5%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL REVENUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="admin-stat-revenue">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-calendar me-1"></i>This month
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-chart-line text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts -- Colorful Card Design --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="mdi mdi-lightning-bolt-circle text-primary" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Administration Shortcuts</h4>
                        <p class="text-secondary mb-0">System configuration and management tools</p>
                    </div>
                    <span class="ms-auto d-none d-md-block badge bg-dark bg-opacity-10 text-dark rounded-pill px-4 py-2">
                        <i class="mdi mdi-shield-account me-1"></i>Admin Access
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- Roles - Royal Purple --}}
                    @if(Route::has('roles.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('roles.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-shield-account" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Roles</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Access control</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Permissions - Ocean Blue --}}
                    @if(Route::has('permissions.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('permissions.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0f2fe, #b8e1ff); border: 1px solid #7dd3fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-lock" style="font-size: 2.5rem; color: #0369a1;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0369a1;">Permissions</h6>
                                <small class="text-dark" style="color: #0369a1 !important;">Security</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Staff - Sunset Orange --}}
                    @if(Route::has('staff.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('staff.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff4e6, #ffe4cc); border: 1px solid #ffc999;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-tie" style="font-size: 2.5rem; color: #c2410c;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #c2410c;">Staff</h6>
                                <small class="text-dark" style="color: #c2410c !important;">Management</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Specializations - Emerald Green --}}
                    @if(Route::has('specializations.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('specializations.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-star" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">Specializations</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Departments</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Clinics - Teal --}}
                    @if(Route::has('clinics.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('clinics.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border: 1px solid #5eead4;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-hospital-building" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0f766e;">Clinics</h6>
                                <small class="text-dark" style="color: #0f766e !important;">Facilities</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- HMOs - Gold --}}
                    @if(Route::has('hmo.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fef9e7, #fef3c7); border: 1px solid #fde68a;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-cash" style="font-size: 2.5rem; color: #b45309;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #b45309;">HMOs</h6>
                                <small class="text-dark" style="color: #b45309 !important;">Insurance</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Hospital Config - Gray --}}
                    @if(Route::has('hospital-config.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hospital-config.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border: 1px solid #cbd5e1;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-cogs" style="font-size: 2.5rem; color: #334155;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #334155;">Hospital Config</h6>
                                <small class="text-dark" style="color: #334155 !important;">Settings</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Audit Logs - Dark --}}
                    @if(Route::has('audit-logs.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('audit-logs.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: 1px solid #a5b4fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-history" style="font-size: 2.5rem; color: #1e1b4b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e1b4b;">Audit Logs</h6>
                                <small class="text-dark" style="color: #1e1b4b !important;">Activity</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Wards - Indigo --}}
                    @if(Route::has('wards.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('wards.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: 1px solid #a5b4fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-hospital-marker" style="font-size: 2.5rem; color: #3730a3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #3730a3;">Wards</h6>
                                <small class="text-dark" style="color: #3730a3 !important;">Units</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Beds - Cyan --}}
                    @if(Route::has('beds.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('beds.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #cffafe, #a5f3fc); border: 1px solid #67e8f9;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-bed" style="font-size: 2.5rem; color: #0e7490;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0e7490;">Beds</h6>
                                <small class="text-dark" style="color: #0e7490 !important;">Allocation</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Vaccine Schedule - Red --}}
                    @if(Route::has('vaccine-schedule.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('vaccine-schedule.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border: 1px solid #fca5a5;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-needle" style="font-size: 2.5rem; color: #991b1b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #991b1b;">Vaccine Schedule</h6>
                                <small class="text-dark" style="color: #991b1b !important;">Immunization</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- All Transactions - Green --}}
                    @if(Route::has('transactions'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('transactions') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-chart-line" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">All Transactions</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Financial</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section with Modern Redesign --}}
<div class="row g-4">
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-chart-line text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Revenue This Month</h5>
                            <p class="text-secondary mb-0">Monthly financial overview</p>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2">
                        <i class="mdi mdi-trending-up me-1"></i>+23.5%
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="adminRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-account-multiple-plus text-primary" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Patient Registrations</h5>
                            <p class="text-secondary mb-0">This month's registration trend</p>
                        </div>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2">
                        <i class="mdi mdi-account-plus me-1"></i>+12.8%
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="adminRegistrationsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern UI Enhancements - Seamlessly integrates with existing Laravel styles */
.backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 30px rgba(0,0,0,0.12) !important;
}

.icon-wrapper {
    transition: all 0.3s ease;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hover-lift:hover .icon-wrapper {
    transform: scale(1.1);
    background: rgba(255,255,255,0.25) !important;
}

.shortcut-card {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.shortcut-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 20px 30px rgba(0,0,0,0.08) !important;
}
.shortcut-card:hover .shortcut-icon-wrapper i {
    transform: scale(1.15);
}
.shortcut-icon-wrapper i {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bg-white.bg-opacity-20 {
    background: rgba(255, 255, 255, 0.2) !important;
}
.bg-white.bg-opacity-15 {
    background: rgba(255, 255, 255, 0.15) !important;
}
.bg-white.bg-opacity-25 {
    background: rgba(255, 255, 255, 0.25) !important;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .display-6 {
        font-size: 1.8rem;
    }
    .icon-wrapper {
        width: 55px;
        height: 55px;
    }
    .icon-wrapper i {
        font-size: 1.8rem !important;
    }
    .shortcut-card {
        padding: 1rem !important;
    }
    .shortcut-icon-wrapper i {
        font-size: 2rem !important;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 1.25rem !important;
    }
    .badge {
        font-size: 0.7rem;
    }
    .shortcut-card {
        padding: 0.75rem !important;
    }
    .shortcut-card h6 {
        font-size: 0.85rem;
    }
    .display-6 {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Live Date and Time Update
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    };
    document.getElementById('currentDateTime').innerHTML = now.toLocaleDateString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Your existing chart initialization
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    if (document.getElementById('adminRevenueChart')) {
        const ctx = document.getElementById('adminRevenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [{
                    label: 'Revenue',
                    data: [28500, 31200, 35800, 39400, 42500],
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#0f766e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a2639',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Patient Registrations Chart
    if (document.getElementById('adminRegistrationsChart')) {
        const ctx = document.getElementById('adminRegistrationsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'New Patients',
                    data: [145, 168, 192, 178, 205, 235],
                    backgroundColor: [
                        'rgba(65, 88, 208, 0.8)',
                        'rgba(147, 51, 234, 0.8)',
                        'rgba(2, 132, 199, 0.8)',
                        'rgba(7, 89, 133, 0.8)',
                        'rgba(22, 78, 99, 0.8)',
                        'rgba(15, 118, 110, 0.8)'
                    ],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a2639',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>
{{-- Receptionist Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(145deg, #FF6B6B 0%, #4ECDC4 50%, #FFE66D 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-account-tie-woman text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Receptionist</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-chart-arc text-white me-1"></i>
                            <span class="fw-semibold text-white">Front Desk</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                8
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
    {{-- New Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #4158D0, #764ba2);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-arrow-up-bold-circle-outline me-1"></i>+8.5%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">NEW PATIENTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="recep-stat-new-patients">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-outline me-1"></i>This week
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-account-plus text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Returning Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #f093fb, #f5576c);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-repeat me-1"></i>+32.5%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">RETURNING PATIENTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="recep-stat-returning-patients">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-calendar-check me-1"></i>Loyalty rate ↑
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-account-check text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Admissions --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #4facfe, #00c9fe);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-hospital-building me-1"></i>12 beds
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">ADMISSIONS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="recep-stat-admissions">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-bed me-1"></i>4 discharges today
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-hospital text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Bookings --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #fa709a, #fee140);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-clock-outline me-1"></i>9 pending
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">BOOKINGS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="recep-stat-bookings">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-calendar-clock me-1"></i>Next: Dr. Lee 2:30 PM
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-calendar-check text-white" style="font-size: 2.2rem;"></i>
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
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Receptionist Shortcuts</h4>
                        <p class="text-secondary mb-0">Quick access to front desk operations</p>
                    </div>
                    <span class="ms-auto d-none d-md-block badge bg-light text-dark rounded-pill px-4 py-2">
                        <i class="mdi mdi-timer-sand me-1"></i>Today: 32 appointments
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- Workbench - Royal Purple --}}
                    <div class="col-6 col-md-3">
                        <a href="{{ route('reception.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-desktop-mac-dashboard" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Workbench</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Main desk</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- New Patient - Emerald Green --}}
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.create') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-plus" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">New Patient</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Register</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- All Patients - Ocean Blue --}}
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0f2fe, #b8e1ff); border: 1px solid #7dd3fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0369a1;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0369a1;">All Patients</h6>
                                <small class="text-dark" style="color: #0369a1 !important;">Directory</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- Billing - Sunset Orange --}}
                    <div class="col-6 col-md-3">
                        <a href="{{ route('billing.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff4e6, #ffe4cc); border: 1px solid #ffc999;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-cash-register" style="font-size: 2.5rem; color: #c2410c;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #c2410c;">Billing</h6>
                                <small class="text-dark" style="color: #c2410c !important;">Payments</small>
                            </div>
                        </a>
                    </div>
                </div>
                
                {{-- Additional Quick Actions Row - Using Only Confirmed Routes --}}
                <div class="row g-3 mt-3">
                    {{-- Appointments - Using existing route or remove if not available --}}
                    @if(Route::has('appointments.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('appointments.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fef9e7, #fef3c7); border: 1px solid #fde68a;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-calendar-check" style="font-size: 2.5rem; color: #b45309;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #b45309;">Appointments</h6>
                                <small class="text-dark" style="color: #b45309 !important;">Schedule</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Insurance - Only if route exists --}}
                    @if(Route::has('insurance.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('insurance.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border: 1px solid #cbd5e1;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-shield-account" style="font-size: 2.5rem; color: #334155;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #334155;">Insurance</h6>
                                <small class="text-dark" style="color: #334155 !important;">Verification</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Emergency - Only if route exists --}}
                    @if(Route::has('emergency.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('emergency.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border: 1px solid #fca5a5;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-alert-octagon" style="font-size: 2.5rem; color: #991b1b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #991b1b;">Emergency</h6>
                                <small class="text-dark" style="color: #991b1b !important;">Triage</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Today's Schedule - Alternative to appointments.create --}}
                    <div class="col-6 col-md-3">
                        <a href="{{ route('reception.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-calendar-today" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Today's</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Schedule</small>
                            </div>
                        </a>
                    </div>
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
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-account-multiple-plus text-primary" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Patient Registrations</h5>
                            <p class="text-secondary mb-0">This month's registration trend</p>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2">
                        <i class="mdi mdi-trending-up me-1"></i>+15.3%
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="recepRegistrationsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-calendar-check text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Appointments</h5>
                            <p class="text-secondary mb-0">Daily appointment distribution</p>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-4 py-2">
                        <i class="mdi mdi-calendar-clock me-1"></i>32 today
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="recepAppointmentsChart"></canvas>
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
    // Patient Registrations Chart
    if (document.getElementById('recepRegistrationsChart')) {
        const ctx = document.getElementById('recepRegistrationsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [{
                    label: 'New Patients',
                    data: [24, 32, 28, 35, 42],
                    borderColor: '#4158D0',
                    backgroundColor: 'rgba(65, 88, 208, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#4158D0',
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

    // Appointments Chart
    if (document.getElementById('recepAppointmentsChart')) {
        const ctx = document.getElementById('recepAppointmentsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Appointments',
                    data: [18, 22, 20, 25, 30, 12],
                    backgroundColor: [
                        'rgba(250, 112, 154, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(74, 207, 142, 0.8)',
                        'rgba(255, 159, 67, 0.8)'
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
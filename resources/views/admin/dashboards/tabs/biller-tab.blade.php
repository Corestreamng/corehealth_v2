{{-- Biller/Accounts Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(135deg, #4158D0 0%, #C850C0 46%, #FFCC70 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-account-tie text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Biller</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-chart-arc text-white me-1"></i>
                            <span class="fw-semibold text-white">EMR v2.0</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                3
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
    {{-- Today's Revenue --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 16px; background: linear-gradient(145deg, #0b6e4f, #0a8c5e);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-arrow-up-bold-circle-outline me-1"></i>+12.5%
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TODAY'S REVENUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="biller-stat-revenue">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-outline me-1"></i>Updated just now
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-cash-multiple text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Payment Requests --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 16px; background: linear-gradient(145deg, #b34180, #d44c66);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-alert-circle-outline me-1"></i>3 urgent
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">PAYMENT REQUESTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="biller-stat-payment-requests">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-outline me-1"></i>Awaiting action
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-clipboard-list text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- My Payments --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 16px; background: linear-gradient(145deg, #4a4e8f, #5d54a4);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-check-circle-outline me-1"></i>42 total
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">MY PAYMENTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="biller-stat-my-payments">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-cash-check me-1"></i>$12,450 processed
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-receipt text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Consultations --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 16px; background: linear-gradient(145deg, #c44536, #e17b5c);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-calendar-check me-1"></i>+6 today
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">CONSULTATIONS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="biller-stat-consultations">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-clock-outline me-1"></i>Next: Dr. Smith - 2:30 PM
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-stethoscope text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts -- Colorful Card Design --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="mdi mdi-lightning-bolt text-primary" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Billing & Accounts Shortcuts</h4>
                        <p class="text-secondary mb-0">Quick access to your most used billing tools</p>
                    </div>
                </div>
                
                <div class="row g-3">
                    {{-- Billing Workbench - Ocean Breeze --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('billing.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7ff, #bae7ff); border: 1px solid #91d5ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-view-dashboard" style="font-size: 2.5rem; color: #0050b3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0050b3;">Billing</h6>
                                <small class="text-dark" style="color: #0050b3 !important;">Workbench</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- Payment Requests - Sunset Glow --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('product-or-service-request.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff7e6, #ffe7ba); border: 1px solid #ffc069;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-clipboard-list" style="font-size: 2.5rem; color: #d46b00;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #d46b00;">Payment</h6>
                                <small class="text-dark" style="color: #d46b00 !important;">Requests</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- My Transactions - Mint Fresh --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('my-transactions') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f6ffed, #d9f7be); border: 1px solid #b7eb8f;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-receipt" style="font-size: 2.5rem; color: #237804;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #237804;">My</h6>
                                <small class="text-dark" style="color: #237804 !important;">Transactions</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- Consultations - Lavender Dream --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('allPrevEncounters') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f9f0ff, #efdbff); border: 1px solid #d3adf7;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-stethoscope" style="font-size: 2.5rem; color: #531dab;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #531dab;">Consultations</h6>
                                <small class="text-dark" style="color: #531dab !important;">History</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- All Patients - Peach Sorbet --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff2e8, #ffd8bf); border: 1px solid #ffbb96;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #c41e3a;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #c41e3a;">All</h6>
                                <small class="text-dark" style="color: #c41e3a !important;">Patients</small>
                            </div>
                        </a>
                    </div>
                    
                    {{-- More Settings - Slate Elegance --}}
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f0f2f5, #e6e9f0); border: 1px solid #cdd2da;">
                            <div class="shortcut-icon-wrapper mb-3">
                                <i class="mdi mdi-cog" style="font-size: 2.5rem; color: #5a6778;"></i>
                            </div>
                            <h6 class="fw-bold mb-1" style="color: #5a6778;">More</h6>
                            <small class="text-dark" style="color: #5a6778 !important;">Settings</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section with Modern Redesign --}}
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-chart-line text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1" style="color: #1a2639;">Revenue Trend</h4>
                            <p class="text-secondary mb-0">Monthly performance overview</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark rounded-pill px-4 py-2">
                            <i class="mdi mdi-calendar me-1"></i>This Month
                        </span>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2">
                            <i class="mdi mdi-trending-up me-1"></i>+23.5%
                        </span>
                    </div>
                </div>
                <div style="height: 300px;">
                    <canvas id="billerRevenueChart"></canvas>
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
    box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important;
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
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.shortcut-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important;
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
}

/* Additional color hover effects for shortcut cards */
.shortcut-card[style*="Ocean Breeze"]:hover {
    background: linear-gradient(145deg, #bae7ff, #91d5ff) !important;
}
.shortcut-card[style*="Sunset Glow"]:hover {
    background: linear-gradient(145deg, #ffe7ba, #ffc069) !important;
}
.shortcut-card[style*="Mint Fresh"]:hover {
    background: linear-gradient(145deg, #d9f7be, #b7eb8f) !important;
}
.shortcut-card[style*="Lavender Dream"]:hover {
    background: linear-gradient(145deg, #efdbff, #d3adf7) !important;
}
.shortcut-card[style*="Peach Sorbet"]:hover {
    background: linear-gradient(145deg, #ffd8bf, #ffbb96) !important;
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
    if (document.getElementById('billerRevenueChart')) {
        const ctx = document.getElementById('billerRevenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                datasets: [{
                    label: 'Revenue',
                    data: [2840, 3540, 4080, 4750, 5120],
                    borderColor: '#2a9d8f',
                    backgroundColor: 'rgba(42, 157, 143, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#2a9d8f',
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
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
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
});
</script>
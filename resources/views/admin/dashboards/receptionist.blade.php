<div class="row g-4 mb-5">
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px;">
            <div class="card-body m-3 text-white position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title fw-light mb-2 opacity-75">New Patients</h6>
                        <h2 class="fw-bold mb-1" id="stat-new-patients">-</h2>
                        <p class="mb-0 small opacity-75">Registered Today</p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-plus mdi-24px"></i>
                    </div>
                </div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="mdi mdi-account-plus" style="font-size: 120px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 20px;">
            <div class="card-body m-3 text-white position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title fw-light mb-2 opacity-75">Returning Patients</h6>
                        <h2 class="fw-bold mb-1" id="stat-returning-patients">-</h2>
                        <p class="mb-0 small opacity-75">Seen Today</p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-check mdi-24px"></i>
                    </div>
                </div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="mdi mdi-account-check" style="font-size: 120px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 20px;">
            <div class="card-body m-3 text-white position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title fw-light mb-2 opacity-75">Admissions</h6>
                        <h2 class="fw-bold mb-1" id="stat-admissions">-</h2>
                        <p class="mb-0 small opacity-75">Admitted Today</p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-hospital mdi-24px"></i>
                    </div>
                </div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="mdi mdi-hospital" style="font-size: 120px;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-lg position-relative overflow-hidden" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 20px;">
            <div class="card-body m-3 text-white position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title fw-light mb-2 opacity-75">Bookings</h6>
                        <h2 class="fw-bold mb-1" id="stat-bookings">-</h2>
                        <p class="mb-0 small opacity-75">Booked Today</p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-calendar-check mdi-24px"></i>
                    </div>
                </div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="mdi mdi-calendar-check" style="font-size: 120px;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Totals Section - Modern Minimal Cards -->
<div class="row g-4 mb-5">
    @foreach ([
        ['title' => 'Total Patients', 'id' => 'stat-total-patients', 'icon' => 'mdi-account-multiple'],
        ['title' => 'Total Admissions', 'id' => 'stat-total-admissions', 'icon' => 'mdi-hospital-building'],
        ['title' => 'Total Bookings', 'id' => 'stat-total-bookings', 'icon' => 'mdi-calendar-text'],
        ['title' => 'Total Encounters', 'id' => 'stat-total-encounters', 'icon' => 'mdi-stethoscope']
    ] as $stat)
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-muted mb-2 fw-normal">{{ $stat['title'] }}</h6>
                        <h3 class="fw-bold text-dark mb-0" id="{{ $stat['id'] }}">-</h3>
                    </div>
                    <div class="bg-light rounded-circle p-3">
                        <i class="mdi {{ $stat['icon'] }} mdi-24px text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Role-based Advanced Shortcuts and Widgets - Modern Grid Layout --}}
<div class="row g-4 mb-5">
    {{-- Receptionist Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN|RECEPTIONIST')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-account-tie mdi-24px text-primary"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Receptionist Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <a href="{{ route('patient.create') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-plus me-2"></i>New Patient
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('add-to-queue') }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-arrow-right me-2"></i>Queue Patient
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('admission-requests.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-hospital me-2"></i>Admissions
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('beds.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="fa fa-bed me-2"></i>Beds
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="#" class="btn btn-outline-warning w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-calendar-edit me-2"></i>Bookings
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('product-or-service-request.index') }}" class="btn btn-outline-success w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-cash-multiple me-2"></i>Payments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole

    {{-- Doctor Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN|DOCTOR')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-stethoscope mdi-24px text-success"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Doctor Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <a href="{{ route('encounters.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-stethoscope me-2"></i>All Consultations
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-multiple me-2"></i>Patients
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('add-to-queue') }}" class="btn btn-outline-warning w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-arrow-right me-2"></i>Patient Lookup
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole

    {{-- Nurse Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN|NURSE')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-danger bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-heart-pulse mdi-24px text-danger"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Nursing Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <a href="{{ route('vitals.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-heart-pulse me-2"></i>Queue
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('vitals.index', ['history' => true]) }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-history me-2"></i>History
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('admission-requests.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-hospital me-2"></i>Admissions
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('beds.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="fa fa-bed me-2"></i>Beds
                        </a>
                    </div>
                    <div class="col-12 col-md-8">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-success w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-multiple me-2"></i>Patients
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole

    {{-- Lab/Investigation Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN|LAB SCIENTIST|RADIOLOGIST')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-info bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-flask mdi-24px text-info"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Lab/Investigation Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <a href="{{ route('services-category.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-flask me-2"></i>Categories
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('services.index') }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-flask-outline me-2"></i>Services
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('service-requests.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-format-list-bulleted me-2"></i>Queue
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('service-requests.index', ['history' => true]) }}" class="btn btn-outline-success w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-history me-2"></i>History
                        </a>
                    </div>
                    <div class="col-12 col-md-8">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-multiple me-2"></i>Patients
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole

    {{-- Store/Pharmacy Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN|STORE|PHARMACIST')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-store mdi-24px text-warning"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Store/Pharmacy Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <a href="{{ route('product-requests.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-cart me-2"></i>My Queue
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('product-requests.index', ['history' => true]) }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-history me-2"></i>History
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('product-category.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-tag-multiple me-2"></i>Categories
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('stores.index') }}" class="btn btn-outline-success w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-store me-2"></i>Stores
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-pill me-2"></i>Products
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-multiple me-2"></i>Patients
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole

    {{-- Admin Shortcuts --}}
    @hasanyrole('SUPERADMIN|ADMIN')
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg" style="border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.9) 100%); backdrop-filter: blur(10px);">
            <div class="card-body m-3">
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-dark bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="mdi mdi-shield-account mdi-24px text-dark"></i>
                    </div>
                    <h5 class="card-title mb-0 fw-bold">Administration Shortcuts</h5>
                </div>
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <a href="{{ route('roles.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-shield-account me-2"></i>Roles
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('permissions.index') }}" class="btn btn-outline-info w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-lock me-2"></i>Permissions
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('staff.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-account-tie me-2"></i>Staff
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('specializations.index') }}" class="btn btn-outline-success w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-star me-2"></i>Specializations
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('clinics.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-hospital-building me-2"></i>Clinics
                        </a>
                    </div>
                    <div class="col-6 col-md-4">
                        <a href="{{ route('hmo.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 text-start">
                            <i class="mdi mdi-cash me-2"></i>HMOs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endhasanyrole
</div>

<!-- Chart Filters - Modern Design -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 8px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.95) 100%);">
            <div class="card-body m-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                            <i class="mdi mdi-chart-line mdi-20px text-secondary"></i>
                        </div>
                        <label class="fw-bold mb-0 text-dark">Analytics Date Range:</label>
                    </div>
                    <select class="form-select chart-date-range shadow-sm" style="width: auto; min-width: 180px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1);">
                        <option value="today">Today</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="this_quarter">This Quarter</option>
                        <option value="last_six_months">Last Six Months</option>
                        <option value="this_year">This Year</option>
                        <option value="custom">Custom Range</option>
                        <option value="all_time">All Time</option>
                    </select>
                    <input type="date" class="form-control custom-date-start shadow-sm" style="width: auto; min-width: 160px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); display: none;">
                    <input type="date" class="form-control custom-date-end shadow-sm" style="width: auto; min-width: 160px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); display: none;">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Grid - Modern Chart Cards -->
<div class="row g-4">
    @foreach([
        ['title' => 'Appointments Over Time', 'id' => 'appointmentsOverTime', 'icon' => 'mdi-chart-line', 'color' => 'primary'],
        ['title' => 'Appointments by Clinic', 'id' => 'appointmentsByClinic', 'icon' => 'mdi-chart-donut', 'color' => 'success'],
        ['title' => 'Top Clinic Services', 'id' => 'topClinicServices', 'icon' => 'mdi-chart-bar', 'color' => 'info'],
        ['title' => 'Queue Status Overview', 'id' => 'queueStatusChart', 'icon' => 'mdi-chart-pie', 'color' => 'warning']
    ] as $chart)
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-400" style="border-radius: 10px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(2px);">
            <div class="card-header border-0 bg-transparent pt-4 pb-2">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-{{ $chart['color'] }} bg-opacity-10 rounded-circle p-3 me-3">
                            <i class="mdi {{ $chart['icon'] }} mdi-24px text-secondary"></i>
                        </div>
                        <h5 class="card-title mb-0 fw-bold">{{ $chart['title'] }}</h5>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                            <i class="mdi mdi-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: 12px;">
                            <li><a class="dropdown-item" href="#"><i class="mdi mdi-refresh me-2"></i>Refresh</a></li>
                            <li><a class="dropdown-item" href="#"><i class="mdi mdi-download me-2"></i>Export</a></li>
                            <li><a class="dropdown-item" href="#"><i class="mdi mdi-fullscreen me-2"></i>Full Screen</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="chart-container position-relative" style="height: 320px;">
                    <canvas id="{{ $chart['id'] }}" class="w-200 h-200"></canvas>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<style>
/* Custom styles for modern look */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

.btn {
    transition: all 0.3s ease;
    border: none;
    font-weight: 500;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-outline-primary:hover,
.btn-outline-info:hover,
.btn-outline-success:hover,
.btn-outline-warning:hover,
.btn-outline-secondary:hover,
.btn-outline-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.form-select,
.form-control {
    transition: all 0.3s ease;
}

.form-select:focus,
.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    border-color: rgba(13, 110, 253, 0.5);
    transform: translateY(-1px);
}

.chart-container {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(248, 250, 252, 0.1) 100%);
    border-radius: 12px;
    padding: 15px;
}

@media (max-width: 768px) {
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }

    .card-body {
        padding: 1.25rem;
    }

    .row.g-4 {
        --bs-gutter-x: 1rem;
        --bs-gutter-y: 1rem;
    }
}

/* Glassmorphism effect for modern cards */
.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    border-radius: inherit;
    pointer-events: none;
}

/* Smooth animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.6s ease-out;
}

/* Custom scrollbar for modern look */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}
</style>
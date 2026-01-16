{{-- Administration Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Staff</h6>
                        <h2 class="fw-bold mb-0" id="admin-stat-staff">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-group mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #833ab4 0%, #fd1d1d 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Patients</h6>
                        <h2 class="fw-bold mb-0" id="admin-stat-patients">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-multiple mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Clinics</h6>
                        <h2 class="fw-bold mb-0" id="admin-stat-clinics">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-hospital-building mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Revenue</h6>
                        <h2 class="fw-bold mb-0" id="admin-stat-revenue">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-chart-line mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts --}}
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="mdi mdi-shield-account text-dark me-2"></i>Administration Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('roles.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-shield-account d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Roles</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('permissions.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-lock d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Permissions</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('staff.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-tie d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Staff</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('specializations.index') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-star d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Specializations</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('clinics.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-hospital-building d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Clinics</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-cash d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">HMOs</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hospital-config.index') }}" class="btn btn-outline-dark w-100 rounded-pill py-3">
                            <i class="mdi mdi-cogs d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Hospital Config</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-dark w-100 rounded-pill py-3">
                            <i class="mdi mdi-history d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Audit Logs</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('wards.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-hospital-marker d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Wards</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('beds.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-bed d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Beds</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('vaccine-schedule.index') }}" class="btn btn-outline-danger w-100 rounded-pill py-3">
                            <i class="mdi mdi-needle d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Vaccine Schedule</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('transactions') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-chart-line d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">All Transactions</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section --}}
<div class="row g-4 mt-2">
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="mdi mdi-chart-line text-success me-2"></i>Revenue This Month
                </h5>
                <div style="height: 250px;">
                    <canvas id="adminRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="mdi mdi-account-multiple-plus text-primary me-2"></i>Patient Registrations This Month
                </h5>
                <div style="height: 250px;">
                    <canvas id="adminRegistrationsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Receptionist Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">New Patients</h6>
                        <h2 class="fw-bold mb-0" id="recep-stat-new-patients">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-plus mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Returning Patients</h6>
                        <h2 class="fw-bold mb-0" id="recep-stat-returning-patients">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-check mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Admissions</h6>
                        <h2 class="fw-bold mb-0" id="recep-stat-admissions">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-hospital mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Bookings</h6>
                        <h2 class="fw-bold mb-0" id="recep-stat-bookings">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-calendar-check mdi-24px"></i>
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
                    <i class="mdi mdi-account-tie text-primary me-2"></i>Receptionist Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('reception.workbench') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-desktop-mac-dashboard d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.create') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-plus d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">New Patient</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">All Patients</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('billing.workbench') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-cash-register d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Billing</span>
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
                    <i class="mdi mdi-account-multiple-plus text-primary me-2"></i>Patient Registrations This Month
                </h5>
                <div style="height: 250px;">
                    <canvas id="recepRegistrationsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="mdi mdi-calendar-check text-success me-2"></i>Appointments This Month
                </h5>
                <div style="height: 250px;">
                    <canvas id="recepAppointmentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

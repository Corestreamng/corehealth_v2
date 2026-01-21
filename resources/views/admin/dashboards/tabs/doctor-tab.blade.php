{{-- Doctor Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Consultations Today</h6>
                        <h2 class="fw-bold mb-0" id="doc-stat-consultations">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-stethoscope mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #4ca1af 0%, #c4e0e5 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Ward Rounds</h6>
                        <h2 class="fw-bold mb-0" id="doc-stat-ward-rounds">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-hospital-building mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #02aab0 0%, #00cdac 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">My Patients</h6>
                        <h2 class="fw-bold mb-0" id="doc-stat-patients">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-multiple mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #108dc7 0%, #ef8e38 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Appointments</h6>
                        <h2 class="fw-bold mb-0" id="doc-stat-appointments">-</h2>
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
        <div class="card-modern border-0 shadow-sm" style="border-radius: 12px;">
            <div class="card-body">
                <h5 class="card-title mb-4">
                    <i class="mdi mdi-stethoscope text-success me-2"></i>Doctor Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('encounters.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-stethoscope d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">All Consultations</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Patients</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('add-to-queue') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-arrow-right d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Patient Lookup</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

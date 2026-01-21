{{-- HMO Executive Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #5433ff 0%, #20bdff 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">HMO Patients</h6>
                        <h2 class="fw-bold mb-0" id="hmo-stat-patients">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-account-group mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #667db6 0%, #0082c8 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Pending Claims</h6>
                        <h2 class="fw-bold mb-0" id="hmo-stat-pending-claims">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-clipboard-text mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #06beb6 0%, #48b1bf 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Approved Claims</h6>
                        <h2 class="fw-bold mb-0" id="hmo-stat-approved-claims">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-check-circle mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #3a7bd5 0%, #3a6073 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total HMOs</h6>
                        <h2 class="fw-bold mb-0" id="hmo-stat-total-hmos">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-medical-bag mdi-24px"></i>
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
                    <i class="mdi mdi-medical-bag text-primary me-2"></i>HMO Executive Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.workbench') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-hospital-building d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">HMO Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.reports') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-file-chart d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">HMO Reports</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">All Patients</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index', ['hmo_only' => 1]) }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-group d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">HMO Patients</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

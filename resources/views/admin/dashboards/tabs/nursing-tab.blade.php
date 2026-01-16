{{-- Nursing Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #f12711 0%, #f5af19 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Vitals Queue</h6>
                        <h2 class="fw-bold mb-0" id="nurs-stat-vitals-queue">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-heart-pulse mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #c21500 0%, #ffc500 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Bed Requests</h6>
                        <h2 class="fw-bold mb-0" id="nurs-stat-bed-requests">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-hospital mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Medication Due</h6>
                        <h2 class="fw-bold mb-0" id="nurs-stat-medication-due">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-pill mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Admitted Patients</h6>
                        <h2 class="fw-bold mb-0" id="nurs-stat-admitted">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-bed mdi-24px"></i>
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
                    <i class="mdi mdi-heart-pulse text-danger me-2"></i>Nursing Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('nursing-workbench.index') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-hospital-box d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Nursing Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('vitals.index') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-heart-pulse d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Vitals Queue</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('admission-requests.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-hospital d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Admissions</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('beds.index') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-bed d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Beds</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-warning w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Patients</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

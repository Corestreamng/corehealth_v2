{{-- Lab Scientist/Radiologist Dashboard Tab --}}
<div class="row g-4 mb-4">
    {{-- Quick Stats --}}
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #0575e6 0%, #021b79 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Lab Queue</h6>
                        <h2 class="fw-bold mb-0" id="lab-stat-queue">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-flask mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #373b44 0%, #4286f4 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Imaging Queue</h6>
                        <h2 class="fw-bold mb-0" id="lab-stat-imaging">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-radiobox-marked mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #2b5876 0%, #4e4376 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Completed Today</h6>
                        <h2 class="fw-bold mb-0" id="lab-stat-completed">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-check-circle mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="card-modern border-0 shadow-sm h-100" style="border-radius: 12px; background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 100%);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 opacity-75">Total Services</h6>
                        <h2 class="fw-bold mb-0" id="lab-stat-services">-</h2>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-circle p-3">
                        <i class="mdi mdi-flask-outline mdi-24px"></i>
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
                    <i class="mdi mdi-flask text-info me-2"></i>Lab/Investigation Shortcuts
                </h5>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.workbench') }}" class="btn btn-outline-primary w-100 rounded-pill py-3">
                            <i class="mdi mdi-flask d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Lab Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('imaging.workbench') }}" class="btn btn-outline-info w-100 rounded-pill py-3">
                            <i class="mdi mdi-radiobox-marked d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Imaging Workbench</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('services-category.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-tag-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Categories</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-3">
                            <i class="mdi mdi-flask-outline d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Services</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="btn btn-outline-success w-100 rounded-pill py-3">
                            <i class="mdi mdi-account-multiple d-block mb-2" style="font-size: 2rem;"></i>
                            <span class="fw-bold">Patients</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

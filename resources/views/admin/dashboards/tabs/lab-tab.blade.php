{{-- Lab Scientist/Radiologist Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(145deg, #0575e6 0%, #4286f4 50%, #00c6fb 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-microscope text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Scientist</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-flask text-white me-1"></i>
                            <span class="fw-semibold text-white">Lab & Imaging</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                9
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
    {{-- Lab Queue --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #0575e6, #021b79);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-clock-outline me-1"></i>+12 since morning
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">LAB QUEUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="lab-stat-queue">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-flask me-1"></i>Avg wait: 15 min
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-flask text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Imaging Queue --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #373b44, #4286f4);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-alert-circle-outline me-1"></i>4 urgent
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">IMAGING QUEUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="lab-stat-imaging">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-radiobox-marked me-1"></i>MRI: 3, X-Ray: 8
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-radiobox-marked text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Completed Today --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #2b5876, #4e4376);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-check-circle-outline me-1"></i>+8 vs yesterday
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">COMPLETED TODAY</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="lab-stat-completed">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-check-circle me-1"></i>98% on time
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-check-circle text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total Services --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #1a2a6c, #b21f1f);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-package-variant me-1"></i>Active services
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">TOTAL SERVICES</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="lab-stat-services">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-flask-outline me-1"></i>Lab: 45, Imaging: 28
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-flask-outline text-white" style="font-size: 2.2rem;"></i>
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
                    <div class="bg-info bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="mdi mdi-lightning-bolt-circle text-info" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Lab & Investigation Shortcuts</h4>
                        <p class="text-secondary mb-0">Quick access to diagnostic and imaging tools</p>
                    </div>
                    <span class="ms-auto d-none d-md-block badge bg-info bg-opacity-10 text-info rounded-pill px-4 py-2">
                        <i class="mdi mdi-flask me-1"></i>Both departments
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- Lab Workbench - Primary Blue --}}
                    @if(Route::has('lab.workbench'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7ff, #bae7ff); border: 1px solid #91d5ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-flask" style="font-size: 2.5rem; color: #0050b3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0050b3;">Lab</h6>
                                <small class="text-dark" style="color: #0050b3 !important;">Workbench</small>
                            </div>
                        </a>
                    </div>
                    @else
                    <div class="col-6 col-md-3">
                        <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f5f5f5, #e8e8e8); border: 1px solid #d9d9d9; opacity: 0.8;">
                            <div class="shortcut-icon-wrapper mb-3">
                                <i class="mdi mdi-flask" style="font-size: 2.5rem; color: #8c8c8c;"></i>
                            </div>
                            <h6 class="fw-bold mb-1" style="color: #595959;">Lab</h6>
                            <small class="text-dark" style="color: #595959 !important;">Workbench</small>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Imaging Workbench - Cyan --}}
                    @if(Route::has('imaging.workbench'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('imaging.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border: 1px solid #5eead4;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-radiobox-marked" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0f766e;">Imaging</h6>
                                <small class="text-dark" style="color: #0f766e !important;">Workbench</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Categories - Sunset Orange --}}
                    @if(Route::has('services-category.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('services-category.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff7e6, #ffe7ba); border: 1px solid #ffc069;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-tag-multiple" style="font-size: 2.5rem; color: #d46b00;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #d46b00;">Categories</h6>
                                <small class="text-dark" style="color: #d46b00 !important;">Service types</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Services - Purple --}}
                    @if(Route::has('services.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('services.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-flask-outline" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Services</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Test catalog</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Patients - Green --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">Patients</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Directory</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
                
                {{-- Additional Lab/Imaging Shortcuts Row --}}
                <div class="row g-3 mt-3">
                    {{-- Results Entry - Teal --}}
                    @if(Route::has('lab.results'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.results') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #d1e7ff, #a6d0ff); border: 1px solid #7ab7ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-clipboard-text" style="font-size: 2.5rem; color: #02669b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #02669b;">Results</h6>
                                <small class="text-dark" style="color: #02669b !important;">Entry</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Pending Approvals - Red --}}
                    @if(Route::has('lab.pending'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.pending') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border: 1px solid #fca5a5;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-clock-alert" style="font-size: 2.5rem; color: #991b1b;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #991b1b;">Pending</h6>
                                <small class="text-dark" style="color: #991b1b !important;">Approvals</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Equipment Status - Gray --}}
                    @if(Route::has('equipment.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('equipment.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border: 1px solid #cbd5e1;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-x-ray" style="font-size: 2.5rem; color: #334155;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #334155;">Equipment</h6>
                                <small class="text-dark" style="color: #334155 !important;">Status</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Quality Control - Yellow --}}
                    @if(Route::has('lab.qc'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.qc') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fef9e7, #fef3c7); border: 1px solid #fde68a;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-check-decagram" style="font-size: 2.5rem; color: #b45309;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #b45309;">QC</h6>
                                <small class="text-dark" style="color: #b45309 !important;">Quality</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Reports - Dark Blue --}}
                    @if(Route::has('lab.reports'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.reports') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border: 1px solid #93c5fd;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-chart-bar" style="font-size: 2.5rem; color: #1e40af;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e40af;">Reports</h6>
                                <small class="text-dark" style="color: #1e40af !important;">Analytics</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Reference Ranges - Brown --}}
                    @if(Route::has('lab.ranges'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.ranges') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f4e6d1, #ecdcc0); border: 1px solid #d4b48c;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-table" style="font-size: 2.5rem; color: #7b4b2d;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #7b4b2d;">Reference</h6>
                                <small class="text-dark" style="color: #7b4b2d !important;">Ranges</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section for Lab/Imaging Dashboard --}}
<div class="row g-4">
    {{-- Queue Trends --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-chart-line text-primary" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Queue Trends</h5>
                            <p class="text-secondary mb-0">Today's lab & imaging workload</p>
                        </div>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-4 py-2">
                        <i class="mdi mdi-clock-outline me-1"></i>Peak: 10:00 AM
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="labQueueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Test Type Distribution --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-chart-pie text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Service Distribution</h5>
                            <p class="text-secondary mb-0">Tests and imaging by type</p>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2">
                        <i class="mdi mdi-flask me-1"></i>Total: 156
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="labDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Pending Results Table --}}
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-clock-alert text-warning" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Pending Results</h5>
                            <p class="text-secondary mb-0">Tests awaiting processing and verification</p>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-4 py-2">
                        <i class="mdi mdi-alert me-1"></i>15 urgent
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">Time</th>
                                <th class="py-3">Patient</th>
                                <th class="py-3">Test/Imaging</th>
                                <th class="py-3">Department</th>
                                <th class="py-3">Priority</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="fw-semibold">09:30 AM</span></td>
                                <td>Emily Davis</td>
                                <td>Complete Blood Count</td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">Lab</span></td>
                                <td><span class="badge bg-danger bg-opacity-20 text-danger px-3 py-2 rounded-pill">STAT</span></td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">Processing</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-clipboard-text me-1"></i>Enter
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-semibold">10:15 AM</span></td>
                                <td>Michael Brown</td>
                                <td>Chest X-Ray</td>
                                <td><span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Imaging</span></td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">High</span></td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-camera me-1"></i>Review
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-semibold">11:00 AM</span></td>
                                <td>Sarah Wilson</td>
                                <td>Lipid Panel</td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">Lab</span></td>
                                <td><span class="badge bg-success bg-opacity-20 text-success px-3 py-2 rounded-pill">Routine</span></td>
                                <td><span class="badge bg-secondary bg-opacity-20 text-secondary px-3 py-2 rounded-pill">Collected</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-clipboard-text me-1"></i>Enter
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-semibold">11:45 AM</span></td>
                                <td>James Lee</td>
                                <td>MRI Brain</td>
                                <td><span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Imaging</span></td>
                                <td><span class="badge bg-danger bg-opacity-20 text-danger px-3 py-2 rounded-pill">STAT</span></td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">In Progress</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-radiobox-marked me-1"></i>Process
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-outline-secondary rounded-pill px-4">
                        <i class="mdi mdi-format-list-bulleted me-1"></i>View All Pending
                    </a>
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

.bg-opacity-20 {
    --bs-bg-opacity: 0.2;
}
.bg-warning.bg-opacity-20,
.bg-danger.bg-opacity-20,
.bg-success.bg-opacity-20,
.bg-secondary.bg-opacity-20 {
    background-color: rgba(255, 255, 255, 0.2) !important;
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
    .table {
        font-size: 0.85rem;
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
    .table .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
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

// Chart Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Lab Queue Chart
    if (document.getElementById('labQueueChart')) {
        const ctx = document.getElementById('labQueueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['6-8 AM', '8-10 AM', '10-12 PM', '12-2 PM', '2-4 PM', '4-6 PM'],
                datasets: [
                    {
                        label: 'Lab Tests',
                        data: [12, 24, 32, 28, 22, 18],
                        borderColor: '#0575e6',
                        backgroundColor: 'rgba(5, 117, 230, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#0575e6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Imaging',
                        data: [6, 14, 18, 22, 16, 12],
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
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6
                        }
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
                        title: {
                            display: true,
                            text: 'Number of requests'
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

    // Lab Distribution Chart
    if (document.getElementById('labDistributionChart')) {
        const ctx = document.getElementById('labDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Hematology', 'Chemistry', 'Microbiology', 'X-Ray', 'MRI/CT', 'Ultrasound'],
                datasets: [{
                    data: [42, 38, 25, 22, 15, 14],
                    backgroundColor: [
                        '#0575e6',
                        '#0f766e',
                        '#b21f1f',
                        '#4286f4',
                        '#2b5876',
                        '#4e4376'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1a2639',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                cutout: '65%'
            }
        });
    }

    // Initialize stat values if empty
    const statElements = {
        'lab-stat-queue': '32',
        'lab-stat-imaging': '18',
        'lab-stat-completed': '45',
        'lab-stat-services': '73'
    };

    Object.keys(statElements).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.innerText === '-') {
            el.innerText = statElements[id];
        }
    });
});
</script>
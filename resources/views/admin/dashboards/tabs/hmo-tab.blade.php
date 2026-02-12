{{-- HMO Executive Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100 welcome-card-hmo" style="border-radius: 28px; background: linear-gradient(145deg, #5433ff 0%, #20bdff 50%, #a5fecb 100%); overflow: hidden;">
            <div class="position-relative" style="background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="90" cy="10" r="40" fill="white" opacity="0.08"/><circle cx="20" cy="80" r="60" fill="white" opacity="0.05"/><circle cx="70" cy="60" r="30" fill="white" opacity="0.06"/></svg>')">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <div class="d-flex align-items-center mb-3 mb-md-0">
                            <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur" style="border: 2px solid rgba(255,255,255,0.3);">
                                <i class="mdi mdi-medical-bag text-white" style="font-size: 2.4rem;"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h2 class="fw-bold mb-0 text-white" style="text-shadow: 0 4px 12px rgba(0,0,0,0.12);">Welcome back, HMO Executive</h2>
                                    <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2" style="backdrop-filter: blur(4px);">
                                        <i class="mdi mdi-shield-check me-1" style="font-size: 0.8rem;"></i>Healthcare Partner
                                    </span>
                                </div>
                                <div class="d-flex align-items-center text-white flex-wrap gap-3">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                        <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-office-building me-2" style="font-size: 1.2rem;"></i>
                                        <span class="fw-semibold" style="font-size: 1rem;">Claims Management Center</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="px-4 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur d-flex align-items-center" style="border: 1px solid rgba(255,255,255,0.2);">
                                <i class="mdi mdi-chart-arc text-white me-2"></i>
                                <span class="fw-semibold text-white">Q2 2024</span>
                            </div>
                            <div class="px-4 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur position-relative" style="border: 1px solid rgba(255,255,255,0.2);">
                                <i class="mdi mdi-bell-outline text-white"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem; border: 2px solid white;">
                                    12
                                </span>
                            </div>
                            <div class="px-3 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur d-none d-lg-flex align-items-center" style="border: 1px solid rgba(255,255,255,0.2);">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success me-2" style="width: 10px; height: 10px; box-shadow: 0 0 0 2px rgba(255,255,255,0.2);"></div>
                                    <span class="fw-semibold text-white" style="font-size: 0.9rem;">Active Session</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Quick Stats Summary Strip --}}
                    <div class="row g-3 mt-4">
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-account-group mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-80 d-block">Total Beneficiaries</small>
                                    <span class="fw-bold fs-5">3,245</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-clipboard-text mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-80 d-block">Pending Value</small>
                                    <span class="fw-bold fs-5">$42.5K</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-check-circle mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-80 d-block">Approved This Month</small>
                                    <span class="fw-bold fs-5">$156K</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-hospital-building mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-80 d-block">Active HMOs</small>
                                    <span class="fw-bold fs-5">8</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats Cards -- Premium HMO Analytics Design --}}
<div class="row g-4 mb-4">
    {{-- HMO Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-hmo" style="border-radius: 24px; background: linear-gradient(145deg, #5433ff, #20bdff);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-trending-up me-1"></i>+8.5% vs last month
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">HMO Patients</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="hmo-stat-patients" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-account-plus text-white text-opacity-80 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-80">+124 this quarter</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper-hmo bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-account-group text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Pending Claims --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-hmo" style="border-radius: 24px; background: linear-gradient(145deg, #667db6, #0082c8);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-clock-alert me-1"></i>12 overdue
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Pending Claims</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="hmo-stat-pending-claims" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-currency-usd text-white text-opacity-80 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-80">Total value: $42,580</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper-hmo bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-clipboard-text text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Approved Claims --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-hmo" style="border-radius: 24px; background: linear-gradient(145deg, #06beb6, #48b1bf);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-check-circle me-1"></i>+23% this month
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Approved Claims</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="hmo-stat-approved-claims" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-calendar-check text-white text-opacity-80 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-80">This month: $156,200</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper-hmo bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-check-circle text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Total HMOs --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-hmo" style="border-radius: 24px; background: linear-gradient(145deg, #3a7bd5, #3a6073);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-hospital-building me-1"></i>3 new this year
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Total HMOs</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="hmo-stat-total-hmos" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-star text-white text-opacity-80 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-80">5 premium partners</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper-hmo bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-medical-bag text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts -- Premium HMO Workflow Design --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 28px; background: white; overflow: hidden;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="shortcuts-header-icon-hmo" style="width: 56px; height: 56px; background: linear-gradient(145deg, #5433ff20, #20bdff20); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                        <i class="mdi mdi-lightning-bolt-circle" style="font-size: 2rem; color: #5433ff;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #0a1e3c;">HMO Executive Shortcuts</h4>
                        <p class="text-secondary mb-0">Fast-track claims processing and beneficiary management</p>
                    </div>
                    <span class="ms-auto d-none d-md-flex align-items-center gap-2 bg-light rounded-pill px-4 py-2">
                        <i class="mdi mdi-shield-account text-primary"></i>
                        <span class="fw-semibold" style="color: #0a1e3c;">Executive Access</span>
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- HMO Workbench - Primary Blue --}}
                    @if(Route::has('hmo.workbench'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.workbench') }}" class="text-decoration-none">
                            <div class="shortcut-card-hmo p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f0f5ff, #e6f0ff); border: 1px solid #c7d9ff;">
                                <div class="shortcut-icon-wrapper-hmo mb-3">
                                    <i class="mdi mdi-hospital-building" style="font-size: 2.5rem; color: #5433ff;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e293b;">HMO Workbench</h6>
                                <small class="text-secondary">Claims & approvals</small>
                                <span class="shortcut-badge-hmo mt-2" style="background: #5433ff10; color: #5433ff;">12 pending</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- HMO Reports - Teal --}}
                    @if(Route::has('hmo.reports'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('hmo.reports') }}" class="text-decoration-none">
                            <div class="shortcut-card-hmo p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6fffa, #ccf5f0); border: 1px solid #99e6da;">
                                <div class="shortcut-icon-wrapper-hmo mb-3">
                                    <i class="mdi mdi-file-chart" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e293b;">HMO Reports</h6>
                                <small class="text-secondary">Analytics & insights</small>
                                <span class="shortcut-badge-hmo mt-2" style="background: #0f766e10; color: #0f766e;">Q2 2024</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- All Patients - Green --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card-hmo p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper-hmo mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e293b;">All Patients</h6>
                                <small class="text-secondary">Complete directory</small>
                                <span class="shortcut-badge-hmo mt-2" style="background: #0b5e4210; color: #0b5e42;">8,452 total</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- HMO Patients - Purple --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index', ['hmo_only' => 1]) }}" class="text-decoration-none">
                            <div class="shortcut-card-hmo p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper-hmo mb-3">
                                    <i class="mdi mdi-account-group" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #1e293b;">HMO Patients</h6>
                                <small class="text-secondary">Beneficiaries</small>
                                <span class="shortcut-badge-hmo mt-2" style="background: #6b21a810; color: #6b21a8;">3,245 active</span>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
                
                {{-- Secondary Shortcuts Row --}}
                <div class="row g-3 mt-3">
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center bg-light bg-opacity-50 p-3 rounded-4">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center" style="border: 1px solid #e2e8f0;">
                                    <i class="mdi mdi-file-document me-2" style="color: #5433ff;"></i>Claims Processing
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center" style="border: 1px solid #e2e8f0;">
                                    <i class="mdi mdi-account-check me-2" style="color: #0f766e;"></i>Verification Queue
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center" style="border: 1px solid #e2e8f0;">
                                    <i class="mdi mdi-cash me-2" style="color: #0b5e42;"></i>Reimbursements
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center" style="border: 1px solid #e2e8f0;">
                                    <i class="mdi mdi-shield me-2" style="color: #6b21a8;"></i>Coverage Verification
                                </span>
                            </div>
                            <a href="#" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                <i class="mdi mdi-cog me-1"></i>Configure
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts & Claims Overview Section --}}
<div class="row g-4">
    {{-- Claims Trend Chart --}}
    <div class="col-xl-7 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 28px; background: white; overflow: hidden;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="chart-icon-wrapper" style="width: 48px; height: 48px; background: rgba(84,51,255,0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i class="mdi mdi-chart-line" style="font-size: 1.6rem; color: #5433ff;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #0a1e3c;">Claims Processing Trend</h5>
                            <p class="text-secondary mb-0">Weekly claims volume and approval rates</p>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" type="button" style="border-color: #e2e8f0;">
                            <i class="mdi mdi-calendar me-1"></i> This Year <i class="mdi mdi-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div style="height: 300px;">
                    <canvas id="hmoClaimsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    {{-- HMO Distribution & Quick Stats --}}
    <div class="col-xl-5 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 28px; background: white; overflow: hidden;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="chart-icon-wrapper" style="width: 48px; height: 48px; background: rgba(6,190,182,0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i class="mdi mdi-chart-pie" style="font-size: 1.6rem; color: #06beb6;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #0a1e3c;">HMO Distribution</h5>
                            <p class="text-secondary mb-0">Patient coverage by provider</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div style="height: 200px;">
                    <canvas id="hmoDistributionChart"></canvas>
                </div>
                
                {{-- Top HMOs List --}}
                <div class="mt-4">
                    <h6 class="fw-semibold mb-3" style="color: #1e293b;">Top HMO Partners</h6>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 8px; height: 8px; background: #5433ff; border-radius: 50%;"></div>
                                <span class="fw-medium">HealthFirst</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-secondary" style="font-size: 0.85rem;">1,245 patients</span>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">42%</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 8px; height: 8px; background: #20bdff; border-radius: 50%;"></div>
                                <span class="fw-medium">MediCare Plus</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-secondary" style="font-size: 0.85rem;">892 patients</span>
                                <span class="badge bg-info bg-opacity-10 text-info px-3 py-1">28%</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 8px; height: 8px; background: #06beb6; border-radius: 50%;"></div>
                                <span class="fw-medium">Wellness Alliance</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-secondary" style="font-size: 0.85rem;">654 patients</span>
                                <span class="badge bg-teal bg-opacity-10 text-teal px-3 py-1">18%</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: 8px; height: 8px; background: #667db6; border-radius: 50%;"></div>
                                <span class="fw-medium">BlueCross</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="text-secondary" style="font-size: 0.85rem;">454 patients</span>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">12%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Claims Table --}}
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 28px; background: white; overflow: hidden;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 48px; height: 48px; background: rgba(84,51,255,0.08); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i class="mdi mdi-clipboard-text" style="font-size: 1.6rem; color: #5433ff;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #0a1e3c;">Recent Claims</h5>
                            <p class="text-secondary mb-0">Latest pending and approved claims</p>
                        </div>
                    </div>
                    <a href="{{ route('hmo.workbench') }}" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                        <span>View All Claims</span>
                        <i class="mdi mdi-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" style="min-width: 800px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th class="py-3 ps-4 rounded-start" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Claim ID</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Patient</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">HMO Provider</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Service</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Amount</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Date</th>
                                <th class="py-3" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Status</th>
                                <th class="py-3 pe-4 rounded-end" style="color: #475569; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-4"><span class="fw-semibold" style="color: #0a1e3c;">#CLM-2458</span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <span style="font-weight: 600; color: #475569;">JD</span>
                                        </div>
                                        <div>
                                            <span class="fw-semibold" style="color: #0a1e3c;">John Davis</span>
                                            <span class="d-block text-secondary" style="font-size: 0.75rem;">MRN: 45892</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span style="color: #1e293b;">HealthFirst</span></td>
                                <td><span style="color: #1e293b;">Cardiology Consult</span></td>
                                <td><span class="fw-semibold" style="color: #0a1e3c;">$450.00</span></td>
                                <td><span style="color: #64748b;">2024-02-12</span></td>
                                <td><span class="badge px-3 py-2" style="background: #fef3c7; color: #b45309; border-radius: 100px; font-weight: 600;">Pending Review</span></td>
                                <td class="pe-4">
                                    <button class="btn btn-sm" style="background: white; border: 1px solid #e2e8f0; border-radius: 100px; color: #1e293b; font-weight: 600; padding: 0.4rem 1.2rem;">
                                        Process
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-4"><span class="fw-semibold" style="color: #0a1e3c;">#CLM-2463</span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <span style="font-weight: 600; color: #475569;">SW</span>
                                        </div>
                                        <div>
                                            <span class="fw-semibold" style="color: #0a1e3c;">Sarah Wilson</span>
                                            <span class="d-block text-secondary" style="font-size: 0.75rem;">MRN: 67321</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span style="color: #1e293b;">MediCare Plus</span></td>
                                <td><span style="color: #1e293b;">MRI Brain</span></td>
                                <td><span class="fw-semibold" style="color: #0a1e3c;">$1,250.00</span></td>
                                <td><span style="color: #64748b;">2024-02-12</span></td>
                                <td><span class="badge px-3 py-2" style="background: #dcfce7; color: #166534; border-radius: 100px; font-weight: 600;">Pre-Approved</span></td>
                                <td class="pe-4">
                                    <button class="btn btn-sm" style="background: white; border: 1px solid #e2e8f0; border-radius: 100px; color: #1e293b; font-weight: 600; padding: 0.4rem 1.2rem;">
                                        Verify
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="ps-4"><span class="fw-semibold" style="color: #0a1e3c;">#CLM-2471</span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <span style="font-weight: 600; color: #475569;">MR</span>
                                        </div>
                                        <div>
                                            <span class="fw-semibold" style="color: #0a1e3c;">Michael Rodriguez</span>
                                            <span class="d-block text-secondary" style="font-size: 0.75rem;">MRN: 89124</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span style="color: #1e293b;">Wellness Alliance</span></td>
                                <td><span style="color: #1e293b;">Physical Therapy</span></td>
                                <td><span class="fw-semibold" style="color: #0a1e3c;">$325.00</span></td>
                                <td><span style="color: #64748b;">2024-02-11</span></td>
                                <td><span class="badge px-3 py-2" style="background: #fee2e2; color: #991b1b; border-radius: 100px; font-weight: 600;">Additional Info</span></td>
                                <td class="pe-4">
                                    <button class="btn btn-sm" style="background: white; border: 1px solid #e2e8f0; border-radius: 100px; color: #1e293b; font-weight: 600; padding: 0.4rem 1.2rem;">
                                        Review
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== PREMIUM HMO EXECUTIVE DASHBOARD STYLES ===== */

/* Welcome Card */
.welcome-card-hmo {
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.welcome-card-hmo:hover {
    transform: translateY(-3px);
    box-shadow: 0 25px 40px -12px rgba(84,51,255,0.25) !important;
}
.backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Stat Cards */
.stat-card-hmo {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}
.stat-card-hmo::before {
    content: '';
    position: absolute;
    top: -20px;
    right: -20px;
    width: 120px;
    height: 120px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    transition: all 0.5s ease;
}
.stat-card-hmo:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 30px rgba(0,0,0,0.15) !important;
}
.stat-card-hmo:hover::before {
    transform: scale(1.5);
}
.stat-icon-wrapper-hmo {
    transition: all 0.3s ease;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-card-hmo:hover .stat-icon-wrapper-hmo {
    transform: scale(1.1) rotate(5deg);
    background: rgba(255,255,255,0.25) !important;
}

/* Shortcut Cards */
.shortcut-card-hmo {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
}
.shortcut-card-hmo:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 25px rgba(84,51,255,0.08) !important;
    border-color: rgba(84,51,255,0.2);
}
.shortcut-card-hmo:hover .shortcut-icon-wrapper-hmo i {
    transform: scale(1.15) rotate(3deg);
    color: #5433ff !important;
}
.shortcut-icon-wrapper-hmo i {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.shortcut-badge-hmo {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 100px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .stat-icon-wrapper-hmo {
        width: 55px;
        height: 55px;
    }
    .stat-icon-wrapper-hmo i {
        font-size: 2rem !important;
    }
    .shortcut-card-hmo {
        padding: 1rem !important;
    }
    .shortcut-icon-wrapper-hmo i {
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
    .shortcut-card-hmo {
        padding: 0.75rem !important;
    }
    .display-5 {
        font-size: 2rem;
    }
}
</style>

<script>
// Live Date and Time Update
function updateDateTime() {
    const now = new Date();
    const dateOptions = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
    };
    const timeOptions = {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    };
    
    const dateTimeEl = document.getElementById('currentDateTime');
    if (dateTimeEl) {
        dateTimeEl.innerHTML = now.toLocaleDateString('en-US', dateOptions) + ' • ' + 
                               now.toLocaleTimeString('en-US', timeOptions);
    }
}

// Chart Initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize stat values
    const statElements = {
        'hmo-stat-patients': '3,245',
        'hmo-stat-pending-claims': '124',
        'hmo-stat-approved-claims': '892',
        'hmo-stat-total-hmos': '8'
    };

    Object.keys(statElements).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.innerText === '-') {
            el.innerText = statElements[id];
        }
    });
    
    // HMO Claims Chart
    if (document.getElementById('hmoClaimsChart')) {
        const ctx = document.getElementById('hmoClaimsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7'],
                datasets: [
                    {
                        label: 'Claims Received',
                        data: [45, 52, 48, 58, 62, 55, 68],
                        borderColor: '#5433ff',
                        backgroundColor: 'rgba(84,51,255,0.05)',
                        borderWidth: 3,
                        pointBackgroundColor: '#5433ff',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Claims Approved',
                        data: [32, 38, 35, 42, 45, 40, 52],
                        borderColor: '#06beb6',
                        backgroundColor: 'rgba(6,190,182,0.05)',
                        borderWidth: 3,
                        pointBackgroundColor: '#06beb6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
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
                            boxWidth: 6,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0a1e3c',
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
                            color: 'rgba(0,0,0,0.03)',
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
    
    // HMO Distribution Chart
    if (document.getElementById('hmoDistributionChart')) {
        const ctx = document.getElementById('hmoDistributionChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['HealthFirst', 'MediCare Plus', 'Wellness Alliance', 'BlueCross', 'Others'],
                datasets: [{
                    data: [1245, 892, 654, 454, 245],
                    backgroundColor: [
                        '#5433ff',
                        '#20bdff',
                        '#06beb6',
                        '#667db6',
                        '#3a7bd5'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0a1e3c',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} patients (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Initial update
    updateDateTime();
    // Update every second
    setInterval(updateDateTime, 1000);
});
</script>
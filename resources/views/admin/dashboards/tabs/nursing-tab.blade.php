{{-- Nursing Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: linear-gradient(145deg, #ff6b6b 0%, #ee5a6f 50%, #ff8e88 100%);">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur">
                            <i class="mdi mdi-nurse text-white" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-1 text-white" style="text-shadow: 0 2px 10px rgba(0,0,0,0.1);">Welcome back, Nurse</h2>
                            <div class="d-flex align-items-center text-white">
                                <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur">
                            <i class="mdi mdi-heart-pulse text-white me-1"></i>
                            <span class="fw-semibold text-white">Ward 2A</span>
                        </div>
                        <div class="px-4 py-2 bg-white bg-opacity-20 rounded-4 backdrop-blur position-relative">
                            <i class="mdi mdi-bell-outline text-white"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;">
                                7
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
    {{-- Vitals Queue --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #f12711, #f5af19);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-clock-outline me-1"></i>+5 since morning
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">VITALS QUEUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="nurs-stat-vitals-queue">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-heart-pulse me-1"></i>Avg wait: 8 min
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-heart-pulse text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Bed Requests --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #c21500, #ffc500);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-alert-circle-outline me-1"></i>3 urgent
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">BED REQUESTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="nurs-stat-bed-requests">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-hospital me-1"></i>Ward 2A, 3B
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-hospital text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Medication Due --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #ee0979, #ff6a00);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-clock-alert me-1"></i>Due in 30min
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">MEDICATION DUE</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="nurs-stat-medication-due">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-pill me-1"></i>12:30 PM round
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-pill text-white" style="font-size: 2.2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Admitted Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-sm h-100 hover-lift" style="border-radius: 20px; background: linear-gradient(145deg, #ff6b6b, #ee5a6f);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-white mb-2 rounded-pill px-3 py-2">
                            <i class="mdi mdi-bed me-1"></i>4 discharges today
                        </span>
                        <h6 class="text-white text-opacity-75 mb-2 fw-semibold" style="letter-spacing: 0.5px;">ADMITTED PATIENTS</h6>
                        <h2 class="fw-bold text-white mb-0 display-6" id="nurs-stat-admitted">-</h2>
                        <small class="text-white text-opacity-75 mt-2 d-block">
                            <i class="mdi mdi-bed me-1"></i>24/32 beds occupied
                        </small>
                    </div>
                    <div class="icon-wrapper bg-white bg-opacity-15 rounded-3 p-3">
                        <i class="mdi mdi-bed text-white" style="font-size: 2.2rem;"></i>
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
                    <div class="bg-danger bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="mdi mdi-lightning-bolt-circle text-danger" style="font-size: 1.8rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #1a2639;">Nursing Shortcuts</h4>
                        <p class="text-secondary mb-0">Quick access to patient care and ward management</p>
                    </div>
                    <span class="ms-auto d-none d-md-block badge bg-danger bg-opacity-10 text-danger rounded-pill px-4 py-2">
                        <i class="mdi mdi-heart-pulse me-1"></i>On Duty: Morning Shift
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- Nursing Workbench - Primary Blue --}}
                    @if(Route::has('nursing-workbench.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('nursing-workbench.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7ff, #bae7ff); border: 1px solid #91d5ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-hospital-box" style="font-size: 2.5rem; color: #0050b3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0050b3;">Nursing</h6>
                                <small class="text-dark" style="color: #0050b3 !important;">Workbench</small>
                            </div>
                        </a>
                    </div>
                    @else
                    <div class="col-6 col-md-3">
                        <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f5f5f5, #e8e8e8); border: 1px solid #d9d9d9; opacity: 0.8;">
                            <div class="shortcut-icon-wrapper mb-3">
                                <i class="mdi mdi-hospital-box" style="font-size: 2.5rem; color: #8c8c8c;"></i>
                            </div>
                            <h6 class="fw-bold mb-1" style="color: #595959;">Nursing</h6>
                            <small class="text-dark" style="color: #595959 !important;">Workbench</small>
                        </div>
                    </div>
                    @endif
                    
                    {{-- Vitals Queue - Pink/Red --}}
                    @if(Route::has('vitals.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('vitals.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ffe6f0, #ffd9e6); border: 1px solid #ffb3c6;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-heart-pulse" style="font-size: 2.5rem; color: #b3005a;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #b3005a;">Vitals</h6>
                                <small class="text-dark" style="color: #b3005a !important;">Queue</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Admissions - Teal --}}
                    @if(Route::has('admission-requests.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('admission-requests.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border: 1px solid #5eead4;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-hospital" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0f766e;">Admissions</h6>
                                <small class="text-dark" style="color: #0f766e !important;">Requests</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Beds - Green --}}
                    @if(Route::has('beds.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('beds.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6f7e6, #d1fadf); border: 1px solid #a7f3d0;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-bed" style="font-size: 2.5rem; color: #0b5e42;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0b5e42;">Beds</h6>
                                <small class="text-dark" style="color: #0b5e42 !important;">Management</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Patients - Ocean Blue --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0f2fe, #b8e1ff); border: 1px solid #7dd3fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0369a1;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0369a1;">Patients</h6>
                                <small class="text-dark" style="color: #0369a1 !important;">Directory</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
                
                {{-- Additional Nursing Shortcuts Row --}}
                <div class="row g-3 mt-3">
                    {{-- Medication Administration - Purple --}}
                    @if(Route::has('medications.administer'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('medications.administer') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-pill" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Medications</h6>
                                <small class="text-dark" style="color: #6b21a8 !important;">Administration</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Care Plans - Orange --}}
                    @if(Route::has('care-plans.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('care-plans.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff7e6, #ffe7ba); border: 1px solid #ffc069;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-clipboard-text" style="font-size: 2.5rem; color: #d46b00;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #d46b00;">Care Plans</h6>
                                <small class="text-dark" style="color: #d46b00 !important;">Patient care</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Wards - Indigo --}}
                    @if(Route::has('wards.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('wards.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: 1px solid #a5b4fc;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-hospital-marker" style="font-size: 2.5rem; color: #3730a3;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #3730a3;">Wards</h6>
                                <small class="text-dark" style="color: #3730a3 !important;">Assignments</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Shift Handover - Gray --}}
                    @if(Route::has('handover.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('handover.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border: 1px solid #cbd5e1;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-switch" style="font-size: 2.5rem; color: #334155;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #334155;">Handover</h6>
                                <small class="text-dark" style="color: #334155 !important;">Shift report</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Discharge Planning - Yellow --}}
                    @if(Route::has('discharge.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('discharge.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fef9e7, #fef3c7); border: 1px solid #fde68a;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-home-outline" style="font-size: 2.5rem; color: #b45309;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #b45309;">Discharge</h6>
                                <small class="text-dark" style="color: #b45309 !important;">Planning</small>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Nursing Notes - Brown --}}
                    @if(Route::has('nursing-notes.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('nursing-notes.index') }}" class="text-decoration-none">
                            <div class="shortcut-card p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f4e6d1, #ecdcc0); border: 1px solid #d4b48c;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-file-document" style="font-size: 2.5rem; color: #7b4b2d;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #7b4b2d;">Nursing Notes</h6>
                                <small class="text-dark" style="color: #7b4b2d !important;">Documentation</small>
                            </div>
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Section for Nursing Dashboard --}}
<div class="row g-4">
    {{-- Vital Signs Trend --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-heart-pulse text-danger" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Vital Signs Queue</h5>
                            <p class="text-secondary mb-0">Today's patient vitals workload</p>
                        </div>
                    </div>
                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-4 py-2">
                        <i class="mdi mdi-clock-outline me-1"></i>Peak: 10:00 AM
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="nursingVitalsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Bed Occupancy --}}
    <div class="col-xl-6 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 24px; background: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="mdi mdi-bed text-success" style="font-size: 1.8rem;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Bed Occupancy</h5>
                            <p class="text-secondary mb-0">Current ward bed status</p>
                        </div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2">
                        <i class="mdi mdi-bed me-1"></i>75% Occupied
                    </span>
                </div>
                <div style="height: 280px;">
                    <canvas id="nursingBedsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Medication Schedule Row --}}
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
                            <h5 class="fw-bold mb-1" style="color: #1a2639;">Today's Medication Schedule</h5>
                            <p class="text-secondary mb-0">Upcoming medication administrations</p>
                        </div>
                    </div>
                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-4 py-2">
                        <i class="mdi mdi-pill me-1"></i>24 doses remaining
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3">Time</th>
                                <th class="py-3">Patient</th>
                                <th class="py-3">Room/Bed</th>
                                <th class="py-3">Medication</th>
                                <th class="py-3">Dosage</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="fw-semibold">12:30 PM</span></td>
                                <td>John Smith</td>
                                <td>201A - Bed 2</td>
                                <td>Amoxicillin</td>
                                <td>500mg</td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-check me-1"></i>Administer
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-semibold">1:00 PM</span></td>
                                <td>Sarah Johnson</td>
                                <td>202B - Bed 5</td>
                                <td>Metformin</td>
                                <td>850mg</td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-check me-1"></i>Administer
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="fw-semibold">1:30 PM</span></td>
                                <td>Robert Chen</td>
                                <td>203C - Bed 8</td>
                                <td>Lisinopril</td>
                                <td>10mg</td>
                                <td><span class="badge bg-warning bg-opacity-20 text-warning px-3 py-2 rounded-pill">Pending</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="mdi mdi-check me-1"></i>Administer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-outline-secondary rounded-pill px-4">
                        <i class="mdi mdi-calendar-clock me-1"></i>View Full Schedule
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
.bg-warning.bg-opacity-20 {
    background-color: rgba(255, 193, 7, 0.2) !important;
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
    // Vital Signs Queue Chart
    if (document.getElementById('nursingVitalsChart')) {
        const ctx = document.getElementById('nursingVitalsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['6-8 AM', '8-10 AM', '10-12 PM', '12-2 PM', '2-4 PM', '4-6 PM'],
                datasets: [{
                    label: 'Patients Waiting',
                    data: [8, 15, 22, 18, 14, 9],
                    backgroundColor: [
                        'rgba(241, 39, 17, 0.8)',
                        'rgba(241, 39, 17, 0.8)',
                        'rgba(241, 39, 17, 0.8)',
                        'rgba(241, 39, 17, 0.8)',
                        'rgba(241, 39, 17, 0.8)',
                        'rgba(241, 39, 17, 0.8)'
                    ],
                    borderRadius: 8,
                    borderSkipped: false
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
                        title: {
                            display: true,
                            text: 'Number of patients'
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

    // Bed Occupancy Chart
    if (document.getElementById('nursingBedsChart')) {
        const ctx = document.getElementById('nursingBedsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Available', 'Maintenance', 'Discharge Today'],
                datasets: [{
                    data: [24, 6, 2, 4],
                    backgroundColor: [
                        '#ee0979',
                        '#00c9fe',
                        '#f7971e',
                        '#56ab2f'
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
        'nurs-stat-vitals-queue': '18',
        'nurs-stat-bed-requests': '7',
        'nurs-stat-medication-due': '24',
        'nurs-stat-admitted': '24'
    };

    Object.keys(statElements).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.innerText === '-') {
            el.innerText = statElements[id];
        }
    });
});
</script>
{{-- Doctor Dashboard Tab --}}
{{-- Full Width Welcome Card with Bright Gradient & Live Date/Time --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg h-100 welcome-card-doctor" style="border-radius: 28px; background: linear-gradient(145deg, #00c6ff 0%, #0072ff 50%, #00a3ff 100%); overflow: hidden;">
            <div class="position-relative" style="background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="90" cy="10" r="40" fill="white" opacity="0.05"/><circle cx="20" cy="80" r="60" fill="white" opacity="0.03"/><circle cx="70" cy="60" r="30" fill="white" opacity="0.04"/></svg>')">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <div class="d-flex align-items-center mb-3 mb-md-0">
                            <div class="rounded-circle bg-white bg-opacity-25 p-3 me-3 backdrop-blur" style="border: 2px solid rgba(255,255,255,0.3);">
                                <i class="mdi mdi-stethoscope text-white" style="font-size: 2.4rem;"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h2 class="fw-bold mb-0 text-white" style="text-shadow: 0 4px 12px rgba(0,0,0,0.12);">Welcome back, Dr. Smith</h2>
                                    <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2" style="backdrop-filter: blur(4px);">
                                        <i class="mdi mdi-check-circle me-1" style="font-size: 0.8rem;"></i>Cardiology
                                    </span>
                                </div>
                                <div class="d-flex align-items-center text-white flex-wrap gap-3">
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-calendar-clock me-2" style="font-size: 1.2rem;"></i>
                                        <span id="currentDateTime" class="fw-semibold" style="font-size: 1.1rem;"></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="mdi mdi-map-marker me-2" style="font-size: 1.2rem;"></i>
                                        <span class="fw-semibold" style="font-size: 1rem;">Ward 2A • Room 304</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="px-4 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur d-flex align-items-center" style="border: 1px solid rgba(255,255,255,0.2);">
                                <i class="mdi mdi-weather-partly-cloudy text-white me-2"></i>
                                <span class="fw-semibold text-white">22°C • Sunny</span>
                            </div>
                            <div class="px-4 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur position-relative" style="border: 1px solid rgba(255,255,255,0.2);">
                                <i class="mdi mdi-bell-outline text-white"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem; border: 2px solid white;">
                                    6
                                </span>
                            </div>
                            <div class="px-3 py-2 bg-white bg-opacity-15 rounded-4 backdrop-blur d-none d-lg-flex align-items-center" style="border: 1px solid rgba(255,255,255,0.2);">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success me-2" style="width: 10px; height: 10px;"></div>
                                    <span class="fw-semibold text-white" style="font-size: 0.9rem;">On Duty</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Quick Stats Summary --}}
                    <div class="row g-3 mt-4">
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-clock-outline mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-75 d-block">Next Appointment</small>
                                    <span class="fw-bold">11:30 AM</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-bed mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-75 d-block">Ward Patients</small>
                                    <span class="fw-bold">12</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-flask mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-75 d-block">Pending Labs</small>
                                    <span class="fw-bold">3</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="d-flex align-items-center text-white">
                                <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                                    <i class="mdi mdi-message-text mdi-24px"></i>
                                </div>
                                <div>
                                    <small class="text-white text-opacity-75 d-block">Unread</small>
                                    <span class="fw-bold">4</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats Cards -- Modern Glassmorphism Design --}}
<div class="row g-4 mb-4">
    {{-- Consultations Today --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-doctor" style="border-radius: 24px; background: linear-gradient(145deg, #00c6ff, #0072ff);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-trending-up me-1"></i>+12.5%
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Consultations Today</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="doc-stat-consultations" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-clock-outline text-white text-opacity-75 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-75">Next: 11:30 AM • Michael Chen</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-stethoscope text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Ward Rounds --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-doctor" style="border-radius: 24px; background: linear-gradient(145deg, #4ca1af, #2a7c8a);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-hospital-building me-1"></i>2 Wards
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Ward Rounds</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="doc-stat-ward-rounds" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-bed text-white text-opacity-75 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-75">12 patients • 2A, 3B</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-hospital-building text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- My Patients --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-doctor" style="border-radius: 24px; background: linear-gradient(145deg, #02aab0, #00b09b);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-account-plus me-1"></i>+3 New
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">My Patients</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="doc-stat-patients" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-account-group text-white text-opacity-75 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-75">156 total • 24 active</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-account-multiple text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Appointments --}}
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card border-0 shadow-lg h-100 stat-card-doctor" style="border-radius: 24px; background: linear-gradient(145deg, #108dc7, #ef8e38);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white bg-opacity-30 text-white rounded-pill px-3 py-2">
                                <i class="mdi mdi-clock-alert me-1"></i>2 Urgent
                            </span>
                        </div>
                        <h6 class="text-white text-opacity-80 mb-2 fw-semibold text-uppercase" style="letter-spacing: 0.8px; font-size: 0.8rem;">Appointments</h6>
                        <h2 class="fw-bold text-white mb-0 display-5" id="doc-stat-appointments" style="font-size: 2.8rem;">-</h2>
                        <div class="d-flex align-items-center mt-2">
                            <i class="mdi mdi-calendar-check text-white text-opacity-75 me-1" style="font-size: 0.9rem;"></i>
                            <small class="text-white text-opacity-75">8 today • 4 remaining</small>
                        </div>
                    </div>
                    <div class="stat-icon-wrapper bg-white bg-opacity-20 rounded-4 p-3">
                        <i class="mdi mdi-calendar-check text-white" style="font-size: 2.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Shortcuts -- Premium Glass Card Design --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="border-radius: 28px; background: white; overflow: hidden;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="shortcuts-header-icon bg-primary bg-opacity-10 p-3 rounded-4 me-3">
                        <i class="mdi mdi-lightning-bolt-circle text-primary" style="font-size: 2rem;"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1" style="color: #0a1e3c;">Clinical Workflow</h4>
                        <p class="text-secondary mb-0">Quick access to your most used clinical tools</p>
                    </div>
                    <span class="ms-auto d-none d-md-flex align-items-center gap-2 bg-light rounded-pill px-4 py-2">
                        <i class="mdi mdi-stethoscope text-primary"></i>
                        <span class="fw-semibold" style="color: #0a1e3c;">Dr. Smith • Cardiology</span>
                    </span>
                </div>
                
                <div class="row g-3">
                    {{-- All Consultations - Medical Blue --}}
                    @if(Route::has('encounters.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('encounters.index') }}" class="text-decoration-none">
                            <div class="shortcut-card-doctor p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f0f9ff, #e6f3ff); border: 1px solid #b8d6ff;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-stethoscope" style="font-size: 2.5rem; color: #0066cc;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0066cc;">Consultations</h6>
                                <small class="text-secondary">All encounters</small>
                                <span class="shortcut-badge mt-2">12 today</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Patients - Ocean Teal --}}
                    @if(Route::has('patient.index'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('patient.index') }}" class="text-decoration-none">
                            <div class="shortcut-card-doctor p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #e6fffa, #ccf5f0); border: 1px solid #99e6da;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-multiple" style="font-size: 2.5rem; color: #0f766e;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #0f766e;">Patients</h6>
                                <small class="text-secondary">Directory</small>
                                <span class="shortcut-badge mt-2">156 total</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Patient Lookup - Sunset --}}
                    @if(Route::has('add-to-queue'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('add-to-queue') }}" class="text-decoration-none">
                            <div class="shortcut-card-doctor p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #fff4e6, #ffe9d4); border: 1px solid #ffccaa;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-account-arrow-right" style="font-size: 2.5rem; color: #c2410c;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #c2410c;">Patient Lookup</h6>
                                <small class="text-secondary">Quick search</small>
                                <span class="shortcut-badge mt-2">Add to queue</span>
                            </div>
                        </a>
                    </div>
                    @endif
                    
                    {{-- Lab Results - Purple --}}
                    @if(Route::has('lab.results'))
                    <div class="col-6 col-md-3">
                        <a href="{{ route('lab.results') }}" class="text-decoration-none">
                            <div class="shortcut-card-doctor p-4 rounded-4 text-center h-100" style="background: linear-gradient(145deg, #f3e8ff, #edddf9); border: 1px solid #d8b4fe;">
                                <div class="shortcut-icon-wrapper mb-3">
                                    <i class="mdi mdi-flask" style="font-size: 2.5rem; color: #6b21a8;"></i>
                                </div>
                                <h6 class="fw-bold mb-1" style="color: #6b21a8;">Lab Results</h6>
                                <small class="text-secondary">Pending: 3</small>
                                <span class="shortcut-badge urgent-badge mt-2">STAT</span>
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
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center">
                                    <i class="mdi mdi-pill text-success me-2"></i>Prescribe
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center">
                                    <i class="mdi mdi-radiobox-marked text-info me-2"></i>Imaging
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center">
                                    <i class="mdi mdi-file-document text-warning me-2"></i>Notes
                                </span>
                                <span class="badge bg-white text-dark rounded-pill px-4 py-2 d-flex align-items-center">
                                    <i class="mdi mdi-hospital-building text-secondary me-2"></i>Referrals
                                </span>
                            </div>
                            <a href="#" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                <i class="mdi mdi-cog me-1"></i>Customize
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Today's Schedule & Tasks - Premium UI Design --}}
<div class="row g-4">
    {{-- Today's Schedule - Advanced Timeline Design --}}
    <div class="col-xl-7 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 28px; background: white; overflow: hidden;">
            {{-- Schedule Header with Premium Styling --}}
            <div class="schedule-header-gradient p-4" style="background: linear-gradient(145deg, #fafcff, #f0f7fe); border-bottom: 1px solid rgba(0,114,255,0.1);">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="schedule-icon-container">
                            <i class="mdi mdi-calendar-clock" style="font-size: 2.2rem; color: #0072ff;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1" style="color: #0a1e3c; font-size: 1.5rem;">Clinical Schedule</h4>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="schedule-date-display">
                                    <i class="mdi mdi-calendar-blank me-1" style="color: #0072ff;"></i>
                                    <span id="todayDatePremium" class="fw-semibold" style="color: #1e293b;"></span>
                                </div>
                                <div class="schedule-time-display">
                                    <i class="mdi mdi-clock-outline me-1" style="color: #64748b;"></i>
                                    <span id="currentTimePremium" style="color: #475569;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <div class="schedule-stat-pill">
                            <i class="mdi mdi-check-circle text-success me-1"></i>
                            <span class="fw-bold">8</span> completed
                        </div>
                        <div class="schedule-stat-pill bg-warning bg-opacity-10">
                            <i class="mdi mdi-clock-outline text-warning me-1"></i>
                            <span class="fw-bold">4</span> remaining
                        </div>
                    </div>
                </div>
                
                {{-- Day Progress Timeline --}}
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-timeline-clock text-primary me-2"></i>
                            <span class="small fw-semibold" style="color: #475569;">Day Progress</span>
                        </div>
                        <span class="day-progress-percentage fw-bold" style="color: #0072ff;" id="dayProgressValue">64%</span>
                    </div>
                    <div class="progress" style="height: 8px; border-radius: 100px; background: #e6f0ff;">
                        <div class="progress-bar day-progress-bar" style="width: 64%; background: linear-gradient(90deg, #0072ff, #00c6ff); border-radius: 100px;" id="dayProgressFill"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small" style="color: #64748b;">
                        <span><i class="mdi mdi-weather-sunny me-1 text-warning"></i>08:00</span>
                        <span><i class="mdi mdi-weather-sunny-off me-1"></i>12:00</span>
                        <span><i class="mdi mdi-weather-partly-cloudy me-1"></i>16:00</span>
                        <span><i class="mdi mdi-weather-night me-1"></i>20:00</span>
                    </div>
                </div>
            </div>
            
            {{-- Status Legend --}}
            <div class="px-4 pt-3 pb-2 d-flex align-items-center gap-4 border-bottom bg-light bg-opacity-30">
                <div class="d-flex align-items-center gap-2">
                    <span class="legend-dot" style="background: #10b981;"></span>
                    <span class="small text-secondary">Completed</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="legend-dot" style="background: #f59e0b;"></span>
                    <span class="small text-secondary">In Progress</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="legend-dot" style="background: #ef4444;"></span>
                    <span class="small text-secondary">STAT/Emergency</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="legend-dot" style="background: #94a3b8;"></span>
                    <span class="small text-secondary">Scheduled</span>
                </div>
                <div class="ms-auto d-flex align-items-center">
                    <i class="mdi mdi-hospital-building text-primary me-1"></i>
                    <span class="small fw-semibold">Cardiology • Dr. Smith</span>
                </div>
            </div>
            
            {{-- Appointment Timeline --}}
            <div class="appointment-timeline-container" style="max-height: 480px; overflow-y: auto;">
                {{-- Morning Section --}}
                <div class="timeline-section-premium">
                    <div class="timeline-section-header-premium">
                        <div class="d-flex align-items-center gap-2">
                            <div class="section-icon morning">
                                <i class="mdi mdi-weather-sunny"></i>
                            </div>
                            <h6 class="fw-semibold mb-0">Morning Clinic</h6>
                        </div>
                        <span class="section-badge">2 appointments</span>
                    </div>
                    
                    {{-- Completed Appointment --}}
                    <div class="appointment-item-premium completed">
                        <div class="appointment-time-premium">
                            <span class="time-badge-premium completed">09:00</span>
                            <span class="time-period">AM</span>
                            <div class="time-connector"></div>
                        </div>
                        <div class="appointment-card-premium completed">
                            <div class="appointment-status-badge-premium completed">
                                <i class="mdi mdi-check-circle me-1"></i>Completed
                            </div>
                            <div class="appointment-content-premium">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="patient-avatar-premium completed">
                                        <span>JS</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0">John Smith</h6>
                                            <span class="patient-mrn-premium">MRN: 12458</span>
                                            <span class="appointment-type-badge follow-up">Follow-up</span>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-heart-pulse text-danger me-1"></i>Hypertension
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-clock-outline me-1"></i>35 min
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-doctor me-1"></i>Dr. Smith
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill view-notes-btn" disabled>
                                        <i class="mdi mdi-file-document-outline me-1"></i>Notes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- In Progress Appointment --}}
                    <div class="appointment-item-premium active">
                        <div class="appointment-time-premium">
                            <span class="time-badge-premium active">10:30</span>
                            <span class="time-period">AM</span>
                            <div class="time-connector active"></div>
                            <span class="live-badge">LIVE</span>
                        </div>
                        <div class="appointment-card-premium active">
                            <div class="appointment-status-badge-premium in-progress">
                                <i class="mdi mdi-clock-outline me-1"></i>In Progress • 15 min
                            </div>
                            <div class="appointment-content-premium">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="patient-avatar-premium active">
                                        <span>SJ</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0">Sarah Johnson</h6>
                                            <span class="patient-mrn-premium">MRN: 23567</span>
                                            <span class="appointment-type-badge new-patient">New Patient</span>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-lungs text-info me-1"></i>Respiratory
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-clock-start me-1"></i>Started 10:32
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-room me-1"></i>Room 304
                                            </span>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary rounded-pill">
                                            <i class="mdi mdi-stethoscope me-1"></i>Continue
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill">
                                            <i class="mdi mdi-chat-outline"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Afternoon Section --}}
                <div class="timeline-section-premium">
                    <div class="timeline-section-header-premium">
                        <div class="d-flex align-items-center gap-2">
                            <div class="section-icon afternoon">
                                <i class="mdi mdi-weather-partly-cloudy"></i>
                            </div>
                            <h6 class="fw-semibold mb-0">Afternoon Clinic</h6>
                        </div>
                        <span class="section-badge">2 appointments</span>
                    </div>
                    
                    {{-- STAT/Emergency Appointment --}}
                    <div class="appointment-item-premium urgent">
                        <div class="appointment-time-premium">
                            <span class="time-badge-premium urgent">11:30</span>
                            <span class="time-period">AM</span>
                            <div class="time-connector urgent"></div>
                        </div>
                        <div class="appointment-card-premium urgent">
                            <div class="appointment-status-badge-premium urgent">
                                <i class="mdi mdi-alert-circle me-1"></i>STAT • Waiting 20 min
                            </div>
                            <div class="appointment-content-premium">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="patient-avatar-premium urgent">
                                        <span>MC</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0">Michael Chen</h6>
                                            <span class="patient-mrn-premium">MRN: 34789</span>
                                            <span class="appointment-type-badge emergency">Emergency</span>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-heart text-danger me-1"></i>Chest Pain
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-clock-alert me-1"></i>Waiting 20 min
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-ambulance me-1"></i>ER Transfer
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-danger rounded-pill pulse-animation">
                                        <i class="mdi mdi-alert me-1"></i>See Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Scheduled Appointment --}}
                    <div class="appointment-item-premium">
                        <div class="appointment-time-premium">
                            <span class="time-badge-premium">14:00</span>
                            <span class="time-period">PM</span>
                            <div class="time-connector"></div>
                        </div>
                        <div class="appointment-card-premium">
                            <div class="appointment-status-badge-premium scheduled">
                                <i class="mdi mdi-calendar-check me-1"></i>Scheduled
                            </div>
                            <div class="appointment-content-premium">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="patient-avatar-premium">
                                        <span>ED</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0">Emily Davis</h6>
                                            <span class="patient-mrn-premium">MRN: 45210</span>
                                            <span class="appointment-type-badge checkup">Check-up</span>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-heart-pulse text-success me-1"></i>Annual Physical
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-clock-outline me-1"></i>30 min
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-file-document me-1"></i>Lab ready
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="mdi mdi-chart-box-outline me-1"></i>Prep
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Evening Section --}}
                <div class="timeline-section-premium">
                    <div class="timeline-section-header-premium">
                        <div class="d-flex align-items-center gap-2">
                            <div class="section-icon evening">
                                <i class="mdi mdi-weather-night"></i>
                            </div>
                            <h6 class="fw-semibold mb-0">Evening Clinic</h6>
                        </div>
                        <span class="section-badge">1 appointment</span>
                    </div>
                    
                    <div class="appointment-item-premium">
                        <div class="appointment-time-premium">
                            <span class="time-badge-premium">16:30</span>
                            <span class="time-period">PM</span>
                            <div class="time-connector"></div>
                        </div>
                        <div class="appointment-card-premium">
                            <div class="appointment-status-badge-premium scheduled">
                                <i class="mdi mdi-calendar-check me-1"></i>Scheduled
                            </div>
                            <div class="appointment-content-premium">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="patient-avatar-premium">
                                        <span>RW</span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <h6 class="fw-bold mb-0">Robert Wilson</h6>
                                            <span class="patient-mrn-premium">MRN: 56789</span>
                                            <span class="appointment-type-badge follow-up">Follow-up</span>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-blood-bag text-danger me-1"></i>Diabetes
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-clock-outline me-1"></i>45 min
                                            </span>
                                            <span class="appointment-detail">
                                                <i class="mdi mdi-needle me-1"></i>A1C due
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill">
                                        <i class="mdi mdi-file-document-outline me-1"></i>Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- View All Link with Animation --}}
            <div class="p-4 border-top text-center schedule-footer">
                <a href="{{ route('encounters.index') }}" class="btn btn-link text-decoration-none fw-semibold schedule-view-all">
                    <i class="mdi mdi-calendar-clock me-2"></i>View Full Week Schedule
                    <i class="mdi mdi-arrow-right ms-2 arrow-animation"></i>
                </a>
            </div>
        </div>
    </div>
    
    {{-- Clinical Tasks & Alerts - Premium Design --}}
    <div class="col-xl-5 col-lg-12">
        <div class="card border-0 shadow-lg h-100" style="border-radius: 28px; background: white; overflow: hidden;">
            {{-- Tasks Header --}}
            <div class="task-header-gradient p-4" style="background: linear-gradient(145deg, #fff9f5, #fff2ec); border-bottom: 1px solid rgba(220,38,38,0.1);">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="task-icon-container">
                            <i class="mdi mdi-format-list-checks" style="font-size: 2.2rem; color: #dc2626;"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1" style="color: #0a1e3c;">Clinical Tasks</h4>
                            <p class="mb-0 small text-secondary">Actions requiring your attention</p>
                        </div>
                    </div>
                    <div class="task-urgent-pill">
                        <span class="task-count-badge">7</span>
                        <span class="fw-semibold ms-1 d-none d-sm-inline">pending</span>
                    </div>
                </div>
            </div>
            
            {{-- Task Categories --}}
            <div class="p-4">
                {{-- Urgent Tasks Section --}}
                <div class="task-category-premium mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-alert-circle text-danger"></i>
                        <span class="task-category-title">Urgent (3)</span>
                    </div>
                </div>
                
                {{-- Urgent Task 1: Lab Results --}}
                <div class="task-item-premium urgent">
                    <div class="task-checkbox-premium urgent">
                        <i class="mdi mdi-alert"></i>
                    </div>
                    <div class="task-content-premium">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1">Lab Results Review</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="task-badge-premium critical">Critical</span>
                                    <span class="task-time-premium">
                                        <i class="mdi mdi-clock-alert me-1"></i>Overdue by 2h
                                    </span>
                                </div>
                                <div class="patient-avatar-group">
                                    <span class="task-patient-avatar-premium" style="background: #fee2e2; color: #dc2626;">JD</span>
                                    <span class="task-patient-avatar-premium" style="background: #fee2e2; color: #dc2626;">MS</span>
                                    <span class="task-patient-avatar-premium" style="background: #fee2e2; color: #dc2626;">LK</span>
                                    <span class="task-patient-count-premium">+2</span>
                                </div>
                                <div class="task-progress-premium mt-2">
                                    <div class="d-flex justify-content-between small">
                                        <span style="color: #64748b;">3/5 reviewed</span>
                                        <span class="fw-semibold" style="color: #dc2626;">60%</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 4px;">
                                        <div class="progress-bar bg-danger" style="width: 60%;"></div>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-danger rounded-pill">
                                Review
                            </button>
                        </div>
                    </div>
                </div>
                
                {{-- Urgent Task 2: Imaging --}}
                <div class="task-item-premium urgent">
                    <div class="task-checkbox-premium urgent">
                        <i class="mdi mdi-alert"></i>
                    </div>
                    <div class="task-content-premium">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1">Imaging Reports</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="task-badge-premium stat">STAT</span>
                                    <span class="task-time-premium">
                                        <i class="mdi mdi-radiobox-marked me-1"></i>MRI Brain
                                    </span>
                                </div>
                                <div class="patient-avatar-group">
                                    <span class="task-patient-avatar-premium" style="background: #fee2e2; color: #dc2626;">RW</span>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                        <i class="mdi mdi-clock-outline me-1"></i>Ordered: 09:30
                                    </span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-danger rounded-pill">
                                View
                            </button>
                        </div>
                    </div>
                </div>
                
                {{-- Due Today Section --}}
                <div class="task-category-premium mt-4 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="mdi mdi-clock-outline text-warning"></i>
                        <span class="task-category-title">Due Today (4)</span>
                    </div>
                </div>
                
                {{-- Prescription Approvals --}}
                <div class="task-item-premium">
                    <div class="task-checkbox-premium warning">
                        <i class="mdi mdi-clock"></i>
                    </div>
                    <div class="task-content-premium">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1">Prescription Approvals</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="task-badge-premium warning">Pending</span>
                                    <span class="task-time-premium">
                                        <i class="mdi mdi-clock-outline me-1"></i>Due 5:00 PM
                                    </span>
                                </div>
                                <span class="small text-secondary">2 prescriptions need signature</span>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                        <i class="mdi mdi-pill me-1 text-success"></i>Amoxicillin, Metformin
                                    </span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-warning rounded-pill">
                                Sign
                            </button>
                        </div>
                    </div>
                </div>
                
                {{-- Discharge Summaries --}}
                <div class="task-item-premium">
                    <div class="task-checkbox-premium warning">
                        <i class="mdi mdi-clock"></i>
                    </div>
                    <div class="task-content-premium">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1">Discharge Summaries</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="task-badge-premium warning">In Progress</span>
                                    <span class="task-time-premium">
                                        <i class="mdi mdi-clock-outline me-1"></i>4 remaining
                                    </span>
                                </div>
                                <div class="patient-avatar-group">
                                    <span class="task-patient-avatar-premium">AB</span>
                                    <span class="task-patient-avatar-premium">CD</span>
                                    <span class="task-patient-avatar-premium">EF</span>
                                    <span class="task-patient-avatar-premium">GH</span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary rounded-pill">
                                Continue
                            </button>
                        </div>
                    </div>
                </div>
                
                {{-- Collaborative Task --}}
                <div class="task-item-premium">
                    <div class="task-checkbox-premium info">
                        <i class="mdi mdi-account-group"></i>
                    </div>
                    <div class="task-content-premium">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-semibold mb-1">Multidisciplinary Rounds</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="task-badge-premium info">Team</span>
                                    <span class="task-time-premium">
                                        <i class="mdi mdi-calendar me-1"></i>Today 3:30 PM
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-semibold" style="color: #475569;">With:</span>
                                    <div class="d-flex align-items-center">
                                        <span class="collab-avatar-premium" style="background: #dbeafe; color: #1e40af;">Dr.W</span>
                                        <span class="collab-avatar-premium" style="background: #f1f5f9; color: #475569;">RN.J</span>
                                        <span class="collab-avatar-premium" style="background: #f1f5f9; color: #475569;">PT.M</span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-info rounded-pill">
                                Join
                            </button>
                        </div>
                    </div>
                </div>
                
                {{-- Quick Actions Grid --}}
                <div class="quick-actions-grid-premium mt-4">
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-plus-circle-outline"></i>
                        <span>New Note</span>
                    </button>
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-send-outline"></i>
                        <span>Referral</span>
                    </button>
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-pill"></i>
                        <span>Prescribe</span>
                    </button>
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-chat-outline"></i>
                        <span>Message</span>
                    </button>
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-file-document"></i>
                        <span>Template</span>
                    </button>
                    <button class="quick-action-btn-premium">
                        <i class="mdi mdi-calculator"></i>
                        <span>Dosing</span>
                    </button>
                </div>
                
                {{-- Complete All Button --}}
                <div class="mt-4">
                    <button class="btn btn-outline-secondary rounded-pill w-100 py-3 d-flex align-items-center justify-content-center gap-2" style="border-style: dashed; border-width: 2px; border-color: #cbd5e1;">
                        <i class="mdi mdi-check-circle-outline" style="font-size: 1.2rem;"></i>
                        <span class="fw-semibold">Mark All as Complete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== PREMIUM DOCTOR DASHBOARD STYLES ===== */

/* Welcome Card */
.welcome-card-doctor {
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.welcome-card-doctor:hover {
    transform: translateY(-3px);
    box-shadow: 0 25px 40px -12px rgba(0,114,255,0.25) !important;
}
.backdrop-blur {
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Stat Cards */
.stat-card-doctor {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}
.stat-card-doctor::before {
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
.stat-card-doctor:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 30px rgba(0,0,0,0.15) !important;
}
.stat-card-doctor:hover::before {
    transform: scale(1.5);
}
.stat-icon-wrapper {
    transition: all 0.3s ease;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-card-doctor:hover .stat-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
    background: rgba(255,255,255,0.25) !important;
}

/* Shortcut Cards */
.shortcut-card-doctor {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
    border: 1px solid transparent;
}
.shortcut-card-doctor:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.06) !important;
    border-color: rgba(0,114,255,0.3);
}
.shortcut-card-doctor:hover .shortcut-icon-wrapper i {
    transform: scale(1.15) rotate(3deg);
    color: #0066cc !important;
}
.shortcut-icon-wrapper i {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.shortcut-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(0,114,255,0.1);
    border-radius: 100px;
    font-size: 0.75rem;
    color: #0066cc;
    font-weight: 600;
}
.urgent-badge {
    background: rgba(220,38,38,0.1);
    color: #dc2626;
}

/* Schedule Section Premium */
.schedule-icon-container {
    width: 56px;
    height: 56px;
    background: rgba(0,114,255,0.12);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.schedule-date-display {
    background: white;
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.9rem;
    color: #1e293b;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    border: 1px solid #e6f0ff;
}
.schedule-time-display {
    background: #f8fafc;
    padding: 6px 16px;
    border-radius: 100px;
    font-size: 0.9rem;
    color: #475569;
}
.schedule-stat-pill {
    background: white;
    padding: 6px 18px;
    border-radius: 100px;
    font-size: 0.85rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.02);
    border: 1px solid #e2e8f0;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

/* Timeline Premium */
.timeline-section-premium {
    margin-bottom: 12px;
}
.timeline-section-header-premium {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px 8px;
}
.section-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.section-icon.morning {
    background: rgba(245,158,11,0.12);
    color: #f59e0b;
}
.section-icon.afternoon {
    background: rgba(100,116,139,0.12);
    color: #64748b;
}
.section-icon.evening {
    background: rgba(79,70,229,0.12);
    color: #4f46e5;
}
.section-badge {
    background: #f1f5f9;
    padding: 4px 14px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}

/* Appointment Items Premium */
.appointment-item-premium {
    display: flex;
    padding: 12px 24px;
    transition: all 0.2s ease;
    position: relative;
}
.appointment-item-premium:hover {
    background: rgba(0,114,255,0.02);
}
.appointment-time-premium {
    flex: 0 0 100px;
    position: relative;
    padding-right: 24px;
}
.time-badge-premium {
    display: inline-block;
    font-weight: 700;
    font-size: 1.1rem;
    color: #1e293b;
    background: white;
    padding: 4px 12px;
    border-radius: 100px;
    border: 1px solid #e2e8f0;
}
.time-badge-premium.active {
    background: #0072ff;
    color: white;
    border-color: #0072ff;
}
.time-badge-premium.urgent {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}
.time-badge-premium.completed {
    background: #10b981;
    color: white;
    border-color: #10b981;
}
.time-connector {
    position: absolute;
    right: 0;
    top: 50%;
    width: 12px;
    height: 2px;
    background: #e2e8f0;
    transform: translateY(-50%);
}
.time-connector.active {
    background: #0072ff;
}
.time-connector.urgent {
    background: #dc2626;
}
.live-badge {
    position: absolute;
    right: -30px;
    top: 50%;
    transform: translateY(-50%);
    background: #dc2626;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 100px;
    animation: pulse 1.5s infinite;
}

/* Appointment Cards Premium */
.appointment-card-premium {
    flex: 1;
    background: white;
    border-radius: 20px;
    padding: 16px;
    border: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    position: relative;
}
.appointment-card-premium.active {
    border-color: rgba(0,114,255,0.3);
    box-shadow: 0 8px 20px rgba(0,114,255,0.08);
}
.appointment-card-premium.urgent {
    border-color: rgba(220,38,38,0.3);
    box-shadow: 0 8px 20px rgba(220,38,38,0.08);
}
.appointment-card-premium.completed {
    background: #f8fafc;
}
.appointment-status-badge-premium {
    position: absolute;
    top: -10px;
    right: 20px;
    background: white;
    padding: 4px 16px;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.appointment-status-badge-premium.completed {
    background: #10b981;
    color: white;
    border-color: #10b981;
}
.appointment-status-badge-premium.in-progress {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}
.appointment-status-badge-premium.urgent {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}
.appointment-status-badge-premium.scheduled {
    background: #f1f5f9;
    color: #475569;
    border-color: #e2e8f0;
}

/* Patient Avatars Premium */
.patient-avatar-premium {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #475569;
    flex-shrink: 0;
}
.patient-avatar-premium.completed {
    background: rgba(16,185,129,0.15);
    color: #10b981;
}
.patient-avatar-premium.active {
    background: rgba(0,114,255,0.15);
    color: #0072ff;
}
.patient-avatar-premium.urgent {
    background: rgba(220,38,38,0.15);
    color: #dc2626;
}
.patient-mrn-premium {
    font-size: 0.7rem;
    color: #94a3b8;
    background: #f8fafc;
    padding: 2px 10px;
    border-radius: 100px;
}
.appointment-type-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 12px;
    border-radius: 100px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.appointment-type-badge.follow-up {
    background: rgba(0,114,255,0.1);
    color: #0072ff;
}
.appointment-type-badge.new-patient {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}
.appointment-type-badge.emergency {
    background: rgba(220,38,38,0.1);
    color: #dc2626;
}
.appointment-type-badge.checkup {
    background: rgba(245,158,11,0.1);
    color: #f59e0b;
}
.appointment-detail {
    font-size: 0.8rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
}

/* Tasks Premium */
.task-header-icon {
    width: 56px;
    height: 56px;
    background: rgba(220,38,38,0.12);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.task-urgent-pill {
    background: white;
    padding: 8px 20px;
    border-radius: 100px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    border: 1px solid #fee2e2;
}
.task-count-badge {
    background: #dc2626;
    color: white;
    padding: 4px 14px;
    border-radius: 100px;
    font-weight: 700;
    font-size: 1rem;
}
.task-category-premium {
    display: flex;
    align-items: center;
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
}
.task-category-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.task-item-premium {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px;
    border-radius: 20px;
    background: white;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    margin-bottom: 8px;
}
.task-item-premium:hover {
    background: #fafbfc;
    border-color: #e2e8f0;
}
.task-item-premium.urgent {
    background: #fff5f5;
    border-color: #fecaca;
}
.task-checkbox-premium {
    width: 32px;
    height: 32px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #475569;
    flex-shrink: 0;
}
.task-checkbox-premium.urgent {
    background: #fee2e2;
    color: #dc2626;
}
.task-checkbox-premium.warning {
    background: #fff3cd;
    color: #f59e0b;
}
.task-checkbox-premium.info {
    background: #dbeafe;
    color: #1e40af;
}
.task-badge-premium {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 12px;
    border-radius: 100px;
    text-transform: uppercase;
}
.task-badge-premium.critical {
    background: #dc2626;
    color: white;
}
.task-badge-premium.stat {
    background: #f59e0b;
    color: white;
}
.task-badge-premium.warning {
    background: #fef3c7;
    color: #b45309;
}
.task-badge-premium.info {
    background: #dbeafe;
    color: #1e40af;
}
.task-time-premium {
    font-size: 0.75rem;
    color: #64748b;
    display: inline-flex;
    align-items: center;
}

/* Patient Avatar Groups */
.patient-avatar-group {
    display: flex;
    align-items: center;
    gap: 4px;
}
.task-patient-avatar-premium {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
}
.task-patient-count-premium {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    color: #334155;
}
.collab-avatar-premium {
    width: 28px;
    height: 28px;
    border-radius: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: -8px;
    border: 2px solid white;
}
.collab-avatar-premium:first-child {
    margin-left: 0;
}

/* Quick Actions Grid Premium */
.quick-actions-grid-premium {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.quick-action-btn-premium {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 12px 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.quick-action-btn-premium:hover {
    background: white;
    border-color: #0072ff;
    color: #0072ff;
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,114,255,0.1);
}
.quick-action-btn-premium i {
    font-size: 1.3rem;
}

/* Schedule Footer */
.schedule-footer {
    background: linear-gradient(to right, #fafcff, white);
}
.schedule-view-all {
    color: #0072ff;
    font-weight: 600;
    transition: all 0.2s ease;
}
.schedule-view-all:hover {
    transform: translateX(5px);
    color: #0055cc;
}
.arrow-animation {
    transition: transform 0.2s ease;
}
.schedule-view-all:hover .arrow-animation {
    transform: translateX(5px);
}

/* Animations */
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
.pulse-animation {
    animation: pulse 1.5s infinite;
}

/* Mobile Responsiveness */
@media (max-width: 992px) {
    .quick-actions-grid-premium {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 768px) {
    .appointment-item-premium {
        flex-direction: column;
        padding: 16px;
    }
    .appointment-time-premium {
        flex: 0 0 auto;
        margin-bottom: 12px;
        padding-right: 0;
    }
    .time-connector {
        display: none;
    }
    .live-badge {
        position: static;
        display: inline-block;
        margin-left: 8px;
    }
    .quick-actions-grid-premium {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .schedule-stat-pill {
        display: none;
    }
    .task-urgent-pill {
        padding: 8px 12px;
    }
    .patient-avatar-premium {
        width: 40px;
        height: 40px;
    }
    .quick-actions-grid-premium {
        grid-template-columns: 1fr;
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
    const todayDateEl = document.getElementById('todayDatePremium');
    const currentTimeEl = document.getElementById('currentTimePremium');
    const progressValueEl = document.getElementById('dayProgressValue');
    const progressBarEl = document.getElementById('dayProgressFill');
    
    if (dateTimeEl) {
        dateTimeEl.innerHTML = now.toLocaleDateString('en-US', dateOptions) + ' • ' + 
                               now.toLocaleTimeString('en-US', timeOptions);
    }
    if (todayDateEl) {
        todayDateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
    }
    if (currentTimeEl) {
        currentTimeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
    }
    
    // Update day progress
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const dayProgress = ((hours * 60 + minutes) / (24 * 60)) * 100;
    const roundedProgress = Math.min(Math.round(dayProgress), 100);
    
    if (progressValueEl) {
        progressValueEl.textContent = roundedProgress + '%';
    }
    if (progressBarEl) {
        progressBarEl.style.width = roundedProgress + '%';
    }
}

// Initialize stat values if empty
document.addEventListener('DOMContentLoaded', function() {
    const statElements = {
        'doc-stat-consultations': '12',
        'doc-stat-ward-rounds': '18',
        'doc-stat-patients': '156',
        'doc-stat-appointments': '8'
    };

    Object.keys(statElements).forEach(id => {
        const el = document.getElementById(id);
        if (el && el.innerText === '-') {
            el.innerText = statElements[id];
        }
    });
    
    // Initial update
    updateDateTime();
    // Update every second
    setInterval(updateDateTime, 1000);
});
</script>
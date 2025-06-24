<!-- Top Statistic Cards - Modern Look with Icons -->
<div class="row">
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">New Patients</h4>
                    <h3 class="font-weight-bold" id="stat-new-patients">-</h3>
                    <p class="mb-0">Registered Today</p>
                </div>
                <i class="mdi mdi-account-plus mdi-36px opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Returning Patients</h4>
                    <h3 class="font-weight-bold" id="stat-returning-patients">-</h3>
                    <p class="mb-0">Seen Today</p>
                </div>
                <i class="mdi mdi-account-check mdi-36px opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-danger text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Admissions</h4>
                    <h3 class="font-weight-bold" id="stat-admissions">-</h3>
                    <p class="mb-0">Admitted Today</p>
                </div>
                <i class="mdi mdi-hospital mdi-36px opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-1">Bookings</h4>
                    <h3 class="font-weight-bold" id="stat-bookings">-</h3>
                    <p class="mb-0">Booked Today</p>
                </div>
                <i class="mdi mdi-calendar-check mdi-36px opacity-75"></i>
            </div>
        </div>
    </div>
</div>

<!-- Totals Section -->
<div class="row">
    @foreach ([
        ['title' => 'Total Patients', 'id' => 'stat-total-patients', 'icon' => 'mdi-account-multiple'],
        ['title' => 'Total Admissions', 'id' => 'stat-total-admissions', 'icon' => 'mdi-hospital-building'],
        ['title' => 'Total Bookings', 'id' => 'stat-total-bookings', 'icon' => 'mdi-calendar-text'],
        ['title' => 'Total Encounters', 'id' => 'stat-total-encounters', 'icon' => 'mdi-stethoscope']
    ] as $stat)
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card border shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-1">{{ $stat['title'] }}</h6>
                    <h4 class="font-weight-bold" id="{{ $stat['id'] }}">-</h4>
                </div>
                <i class="mdi {{ $stat['icon'] }} mdi-36px text-muted"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-md-6 grid-margin">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">New Patients</h5>
                <p class="text-muted">Register new patients quickly.</p>
                <a href="{{ route('patient.create') }}" class="btn btn-primary btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Add New Patient
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 grid-margin">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Returning Patients</h5>
                <p class="text-muted">Queue returning patients for today.</p>
                <a href="{{ route('add-to-queue') }}" class="btn btn-info btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Queue Patient
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 grid-margin">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Admissions</h5>
                <p class="text-muted">Manage current in-patients and beds.</p>
                <a href="{{ route('admission-requests.index') }}" class="btn btn-secondary btn-icon-text me-2">
                    <i class="mdi mdi-hospital"></i> Manage Admissions
                </a>
                <a href="{{ route('beds.index') }}" class="btn btn-secondary btn-icon-text">
                    <i class="fa fa-bed"></i> Manage Beds
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 grid-margin">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Bookings</h5>
                <p class="text-muted">Manage and monitor upcoming appointments.</p>
                <a href="#" class="btn btn-warning btn-icon-text">
                    <i class="mdi mdi-calendar-edit"></i> Manage Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Chart Filters -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex align-items-center bg-light rounded px-3 py-2 shadow-sm">
            <label class="me-2 fw-bold mb-0">Date Range:</label>
            <select class="form-select chart-date-range me-2" style="width:auto;">
                <option value="today">Today</option>
                <option value="this_month">This Month</option>
                <option value="this_quarter">This Quarter</option>
                <option value="last_six_months">Last Six Months</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom</option>
                <option value="all_time">All Time</option>
            </select>
            <input type="date" class="form-control custom-date-start me-2" style="width:auto; display:none;">
            <input type="date" class="form-control custom-date-end" style="width:auto; display:none;">
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="row">
    @foreach([
        ['title' => 'Appointments Over Time', 'id' => 'appointmentsOverTime'],
        ['title' => 'Appointments by Clinic', 'id' => 'appointmentsByClinic'],
        ['title' => 'Top Clinic Services', 'id' => 'topClinicServices'],
        ['title' => 'Queue Status Overview', 'id' => 'queueStatusChart']
    ] as $chart)
    <div class="col-md-6 grid-margin">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">{{ $chart['title'] }}</h5>
                <canvas id="{{ $chart['id'] }}"></canvas>
            </div>
        </div>
    </div>
    @endforeach
</div>

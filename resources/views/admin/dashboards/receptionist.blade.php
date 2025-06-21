<div class="row">
    <!-- Top Statistic Cards -->
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4 class="card-title">New Patients</h4>
                <h3 class="font-weight-bold">-</h3>
                <p>Registered Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4 class="card-title">Returning Patients</h4>
                <h3 class="font-weight-bold">-</h3>
                <p>Seen Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h4 class="card-title">Admissions</h4>
                <h3 class="font-weight-bold">-</h3>
                <p>Admitted Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 stretch-card grid-margin">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h4 class="card-title">Bookings</h4>
                <h3 class="font-weight-bold">-</h3>
                <p>Booked Today</p>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Section -->
<div class="row">
    <div class="col-md-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">New Patients</h5>
                <p class="text-muted">Register new patients</p>
                <a href="{{ route('patient.create') }}" class="btn btn-primary btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Add New Patient
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Returning Patients</h5>
                <p class="text-muted">Queue returning patients</p>
                <a href="{{ route('add-to-queue') }}" class="btn btn-info btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Queue Patient
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Admissions</h5>
                <p class="text-muted">Manage in-patients</p>
                <a href="{{ route('admission-requests.index') }}" class="btn btn-secondary btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Manage Admissions
                </a>
                <a href="{{ route('beds.index') }}" class="btn btn-secondary btn-icon-text">
                    <i class="fa fa-bed"></i> Manage Beds
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Bookings</h5>
                <p class="text-muted">Register and manage appointments</p>
                <a href="#" class="btn btn-warning btn-icon-text">
                    <i class="mdi mdi-plus-circle"></i> Manage Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Chart Containers -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Appointments Over Time</h5>
                <canvas id="appointmentsOverTime"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Appointments by Clinic</h5>
                <canvas id="appointmentsByClinic"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Top Clinic Services</h5>
                <canvas id="topClinicServices"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Queue Status Overview</h5>
                <canvas id="queueStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

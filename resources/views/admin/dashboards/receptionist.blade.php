<div class="row">
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> New Patients <small class="d-block text-muted">Register new patients</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0"> 2,409</span>
                </h3>
                <span class="text-muted font-13">Registered today</span>
                <a href="{{route('patient.create')}}">
                    <br><br><button type="button" class="btn btn-primary mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> Add New Patient </button>
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> Returning Patients <small class="d-block text-muted">Queue returning patients</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0">409</span>
                </h3>
                <span class="text-muted font-13">Seen today today</span>
                <a href="{{route('add-to-queue')}}">
                    <br><br><button type="button" class="btn btn-info mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> Queue Patient </button>
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> Admissions <small class="d-block text-muted">Manage in-patients</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0">409</span>
                </h3>
                <span class="text-muted font-13">Admitted today</span>
                <a href="{{route('admission-requests.index')}}">
                    <br><br><button type="button" class="btn btn-secondary mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> Manage Admissions </button>
                </a>
                <a href="{{route('beds.index')}}">
                    <button type="button" class="btn btn-secondary mt-2 mt-sm-0 btn-icon-text">
                        <i class="fa fa-bed"></i> Manage Beds </button>
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> Bookings <small class="d-block text-muted">Register and manage appointments</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0"> 409</span>
                </h3>
                <span class="text-muted font-13">Booked today</span>
                <a href="#">
                    <br><br><button type="button" class="btn btn-warning mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> Manage bookings </button>
                </a>
            </div>
        </div>
    </div>
</div>

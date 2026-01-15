@extends('doctor.layout.app')
@section('title', 'Doctor Dashboard')
@section('page_name', 'Dashboard')

@section('content')

    <div class="row">

        <div class="col-md-6 stretch-card grid-margin">
            <div class="card-modern">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="card-title"> Procedures <small class="d-block text-muted">Queue For Procedures</small>
                        </div>
                        <div class="d-flex text-muted font-20">
                            <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                        </div>
                    </div>
                    <h3 class="font-weight-bold mb-0">409</span>
                    </h3>
                    <span class="text-muted font-13">Seen today today</span>
                    <a href="#">
                        <br><br><button type="button" class="btn btn-info mt-2 mt-sm-0 btn-icon-text">
                            <i class="mdi mdi-plus-circle"></i> Queue Procedures </button>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 stretch-card grid-margin">
            <div class="card-modern">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="card-title"> Consultations <small class="d-block text-muted">Manage in-patients</small>
                        </div>
                        <div class="d-flex text-muted font-20">
                            <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                        </div>
                    </div>
                    <h3 class="font-weight-bold mb-0">409</span>
                    </h3>
                    <span class="text-muted font-13">Admitted today</span>
                    <a href="#">
                        <br><br><button type="button" class="btn btn-secondary mt-2 mt-sm-0 btn-icon-text">
                            <i class="mdi mdi-plus-circle"></i> Manage Admissions </button>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 stretch-card grid-margin">
            <div class="card-modern">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="card-title"> Ward Rounds <small class="d-block text-muted">Register and manage appointments</small>
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

@endsection

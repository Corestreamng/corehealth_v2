@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('content')
<div class="row">
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> services <small class="d-block text-muted">View all services</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0"> 2,409</span>
                </h3>
                <span class="text-muted font-13">View services</span>
                <a href="{{route('patient.create')}}">
                    <br><br><button type="button" class="btn btn-primary mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> View Services </button>
                </a>
            </div>
        </div>
    </div>
    <div class="col-sm-6 stretch-card grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div class="card-title"> <small class="d-block text-muted">view invoice</small>
                    </div>
                    <div class="d-flex text-muted font-20">
                        <i class="mdi mdi-help-circle-outline ml-2 mouse-pointer"></i>
                    </div>
                </div>
                <h3 class="font-weight-bold mb-0">409</span>
                </h3>
                <span class="text-muted font-13">Services paid for today</span>
                <a href="#">
                    <br><br><button type="button" class="btn btn-info mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i>  </button>
                </a>
            </div>
        </div>
    </div>
    {{-- <div class="col-sm-6 stretch-card grid-margin">
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
                <a href="#">
                    <br><br><button type="button" class="btn btn-secondary mt-2 mt-sm-0 btn-icon-text">
                        <i class="mdi mdi-plus-circle"></i> Manage Admissions </button>
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
    </div> --}}
</div>
@endsection

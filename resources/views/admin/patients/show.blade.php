@extends('admin.layouts.app')
@section('title', 'Patients Profile')
@section('page_name', 'Patients')
@section('subpage_name', 'Show Patient')
@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Patient Data</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-9">
                        <h1>{{ userfullname($user->id) }}</h1>
                        <h3>File No: {{ $patient->file_no }}</h3>
                        @if ($user->old_records)
                            <div class="form-group">
                                <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old Records</a>
                                <br>
                            </div>
                        @else
                            <div class="form-group">
                                <a href="#"><i class="fa fa-file"></i> No Old Records Attached</a>
                                <br>
                            </div>
                        @endif
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                            <br>
                        </div>
                    </div>
                </div>
                <br>
                <hr>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Gender: </th>
                                <td>{{ $patient->gender ?? 'N/A' }}</td>
                                <th>D.O.B:</th>
                                <td>{{ $patient->dob ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Blood Group:</th>
                                <td>{{ $patient->blood_group ?? 'N/A' }}</td>
                                <th>Genotype :</th>
                                <td>{{ $patient->genotype ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Nationality: </th>
                                <td>{{ $patient->nationality ?? 'N/A' }}</td>
                                <th>Ethnicity:</th>
                                <td>{{ $patient->ethnicity ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Address: </th>
                                <td>{{ $patient->address ?? 'N/A' }}</td>
                                <th>Other info:</th>
                                <td>{{ $patient->misc ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Insurance/HMO: </th>
                                <td>{{ $patient->hmo->name ?? 'N/A' }}</td>
                                <th>HMO No:</th>
                                <td>{{ $patient->hmo_no ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Disability status:</th>
                                <td>{{ $patient->disability == 1 ? 'Disabled' : 'None' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($user->assignRole == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Roles Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getRoleNames()))
                                @foreach ($user->getRoleNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif

                @if ($user->assignPermission == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Permission Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getPermissionNames()))
                                @foreach ($user->getPermissionNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Vitals
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#vitalsCardBody" aria-expanded="false" aria-controls="vitalsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="vitalsCardBody">
                vitals
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Accounts
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#accountsCardBody" aria-expanded="false" aria-controls="accountsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="accountsCardBody">
                accounts
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Admission History
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#addmissionsCardBody" aria-expanded="false"
                    aria-controls="addmissionsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="addmissionsCardBody">
                history
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                ward notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#wardNotesCardBody" aria-expanded="false" aria-controls="wardNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="wardNotesCardBody">
                ward notes
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                nursing notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#nurseingNotesCardBody" aria-expanded="false"
                    aria-controls="nurseingNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="nurseingNotesCardBody">
                nursing notes
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Doctor notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#doctorNotesCardBody" aria-expanded="false"
                    aria-controls="doctorNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="doctorNotesCardBody">
                Doctor notes
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Prescriptions
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#prescriptionsNotesCardBody" aria-expanded="false"
                    aria-controls="prescriptionsNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="prescriptionsNotesCardBody">
                Prescriptions

            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                investigations
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#investigationsCardBody" aria-expanded="false"
                    aria-controls="investigationsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="investigationsCardBody">
                investigations

            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <div class="col-sm-6">
                        <a href="{{ route('staff.index') }}" class="btn btn-danger"><i class="fa fa-close"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection

@section('scripts')
    <!-- jQuery -->
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
    </script>
@endsection

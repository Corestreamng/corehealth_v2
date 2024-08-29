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
                    <div class="col-md-9">
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
                    <div class="col-md-3">
                        <div class="form-group">
                            <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                            <br>
                        </div>
                    </div>
                </div>
                <br>
                <hr>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
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
                                <th>Phone: </th>
                                <td>{{ $patient->phone_no }}</td>
                            </tr>
                            <tr>
                                <th>Next Of Kin: </th>
                                <td>{{ $patient->next_of_kin_name ?? 'N/A' }}</td>
                                <th>Other next of kin info:</th>
                                <td>
                                    Phone : {{ $patient->next_of_kin_phone ?? 'N/A' }} <br>
                                    Address : {{ $patient->next_of_kin_address ?? 'N/A' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($user->assignRole == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-md-2 control-label">Roles Assigned:</label>

                        <div class="col-md-10">
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
                        <label for="inputPassword3" class="col-md-2 control-label">Permission Assigned:</label>

                        <div class="col-md-10">
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
        @can('see-vitals')
            <div class="card mt-3">
                <div class="card-header">
                    Vitals
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#vitalsCardBody" aria-expanded="false" aria-controls="vitalsCardBody"><span
                            class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'vitalsCardBody' ? 'show' : '' }}"
                    id="vitalsCardBody">
                    <div class="row">
                        <div class="col-12">
                            <form method="post" action="{{ route('vitals.store') }}">
                                @csrf
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                        <label for="bloodPressure">Blood Pressure (mmHg) <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="bloodPressure" name="bloodPressure"
                                            pattern="\d+/\d+">
                                        <small class="form-text text-muted">Enter in the format of "systolic/diastolic", e.g.,
                                            120/80.</small>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="bodyTemperature">Body Temperature (°C) <span
                                                class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="bodyTemperature" name="bodyTemperature"
                                            min="34" max="39" step="0.1" required>
                                        <small class="form-text text-muted">Min : 34, Max: 39</small>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="bodyWeight">Body Weight (Kg)
                                            <input type="number" class="form-control" id="bodyWeight" name="bodyWeight"
                                                min="1" max="300" step="0.1" required>
                                            <small class="form-text text-muted">Min : 1, Max: 300</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="respiratoryRate">Respiratory Rate (BPM)</label>
                                        <input type="number" class="form-control" id="respiratoryRate" name="respiratoryRate"
                                            min="12" max="50">
                                        <small class="form-text text-muted">Breaths per Minute. Min : 12, Max: 50</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="heartRate">Heart Rate (BPM)</label>
                                        <input type="number" class="form-control" id="heartRate" name="heartRate"
                                            min="60" max="220">
                                        <small class="form-text text-muted">Beats Per Min. Min : 60, Max: 220</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="datetimeField">Time Taken</label>
                                        <input type="datetime-local" class="form-control" id="datetimeField"
                                            name="datetimeField" value="{{ date('Y-m-d\TH:i') }}" required>
                                        <small class="form-text text-muted">The exact time the vitals were taken</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="otherNotes">Other Notes</label>
                                        <textarea name="otherNotes" id="otherNotes" class="form-control"></textarea>
                                        <small class="form-text text-muted">Any other specifics about the patient</small>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit</button>
                            </form>
                        </div>

                        <div class="col-12">
                            <hr>
                            <h4>Vital Signs Charts(up to last 30 readings)</h4>
                            <br>
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Blood Pressure Chart -->
                                    <canvas id="bloodPressureChart"></canvas>
                                </div>

                                <div class="col-md-6">
                                    <!-- Temperature Chart -->
                                    <canvas id="temperatureChart"></canvas>
                                </div>

                                <div class="col-md-6">
                                    <!-- Weight Chart -->
                                    <canvas id="weightChart"></canvas>
                                </div>

                                <div class="col-md-6">
                                    <!-- Heart Rate Chart -->
                                    <canvas id="heartRateChart"></canvas>

                                </div>

                                <div class="col-md-6">
                                    <!-- Respiratory Rate Chart -->
                                    <canvas id="respRateChart"></canvas>
                                </div>
                            </div>

                        </div>
                    </div>
                    <hr>
                    <h4>Vitals History</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%" id="vitals_history">
                            <thead>
                                <th>#</th>
                                <th>Service</th>
                                <th>Details</th>
                                {{-- <th>Entry</th> --}}
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        @endcan
        {{-- <div class="card mt-3">
            <div class="card-header">
                Billing
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#billingCardBody" aria-expanded="false" aria-controls="billingCardBody"><span class="fa fa-caret-down"></span></button>
            </div>
            <div class="collapse card-body" id="billingCardBody">
                <form action="" method="post">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="presc_history_bills">
                        <thead>
                            <th>#</th>
                            <th>Select</th>
                            <th>Product</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <div class="form-group">
                        <label for="">Total cost of selected items</label>
                        <input type="number" value="0" class="form-control" id="presc_bill_tot" name="presc_bill_tot"
                            readonly required>

                    </div>
                    <div class="form-group">
                        <label for="">Total amount to bill</label>
                        <input type="number" value="0" id="presc_billed_tot" name="presc_billed_tot"
                            class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
                    <button type="submit" value="dismiss_presc_bill" class="btn btn-danger"
                        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                        style="float: right">Dismiss</button>
                </form>
            </div>
        </div> --}}
        @can('see-accounts')
            <div class="card mt-3">
                <div class="card-header">
                    Accounts
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#accountsCardBody" aria-expanded="false" aria-controls="accountsCardBody"><span
                            class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'accountsCardBody' ? 'show' : '' }}"
                    id="accountsCardBody">
                    @if (null != $patient_acc)
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="bg-dark text-light">
                                    <th>Account Id</th>
                                    <th>Account bal</th>
                                    <th>Last Updated</th>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $patient_acc->id }}</td>
                                        <td>{{ $patient_acc->balance }}</td>
                                        <td>{{ date('h:i a D M j, Y', strtotime($patient_acc->updated_at)) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <h5>Make Deposit</h5>
                        <form action="{{ route('account-make-deposit') }}" method="post">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            <input type="hidden" name="acc_id" value="{{ $patient_acc->id }}">
                            <div class="form-group">
                                <label for="">Amount | <small>Enter negative values for debt / credit</small></label>
                                <input type="number" name="amount" id="" class="form-control"
                                    placeholder="Enter amount to deposit" required>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                onclick="return confirm('Are you sure you wish to save this deposit?')">Save</button>
                        </form>
                    @else
                        <h4>Patient Has no acc</h4>
                        <form action="{{ route('patient-account.store') }}" method="post">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                            <button type="submit" class="btn btn-primary">Create account</button>
                        </form>
                    @endif
                    <hr>
                    <h4>All services Rendered</h4>
                    <a href="{{ route('patient-services-rendered', $patient->id) }}" class="btn btn-primary">See Details</a>
                    <hr>
                    <h4>Add Misc. Bills</h4>
                    <form action="{{ route('add-misc-bill') }}" method="post">
                        @csrf
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Service desc.</th>
                                    <th>Cost (NGN)</th>
                                    <th><button type="button" class="btn btn-primary btn-sm" onclick="addMiscBillRow()"><i
                                                class="fa fa-plus"></i> Add row</button></th>
                                </tr>
                            </thead>
                            <tbody id="misc-bill-row">
                                <tr>
                                    <td>
                                        <input type="text" class="form-control" name="names[]"
                                            placeholder="Describe service rendered...">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="prices[]" min="1">
                                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                    </td>
                                    <td><button type="button" onclick="removeMiscBillRow(this)"
                                            class="btn btn-danger btn-sm"><i class="fa fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to Save these Misc. bills?')">Save</button>
                    </form>
                    <hr>
                    <h4>Requested Misc. Bill Items</h4>
                    <form action="{{ route('bill-misc-bill') }}" method="post">
                        @csrf
                        <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                        <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user_id }}">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%" id="misc_bill_bills">
                            <thead>
                                <th>#</th>
                                <th>Select</th>
                                <th>Service</th>
                                <th>Details</th>
                            </thead>
                        </table>
                        <hr>
                        <div class="form-group">
                            <label for="">Total cost of selected items</label>
                            <input type="number" value="0" class="form-control" id="misc_bill_tot"
                                name="misc_bill_tot" readonly required>

                        </div>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
                        <button type="submit" value="dismiss_misc_bill" name="dismiss_misc_bill" class="btn btn-danger"
                            onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                            style="float: right">Dismiss</button>
                    </form>
                    <hr>
                    <h4>All Previous Misc. Bill Items</h4>
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="misc_bill_bills_hist">
                        <thead>
                            <th>#</th>
                            <th>Service</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <hr>
                    <h4>Pending Paymnets</h4>
                    <div class="table-responsive">
                        <table id="pending-paymnet-list" class="table table-sm table-bordered table-striped"
                            style="width: 100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Service</th>
                                    <th>Product</th>
                                    <th>View</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <hr>
                    <h4>All Previous Transactions</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="payment_history_list">
                            <thead>
                                <th>#</th>
                                <th>Staff</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Service(s)</th>
                                <th>Date</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        @endcan
        @can('see-admissions')
            <div class="card mt-3">
                <div class="card-header">
                    Admission History
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#addmissionsCardBody" aria-expanded="false" aria-controls="addmissionsCardBody"><span
                            class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'addmissionsCardBody' ? 'show' : '' }}"
                    id="addmissionsCardBody">
                    <div class="table-responsive">
                        <table id="admission-request-list" class="table table-sm table-bordered table-striped"
                            style="width: 100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Requested By</th>
                                    <th>Bills</th>
                                    <th>Bed</th>
                                    <th>View</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        @endcan
        @can('see-procedures')
            <div class="card mt-3">
                <div class="card-header">
                    Procedure notes
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#wardNotesCardBody" aria-expanded="false" aria-controls="wardNotesCardBody"><span
                            class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'wardNotesCardBody' ? 'show' : '' }}"
                    id="wardNotesCardBody">
                    Procedure notes
                </div>
            </div>
        @endcan
        @can('see-nursing-notes')
            <div class="card mt-3">
                <div class="card-header">
                    Nursing notes
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#nurseingNotesCardBody" aria-expanded="false"
                        aria-controls="nurseingNotesCardBody"><span class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'nurseingNotesCardBody' ? 'show' : '' }}"
                    id="nurseingNotesCardBody">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="observation-tab" data-toggle="tab" href="#observation"
                                role="tab" aria-controls="observation" aria-selected="true">Observation Chart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="treatment-tab" data-toggle="tab" href="#treatment" role="tab"
                                aria-controls="treatment" aria-selected="false">Treatment Sheet</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="io-tab" data-toggle="tab" href="#io" role="tab"
                                aria-controls="io" aria-selected="false">Intake/Output Chart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="labour-tab" data-toggle="tab" href="#labour" role="tab"
                                aria-controls="labour" aria-selected="false">Labour Records</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="others-tab" data-toggle="tab" href="#others" role="tab"
                                aria-controls="others" aria-selected="false">Other Notes</a>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="observation" role="tabpanel"
                            aria-labelledby="observation-tab">
                            <form action="{{ route('nursing-note.store') }}" method="post" id="observation_form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="1">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <div class="form-group">
                                    <label for="pateintNoteReport" class="control-label">Observation Chart for
                                        {{ $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br><br>
                                    <div style="border:1px solid black;" id="the-observation-note"
                                        class='the-observation-note'>
                                        <?php echo $observation_note->note ?? $observation_note_template->template; ?>
                                    </div>
                                    <textarea style="display: none" id="observation_text" name="the_text" class="form-control observation_text">
                            </textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Are you sure you wish to save your entries?')">Save</button>
                            </form>
                            <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="1">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <button type="submit"
                                    onclick="return confirm('Are you sure you wish to save your entries and load a fresh sheet?')"
                                    class="btn btn-success" style="float: right; margin-top:-40px">Save &
                                    New</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="treatment" role="tabpanel" aria-labelledby="treatment-tab">
                            <form action="{{ route('nursing-note.store') }}" method="post" id="treatment_form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="2">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <div class="form-group">
                                    <label for="pateintNoteReport" class="control-label">Treatment sheet for
                                        {{ $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br><br>
                                    <div style="border:1px solid black;" id="the-treatment-note" class='the-treatment-note'>
                                        <?php echo $treatment_sheet->note ?? $treatment_sheet_template->template; ?>
                                    </div>
                                    <textarea style="display: none" id="treatment_text" name="the_text" class="form-control treatment_text">
                            </textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Are you sure you wish to save your entries?')">Save</button>
                            </form>
                            <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="2">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Are you sure you wish to save your entries and load a fresh sheet?')"
                                    style="float: right; margin-top:-40px">Save &
                                    New</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="io" role="tabpanel" aria-labelledby="io-tab">
                            <form action="{{ route('nursing-note.store') }}" method="post" id="io_form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="3">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <div class="form-group">
                                    <label for="pateintNoteReport" class="control-label">Intake/Output Chart
                                        {{ $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br><br>
                                    <div style="border:1px solid black;" id="the-io-note" class='the-io-note'>
                                        <?php echo $io_chart->note ?? $io_chart_template->template; ?>
                                    </div>
                                    <textarea style="display: none" id="io_text" name="the_text" class="form-control io_text">
                            </textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Are you sure you wish to save your entries?')">Save</button>
                            </form>
                            <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="3">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Are you sure you wish to save your entries and load a fresh sheet?')"
                                    style="float: right; margin-top:-40px">Save &
                                    New</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="labour" role="tabpanel" aria-labelledby="labour-tab">
                            <form action="{{ route('nursing-note.store') }}" method="post" id="labour_form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="4">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <input type="hidden" id="close_after_save" value="0">
                                <div class="form-group">
                                    <label for="pateintDiagnosisReport" class="control-label">Labour Records
                                        {{ $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br><br>
                                    <div style="border:1px solid black;" id="the-labour-note" class='the-labour-note'>
                                        <?php echo $labour_record->note ?? $labour_record_template->template; ?>
                                    </div>
                                    <textarea style="display: none" id="labour_text" name="the_text" class="form-control labour_text">
                            </textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Are you sure you wish to save your entries?')">Save</button>
                            </form>
                            <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="4">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Are you sure you wish to save your entries and load a fresh sheet?')"
                                    style="float: right; margin-top:-40px">Save &
                                    New</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="others" role="tabpanel" aria-labelledby="others-tab">
                            <form action="{{ route('nursing-note.store') }}" method="post" id="others_form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="5">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <input type="hidden" id="close_after_save" value="0">
                                <div class="form-group">
                                    <br><label for="pateintDiagnosisReport" class="control-label">Other Notes
                                        {{ $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br><br>
                                    {{-- <div style="border:1px solid black;" id="the-others-note" class='the-others-note classic-editor'>
                                    <?php //echo $others_record->note ?? $others_record_template->template;
                                    ?>
                                </div> --}}
                                    <textarea id="others_text" name="the_text" class="form-control classic-editor others_text">
                                    <?php echo $others_record->note ?? $others_record_template->template; ?>
                            </textarea>
                                </div>
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Are you sure you wish to save your entries?')">Save</button>
                            </form>
                            <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                                {{ csrf_field() }}
                                <input type="hidden" name="note_type" value="5">
                                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                <button type="submit" class="btn btn-success"
                                    onclick="return confirm('Are you sure you wish to save your entries and load a fresh sheet?')"
                                    style="float: right; margin-top:-40px">Save &
                                    New</button>
                            </form>
                        </div>
                    </div>
                    <hr>
                    All Patient Nursing Sheets
                    <hr>
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="closed-observation-tab" data-toggle="tab"
                                href="#closed-observation" role="tab" aria-controls="closed-observation"
                                aria-selected="true"> Observation Charts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-treatment-tab" data-toggle="tab" href="#closed-treatment"
                                role="tab" aria-controls="closed-treatment" aria-selected="false"> Treatment Sheets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-io-tab" data-toggle="tab" href="#closed-io" role="tab"
                                aria-controls="closed-io" aria-selected="false"> Intake/Output Charts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-labour-tab" data-toggle="tab" href="#closed-labour"
                                role="tab" aria-controls="closed-labour" aria-selected="false"> Labour Records</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-others-tab" data-toggle="tab" href="#closed-others"
                                role="tab" aria-controls="closed-others" aria-selected="false"> Other Notes</a>
                        </li>
                    </ul>
                    <div class="tab-content" id="myClosedTabContent">
                        <div class="tab-pane fade show active" id="closed-observation" role="tabpanel"
                            aria-labelledby="closed-observation-tab">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                    id="nurse_note_hist_1">
                                    <thead>
                                        <th>#</th>
                                        <th>Note type</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </thead>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="closed-treatment" role="tabpanel"
                            aria-labelledby="closed-treatment-tab">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                    id="nurse_note_hist_2">
                                    <thead>
                                        <th>#</th>
                                        <th>Note type</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </thead>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="closed-io" role="tabpanel" aria-labelledby="closed-io-tab">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                    id="nurse_note_hist_3">
                                    <thead>
                                        <th>#</th>
                                        <th>Note type</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </thead>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="closed-labour" role="tabpanel" aria-labelledby="closed-labour-tab">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                    id="nurse_note_hist_4">
                                    <thead>
                                        <th>#</th>
                                        <th>Note type</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </thead>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="closed-others" role="tabpanel" aria-labelledby="closed-others-tab">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                    id="nurse_note_hist_5">
                                    <thead>
                                        <th>#</th>
                                        <th>Note type</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
        @can('see-doctor-notes')
            <div class="card mt-3">
                <div class="card-header">
                    Doctor notes
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#doctorNotesCardBody" aria-expanded="false" aria-controls="doctorNotesCardBody"><span
                            class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'doctorNotesCardBody' ? 'show' : '' }}"
                    id="doctorNotesCardBody">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="encounter_history_list">
                            <thead>
                                <th>#</th>
                                {{-- <th>Doctor</th> --}}
                                <th>Notes</th>
                                {{-- <th>Time</th> --}}
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        @endcan
        @can('see-prescriptions')
            <div class="card mt-3">
                <div class="card-header">
                    Prescriptions
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#prescriptionsNotesCardBody" aria-expanded="false"
                        aria-controls="prescriptionsNotesCardBody"><span class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'prescriptionsNotesCardBody' ? 'show' : '' }}"
                    id="prescriptionsNotesCardBody">
                    <h4>Requested Prescription(billing)</h4>
                    <form action="{{ route('product-bill-patient') }}" method="post">
                        @csrf
                        <h6>Requested Items</h6>
                        <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                        <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="presc_history_bills">
                            <thead>
                                <th>#</th>
                                <th>Select</th>
                                <th>Product</th>
                                <th>Details</th>
                            </thead>
                        </table>
                        <hr>
                        <h6>Other Items</h6>
                        <label for="">Search products</label>
                        <input type="text" class="form-control" id="consult_presc_search"
                            onkeyup="searchProducts(this.value)" placeholder="search products..." autocomplete="off">
                        <ul class="list-group" id="consult_presc_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>*</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Dose/Freq.</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-products">

                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="form-group">
                            <label for="">Total cost of selected items</label>
                            <input type="number" value="0" class="form-control" id="presc_bill_tot"
                                name="presc_bill_tot" readonly required>

                        </div>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
                        <button type="submit" value="dismiss_presc_bill" name="dismiss_presc_bill" class="btn btn-danger"
                            onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                            style="float: right">Dismiss</button>
                    </form>
                    <hr>
                    <h4>Requested Prescription(Dispense)</h4>
                    <form action="{{ route('product-dispense-patient') }}" method="post">
                        @csrf
                        <h6>Requested Items</h6>
                        <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                        <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="presc_history_dispense">
                            <thead>
                                <th>#</th>
                                <th>Select</th>
                                <th>Product</th>
                                <th>Details</th>
                            </thead>
                        </table>
                        <hr>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to dispense the selected items')">Dispense</button>
                        <button type="submit" value="dismiss_presc_dispense" name="dismiss_presc_bill"
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                            style="float: right">Dismiss</button>
                    </form>
                    <hr>
                    <h4>Precription History</h4>
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="presc_history_list">
                        <thead>
                            <th>#</th>
                            <th>Product</th>
                            <th>Details</th>
                        </thead>
                    </table>
                </div>
            </div>
        @endcan
        @can('see-investigations')
            <div class="card mt-3">
                <div class="card-header">
                    Investigations
                    <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                        data-target="#investigationsCardBody" aria-expanded="false"
                        aria-controls="investigationsCardBody"><span class="fa fa-caret-down"></span></button>
                </div>
                <div class="collapse card-body {{ request()->get('section') && request()->get('section') == 'investigationsCardBody' ? 'show' : '' }}"
                    id="investigationsCardBody">
                    <h4>Requested Investigations(billing)</h4>
                    <form action="{{ route('service-bill-patient') }}" method="post">
                        @csrf
                        <h6>Requested Items</h6>
                        <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                        <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="invest_history_bills">
                            <thead>
                                <th>#</th>
                                <th>Select</th>
                                <th>Service</th>
                                <th>Details</th>
                            </thead>
                        </table>
                        <hr>
                        <h6>Other Items</h6>
                        <label for="consult_invest_search">Search services</label>
                        <input type="text" class="form-control" id="consult_invest_search"
                            onkeyup="searchServices(this.value)" placeholder="search services..." autocomplete="off">
                        <ul class="list-group" id="consult_invest_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>*</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Notes/Specimen</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-services">

                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="form-group">
                            <label for="">Total cost of selected items</label>
                            <input type="number" value="0" class="form-control" id="invest_bill_tot"
                                name="invest_bill_tot" readonly required>

                        </div>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
                        <button type="submit" value="dismiss_invest_bill" name="dismiss_invest_bill" class="btn btn-danger"
                            onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                            style="float: right">Dismiss</button>
                    </form>
                    <hr>
                    <h4>Requested Investigations(sample collection)</h4>
                    <form action="{{ route('service-sample-patient') }}" method="post">
                        @csrf
                        <h6>Requested Items</h6>
                        <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                        <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="invest_history_sample">
                            <thead>
                                <th>#</th>
                                <th>Select</th>
                                <th>Service</th>
                                <th>Details</th>
                            </thead>
                        </table>
                        <hr>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Are you sure you wish to mark the selected items as \'sample taken\'?')">Take
                            Sample</button>
                        <button type="submit" value="dismiss_invest_sample" name="dismiss_invest_bill"
                            class="btn btn-danger"
                            onclick="return confirm('Are you sure you wish to dissmiss the selected items, you cannot undo this!!!')"
                            style="float: right">Dismiss</button>
                    </form>
                    <hr>

                    <h4>Investigation Result Entry</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="invest_history_res">
                            <thead>
                                <th>#</th>
                                <th>Service</th>
                                <th>Details</th>
                                <th>Entry</th>
                            </thead>
                        </table>
                    </div>
                    <hr>
                    <h4>Investigation History</h4>
                    <div class="table responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="investigation_history_list">
                            <thead>
                                <th>#</th>
                                <th>Results</th>
                                <th>Details</th>
                            </thead>
                        </table>
                    </div>

                </div>
            </div>
        @endcan
        <div class="row mt-2">
            <div class="col-12">
                <div class="form-group">
                    <div class="col-md-6">
                        <a href="{{ route('patient.index') }}" class="btn btn-danger"><i class="fa fa-close"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="{{ route('service-save-result') }}" method="post" onsubmit="copyResTemplateToField()">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                                id="invest_res_service_name"></span>)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="invest_res_template" class="table-reponsive" style="border: 1px solid black;">

                        </div>
                        <input type="hidden" id="invest_res_entry_id" name="invest_res_entry_id">
                        <input type="hidden" name="invest_res_template_submited" id="invest_res_template_submited">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit"
                            onclick="return confirm('Are you sure you wish to save this result entry? It can not be edited after!')"
                            class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog"
        aria-labelledby="investResViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="investResViewModalLabel">View Result (<span
                            class="invest_res_service_name_view"></span>)</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    @php
                        $sett = appsettings();
                    @endphp
                    <div id="resultViewTable">
                        <table class="table table-bordered">
                            <tr>
                                <td style="max-width: 20%">
                                    <img
                                        src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Image"
                                        style="width: 100px" />

                                </td>
                                <td colspan="3">
                                    {{$sett->site_name}}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">{{$sett->contact_address}}</td>
                                <td>{{$sett->contact_phones}}</td>
                                <td>{{$sett->contact_emails}}</td>
                            </tr>
                            <tr>
                                <td colspan="2" id="invest_name">

                                </td>
                                <td>
                                    <span class="invest_res_service_name_view"></span>
                                </td>
                                <td>
                                    Sample Date :<span id="res_sample_date"></span>
                                    <br>
                                    Result Date: <span id="res_result_date"></span>
                                    <br>
                                    Result By: <span id="res_result_by"></span>
                                </td>
                            </tr>
                        </table>
                        <p id="invest_res">

                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    {{-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> --}}
                    <button type="submit" onclick="PrintElem('resultViewTable')" class="btn btn-primary">Print</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="nursingNoteModal" tabindex="-1" role="dialog" aria-labelledby="nursingNoteModal"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Nursing Note Result (<span
                            id="note_type_name_"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignBillModal" tabindex="-1" role="dialog" aria-labelledby="assignBillModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('assign-bill') }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Bill </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="assign_bed_req_id_" name="assign_bed_req_id">
                        <div class="form-group">
                            <label for="admit_days">No of days admitted</label>
                            <input type="text" name="days" class="form-control" id="admit_days" readonly>
                        </div>
                        <div class="form-group">
                            <h6>Bed Details</h6>
                            <p id="admit_bed_details"></p>
                            <label>Price</label>
                            <input type="text" class="form-control" id="admit_price" readonly>
                        </div>
                        <div class="form-group">
                            <label for="">Total</label>
                            <input type="text" id="admit_total" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit"
                            onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                            class="btn btn-primary">Save Bill </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignBedModal" tabindex="-1" role="dialog" aria-labelledby="assignBedModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{ route('assign-bed') }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Bed </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="assign_bed_req_id" name="assign_bed_req_id">
                        {{-- Redundent --}}
                        <input type="hidden" id="assign_bed_reassign" name="assign_bed_reassign">
                        <div class="form-group">
                            <label for="">Select Bed</label>
                            <select name="bed_id" class="form-control">
                                <option value="">--select bed--</option>
                                @foreach ($avail_beds as $bed)
                                    <option value="{{ $bed->id }}">{{ $bed->name }}[Price: NGN
                                        {{ $bed->price }}, Ward: {{ $bed->ward }}, Unit: {{ $bed->unit }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit"
                            onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                            class="btn btn-primary">Assign Bed </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- jQuery -->
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('.classic-editor'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                },
                cloudServices: {
                    // All predefined builds include the Easy Image feature.
                    // Provide correct configuration values to use it.
                    // tokenUrl: 'https://example.com/cs-token-endpoint',
                    // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
                    // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
                    // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
                }
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(err => {
                console.error(err);
            });
    </script>

    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
        $(function() {
            $('#encounter_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('EncounterHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    //{
                    //    data: "doctor_id",
                    //    name: "doctor_id"
                    //},
                    {
                        data: "notes",
                        name: "notes"
                    },
                    //{
                    //    data: "created_at",
                    //    name: "created_at"
                    //},
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#payment_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patientPaymentHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "user_id",
                        name: "user_id"
                    },
                    {
                        data: "total",
                        name: "total"
                    },
                    {
                        data: "payment_type",
                        name: "payment_type"
                    },
                    {
                        data: "product_or_service_request",
                        name: "product_or_service_request"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#investigation_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investigationHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#admission-request-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patient-admission-requests-list', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "doctor_id",
                        name: "doctor_id"
                    },
                    {
                        data: "billed_by",
                        name: "billed_by"
                    },
                    {
                        data: "bed_id",
                        name: "bed_id"
                    },
                    {
                        data: "show",
                        name: "show"
                    },
                ],
                // initComplete: function () {
                //     this.api().columns().every(function () {
                //         var column = this;
                //         var input = document.createElement("input");
                //         $(input).appendTo($(column.footer()).empty())
                //         .on('change', function () {
                //             column.search($(this).val(), false, false, true).draw();
                //         });
                //     });
                // },
                "paging": true
                // "lengthChange": false,
                // "searching": true,
                // "ordering": true,
                // "info": true,
                // "autoWidth": false
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#misc_bill_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('miscBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#misc_bill_bills_hist').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('miscBillHistList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_dispense').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescDispenseList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function add_to_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) + parseFloat(v);
            $('#presc_bill_tot').val(new_tot);
            $('#presc_billed_tot').val(new_tot);
        }

        function subtract_from_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) - parseFloat(v);
            //in order to prevent negative values
            if (new_tot > 0) {
                $('#presc_bill_tot').val(new_tot);
                $('#presc_billed_tot').val(new_tot);
            } else {
                $('#presc_bill_tot').val(0);
                $('#presc_billed_tot').val(0);
            }
        }

        function checkPrescBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_presc_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_presc_bill(row_val);
            }
        }
    </script>
    <script>
        function add_to_total_misc_bill(v) {
            let new_tot = parseFloat($('#misc_bill_tot').val()) + parseFloat(v);
            $('#misc_bill_tot').val(new_tot);
            $('#misc_billed_tot').val(new_tot);
        }

        function subtract_from_total_misc_bill(v) {
            let new_tot = parseFloat($('#misc_bill_tot').val()) - parseFloat(v);
            //in order to prevent negative values
            if (new_tot > 0) {
                $('#misc_bill_tot').val(new_tot);
                $('#misc_billed_tot').val(new_tot);
            } else {
                $('#misc_bill_tot').val(0);
                $('#misc_billed_tot').val(0);
            }
        }

        function checkMiscBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_misc_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_misc_bill(row_val);
            }
        }
    </script>
    <script>
        function removeProdRow(obj, price) {
            subtract_from_total_presc_bill(price);
            subtract_from_total_invest_bill(price);
            $(obj).closest('tr').remove();
        }

        function setSearchValProd(name, id, price) {
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price = '${price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_presc_dose[] required>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${price}')">x</button></td>
                </tr>
            `;

            $('#selected-products').append(mk);
            $('#consult_presc_res').html('');

        }

        function searchProducts(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-products') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_presc_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            // Append the new options to the list field
                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValProd('${data[i].product_name}[${data[i].product_code}](${data[i].stock.current_quantity} avail.)', '${data[i].id}', '${data[i].price.initial_sale_price}')">
                                   [${data[i].category.category_name}]<b>${data[i].product_name}[${data[i].product_code}]</b> (${data[i].stock.current_quantity} avail.) NGN ${data[i].price.initial_sale_price}</li>`;
                            $('#consult_presc_res').append(mk);
                            $('#consult_presc_res').show();
                        }
                    }
                });
            } else {
                $('#consult_presc_res').html('');
            }
        }
    </script>
    <script>
        $(function() {
            $('#invest_history_sample').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investSampleList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#invest_history_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>

    <script>
        $(function() {
            $('#invest_history_res').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investResList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function add_to_total_invest_bill(v) {
            let new_tot = parseFloat($('#invest_bill_tot').val()) + parseFloat(v);
            $('#invest_bill_tot').val(new_tot);
            $('#invest_billed_tot').val(new_tot);
        }

        function subtract_from_total_invest_bill(v) {
            let new_tot = parseFloat($('#invest_bill_tot').val()) - parseFloat(v);
            //in order to avoid having negative values
            if (new_tot > 0) {
                $('#invest_bill_tot').val(new_tot);
                $('#invest_billed_tot').val(new_tot);
            } else {
                $('#invest_bill_tot').val(0);
                $('#invest_billed_tot').val(0);
            }
        }

        function checkInvestBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_invest_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_invest_bill(row_val);
            }
        }

        function setSearchValSer(name, id, price) {
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedInvestBillRows[]' onclick='checkInvestBillRow(this)' data-price = '${price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_invest_note[]>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${price}')">x</button></td>
                </tr>
            `;

            $('#selected-services').append(mk);
            $('#consult_invest_res').html('');

        }

        function searchServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-services') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_invest_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            // Append the new options to the list field
                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValSer('${data[i].service_name}[${data[i].service_code}]', '${data[i].id}', '${data[i].price.sale_price}')">
                                   [${data[i].category.category_name}]<b>${data[i].service_name}[${data[i].service_code}]</b> NGN ${data[i].price.sale_price}</li>`;
                            $('#consult_invest_res').append(mk);
                            $('#consult_invest_res').show();
                        }
                    }
                });
            } else {
                $('#consult_invest_res').html('');
            }
        }
    </script>
    <script>
        function setResTempInModal(obj) {
            $('#invest_res_service_name').text($(obj).attr('data-service-name'));
            $('#invest_res_template').html($(obj).attr('data-template'));
            $('#invest_res_entry_id').val($(obj).attr('data-id'));
            $('#investResModal').modal('show');
        }

        function copyResTemplateToField() {
            $('#invest_res_template_submited').val($('#invest_res_template').html());
            return true;
        }
    </script>
    <script>
        function setResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));
            $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));
            $('#invest_res').html(res_obj.result);
            $('#res_sample_date').html(res_obj.sample_date);
            $('#res_result_date').html(res_obj.result_date);
            $('#res_result_by').html(res_obj.results_person.firstname +' '+ res_obj.results_person.surname);
            $('#invest_name').text(res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname +'('+ res_obj.patient.file_no+')');
            $('#investResViewModal').modal('show');
        }
    </script>
    <script>
        function PrintElem(elem) {
            var mywindow = window.open('', 'PRINT', 'height=600,width=800');

            mywindow.document.write('<html><head><title>' + document.title + '</title>');
            mywindow.document.write(
                `<link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}" />`);

            mywindow.document.write(
                `<link rel="stylesheet" href=" {{ asset('admin/assets/css/demo_1/style.css') }}" />`);
            mywindow.document.write('</head><body>');
            mywindow.document.write(document.getElementById(elem).innerHTML);
            mywindow.document.write('</body></html>');

            mywindow.document.close(); // IE >= 10

            // Wait for the window and its contents to load
            mywindow.onload = function() {
                mywindow.focus(); // Set focus for IE
                mywindow.print();
                // Optional: Uncomment the line below to close the window after printing
                // mywindow.close();
            };

            return true;
        }
    </script>
    <script>
        function setBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#assignBedModal').modal('show');
        }
    </script>

    <script>
        function newBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#assignBedModal').modal('show');
        }
    </script>
    <script>
        function setBillModal(obj) {
            $('#assign_bed_req_id_').val($(obj).attr('data-id'));
            $('#admit_days').val($(obj).attr('data-days'));
            $('#admit_price').val($(obj).attr('data-price'));
            $('#admit_bed_details').html($(obj).attr('data-bed'));
            $('#admit_total').val(parseFloat($(obj).attr('data-price')) * parseFloat($(obj).attr('data-days')));
            $('#assignBillModal').modal('show');
        }
    </script>
    @include('admin.partials.nursing-note-save-scripts')
    <script>
        $(function() {
            $('#nurse_note_hist_1').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 1]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_2').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 2]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_3').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 3]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_4').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 4]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_5').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 5]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function setNoteInModal(obj) {
            $('#note_type_name_').text($(obj).attr('data-service-name'));
            $('#nursing_note_template_').html($(obj).attr('data-template'));
            $('#nursingNoteModal').modal('show');
        }
    </script>
    <script>
        function toggle_group(class_) {
            var x = document.getElementsByClassName(class_);
            for (i = 0; i < x.length; i++) {
                if (x[i].style.display === "none") {
                    x[i].style.display = "block";
                } else {
                    x[i].style.display = "none";
                }
            }

        }
    </script>

    <script>
        function addMiscBillRow() {
            let mrkup = `
            <tr>
                <td>
                    <input type="text" class="form-control" name="names[]" placeholder="Describe service rendered...">
                </td>
                <td>
                    <input type="number" class="form-control" name="prices[]" min="1">
                </td>
                <td><button type="button" onclick="removeMiscBillRow(this)" class="btn btn-danger btn-sm"><i
                            class="fa fa-times"></i></button></td>
            </tr>
            `;

            $('#misc-bill-row').append(mrkup);
        }

        function removeMiscBillRow(obj) {
            $(obj).closest('tr').remove();
        }
    </script>

    <script>
        $(function() {
            $('#pending-paymnet-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('product-services-requesters-list', $patient->user_id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "service_id",
                        name: "service_id"
                    },
                    {
                        data: "product_id",
                        name: "product_id"
                    },
                    {
                        data: "show",
                        name: "show"
                    },
                ],
                "paging": true
            });
        });
    </script>

    @include('admin.partials.vitals-scripts')
@endsection

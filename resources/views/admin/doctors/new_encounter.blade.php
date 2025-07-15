@extends('admin.layouts.app')
@section('title', 'New Encounter')
@if (request()->get('admission_req_id') != '')
    @section('page_name', 'Ward Round')
@else
    @section('page_name', 'Consultations')
@endif
@section('subpage_name', 'New Encounter')
@section('content')
    <style>
        /* CSS to add a blue outline to all radio buttons and checkboxes */
        input[type="radio"],
        input[type="checkbox"] {
            outline: 2px solid blue !important;
            /* Blue outline with 2px thickness */
            outline-offset: 2px !important;
            margin-left: 4px;
            /* Optional: offset the outline from the border */
        }

        /* Additional styles for better visibility and interaction */
        input[type="radio"]:focus,
        input[type="checkbox"]:focus {
            outline-width: 3px !important;
            /* Thicker outline on focus for better accessibility */
        }
    </style>
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="patient_data_tab" data-bs-toggle="tab" data-bs-target="#patient_data"
                type="button" role="tab" aria-controls="patient_data" aria-selected="true">Patient Data</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="vitals_data_tab" data-bs-toggle="tab" data-bs-target="#vitals" type="button"
                role="tab" aria-controls="vitals_data" aria-selected="false">Viatals/ Allergies</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="investigation_hist_tab" data-bs-toggle="tab" data-bs-target="#investigation_hist"
                type="button" role="tab" aria-controls="investigation_hist" aria-selected="false">Investigation
                History</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="presc_hist_tab" data-bs-toggle="tab" data-bs-target="#presc_hist" type="button"
                role="tab" aria-controls="presc_hist" aria-selected="false">Drug
                History</button>
        </li <li class="nav-item" role="presentation">
        <button class="nav-link " id="encounter_hist_tab" data-bs-toggle="tab" data-bs-target="#encounter_hist"
            type="button" role="tab" aria-controls="encounter_hist" aria-selected="true">Encounter History</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="nursing_notes_tab" data-bs-toggle="tab" data-bs-target="#nursing_notes"
                type="button" role="tab" aria-controls="nursing_notes" aria-selected="false">Nursing Notes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="nurse_charts_tab" data-bs-toggle="tab" data-bs-target="#nurse_charts"
                type="button" role="tab" aria-controls="nurse_charts" aria-selected="false">New Nurse Charts</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="my_notes_tab" data-bs-toggle="tab" data-bs-target="#my_notes" type="button"
                role="tab" aria-controls="my_notes" aria-selected="false">My Diagnosis and Notes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="investigations_tab" data-bs-toggle="tab" data-bs-target="#investigations"
                type="button" role="tab" aria-controls="investigations" aria-selected="false">Investigation</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link " id="prescription_tab" data-bs-toggle="tab" data-bs-target="#prescription"
                type="button" role="tab" aria-controls="prescription" aria-selected="true">Prescription</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="admission_tab" data-bs-toggle="tab" data-bs-target="#admission" type="button"
                role="tab" aria-controls="admission" aria-selected="false">Conclusion</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="patient_data" role="tabpanel" aria-labelledby="patient_data">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <div class="row">
                        <div class="col-md-9">
                            <h1>{{ userfullname($patient->user->id) }}</h1>
                            <h3>File No: {{ $patient->file_no }}</h3>
                            @if ($patient->user->old_records)
                                <div class="form-group">
                                    <a href="{!! url('storage/image/user/old_records/' . $patient->user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old
                                        Records</a>
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
                                <img src="{!! url('storage/image/user/' . $patient->user->filename) !!}" valign="middle" width="150px" height="120px" />
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
                    @if (isset($admission_request))
                        <hr>
                        <h4>Admission Info <a href="{{ route('discharge-patient', $admission_request->id) }}"
                                class="btn btn-warning"
                                onclick="return confirm('Are you sure you wish to discharge ths patient?')">Discharge</a>
                        </h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <tr>
                                    <th>Admitted By</th>
                                    <td>{{ userfullname($admission_request->doctor_id) }}</td>
                                    <th>Admitted On </th>
                                    <td>{{ date('h:i a D M j, Y', strtotime($admission_request->created_at)) }}</td>
                                </tr>
                                <tr>
                                    <th>Bed</th>
                                    <td>{{ $admission_request->bed ? $admission_request->bed->name : 'N/A' }}</td>
                                    <th>Ward</th>
                                    <td>{{ $admission_request->bed ? $admission_request->bed->ward : 'N/A' }},
                                        <b>Unit:</b>
                                        {{ $admission_request->bed ? $admission_request->bed->unit : 'N/A' }}
                                    </td>
                                </tr>
                            </table>

                        </div>
                        <hr>
                    @endif
                    <br><button type="button" onclick="switch_tab(event,'vitals_data_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="vitals" role="tabpanel" aria-labelledby="vitals_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">

                    <div class="row">
                        <div class="col-12">
                            <form method="post" action="{{ route('vitals.store') }}">
                                @csrf
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                                        <label for="bloodPressure">Blood Pressure (mmHg) <span
                                                class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="bloodPressure"
                                            name="bloodPressure" pattern="\d+/\d+" required>
                                        <small class="form-text text-muted">Enter in the format of
                                            "systolic/diastolic", e.g.,
                                            120/80.</small>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="bodyTemperature">Body Temperature (°C) <span
                                                class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="bodyTemperature"
                                            name="bodyTemperature" min="34" max="39" step="0.1"
                                            required>
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
                                        <input type="number" class="form-control" id="respiratoryRate"
                                            name="respiratoryRate" min="12" max="30">
                                        <small class="form-text text-muted">Breaths per Minute. Min : 12, Max:
                                            30</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="heartRate">Heart Rate (BPM)</label>
                                        <input type="number" class="form-control" id="heartRate" name="heartRate"
                                            min="60" max="220">
                                        <small class="form-text text-muted">Beats Per Min. Min : 60, Max:
                                            220</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="datetimeField">Time Taken</label>
                                        <input type="datetime-local" class="form-control" id="datetimeField"
                                            name="datetimeField" value="{{ date('Y-m-d\TH:i') }}" required>
                                        <small class="form-text text-muted">The exact time the vitals were
                                            taken</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="otherNotes">Other Notes</label>
                                        <textarea name="otherNotes" id="otherNotes" class="form-control"></textarea>
                                        <small class="form-text text-muted">Any other specifics about the
                                            patient</small>
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
                        <hr>
                        <h4>Vitals History</h4>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped" style="width: 100%"
                                id="vitals_history">
                                <thead>
                                    <th>#</th>
                                    <th>Service</th>
                                    <th>Details</th>
                                    {{-- <th>Entry</th> --}}
                                </thead>
                            </table>
                        </div>
                    </div>
                    <br><button type="button" onclick="switch_tab(event,'patient_data_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'investigation_hist_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="investigation_hist" role="tabpanel" aria-labelledby="investigation_hist_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="investigation_history_list">
                        <thead>
                            <th>#</th>
                            <th>Results</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <br><button type="button" onclick="switch_tab(event,'vitals_data_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'presc_hist_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="presc_hist" role="tabpanel" aria-labelledby="presc_hist_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="presc_history_list">
                        <thead>
                            <th>#</th>
                            <th>Product</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <br><button type="button" onclick="switch_tab(event,'investigation_hist_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'encounter_hist_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade " id="encounter_hist" role="tabpanel" aria-labelledby="encounter_hist_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="encounter_history_list">
                        <thead>
                            <th>#</th>
                            {{-- <th>Doctor</th> --}}
                            <th>Notes</th>
                            {{-- <th>Time</th> --}}
                        </thead>
                    </table>
                    <br><button type="button" onclick="switch_tab(event,'investigation_hist_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="nursing_notes" role="tabpanel" aria-labelledby="nursing_notes_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    @if (isset($admission_request))
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="observation-tab" data-toggle="tab" href="#observation"
                                    role="tab" aria-controls="observation" aria-selected="true">Observation Chart</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="treatment-tab" data-toggle="tab" href="#treatment"
                                    role="tab" aria-controls="treatment" aria-selected="false">Treatment Sheet</a>
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
                                        <div style="border:1px solid black;" id="the-treatment-note"
                                            class='the-treatment-note'>
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
                                        <div style="border:1px solid black;" id="the-labour-note"
                                            class='the-labour-note'>
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
                    @endif
                    <hr>
                    All Patient Nursing Sheets
                    <hr>
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="closed-observation-tab" data-toggle="tab"
                                href="#closed-observation" role="tab" aria-controls="closed-observation"
                                aria-selected="true">Observation Charts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-treatment-tab" data-toggle="tab" href="#closed-treatment"
                                role="tab" aria-controls="closed-treatment" aria-selected="false"> Treatment
                                Sheets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-io-tab" data-toggle="tab" href="#closed-io" role="tab"
                                aria-controls="closed-io" aria-selected="false"> Intake/Output Charts</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-labour-tab" data-toggle="tab" href="#closed-labour"
                                role="tab" aria-controls="closed-labour" aria-selected="false"> Labour
                                Records</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-others-tab" data-toggle="tab" href="#closed-others"
                                role="tab" aria-controls="closed-others" aria-selected="false"> Others </a>
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
                        <div class="tab-pane fade" id="closed-labour" role="tabpanel"
                            aria-labelledby="closed-labour-tab">
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
                        <div class="tab-pane fade" id="closed-others" role="tabpanel"
                            aria-labelledby="closed-others-tab">
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
                    <br><button type="button" onclick="switch_tab(event,'encounter_hist_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'nurse_charts_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="nurse_charts" role="tabpanel" aria-labelledby="nurse_charts_tab">
            <div class="card mt-2">
                <div class="card-body">
                    <!-- Nurse Charts Content -->
                    <div class="alert alert-info">
                        <i class="mdi mdi-information-outline me-2"></i>
                        This is a read-only view of nurse medication and intake/output charts for the patient.
                    </div>

                    <!-- Date Range Filter -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i> Date Range Filter</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="chart-date-from" class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="chart-date-from">
                                </div>
                                <div class="col-md-4">
                                    <label for="chart-date-to" class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="chart-date-to">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" id="apply-date-filter" class="btn btn-primary me-2">
                                        <i class="mdi mdi-filter"></i> Apply Filter
                                    </button>
                                    <button type="button" id="reset-date-filter" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="text-muted mb-0">
                                <i class="mdi mdi-calendar me-1"></i>
                                <span id="date-range-summary">Showing data from the last 30 days</span>
                            </h5>
                        </div>
                        <div id="data-summary-stats" class="d-flex gap-3">
                            <!-- Will be populated with JavaScript -->
                        </div>
                    </div>

                    <!-- Tabs for Medication and Intake/Output Charts -->
                    <ul class="nav nav-tabs mb-3" id="nurseChartTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="medication-chart-tab" data-toggle="tab" href="#medication-chart"
                               role="tab" aria-controls="medication-chart" aria-selected="true">
                               <i class="mdi mdi-pill me-1"></i> Medication Chart
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="fluid-io-chart-tab" data-toggle="tab" href="#fluid-io-chart"
                               role="tab" aria-controls="fluid-io-chart" aria-selected="false">
                               <i class="mdi mdi-water me-1"></i> Fluid Intake/Output
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="solid-io-chart-tab" data-toggle="tab" href="#solid-io-chart"
                               role="tab" aria-controls="solid-io-chart" aria-selected="false">
                               <i class="mdi mdi-food-apple me-1"></i> Solid Intake/Output
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="nurseChartTabContent">
                        <!-- Medication Chart Tab -->
                        <div class="tab-pane fade show active" id="medication-chart" role="tabpanel" aria-labelledby="medication-chart-tab">
                            <div class="medication-chart-container">
                                <div id="medication-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading medication chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fluid I/O Chart Tab -->
                        <div class="tab-pane fade" id="fluid-io-chart" role="tabpanel" aria-labelledby="fluid-io-chart-tab">
                            <div class="fluid-io-chart-container">
                                <div id="fluid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading fluid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Solid I/O Chart Tab -->
                        <div class="tab-pane fade" id="solid-io-chart" role="tabpanel" aria-labelledby="solid-io-chart-tab">
                            <div class="solid-io-chart-container">
                                <div id="solid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading solid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" onclick="switch_tab(event,'nursing_notes_tab')" class="btn btn-secondary me-2">
                            <i class="mdi mdi-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" onclick="switch_tab(event,'my_notes_tab')" class="btn btn-primary">
                            Next <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <form action="{{ route('encounters.store') }}" method="post">
            @csrf
            <div class="tab-pane fade" id="my_notes" role="tabpanel" aria-labelledby="my_notes_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">

                        <input type="hidden" value="{{ $req_entry->service_id ?? 'ward_round' }}"
                            name="req_entry_service_id" required>
                        <input type="hidden" value="{{ $req_entry->id ?? 'ward_round' }}" name="req_entry_id">
                        <input type="hidden" value="{{ request()->get('patient_id') }}" name="patient_id"
                            id="encounter_patient_id__">
                        <input type="hidden" value="{{ request()->get('queue_id') ?? 'ward_round' }}" name="queue_id">
                        <input type="hidden" id="encounter_id__" name="encounter_id" value="{{ $encounter->id }}"
                            required>
                        @if (request()->get('admission_req_id') != '')
                            <input type="hidden" value="{{ request()->get('admission_req_id') }}" name="queue_id">
                        @endif
                        <div class="form-group">
                            <div class="container">
                                <div class="accordion" id="accordionForProfile">
                                    <div class="accordion-item">
                                        <h4 class="accordion-header" id="flush-headingOne">
                                            <span class="collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#flush-collapseOne" aria-expanded="false"
                                                aria-controls="flush-collapseOne">
                                                <span class="fa fa-eye"></span>
                                                See Patient Profiles</span>
                                            <span class="fa fa-caret-down"></span>
                                        </h4>
                                        <div id="flush-collapseOne" class="accordion-collapse collapse"
                                            aria-labelledby="flush-headingOne" data-bs-parent="#accordionForProfile">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-between">
                                                    <h5>Forms/Profiles</h5>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                                        data-bs-target="#profileModal"> <span class="fa fa-plus"></span>
                                                        Fill New patient Profile
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table" id="profile_forms_table" style="width: 100%">
                                                        <thead>
                                                            <th>#</th>
                                                            <th>Form Data</th>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- {!!generateForm($formdata)!!} --}}

                            </div>
                        </div>
                        <hr>
                        @if (env('REQUIRE_DIAGNOSIS'))
                            <div class="form-group">

                                <label for="reasons_for_encounter">Select ICPC -2 Reason(s) for Encounter/
                                    Diagnosis(required)
                                    <span class="text-danger">*</span></label>
                                <select name="reasons_for_encounter[]" id="reasons_for_encounter" class="text-lg"
                                    multiple="multiple" required style="width: 100%; display:block;">
                                    @foreach ($reasons_for_encounter_cat_list as $reason_cat)
                                        <optgroup label="{{ $reason_cat->category }}">
                                            @foreach ($reasons_for_encounter_sub_cat_list as $reason_sub_cat)
                                                @if ($reason_sub_cat->category == $reason_cat->category)
                                                    <option disabled style="font-weight: bold;">
                                                        {{ $reason_sub_cat->sub_category }}</option>
                                                    @foreach ($reasons_for_encounter_list as $reason_item)
                                                        @if ($reason_item->category == $reason_cat->category && $reason_item->sub_category == $reason_sub_cat->sub_category)
                                                            <option
                                                                value="{{ $reason_item->code }}-{{ $reason_item->name }}">
                                                                &emsp;{{ $reason_item->code }} {{ $reason_item->name }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="reasons_for_encounter_comment_1">Select Diagnosis Comment
                                            1(required)</label>
                                        <select class="form-control" name="reasons_for_encounter_comment_1"
                                            id="reasons_for_encounter_comment_1" required>
                                            <option value="NA">Not Applicable</option>
                                            <option value="QUERY">Query</option>
                                            <option value="DIFFRENTIAL">Diffrential</option>
                                            <option value="CONFIRMED">Confirmed</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="reasons_for_encounter_comment_2"> Select Diagnosis Comment
                                            2(required)</label>
                                        <select class="form-control" name="reasons_for_encounter_comment_2"
                                            id="reasons_for_encounter_comment_2" required>
                                            <option value="NA">Not Applicable</option>
                                            <option value="ACUTE">Acute</option>
                                            <option value="CHRONIC">Chronic</option>
                                            <option value="RECURRENT">Recurrent</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <hr>
                        @endif
                        <div>
                            <i class="fa fa-save"></i><span id="autosave_status_text"> Auto Save Enabled...</span>
                            <textarea name="doctor_diagnosis" id="doctor_diagnosis_text" class="form-control classic-editor2">{{ $encounter->notes }}</textarea>
                        </div>

                        <br><button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
                            class="btn btn-secondary mr-2">
                            Prev
                        </button>
                        <button type="button" onclick="switch_tab(event,'investigations_tab')"
                            class="btn btn-primary mr-2">Next</button>
                        <a href="{{ route('encounters.index') }}"
                            onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                            class="btn btn-light">Exit</a>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="investigations" role="tabpanel" aria-labelledby="investigations_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">
                        <label for="consult_invest_search">Search services</label>
                        <input type="text" class="form-control" id="consult_invest_search"
                            onkeyup="searchServices(this.value)" placeholder="search services..." autocomplete="off">
                        <ul class="list-group" id="consult_invest_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Notes/specimen(optional)</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-services">

                                </tbody>
                            </table>
                        </div>
                        <br><button type="button" onclick="switch_tab(event,'my_notes_tab')"
                            class="btn btn-secondary mr-2">
                            Prev
                        </button>
                        <button type="button" onclick="switch_tab(event,'prescription_tab')"
                            class="btn btn-primary mr-2">Next</button>
                        <a href="{{ route('encounters.index') }}"
                            onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                            class="btn btn-light">Exit</a>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="prescription" role="tabpanel" aria-labelledby="prescription_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">
                        <label for="">Search products</label>
                        <input type="text" class="form-control" id="consult_presc_search"
                            onkeyup="searchProducts(this.value)" placeholder="search products..." autocomplete="off">
                        <ul class="list-group" id="consult_presc_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Dose/Freq.(required)</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-products">

                                </tbody>
                            </table>
                        </div>
                        <br><button type="button" onclick="switch_tab(event,'investigations_tab')"
                            class="btn btn-secondary mr-2">
                            Prev
                        </button>
                        <button type="button" onclick="switch_tab(event,'admission_tab')"
                            class="btn btn-primary mr-2">Next</button>
                        <a href="{{ route('encounters.index') }}"
                            onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                            class="btn btn-light">Exit</a>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="admission" role="tabpanel" aria-labelledby="admission_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">
                        <label for="end_consultation">End Consultatation Cycle Now ? </label>
                        <input type="checkbox" name="end_consultation" id="end_consultation" value="1">
                        <hr>
                        @if (isset($admission_request) || $admission_exists_ == 1)
                            <h4>Currently Admitted</h4>
                        @else
                            <label for="consult_admit">Admit Patient ? </label>
                            <input type="checkbox" name="consult_admit" id="consult_admit" value="1">
                            <hr>
                        @endif
                        <p>
                            <i>
                                Before saving, please ensure that all your entries are correct and as intended. if the save
                                button does not work/respond,
                                you most likely forgot to type any notes in the notes tab or have blank dosage fields in the
                                prescription tab.
                            </i>
                        </p>
                        <br><button type="button" onclick="switch_tab(event,'prescription_tab')"
                            class="btn btn-secondary mr-2">
                            Prev
                        </button>
                        <button type="submit" class="btn btn-primary mr-2" onclick="return confirm('Are you sure?')">
                            Save </button>
                        <a href="{{ route('encounters.index') }}"
                            onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                            class="btn btn-light">Exit</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!--Profile / Form  Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Fill Profile / Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-10">
                                    <label for="form_type">Form Type</label>
                                    <select id="form_type" class="form-control">
                                        <option value="">--select form--</option>
                                        <option value="test">Test</option>
                                        <option value="anc">ANC</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button onclick="getForm()" class="btn btn-primary mt-4">Get Form</button>
                                </div>
                            </div>
                            <hr>
                            <form action="{{ route('patient-form.store') }}" method="post" id="patient_form_form">


                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
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
                        <span aria-hidden="true">&times;</span>
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

@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>

    <script>
        $(function() {
            $("#reasons_for_encounter").select2();
        });
    </script>
    <script>
        function getForm() {
            let form_ = $('#patient_form_form');
            let form_id = $('#form_type').val();
            let encounter_id = $('#encounter_id__').val();
            let patient_id = $('#encounter_patient_id__').val();
            if (form_id != '') {
                getformreq = $.ajax({
                    type: 'GET',
                    url: "{{ route('patient-form.create') }}",
                    data: {
                        form_id: form_id,
                    },
                    success: function(data) {
                        console.log(data);
                        form_.html(data.formdata);
                        mar = `
                            <input type="hidden" name="form_id" value="${data.form_id}">
                            <input type="hidden" name="patient_id" value="${patient_id}">
                            <input type="hidden" name="encounter_id" value="${encounter_id}">
                            <button type="submit" class="btn btn-primary">Save</button>
                        `;
                        form_.append(mar);
                        form_.append(`{{ csrf_field() }}`);
                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        form_.html('Sorry, Failed to obtain form, please try again later');
                    }
                });
            }
        }
    </script>
    <script>
        // ClassicEditor
        //     .create(document.querySelector('.classic-editor'), {
        //         toolbar: {
        //             items: [
        //                 'undo', 'redo',
        //                 '|', 'heading',
        //                 '|', 'bold', 'italic',
        //                 '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
        //                 '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
        //             ]
        //         },
        //         cloudServices: {
        //             // All predefined builds include the Easy Image feature.
        //             // Provide correct configuration values to use it.
        //             // tokenUrl: 'https://example.com/cs-token-endpoint',
        //             // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
        //             // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
        //             // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
        //         }
        //     })
        //     .then(editor => {
        //         window.editor = editor;
        //     })
        //     .catch(err => {
        //         console.error(err);
        //     });

        ClassicEditor
            .create(document.querySelector('.classic-editor2'), {
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
        function autosavenotes() {
            console.log('...');
            let notes = $('.ck-editor__editable:last').text();
            let encounter_id = $('#encounter_id__').val();
            let autosavesatustext = $('#autosave_status_text');
            if (notes != '') {
                notes = $('.ck-editor__editable:last').html();
                autosavesatustext.text('Autosaving...');
                autosavereq = $.ajax({
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('auto-save-encounter-note') }}",
                    data: {
                        patient_id: "{{ request()->get('patient_id') }}",
                        notes: notes,
                        encounter_id: encounter_id
                    },
                    success: function(data) {
                        console.log(data);
                        autosavesatustext.text('Autosaved')

                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        autosavesatustext.text('Autosave failed')
                    }
                });
            }
        }

        setInterval(autosavenotes, 10000);
    </script>
    <script>
        $(function() {
            $('#profile_forms_table').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 5,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patient-form-list', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "form_data",
                        name: "form_data"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
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
                    "url": "{{ route('EncounterHistoryList', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    // {
                    //     data: "doctor_id",
                    //     name: "doctor_id"
                    // },
                    {
                        data: "notes",
                        name: "notes"
                    },
                    // {
                    //     data: "created_at",
                    //     name: "created_at"
                    // },
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
                    "url": "{{ url('investigationHistoryList', request()->get('patient_id')) }}",
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
                    "url": "{{ url('prescHistoryList', request()->get('patient_id')) }}",
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
            $('#scheduled_consult_list').DataTable({
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
                    "url": "{{ url('patientsList') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "fullname",
                        name: "fullname"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo_id",
                        name: "hmo_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function removeProdRow(obj) {
            $(obj).closest('tr').remove();
        }

        function setSearchValProd(name, id, price) {
            var mk = `
                <tr>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_presc_dose[] required>
                        <input type = 'hidden' name=consult_presc_id[] value='${id}'>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this)">x</button></td>
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
        function switch_tab(e, id_of_next_tab) {
            e.preventDefault();
            $('#' + id_of_next_tab).click();
        }

        function toggleAdmitNote(obj) {
            var opt = $(obj).val();
            console.log(opt);
            if (opt == '0') {
                $('#admit-note-div').html('');
            } else {
                var mk = `
                    <textarea name="admit_note" id="admit_note" class="form-control" placeholder="Enter brief note"></textarea>
                `;
                $('#admit-note-div').append(mk);
            }
        }


        function setSearchValSer(name, id, price) {
            var mk = `
                <tr>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_invest_note[]>
                        <input type = 'hidden' name=consult_invest_id[] value='${id}'>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this)">x</button></td>
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
    @include('admin.partials.vitals-scripts')
    @include('admin.partials.nursing-note-save-scripts')

    <!-- Nurse Charts Scripts -->
    <script>
        // Wait for document to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize nurse chart tabs - using bootstrap 5 syntax
            const nurseChartTabTriggers = document.querySelectorAll('#nurseChartTabs a[data-toggle="tab"]');
            nurseChartTabTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('href');
                    const tab = new bootstrap.Tab(trigger);
                    tab.show();
                });
            });

            // Initialize date filter with default dates (last 30 days)
            initDateFilter();

            // Add event listener for apply filter button
            document.getElementById('apply-date-filter').addEventListener('click', function() {
                loadNurseCharts();
            });

            // Add event listener for reset filter button
            document.getElementById('reset-date-filter').addEventListener('click', function() {
                initDateFilter();
                loadNurseCharts();
            });

            // Add event listener for nurse chart tab activation
            $('#nurse_charts_tab').on('shown.bs.tab', function(e) {
                loadNurseCharts();
            });

            // Helper function to extract medication name from various possible data structures
            function extractMedicationName(obj, prescriptions) {
                if (!obj) return 'N/A';

                // Try different possible paths to find the medication name
                if (obj.medication_name) return obj.medication_name;
                if (obj.product_name) return obj.product_name;
                if (obj.name) return obj.name;

                if (obj.product) {
                    if (typeof obj.product === 'object') {
                        return obj.product.product_name || obj.product.name || 'N/A';
                    }
                    return obj.product;
                }

                if (obj.prescription && obj.prescription.product) {
                    const product = obj.prescription.product;
                    return product.product_name || product.name || 'N/A';
                }

                // For administration records with a product_or_service_request_id
                // Look up the corresponding prescription
                if (obj.product_or_service_request_id && prescriptions && prescriptions.length > 0) {
                    const prescription = prescriptions.find(p => p.id === obj.product_or_service_request_id);
                    if (prescription) {
                        // Recursively call the function to extract the name from the prescription
                        // Passing null as second parameter to avoid infinite recursion
                        return extractMedicationName(prescription, null);
                    }
                }

                // Extract from dosage if it contains medication name
                if (obj.dose && typeof obj.dose === 'string' && obj.dose.includes(':')) {
                    const parts = obj.dose.split(':');
                    if (parts.length > 0) return parts[0].trim();
                }

                return 'N/A';
            }

            // Date utility functions
            function getDefaultStartDate() {
                const date = new Date();
                date.setDate(date.getDate() - 30); // Default to last 30 days
                return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            }

            function getDefaultEndDate() {
                return new Date().toISOString().split('T')[0]; // Today
            }

            function formatDateForApi(dateString) {
                if (!dateString) return '';
                return dateString; // Already in YYYY-MM-DD format for API
            }

            // Initialize date filter with defaults
            function initDateFilter() {
                document.getElementById('chart-date-from').value = getDefaultStartDate();
                document.getElementById('chart-date-to').value = getDefaultEndDate();
            }

            // Format a date for display
            function formatDateForDisplay(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            // Update the date range summary display
            function updateDateRangeSummary(startDate, endDate) {
                const startFormatted = formatDateForDisplay(startDate);
                const endFormatted = formatDateForDisplay(endDate);
                const summaryElement = document.getElementById('date-range-summary');

                if (startDate && endDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} to ${endFormatted}`;
                } else if (startDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} onwards`;
                } else if (endDate) {
                    summaryElement.textContent = `Showing data up to ${endFormatted}`;
                } else {
                    summaryElement.textContent = `Showing all data`;
                }
            }

            // Load nurse charts data
            function loadNurseCharts() {
                const patientId = {{ $patient->id }};

                // Initialize date filter if needed
                if (!document.getElementById('chart-date-from').value) {
                    initDateFilter();
                }

                // Get current filter values
                const startDate = document.getElementById('chart-date-from').value;
                const endDate = document.getElementById('chart-date-to').value;

                // Update the date range summary
                updateDateRangeSummary(startDate, endDate);

                // Load medication chart with date filter
                loadMedicationChart(patientId, startDate, endDate);

                // Load intake/output charts with date filter
                loadIntakeOutputCharts(patientId, startDate, endDate);
            }

            // Function to load medication chart
            function loadMedicationChart(patientId, startDate, endDate) {
                // Show loading indicator
                const container = document.getElementById('medication-chart-content');
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medication data...</p>
                    </div>
                `;

                // Build URL with query parameters
                const url = new URL(`{{ url('/') }}/patients/${patientId}/nurse-chart/medication`);
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        // Log data structure for debugging
                        console.log('Medication Chart Data:', data);
                        const container = document.getElementById('medication-chart-content');

                        // Update summary stats
                        const prescriptionCount = data.prescriptions ? data.prescriptions.length : 0;
                        const administrationCount = data.administrations ? data.administrations.length : 0;

                        document.getElementById('data-summary-stats').innerHTML = `
                            <span class="badge bg-primary rounded-pill fs-6">
                                <i class="mdi mdi-pill me-1"></i> ${prescriptionCount} medications
                            </span>
                            <span class="badge bg-info rounded-pill fs-6">
                                <i class="mdi mdi-history me-1"></i> ${administrationCount} administrations
                            </span>
                        `;

                        // Create medication list view (read-only)
                        let html = '<div class="card shadow-sm">';

                        // Show prescription count in the header
                        // Using the same prescriptionCount from above to avoid duplicate declaration
                        html += `<div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="mdi mdi-pill me-1"></i> Current Medications</h6>
                                <small class="text-muted">${prescriptionCount} medications found</small>
                            </div>
                        </div>`;
                        html += '<div class="card-body p-0"><div class="table-responsive">';
                        html += '<table class="table table-sm table-hover mb-0">';
                        html += '<thead class="table-light"><tr>';
                        html += '<th>Medication</th>';
                        html += '<th>Dose</th>';
                        html += '<th>Route</th>';
                        html += '<th>Frequency</th>';
                        html += '<th>Status</th>';
                        html += '</tr></thead><tbody>';

                        if (data.prescriptions && data.prescriptions.length > 0) {
                            data.prescriptions.forEach(prescription => {
                                // Use our helper function to get the medication name
                                const medicationName = extractMedicationName(prescription, null);

                                // Get schedule info
                                const schedules = prescription.schedules || [];
                                const isDiscontinued = schedules.some(s => s.discontinued_at);

                                // Get dosage from various possible properties
                                let dosage = 'N/A';
                                if (prescription.dosage) {
                                    dosage = prescription.dosage;
                                } else if (prescription.dose) {
                                    // If dose contains both med name and dosage (format: "Med Name: Dosage")
                                    if (typeof prescription.dose === 'string' && prescription.dose.includes(':')) {
                                        const parts = prescription.dose.split(':');
                                        if (parts.length > 1) dosage = parts[1].trim();
                                        else dosage = prescription.dose;
                                    } else {
                                        dosage = prescription.dose;
                                    }
                                }

                                html += '<tr>';
                                html += `<td>${medicationName}</td>`;
                                html += `<td>${dosage}</td>`;
                                html += `<td>${prescription.route || 'Oral'}</td>`;
                                html += `<td>${prescription.frequency || prescription.timing || 'N/A'}</td>`;
                                if (isDiscontinued) {
                                    html += '<td><span class="badge bg-danger">Discontinued</span></td>';
                                } else {
                                    html += '<td><span class="badge bg-success">Active</span></td>';
                                }
                                html += '</tr>';
                            });
                        } else {
                            html += '<tr><td colspan="5" class="text-center">No medication records found</td></tr>';
                        }

                        html += '</tbody></table></div></div></div>';

                        // Add medication history section
                        html += '<div class="card shadow-sm mt-3">';

                        // Show date range and record count in the header
                        // Using the same administrationCount from above
                        html += `<div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="mdi mdi-history me-1"></i> Recent Administrations</h6>
                                <small class="text-muted">${administrationCount} records found</small>
                            </div>
                        </div>`;
                        html += '<div class="card-body p-0"><div class="table-responsive">';
                        html += '<table class="table table-sm table-hover mb-0">';
                        html += '<thead class="table-light"><tr>';
                        html += '<th>Time</th>';
                        html += '<th>Medication</th>';
                        html += '<th>Dose</th>';
                        html += '<th>Route</th>';
                        html += '<th>Given By</th>';
                        html += '<th>Status</th>';
                        html += '</tr></thead><tbody>';

                        if (data.administrations && data.administrations.length > 0) {
                            // Sort administrations by time, newest first
                            const administrations = [...data.administrations].sort((a, b) => {
                                return new Date(b.administered_at) - new Date(a.administered_at);
                            });

                            // Log first administration entry for structure inspection
                            if (administrations.length > 0) {
                                console.log('Sample Administration Entry:', administrations[0]);
                            }

                            // Get prescriptions array for mapping
                            const prescriptions = data.prescriptions || [];

                            // Log the prescriptions array for debugging
                            console.log(`Found ${prescriptions.length} prescriptions for mapping administration records`);

                            administrations.slice(0, 10).forEach(admin => {
                                // Log the mapping attempt
                                if (admin.product_or_service_request_id) {
                                    const matchedPrescription = prescriptions.find(p => p.id === admin.product_or_service_request_id);
                                    console.log(`Mapping administration ID ${admin.id || 'unknown'} to prescription ID ${admin.product_or_service_request_id}:`,
                                        matchedPrescription ? 'Found match' : 'No match found');
                                }

                                // Extract medication name using our helper function, passing prescriptions for mapping
                                const medication = extractMedicationName(admin, prescriptions);

                                // Format date for display
                                const time = new Date(admin.administered_at);
                                const timeStr = time.toLocaleString();

                                // Get dosage from any available property
                                const dosage = admin.dosage || admin.dose || 'N/A';

                                html += '<tr>';
                                html += `<td>${timeStr}</td>`;
                                html += `<td>${medication}</td>`;
                                html += `<td>${dosage}</td>`;
                                html += `<td>${admin.route || 'Oral'}</td>`;
                                html += `<td>${admin.administered_by_name || 'Unknown'}</td>`;

                                if (admin.deleted_at) {
                                    html += '<td><span class="badge bg-danger">Deleted</span></td>';
                                } else if (admin.edited_at) {
                                    html += '<td><span class="badge bg-warning">Edited</span></td>';
                                } else {
                                    html += '<td><span class="badge bg-success">Given</span></td>';
                                }

                                html += '</tr>';
                            });
                        } else {
                            html += '<tr><td colspan="6" class="text-center">No administration records found</td></tr>';
                        }

                        html += '</tbody></table></div></div></div>';

                        container.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Error loading medication chart:', error);
                        document.getElementById('medication-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load medication chart data. Please try again later.</div>';
                    });
            }

            // Function to load intake/output charts
            function loadIntakeOutputCharts(patientId, startDate, endDate) {
                // Show loading indicators
                const fluidContainer = document.getElementById('fluid-io-chart-content');
                const solidContainer = document.getElementById('solid-io-chart-content');

                const loadingHtml = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading data...</p>
                    </div>
                `;

                fluidContainer.innerHTML = loadingHtml;
                solidContainer.innerHTML = loadingHtml;

                // Build URL with query parameters
                const url = new URL(`{{ url('/') }}/patients/${patientId}/nurse-chart/intake-output`);
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        // Process fluid periods
                        renderIntakeOutputChart(data.fluidPeriods, 'fluid-io-chart-content', 'Fluid');

                        // Process solid periods
                        renderIntakeOutputChart(data.solidPeriods, 'solid-io-chart-content', 'Solid');

                        // Count total I/O records
                        let totalRecords = 0;
                        let totalPeriods = 0;

                        if (data.fluidPeriods) {
                            totalPeriods += data.fluidPeriods.length;
                            data.fluidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        if (data.solidPeriods) {
                            totalPeriods += data.solidPeriods.length;
                            data.solidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        // Add to existing summary stats
                        const statsContainer = document.getElementById('data-summary-stats');
                        statsContainer.innerHTML += `
                            <span class="badge bg-warning rounded-pill fs-6">
                                <i class="mdi mdi-water me-1"></i> ${totalRecords} I/O records
                            </span>
                        `;
                    })
                    .catch(error => {
                        console.error('Error loading intake/output charts:', error);
                        document.getElementById('fluid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load fluid intake/output data. Please try again later.</div>';
                        document.getElementById('solid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load solid intake/output data. Please try again later.</div>';
                    });
            }

            // Helper function to render intake/output chart
            function renderIntakeOutputChart(periods, containerId, type) {
                const container = document.getElementById(containerId);

                // Sort periods by started_at, newest first
                const sortedPeriods = [...periods].sort((a, b) => {
                    return new Date(b.started_at) - new Date(a.started_at);
                });

                if (sortedPeriods.length === 0) {
                    container.innerHTML = `<div class="alert alert-info">No ${type.toLowerCase()} intake/output records found</div>`;
                    return;
                }

                let html = '';

                // Add legend
                html += `<div class="card mb-2">
                    <div class="card-body p-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <small class="text-muted me-2">Legend:</small>
                            <span class="badge bg-primary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-down-bold me-1"></i> Intake
                            </span>
                            <span class="badge bg-warning rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-up-bold me-1"></i> Output
                            </span>
                            <span class="badge bg-success rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-start me-1"></i> Active
                            </span>
                            <span class="badge bg-secondary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-end me-1"></i> Ended
                            </span>
                        </div>
                    </div>
                </div>`;

                // Process each period
                sortedPeriods.forEach((period, index) => {
                    const isActive = !period.ended_at;
                    const startTime = new Date(period.started_at).toLocaleString();
                    const endTime = period.ended_at ? new Date(period.ended_at).toLocaleString() : 'Ongoing';

                    // Calculate totals
                    let intakeTotal = 0;
                    let outputTotal = 0;

                    // Sort records by created_at, newest first
                    const sortedRecords = [...period.records].sort((a, b) => {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    sortedRecords.forEach(record => {
                        if (record.type === 'intake') {
                            intakeTotal += parseFloat(record.amount);
                        } else {
                            outputTotal += parseFloat(record.amount);
                        }
                    });

                    const balance = intakeTotal - outputTotal;
                    const balanceClass = balance > 0 ? 'text-success' : (balance < 0 ? 'text-danger' : 'text-muted');

                    // Period card
                    html += `<div class="card shadow-sm mb-3 period-card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    Period ${isActive ?
                                        '<span class="badge bg-success ms-1">Active</span>' :
                                        '<span class="badge bg-secondary ms-1">Ended</span>'
                                    }
                                </h6>
                                <small class="text-muted">Started: ${startTime}</small>
                                ${period.ended_at ? `<br><small class="text-muted">Ended: ${endTime}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">Nurse: ${period.nurse_name}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                    if (sortedRecords.length > 0) {
                        sortedRecords.forEach(record => {
                            const recordTime = new Date(record.created_at).toLocaleString();
                            const recordType = record.type === 'intake' ? 'Intake' : 'Output';
                            const typeClass = record.type === 'intake' ? 'bg-primary' : 'bg-warning';
                            const typeIcon = record.type === 'intake' ? 'mdi-arrow-down-bold' : 'mdi-arrow-up-bold';

                            html += `<tr>
                                <td>${recordTime}</td>
                                <td><span class="badge ${typeClass} rounded-pill"><i class="mdi ${typeIcon} me-1"></i> ${recordType}</span></td>
                                <td>${record.description || 'N/A'}</td>
                                <td>${record.amount} ${record.unit || ''}</td>
                                <td>${record.nurse_name}</td>
                            </tr>`;
                        });
                    } else {
                        html += `<tr><td colspan="5" class="text-center">No records found for this period</td></tr>`;
                    }

                    // Add balance row
                    html += `</tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="text-primary fw-bold">Total Intake: ${intakeTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-warning fw-bold">Total Output: ${outputTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="${balanceClass} fw-bold">Balance: ${balance} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    // Add a separator between periods
                    if (index < sortedPeriods.length - 1) {
                        html += '<hr class="my-3 opacity-50">';
                    }
                });

                container.innerHTML = html;
            }
        });
    </script>
@endsection

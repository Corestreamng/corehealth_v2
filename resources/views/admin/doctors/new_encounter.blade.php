@extends('admin.layouts.app')
@section('title', 'New Encounter')
@if (request()->get('admission_req_id') != '')
    @section('page_name', 'Ward Round')
@else
    @section('page_name', 'Consultations')
@endif
@section('subpage_name', 'New Encounter')
@section('content')
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
            <button class="nav-link" id="my_notes_tab" data-bs-toggle="tab" data-bs-target="#my_notes" type="button"
                role="tab" aria-controls="my_notes" aria-selected="false">My Diagnosis and Notes</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link " id="prescription_tab" data-bs-toggle="tab" data-bs-target="#prescription"
                type="button" role="tab" aria-controls="prescription" aria-selected="true">Prescription</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="investigations_tab" data-bs-toggle="tab" data-bs-target="#investigations"
                type="button" role="tab" aria-controls="investigations" aria-selected="false">Investigation</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="admission_tab" data-bs-toggle="tab" data-bs-target="#admission" type="button"
                role="tab" aria-controls="admission" aria-selected="false">Admission</button>
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
                                    <td>{{ $admission_request->bed->name }}</td>
                                    <th>Ward</th>
                                    <td>{{ $admission_request->bed->ward }}, <b>Unit:</b>
                                        {{ $admission_request->bed->unit ?? 'N/A' }}</td>
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
                            <th>Doctor</th>
                            <th>Notes</th>
                            <th>Time</th>
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
                    <button type="button" onclick="switch_tab(event,'my_notes_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
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
                        <input type="hidden" value="{{ request()->get('patient_id') }}" name="patient_id">
                        <input type="hidden" value="{{ request()->get('queue_id') ?? 'ward_round' }}" name="queue_id">
                        @if (request()->get('admission_req_id') != '')
                            <input type="hidden" value="{{ request()->get('admission_req_id') }}" name="queue_id">
                        @endif
                        {{-- <select name="reasons_for_encounter" id="reasons_for_encounter" class="form-control" multiple>
                            <option value="">--select reason--</option>
                        </select> --}}
                        <div>
                            <textarea name="doctor_diagnosis" id="" class="form-control classic-editor"></textarea>
                        </div>

                        <br><button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
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
                            onkeyup="searchProducts(this.value)" placeholder="search products...">
                        <ul class="list-group" id="consult_presc_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Dose/Freq.</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-products">

                                </tbody>
                            </table>
                        </div>
                        <br><button type="button" onclick="switch_tab(event,'my_notes_tab')"
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
                            onkeyup="searchServices(this.value)" placeholder="search services...">
                        <ul class="list-group" id="consult_invest_res" style="display: none;">

                        </ul>
                        <br>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Notes/specimen</th>
                                    <th>*</th>
                                </thead>
                                <tbody id="selected-services">

                                </tbody>
                            </table>
                        </div>
                        <br><button type="button" onclick="switch_tab(event,'prescription_tab')"
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
                        @if (isset($admission_request))
                            <h4>Currently Admitted</h4>
                        @else
                            <label for="consult_admit">Admit Patient</label>
                            <select name="consult_admit" id="consult_admit" class="form-control"
                                onchange="toggleAdmitNote(this)">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                            <br>
                            <div id="admit-note-div">

                            </div>
                        @endif
                        <br><button type="button" onclick="switch_tab(event,'investigations_tab')"
                            class="btn btn-secondary mr-2">
                            Prev
                        </button>
                        <button type="submit" class="btn btn-primary mr-2"> Save </button>
                        <a href="{{ route('encounters.index') }}"
                            onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                            class="btn btn-light">Exit</a>
                    </div>
                </div>
            </div>
        </form>
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
                    {
                        data: "doctor_id",
                        name: "doctor_id"
                    },
                    {
                        data: "notes",
                        name: "notes"
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
                    <textarea name="admit_note" id="admit_note" class="form-control">Enter brief note</textarea>
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
        $('.DataTables').DataTable();
    </script>
@endsection

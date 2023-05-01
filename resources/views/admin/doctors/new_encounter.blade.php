@extends('admin.layouts.app')
@section('title', 'New Encounter')
@section('page_name', 'Consultations')
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
                        <div class="col-sm-9">
                            <h1>{{ userfullname($patient->user->id) }}</h1>
                            <h3>File No: {{ $patient->file_no }}</h3>
                            @if ($patient->user->old_records)
                                <div class="form-group">
                                    <a href="{!! url('storage/image/user/old_records/' . $patient->user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old
                                        Records</a>
                                    <br>
                                </div>
                            @endif
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                <img src="{!! url('storage/image/user/' . $patient->user->filename) !!}" valign="middle" width="150px" height="120px" />
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
                                    <th>Disability status:</th>
                                    <td>{{ $patient->disability == 1 ? 'Disabled' : 'None' }}</td>
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
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="switch_tab(event,'vitals_data_tab')"
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
                    <button type="button" onclick="switch_tab(event,'patient_data_tab')" class="btn btn-secondary mr-2">
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
                    <button type="button" onclick="switch_tab(event,'vitals_data_tab')" class="btn btn-secondary mr-2">
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
                    <table class="table" id="encounter_history_list">
                        <thead>
                            <th>#</th>
                            <th>Doctor</th>
                            <th>Notes</th>
                            <th>Time</th>
                        </thead>
                    </table>
                    <button type="button" onclick="switch_tab(event,'investigation_hist_tab')"
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
                    <button type="button" onclick="switch_tab(event,'encounter_hist_tab')"
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
            <div class="tab-pane fade" id="my_notes" role="tabpanel" aria-labelledby="my_notes_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">
                        <select name="reasons_for_encounter" id="reasons_for_encounter" class="form-control" multiple>
                            <option value="">--select reason--</option>
                        </select>
                        <div contenteditable="true">
                            {!! $clinic->template !!}
                        </div>

                        <textarea name="notes" id="doc_notes" hidden></textarea>
                        <button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
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
            <div class="tab-pane fade show " id="prescription" role="tabpanel" aria-labelledby="prescription_tab">
                <div class="card mt-2">
                    <div class="card-body table-responsive">
                        <button type="button" onclick="switch_tab(event,'my_notes_tab')" class="btn btn-secondary mr-2">
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
                        <button type="button" onclick="switch_tab(event,'prescription_tab')"
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
                        <button type="button" onclick="switch_tab(event,'investigations_tab')"
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
@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            $('#encounter_history_list').DataTable({
                "dom": 'Bfrtip',
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
            $('#cont_consult_list').DataTable({
                "dom": 'Bfrtip',
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
        $(function() {
            $('#scheduled_consult_list').DataTable({
                "dom": 'Bfrtip',
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
        function switch_tab(e, id_of_next_tab) {
            e.preventDefault();
            $('#' + id_of_next_tab).click();
        }
    </script>
@endsection

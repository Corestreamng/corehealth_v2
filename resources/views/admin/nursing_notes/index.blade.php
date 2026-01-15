@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('subpage_name', 'Edit Patient')
@section('content')
    <div class="card-modern">
        <div class="card-header">
            <h3 class="card-title">{{ __('Notes ') }}</h3>
            @if (isset($dependant))
                <a class="btn btn-sm btn-primary" href="{{ route('viewConsultation', [$patient->id, $dependant->id]) }}"
                    target="_blank">View File</a>
            @else
                <a class="btn btn-sm btn-primary" href="{{ route('viewConsultation', [$patient->id]) }}" target="_blank">View
                    File</a>
            @endif

        </div>

        <div class="card-body">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="observation-tab" data-toggle="tab" href="#observation" role="tab"
                        aria-controls="observation" aria-selected="true">Observation Chart</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="treatment-tab" data-toggle="tab" href="#treatment" role="tab"
                        aria-controls="treatment" aria-selected="false">Treatment Sheet</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="io-tab" data-toggle="tab" href="#io" role="tab" aria-controls="io"
                        aria-selected="false">Intake/Output Chart</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="labour-tab" data-toggle="tab" href="#labour" role="tab"
                        aria-controls="labour" aria-selected="false">Labour Records</a>
                </li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="observation" role="tabpanel" aria-labelledby="observation-tab">
                    <form action="{{ route('nursing-note.store') }}" method="post" id="observation_form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="1">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <div class="form-group">
                            <label for="pateintNoteReport" class="control-label">Observation Chart for
                                {{ $dependant->fullname ?? $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br>
                            <div style="border:1px solid black;" id="the-observation-note" class='the-observation-note'>
                                <?php echo $observation_note->note ?? $observation_note_template->template; ?>
                            </div>
                            <textarea style="display: none" id="observation_text" name="the_text" class="form-control observation_text">
                        </textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                    <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="1">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <button type="submit" class="btn btn-success" style="float: right; margin-top:-40px">Save &
                            New</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="treatment" role="tabpanel" aria-labelledby="treatment-tab">
                    <form action="{{ route('nursing-note.store') }}" method="post" id="treatment_form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="2">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <div class="form-group">
                            <label for="pateintNoteReport" class="control-label">Treatment sheet for
                                {{ $dependant->fullname ?? $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br>
                            <div style="border:1px solid black;" id="the-treatment-note" class='the-treatment-note'>
                                <?php echo $treatment_sheet->note ?? $treatment_sheet_template->template; ?>
                            </div>
                            <textarea style="display: none" id="treatment_text" name="the_text" class="form-control treatment_text">
                        </textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                    <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="2">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <button type="submit" class="btn btn-success" style="float: right; margin-top:-40px">Save &
                            New</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="io" role="tabpanel" aria-labelledby="io-tab">
                    <form action="{{ route('nursing-note.store') }}" method="post" id="io_form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="3">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <div class="form-group">
                            <label for="pateintNoteReport" class="control-label">Intake/Output Chart
                                {{ $dependant->fullname ?? $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br>
                            <div style="border:1px solid black;" id="the-io-note" class='the-io-note'>
                                <?php echo $io_chart->note ?? $io_chart_template->template; ?>
                            </div>
                            <textarea style="display: none" id="io_text" name="the_text" class="form-control io_text">
                        </textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                    <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="3">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <button type="submit" class="btn btn-success" style="float: right; margin-top:-40px">Save &
                            New</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="labour" role="tabpanel" aria-labelledby="labour-tab">
                    <form action="{{ route('nursing-note.store') }}" method="post" id="labour_form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="4">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <input type="hidden" id="close_after_save" value="0">
                        <div class="form-group">
                            <label for="pateintDiagnosisReport" class="control-label">Labour Records
                                {{ $dependant->fullname ?? $patient->user->surname . ' ' . $patient->user->firstname . ' ' . $patient->user->othername }}</label><br>
                            <div style="border:1px solid black;" id="the-labour-note" class='the-labour-note'>
                                <?php echo $labour_record->note ?? $labour_record_template->template; ?>
                            </div>
                            <textarea style="display: none" id="labour_text" name="the_text" class="form-control labour_text">
                        </textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                    <form action="{{ route('nursing-note.new') }}" method="POST" class="form">
                        {{ csrf_field() }}
                        <input type="hidden" name="note_type" value="4">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        @if (isset($dependant))
                            <input type="hidden" name="dependant_id" value="{{ $dependant->id }}">
                        @endif
                        <button type="submit" class="btn btn-success" style="float: right; margin-top:-40px">Save &
                            New</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        function switch_tab(e, id_of_next_tab) {
            e.preventDefault();
            $('#' + id_of_next_tab).click();
        }
    </script>
    <script>
        $('#observation_form').on('submit', function(e) {
            e.preventDefault();
            var the_observation_note = $('#the-observation-note').html();
            document.getElementById('observation_text').innerHTML = the_observation_note;
            this.submit();
        })

        $('#treatment_form').on('submit', function(e) {
            e.preventDefault();
            var the_observation_note = $('#the-treatment-note').html();
            document.getElementById('treatment_text').innerHTML = the_observation_note;
            this.submit();
        })

        $('#io_form').on('submit', function(e) {
            e.preventDefault();
            var the_observation_note = $('#the-io-note').html();
            document.getElementById('io_text').innerHTML = the_observation_note;
            this.submit();
        })

        $('#labour_form').on('submit', function(e) {
            e.preventDefault();
            var the_observation_note = $('#the-labour-note').html();
            document.getElementById('labour_text').innerHTML = the_observation_note;
            this.submit();
        })
    </script>

@endsection

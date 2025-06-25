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
            <button type="submit" class="btn btn-success">New</button>
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
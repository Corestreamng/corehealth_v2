@php
    // $patient is available in parent view
@endphp
<div class="nurse-chart-tabs">
    <ul class="nav nav-tabs" id="nurseChartTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="medication-tab" data-bs-toggle="tab" data-bs-target="#medicationChart"
                type="button" role="tab">Medication Chart</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="intake-output-tab" data-bs-toggle="tab" data-bs-target="#intakeOutputChart"
                type="button" role="tab">Intake & Output Chart</button>
        </li>
        <li class="nav-item">
        <button class="nav-link" id="others-tab" data-bs-toggle="tab" data-bs-target="#others" type="button" role="tab"
            aria-controls="others" aria-selected="false">My Notes</button>
</li>
            <li class="nav-item">
        <button class="nav-link" id="closed-others-tab" data-bs-toggle="tab" data-bs-target="#closed-others" type="button" role="tab" aria-controls="closed-others" aria-selected="false">Notes History</button>
    </li>
    </ul>
    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="medicationChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_medication_enhanced')
        </div>
        <div class="tab-pane fade" id="intakeOutputChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_intake_output')
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

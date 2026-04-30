<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-flask-outline me-2 text-primary"></i>Investigation History</h5>
    @if(\Route::has('lab.workbench'))
        <a href="{{ route('lab.workbench') }}?patient_id={{ $patient->id }}"
           class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="mdi mdi-flask-outline me-1"></i> Open Lab Workbench
        </a>
    @endif
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Read-only view of all lab investigations. Use the Lab Workbench to request new tests or enter results.
</div>

<div class="table-responsive">
    <table id="investigation_history_list" class="table table-sm" style="width: 100%">
        <thead>
            <tr>
                <th>Investigation History</th>
            </tr>
        </thead>
    </table>
</div>

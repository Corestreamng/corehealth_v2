<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-image-filter-center-focus me-2 text-primary"></i>Imaging History</h5>
    @if(\Route::has('imaging.workbench'))
        <a href="{{ route('imaging.workbench') }}?patient_id={{ $patient->id }}"
           class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="mdi mdi-image-filter-center-focus me-1"></i> Open Imaging Workbench
        </a>
    @endif
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Read-only view of all imaging studies. Use the Imaging Workbench to request new studies or enter results.
</div>

<div class="table-responsive">
    <table id="imaging_history_list" class="table table-sm" style="width: 100%">
        <thead>
            <tr>
                <th>Imaging History</th>
            </tr>
        </thead>
    </table>
</div>

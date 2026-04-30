<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-bed me-2 text-primary"></i>Admission History</h5>
    @if(\Route::has('nursing-workbench.index'))
        <a href="{{ route('nursing-workbench.index') }}?patient_id={{ $patient->id }}"
           class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="mdi mdi-heart-pulse me-1"></i> Open Nursing Workbench
        </a>
    @endif
</div>

<div class="table-responsive">
    <table id="admission-request-list" class="table table-hover" style="width: 100%">
        <thead class="table-light">
            <tr>
                <th style="width: 100%;"><i class="fa fa-bed"></i> Admission History</th>
            </tr>
        </thead>
    </table>
</div>

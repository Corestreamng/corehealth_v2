<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-pill me-2 text-primary"></i>Prescription History</h5>
    @if(\Route::has('pharmacy.workbench'))
        <a href="{{ route('pharmacy.workbench') }}?patient_id={{ $patient->id }}"
           class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="mdi mdi-pill me-1"></i> Open Pharmacy Workbench
        </a>
    @endif
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Read-only view of all prescriptions. Use the Pharmacy Workbench to create or dispense prescriptions.
</div>

<div class="table-responsive">
    <table id="show1_presc_history_table" class="table table-sm table-bordered table-striped" style="width: 100%">
        <thead class="thead-light">
            <tr>
                <th>#</th>
                <th>Medication</th>
                <th>Code</th>
                <th>Dose</th>
                <th>Qty</th>
                <th>Status</th>
                <th>Prescribed By</th>
                <th>Date</th>
                <th>Payment</th>
            </tr>
        </thead>
    </table>
</div>


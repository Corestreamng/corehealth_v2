<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-cash-multiple me-2 text-primary"></i>Account Summary</h5>
    <div class="d-flex gap-2">
        @if(\Route::has('billing.workbench'))
            <a href="{{ route('billing.workbench') }}?patient_id={{ $patient->id }}"
               class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="mdi mdi-cash-register me-1"></i> Open Billing Workbench
            </a>
        @endif
        <a href="{{ route('patient-services-rendered', ['patient_id' => $patient->id]) }}"
           class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="mdi mdi-clipboard-list-outline me-1"></i> Services Rendered
        </a>
    </div>
</div>

{{-- Account Balance Card --}}
@if(null != $patient_acc)
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="mdi mdi-identifier me-1"></i>Account ID</div>
                    <div class="h4 font-weight-bold text-primary">{{ $patient_acc->id }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="mdi mdi-wallet me-1"></i>Current Balance</div>
                    <div class="h4 font-weight-bold text-success">₦{{ number_format($patient_acc->balance, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1"><i class="mdi mdi-clock-outline me-1"></i>Last Updated</div>
                    <div class="small text-info font-weight-bold">{{ date('d M Y, h:i a', strtotime($patient_acc->updated_at)) }}</div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-warning mb-4">
        <i class="mdi mdi-alert-circle-outline me-2"></i>
        This patient does not have an account yet. Please use the Billing Workbench to create one.
    </div>
@endif

{{-- Payment History --}}
<div class="mb-4">
    <h6 class="font-weight-bold text-muted mb-2"><i class="mdi mdi-history me-1"></i>Payment History</h6>
    <div class="table-responsive">
        <table id="payment_history_list" class="table table-sm table-bordered table-striped" style="width: 100%">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Total</th>
                    <th>Payment Type</th>
                    <th>Service / Product</th>
                    <th>Date</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- Misc Bill History (read-only) --}}
<div class="mb-4">
    <h6 class="font-weight-bold text-muted mb-2"><i class="mdi mdi-receipt me-1"></i>Miscellaneous Bill History</h6>
    <div class="table-responsive">
        <table id="misc_bill_bills_hist" class="table table-sm table-bordered table-striped" style="width: 100%">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Service</th>
                    <th>Details</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{-- Pending Payments --}}
<div class="mb-2">
    <h6 class="font-weight-bold text-muted mb-2"><i class="mdi mdi-clock-alert-outline me-1"></i>Pending Payments</h6>
    <div class="table-responsive">
        <table id="pending-paymnet-list" class="table table-sm table-bordered table-striped" style="width: 100%">
            <thead class="table-light">
                <tr>
                    <th>SN</th>
                    <th>Service</th>
                    <th>Product</th>
                    <th>View</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{--
    Patient Deposits Index/Dashboard
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 4
    Access: SUPERADMIN|ADMIN|ACCOUNTS|BILLER

    This integrates with:
    - PatientAccount (legacy balance tracking)
    - PatientDeposit (detailed deposit tracking)
    - Aged Payables (positive balance = hospital liability)
    - Aged Receivables (negative balance = patient debt)
--}}

@extends('admin.layouts.app')

@section('title', 'Patient Deposits')
@section('page_name', 'Accounting')
@section('subpage_name', 'Patient Deposits')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Patient Deposits', 'url' => '#', 'icon' => 'mdi-account-cash']
    ]
])

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
}
.stat-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-card .value {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-card .label {
    font-size: 0.85rem;
    color: #666;
}
.filter-panel {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.balance-indicator {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}
.balance-indicator.credit {
    background: #d4edda;
    color: #155724;
}
.balance-indicator.debt {
    background: #f8d7da;
    color: #721c24;
}
.integration-note {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Integration Note -->
    <div class="integration-note">
        <div class="d-flex align-items-center">
            <i class="mdi mdi-information-outline mdi-24px mr-3"></i>
            <div>
                <strong>Integrated Deposit Management</strong>
                <div class="small opacity-75">
                    Patient deposits sync with Billing Workbench accounts. Positive balances appear in Aged Payables (hospital liability),
                    negative balances in Aged Receivables (patient debt). All transactions create GL journal entries automatically.
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="icon bg-success text-white mr-3">
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>
                    <div>
                        <div class="value text-success">₦{{ number_format($stats['total_active_deposits'], 2) }}</div>
                        <div class="label">Active Deposits</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="icon bg-info text-white mr-3">
                        <i class="mdi mdi-account-multiple-check"></i>
                    </div>
                    <div>
                        <div class="value text-info">{{ $stats['patients_with_credit'] }}</div>
                        <div class="label">Patients with Credit</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="icon bg-warning text-white mr-3">
                        <i class="mdi mdi-alert-circle-outline"></i>
                    </div>
                    <div>
                        <div class="value text-warning">{{ $stats['patients_with_debt'] }}</div>
                        <div class="label">Patients with Debt</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center">
                    <div class="icon bg-primary text-white mr-3">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div>
                        <div class="value text-primary">{{ $stats['pending_application'] }}</div>
                        <div class="label">Pending Application</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card text-center">
                <div class="label">Hospital Liability (Payables)</div>
                <div class="value text-danger">₦{{ number_format($stats['patient_credits'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card text-center">
                <div class="label">Patient Debt (Receivables)</div>
                <div class="value text-success">₦{{ number_format($stats['patient_debts'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card text-center">
                <div class="label">Today's Deposits</div>
                <div class="value text-primary">₦{{ number_format($stats['today_deposits'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card text-center">
                <div class="label">This Month Applied</div>
                <div class="value text-info">₦{{ number_format($stats['month_applications'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form id="filter-form" class="row align-items-end">
            <div class="col-md-2">
                <label>Status</label>
                <select name="status" class="form-control form-control-sm">
                    <option value="">All Statuses</option>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Deposit Type</label>
                <select name="deposit_type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    @foreach($depositTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label>To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-md-2">
                <label>Search</label>
                <input type="text" name="search_term" class="form-control form-control-sm" placeholder="Name, File No, Deposit #">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm mr-1">
                    <i class="mdi mdi-filter mr-1"></i> Filter
                </button>
                <button type="reset" class="btn btn-secondary btn-sm" id="btn-reset">
                    <i class="mdi mdi-refresh"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Actions -->
    <div class="d-flex justify-content-between mb-3">
        <div>
            <a href="{{ route('accounting.patient-deposits.create') }}" class="btn btn-success">
                <i class="mdi mdi-plus mr-1"></i> New Deposit
            </a>
            <a href="{{ route('accounting.reports.aged-payables') }}?payable_type=patient_deposits" class="btn btn-outline-info ml-2">
                <i class="mdi mdi-file-chart mr-1"></i> Payables Report
            </a>
        </div>
        <div class="btn-group">
            <a href="{{ route('accounting.patient-deposits.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="btn btn-danger">
                <i class="mdi mdi-file-pdf mr-1"></i> PDF
            </a>
            <a href="{{ route('accounting.patient-deposits.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="btn btn-success">
                <i class="mdi mdi-file-excel mr-1"></i> Excel
            </a>
        </div>
    </div>

    <!-- Deposits Table -->
    <div class="card">
        <div class="card-body">
            <table id="deposits-table" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>Deposit #</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>File No</th>
                        <th>Type</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Balance</th>
                        <th>Utilization</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-credit-card mr-2"></i>Apply Deposit to Bill</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="apply-form">
                <div class="modal-body">
                    <input type="hidden" id="apply-deposit-id">
                    <div class="form-group">
                        <label>Amount to Apply <span class="text-danger">*</span></label>
                        <input type="number" id="apply-amount" class="form-control" step="0.01" min="0.01" required>
                        <small class="text-muted">Available balance: ₦<span id="apply-available">0.00</span></small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="apply-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-cash-refund mr-2"></i>Process Refund</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="refund-form">
                <div class="modal-body">
                    <input type="hidden" id="refund-deposit-id">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert-circle mr-2"></i>
                        Refunding will reduce the patient's account balance and create a journal entry.
                    </div>
                    <div class="form-group">
                        <label>Refund Amount <span class="text-danger">*</span></label>
                        <input type="number" id="refund-amount" class="form-control" step="0.01" min="0.01" required>
                        <small class="text-muted">Maximum refundable: ₦<span id="refund-max">0.00</span></small>
                    </div>
                    <div class="form-group">
                        <label>Reason for Refund <span class="text-danger">*</span></label>
                        <textarea id="refund-reason" class="form-control" rows="2" required placeholder="Explain why the refund is being processed"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Process Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#deposits-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.patient-deposits.datatable') }}',
            data: function(d) {
                d.status = $('select[name="status"]').val();
                d.deposit_type = $('select[name="deposit_type"]').val();
                d.date_from = $('input[name="date_from"]').val();
                d.date_to = $('input[name="date_to"]').val();
                d.search_term = $('input[name="search_term"]').val();
            }
        },
        columns: [
            { data: 'deposit_number', name: 'deposit_number' },
            { data: 'deposit_date', name: 'deposit_date', render: function(data) {
                return moment(data).format('MMM D, YYYY');
            }},
            { data: 'patient_name', name: 'patient_name' },
            { data: 'file_no', name: 'file_no' },
            { data: 'deposit_type', name: 'deposit_type', render: function(data) {
                return data.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            }},
            { data: 'amount', name: 'amount', className: 'text-right', render: function(data) {
                return '₦' + parseFloat(data).toLocaleString('en-NG', {minimumFractionDigits: 2});
            }},
            { data: 'balance', name: 'balance', className: 'text-right', render: function(data) {
                var color = parseFloat(data) > 0 ? 'success' : 'secondary';
                return '<span class="text-' + color + '">₦' + parseFloat(data).toLocaleString('en-NG', {minimumFractionDigits: 2}) + '</span>';
            }},
            { data: 'utilization_percent', name: 'utilization_percent', render: function(data) {
                var percent = parseFloat(data);
                var color = percent >= 100 ? 'success' : (percent > 50 ? 'info' : 'warning');
                return '<div class="progress" style="height: 20px;"><div class="progress-bar bg-' + color + '" style="width: ' + percent + '%">' + percent.toFixed(0) + '%</div></div>';
            }},
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25
    });

    // Filter form
    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    $('#btn-reset').on('click', function() {
        $('#filter-form')[0].reset();
        table.ajax.reload();
    });

    // Apply deposit
    $(document).on('click', '.btn-apply', function() {
        var id = $(this).data('id');
        var row = table.row($(this).closest('tr')).data();
        $('#apply-deposit-id').val(id);
        $('#apply-available').text(parseFloat(row.balance).toFixed(2));
        $('#apply-amount').attr('max', row.balance);
        $('#applyModal').modal('show');
    });

    $('#apply-form').on('submit', function(e) {
        e.preventDefault();
        var id = $('#apply-deposit-id').val();

        $.ajax({
            url: '/accounting/patient-deposits/' + id + '/apply',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                amount: $('#apply-amount').val(),
                notes: $('#apply-notes').val()
            },
            success: function(res) {
                $('#applyModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to apply deposit');
            }
        });
    });

    // Refund deposit
    $(document).on('click', '.btn-refund', function() {
        var id = $(this).data('id');
        var balance = $(this).data('balance');
        $('#refund-deposit-id').val(id);
        $('#refund-max').text(parseFloat(balance).toFixed(2));
        $('#refund-amount').attr('max', balance);
        $('#refundModal').modal('show');
    });

    $('#refund-form').on('submit', function(e) {
        e.preventDefault();
        var id = $('#refund-deposit-id').val();

        $.ajax({
            url: '/accounting/patient-deposits/' + id + '/refund',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                amount: $('#refund-amount').val(),
                reason: $('#refund-reason').val()
            },
            success: function(res) {
                $('#refundModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process refund');
            }
        });
    });
});
</script>
@endpush

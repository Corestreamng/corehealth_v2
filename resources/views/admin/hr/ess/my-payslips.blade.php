@extends('admin.layouts.app')

@section('title', 'ESS - My Payslips')

@section('styles')
<link href="{{ asset('plugins/datatables/datatables.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-file-document mr-2"></i>My Payslips
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">My Payslips</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Latest Net Salary</h6>
                            <h3 class="mb-0" style="font-weight: 700;">
                                ₦{{ number_format($latestPayslip->net_salary ?? 0, 2) }}
                            </h3>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-cash"></i>
                        </div>
                    </div>
                    @if($latestPayslip)
                    <small class="text-white-50">
                        {{ \Carbon\Carbon::parse($latestPayslip->payrollBatch->pay_period ?? now())->format('F Y') }}
                    </small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">YTD Gross</h6>
                            <h3 class="mb-0" style="font-weight: 700;">
                                ₦{{ number_format($ytdGross ?? 0, 2) }}
                            </h3>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-chart-line"></i>
                        </div>
                    </div>
                    <small class="text-white-50">Year to date ({{ date('Y') }})</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">YTD Deductions</h6>
                            <h3 class="mb-0" style="font-weight: 700;">
                                ₦{{ number_format($ytdDeductions ?? 0, 2) }}
                            </h3>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-minus-circle"></i>
                        </div>
                    </div>
                    <small class="text-white-50">Year to date ({{ date('Y') }})</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Payslips Table -->
    <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>Payslip History
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="payslipsTable" class="table table-hover" style="width: 100%;">
                    <thead class="bg-light">
                        <tr>
                            <th>Pay Period</th>
                            <th>Basic Salary</th>
                            <th>Gross Salary</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Payslip Modal -->
<div class="modal fade" id="viewPayslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-primary text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-document mr-2"></i>Payslip Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Close</button>
                <button type="button" class="btn btn-primary btn-download-payslip" style="border-radius: 8px;">
                    <i class="mdi mdi-download mr-1"></i>Download PDF
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/datatables/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#payslipsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("ess.my-payslips.data") }}',
        columns: [
            { data: 'pay_period', name: 'payrollBatch.pay_period' },
            { data: 'basic_salary', name: 'basic_salary' },
            { data: 'gross_salary', name: 'gross_salary' },
            { data: 'total_deductions', name: 'total_deductions' },
            { data: 'net_salary', name: 'net_salary' },
            { data: 'status', name: 'payrollBatch.status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: "No payslips found"
        }
    });

    // View payslip
    var currentPayslipId = null;

    $(document).on('click', '.view-payslip', function() {
        var id = $(this).data('id');
        currentPayslipId = id;

        $.get('{{ url("ess/my-payslips") }}/' + id, function(data) {
            var html = generatePayslipHtml(data);
            $('#payslipContent').html(html);
            $('#viewPayslipModal').modal('show');
        });
    });

    // Generate payslip HTML
    function generatePayslipHtml(data) {
        var html = `
            <div class="payslip-container" style="font-family: Arial, sans-serif;">
                <div class="text-center mb-4">
                    <h4 style="font-weight: 700;">{{ config('app.name') }}</h4>
                    <p class="text-muted mb-0">Payslip for ${data.pay_period}</p>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Employee Name:</td>
                                <td><strong>${data.staff_name}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Employee ID:</td>
                                <td>${data.staff_id}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Department:</td>
                                <td>${data.department || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Pay Period:</td>
                                <td><strong>${data.pay_period}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Payment Date:</td>
                                <td>${data.payment_date || 'Pending'}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Bank Account:</td>
                                <td>${data.bank_account || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3" style="border-radius: 8px;">
                            <div class="card-header bg-success text-white py-2" style="border-radius: 8px 8px 0 0;">
                                <strong>Earnings</strong>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="text-right">₦${formatNumber(data.basic_salary)}</td>
                                    </tr>`;

        // Add earnings/additions
        if (data.additions && data.additions.length > 0) {
            data.additions.forEach(function(item) {
                html += `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td class="text-right">₦${formatNumber(item.amount)}</td>
                                    </tr>`;
            });
        }

        html += `
                                    <tr class="bg-light">
                                        <td><strong>Gross Salary</strong></td>
                                        <td class="text-right"><strong>₦${formatNumber(data.gross_salary)}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-3" style="border-radius: 8px;">
                            <div class="card-header bg-danger text-white py-2" style="border-radius: 8px 8px 0 0;">
                                <strong>Deductions</strong>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">`;

        // Add deductions
        if (data.deductions && data.deductions.length > 0) {
            data.deductions.forEach(function(item) {
                html += `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td class="text-right">₦${formatNumber(item.amount)}</td>
                                    </tr>`;
            });
        } else {
            html += `
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">No deductions</td>
                                    </tr>`;
        }

        html += `
                                    <tr class="bg-light">
                                        <td><strong>Total Deductions</strong></td>
                                        <td class="text-right"><strong>₦${formatNumber(data.total_deductions)}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Net Salary</h5>
                            <h3 class="mb-0" style="font-weight: 700;">₦${formatNumber(data.net_salary)}</h3>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-NG', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Download payslip
    $('.btn-download-payslip').on('click', function() {
        if (currentPayslipId) {
            window.location.href = '{{ url("ess/my-payslips") }}/' + currentPayslipId + '/download';
        }
    });
});
</script>
@endsection

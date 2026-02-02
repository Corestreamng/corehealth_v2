@extends('admin.layouts.app')

@section('title', 'Payslips - ' . $payrollBatch->batch_number)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-file-document-multiple mr-2"></i>Payslips
                    </h3>
                    <p class="text-muted mb-0">
                        Batch: {{ $payrollBatch->batch_number }} |
                        Period: {{ $payrollBatch->pay_period_start->format('M d') }} - {{ $payrollBatch->pay_period_end->format('M d, Y') }}
                        @if($payrollBatch->days_worked && $payrollBatch->days_in_month && $payrollBatch->days_worked < $payrollBatch->days_in_month)
                        <span class="badge badge-warning ml-2">
                            Pro-rata: {{ $payrollBatch->days_worked }}/{{ $payrollBatch->days_in_month }} days
                        </span>
                        @endif
                    </p>
                </div>
                <div>
                    <a href="{{ route('hr.payroll.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Batches
                    </a>
                </div>
            </div>

            @if($payrollBatch->work_period_start && $payrollBatch->work_period_end && $payrollBatch->days_worked < $payrollBatch->days_in_month)
            <!-- Pro-rata Info Banner -->
            <div class="alert alert-info mb-4" style="border-radius: 8px; border: none; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="mdi mdi-information-outline mr-2"></i>
                        <strong>Pro-rata Calculation Applied</strong><br>
                        <small class="text-muted">
                            Work Period: {{ $payrollBatch->work_period_start->format('M d') }} - {{ $payrollBatch->work_period_end->format('M d, Y') }}
                            | Days Worked: <strong>{{ $payrollBatch->days_worked }}</strong> of {{ $payrollBatch->days_in_month }} days
                            ({{ round(($payrollBatch->days_worked / $payrollBatch->days_in_month) * 100, 1) }}%)
                        </small>
                    </div>
                    <div class="text-right">
                        <small class="text-muted">Pro-rata Factor</small>
                        <h4 class="mb-0 text-primary">{{ round(($payrollBatch->days_worked / $payrollBatch->days_in_month) * 100, 1) }}%</h4>
                    </div>
                </div>
            </div>
            @endif

            <!-- Summary Card -->
            <div class="card-modern mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Total Staff</h6>
                            <h4 class="text-primary mb-0">{{ $payrollBatch->total_staff }}</h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Total Gross</h6>
                            <h4 class="text-success mb-0">₦{{ number_format($payrollBatch->total_gross, 2) }}</h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Total Deductions</h6>
                            <h4 class="text-danger mb-0">₦{{ number_format($payrollBatch->total_deductions, 2) }}</h4>
                        </div>
                        <div class="col-md-3">
                            <h6 class="text-muted mb-1">Total Net Pay</h6>
                            <h4 class="text-info mb-0">₦{{ number_format($payrollBatch->total_net, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payslips -->
            @foreach($payslips as $payslip)
            <div class="card-modern payslip-card mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <div>
                        <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                            {{ $payslip['employee_name'] }}
                        </h5>
                        <small class="text-muted">{{ $payslip['employee_id'] }} | {{ $payslip['department'] }}</small>
                    </div>
                    <div class="text-right d-flex align-items-center">
                        <div class="mr-3">
                            <small class="text-muted d-block">Payslip #{{ $payslip['payslip_number'] }}</small>
                            <span class="badge badge-success">{{ $payslip['pay_period'] }}</span>
                        </div>
                        <a href="{{ route('hr.payroll.payslip.print', [$payrollBatch->id, $payslip['item_id']]) }}"
                           target="_blank"
                           class="btn btn-sm btn-outline-primary no-print"
                           title="Print this payslip">
                            <i class="mdi mdi-printer"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Earnings -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3" style="font-weight: 600;">
                                <i class="mdi mdi-plus-circle mr-1"></i> Earnings
                            </h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="text-right font-weight-bold">₦{{ number_format($payslip['basic_salary'], 2) }}</td>
                                    </tr>
                                    @foreach($payslip['additions'] as $addition)
                                    <tr>
                                        <td>{{ $addition['name'] }}</td>
                                        <td class="text-right">₦{{ number_format($addition['amount'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-top">
                                    <tr class="text-success">
                                        <th>Gross Salary</th>
                                        <th class="text-right">₦{{ number_format($payslip['gross_salary'], 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Deductions -->
                        <div class="col-md-6">
                            <h6 class="text-danger mb-3" style="font-weight: 600;">
                                <i class="mdi mdi-minus-circle mr-1"></i> Deductions
                            </h6>
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    @forelse($payslip['deductions'] as $deduction)
                                    <tr>
                                        <td>{{ $deduction['name'] }}</td>
                                        <td class="text-right">₦{{ number_format($deduction['amount'], 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="2" class="text-muted">No deductions</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="border-top">
                                    <tr class="text-danger">
                                        <th>Total Deductions</th>
                                        <th class="text-right">₦{{ number_format($payslip['total_deductions'], 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Net Pay -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="bg-primary text-white p-3 d-flex justify-content-between align-items-center" style="border-radius: 8px;">
                                <div>
                                    <h6 class="mb-0 text-white-50">Net Pay</h6>
                                    <small class="text-white-50">Payment Date: {{ $payslip['payment_date'] }}</small>
                                </div>
                                <h3 class="mb-0">₦{{ number_format($payslip['net_salary'], 2) }}</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    @if($payslip['bank_name'] || $payslip['bank_account_number'])
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="bg-light p-3" style="border-radius: 8px;">
                                <h6 class="mb-2" style="font-weight: 600;">
                                    <i class="mdi mdi-bank mr-1"></i> Bank Details
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Bank Name</small>
                                        <strong>{{ $payslip['bank_name'] ?? 'N/A' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Account Number</small>
                                        <strong>{{ $payslip['bank_account_number'] ?? 'N/A' }}</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Account Name</small>
                                        <strong>{{ $payslip['bank_account_name'] ?? 'N/A' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            @if($payslips->isEmpty())
            <div class="card-modern" style="border-radius: 12px; border: none;">
                <div class="card-body text-center py-5">
                    <i class="mdi mdi-file-document-outline text-muted" style="font-size: 4rem;"></i>
                    <h5 class="text-muted mt-3">No Payslips Available</h5>
                    <p class="text-muted">This batch has no payroll items yet.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .sidebar, .navbar, .page-header, nav, .no-print {
        display: none !important;
    }

    .payslip-card {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-bottom: 20px;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    .container-fluid {
        padding: 0 !important;
    }

    .card {
        box-shadow: none !important;
    }
}
</style>
@endsection

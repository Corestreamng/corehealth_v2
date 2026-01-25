@extends('admin.layouts.app')

@section('title', 'ESS - My Payslips')

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
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
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
                        {{ \Carbon\Carbon::parse($latestPayslip->payrollBatch->pay_period_start ?? now())->format('F Y') }}
                    </small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
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
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
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
    <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>Payslip History
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
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
                    <tbody>
                        @forelse($payslips as $payslip)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payslip->payrollBatch->pay_period_start ?? now())->format('F Y') }}</td>
                            <td>₦{{ number_format($payslip->basic_salary, 2) }}</td>
                            <td>₦{{ number_format($payslip->gross_salary, 2) }}</td>
                            <td>₦{{ number_format($payslip->total_deductions, 2) }}</td>
                            <td><strong>₦{{ number_format($payslip->net_salary, 2) }}</strong></td>
                            <td>
                                @php
                                    $status = $payslip->payrollBatch->status ?? 'pending';
                                    $badgeClass = match($status) {
                                        'approved' => 'success',
                                        'paid' => 'info',
                                        default => 'warning'
                                    };
                                @endphp
                                <span class="badge badge-{{ $badgeClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('hr.ess.my-payslips.print', $payslip->id) }}"
                                   class="btn btn-sm btn-primary"
                                   target="_blank"
                                   style="border-radius: 6px;">
                                    <i class="mdi mdi-printer"></i> Print
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="mdi mdi-alert-circle-outline" style="font-size: 2rem;"></i>
                                <p class="mb-0">No payslips found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($payslips->hasPages())
            <div class="mt-3">
                {{ $payslips->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

@endsection

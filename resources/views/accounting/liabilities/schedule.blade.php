@extends('admin.layouts.app')
@section('title', 'Amortization Schedule')
@section('page_name', 'Accounting')
@section('subpage_name', 'Amortization Schedule')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock'],
    ['label' => $liability->liability_number, 'url' => route('accounting.liabilities.show', $liability->id), 'icon' => 'mdi-eye'],
    ['label' => 'Amortization Schedule', 'url' => '#', 'icon' => 'mdi-table']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Liability Summary -->
        <div class="card-modern mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="mdi mdi-credit-card-clock mr-2"></i>{{ $liability->liability_number }} - {{ $liability->creditor_name }}
                </h5>
                <div>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-printer"></i> Print
                    </button>
                    <a href="{{ route('accounting.liabilities.show', $liability->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Back to Details
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Principal Amount</small>
                        <h5 class="mb-0">₦{{ number_format($liability->principal_amount, 2) }}</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Interest Rate</small>
                        <h5 class="mb-0">{{ $liability->interest_rate }}% p.a.</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Term</small>
                        <h5 class="mb-0">{{ $liability->term_months }} months</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Payment Frequency</small>
                        <h5 class="mb-0">{{ ucfirst(str_replace('_', ' ', $liability->payment_frequency)) }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Amortization Table -->
        <div class="card-modern card-modern">
            <div class="card-header">
                <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Full Amortization Schedule</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="amortization-table">
                        <thead class="thead-dark">
                            <tr>
                                <th class="text-center">#</th>
                                <th>Due Date</th>
                                <th class="text-right">Payment Amount</th>
                                <th class="text-right">Principal</th>
                                <th class="text-right">Interest</th>
                                <th class="text-right">Balance After</th>
                                <th class="text-center">Status</th>
                                <th>Paid Date</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalPayment = 0;
                                $totalPrincipal = 0;
                                $totalInterest = 0;
                                $totalPaid = 0;
                            @endphp
                            @foreach($schedule as $payment)
                            @php
                                $totalPayment += $payment->payment_amount;
                                $totalPrincipal += $payment->principal_amount;
                                $totalInterest += $payment->interest_amount;
                                if ($payment->paid_date) {
                                    $totalPaid += $payment->amount_paid ?? $payment->payment_amount;
                                }

                                $rowClass = '';
                                if ($payment->paid_date) {
                                    $rowClass = 'table-success';
                                } elseif ($payment->due_date < now()->toDateString()) {
                                    $rowClass = 'table-danger';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center">{{ $payment->payment_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                <td class="text-right">₦{{ number_format($payment->payment_amount, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->principal_amount, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->interest_amount, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->balance_after_payment, 2) }}</td>
                                <td class="text-center">
                                    @if($payment->paid_date)
                                        <span class="badge badge-success"><i class="mdi mdi-check"></i> Paid</span>
                                    @elseif($payment->due_date < now()->toDateString())
                                        <span class="badge badge-danger"><i class="mdi mdi-alert"></i> Overdue</span>
                                    @else
                                        <span class="badge badge-warning"><i class="mdi mdi-clock"></i> Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payment->paid_date)
                                        {{ \Carbon\Carbon::parse($payment->paid_date)->format('M d, Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $payment->payment_reference ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2" class="text-right">TOTALS:</th>
                                <th class="text-right">₦{{ number_format($totalPayment, 2) }}</th>
                                <th class="text-right">₦{{ number_format($totalPrincipal, 2) }}</th>
                                <th class="text-right">₦{{ number_format($totalInterest, 2) }}</th>
                                <th colspan="4"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Summary Cards -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card-modern bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Payments</h6>
                                <h4 class="mb-0">{{ $schedule->count() }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Payments Made</h6>
                                <h4 class="mb-0">{{ $schedule->whereNotNull('paid_date')->count() }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Total Interest</h6>
                                <h4 class="mb-0">₦{{ number_format($totalInterest, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Total Repayment</h6>
                                <h4 class="mb-0">₦{{ number_format($totalPayment, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .btn, .breadcrumb, nav, .sidebar, .navbar, footer { display: none !important; }
        .card { border: 1px solid #ddd !important; }
        .table-responsive { overflow: visible !important; }
    }
</style>
@endpush

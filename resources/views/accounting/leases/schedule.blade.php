@extends('admin.layouts.app')
@section('title', 'Lease Payment Schedule')
@section('page_name', 'Accounting')
@section('subpage_name', 'Payment Schedule')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Payment Schedule', 'url' => '#', 'icon' => 'mdi-table']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Lease Summary -->
        <div class="card card-modern mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="mdi mdi-file-document-edit mr-2"></i>{{ $lease->lease_number }} - {{ $lease->leased_item }}
                </h5>
                <div>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-printer"></i> Print
                    </button>
                    <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Back to Details
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <small class="text-muted">Lessor</small>
                        <h6 class="mb-0">{{ $lease->lessor_name ?: $lease->supplier_name ?: '-' }}</h6>
                    </div>
                    <div class="col-md-2 text-center">
                        <small class="text-muted">Monthly Payment</small>
                        <h6 class="mb-0">₦{{ number_format($lease->monthly_payment, 2) }}</h6>
                    </div>
                    <div class="col-md-2 text-center">
                        <small class="text-muted">Lease Term</small>
                        <h6 class="mb-0">{{ $lease->lease_term_months }} months</h6>
                    </div>
                    <div class="col-md-2 text-center">
                        <small class="text-muted">IBR</small>
                        <h6 class="mb-0">{{ $lease->incremental_borrowing_rate }}% p.a.</h6>
                    </div>
                    <div class="col-md-2 text-center">
                        <small class="text-muted">Initial Liability</small>
                        <h6 class="mb-0">₦{{ number_format($lease->initial_lease_liability, 2) }}</h6>
                    </div>
                    <div class="col-md-2 text-center">
                        <small class="text-muted">Initial ROU Asset</small>
                        <h6 class="mb-0">₦{{ number_format($lease->initial_rou_asset_value, 2) }}</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Schedule Table -->
        <div class="card card-modern">
            <div class="card-header">
                <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Full Payment Schedule</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="schedule-table">
                        <thead class="thead-dark">
                            <tr>
                                <th class="text-center">#</th>
                                <th>Due Date</th>
                                <th class="text-right">Payment</th>
                                <th class="text-right">Principal</th>
                                <th class="text-right">Interest</th>
                                <th class="text-right">Liability After</th>
                                <th class="text-right">ROU Depr.</th>
                                <th class="text-right">ROU Value After</th>
                                <th class="text-center">Status</th>
                                <th>Paid Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalPayment = 0;
                                $totalPrincipal = 0;
                                $totalInterest = 0;
                                $totalDepreciation = 0;
                            @endphp
                            @foreach($schedule as $payment)
                            @php
                                $totalPayment += $payment->payment_amount;
                                $totalPrincipal += $payment->principal_portion;
                                $totalInterest += $payment->interest_portion;
                                $totalDepreciation += $payment->rou_depreciation;

                                $rowClass = '';
                                if ($payment->payment_date) {
                                    $rowClass = 'table-success';
                                } elseif ($payment->due_date < now()->toDateString() && $lease->status === 'active') {
                                    $rowClass = 'table-danger';
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center">{{ $payment->payment_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                <td class="text-right">₦{{ number_format($payment->payment_amount, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->principal_portion, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->interest_portion, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->closing_liability, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->rou_depreciation, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->closing_rou_value, 2) }}</td>
                                <td class="text-center">
                                    @if($payment->payment_date)
                                        <span class="badge badge-success"><i class="mdi mdi-check"></i> Paid</span>
                                    @elseif($payment->due_date < now()->toDateString() && $lease->status === 'active')
                                        <span class="badge badge-danger"><i class="mdi mdi-alert"></i> Overdue</span>
                                    @elseif($lease->status !== 'active')
                                        <span class="badge badge-secondary"><i class="mdi mdi-minus"></i> Cancelled</span>
                                    @else
                                        <span class="badge badge-warning"><i class="mdi mdi-clock"></i> Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($payment->payment_date)
                                        {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="2" class="text-right">TOTALS:</th>
                                <th class="text-right">₦{{ number_format($totalPayment, 2) }}</th>
                                <th class="text-right">₦{{ number_format($totalPrincipal, 2) }}</th>
                                <th class="text-right">₦{{ number_format($totalInterest, 2) }}</th>
                                <th></th>
                                <th class="text-right">₦{{ number_format($totalDepreciation, 2) }}</th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Summary Cards -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Payments</h6>
                                <h4 class="mb-0">{{ $schedule->count() }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Payments Made</h6>
                                <h4 class="mb-0">{{ $schedule->whereNotNull('payment_date')->count() }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h6>Total Interest</h6>
                                <h4 class="mb-0">₦{{ number_format($totalInterest, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Total Depreciation</h6>
                                <h4 class="mb-0">₦{{ number_format($totalDepreciation, 2) }}</h4>
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

@extends('admin.layouts.app')
@section('title', 'IFRS 16 Disclosure Report')
@section('page_name', 'Accounting')
@section('subpage_name', 'IFRS 16 Disclosure')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => 'IFRS 16 Disclosure', 'url' => '#', 'icon' => 'mdi-file-chart']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Report Header -->
        <div class="card-modern mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-file-chart mr-2"></i>IFRS 16 Lease Disclosure Report</h5>
                <div>
                    <button onclick="window.print()" class="btn btn-light btn-sm">
                        <i class="mdi mdi-printer"></i> Print Report
                    </button>
                    <a href="{{ route('accounting.leases.export.excel') }}" class="btn btn-light btn-sm">
                        <i class="mdi mdi-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <h4>Lease Disclosures in Accordance with IFRS 16</h4>
                    <p class="text-muted">As at {{ date('F d, Y') }}</p>
                </div>
            </div>
        </div>

        <!-- Balance Sheet Impact -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card-modern h-100">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="mdi mdi-office-building mr-2"></i>Right-of-Use Assets</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-bordered mb-0">
                            <tr>
                                <td>Opening Balance (Initial Recognition)</td>
                                <td class="text-right">₦{{ number_format($summary['total_initial_rou'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Less: Accumulated Depreciation</td>
                                <td class="text-right text-danger">(₦{{ number_format($summary['total_accumulated_depreciation'], 2) }})</td>
                            </tr>
                            <tr class="table-primary font-weight-bold">
                                <td>Carrying Amount</td>
                                <td class="text-right">₦{{ number_format($summary['total_rou_assets'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-modern h-100">
                    <div class="card-header bg-warning">
                        <h6 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i>Lease Liabilities</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-bordered mb-0">
                            <tr>
                                <td>Opening Balance (Initial Recognition)</td>
                                <td class="text-right">₦{{ number_format($summary['total_initial_liability'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Less: Principal Payments Made</td>
                                <td class="text-right text-success">(₦{{ number_format($summary['total_initial_liability'] - $summary['total_lease_liabilities'], 2) }})</td>
                            </tr>
                            <tr class="table-warning font-weight-bold">
                                <td>Carrying Amount</td>
                                <td class="text-right">₦{{ number_format($summary['total_lease_liabilities'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maturity Analysis -->
        <div class="card-modern mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="mdi mdi-calendar-range mr-2"></i>Maturity Analysis of Lease Liabilities</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Time Band</th>
                                    <th class="text-right">Undiscounted Future Lease Payments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Less than 1 year (Current)</td>
                                    <td class="text-right">₦{{ number_format($maturityAnalysis['less_than_1_year'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>1 to 5 years (Non-current)</td>
                                    <td class="text-right">₦{{ number_format($maturityAnalysis['1_to_5_years'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>More than 5 years (Non-current)</td>
                                    <td class="text-right">₦{{ number_format($maturityAnalysis['more_than_5_years'], 2) }}</td>
                                </tr>
                                <tr class="table-dark font-weight-bold">
                                    <td>Total</td>
                                    <td class="text-right">₦{{ number_format(array_sum($maturityAnalysis), 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <div class="card-modern bg-light">
                            <div class="card-body">
                                <h6 class="text-muted">Current/Non-Current Split</h6>
                                <hr>
                                <p class="mb-1">
                                    <span class="badge badge-success">Current</span>
                                    ₦{{ number_format($maturityAnalysis['less_than_1_year'], 2) }}
                                </p>
                                <p class="mb-0">
                                    <span class="badge badge-secondary">Non-Current</span>
                                    ₦{{ number_format($maturityAnalysis['1_to_5_years'] + $maturityAnalysis['more_than_5_years'], 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- By Lease Type -->
        <div class="card-modern mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i>Analysis by Lease Type</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Lease Type</th>
                                <th class="text-center">Count</th>
                                <th class="text-right">ROU Asset Value</th>
                                <th class="text-right">Lease Liability</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($byType as $type => $data)
                            <tr>
                                <td>
                                    @php
                                        $badges = [
                                            'operating' => 'badge-secondary',
                                            'finance' => 'badge-primary',
                                            'short_term' => 'badge-info',
                                            'low_value' => 'badge-light',
                                        ];
                                    @endphp
                                    <span class="badge {{ $badges[$type] ?? 'badge-secondary' }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
                                </td>
                                <td class="text-center">{{ $data['count'] }}</td>
                                <td class="text-right">₦{{ number_format($data['rou_asset'], 2) }}</td>
                                <td class="text-right">₦{{ number_format($data['liability'], 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No active leases</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th>Total</th>
                                <th class="text-center">{{ $activeLeases->count() }}</th>
                                <th class="text-right">₦{{ number_format($summary['total_rou_assets'], 2) }}</th>
                                <th class="text-right">₦{{ number_format($summary['total_lease_liabilities'], 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Active Leases Detail -->
        <div class="card-modern mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Active Leases Detail</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th>Lease #</th>
                                <th>Leased Item</th>
                                <th>Type</th>
                                <th>Commencement</th>
                                <th>End Date</th>
                                <th class="text-right">Monthly Payment</th>
                                <th class="text-right">ROU Asset</th>
                                <th class="text-right">Lease Liability</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeLeases as $lease)
                            <tr>
                                <td>
                                    <a href="{{ route('accounting.leases.show', $lease->id) }}">{{ $lease->lease_number }}</a>
                                </td>
                                <td>{{ $lease->leased_item }}</td>
                                <td>
                                    @php
                                        $badges = [
                                            'operating' => 'badge-secondary',
                                            'finance' => 'badge-primary',
                                            'short_term' => 'badge-info',
                                            'low_value' => 'badge-light',
                                        ];
                                    @endphp
                                    <span class="badge {{ $badges[$lease->lease_type] ?? 'badge-secondary' }}">{{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}</span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($lease->commencement_date)->format('M d, Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</td>
                                <td class="text-right">₦{{ number_format($lease->monthly_payment, 2) }}</td>
                                <td class="text-right">₦{{ number_format($lease->current_rou_asset_value, 2) }}</td>
                                <td class="text-right">₦{{ number_format($lease->current_lease_liability, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No active leases found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Accounting Policies Note -->
        <div class="card-modern card-modern">
            <div class="card-header">
                <h6 class="mb-0"><i class="mdi mdi-book-open mr-2"></i>Accounting Policy Note</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <strong>Leases (IFRS 16)</strong><br>
                    At inception of a contract, the entity assesses whether the contract is, or contains, a lease. A contract is, or contains,
                    a lease if the contract conveys the right to control the use of an identified asset for a period of time in exchange for consideration.
                </p>
                <p class="text-muted small mb-0">
                    The entity recognizes a right-of-use asset and a lease liability at the lease commencement date. The right-of-use asset is initially
                    measured at cost, which comprises the initial amount of the lease liability adjusted for any lease payments made at or before the
                    commencement date, plus any initial direct costs incurred and an estimate of costs to dismantle and remove the underlying asset
                    or to restore the underlying asset or the site on which it is located, less any lease incentives received.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .btn, .breadcrumb, nav, .sidebar, .navbar, footer { display: none !important; }
        .card { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .table-responsive { overflow: visible !important; }
        body { font-size: 12px; }
    }
</style>
@endpush

{{--
    Cost Center Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cost Center: ' . $costCenter->code)
@section('page_name', 'Accounting')
@section('subpage_name', 'Cost Center Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => route('accounting.cost-centers.index'), 'icon' => 'mdi-sitemap'],
        ['label' => $costCenter->code, 'url' => '#', 'icon' => 'mdi-eye']
    ]
])

<style>
.center-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}
.center-header h3 { margin: 0; font-weight: 600; }
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
    height: calc(100% - 20px);
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.stat-box {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
}
.stat-box.revenue { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); }
.stat-box.expense { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); }
.stat-box.budget { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); }
.stat-box .amount { font-size: 1.5rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.85rem; }
.info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f1f1; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #6c757d; }
.info-row .value { font-weight: 500; color: #333; }
.budget-progress { margin-top: 15px; }
.budget-progress .progress { height: 20px; border-radius: 10px; }
.budget-progress .progress-bar { border-radius: 10px; }
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.balance-card .amount { font-size: 1.8rem; font-weight: 700; }
.balance-card .label { opacity: 0.8; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="center-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    @php
                        $typeIcons = [
                            'revenue' => 'mdi-cash-multiple',
                            'cost' => 'mdi-cash-minus',
                            'service' => 'mdi-cog-outline',
                            'project' => 'mdi-briefcase-outline',
                        ];
                        $typeIcon = $typeIcons[$costCenter->center_type] ?? 'mdi-sitemap';
                    @endphp
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mr-3"
                         style="width: 60px; height: 60px;">
                        <i class="mdi {{ $typeIcon }}" style="font-size: 1.8rem; color: #667eea;"></i>
                    </div>
                    <div>
                        <h3>{{ $costCenter->name }}</h3>
                        <div>
                            <span class="badge badge-light mr-2">{{ $costCenter->code }}</span>
                            <span class="badge badge-{{ $costCenter->center_type == 'revenue' ? 'success' : ($costCenter->center_type == 'cost' ? 'info' : ($costCenter->center_type == 'project' ? 'warning' : 'secondary')) }}">
                                {{ ucfirst($costCenter->center_type) }} Center
                            </span>
                            @if($costCenter->is_active)
                                <span class="badge badge-light ml-1">Active</span>
                            @else
                                <span class="badge badge-dark ml-1">Inactive</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('accounting.cost-centers.edit', $costCenter->id) }}" class="btn btn-light btn-sm">
                    <i class="mdi mdi-pencil"></i> Edit
                </a>
                <a href="{{ route('accounting.cost-centers.report', $costCenter->id) }}" class="btn btn-light btn-sm">
                    <i class="mdi mdi-chart-bar"></i> Report
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Period Stats -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="stat-box revenue">
                        <div class="amount">₦{{ number_format($periodData['mtd_revenue'], 2) }}</div>
                        <div class="label">MTD Revenue</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-box expense">
                        <div class="amount">₦{{ number_format($periodData['mtd_expenses'], 2) }}</div>
                        <div class="label">MTD Expenses</div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="stat-box budget">
                        <div class="amount">₦{{ number_format($periodData['budget'] ?? 0, 2) }}</div>
                        <div class="label">{{ date('Y') }} Budget</div>
                    </div>
                </div>
            </div>

            <!-- YTD Stats -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="info-card">
                        <h6><i class="mdi mdi-cash-plus mr-2 text-success"></i>Year-to-Date Revenue</h6>
                        <div class="text-center">
                            <h2 class="text-success mb-0">₦{{ number_format($periodData['ytd_revenue'], 2) }}</h2>
                            <small class="text-muted">From {{ $periodData['transaction_count'] ?? 0 }} transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="info-card">
                        <h6><i class="mdi mdi-cash-minus mr-2 text-danger"></i>Year-to-Date Expenses</h6>
                        <div class="text-center">
                            <h2 class="text-danger mb-0">₦{{ number_format($periodData['ytd_expenses'], 2) }}</h2>
                            @if($periodData['budget'] > 0)
                                @php
                                    $utilization = ($periodData['ytd_expenses'] / $periodData['budget']) * 100;
                                @endphp
                                <small class="text-muted">{{ number_format($utilization, 1) }}% of budget used</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Utilization -->
            @if($periodData['budget'] > 0)
            <div class="info-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>Budget Utilization</h6>
                @php
                    $utilization = ($periodData['ytd_expenses'] / $periodData['budget']) * 100;
                    $remaining = $periodData['budget'] - $periodData['ytd_expenses'];
                    $progressColor = $utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'success');
                @endphp
                <div class="budget-progress">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Used: ₦{{ number_format($periodData['ytd_expenses'], 2) }}</span>
                        <span>Budget: ₦{{ number_format($periodData['budget'], 2) }}</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-{{ $progressColor }}" style="width: {{ min($utilization, 100) }}%">
                            {{ number_format($utilization, 1) }}%
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        @if($remaining >= 0)
                            <span class="text-success">₦{{ number_format($remaining, 2) }} remaining</span>
                        @else
                            <span class="text-danger">₦{{ number_format(abs($remaining), 2) }} over budget</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            <!-- Recent Transactions -->
            <div class="info-card">
                <h6><i class="mdi mdi-history mr-2"></i>Recent Transactions</h6>
                @if(count($recentTransactions) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>JE #</th>
                                    <th>Description</th>
                                    <th>Account</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentTransactions as $txn)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($txn->journalEntry->entry_date)->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('accounting.journal-entries.show', $txn->journal_entry_id) }}">
                                                {{ $txn->journalEntry->entry_number }}
                                            </a>
                                        </td>
                                        <td>{{ Str::limit($txn->description ?? $txn->journalEntry->description, 30) }}</td>
                                        <td>{{ $txn->account->code ?? 'N/A' }} - {{ Str::limit($txn->account->name ?? 'N/A', 20) }}</td>
                                        <td class="text-right">{{ $txn->debit > 0 ? number_format($txn->debit, 2) : '-' }}</td>
                                        <td class="text-right">{{ $txn->credit > 0 ? number_format($txn->credit, 2) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="{{ route('accounting.cost-centers.report', $costCenter->id) }}" class="btn btn-outline-primary btn-sm">
                            View All Transactions
                        </a>
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="mdi mdi-file-document-outline text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No transactions yet</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Net Balance -->
            @php
                $netBalance = $periodData['ytd_revenue'] - $periodData['ytd_expenses'];
            @endphp
            <div class="balance-card" style="background: linear-gradient(135deg, {{ $netBalance >= 0 ? '#28a745' : '#dc3545' }} 0%, {{ $netBalance >= 0 ? '#20c997' : '#c82333' }} 100%);">
                <div class="label">YTD Net Balance</div>
                <div class="amount">₦{{ number_format(abs($netBalance), 2) }}</div>
                <div class="label">{{ $netBalance >= 0 ? 'Surplus' : 'Deficit' }}</div>
            </div>

            <!-- Organization Info -->
            <div class="info-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Details</h6>
                <div class="info-row">
                    <span class="label">Code</span>
                    <span class="value">{{ $costCenter->code }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Type</span>
                    <span class="value">{{ ucfirst($costCenter->center_type) }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Department</span>
                    <span class="value">{{ $costCenter->department->name ?? 'Not Assigned' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Manager</span>
                    <span class="value">{{ $costCenter->manager->name ?? 'Not Assigned' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value">
                        @if($costCenter->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </span>
                </div>
                @if($costCenter->description)
                    <div class="mt-3">
                        <small class="text-muted">{{ $costCenter->description }}</small>
                    </div>
                @endif
            </div>

            <!-- Hierarchy -->
            @if($costCenter->parent || $costCenter->children->count() > 0)
            <div class="info-card">
                <h6><i class="mdi mdi-family-tree mr-2"></i>Hierarchy</h6>
                @if($costCenter->parent)
                    <div class="mb-3">
                        <small class="text-muted">Parent</small><br>
                        <a href="{{ route('accounting.cost-centers.show', $costCenter->parent->id) }}">
                            <i class="mdi mdi-arrow-up"></i> {{ $costCenter->parent->code }} - {{ $costCenter->parent->name }}
                        </a>
                    </div>
                @endif
                @if($costCenter->children->count() > 0)
                    <div>
                        <small class="text-muted">Children ({{ $costCenter->children->count() }})</small><br>
                        @foreach($costCenter->children as $child)
                            <a href="{{ route('accounting.cost-centers.show', $child->id) }}" class="d-block py-1">
                                <i class="mdi mdi-arrow-down"></i> {{ $child->code }} - {{ $child->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

            <!-- Budget History -->
            @if($costCenter->budgets->count() > 0)
            <div class="info-card">
                <h6><i class="mdi mdi-calendar-month mr-2"></i>Budget History</h6>
                @foreach($costCenter->budgets->sortByDesc('fiscal_year')->take(3) as $budget)
                    <div class="info-row">
                        <span class="label">{{ $budget->fiscal_year }}</span>
                        <span class="value">₦{{ number_format($budget->amount, 2) }}</span>
                    </div>
                @endforeach
                <div class="text-center mt-3">
                    <a href="{{ route('accounting.cost-centers.budgets', $costCenter->id) }}" class="btn btn-outline-primary btn-sm btn-block">
                        Manage Budgets
                    </a>
                </div>
            </div>
            @endif

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <a href="{{ route('accounting.cost-centers.edit', $costCenter->id) }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-pencil mr-1"></i> Edit Details
                </a>
                <a href="{{ route('accounting.cost-centers.report', $costCenter->id) }}" class="btn btn-outline-info btn-block btn-sm mb-2">
                    <i class="mdi mdi-chart-bar mr-1"></i> View Report
                </a>
                <a href="{{ route('accounting.cost-centers.budgets', $costCenter->id) }}" class="btn btn-outline-success btn-block btn-sm mb-2">
                    <i class="mdi mdi-cash mr-1"></i> Manage Budgets
                </a>
                <a href="{{ route('accounting.cost-centers.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

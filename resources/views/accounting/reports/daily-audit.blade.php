@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Daily Audit Trail')

@section('content')
<div class="container-fluid">
    {{-- Header with Title --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Daily Audit Trail</h4>
            <p class="text-muted mb-0">All accounting transactions for a specific day</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.daily-audit', ['export' => 'pdf', 'date' => $date->format('Y-m-d')]) }}" class="btn btn-danger">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
        </div>
    </div>

    {{-- Date Selection Card --}}
    <div class="card card-modern mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="mdi mdi-calendar mr-2"></i>Select Date</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.daily-audit') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Audit Date</label>
                        <input type="date" name="date" class="form-control" value="{{ $date->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-magnify mr-1"></i> Load Transactions
                        </button>
                    </div>
                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                        <div class="btn-group">
                            <a href="{{ route('accounting.reports.daily-audit', ['date' => $date->subDay()->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                                <i class="mdi mdi-chevron-left"></i> Previous Day
                            </a>
                            <a href="{{ route('accounting.reports.daily-audit', ['date' => now()->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                                Today
                            </a>
                            <a href="{{ route('accounting.reports.daily-audit', ['date' => $date->addDay()->format('Y-m-d')]) }}" class="btn btn-outline-secondary">
                                Next Day <i class="mdi mdi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-light mr-3">
                            <i class="mdi mdi-file-document-multiple text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Entries</h6>
                            <h4 class="mb-0">{{ $stats['total_entries'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-light mr-3">
                            <i class="mdi mdi-check-circle text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Posted</h6>
                            <h4 class="mb-0">{{ $stats['posted_entries'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info-light mr-3">
                            <i class="mdi mdi-calculator text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Debits</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_debits'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-light mr-3">
                            <i class="mdi mdi-calculator-variant text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Credits</h6>
                            <h4 class="mb-0">₦{{ number_format($stats['total_credits'], 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Activity Breakdown --}}
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card card-modern h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i>By Entry Type</h5>
                </div>
                <div class="card-body">
                    @forelse($stats['by_type'] as $type => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-{{ $type === 'manual' ? 'info' : ($type === 'automated' ? 'secondary' : 'warning') }}">
                            {{ ucfirst($type) }}
                        </span>
                        <span class="font-weight-bold">{{ $count }} entries</span>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No entries for this date</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-modern h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="mdi mdi-account-multiple mr-2"></i>By User</h5>
                </div>
                <div class="card-body">
                    @forelse($stats['by_user'] as $user => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>{{ $user }}</span>
                        <span class="font-weight-bold">{{ $count }} entries</span>
                    </div>
                    @empty
                    <p class="text-muted mb-0">No entries for this date</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Transactions Table --}}
    <div class="card card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>Transaction Details</h5>
            <span class="badge badge-info">{{ $date->format('l, M d, Y') }}</span>
        </div>
        <div class="card-body">
            @forelse($entries as $entry)
            <div class="audit-entry mb-4 pb-4 border-bottom">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <a href="{{ route('accounting.journal-entries.show', $entry->id) }}" class="h6 mb-0">
                            <code>{{ $entry->entry_number }}</code>
                        </a>
                        <span class="badge badge-{{ $entry->status === 'posted' ? 'success' : ($entry->status === 'pending' ? 'warning' : 'secondary') }} ml-2">
                            {{ ucfirst($entry->status) }}
                        </span>
                        <span class="badge badge-outline-info ml-1">{{ ucfirst($entry->entry_type) }}</span>
                    </div>
                    <div class="text-right text-muted small">
                        <div>Created: {{ $entry->created_at->format('h:i A') }} by {{ $entry->createdBy?->name ?? 'System' }}</div>
                        @if($entry->posted_at)
                        <div>Posted: {{ $entry->posted_at->format('h:i A') }} by {{ $entry->postedBy?->name ?? 'System' }}</div>
                        @endif
                    </div>
                </div>
                <p class="mb-2"><strong>Description:</strong> {{ $entry->description }}</p>
                @if($entry->reference)
                <p class="mb-2 small text-muted"><strong>Reference:</strong> {{ $entry->reference }}</p>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Account</th>
                                <th class="text-right" width="150">Debit</th>
                                <th class="text-right" width="150">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entry->lines as $line)
                            <tr>
                                <td>
                                    <code class="mr-2">{{ $line->account->account_code ?? 'N/A' }}</code>
                                    {{ $line->account->name ?? 'Unknown Account' }}
                                    @if($line->description)
                                    <br><small class="text-muted">{{ $line->description }}</small>
                                    @endif
                                </td>
                                <td class="text-right">{{ $line->debit_amount > 0 ? number_format($line->debit_amount, 2) : '' }}</td>
                                <td class="text-right">{{ $line->credit_amount > 0 ? number_format($line->credit_amount, 2) : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td>Totals</td>
                                <td class="text-right">{{ number_format($entry->lines->sum('debit_amount'), 2) }}</td>
                                <td class="text-right">{{ number_format($entry->lines->sum('credit_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @empty
            <div class="text-center py-5">
                <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                <p class="text-muted mt-2 mb-0">No journal entries found for {{ $date->format('M d, Y') }}</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-icon i {
        font-size: 24px;
    }

    .bg-primary-light { background-color: rgba(0, 123, 255, 0.1); }
    .bg-success-light { background-color: rgba(40, 167, 69, 0.1); }
    .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }

    .audit-entry:last-child {
        border-bottom: none !important;
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    .badge-outline-info {
        background-color: transparent;
        border: 1px solid #17a2b8;
        color: #17a2b8;
    }
</style>
@endpush

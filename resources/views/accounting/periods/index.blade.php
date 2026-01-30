@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Fiscal Periods')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Fiscal Periods', 'url' => route('accounting.periods'), 'icon' => 'mdi-calendar-range']
]])

<div class="container-fluid">
    {{-- Header with Title and Action Button --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Fiscal Periods Management</h4>
            <p class="text-muted mb-0">Manage fiscal years and accounting periods</p>
        </div>
        @can('periods.create')
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newFiscalYearModal">
                <i class="mdi mdi-plus mr-1"></i> New Fiscal Year
            </button>
        </div>
        @endcan
    </div>

    {{-- Stats Summary --}}
    <div class="row mb-4">
        @php
            $activeYear = $fiscalYears->where('is_active', true)->first();
            $currentPeriod = $activeYear ? $activeYear->periods->first(function($p) {
                return $p->start_date <= now() && $p->end_date >= now() && !$p->is_closed;
            }) : null;
            $totalYears = $fiscalYears->count();
            $openPeriods = $fiscalYears->flatMap->periods->where('is_closed', false)->count();
        @endphp
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Active Year</h6>
                            <h4 class="mb-0">{{ $activeYear ? $activeYear->name : 'None' }}</h4>
                        </div>
                        <div class="stat-icon bg-primary-light">
                            <i class="mdi mdi-calendar-star text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Current Period</h6>
                            <h4 class="mb-0">{{ $currentPeriod ? $currentPeriod->name : 'None' }}</h4>
                        </div>
                        <div class="stat-icon bg-success-light">
                            <i class="mdi mdi-calendar-check text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Fiscal Years</h6>
                            <h4 class="mb-0">{{ $totalYears }}</h4>
                        </div>
                        <div class="stat-icon bg-info-light">
                            <i class="mdi mdi-calendar-multiple text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Open Periods</h6>
                            <h4 class="mb-0">{{ $openPeriods }}</h4>
                        </div>
                        <div class="stat-icon bg-warning-light">
                            <i class="mdi mdi-calendar-clock text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fiscal Years List --}}
    @forelse($fiscalYears as $year)
        <div class="card card-modern mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">
                        <i class="mdi mdi-calendar-range mr-2"></i>{{ $year->name }}
                        @if($year->is_active)
                            <span class="badge badge-success ml-2">Active</span>
                        @endif
                        @if($year->is_closed)
                            <span class="badge badge-secondary ml-2">Closed</span>
                        @endif
                    </h5>
                    <small class="text-muted">
                        {{ $year->start_date->format('M d, Y') }} - {{ $year->end_date->format('M d, Y') }}
                        <span class="mx-2">|</span>
                        {{ $year->periods->count() }} periods
                    </small>
                </div>
                <div class="btn-group">
                    @if(!$year->is_active && !$year->is_closed)
                        <button type="button" class="btn btn-sm btn-outline-success btn-set-active"
                                data-id="{{ $year->id }}" data-name="{{ $year->name }}">
                            <i class="mdi mdi-check mr-1"></i> Set Active
                        </button>
                    @endif
                    @can('periods.close')
                    @if(!$year->is_closed)
                        <button type="button" class="btn btn-sm btn-outline-warning btn-close-year"
                                data-id="{{ $year->id }}" data-name="{{ $year->name }}">
                            <i class="mdi mdi-lock mr-1"></i> Close Year
                        </button>
                    @endif
                    @endcan
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th width="200">Period</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Entries</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($year->periods->sortBy('start_date') as $period)
                                @php
                                    $isCurrent = $period->start_date <= now() && $period->end_date >= now() && !$period->is_closed;
                                    $isPast = $period->end_date < now();
                                    $isFuture = $period->start_date > now();
                                @endphp
                                <tr class="{{ $isCurrent ? 'table-success' : '' }}">
                                    <td>
                                        <strong>{{ $period->name }}</strong>
                                        @if($isCurrent)
                                            <span class="badge badge-success ml-1">Current</span>
                                        @endif
                                    </td>
                                    <td>{{ $period->start_date->format('M d, Y') }}</td>
                                    <td>{{ $period->end_date->format('M d, Y') }}</td>
                                    <td class="text-center">
                                        @if($period->is_closed)
                                            <span class="badge badge-secondary">
                                                <i class="mdi mdi-lock mr-1"></i>Closed
                                            </span>
                                        @elseif($isFuture)
                                            <span class="badge badge-info">
                                                <i class="mdi mdi-clock-outline mr-1"></i>Future
                                            </span>
                                        @elseif($isPast)
                                            <span class="badge badge-warning">
                                                <i class="mdi mdi-alert-outline mr-1"></i>Past (Open)
                                            </span>
                                        @else
                                            <span class="badge badge-success">
                                                <i class="mdi mdi-check mr-1"></i>Open
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $entryCount = \App\Models\Accounting\JournalEntry::whereBetween('entry_date', [$period->start_date, $period->end_date])->count();
                                        @endphp
                                        <a href="{{ route('accounting.journal-entries.index', ['period_id' => $period->id]) }}"
                                           class="badge badge-light">
                                            {{ $entryCount }} entries
                                        </a>
                                    </td>
                                    <td>
                                        @can('periods.close')
                                        @if(!$period->is_closed && !$year->is_closed)
                                            <button type="button" class="btn btn-sm btn-outline-warning btn-close-period"
                                                    data-id="{{ $period->id }}" data-name="{{ $period->name }}">
                                                <i class="mdi mdi-lock"></i>
                                            </button>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card card-modern">
            <div class="card-body text-center py-5">
                <i class="mdi mdi-calendar-remove mdi-48px text-muted mb-3"></i>
                <h5>No Fiscal Years Configured</h5>
                <p class="text-muted">Create a fiscal year to start tracking accounting periods.</p>
                @can('periods.create')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newFiscalYearModal">
                    <i class="mdi mdi-plus mr-1"></i> Create Fiscal Year
                </button>
                @endcan
            </div>
        </div>
    @endforelse
</div>

{{-- New Fiscal Year Modal --}}
<div class="modal fade" id="newFiscalYearModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="newFiscalYearForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="mdi mdi-calendar-plus mr-2"></i>Create New Fiscal Year</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Fiscal Year Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="e.g., FY 2025" value="FY {{ now()->year }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required
                                       value="{{ now()->startOfYear()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required
                                       value="{{ now()->endOfYear()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="mdi mdi-information mr-2"></i>
                        Monthly accounting periods will be automatically created for this fiscal year.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-check mr-1"></i>Create Fiscal Year
                    </button>
                </div>
            </form>
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
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); }
    .bg-info-light { background-color: rgba(23, 162, 184, 0.1); }

    .table-success {
        background-color: rgba(40, 167, 69, 0.1) !important;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Create Fiscal Year
    $('#newFiscalYearForm').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            _token: '{{ csrf_token() }}',
            name: $('input[name="name"]').val(),
            start_date: $('input[name="start_date"]').val(),
            end_date: $('input[name="end_date"]').val()
        };

        $.post('{{ route("accounting.fiscal-years.store") }}', formData)
        .done(function(response) {
            toastr.success('Fiscal year created successfully');
            $('#newFiscalYearModal').modal('hide');
            location.reload();
        })
        .fail(function(xhr) {
            var errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(function(msgs) {
                    msgs.forEach(function(msg) {
                        toastr.error(msg);
                    });
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Error creating fiscal year');
            }
        });
    });

    // Close Period
    $(document).on('click', '.btn-close-period', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        if (!confirm('Close period "' + name + '"?\n\nJournal entries can no longer be posted to closed periods.')) {
            return;
        }

        $.post('{{ url("accounting/periods") }}/' + id + '/close', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            toastr.success('Period closed successfully');
            location.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error closing period');
        });
    });

    // Close Fiscal Year
    $(document).on('click', '.btn-close-year', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        if (!confirm('Close fiscal year "' + name + '"?\n\nThis will:\n- Close all periods in this year\n- Transfer net income to retained earnings\n- This action cannot be undone.')) {
            return;
        }

        $.post('{{ url("accounting/fiscal-years") }}/' + id + '/close', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            toastr.success('Fiscal year closed successfully');
            location.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error closing fiscal year');
        });
    });

    // Set Active Year
    $(document).on('click', '.btn-set-active', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');

        if (!confirm('Set "' + name + '" as the active fiscal year?')) {
            return;
        }

        $.post('{{ url("accounting/fiscal-years") }}/' + id + '/set-active', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            toastr.success('Active fiscal year updated');
            location.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error setting active year');
        });
    });
});
</script>
@endpush

@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('title', 'Cost Allocations')

@section('styles')
<link href="{{ asset('plugins/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('plugins/flatpickr/flatpickr.min.css') }}" rel="stylesheet" />
<style>
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .stat-card.success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }
    .stat-card.info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
    }
    .stat-label {
        opacity: 0.8;
        font-size: 0.9rem;
    }
    .allocation-card {
        border-left: 4px solid #667eea;
        transition: all 0.3s ease;
    }
    .allocation-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .allocation-flow {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
    }
    .allocation-flow .arrow {
        font-size: 1.5rem;
        color: #667eea;
        margin: 0 15px;
    }
    .cost-center-badge {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px 15px;
        text-align: center;
    }
    .cost-center-badge .code {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .cost-center-badge .name {
        font-weight: 600;
        color: #343a40;
    }
</style>
@endsection

@section('content')
@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
<div class="container-fluid">
    @include('accounting.partials.breadcrumb', [
        'items' => [
            ['name' => 'Cost Centers', 'url' => route('accounting.cost-centers.index')],
            ['name' => 'Allocations', 'url' => null]
        ]
    ])

    @if($stats['total_allocations'] === 0 && $recentAllocations->isEmpty())
    <div class="alert alert-info alert-dismissible fade show">
        <i class="mdi mdi-information mr-2"></i>
        <strong>Feature Coming Soon:</strong> The cost allocations feature is currently being set up. The database table will be created in the next system update.
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    @endif

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number">{{ number_format($stats['total_allocations']) }}</div>
                <div class="stat-label">Total Allocations</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card success">
                <div class="stat-number">{{ number_format($stats['active_cost_centers']) }}</div>
                <div class="stat-label">Active Cost Centers</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card info">
                <div class="stat-number">₦{{ number_format($stats['this_month'], 2) }}</div>
                <div class="stat-label">Allocated This Month</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- New Allocation Form -->
        <div class="col-lg-5">
            <div class="card card-modern">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="mdi mdi-plus-circle text-primary me-2"></i>
                        New Cost Allocation
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('accounting.cost-centers.allocations.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Source Cost Center <span class="text-danger">*</span></label>
                            <select name="source_cost_center_id" class="form-select select2" required>
                                <option value="">Select source...</option>
                                @foreach($costCenters as $costCenter)
                                    <option value="{{ $costCenter->id }}" {{ old('source_cost_center_id') == $costCenter->id ? 'selected' : '' }}>
                                        {{ $costCenter->code }} - {{ $costCenter->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_cost_center_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="text-center my-3">
                            <i class="mdi mdi-arrow-down-bold text-primary" style="font-size: 2rem;"></i>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Target Cost Center <span class="text-danger">*</span></label>
                            <select name="target_cost_center_id" class="form-select select2" required>
                                <option value="">Select target...</option>
                                @foreach($costCenters as $costCenter)
                                    <option value="{{ $costCenter->id }}" {{ old('target_cost_center_id') == $costCenter->id ? 'selected' : '' }}>
                                        {{ $costCenter->code }} - {{ $costCenter->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_cost_center_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₦</span>
                                        <input type="number" name="amount" class="form-control"
                                               step="0.01" min="0.01" value="{{ old('amount') }}" required>
                                    </div>
                                    @error('amount')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Allocation Date <span class="text-danger">*</span></label>
                                    <input type="date" name="allocation_date" class="form-control flatpickr"
                                           value="{{ old('allocation_date', date('Y-m-d')) }}" required>
                                    @error('allocation_date')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Allocation Basis <span class="text-danger">*</span></label>
                            <select name="allocation_basis" class="form-select" required>
                                <option value="">Select basis...</option>
                                <option value="headcount" {{ old('allocation_basis') == 'headcount' ? 'selected' : '' }}>Headcount</option>
                                <option value="square_footage" {{ old('allocation_basis') == 'square_footage' ? 'selected' : '' }}>Square Footage</option>
                                <option value="revenue" {{ old('allocation_basis') == 'revenue' ? 'selected' : '' }}>Revenue</option>
                                <option value="direct_usage" {{ old('allocation_basis') == 'direct_usage' ? 'selected' : '' }}>Direct Usage</option>
                                <option value="percentage" {{ old('allocation_basis') == 'percentage' ? 'selected' : '' }}>Fixed Percentage</option>
                                <option value="manual" {{ old('allocation_basis') == 'manual' ? 'selected' : '' }}>Manual/Other</option>
                            </select>
                            @error('allocation_basis')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="Describe the allocation purpose..." required>{{ old('description') }}</textarea>
                            @error('description')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="mdi mdi-check me-1"></i> Record Allocation
                        </button>
                    </form>
                </div>
            </div>

            <!-- Run Automatic Allocation -->
            <div class="card card-modern mt-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="mdi mdi-cog-sync text-warning me-2"></i>
                        Automatic Allocation
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Run automatic cost allocations based on predefined rules and allocation bases.
                    </p>
                    <form action="{{ route('accounting.cost-centers.allocations.run') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Period</label>
                                    <select name="period" class="form-select" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Allocation Date</label>
                                    <input type="date" name="allocation_date" class="form-control"
                                           value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="mdi mdi-play me-1"></i> Run Allocation
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Allocations -->
        <div class="col-lg-7">
            <div class="card card-modern">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="mdi mdi-history text-info me-2"></i>
                        Recent Allocations
                    </h5>
                    <span class="badge bg-info">{{ count($recentAllocations) }} records</span>
                </div>
                <div class="card-body p-0">
                    @if(count($recentAllocations) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th></th>
                                        <th>Target</th>
                                        <th class="text-end">Amount</th>
                                        <th>Basis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentAllocations as $allocation)
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($allocation->allocation_date)->format('d M Y') }}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $allocation->source_code }}</span>
                                                <br>
                                                <small>{{ Str::limit($allocation->source_name, 20) }}</small>
                                            </td>
                                            <td class="text-center">
                                                <i class="mdi mdi-arrow-right text-primary"></i>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $allocation->target_code }}</span>
                                                <br>
                                                <small>{{ Str::limit($allocation->target_name, 20) }}</small>
                                            </td>
                                            <td class="text-end fw-bold">
                                                ₦{{ number_format($allocation->amount, 2) }}
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $allocation->allocation_basis)) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="mdi mdi-folder-open-outline text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No allocations recorded yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Allocation Basis Guide -->
            <div class="card card-modern mt-3">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="mdi mdi-information-outline text-info me-2"></i>
                        Allocation Basis Guide
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Headcount</strong>
                                    <br><small class="text-muted">Based on number of employees</small>
                                </li>
                                <li class="mb-2">
                                    <strong>Square Footage</strong>
                                    <br><small class="text-muted">Based on occupied space</small>
                                </li>
                                <li class="mb-2">
                                    <strong>Revenue</strong>
                                    <br><small class="text-muted">Proportional to revenue generated</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong>Direct Usage</strong>
                                    <br><small class="text-muted">Based on actual consumption</small>
                                </li>
                                <li class="mb-2">
                                    <strong>Fixed Percentage</strong>
                                    <br><small class="text-muted">Predetermined allocation %</small>
                                </li>
                                <li class="mb-2">
                                    <strong>Manual</strong>
                                    <br><small class="text-muted">One-time or special allocations</small>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@else
    <div class="container-fluid">
        <div class="alert alert-danger">
            <i class="mdi mdi-alert-circle me-2"></i>
            You do not have permission to access this page.
        </div>
    </div>
@endhasanyrole
@endsection

@section('scripts')
<script src="{{ asset('plugins/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('plugins/flatpickr/flatpickr.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        width: '100%',
        placeholder: 'Select...'
    });

    // Initialize Flatpickr
    $('.flatpickr').flatpickr({
        dateFormat: 'Y-m-d',
        maxDate: 'today'
    });

    // Validate source != target
    $('form').on('submit', function(e) {
        var source = $('select[name="source_cost_center_id"]').val();
        var target = $('select[name="target_cost_center_id"]').val();

        if (source && target && source === target) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Selection',
                text: 'Source and target cost centers must be different.'
            });
        }
    });
});
</script>
@endsection

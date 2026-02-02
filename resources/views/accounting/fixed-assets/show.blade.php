{{--
    Fixed Asset Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', $fixedAsset->asset_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Asset Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Fixed Assets', 'url' => route('accounting.fixed-assets.index'), 'icon' => 'mdi-domain'],
        ['label' => $fixedAsset->asset_number, 'url' => '#', 'icon' => 'mdi-information']
    ]
])

<style>
.asset-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 10px;
    margin-bottom: 25px;
}
.asset-header .number {
    font-size: 1.3rem;
    font-weight: 600;
}
.asset-header .name {
    font-size: 1.8rem;
    font-weight: 700;
}
.asset-header .status-badge {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px dashed #eee;
}
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #666; }
.info-row .value { font-weight: 500; text-align: right; }
.book-value-card {
    text-align: center;
    padding: 25px;
    border-radius: 10px;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}
.book-value-card.fully-depreciated {
    background: linear-gradient(135deg, #434343 0%, #000000 100%);
}
.book-value-card.disposed {
    background: linear-gradient(135deg, #c31432 0%, #240b36 100%);
}
.book-value-card .amount {
    font-size: 2.5rem;
    font-weight: 700;
}
.depreciation-progress {
    height: 25px;
    border-radius: 15px;
    background: #e9ecef;
    overflow: hidden;
    margin-bottom: 10px;
}
.depreciation-progress .fill {
    height: 100%;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    transition: width 0.5s;
}
.je-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}
.je-line {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.je-line:last-child { border-bottom: none; }
.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 20px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 25px;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item:last-child::before { display: none; }
.timeline-dot {
    position: absolute;
    left: 0;
    top: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #667eea;
    border: 3px solid white;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="asset-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="number">{{ $fixedAsset->asset_number }}</div>
                <div class="name">{{ $fixedAsset->name }}</div>
                <div class="opacity-75 mt-2">
                    <i class="mdi mdi-folder-star mr-1"></i> {{ $fixedAsset->category?->name ?? 'Uncategorized' }}
                    @if($fixedAsset->department)
                        <span class="mx-2">•</span>
                        <i class="mdi mdi-domain mr-1"></i> {{ $fixedAsset->department->name }}
                    @endif
                </div>
            </div>
            <div class="col-md-4 text-md-right">
                @php
                    $statusColors = [
                        'active' => 'bg-success',
                        'fully_depreciated' => 'bg-info',
                        'disposed' => 'bg-secondary',
                        'impaired' => 'bg-warning text-dark',
                        'under_maintenance' => 'bg-primary',
                        'idle' => 'bg-dark',
                    ];
                @endphp
                <span class="status-badge {{ $statusColors[$fixedAsset->status] ?? 'bg-secondary' }}">
                    {{ ucfirst(str_replace('_', ' ', $fixedAsset->status)) }}
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Cost Information -->
            <div class="info-card">
                <h6><i class="mdi mdi-currency-ngn mr-2"></i>Cost Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Acquisition Cost</span>
                            <span class="value">₦{{ number_format($fixedAsset->acquisition_cost, 2) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Additional Costs</span>
                            <span class="value">₦{{ number_format($fixedAsset->additional_costs, 2) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label"><strong>Total Cost</strong></span>
                            <span class="value"><strong>₦{{ number_format($fixedAsset->total_cost, 2) }}</strong></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Salvage Value</span>
                            <span class="value">₦{{ number_format($fixedAsset->salvage_value, 2) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Depreciable Amount</span>
                            <span class="value">₦{{ number_format($fixedAsset->depreciable_amount, 2) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Accumulated Depreciation</span>
                            <span class="value text-warning">₦{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Depreciation Progress -->
                <div class="mt-4">
                    @php
                        $depPercent = $fixedAsset->depreciable_amount > 0
                            ? min(100, ($fixedAsset->accumulated_depreciation / $fixedAsset->depreciable_amount) * 100)
                            : 0;
                        $depColor = $depPercent > 80 ? 'danger' : ($depPercent > 50 ? 'warning' : 'success');
                    @endphp
                    <label>Depreciation Progress</label>
                    <div class="depreciation-progress">
                        <div class="fill bg-{{ $depColor }}" style="width: {{ $depPercent }}%">
                            @if($depPercent > 10)
                                {{ number_format($depPercent, 1) }}%
                            @endif
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Depreciated: ₦{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</small>
                        <small class="text-muted">Remaining: ₦{{ number_format($fixedAsset->depreciable_amount - $fixedAsset->accumulated_depreciation, 2) }}</small>
                    </div>
                </div>
            </div>

            <!-- Depreciation Settings -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-bell-curve mr-2"></i>Depreciation Settings</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Method</span>
                            <span class="value">{{ ucfirst(str_replace('_', ' ', $fixedAsset->depreciation_method)) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Useful Life</span>
                            <span class="value">{{ $fixedAsset->useful_life_years }} years ({{ $fixedAsset->useful_life_months }} months)</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Monthly Depreciation</span>
                            <span class="value">₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Last Depreciation</span>
                            <span class="value">{{ $fixedAsset->last_depreciation_date?->format('M d, Y') ?? 'Never' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Details -->
            <div class="info-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Asset Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Serial Number</span>
                            <span class="value">{{ $fixedAsset->serial_number ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Model Number</span>
                            <span class="value">{{ $fixedAsset->model_number ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Manufacturer</span>
                            <span class="value">{{ $fixedAsset->manufacturer ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Supplier</span>
                            <span class="value">{{ $fixedAsset->supplier?->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Location</span>
                            <span class="value">{{ $fixedAsset->location ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Department</span>
                            <span class="value">{{ $fixedAsset->department?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Custodian</span>
                            <span class="value">{{ $fixedAsset->custodian?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Invoice Number</span>
                            <span class="value">{{ $fixedAsset->invoice_number ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                @if($fixedAsset->description)
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Description:</strong> {{ $fixedAsset->description }}
                    </div>
                @endif
            </div>

            <!-- Dates -->
            <div class="info-card">
                <h6><i class="mdi mdi-calendar-clock mr-2"></i>Important Dates</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Acquisition Date</span>
                            <span class="value">{{ $fixedAsset->acquisition_date?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">In-Service Date</span>
                            <span class="value">{{ $fixedAsset->in_service_date?->format('M d, Y') ?? 'N/A' }}</span>
                        </div>
                        @if($fixedAsset->disposal_date)
                            <div class="info-row">
                                <span class="label">Disposal Date</span>
                                <span class="value text-danger">{{ $fixedAsset->disposal_date->format('M d, Y') }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <span class="label">Warranty Expiry</span>
                            <span class="value {{ $fixedAsset->warranty_expiry_date && $fixedAsset->warranty_expiry_date->isPast() ? 'text-danger' : '' }}">
                                {{ $fixedAsset->warranty_expiry_date?->format('M d, Y') ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">Insurance Expiry</span>
                            <span class="value {{ $fixedAsset->insurance_expiry_date && $fixedAsset->insurance_expiry_date->isPast() ? 'text-danger' : '' }}">
                                {{ $fixedAsset->insurance_expiry_date?->format('M d, Y') ?? 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Journal Entry -->
            @if($fixedAsset->journalEntry)
                <div class="info-card">
                    <h6><i class="mdi mdi-book-open mr-2"></i>Acquisition Journal Entry</h6>
                    <div class="je-preview">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>Entry #:</strong> {{ $fixedAsset->journalEntry->entry_number }}</span>
                            <span><strong>Date:</strong> {{ $fixedAsset->journalEntry->entry_date->format('M d, Y') }}</span>
                        </div>
                        <hr>
                        @foreach($fixedAsset->journalEntry->lines as $line)
                            <div class="je-line">
                                <div>
                                    <span class="badge badge-{{ $line->debit_amount > 0 ? 'primary' : 'success' }}">
                                        {{ $line->debit_amount > 0 ? 'DR' : 'CR' }}
                                    </span>
                                    {{ $line->account->display_name ?? $line->account->account_name }}
                                </div>
                                <div class="font-weight-bold">
                                    ₦{{ number_format($line->debit_amount ?: $line->credit_amount, 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Depreciation History -->
            @if($depreciationHistory->count() > 0)
                <div class="info-card">
                    <h6><i class="mdi mdi-history mr-2"></i>Depreciation History</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Book Value After</th>
                                <th>JE #</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($depreciationHistory as $dep)
                                <tr>
                                    <td>{{ $dep->depreciation_date->format('M d, Y') }}</td>
                                    <td>₦{{ number_format($dep->amount, 2) }}</td>
                                    <td>₦{{ number_format($dep->book_value_after, 2) }}</td>
                                    <td>
                                        @if($dep->journalEntry)
                                            <a href="{{ route('accounting.journal-entries.show', $dep->journalEntry->id) }}">
                                                {{ $dep->journalEntry->entry_number }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Book Value Card -->
            <div class="book-value-card {{ $fixedAsset->status === 'fully_depreciated' ? 'fully-depreciated' : ($fixedAsset->status === 'disposed' ? 'disposed' : '') }} mb-4">
                <div class="opacity-75">Net Book Value</div>
                <div class="amount">₦{{ number_format($fixedAsset->book_value, 2) }}</div>
                @if($fixedAsset->status === 'active')
                    <div class="opacity-75 mt-2">
                        <small>Next Depreciation: ₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}</small>
                    </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <div class="btn-group-vertical w-100">
                    <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-outline-secondary">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                    </a>

                    @if(!in_array($fixedAsset->status, ['disposed', 'fully_depreciated']))
                        <a href="{{ route('accounting.fixed-assets.edit', $fixedAsset) }}" class="btn btn-warning">
                            <i class="mdi mdi-pencil mr-1"></i> Edit Asset
                        </a>
                    @endif

                    @if($fixedAsset->status === 'active')
                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#disposeModal">
                            <i class="mdi mdi-delete mr-1"></i> Dispose Asset
                        </button>
                    @endif

                    <a href="javascript:window.print()" class="btn btn-outline-info">
                        <i class="mdi mdi-printer mr-1"></i> Print
                    </a>
                </div>
            </div>

            <!-- Warranty & Insurance Info -->
            <div class="info-card">
                <h6><i class="mdi mdi-shield-check mr-2"></i>Warranty & Insurance</h6>
                <div class="info-row">
                    <span class="label">Warranty Status</span>
                    <span class="value">
                        @if($fixedAsset->warranty_expiry_date)
                            @if($fixedAsset->warranty_expiry_date->isPast())
                                <span class="badge badge-danger">Expired</span>
                            @elseif($fixedAsset->warranty_expiry_date->diffInDays(now()) < 30)
                                <span class="badge badge-warning">Expiring Soon</span>
                            @else
                                <span class="badge badge-success">Active</span>
                            @endif
                        @else
                            <span class="badge badge-secondary">N/A</span>
                        @endif
                    </span>
                </div>
                @if($fixedAsset->warranty_provider)
                    <div class="info-row">
                        <span class="label">Provider</span>
                        <span class="value">{{ $fixedAsset->warranty_provider }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="label">Insurance Status</span>
                    <span class="value">
                        @if($fixedAsset->insurance_expiry_date)
                            @if($fixedAsset->insurance_expiry_date->isPast())
                                <span class="badge badge-danger">Expired</span>
                            @elseif($fixedAsset->insurance_expiry_date->diffInDays(now()) < 30)
                                <span class="badge badge-warning">Expiring Soon</span>
                            @else
                                <span class="badge badge-success">Active</span>
                            @endif
                        @else
                            <span class="badge badge-secondary">N/A</span>
                        @endif
                    </span>
                </div>
                @if($fixedAsset->insurance_policy_number)
                    <div class="info-row">
                        <span class="label">Policy #</span>
                        <span class="value">{{ $fixedAsset->insurance_policy_number }}</span>
                    </div>
                @endif
            </div>

            <!-- GL Accounts -->
            @if($fixedAsset->category)
                <div class="info-card">
                    <h6><i class="mdi mdi-file-tree mr-2"></i>GL Accounts</h6>
                    <div class="info-row">
                        <span class="label">Asset Account</span>
                        <span class="value">{{ $fixedAsset->category->assetAccount?->account_code ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Depreciation Account</span>
                        <span class="value">{{ $fixedAsset->category->depreciationAccount?->account_code ?? 'N/A' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Expense Account</span>
                        <span class="value">{{ $fixedAsset->category->expenseAccount?->account_code ?? 'N/A' }}</span>
                    </div>
                </div>
            @endif

            <!-- Notes -->
            @if($fixedAsset->notes)
                <div class="info-card">
                    <h6><i class="mdi mdi-note-text mr-2"></i>Notes</h6>
                    <p class="mb-0">{{ $fixedAsset->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Disposal Modal -->
@if($fixedAsset->status === 'active')
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete mr-2"></i>Dispose Asset</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="dispose-form">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert-circle mr-2"></i>
                        You are about to dispose <strong>{{ $fixedAsset->name }}</strong>.
                        Current book value: <strong>₦{{ number_format($fixedAsset->book_value, 2) }}</strong>
                    </div>
                    <div class="form-group">
                        <label>Disposal Date <span class="text-danger">*</span></label>
                        <input type="date" id="dispose-date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Disposal Type <span class="text-danger">*</span></label>
                        <select id="dispose-type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="sale">Sale</option>
                            <option value="scrapped">Scrapped</option>
                            <option value="donation">Donation</option>
                            <option value="transfer">Transfer to Another Entity</option>
                            <option value="write_off">Write Off</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Disposal Amount</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" id="dispose-amount" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        <small class="text-muted">Amount received from sale/transfer (if any)</small>
                    </div>
                    <div class="form-group">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea id="dispose-reason" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Buyer/Recipient Info</label>
                        <input type="text" id="dispose-buyer" class="form-control" placeholder="Name of buyer or recipient">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Dispose Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    @if($fixedAsset->status === 'active')
    $('#dispose-form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route('accounting.fixed-assets.dispose', $fixedAsset) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                disposal_date: $('#dispose-date').val(),
                disposal_type: $('#dispose-type').val(),
                disposal_amount: $('#dispose-amount').val() || 0,
                disposal_reason: $('#dispose-reason').val(),
                buyer_info: $('#dispose-buyer').val()
            },
            success: function(res) {
                toastr.success(res.message);
                setTimeout(function() {
                    location.reload();
                }, 1500);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to dispose asset');
            }
        });
    });
    @endif
});
</script>
@endpush

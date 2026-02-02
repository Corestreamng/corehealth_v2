{{--
    Fixed Assets Register
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Fixed Assets')
@section('page_name', 'Accounting')
@section('subpage_name', 'Fixed Assets')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Fixed Assets', 'url' => '#', 'icon' => 'mdi-domain']
    ]
])

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid;
}
.stat-card.primary { border-left-color: #667eea; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-card .value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #333;
}
.stat-card .label {
    color: #666;
    font-size: 0.85rem;
}
.filter-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.category-chip {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    margin: 3px;
    cursor: pointer;
}
.depreciation-bar {
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    overflow: hidden;
}
.depreciation-bar .fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s;
}
.alert-card {
    background: #fff3cd;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid #ffc107;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Stats Row -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card primary">
                <div class="value">{{ number_format($stats['total_assets']) }}</div>
                <div class="label">Total Assets</div>
                <small class="text-muted">Cost: ₦{{ number_format($stats['total_cost'], 0) }}</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card success">
                <div class="value">₦{{ number_format($stats['total_book_value'], 0) }}</div>
                <div class="label">Net Book Value</div>
                <small class="text-muted">After depreciation</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card warning">
                <div class="value">₦{{ number_format($stats['total_accum_depreciation'], 0) }}</div>
                <div class="label">Accumulated Depreciation</div>
                <small class="text-muted">YTD: ₦{{ number_format($stats['ytd_depreciation'], 0) }}</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card info">
                <div class="value">₦{{ number_format($stats['monthly_depreciation_due'], 0) }}</div>
                <div class="label">Monthly Depreciation</div>
                <small class="text-muted">Due this period</small>
            </div>
        </div>
    </div>

    <!-- Alerts Row -->
    <div class="row">
        @if($stats['warranty_expiring_soon'] > 0)
        <div class="col-md-6">
            <div class="alert-card">
                <i class="mdi mdi-shield-alert-outline text-warning mr-2"></i>
                <strong>{{ $stats['warranty_expiring_soon'] }}</strong> assets have warranties expiring in 30 days
            </div>
        </div>
        @endif
        @if($stats['insurance_expiring_soon'] > 0)
        <div class="col-md-6">
            <div class="alert-card">
                <i class="mdi mdi-shield-alert-outline text-warning mr-2"></i>
                <strong>{{ $stats['insurance_expiring_soon'] }}</strong> assets have insurance expiring in 30 days
            </div>
        </div>
        @endif
    </div>

    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="d-flex justify-content-between flex-wrap">
                <div>
                    <a href="{{ route('accounting.fixed-assets.create') }}" class="btn btn-primary">
                        <i class="mdi mdi-plus mr-1"></i> Add New Asset
                    </a>
                    <a href="{{ route('accounting.fixed-assets.categories.index') }}" class="btn btn-outline-secondary ml-2">
                        <i class="mdi mdi-folder-outline mr-1"></i> Categories
                    </a>
                    <button type="button" class="btn btn-outline-warning ml-2" data-toggle="modal" data-target="#depreciationModal">
                        <i class="mdi mdi-chart-bell-curve mr-1"></i> Run Depreciation
                    </button>
                </div>
                <div class="btn-group">
                    <a href="{{ route('accounting.fixed-assets.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn btn-danger">
                        <i class="mdi mdi-file-pdf mr-1"></i> PDF
                    </a>
                    <a href="{{ route('accounting.fixed-assets.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="btn btn-success">
                        <i class="mdi mdi-file-excel mr-1"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <div class="row">
            <div class="col-md-3">
                <label>Search</label>
                <input type="text" id="filter-search" class="form-control" placeholder="Asset #, Name, Serial...">
            </div>
            <div class="col-md-2">
                <label>Status</label>
                <select id="filter-status" class="form-control">
                    <option value="">All Status</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Category</label>
                <select id="filter-category" class="form-control">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Department</label>
                <select id="filter-department" class="form-control">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label>Acquisition Date</label>
                <div class="input-group">
                    <input type="date" id="filter-date-from" class="form-control">
                    <input type="date" id="filter-date-to" class="form-control">
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-12">
                <button type="button" id="btn-filter" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-filter mr-1"></i> Apply Filters
                </button>
                <button type="button" id="btn-clear" class="btn btn-outline-secondary btn-sm ml-2">
                    <i class="mdi mdi-close mr-1"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="card-modern">
        <div class="card-body">
            <table id="assets-table" class="table table-striped table-bordered w-100">
                <thead>
                    <tr>
                        <th>Asset Number</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Department</th>
                        <th>Total Cost</th>
                        <th>Book Value</th>
                        <th>Depreciation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header">
                    <h6 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i>Assets by Category</h6>
                </div>
                <div class="card-body">
                    @forelse($stats['by_category'] as $item)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>{{ $item->category?->name ?? 'Uncategorized' }}</span>
                            <span>
                                <span class="badge badge-primary">{{ $item->count }} assets</span>
                                <span class="badge badge-success">₦{{ number_format($item->value, 0) }}</span>
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No assets found</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header">
                    <h6 class="mb-0"><i class="mdi mdi-counter mr-2"></i>Status Summary</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><span class="badge badge-success mr-2">●</span> Active</span>
                        <span>{{ $stats['active_count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><span class="badge badge-info mr-2">●</span> Fully Depreciated</span>
                        <span>{{ $stats['fully_depreciated_count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><span class="badge badge-secondary mr-2">●</span> Disposed (YTD)</span>
                        <span>{{ $stats['ytd_disposals'] }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><strong>YTD Disposal Gain/(Loss)</strong></span>
                        <span class="{{ $stats['ytd_disposal_gain_loss'] >= 0 ? 'text-success' : 'text-danger' }}">
                            ₦{{ number_format($stats['ytd_disposal_gain_loss'], 2) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Depreciation Modal -->
<div class="modal fade" id="depreciationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-chart-bell-curve mr-2"></i>Run Monthly Depreciation</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="depreciation-form">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="mdi mdi-information-outline mr-2"></i>
                        This will calculate and record depreciation for all active assets for the selected period.
                    </div>
                    <div class="form-group">
                        <label>Depreciation Date <span class="text-danger">*</span></label>
                        <input type="date" id="depreciation-date" class="form-control"
                               value="{{ now()->format('Y-m-d') }}" required>
                        <small class="text-muted">Typically the last day of the month</small>
                    </div>
                    <div class="form-group">
                        <label>Category (Optional)</label>
                        <select id="depreciation-category" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                @if($cat->is_depreciable)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">Leave blank to depreciate all categories</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Run Depreciation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Disposal Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete mr-2"></i>Dispose Asset</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="dispose-form">
                <input type="hidden" id="dispose-asset-id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert-circle mr-2"></i>
                        Disposing <strong id="dispose-asset-name"></strong>. This action cannot be undone.
                    </div>
                    <div class="form-group">
                        <label>Disposal Date <span class="text-danger">*</span></label>
                        <input type="date" id="dispose-date" class="form-control"
                               value="{{ now()->format('Y-m-d') }}" required>
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
                    <div class="form-group" id="disposal-amount-row">
                        <label>Disposal Amount</label>
                        <input type="number" id="dispose-amount" class="form-control"
                               step="0.01" min="0" placeholder="0.00">
                        <small class="text-muted">Amount received (if any)</small>
                    </div>

                    <!-- Payment Source Section (shown when there's proceeds) -->
                    <div id="payment-source-section" style="display: none;">
                        <hr>
                        <h6 class="text-muted mb-3"><i class="mdi mdi-bank-transfer mr-1"></i>Payment Source</h6>
                        <div class="form-group">
                            <label>Received Via <span class="text-danger">*</span></label>
                            <select id="dispose-payment-method" class="form-control">
                                <option value="">Select Payment Source</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                            <small class="text-muted">Where did the disposal proceeds come into?</small>
                        </div>
                        <div class="form-group" id="dispose-bank-row" style="display: none;">
                            <label>Bank Account <span class="text-danger">*</span></label>
                            <select id="dispose-bank-id" class="form-control">
                                <option value="">Select Bank Account</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea id="dispose-reason" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="form-group" id="buyer-info-row" style="display: none;">
                        <label>Buyer Information</label>
                        <input type="text" id="dispose-buyer" class="form-control" placeholder="Buyer name/details">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Dispose Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#assets-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.fixed-assets.datatable') }}',
            data: function(d) {
                d.status = $('#filter-status').val();
                d.category_id = $('#filter-category').val();
                d.department_id = $('#filter-department').val();
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
                d.search_term = $('#filter-search').val();
            }
        },
        columns: [
            { data: 'asset_number', name: 'asset_number' },
            { data: 'name', name: 'name' },
            { data: 'category_name', name: 'category_name', orderable: false },
            { data: 'department_name', name: 'department_name', orderable: false },
            {
                data: 'total_cost',
                name: 'total_cost',
                render: function(data) {
                    return '₦' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            },
            {
                data: 'book_value',
                name: 'book_value',
                render: function(data) {
                    return '₦' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            },
            {
                data: 'depreciation_percent',
                name: 'depreciation_percent',
                orderable: false,
                render: function(data) {
                    var color = data > 80 ? 'danger' : (data > 50 ? 'warning' : 'success');
                    return '<div class="depreciation-bar"><div class="fill bg-' + color + '" style="width: ' + data + '%"></div></div><small>' + data + '%</small>';
                }
            },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Filter handlers
    $('#btn-filter').on('click', function() {
        table.draw();
    });

    $('#btn-clear').on('click', function() {
        $('#filter-search, #filter-date-from, #filter-date-to').val('');
        $('#filter-status, #filter-category, #filter-department').val('');
        table.draw();
    });

    // Enter key to filter
    $('#filter-search').on('keypress', function(e) {
        if (e.which === 13) table.draw();
    });

    // Depreciation form
    $('#depreciation-form').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: '{{ route('accounting.fixed-assets.depreciation.run') }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                depreciation_date: $('#depreciation-date').val(),
                category_id: $('#depreciation-category').val()
            },
            success: function(res) {
                $('#depreciationModal').modal('hide');
                toastr.success(res.message);
                table.draw();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to run depreciation');
            }
        });
    });

    // Show/hide buyer info based on disposal type
    $('#dispose-type').on('change', function() {
        var type = $(this).val();
        if (type === 'sale' || type === 'transfer') {
            $('#buyer-info-row').show();
        } else {
            $('#buyer-info-row').hide();
        }
    });

    // Show/hide payment source based on disposal amount
    $('#dispose-amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        if (amount > 0) {
            $('#payment-source-section').show();
        } else {
            $('#payment-source-section').hide();
            $('#dispose-payment-method').val('');
            $('#dispose-bank-id').val('');
            $('#dispose-bank-row').hide();
        }
    });

    // Show/hide bank selection based on payment method
    $('#dispose-payment-method').on('change', function() {
        var method = $(this).val();
        if (method === 'bank_transfer') {
            $('#dispose-bank-row').show();
        } else {
            $('#dispose-bank-row').hide();
            $('#dispose-bank-id').val('');
        }
    });

    // Reset disposal form when modal opens
    $(document).on('click', '.btn-dispose', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#dispose-asset-id').val(id);
        $('#dispose-asset-name').text(name);
        // Reset form fields
        $('#dispose-amount').val('');
        $('#dispose-reason').val('');
        $('#dispose-buyer').val('');
        $('#dispose-type').val('');
        $('#dispose-payment-method').val('');
        $('#dispose-bank-id').val('');
        $('#payment-source-section').hide();
        $('#dispose-bank-row').hide();
        $('#buyer-info-row').hide();
        $('#disposeModal').modal('show');
    });

    // Dispose form
    $('#dispose-form').on('submit', function(e) {
        e.preventDefault();
        var id = $('#dispose-asset-id').val();
        var disposalAmount = parseFloat($('#dispose-amount').val()) || 0;
        var paymentMethod = $('#dispose-payment-method').val();
        var bankId = $('#dispose-bank-id').val();

        // Validate payment source if there are proceeds
        if (disposalAmount > 0) {
            if (!paymentMethod) {
                toastr.error('Please select a payment source for the disposal proceeds');
                return;
            }
            if (paymentMethod === 'bank_transfer' && !bankId) {
                toastr.error('Please select a bank account');
                return;
            }
        }

        $.ajax({
            url: '{{ url('accounting/fixed-assets') }}/' + id + '/dispose',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                disposal_date: $('#dispose-date').val(),
                disposal_type: $('#dispose-type').val(),
                disposal_amount: disposalAmount,
                disposal_reason: $('#dispose-reason').val(),
                buyer_info: $('#dispose-buyer').val(),
                payment_method: paymentMethod || null,
                bank_id: bankId || null
            },
            success: function(res) {
                $('#disposeModal').modal('hide');
                toastr.success(res.message);
                table.draw();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to dispose asset');
            }
        });
    });
});
</script>
@endpush

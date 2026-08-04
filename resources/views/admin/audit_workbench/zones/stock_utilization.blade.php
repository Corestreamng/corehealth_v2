@extends('admin.audit_workbench.layout')

@section('styles')
<link href="{{ asset('assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .select2-container .select2-selection--single { height: 38px; line-height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
</style>
@endsection

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-clipboard-pulse"></i> Stock Utilization Audit</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export</button>
    </div>
</div>

@include('admin.audit_workbench.partials.datetime_filter')

<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <h5 class="mb-0 fs-14">Advanced Filters</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Store / Department</label>
                <select class="form-select select2" id="filter_store">
                    <option value="">All Stores</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select select2" id="filter_category">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Transaction Type</label>
                <select class="form-select" id="filter_type">
                    <option value="">All Types</option>
                    <option value="in">Stock In</option>
                    <option value="out">Stock Out (General)</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="expired">Expired</option>
                    <option value="damaged">Damaged</option>
                    <option value="adjustment">Manual Adjustment</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" id="filter_start" title="Start Date">
                    <input type="date" class="form-control" id="filter_end" title="End Date">
                </div>
            </div>
            <div class="col-md-12 text-end">
                <button class="btn btn-secondary btn-sm" id="btn_clear_filters"><i class="mdi mdi-refresh"></i> Clear Filters</button>
                <button class="btn btn-primary btn-sm" id="btn_apply_filters"><i class="mdi mdi-filter"></i> Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4" id="stats_container" style="display: none;">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success-subtle">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-success fw-medium mb-1">Total Stock In</p>
                        <h3 class="text-success mb-0" id="stat_in">0</h3>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success rounded-circle fs-3"><i class="mdi mdi-arrow-down-bold"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger-subtle">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-danger fw-medium mb-1">Total Stock Out/Dispensed</p>
                        <h3 class="text-danger mb-0" id="stat_out">0</h3>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-danger rounded-circle fs-3"><i class="mdi mdi-arrow-up-bold"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning-subtle">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-warning fw-medium mb-1">Unique Products Moved</p>
                        <h3 class="text-warning mb-0" id="stat_unique">0</h3>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning rounded-circle fs-3"><i class="mdi mdi-format-list-bulleted"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 w-100" id="stock_audit_table">
                <thead class="bg-light text-muted">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Store</th>
                        <th>Product Details</th>
                        <th>Batch & Expiry</th>
                        <th>Movement Type</th>
                        <th>Qty & Balance</th>
                        <th>Reference / Patient</th>
                        <th>Performer</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTables -->
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/libs/select2/js/select2.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });

        var table = $('#stock_audit_table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('audit.stock-utilization.data') }}",
                data: function (d) {
                    d.store_id = $('#filter_store').val();
                    d.category_id = $('#filter_category').val();
                    d.transaction_type = $('#filter_type').val();
                    d.start_date = $('#filter_start').val();
                    d.end_date = $('#filter_end').val();
                },
                dataSrc: function (json) {
                    // Update Stats
                    if(json.summary_stats) {
                        $('#stats_container').show();
                        $('#stat_in').text(json.summary_stats.total_in_formatted || json.summary_stats.total_in);
                        $('#stat_out').text(json.summary_stats.total_out_formatted || json.summary_stats.total_out);
                        $('#stat_unique').text(json.summary_stats.unique_products);
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'created_at', name: 'created_at', className: 'ps-4' },
                { data: 'store', name: 'store', orderable: false, searchable: false },
                { data: 'product', name: 'stockBatch.product.product_name' },
                { data: 'batch', name: 'stockBatch.batch_number' },
                { data: 'type', name: 'type' },
                { data: 'qty', name: 'qty', searchable: false },
                { data: 'reference', name: 'reference', orderable: false, searchable: false },
                { data: 'performer', name: 'performer.name', orderable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end pe-4' }
            ],
            order: [[0, 'desc']],
            language: {
                emptyTable: "No stock movements found matching your filters."
            }
        });

        $('#btn_apply_filters').on('click', function() {
            table.draw();
        });

        $('#btn_clear_filters').on('click', function() {
            $('#filter_store').val('').trigger('change');
            $('#filter_category').val('').trigger('change');
            $('#filter_type').val('');
            $('#filter_start').val('');
            $('#filter_end').val('');
            table.draw();
        });
    });
</script>
@endsection

@extends('admin.layouts.app')
@section('title', 'Product Details')
@section('page_name', 'Products')
@section('subpage_name', 'Product Details')
@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .product-header { background: linear-gradient(135deg, var(--primary-color) 0%, #1a3a5c 100%); color: #fff; border-radius: 8px 8px 0 0; padding: 30px 24px; text-align: center; }
        .product-header h3 { margin-bottom: 4px; }
        .product-header .code { opacity: .75; font-size: .9rem; }
        .type-badge-lg { display: inline-block; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: .8rem; }
        .type-badge-drug { background: #e8f5e9; color: #2e7d32; }
        .type-badge-consumable { background: #fff8e1; color: #f57f17; }
        .type-badge-utility { background: #e3f2fd; color: #1565c0; }
        .stat-card { background: #f8f9fa; border-radius: 8px; padding: 16px; text-align: center; }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .stat-label { font-size: .8rem; color: #6c757d; }
        .chain-arrow { display: inline-flex; align-items: center; margin: 0 4px; color: #adb5bd; }
        .chain-arrow i { font-size: 1.2rem; }
        .pkg-badge { display: inline-flex; align-items: center; padding: 6px 12px; background: #f1f3f5; border-radius: 6px; margin: 4px; }
        .pkg-badge .pkg-name { font-weight: 600; margin-right: 8px; }
        .pkg-badge .pkg-detail { font-size: .8rem; color: #6c757d; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection
@section('content')
<div class="container-fluid">
    <div class="row">
        {{-- Left Sidebar — Product Profile --}}
        <div class="col-lg-3">
            <div class="card-modern mb-3">
                <div class="product-header">
                    @php
                        $typeClass = match($pp->product_type ?? 'drug') {
                            'drug' => 'type-badge-drug',
                            'consumable' => 'type-badge-consumable',
                            'utility' => 'type-badge-utility',
                            default => 'type-badge-drug',
                        };
                        $typeIcon = match($pp->product_type ?? 'drug') {
                            'drug' => 'mdi-pill',
                            'consumable' => 'mdi-bandage',
                            'utility' => 'mdi-broom',
                            default => 'mdi-pill',
                        };
                    @endphp
                    <i class="mdi {{ $typeIcon }}" style="font-size: 3rem; opacity: .8;"></i>
                    <h3 class="mt-2">{{ $pp->product_name }}</h3>
                    <div class="code">{{ $pp->product_code }}</div>
                    <span class="type-badge-lg {{ $typeClass }} mt-2">{{ ucfirst($pp->product_type ?? 'drug') }}</span>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted"><i class="mdi mdi-folder-outline"></i> Category</td>
                            <td class="font-weight-bold text-right">{{ $pp->category->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="mdi mdi-cube-outline"></i> Base Unit</td>
                            <td class="font-weight-bold text-right">{{ $pp->base_unit_name ?? 'Piece' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="mdi mdi-decimal"></i> Decimals</td>
                            <td class="text-right">
                                @if($pp->allow_decimal_qty)
                                    <span class="badge badge-success">Allowed</span>
                                @else
                                    <span class="badge badge-secondary">Integer Only</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><i class="mdi mdi-alert-outline"></i> Reorder At</td>
                            <td class="font-weight-bold text-right">{{ number_format($pp->reorder_alert ?? 0) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="row mb-3">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value text-primary">{{ number_format($totalQty ?? 0) }}</div>
                        <div class="stat-label">In Stock ({{ $pp->base_unit_name ?? 'pcs' }})</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value text-success">&#x20A6;{{ number_format($pp->price->sale_price ?? 0, 2) }}</div>
                        <div class="stat-label">Sale Price</div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value text-info">{{ number_format($qt ?? 0) }}</div>
                        <div class="stat-label">Total Sold</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-value text-warning">&#x20A6;{{ number_format($pc ?? 0, 2) }}</div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card-modern mb-3">
                <div class="card-body p-3">
                    <a href="{{ route('products.edit', $pp->id) }}" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-pencil"></i> Edit Product
                    </a>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-arrow-left"></i> Back to Products
                    </a>
                </div>
            </div>
        </div>

        {{-- Right Content --}}
        <div class="col-lg-9">
            {{-- Packaging Breakdown --}}
            @if($pp->packagings && $pp->packagings->count())
            <div class="card-modern mb-3">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="mdi mdi-package-variant-closed text-primary"></i> Packaging Hierarchy
                    </h5>
                </div>
                <div class="card-body p-4">
                    {{-- Visual chain --}}
                    <div class="d-flex align-items-center flex-wrap mb-3">
                        <span class="pkg-badge" style="background: #e8f5e9">
                            <span class="pkg-name">{{ $pp->base_unit_name ?? 'Piece' }}</span>
                            <span class="pkg-detail">(base)</span>
                        </span>
                        @foreach($pp->packagings->sortBy('level') as $pkg)
                            <span class="chain-arrow"><i class="mdi mdi-chevron-right"></i> {{ $pkg->units_in_parent }} &rarr;</span>
                            <span class="pkg-badge">
                                <span class="pkg-name">{{ $pkg->name }}</span>
                                <span class="pkg-detail">= {{ number_format($pkg->base_unit_qty, $pkg->base_unit_qty == intval($pkg->base_unit_qty) ? 0 : 2) }} {{ $pp->base_unit_name ?? 'pcs' }}</span>
                            </span>
                        @endforeach
                    </div>

                    {{-- Detailed Table --}}
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Level</th>
                                <th>Packaging</th>
                                <th>Units in Parent</th>
                                <th>Base Unit Equivalent</th>
                                <th>Default Purchase</th>
                                <th>Default Dispense</th>
                                <th>Barcode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="background:#e8f5e9">
                                <td>0 (Base)</td>
                                <td class="font-weight-bold">{{ $pp->base_unit_name ?? 'Piece' }}</td>
                                <td>—</td>
                                <td>1</td>
                                <td>—</td>
                                <td>—</td>
                                <td>—</td>
                            </tr>
                            @foreach($pp->packagings->sortBy('level') as $pkg)
                            <tr>
                                <td>{{ $pkg->level }}</td>
                                <td class="font-weight-bold">{{ $pkg->name }}</td>
                                <td>{{ number_format($pkg->units_in_parent, $pkg->units_in_parent == intval($pkg->units_in_parent) ? 0 : 2) }}</td>
                                <td>{{ number_format($pkg->base_unit_qty, $pkg->base_unit_qty == intval($pkg->base_unit_qty) ? 0 : 2) }} {{ $pp->base_unit_name ?? 'pcs' }}</td>
                                <td>{!! $pkg->is_default_purchase ? '<span class="badge badge-primary">Yes</span>' : '—' !!}</td>
                                <td>{!! $pkg->is_default_dispense ? '<span class="badge badge-success">Yes</span>' : '—' !!}</td>
                                <td>{{ $pkg->barcode ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Stock in packaging --}}
                    @if($totalQty > 0)
                    <div class="mt-3 p-3" style="background: #f8f9fa; border-radius: 6px;">
                        <strong><i class="mdi mdi-package-variant"></i> Current Stock Breakdown:</strong>
                        <span class="ml-2">{{ $pp->formatQty($totalQty) }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Sales History DataTable --}}
            <div class="card-modern">
                <div class="card-header-modern">
                    <h5 class="card-title-modern">
                        <i class="mdi mdi-history text-primary"></i> Issue / Sales History
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table id="products-issue" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product</th>
                                    <th>SIV Number</th>
                                    <th>Client</th>
                                    <th>Qty</th>
                                    <th>Issued Price</th>
                                    <th>Total Amount</th>
                                    <th>Date</th>
                                    <th>Budget Year</th>
                                    <th>Store</th>
                                    <th>Voucher</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th colspan="5">Total Sale Amount: &#x20A6;{!! number_format($pc, 2, '.', ',') !!}</th>
                                    <th colspan="6">Total Sale Quantity: {!! $qt !!}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    $('#products-issue').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, 200, 500, -1], [10, 25, 50, 100, 200, 500, "All"]],
        "buttons": ['pageLength', 'copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5', 'print'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('listSalesProduct', $id) }}",
            "type": "GET"
        },
        "columns": [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "product", name: "product" },
            { data: "trans", name: "trans" },
            { data: "customer", name: "customer" },
            { data: "quantity_buy", name: "quantity_buy" },
            { data: "sale_price", name: "sale_price" },
            { data: "total_amount", name: "total_amount" },
            { data: "sale_date", name: "sale_date" },
            { data: "budgetYear", name: "budgetYear" },
            { data: "store", name: "store" },
            { data: "view", name: "view" }
        ],
        "paging": true
    });
});
</script>
@endsection

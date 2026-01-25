@extends('admin.layouts.app')
@section('title', 'Accounts Payable')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Accounts Payable')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .stats-card h3 {
        margin: 0;
        font-weight: 600;
    }
    .stat-item {
        text-align: center;
        padding: 1rem;
    }
    .stat-item .value {
        font-size: 1.75rem;
        font-weight: bold;
    }
    .stat-item .label {
        font-size: 0.85rem;
        opacity: 0.9;
    }
    .card-custom {
        border: none;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .card-custom .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }
    .card-custom .card-header h5 {
        margin: 0;
        font-weight: 600;
    }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0">Accounts Payable</h4>
                <small class="text-muted">Purchase orders awaiting payment</small>
            </div>
            <div>
                <a href="{{ route('inventory.store-workbench.index') }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Workbench
                </a>
                <a href="{{ route('inventory.purchase-orders.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="mdi mdi-clipboard-list"></i> All POs
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="stats-card">
            <div class="row">
                <div class="col-md-3 stat-item">
                    <div class="value" id="total-count">-</div>
                    <div class="label">Total POs Pending</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="value" id="total-amount">-</div>
                    <div class="label">Total Amount</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="value" id="total-paid">-</div>
                    <div class="label">Amount Paid</div>
                </div>
                <div class="col-md-3 stat-item">
                    <div class="value" id="total-balance">-</div>
                    <div class="label">Balance Due</div>
                </div>
            </div>
        </div>

        <!-- PO List -->
        <div class="card-modern card-custom">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col">
                        <h5><i class="mdi mdi-currency-ngn mr-2"></i> Purchase Orders - Pending Payment</h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="payable-table" style="font-size: 0.9rem;">
                        <thead>
                            <tr>
                                <th style="width: 15%;">PO Details</th>
                                <th style="width: 20%;">Supplier / Store</th>
                                <th style="width: 25%;">Amount Details</th>
                                <th style="width: 15%;">Status</th>
                                <th style="width: 10%;">Date</th>
                                <th style="width: 15%;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(document).ready(function() {
    let table = $('#payable-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('inventory.purchase-orders.accounts-payable') }}',
        columns: [
            {
                data: null,
                name: 'po_number',
                render: function(data, type, row) {
                    return `<div>
                        <strong class="d-block">${row.po_number}</strong>
                        <small class="text-muted">${row.status_badge}</small>
                    </div>`;
                }
            },
            {
                data: null,
                name: 'supplier.company_name',
                render: function(data, type, row) {
                    return `<div>
                        <strong class="d-block">${row.supplier_name}</strong>
                        <small class="text-muted"><i class="mdi mdi-store"></i> ${row.store_name}</small>
                    </div>`;
                }
            },
            {
                data: null,
                name: 'total_amount',
                render: function(data, type, row) {
                    return `<div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Total:</span>
                            <strong>${row.formatted_total}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Paid:</span>
                            <span class="text-success">${row.formatted_paid}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Balance:</span>
                            <strong class="text-danger">${row.formatted_balance}</strong>
                        </div>
                    </div>`;
                },
                orderable: false
            },
            {
                data: 'payment_status_badge',
                name: 'payment_status'
            },
            {
                data: 'formatted_date',
                name: 'created_at'
            },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                className: 'text-center'
            }
        ],
        order: [[4, 'desc']],
        drawCallback: function(settings) {
            updateStats(settings.json);
        }
    });

    function updateStats(response) {
        if (response && response.data) {
            let totalCount = response.recordsFiltered || 0;
            let totalAmount = 0;
            let totalPaid = 0;

            response.data.forEach(function(row) {
                let amount = parseFloat(row.total_amount) || 0;
                let paid = parseFloat(row.amount_paid) || 0;
                totalAmount += amount;
                totalPaid += paid;
            });

            $('#total-count').text(totalCount);
            $('#total-amount').text('₦' + totalAmount.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#total-paid').text('₦' + totalPaid.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#total-balance').text('₦' + (totalAmount - totalPaid).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        }
    }
});
</script>
@endsection

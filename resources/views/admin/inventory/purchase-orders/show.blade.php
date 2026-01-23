@extends('admin.layouts.app')
@section('title', 'Purchase Order - ' . $purchaseOrder->po_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'View Purchase Order')

@section('content')
<style>
    .po-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .po-header h2 {
        margin: 0;
        font-weight: 600;
    }
    .po-header .po-number {
        font-size: 1.25rem;
        opacity: 0.9;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.5em 1em;
        border-radius: 20px;
    }
    .status-draft { background-color: rgba(255,255,255,0.2); }
    .status-submitted { background-color: #17a2b8; }
    .status-approved { background-color: #28a745; }
    .status-partial { background-color: #ffc107; color: #212529; }
    .status-received { background-color: #007bff; }
    .status-cancelled { background-color: #dc3545; }

    .detail-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .detail-card h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        color: #333;
    }
    .detail-label {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.85rem;
    }
    .detail-value {
        font-weight: 600;
        color: #212529;
    }
    .items-table th {
        background: #f8f9fa;
    }
    .action-btn {
        margin-right: 0.5rem;
    }
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    .timeline-item {
        position: relative;
        padding-bottom: 1.5rem;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #007bff;
        border: 2px solid #fff;
    }
    .timeline-item:last-child {
        padding-bottom: 0;
    }
    .receiving-progress {
        height: 8px;
        border-radius: 4px;
    }

    /* ============================================
       PRINT STYLES - Professional Document Output
       ============================================ */
    @media print {
        /* Hide interactive UI elements */
        #left-sidebar,
        .navbar,
        .action-btn,
        button,
        .btn,
        form,
        .modal,
        .alert,
        .breadcrumb,
        #footer,
        .page-footer,
        .dropdown-menu,
        .pagination,
        .no-print {
            display: none !important;
        }

        /* Show print-only elements */
        .print-only {
            display: block !important;
        }

        /* Page layout */
        body {
            background: white !important;
            font-size: 11pt !important;
        }
        #content-wrapper,
        .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Print header styling */
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .company-logo {
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .company-address {
            font-size: 9pt;
            color: #666;
            margin-bottom: 3px;
        }
        .company-contact {
            font-size: 9pt;
            color: #666;
            margin-bottom: 10px;
        }
        .document-title {
            font-size: 14pt;
            font-weight: bold;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* PO header for print */
        .po-header {
            background: #f8f9fa !important;
            color: #333 !important;
            border: 1px solid #333 !important;
            padding: 15px !important;
            margin-bottom: 15px !important;
            page-break-inside: avoid;
        }
        .po-header h2 {
            color: #333 !important;
            font-size: 14pt !important;
        }
        .po-header .po-number {
            font-size: 12pt !important;
        }

        /* Cards and sections */
        .detail-card {
            border: 1px solid #333 !important;
            box-shadow: none !important;
            padding: 12px !important;
            margin-bottom: 15px !important;
            page-break-inside: avoid;
        }
        .detail-card h5 {
            border-bottom: 1px solid #333 !important;
            font-size: 11pt !important;
            padding-bottom: 5px !important;
            margin-bottom: 10px !important;
        }
        .detail-label {
            font-size: 9pt !important;
        }
        .detail-value {
            font-size: 10pt !important;
        }

        /* Tables */
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        table th, table td {
            border: 1px solid #333 !important;
            padding: 6px 8px !important;
            font-size: 10pt !important;
        }
        table th {
            background: #f0f0f0 !important;
            font-weight: bold !important;
        }
        .items-table th {
            background: #f0f0f0 !important;
        }

        /* Progress bars - convert to text */
        .receiving-progress {
            display: none !important;
        }

        /* Status badges */
        .status-badge {
            border: 1px solid #333 !important;
            padding: 2px 8px !important;
            border-radius: 3px !important;
        }
        .badge {
            border: 1px solid #333 !important;
            padding: 2px 6px !important;
        }

        /* Timeline */
        .timeline::before {
            background: #333 !important;
        }
        .timeline-item::before {
            background: #666 !important;
            border-color: white !important;
        }

        /* Layout adjustments - make full width on print */
        .col-md-8, .col-md-4 {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
        }
        .row {
            display: block !important;
            page-break-inside: avoid;
        }
        h1, h2, h3, h4, h5, h6 {
            page-break-after: avoid;
        }

        /* Force table visibility */
        .table-responsive {
            overflow: visible !important;
        }
        .items-table {
            display: table !important;
            width: 100% !important;
        }
        .items-table thead {
            display: table-header-group !important;
        }
        .items-table tbody {
            display: table-row-group !important;
        }
        .items-table tfoot {
            display: table-footer-group !important;
        }
        .items-table tr {
            display: table-row !important;
        }
        .items-table th,
        .items-table td {
            display: table-cell !important;
        }

        /* Payment summary styling */
        .payment-status-badge {
            border: 1px solid #333 !important;
        }

        /* Page settings */
        @page {
            size: A4;
            margin: 15mm 10mm 20mm 10mm;
        }

        /* Print footer */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
    }
</style>

<!-- Print Header (hidden on screen) -->
<div class="print-only print-header" style="display: none;">
    @if(appsettings('logo'))
    <div class="company-logo">
        <img src="data:image/png;base64,{{ appsettings('logo') }}" alt="Logo" style="max-height: 60px; max-width: 200px;">
    </div>
    @endif
    <div class="company-name">{{ appsettings('site_name') ?: config('app.name', 'CoreHealth') }}</div>
    <div class="company-address">{{ appsettings('contact_address') ?: 'Healthcare Management System' }}</div>
    @if(appsettings('contact_phones'))
    <div class="company-contact">Tel: {{ appsettings('contact_phones') }}</div>
    @endif
    <div class="document-title">PURCHASE ORDER</div>
    <div style="font-size: 10pt; margin-top: 5px;">{{ $purchaseOrder->po_number }}</div>
</div>

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="po-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="po-number">{{ $purchaseOrder->po_number }}</div>
                    <h2>{{ $purchaseOrder->supplier_name }}</h2>
                    <div class="mt-2">
                        <span class="status-badge status-{{ $purchaseOrder->status }}">
                            {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <h3 class="mb-0">₦{{ number_format($purchaseOrder->total_amount, 2) }}</h3>
                    <small>Total Amount</small>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="{{ route('inventory.purchase-orders.index') }}" class="btn btn-secondary btn-sm action-btn">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>

            @if($purchaseOrder->status === 'draft')
                @can('purchase-orders.edit')
                <a href="{{ route('inventory.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-warning btn-sm action-btn">
                    <i class="mdi mdi-pencil"></i> Edit
                </a>
                @endcan
                @can('purchase-orders.create')
                <button type="button" class="btn btn-info btn-sm action-btn" onclick="submitPO()">
                    <i class="mdi mdi-send"></i> Submit for Approval
                </button>
                @endcan
            @endif

            @if($purchaseOrder->status === 'submitted')
                @can('purchase-orders.approve')
                <button type="button" class="btn btn-success btn-sm action-btn" onclick="approvePO()">
                    <i class="mdi mdi-check"></i> Approve
                </button>
                @endcan
            @endif

            @if(in_array($purchaseOrder->status, ['approved', 'partial_received']))
                @can('purchase-orders.receive')
                <a href="{{ route('inventory.purchase-orders.receive', $purchaseOrder) }}" class="btn btn-primary btn-sm action-btn">
                    <i class="mdi mdi-package-down"></i> Receive Items
                </a>
                @endcan
            @endif

            @if($purchaseOrder->canRecordPayment())
                @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
                <a href="{{ route('inventory.purchase-orders.payment', $purchaseOrder) }}" class="btn btn-success btn-sm action-btn">
                    <i class="mdi mdi-cash-multiple"></i> Record Payment
                </a>
                @endhasanyrole
            @endif

            @if(in_array($purchaseOrder->status, ['draft', 'submitted']))
                @can('purchase-orders.edit')
                <button type="button" class="btn btn-danger btn-sm action-btn" onclick="cancelPO()">
                    <i class="mdi mdi-close"></i> Cancel PO
                </button>
                @endcan
            @endif

            <button type="button" class="btn btn-outline-secondary btn-sm action-btn" onclick="window.print()">
                <i class="mdi mdi-printer"></i> Print
            </button>
        </div>

        <div class="row">
            <!-- Order Details -->
            <div class="col-md-8">
                <div class="detail-card">
                    <h5><i class="mdi mdi-information"></i> Order Details</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Destination Store</div>
                            <div class="detail-value">{{ $purchaseOrder->targetStore->store_name }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value">{{ $purchaseOrder->created_at->format('M d, Y') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Expected Delivery</div>
                            <div class="detail-value">
                                {{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('M d, Y') : 'Not specified' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Supplier</div>
                            <div class="detail-value">{{ $purchaseOrder->supplier->company_name }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Total Amount</div>
                            <div class="detail-value">₦{{ number_format($purchaseOrder->total_amount, 2) }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Created By</div>
                            <div class="detail-value">{{ $purchaseOrder->creator->name ?? 'N/A' }}</div>
                        </div>
                    </div>
                    @if($purchaseOrder->notes)
                    <div class="mt-2">
                        <div class="detail-label">Notes</div>
                        <div class="detail-value">{{ $purchaseOrder->notes }}</div>
                    </div>
                    @endif
                </div>

                <!-- Line Items -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-package-variant"></i> Line Items</h5>
                    <div class="table-responsive">
                        <table class="table table-sm items-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="text-center">Ordered</th>
                                    <th class="text-center">Received</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Line Total</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseOrder->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->product->product_name }}</strong>
                                        <br><small class="text-muted">{{ $item->product->product_code }}</small>
                                    </td>
                                    <td class="text-center">{{ $item->ordered_qty }}</td>
                                    <td class="text-center">
                                        <span class="{{ $item->received_qty >= $item->ordered_qty ? 'text-success font-weight-bold' : '' }}">
                                            {{ $item->received_qty ?? 0 }}
                                        </span>
                                    </td>
                                    <td class="text-right">₦{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-right">₦{{ number_format($item->line_total, 2) }}</td>
                                    <td style="width: 100px;">
                                        @php
                                            $progress = $item->ordered_qty > 0
                                                ? round((($item->received_qty ?? 0) / $item->ordered_qty) * 100)
                                                : 0;
                                        @endphp
                                        <div class="progress receiving-progress">
                                            <div class="progress-bar {{ $progress >= 100 ? 'bg-success' : 'bg-info' }}"
                                                 style="width: {{ min($progress, 100) }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $progress }}%</small>
                                        <span class="print-only" style="display: none;">{{ $progress }}%</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">Subtotal:</th>
                                    <td class="text-right">₦{{ number_format($purchaseOrder->items->sum('line_total'), 2) }}</td>
                                    <td></td>
                                </tr>
                                @if($purchaseOrder->tax_amount > 0)
                                <tr>
                                    <th colspan="5" class="text-right">Tax:</th>
                                    <td class="text-right">₦{{ number_format($purchaseOrder->tax_amount, 2) }}</td>
                                    <td></td>
                                </tr>
                                @endif
                                @if($purchaseOrder->shipping_cost > 0)
                                <tr>
                                    <th colspan="5" class="text-right">Shipping:</th>
                                    <td class="text-right">₦{{ number_format($purchaseOrder->shipping_cost, 2) }}</td>
                                    <td></td>
                                </tr>
                                @endif
                                <tr class="table-primary">
                                    <th colspan="5" class="text-right">Total:</th>
                                    <td class="text-right"><strong>₦{{ number_format($purchaseOrder->total_amount, 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Timeline -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-history"></i> Timeline</h5>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="detail-label">Created</div>
                            <div class="detail-value">{{ $purchaseOrder->created_at->format('M d, Y H:i') }}</div>
                            <small class="text-muted">by {{ $purchaseOrder->createdBy->name ?? 'System' }}</small>
                        </div>
                        @if($purchaseOrder->submitted_at)
                        <div class="timeline-item">
                            <div class="detail-label">Submitted</div>
                            <div class="detail-value">{{ $purchaseOrder->submitted_at->format('M d, Y H:i') }}</div>
                        </div>
                        @endif
                        @if($purchaseOrder->approved_at)
                        <div class="timeline-item">
                            <div class="detail-label">Approved</div>
                            <div class="detail-value">{{ $purchaseOrder->approved_at->format('M d, Y H:i') }}</div>
                            <small class="text-muted">by {{ $purchaseOrder->approvedBy->name ?? 'System' }}</small>
                        </div>
                        @endif
                        @if($purchaseOrder->received_at)
                        <div class="timeline-item">
                            <div class="detail-label">Fully Received</div>
                            <div class="detail-value">{{ $purchaseOrder->received_at->format('M d, Y H:i') }}</div>
                            <small class="text-muted">by {{ $purchaseOrder->receivedBy->name ?? 'System' }}</small>
                        </div>
                        @endif
                        @if($purchaseOrder->cancelled_at)
                        <div class="timeline-item">
                            <div class="detail-label text-danger">Cancelled</div>
                            <div class="detail-value">{{ $purchaseOrder->cancelled_at->format('M d, Y H:i') }}</div>
                            @if($purchaseOrder->cancellation_reason)
                            <small class="text-muted">Reason: {{ $purchaseOrder->cancellation_reason }}</small>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Receiving Summary -->
                @php
                    $receivedItems = $purchaseOrder->items->where('received_qty', '>', 0);
                @endphp
                @if($receivedItems->count() > 0)
                <div class="detail-card">
                    <h5><i class="mdi mdi-package"></i> Received Items</h5>
                    @foreach($receivedItems as $item)
                    <div class="border-bottom pb-2 mb-2">
                        <strong>{{ $item->product->product_name }}</strong>
                        <br>
                        <small class="text-muted">
                            Ordered: {{ $item->ordered_qty }}<br>
                            Received: {{ $item->received_qty }}<br>
                            Pending: {{ $item->ordered_qty - $item->received_qty }}
                        </small>
                    </div>
                    @endforeach
                </div>
                @endif

                <!-- Expense Link -->
                @if($purchaseOrder->expense)
                <div class="detail-card">
                    <h5><i class="mdi mdi-cash"></i> Related Expense</h5>
                    <p class="mb-1">
                        <strong>{{ $purchaseOrder->expense->description }}</strong>
                    </p>
                    <p class="mb-1">Amount: ₦{{ number_format($purchaseOrder->expense->amount, 2) }}</p>
                    <p class="mb-0">Status:
                        <span class="badge badge-{{ $purchaseOrder->expense->status === 'approved' ? 'success' : 'warning' }}">
                            {{ ucfirst($purchaseOrder->expense->status) }}
                        </span>
                    </p>
                </div>
                @endif

                <!-- Payment Summary (for received POs) -->
                @if(in_array($purchaseOrder->status, ['partial', 'received']))
                <div class="detail-card">
                    <h5><i class="mdi mdi-currency-ngn"></i> Payment Status</h5>
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="text-muted small">Total</div>
                            <div class="font-weight-bold">₦{{ number_format($purchaseOrder->total_amount, 2) }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Paid</div>
                            <div class="font-weight-bold text-success">₦{{ number_format($purchaseOrder->amount_paid, 2) }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Balance</div>
                            <div class="font-weight-bold text-danger">₦{{ number_format($purchaseOrder->balance_due, 2) }}</div>
                        </div>
                    </div>
                    <div class="text-center">
                        <span class="badge {{ $purchaseOrder->getPaymentStatusBadgeClass() }} payment-status-badge">
                            {{ ucfirst($purchaseOrder->payment_status ?? 'unpaid') }}
                        </span>
                    </div>

                    @if($purchaseOrder->canRecordPayment())
                        @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS')
                        <div class="text-center mt-3">
                            <a href="{{ route('inventory.purchase-orders.payment', $purchaseOrder) }}" class="btn btn-success btn-sm">
                                <i class="mdi mdi-cash-multiple"></i> Record Payment
                            </a>
                        </div>
                        @endhasanyrole
                    @endif
                </div>
                @endif

                <!-- Payment History -->
                @if(isset($purchaseOrder->payments) && $purchaseOrder->payments->count() > 0)
                <div class="detail-card">
                    <h5><i class="mdi mdi-history"></i> Payment History</h5>
                    @foreach($purchaseOrder->payments->sortByDesc('payment_date') as $payment)
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>₦{{ number_format($payment->amount, 2) }}</strong>
                            <span class="badge badge-secondary">{{ $payment->payment_method_label }}</span>
                        </div>
                        <small class="text-muted">
                            {{ $payment->payment_date->format('M d, Y') }}
                            @if($payment->reference_number)
                                | Ref: {{ $payment->reference_number }}
                            @endif
                        </small>
                        @if($payment->notes)
                            <br><small class="text-muted">{{ $payment->notes }}</small>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Print Footer (hidden on screen) -->
<div class="print-only print-footer" style="display: none;">
    <p style="margin: 0;">{{ appsettings('site_name') ?: config('app.name', 'CoreHealth') }} - Printed on {{ now()->format('M d, Y H:i') }}</p>
</div>
@endsection

@section('scripts')
<script>
function submitPO() {
    if (confirm('Submit this Purchase Order for approval?')) {
        $.post('{{ route("inventory.purchase-orders.submit", $purchaseOrder) }}', { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order submitted');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to submit');
            });
    }
}

function approvePO() {
    if (confirm('Approve this Purchase Order?')) {
        $.post('{{ route("inventory.purchase-orders.approve", $purchaseOrder) }}', { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order approved');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function cancelPO() {
    var reason = prompt('Please enter cancellation reason:');
    if (reason) {
        $.post('{{ route("inventory.purchase-orders.cancel", $purchaseOrder) }}', {
            _token: '{{ csrf_token() }}',
            cancellation_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Purchase Order cancelled');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}
</script>
@endsection

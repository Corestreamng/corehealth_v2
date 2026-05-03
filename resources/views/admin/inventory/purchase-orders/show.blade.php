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
        @page { size: A4; margin: 10mm; }
        body { background: #fff !important; color: #000 !important; font-family: 'Inter', 'Segoe UI', Arial, sans-serif !important; }
        .no-print { display: none !important; }
        .print-only { display: block !important; }

        .po-print-sheet { padding: 0; background: #fff; }
        .brand-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 15px; margin-bottom: 25px; }
        .brand-logo { width: 80px; height: auto; }
        .brand-info { flex: 1; margin-left: 20px; }
        .brand-name { font-size: 22pt; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
        .brand-meta { font-size: 9pt; color: #64748b; line-height: 1.4; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 20pt; font-weight: 800; color: #1e293b; margin: 0; letter-spacing: 1px; }
        .doc-title p { font-size: 11pt; color: #64748b; margin: 4px 0 0; font-weight: 600; }

        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .info-item { border-left: 3px solid #e2e8f0; padding-left: 12px; }
        .info-label { font-size: 8pt; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { font-size: 10pt; font-weight: 600; color: #1e293b; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .items-table th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 12px 10px; text-align: left; font-size: 9pt; font-weight: 700; color: #475569; text-transform: uppercase; }
        .items-table td { border-bottom: 1px solid #f1f5f9; padding: 10px; font-size: 10pt; vertical-align: top; }
        .items-table tr:last-child td { border-bottom: 2px solid #e2e8f0; }

        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 40px; }
        .totals-box { width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 10pt; }
        .total-row.grand { border-top: 2px solid #1e293b; margin-top: 5px; padding-top: 10px; font-weight: 800; font-size: 12pt; color: #1e293b; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 60px; }
        .sig-line { border-top: 1px solid #94a3b8; text-align: center; padding-top: 8px; font-size: 9pt; color: #64748b; }

        .print-footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #94a3b8; padding: 10px; border-top: 1px solid #f1f5f9; }
    }
</style>

<div class="print-only po-print-sheet" style="display: none;">
    <div class="brand-bar">
        <div style="display: flex; align-items: center;">
            @if(appsettings('logo'))
                <img src="data:image/png;base64,{{ appsettings('logo') }}" alt="Logo" class="brand-logo">
            @endif
            <div class="brand-info">
                <div class="brand-name">{{ appsettings('site_name') ?: config('app.name', 'CoreHealth') }}</div>
                <div class="brand-meta">
                    {{ appsettings('contact_address') ?: 'Healthcare Management System' }}<br>
                    Tel: {{ appsettings('contact_phones') ?: 'N/A' }} | Email: {{ appsettings('contact_emails') ?: 'N/A' }}
                </div>
            </div>
        </div>
        <div class="doc-title">
            <h1>PURCHASE ORDER</h1>
            <p>{{ $purchaseOrder->po_number }}</p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Vendor / Supplier</div>
            <div class="info-value">{{ $purchaseOrder->supplier->company_name }}</div>
            <div class="small text-muted">{{ $purchaseOrder->supplier->phone }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Deliver To</div>
            <div class="info-value">{{ $purchaseOrder->targetStore->store_name }}</div>
            <div class="small text-muted">{{ $purchaseOrder->targetStore->location }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Order Context</div>
            <div class="small">Date: <strong>{{ $purchaseOrder->created_at->format('d/m/Y') }}</strong></div>
            <div class="small">Expected: <strong>{{ $purchaseOrder->expected_date ? \Carbon\Carbon::parse($purchaseOrder->expected_date)->format('d/m/Y') : 'N/A' }}</strong></div>
            <div class="small">Status: <strong>{{ strtoupper($purchaseOrder->status) }}</strong></div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Item Description</th>
                <th style="text-align: center; width: 100px;">Qty Ordered</th>
                <th style="text-align: center; width: 100px;">Qty Received</th>
                <th style="text-align: right; width: 120px;">Unit Cost</th>
                <th style="text-align: right; width: 130px;">Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <div style="font-weight: 700;">{{ $item->product->product_name }}</div>
                    <div style="font-size: 8pt; color: #64748b;">{{ $item->product->product_code }} | {{ ucfirst($item->product->product_type) }}</div>
                </td>
                <td style="text-align: center;">
                    {{ $item->ordered_qty }} {{ $item->product->base_unit_name ?? 'units' }}
                    @if($item->packaging)
                        <div style="font-size: 8pt; color: #0891b2;">({{ (float)$item->packaging_qty }} {{ $item->packaging->name }})</div>
                    @endif
                </td>
                <td style="text-align: center;">
                    {{ $item->received_qty ?? 0 }}
                </td>
                <td style="text-align: right;">{{ number_format($item->unit_cost, 2) }}</td>
                <td style="text-align: right; font-weight: 700;">{{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <div class="totals-box">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₦{{ number_format($purchaseOrder->items->sum('line_total'), 2) }}</span>
            </div>
            @if($purchaseOrder->tax_amount > 0)
            <div class="total-row">
                <span>Tax (VAT)</span>
                <span>₦{{ number_format($purchaseOrder->tax_amount, 2) }}</span>
            </div>
            @endif
            @if($purchaseOrder->shipping_cost > 0)
            <div class="total-row">
                <span>Shipping / Freight</span>
                <span>₦{{ number_format($purchaseOrder->shipping_cost, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand">
                <span>ORDER TOTAL</span>
                <span>₦{{ number_format($purchaseOrder->total_amount, 2) }}</span>
            </div>
        </div>
    </div>

    @if($purchaseOrder->notes)
    <div style="margin-top: 20px; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px;">
        <div class="info-label">Special Notes / Instructions</div>
        <div style="font-size: 10pt; color: #1e293b;">{{ $purchaseOrder->notes }}</div>
    </div>
    @endif

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Authorized Signatory / Warehouse Manager</div>
            <div style="text-align: center; margin-top: 5px; font-size: 8pt;">{{ auth()->user()->name }} | {{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">Supplier Acceptance (Stamp & Sign)</div>
            <div style="text-align: center; margin-top: 5px; font-size: 8pt;">Name: ______________________</div>
        </div>
    </div>

    <div class="print-footer">
        Generated by CoreHealth v2.0 | {{ config('app.name') }} Inventory Module | Page 1 of 1
    </div>
</div>

<div id="content-wrapper" class="no-print">
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

            @if(in_array($purchaseOrder->status, ['approved', 'partial']))
                @can('purchase-orders.receive')
                <a href="{{ route('inventory.purchase-orders.receive', $purchaseOrder) }}" class="btn btn-primary btn-sm action-btn">
                    <i class="mdi mdi-package-down"></i>
                    {{ $purchaseOrder->status === 'partial' ? 'Receive Remaining' : 'Receive Items' }}
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
                                        <strong>{{ $item->product->product_name ?? 'Product Deleted' }}</strong>
                                        <br><small class="text-muted">{{ $item->product->product_code ?? 'N/A' }}</small>
                                        @if($item->product && $item->product->product_type)
                                        <span class="badge badge-{{ $item->product->product_type === 'drug' ? 'success' : ($item->product->product_type === 'consumable' ? 'warning' : 'info') }}" style="font-size:.65rem">{{ ucfirst($item->product->product_type) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ $item->ordered_qty }}
                                        @if($item->packaging)
                                        <br><small class="text-primary">{{ (float)$item->packaging_qty }} {{ $item->packaging->name }}</small>
                                        @endif
                                        @if($item->product)
                                        <br><small class="text-muted">{{ $item->product->base_unit_name ?? 'pcs' }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="{{ $item->received_qty >= $item->ordered_qty ? 'text-success font-weight-bold' : '' }}">
                                            {{ $item->received_qty ?? 0 }}
                                        </span>
                                        @if($item->receivedPackaging)
                                        <br><small class="text-info">{{ (float)$item->received_packaging_qty }} {{ $item->receivedPackaging->name }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">₦{{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-right">₦{{ number_format($item->line_total, 2) }}</td>
                                    <td style="width: 100px;">
                                        @php
                                            $progress = $item->ordered_qty> 0
                                                ? round((($item->received_qty ?? 0) / $item->ordered_qty) * 100)
                                                : 0;
                                        @endphp
                                        <div class="progress receiving-progress">
                                            <div class="progress-bar {{ $progress>= 100 ? 'bg-success' : 'bg-info' }}"
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
                                @if($purchaseOrder->tax_amount> 0)
                                <tr>
                                    <th colspan="5" class="text-right">Tax:</th>
                                    <td class="text-right">₦{{ number_format($purchaseOrder->tax_amount, 2) }}</td>
                                    <td></td>
                                </tr>
                                @endif
                                @if($purchaseOrder->shipping_cost> 0)
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
                @if($receivedItems->count()> 0)
                <div class="detail-card">
                    <h5><i class="mdi mdi-package"></i> Received Items</h5>
                    @foreach($receivedItems as $item)
                    <div class="border-bottom pb-2 mb-2">
                        <strong>{{ $item->product->product_name ?? 'Product Deleted' }}</strong>
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
                @if(isset($purchaseOrder->payments) && $purchaseOrder->payments->count()> 0)
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

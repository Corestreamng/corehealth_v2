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
</style>
@endpush

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
                            <div class="detail-value">{{ $purchaseOrder->store->store_name }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Order Date</div>
                            <div class="detail-value">{{ $purchaseOrder->order_date->format('M d, Y') }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Expected Delivery</div>
                            <div class="detail-value">
                                {{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : 'Not specified' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Payment Terms</div>
                            <div class="detail-value">{{ $purchaseOrder->payment_terms ?: 'Not specified' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Supplier Contact</div>
                            <div class="detail-value">{{ $purchaseOrder->supplier_contact ?: 'Not specified' }}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="detail-label">Created By</div>
                            <div class="detail-value">{{ $purchaseOrder->createdBy->name ?? 'N/A' }}</div>
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
                                    <td class="text-center">{{ $item->quantity_ordered }}</td>
                                    <td class="text-center">
                                        <span class="{{ $item->quantity_received >= $item->quantity_ordered ? 'text-success' : '' }}">
                                            {{ $item->quantity_received }}
                                        </span>
                                    </td>
                                    <td class="text-right">₦{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-right">₦{{ number_format($item->line_total, 2) }}</td>
                                    <td style="width: 100px;">
                                        @php
                                            $progress = $item->quantity_ordered > 0
                                                ? round(($item->quantity_received / $item->quantity_ordered) * 100)
                                                : 0;
                                        @endphp
                                        <div class="progress receiving-progress">
                                            <div class="progress-bar {{ $progress >= 100 ? 'bg-success' : 'bg-info' }}"
                                                 style="width: {{ min($progress, 100) }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $progress }}%</small>
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
                @if($purchaseOrder->batches->count() > 0)
                <div class="detail-card">
                    <h5><i class="mdi mdi-package"></i> Received Batches</h5>
                    @foreach($purchaseOrder->batches as $batch)
                    <div class="border-bottom pb-2 mb-2">
                        <strong>{{ $batch->product->product_name }}</strong>
                        <br>
                        <small class="text-muted">
                            Batch: {{ $batch->batch_number }}<br>
                            Qty: {{ $batch->initial_quantity }}<br>
                            Expiry: {{ $batch->expiry_date ? $batch->expiry_date->format('M Y') : 'N/A' }}
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
            </div>
        </div>
    </div>
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

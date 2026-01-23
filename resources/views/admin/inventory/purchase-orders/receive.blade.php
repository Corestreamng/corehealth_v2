@extends('admin.layouts.app')
@section('title', 'Receive Items - ' . $purchaseOrder->po_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Receive Purchase Order')

@section('content')
<style>
    .po-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        border-left: 4px solid #28a745;
    }
    .po-header {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .po-body {
        padding: 1rem;
    }
    .po-footer {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0 0 8px 8px;
    }
    .receiving-info {
        background: #d4edda;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    .item-row {
        border-bottom: 1px solid #eee;
        padding: 0.75rem 0;
    }
    .item-row:last-child {
        border-bottom: none;
    }
    .batch-details {
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 4px;
        margin-top: 0.5rem;
        display: none;
    }
    .batch-details.show {
        display: block;
    }
    .qty-input {
        width: 70px !important;
        text-align: center;
    }
    .batch-field {
        margin-bottom: 0.5rem;
    }
    .batch-field label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .batch-field input {
        font-size: 0.85rem;
    }
    .toggle-batch {
        cursor: pointer;
        color: #007bff;
        font-size: 0.8rem;
    }
    .toggle-batch:hover {
        text-decoration: underline;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Receive Purchase Order Items</h4>
                <p class="text-muted mb-0">{{ $purchaseOrder->po_number }} - {{ $purchaseOrder->supplier->company_name }}</p>
            </div>
            <a href="{{ route('inventory.purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to PO
            </a>
        </div>

        <div class="receiving-info">
            <i class="mdi mdi-information"></i>
            This purchase order has been approved and is ready to receive items into <strong>{{ $purchaseOrder->targetStore->store_name }}</strong>.
        </div>

        <div class="po-card">
            <div class="po-header">
                <div>
                    <h6 class="mb-1">{{ $purchaseOrder->po_number }}</h6>
                    <small class="text-muted">
                        Approved {{ $purchaseOrder->approved_at?->diffForHumans() ?? 'N/A' }}
                        @if($purchaseOrder->approver)
                            by {{ $purchaseOrder->approver->name }}
                        @endif
                    </small>
                </div>
                <div>
                    <span class="badge badge-success">To: {{ $purchaseOrder->targetStore->store_name }}</span>
                </div>
            </div>

            <div class="po-body">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th style="width: 35%">Product</th>
                            <th class="text-center" style="width: 12%">Ordered</th>
                            <th class="text-center" style="width: 12%">Received</th>
                            <th class="text-center" style="width: 12%">Pending</th>
                            <th class="text-center" style="width: 15%">Receive Now</th>
                            <th style="width: 14%">Batch #</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseOrder->items as $item)
                        @php
                            $pending = $item->ordered_qty - ($item->received_qty ?? 0);
                        @endphp
                        @if($pending > 0)
                        <tr class="item-main-row" data-item-id="{{ $item->id }}">
                            <td>
                                <div>{{ $item->product->product_name ?? 'Unknown' }}</div>
                                <small class="text-muted">{{ $item->product->product_code }}</small>
                                <br>
                                <span class="toggle-batch" data-item-id="{{ $item->id }}">
                                    <i class="mdi mdi-chevron-down"></i> More details
                                </span>
                            </td>
                            <td class="text-center align-middle">{{ $item->ordered_qty }}</td>
                            <td class="text-center align-middle">{{ $item->received_qty ?? 0 }}</td>
                            <td class="text-center align-middle text-warning"><strong>{{ $pending }}</strong></td>
                            <td class="text-center align-middle">
                                <input type="number"
                                       class="form-control form-control-sm qty-input receive-qty"
                                       data-item-id="{{ $item->id }}"
                                       data-product-id="{{ $item->product_id }}"
                                       min="0"
                                       max="{{ $pending }}"
                                       value="{{ $pending }}">
                            </td>
                            <td class="align-middle">
                                <input type="text"
                                       class="form-control form-control-sm batch-number"
                                       placeholder="Required"
                                       data-item-id="{{ $item->id }}">
                            </td>
                        </tr>
                        <tr class="batch-row" id="batch-row-{{ $item->id }}" style="display: none;">
                            <td colspan="6" class="p-0">
                                <div class="batch-details show" style="margin: 0 1rem 0.5rem 1rem;">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="batch-field">
                                                <label>Expiry Date</label>
                                                <input type="date"
                                                       class="form-control form-control-sm expiry-date"
                                                       data-item-id="{{ $item->id }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="batch-field">
                                                <label>Manufacture Date</label>
                                                <input type="date"
                                                       class="form-control form-control-sm manufacture-date"
                                                       data-item-id="{{ $item->id }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="batch-field">
                                                <label>Unit Cost (₦{{ number_format($item->unit_cost, 2) }})</label>
                                                <input type="number"
                                                       class="form-control form-control-sm cost-price"
                                                       step="0.01"
                                                       value="{{ $item->unit_cost }}"
                                                       data-item-id="{{ $item->id }}">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="batch-field">
                                                <label>Selling Price</label>
                                                <input type="number"
                                                       class="form-control form-control-sm selling-price"
                                                       step="0.01"
                                                       value="{{ $item->product->price->pr_sale_price ?? $item->unit_cost }}"
                                                       data-item-id="{{ $item->id }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>

                @if($purchaseOrder->notes)
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">PO Notes:</small>
                    <p class="mb-0">{{ $purchaseOrder->notes }}</p>
                </div>
                @endif
            </div>

            <div class="po-footer">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="mb-1"><small>Receiving Notes (optional)</small></label>
                        <textarea id="receiving-notes" class="form-control form-control-sm" rows="2" placeholder="Any notes about this delivery..."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1"><small>Invoice/Delivery Note #</small></label>
                        <input type="text" id="invoice-number" class="form-control form-control-sm" placeholder="Supplier invoice number">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" id="create-expense" class="form-check-input" checked>
                            <label for="create-expense" class="form-check-label"><small>Create expense</small></label>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-success">Approved - Ready to Receive</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fillAll()">
                            <i class="mdi mdi-check-all"></i> Receive All
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="process-btn" onclick="receiveItems()">
                            <i class="mdi mdi-package-down"></i> Process Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Toggle batch details
$(document).on('click', '.toggle-batch', function() {
    const itemId = $(this).data('item-id');
    const batchRow = $(`#batch-row-${itemId}`);
    const icon = $(this).find('i');

    if (batchRow.is(':visible')) {
        batchRow.hide();
        icon.removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
        $(this).html('<i class="mdi mdi-chevron-down"></i> More details');
    } else {
        batchRow.show();
        icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
        $(this).html('<i class="mdi mdi-chevron-up"></i> Less details');
    }
});

function fillAll() {
    $('.receive-qty').each(function() {
        $(this).val($(this).attr('max'));
    });
}

function receiveItems() {
    const items = [];
    let hasErrors = false;
    let errorMessage = '';

    $('.receive-qty').each(function() {
        const qty = parseInt($(this).val()) || 0;
        if (qty > 0) {
            const itemId = $(this).data('item-id');
            const productId = $(this).data('product-id');
            const batchNumber = $(`.batch-number[data-item-id="${itemId}"]`).val();
            const expiryDate = $(`.expiry-date[data-item-id="${itemId}"]`).val();
            const costPrice = $(`.cost-price[data-item-id="${itemId}"]`).val();
            const sellingPrice = $(`.selling-price[data-item-id="${itemId}"]`).val();
            const manufactureDate = $(`.manufacture-date[data-item-id="${itemId}"]`).val();

            if (!batchNumber) {
                hasErrors = true;
                errorMessage = 'Batch number is required for all items being received';
                $(`.batch-number[data-item-id="${itemId}"]`).addClass('is-invalid').focus();
                return false;
            }

            items.push({
                item_id: itemId,
                product_id: productId,
                qty: qty,
                batch_number: batchNumber,
                expiry_date: expiryDate || null,
                actual_cost: parseFloat(costPrice) || null,
                selling_price: parseFloat(sellingPrice) || null,
                manufacture_date: manufactureDate || null
            });
        }
    });

    if (hasErrors) {
        toastr.error(errorMessage);
        return;
    }

    if (items.length === 0) {
        toastr.warning('Please enter quantities to receive');
        return;
    }

    const btn = $('#process-btn');
    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: '{{ route("inventory.purchase-orders.receive.process", $purchaseOrder) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            items: items,
            receiving_notes: $('#receiving-notes').val(),
            invoice_number: $('#invoice-number').val(),
            create_expense: $('#create-expense').is(':checked') ? 1 : 0
        },
        success: function(response) {
            toastr.success(response.message || 'Items received successfully');
            setTimeout(() => {
                window.location.href = '{{ route("inventory.purchase-orders.show", $purchaseOrder) }}';
            }, 1000);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to receive items');
            btn.prop('disabled', false).html('<i class="mdi mdi-package-down"></i> Process Receipt');
        }
    });
}

// Validate quantity doesn't exceed max
$(document).on('input', '.receive-qty', function() {
    const max = parseInt($(this).attr('max'));
    const val = parseInt($(this).val()) || 0;
    if (val > max) {
        $(this).val(max);
        toastr.warning('Quantity cannot exceed pending amount (' + max + ')');
    }
});

// Remove invalid class when user starts typing
$(document).on('input', '.batch-number', function() {
    $(this).removeClass('is-invalid');
});
</script>
@endsection

@extends('admin.layouts.app')
@section('title', 'Services and Products ')
@section('page_name', 'Summary')
@section('subpage_name', 'Services and Products Request Summary')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-primary text-white">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-0">Summary</h5>
                        </div>
                        <div class="col-md-6 text-right">
                            {{-- Future: Add actions here --}}
                        </div>
                    </div>
                </div>
                <form action="{{ route('complete-payment') }}" method="post" id="summaryForm">
                    @csrf
                    <div class="card-body">
                        <h4 class="mb-3">Services</h4>
                        <div class="table-responsive">
                            <table id="services-summary-list" class="table table-sm table-bordered table-striped align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th>SN</th>
                                        <th>Service Name</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>
                                            Discount
                                            <input type="number" min="0" value="0" max="100" class="form-control form-control-sm mt-1" id="service-discount-all" placeholder="All (%)" style="width: 70px; display: inline-block;">
                                        </th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @isset($services)
                                        @php $i = 0; @endphp
                                        @forelse ($services as $service)
                                            <tr>
                                                <td>{{ $service->id }}</td>
                                                <td>{{ $service->service->service_name }}</td>
                                                <td>
                                                    <span>&#8358;</span>
                                                    @php
                                                        $servicePrice = $service->payable_amount !== null ? $service->payable_amount : ($service->service->price->sale_price ?? 0);
                                                    @endphp
                                                    <span class="service-price">{{ $servicePrice }}</span>
                                                    <input type="hidden" name="servicePrice[]" value="{{ $servicePrice }}">
                                                    @if($service->payable_amount !== null && $service->claims_amount > 0)
                                                        <br><small class="text-success">HMO covers: &#8358;{{ number_format($service->claims_amount, 2) }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="number" name="serviceQty[]" class="form-control form-control-sm service-qty" value="{{ $serviceQty[$i] }}" min="1" style="width: 70px;">
                                                </td>
                                                <td>
                                                    <input type="number" name="serviceDiscount[]" class="form-control form-control-sm service-discount" value="0" max="100" min="0" max="100" style="width: 70px;" placeholder="%">
                                                </td>
                                                <td>
                                                    <span class="service-row-total">&#8358;0.00</span>
                                                </td>
                                            </tr>
                                            @php $i++; @endphp
                                        @empty
                                            <tr><td colspan="6" class="text-center">No service selected for payment</td></tr>
                                        @endforelse
                                    @endisset
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3">Products</h4>
                        <div class="table-responsive">
                            <table id="products-summary-list" class="table table-sm table-bordered table-striped align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th>SN</th>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>
                                            Discount
                                            <input type="number" min="0" value="0" max="100" class="form-control form-control-sm mt-1" id="product-discount-all" placeholder="All (%)" style="width: 70px; display: inline-block;">
                                        </th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @isset($products)
                                        @php $j = 0; @endphp
                                        @forelse ($products as $product)
                                            <tr>
                                                <td>{{ $product->id }}</td>
                                                <td>{{ $product->product->product_name }}</td>
                                                <td>
                                                    <span>&#8358;</span>
                                                    @php
                                                        $productPrice = $product->payable_amount !== null ? $product->payable_amount : $product->product->price->current_sale_price;
                                                    @endphp
                                                    <span class="product-price">{{ $productPrice }}</span>
                                                    <input type="hidden" name="productPrice[]" value="{{ $productPrice }}">
                                                    @if($product->payable_amount !== null && $product->claims_amount > 0)
                                                        <br><small class="text-success">HMO covers: &#8358;{{ number_format($product->claims_amount, 2) }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="number" name="productQty[]" class="form-control form-control-sm product-qty" value="{{ $productQty[$j] }}" min="1" style="width: 70px;">
                                                </td>
                                                <td>
                                                    <input type="number" name="productDiscount[]" class="form-control form-control-sm product-discount" value="0" max="100" min="0" max="100" style="width: 70px;" placeholder="%">
                                                </td>
                                                <td>
                                                    <span class="product-row-total">&#8358;0.00</span>
                                                </td>
                                            </tr>
                                            @php $j++; @endphp
                                        @empty
                                            <tr><td colspan="6" class="text-center">No product selected for payment</td></tr>
                                        @endforelse
                                    @endisset
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="total">Total</label>
                            <input type="text" class="form-control font-weight-bold" name="total" id="total"
                                value="{{ $sumProducts + $sumServices }}" required readonly>
                        </div>
                        <div class="form-group">
                            <label for="payment_type">Payment Type</label>
                            <select class="form-control" name="payment_type" id="payment_type">
                                <option value="POS">POS</option>
                                <option value="CASH">Cash</option>
                                <option value="TRANSFER">Transfer</option>
                                <option value="TELLER">Teller</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="ACC_WITHDRAW">
                                    Credit Account (NGN
                                    {{
                                        ($services[0]->user->patient_profile->account->balance ??
                                         $products[0]->user->patient_profile->account->balance ??
                                         "N/A")
                                    }})
                                </option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reference_no"> Reference Number</label>
                            <input type="text" class="form-control" name="reference_no" id="reference number" value="{{generate_invoice_no()}}">
                            <input type="hidden" name="patient_id" value="{{(($services) ? $services[0]->user->patient_profile->id : (($products) ? $products[0]->user->patient_profile->id : "N/A"))}}">
                        </div>
                        <div>
                            <button type="submit" class="align-self-end btn btn-lg btn-block btn-primary"
                                style="margin-top: auto;">Complete Payment</button>
                        </div>
                    </div>
            </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    function calcServiceTotals() {
        let total = 0;
        $('#services-summary-list tbody tr').each(function () {
            let price = parseFloat($(this).find('.service-price').text()) || 0;
            let qty = parseInt($(this).find('.service-qty').val()) || 1;
            let discount = parseFloat($(this).find('.service-discount').val()) || 0;
            let rowTotal = price * qty * (1 - discount / 100);
            $(this).find('.service-row-total').text('₦' + rowTotal.toLocaleString(undefined, {minimumFractionDigits:2}));
            total += rowTotal;
        });
        return total;
    }
    function calcProductTotals() {
        let total = 0;
        $('#products-summary-list tbody tr').each(function () {
            let price = parseFloat($(this).find('.product-price').text()) || 0;
            let qty = parseInt($(this).find('.product-qty').val()) || 1;
            let discount = parseFloat($(this).find('.product-discount').val()) || 0;
            let rowTotal = price * qty * (1 - discount / 100);
            $(this).find('.product-row-total').text('₦' + rowTotal.toLocaleString(undefined, {minimumFractionDigits:2}));
            total += rowTotal;
        });
        return total;
    }
    function updateGrandTotal() {
        let s = calcServiceTotals();
        let p = calcProductTotals();
        $('#total').val((s + p).toFixed(2));
    }
    $(document).ready(function () {
        // Initial calculation
        updateGrandTotal();

        // Per-row discount/qty changes
        $('.service-qty, .service-discount').on('input', function () {
            updateGrandTotal();
        });
        $('.product-qty, .product-discount').on('input', function () {
            updateGrandTotal();
        });

        // General discount for all services
        $('#service-discount-all').on('input', function () {
            let val = $(this).val();
            $('.service-discount').val(val);
            updateGrandTotal();
        });
        // General discount for all products
        $('#product-discount-all').on('input', function () {
            let val = $(this).val();
            $('.product-discount').val(val);
            updateGrandTotal();
        });
    });
</script>
@endsection

@section('styles')
<style>
    #services-summary-list th, #services-summary-list td,
    #products-summary-list th, #products-summary-list td {
        padding: 0.4rem 0.5rem;
        font-size: 0.97rem;
        vertical-align: middle;
    }
    #services-summary-list input[type="number"],
    #products-summary-list input[type="number"] {
        min-width: 48px;
        max-width: 70px;
        padding: 2px 4px;
        font-size: 0.95em;
        text-align: center;
    }
    .table thead th {
        background: #f8f9fa;
    }
</style>
@endsection

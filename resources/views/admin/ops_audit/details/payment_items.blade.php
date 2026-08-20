<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0">
        <thead class="bg-light">
            <tr>
                <th width="5%">#</th>
                <th>Item Description</th>
                <th class="text-center" width="10%">Qty</th>
                <th class="text-end" width="15%">Total Amount (₦)</th>
                <th class="text-end" width="15%">Payable (₦)</th>
                <th class="text-end" width="15%">Claims (₦)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payment->product_or_service_request as $idx => $req)
                @php
                    $itemName = '-';
                    if ($req->product) $itemName = $req->product->product_name;
                    elseif ($req->service) $itemName = $req->service->service_name;
                    elseif ($req->procedure) $itemName = $req->procedure->is_free_form ? $req->procedure->free_form_name : ($req->procedure->service?->service_name ?? '-');
                    
                    $computedAmount = $req->amount > 0 ? $req->amount : ($req->payable_amount + $req->claims_amount);
                @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $itemName }}</td>
                    <td class="text-center">{{ $req->qty }}</td>
                    <td class="text-end">{{ number_format($computedAmount, 2) }}</td>
                    <td class="text-end text-success">{{ number_format($req->payable_amount, 2) }}</td>
                    <td class="text-end text-info">{{ number_format($req->claims_amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="mdi mdi-flask-empty-outline fs-4 d-block mb-1"></i> No items found for this payment.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($payment->product_or_service_request->count() > 0)
        @php
            $sumAmount = $payment->product_or_service_request->sum(function($r) {
                return $r->amount > 0 ? $r->amount : ($r->payable_amount + $r->claims_amount);
            });
        @endphp
        <tfoot class="fw-bold bg-light">
            <tr>
                <td colspan="3" class="text-end">Grand Total:</td>
                <td class="text-end fs-6">{{ number_format($sumAmount, 2) }}</td>
                <td class="text-end text-success fs-6">{{ number_format($payment->product_or_service_request->sum('payable_amount'), 2) }}</td>
                <td class="text-end text-info fs-6">{{ number_format($payment->product_or_service_request->sum('claims_amount'), 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

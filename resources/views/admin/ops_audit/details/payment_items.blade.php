<div class="p-4">
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped mb-0">
        <thead class="bg-light">
            <tr>
                <th width="5%">#</th>
                <th width="12%">Date/Time</th>
                <th>Item Description</th>
                <th class="text-center" width="10%">Qty</th>
                <th class="text-end" width="15%">Total Amount (₦)</th>
                <th class="text-end" width="15%">Payable (₦)</th>
                <th class="text-end" width="15%">Claims (₦)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumAmount = 0;
                $sumPayable = 0;
                $sumClaims = 0;
            @endphp
            @forelse($payment->product_or_service_request as $idx => $req)
                @php
                    $itemName = '-';
                    $fallbackPrice = 0;
                    $categoryName = '-';
                    if ($req->product) {
                        $itemName = $req->product->product_name;
                        $fallbackPrice = $req->product->price?->sale_price ?? 0;
                        $categoryName = $req->product->category?->category_name ?? 'Product';
                    } elseif ($req->service) {
                        $itemName = $req->service->service_name;
                        $fallbackPrice = $req->service->price?->sale_price ?? 0;
                        $categoryName = $req->service->category?->category_name ?? 'Service';
                    } elseif ($req->procedure) {
                        $itemName = $req->procedure->is_free_form ? $req->procedure->free_form_name : ($req->procedure->service?->service_name ?? '-');
                        $fallbackPrice = $req->procedure->service?->price?->sale_price ?? 0;
                        $categoryName = $req->procedure->service?->category?->category_name ?? 'Procedure';
                    }
                    
                    $billedBy = $req->staff ? trim($req->staff->firstname . ' ' . ($req->staff->surname ?? '')) : 'Unknown';

                    $computedAmount = $req->amount > 0 ? $req->amount : ($req->payable_amount + $req->claims_amount);
                    if ($computedAmount == 0 && $fallbackPrice > 0) {
                        $computedAmount = $fallbackPrice * $req->qty;
                    }
                    
                    $payable = $req->payable_amount ?? ($computedAmount - ($req->claims_amount ?? 0));
                    $claims = $req->claims_amount ?? 0;

                    $sumAmount += $computedAmount;
                    $sumPayable += $payable;
                    $sumClaims += $claims;
                @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td><small class="text-muted">{{ $req->created_at ? $req->created_at->format('d M y, H:i') : '-' }}</small></td>
                    <td>
                        <div class="mb-1 text-dark fw-bold">{{ $itemName }}</div>
                        <div style="font-size: 0.75rem; line-height: 1.4;">
                            <div class="text-muted"><i class="mdi mdi-tag-outline me-1"></i> <strong>Category:</strong> {{ $categoryName }}</div>
                            <div class="text-muted"><i class="mdi mdi-account-cash me-1"></i> <strong>Billed By:</strong> {{ $billedBy }}</div>
                            @if($req->labRequest)
                                @php
                                    $lab = $req->labRequest;
                                    $labStatus = $lab->status == 1 ? 'Completed' : 'Pending';
                                    $sampler = $lab->sampler ? trim($lab->sampler->firstname . ' ' . ($lab->sampler->surname ?? '')) : null;
                                    $resultBy = $lab->resultBy ? trim($lab->resultBy->firstname . ' ' . ($lab->resultBy->surname ?? '')) : null;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-flask me-1 text-primary"></i> <strong>Lab Request:</strong> #{{ $lab->lab_number ?? $lab->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    <div>Status: <span class="badge bg-{{ $lab->status == 1 ? 'success' : 'warning' }} px-1 py-0">{{ $labStatus }}</span></div>
                                    @if($sampler) <div class="text-muted"><i class="mdi mdi-test-tube me-1"></i> Sampled By: {{ $sampler }}</div> @endif
                                    @if($resultBy) <div class="text-muted"><i class="mdi mdi-check-decagram me-1"></i> Result By: {{ $resultBy }}</div> @endif
                                </div>
                            @endif
                            @if($req->imagingRequest)
                                @php
                                    $img = $req->imagingRequest;
                                    $imgStatus = $img->status == 1 ? 'Completed' : 'Pending';
                                    $resultBy = $img->resultBy ? trim($img->resultBy->firstname . ' ' . ($img->resultBy->surname ?? '')) : null;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-radiology me-1 text-primary"></i> <strong>Imaging Request:</strong> #{{ $img->radiology_number ?? $img->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    <div>Status: <span class="badge bg-{{ $img->status == 1 ? 'success' : 'warning' }} px-1 py-0">{{ $imgStatus }}</span></div>
                                    @if($resultBy) <div class="text-muted"><i class="mdi mdi-check-decagram me-1"></i> Result By: {{ $resultBy }}</div> @endif
                                </div>
                            @endif
                            @if($req->productRequest)
                                @php
                                    $rx = $req->productRequest;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-pill me-1 text-primary"></i> <strong>Prescription:</strong> #{{ $rx->prescription_number ?? $rx->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    <div>Status: <span class="badge bg-{{ $rx->status == 1 ? 'success' : 'warning' }} px-1 py-0">{{ $rx->status == 1 ? 'Dispensed' : 'Pending' }}</span></div>
                                    @if($rx->dose) <div class="text-muted"><i class="mdi mdi-information-outline me-1"></i> Dose: {{ $rx->dose }}</div> @endif
                                </div>
                            @endif
                            @if($req->procedure)
                                @php
                                    $proc = $req->procedure;
                                    $requestedBy = $proc->requestedByUser ? trim($proc->requestedByUser->firstname . ' ' . ($proc->requestedByUser->surname ?? '')) : null;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-needle me-1 text-primary"></i> <strong>Procedure:</strong> #{{ $proc->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    <div>Status: <span class="badge bg-{{ $proc->status == 'completed' ? 'success' : ($proc->status == 'cancelled' ? 'danger' : 'warning') }} px-1 py-0">{{ ucfirst($proc->status ?? 'pending') }}</span></div>
                                    @if($proc->outcome) <div class="text-muted">Outcome: {{ ucfirst($proc->outcome) }}</div> @endif
                                    @if($requestedBy) <div class="text-muted"><i class="mdi mdi-account me-1"></i> Requested By: {{ $requestedBy }}</div> @endif
                                </div>
                            @endif
                            @if($req->encounter)
                                @php
                                    $enc = $req->encounter;
                                    $doctor = $enc->doctor ? trim($enc->doctor->firstname . ' ' . ($enc->doctor->surname ?? '')) : null;
                                    $clinicName = $enc->queue && $enc->queue->clinic ? $enc->queue->clinic->name : null;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-stethoscope me-1 text-primary"></i> <strong>Encounter:</strong> #{{ $enc->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    @if($doctor) <div class="text-muted"><i class="mdi mdi-doctor me-1"></i> Doctor: Dr. {{ $doctor }}</div> @endif
                                    @if($clinicName) <div class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> Clinic: {{ $clinicName }}</div> @endif
                                    <div class="text-muted"><i class="mdi mdi-calendar me-1"></i> Date: {{ $enc->created_at ? $enc->created_at->format('d M y H:i') : '-' }}</div>
                                </div>
                            @elseif($req->doctor_queue_entry)
                                @php
                                    $queue = $req->doctor_queue_entry;
                                    $clinic = $queue->clinic ? $queue->clinic->name : null;
                                    $queueDoc = $queue->doctor ? trim($queue->doctor->firstname . ' ' . ($queue->doctor->surname ?? '')) : null;
                                @endphp
                                <div class="text-muted mt-1"><i class="mdi mdi-stethoscope me-1 text-primary"></i> <strong>Consultation Queue:</strong> #{{ $queue->id }}</div>
                                <div class="ps-3 ms-2 mb-1 border-start border-2 border-light">
                                    @if($clinic) <div class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> Clinic: {{ $clinic }}</div> @endif
                                    @if($queueDoc) <div class="text-muted"><i class="mdi mdi-doctor me-1"></i> Doctor: Dr. {{ $queueDoc }}</div> @endif
                                    <div class="text-muted"><i class="mdi mdi-calendar me-1"></i> Queued At: {{ $queue->created_at ? $queue->created_at->format('d M y H:i') : '-' }}</div>
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="text-center">{{ $req->qty }}</td>
                    <td class="text-end">{{ number_format($computedAmount, 2) }}</td>
                    <td class="text-end text-success">{{ number_format($payable, 2) }}</td>
                    <td class="text-end text-info">{{ number_format($claims, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="mdi mdi-flask-empty-outline fs-4 d-block mb-1"></i> No items found for this payment.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($payment->product_or_service_request->count() > 0)
        <tfoot class="fw-bold bg-light">
            <tr>
                <td colspan="4" class="text-end">Grand Total:</td>
                <td class="text-end fs-6">{{ number_format($sumAmount, 2) }}</td>
                <td class="text-end text-success fs-6">{{ number_format($sumPayable, 2) }}</td>
                <td class="text-end text-info fs-6">{{ number_format($sumClaims, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
</div>

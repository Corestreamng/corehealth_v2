<div class="container-fluid p-4">
    <div class="row mb-4 bg-light rounded p-3 align-items-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-3">
                @php
                    $statusColor = 'secondary';
                    if($requisition->status === 'approved') $statusColor = 'primary';
                    if($requisition->status === 'fulfilled') $statusColor = 'success';
                    if($requisition->status === 'rejected') $statusColor = 'danger';
                    if($requisition->status === 'partial') $statusColor = 'warning';
                @endphp
                <div class="badge bg-white text-{{ $statusColor }} border border-{{ $statusColor }} p-2 fs-6 text-uppercase">
                    <i class="mdi mdi-circle-medium"></i> {{ $requisition->status }}
                </div>
                <div class="badge bg-white text-dark border p-2 fs-6">
                    <strong>#{{ $requisition->requisition_number }}</strong>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-muted small"><i class="mdi mdi-store"></i> Route</div>
                <div class="font-weight-bold">
                    <span class="text-primary">{{ optional($requisition->fromStore)->store_name ?? 'Unknown' }}</span> 
                    @if($requisition->fromStore)
                        <small class="text-muted">({{ $requisition->fromStore->distributionRoleLabel() }})</small>
                    @endif
                    <i class="mdi mdi-arrow-right mx-1 text-muted"></i> 
                    <span class="text-success">{{ optional($requisition->toStore)->store_name ?? 'Unknown' }}</span>
                    @if($requisition->toStore)
                        <small class="text-muted">({{ $requisition->toStore->distributionRoleLabel() }})</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <div class="text-muted small"><i class="mdi mdi-account-edit"></i> Requested By</div>
            <div class="font-weight-bold">{{ optional($requisition->requester)->firstname }} {{ optional($requisition->requester)->surname }} <br><small class="text-muted">{{ $requisition->created_at->format('d/m/Y H:i') }}</small></div>
            
            @if($requisition->approved_at)
            <div class="text-muted small mt-2"><i class="mdi mdi-check-decagram text-primary"></i> Approved By</div>
            <div class="font-weight-bold">{{ optional($requisition->approver)->firstname }} {{ optional($requisition->approver)->surname }} <br><small class="text-muted">{{ $requisition->approved_at->format('d/m/Y H:i') }}</small></div>
            @endif
            
            @if($requisition->fulfilled_at)
            <div class="text-muted small mt-2"><i class="mdi mdi-truck-delivery text-success"></i> Fulfilled By</div>
            <div class="font-weight-bold">{{ optional($requisition->fulfiller)->firstname }} {{ optional($requisition->fulfiller)->surname }} <br><small class="text-muted">{{ $requisition->fulfilled_at->format('d/m/Y H:i') }}</small></div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h6 class="font-weight-bold mb-3 text-secondary"><i class="mdi mdi-format-list-bulleted me-1"></i> Requisition Items</h6>
            <div class="table-responsive shadow-sm border rounded">
                <table class="table table-hover align-middle mb-0 bg-white">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Requested</th>
                            <th class="text-end">Approved</th>
                            <th class="text-end">Fulfilled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requisition->items as $item)
                            @php
                                $pkg = $item->packaging ?? optional($item->product)->packagings->firstWhere('is_default_purchase', true) ?? optional($item->product)->packagings->first();
                                $baseName = optional($item->product)->base_unit_name ?? 'unit';
                                
                                $formatQty = function($baseQty) use ($pkg, $baseName) {
                                    if (!$baseQty) return '<span class="text-muted">0</span>';
                                    if ($pkg && $pkg->base_unit_qty > 0) {
                                        $pQty = $baseQty / $pkg->base_unit_qty;
                                        $pkgName = \Illuminate\Support\Str::plural($pkg->name, $pQty);
                                        $bName = \Illuminate\Support\Str::plural($baseName, $baseQty);
                                        $formattedP = rtrim(rtrim(number_format($pQty, 2), '0'), '.');
                                        return "<span class='fw-bold text-dark'>{$formattedP} {$pkgName}</span> <br><small class='text-muted'>({$baseQty} {$bName})</small>";
                                    }
                                    $bName = \Illuminate\Support\Str::plural($baseName, $baseQty);
                                    return "<span class='fw-bold text-dark'>{$baseQty} {$bName}</span>";
                                };

                                $itemStatusColor = 'secondary';
                                if($item->status === 'approved') $itemStatusColor = 'primary';
                                if($item->status === 'fulfilled') $itemStatusColor = 'success';
                                if($item->status === 'rejected') $itemStatusColor = 'danger';
                                if($item->status === 'partial') $itemStatusColor = 'warning';
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ optional($item->product)->product_name }}</div>
                                    @if($item->notes)
                                        <small class="text-muted"><i class="mdi mdi-note-text"></i> {{ $item->notes }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $itemStatusColor }}">{{ ucfirst($item->status) }}</span>
                                </td>
                                <td class="text-end">{!! $formatQty($item->requested_qty) !!}</td>
                                <td class="text-end">{!! $formatQty($item->approved_qty) !!}</td>
                                <td class="text-end">{!! $formatQty($item->fulfilled_qty) !!}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted p-4">No items found for this requisition.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

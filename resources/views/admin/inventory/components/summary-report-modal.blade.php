{{-- Shared Dispense & Requisition Summary Modal --}}
<div class="modal fade" id="{{ $modalId ?? 'summaryReportsModal' }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId ?? 'summaryReportsModal' }}Label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" id="{{ $modalId ?? 'summaryReportsModal' }}Label">
                    <i class="mdi mdi-chart-donut text-primary mr-2"></i> {{ $modalTitle ?? 'Dispense & Requisition Summary' }}
                </h5>
                <button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                @php
                    $tabPrefix = $tabPrefix ?? 'modal-sr';
                @endphp
                <ul class="nav nav-tabs nav-tabs-custom nav-justified px-3 pt-3 mb-0 border-bottom-0" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" data-toggle="tab" data-bs-toggle="tab" href="#{{ $tabPrefix }}-given" data-bs-target="#{{ $tabPrefix }}-given" role="tab">
                            <i class="mdi mdi-arrow-up-bold text-danger mr-1"></i> Stock Given Out (Dispensed/Transferred)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" data-toggle="tab" data-bs-toggle="tab" href="#{{ $tabPrefix }}-received" data-bs-target="#{{ $tabPrefix }}-received" role="tab">
                            <i class="mdi mdi-arrow-down-bold text-success mr-1"></i> Stock Received
                        </a>
                    </li>
                </ul>
                <div class="tab-content p-4">
                    <div class="tab-pane fade show active" id="{{ $tabPrefix }}-given" role="tabpanel">
                        @include('admin.inventory.components.summary-report-ui', [
                            'storeIds' => $storeIds ?? '',
                            'storeName' => $storeName ?? 'All Stores',
                            'mode' => 'given'
                        ])
                    </div>
                    <div class="tab-pane fade" id="{{ $tabPrefix }}-received" role="tabpanel">
                        @include('admin.inventory.components.summary-report-ui', [
                            'storeIds' => $storeIds ?? '',
                            'storeName' => $storeName ?? 'All Stores',
                            'mode' => 'received'
                        ])
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

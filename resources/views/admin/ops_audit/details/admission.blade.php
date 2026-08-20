<div class="container-fluid p-4">
    <div class="row mb-4 bg-light rounded p-3 align-items-center">
        <div class="col-md-6">
            <div class="d-flex align-items-center gap-3">
                @if($data['admission']['status'] === 'admitted')
                    <div class="badge bg-white text-success border border-success p-2 fs-6">
                        <i class="mdi mdi-bed"></i> Admitted
                    </div>
                @else
                    <div class="badge bg-white text-secondary border border-secondary p-2 fs-6">
                        <i class="mdi mdi-logout"></i> Discharged
                    </div>
                @endif
                <div class="badge bg-white text-dark border p-2 fs-6">
                    <i class="mdi mdi-calendar-clock text-primary"></i> LOS: {{ $data['admission']['los'] }}
                </div>
            </div>
            <div class="mt-3">
                <div class="text-muted small"><i class="mdi mdi-map-marker"></i> Ward & Bed</div>
                <div class="font-weight-bold">{{ $data['admission']['ward'] }} — {{ $data['admission']['bed'] }}</div>
            </div>
        </div>
        <div class="col-md-6 text-end">
            <div class="text-muted small"><i class="mdi mdi-login text-success"></i> Admitted Date</div>
            <div class="font-weight-bold">{{ $data['admission']['admitted_date'] }}</div>
            <div class="text-muted small mt-2"><i class="mdi mdi-logout text-danger"></i> Discharged Date</div>
            <div class="font-weight-bold">{{ $data['admission']['discharge_date'] }}</div>
        </div>
    </div>

    <div class="row">
        <!-- Totals & HMO summary on the left -->
        <div class="col-md-5">
            <div class="card-modern shadow-sm border-0 mb-3">
                <div class="card-modern-body bg-dark text-white rounded">
                    <h6 class="text-white-50 border-bottom border-secondary pb-2 mb-3"><i class="mdi mdi-cash-register me-1"></i> Billing Totals</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Gross Total</span>
                        <span>₦{{ number_format($data['totals']['gross'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-warning">
                        <span>Discount</span>
                        <span>- ₦{{ number_format($data['totals']['discount'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-info">
                        <span>HMO Covered</span>
                        <span>- ₦{{ number_format($data['totals']['hmo'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Paid (Cash/Transfer)</span>
                        <span>- ₦{{ number_format($data['totals']['paid'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 mt-2 border-top border-secondary font-weight-bold fs-5">
                        <span>Outstanding</span>
                        <span class="text-danger">₦{{ number_format($data['totals']['balance'], 2) }}</span>
                    </div>
                </div>
            </div>

            @if(isset($data['hmo_claims']) && $data['hmo_claims']['total_items'] > 0)
            <div class="card-modern shadow-sm border-0 border-start border-success border-4 mb-3 bg-light">
                <div class="card-modern-body">
                    <h6 class="font-weight-bold border-bottom pb-2 mb-3 text-success"><i class="mdi mdi-shield-check me-1"></i> HMO Claims Summary</h6>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Total Claim Items</span>
                        <span class="font-weight-bold">{{ $data['hmo_claims']['total_items'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Approved</span>
                        <span class="text-success font-weight-bold">{{ $data['hmo_claims']['approved'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Pending</span>
                        <span class="text-warning font-weight-bold">{{ $data['hmo_claims']['pending'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Rejected</span>
                        <span class="text-danger font-weight-bold">{{ $data['hmo_claims']['rejected'] }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Categories on the right -->
        <div class="col-md-7">
            <h6 class="font-weight-bold mb-3 text-secondary"><i class="mdi mdi-format-list-bulleted me-1"></i> Bill Items Breakdown</h6>
            <div class="d-flex flex-column gap-3" id="accordionCategories">
                @foreach($data['categories'] as $index => $cat)
                <div class="card-modern border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-modern-header bg-white border-0 p-0" id="heading-{{ $index }}">
                        <button class="btn btn-link w-100 text-decoration-none text-start p-3 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" style="box-shadow: none;">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="mdi {{ $cat['icon'] }} fs-5"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-dark" style="font-size:1.05rem;">{{ $cat['name'] }}</div>
                                    <small class="text-muted">{{ $cat['count'] }} {{ Str::plural('Item', $cat['count']) }}</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="font-weight-bold text-dark fs-6">₦{{ number_format($cat['total'], 2) }}</div>
                                <i class="mdi mdi-chevron-down text-muted fs-4"></i>
                            </div>
                        </button>
                    </div>
                    <div id="collapse-{{ $index }}" class="collapse bg-light border-top" data-bs-parent="#accordionCategories">
                        <div class="card-modern-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach($cat['items'] as $item)
                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3">
                                        <div>
                                            <div class="font-weight-bold text-dark" style="font-size:0.95rem;">{{ $item['name'] }}</div>
                                            <small class="text-muted"><i class="mdi mdi-clock-outline me-1"></i>{{ $item['date'] }} &bull; Qty: {{ $item['qty'] }}</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="font-weight-bold text-dark mb-1">₦{{ number_format($item['amount'], 2) }}</div>
                                            @if($item['paid'])
                                                <span class="badge bg-success text-white py-1 px-2 shadow-sm" style="font-size:0.75rem;"><i class="mdi mdi-check-circle me-1"></i>Paid</span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

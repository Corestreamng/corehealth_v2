<div class="container pt-3 pb-4">
    <div class="mb-4 bg-light rounded p-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                @if($data['admission']['status'] === 'admitted')
                <div class="badge bg-white text-success border border-success p-2 fs-6">
                    <i class="mdi mdi-hotel"></i> Admitted
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

    <div class="row mb-4">
        <!-- Totals on the left -->
        <div class="col-md-6">
            <div class="card-modern shadow-sm border-0 h-100">
                <div class="card-modern-body p-3 bg-dark text-white rounded h-100">
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
        </div>

        <!-- HMO summary on the right -->
        <div class="col-md-6">
            @if(isset($data['hmo_claims']) && $data['hmo_claims']['total_items'] > 0)
            <div class="card-modern shadow-sm border-0 border-start border-success border-4 bg-light h-100">
                <div class="card-modern-body p-3">
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
    </div>

    <div class="row">
        <!-- Timeline and Filters full width -->
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <h6 class="font-weight-bold mb-0 text-secondary"><i class="mdi mdi-timeline-clock-outline me-1"></i> Bill Items Timeline</h6>
            </div>

            <!-- Localized Filters -->
            <div class="row g-2 mb-3">
                <div class="col-md">
                    <select class="form-select form-select-sm border-secondary timeline-filter" id="filter-category">
                        <option value="">All Categories</option>
                        @foreach($data['filters']['categories'] as $key => $val)
                        <option value="{{ $key }}">{{ $val }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="col-md">
                    <select class="form-select form-select-sm border-secondary timeline-filter" id="filter-cashier">
                        <option value="">All Cashiers</option>
                        @foreach($data['filters']['cashiers'] as $cashier)
                        <option value="{{ $cashier }}">{{ $cashier }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md">
                    <select class="form-select form-select-sm border-secondary timeline-filter" id="filter-method">
                        <option value="">All Pay Methods</option>
                        @foreach($data['filters']['payment_methods'] as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md">
                    <select class="form-select form-select-sm border-secondary timeline-filter" id="filter-bank">
                        <option value="">All Banks</option>
                        @foreach($data['filters']['banks'] as $bank)
                        <option value="{{ $bank }}">{{ $bank }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md">
                    <select class="form-select form-select-sm border-secondary timeline-filter" id="filter-billed">
                        <option value="">All Orgs/Staff/HMO</option>
                        @foreach($data['filters']['billed_to'] as $billed)
                        <option value="{{ $billed }}">{{ $billed }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="timeline-container position-relative" style="margin-left: 0.5rem; margin-top: 1.5rem;" id="billing-timeline">
                <!-- Vertical Line -->
                <div class="position-absolute h-100 border-left border-primary" style="left: 11px; top: 15px; z-index: 0; border-left-width: 2px !important; opacity: 0.4;"></div>

                @forelse($data['timeline'] as $dayKey => $day)
                <div class="timeline-day-group mb-5 position-relative" data-day="{{ $dayKey }}">
                    <!-- Timeline Dot -->
                    <div class="position-absolute bg-primary rounded-circle shadow-sm" style="width: 16px; height: 16px; left: 4px; top: 8px; border: 3px solid #fff; z-index: 1;"></div>
                    
                    <div style="padding-left: 2.5rem;">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary text-white rounded-pill px-3 py-1 font-weight-bold me-2" style="font-size: 0.85rem;">
                                Day {{ $day['day_number'] }}
                            </div>
                            <div class="text-dark font-weight-bold" style="font-size: 0.95rem;">{{ $day['date'] }}</div>
                            <div class="ms-auto font-weight-bold text-muted" style="font-size: 0.9rem;">
                                Day Total: ₦<span class="day-total">{{ number_format($day['total'], 2) }}</span>
                            </div>
                        </div>

                        <div class="timeline-day-content mb-4" id="accordion-{{ $dayKey }}">
                            @foreach($day['categories'] as $catIndex => $cat)
                            <div class="card shadow-sm border-light mb-3 timeline-category-group" data-category="{{ $catIndex }}">
                                <div class="card-header bg-white p-0 border-0" id="heading-{{ $dayKey }}-{{ $catIndex }}">
                                    <button class="btn btn-link w-100 text-decoration-none text-start py-3 px-3 d-flex justify-content-between align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $dayKey }}-{{ $catIndex }}" aria-expanded="{{ $loop->parent->first && $loop->first ? 'true' : 'false' }}" style="box-shadow: none;">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 text-primary" style="width: 36px; height: 36px; background-color: #eff6ff;">
                                                <i class="mdi {{ $cat['icon'] }} fs-5"></i>
                                            </div>
                                            <div class="font-weight-bold text-dark" style="font-size: 0.95rem;">{{ $cat['name'] }}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="font-weight-bold text-dark me-3" style="font-size: 0.95rem;">₦{{ number_format($cat['total'], 2) }}</div>
                                            <span class="small text-muted fst-italic" style="font-size: 0.75rem;">Click to expand</span>
                                            <i class="mdi mdi-chevron-down text-muted fs-5"></i>
                                        </div>
                                    </button>
                                </div>
                                <div id="collapse-{{ $dayKey }}-{{ $catIndex }}" class="collapse {{ $loop->parent->first && $loop->first ? 'show' : '' }} bg-white border-top border-light" data-bs-parent="#accordion-{{ $dayKey }}">
                                <div class="p-0">
                                    <ul class="list-group list-group-flush">
                                    @foreach($cat['items'] as $item)
                                    <li class="list-group-item bg-transparent p-3 timeline-item"
                                        data-cashier="{{ $item['cashier'] }}"
                                        data-method="{{ $item['payment_method'] }}"
                                        data-bank="{{ $item['bank'] }}"
                                        data-billed="{{ $item['billed_to'] }}"
                                        data-amount="{{ $item['amount'] }}">

                                        <div class="row align-items-center">
                                            <div class="col-md-5">
                                                <div class="font-weight-bold text-dark" style="font-size:0.95rem;">{{ $item['name'] }}</div>
                                                <small class="text-muted"><i class="mdi mdi-clock-outline me-1"></i>{{ $item['date'] }} &bull; Qty: {{ $item['qty'] }}</small>
                                                @include('admin.ops_audit.partials.posr_properties', ['req' => $item['model']])
                                            </div>
                                            <div class="col-md-4 border-start px-3">
                                                @if($item['payment_method'])
                                                <div style="font-size: 0.75rem; line-height: 1.3;">
                                                    <div class="mb-1"><strong>Method:</strong> {{ $item['payment_method'] }}</div>
                                                    @if($item['bank'])
                                                    <div class="text-success fw-bold"><i class="mdi mdi-bank me-1"></i>{{ $item['bank'] }}</div>
                                                    @endif
                                                    @if($item['billed_to'])
                                                    <div class="text-info fw-bold"><i class="mdi mdi-domain me-1"></i>{{ $item['billed_to'] }}</div>
                                                    @endif
                                                    @if($item['cashier'])
                                                    <div class="mt-1 text-muted"><strong>Cashier:</strong> {{ $item['cashier'] }}</div>
                                                    @endif
                                                </div>
                                                @elseif($item['hmo_claims'])
                                                <div style="font-size: 0.75rem; line-height: 1.3;">
                                                    <div class="mb-1 text-warning fw-bold"><i class="mdi mdi-shield-plus me-1"></i>HMO Claim</div>
                                                    <div><strong>Amount:</strong> ₦{{ number_format($item['hmo_claims'], 2) }}</div>
                                                </div>
                                                @else
                                                <span class="text-muted small">- No Payment -</span>
                                                @endif
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <div class="font-weight-bold text-dark mb-1 fs-6">₦{{ number_format($item['amount'], 2) }}</div>
                                                @if($item['paid'])
                                                <span class="badge bg-success text-white py-1 px-2 shadow-sm" style="font-size:0.75rem;"><i class="mdi mdi-check-circle me-1"></i>Paid</span>
                                                @endif
                                            </div>
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
                @empty
                <div class="alert alert-info shadow-sm border-0"><i class="mdi mdi-information-outline me-1"></i> No billing items found for this admission.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('.timeline-filter').on('change', function() {
            let catFilter = $('#filter-category').val();
            let cashFilter = $('#filter-cashier').val();
            let methFilter = $('#filter-method').val();
            let bankFilter = $('#filter-bank').val();
            let billedFilter = $('#filter-billed').val();

            $('.timeline-day-group').each(function() {
                let dayGroup = $(this);
                let dayHasVisibleItems = false;
                let dayTotal = 0;

                dayGroup.find('.timeline-category-group').each(function() {
                    let catGroup = $(this);
                    let catVal = catGroup.data('category');

                    if (catFilter && catFilter !== String(catVal)) {
                        catGroup.hide();
                        return;
                    }

                    let catHasVisibleItems = false;

                    catGroup.find('.timeline-item').each(function() {
                        let item = $(this);
                        let iCash = String(item.data('cashier') || '');
                        let iMeth = String(item.data('method') || '');
                        let iBank = String(item.data('bank') || '');
                        let iBilled = String(item.data('billed') || '');
                        let iAmt = parseFloat(item.data('amount') || 0);

                        let show = true;
                        if (cashFilter && iCash !== cashFilter) show = false;
                        if (methFilter && iMeth !== methFilter) show = false;
                        if (bankFilter && iBank !== bankFilter) show = false;
                        if (billedFilter && iBilled !== billedFilter) show = false;

                        if (show) {
                            item.show();
                            catHasVisibleItems = true;
                            dayHasVisibleItems = true;
                            dayTotal += iAmt;
                        } else {
                            item.hide();
                        }
                    });

                    if (catHasVisibleItems) {
                        catGroup.show();
                    } else {
                        catGroup.hide();
                    }
                });

                if (dayHasVisibleItems) {
                    dayGroup.show();
                    dayGroup.find('.day-total').text(dayTotal.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                } else {
                    dayGroup.hide();
                }
            });
        });
    });
</script>
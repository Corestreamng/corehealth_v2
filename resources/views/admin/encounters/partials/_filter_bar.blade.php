{{-- Filter Bar Partial — Encounter Intelligence Workbench --}}
<div class="card-modern ewb-filter-card mb-3" id="ewb-filter-bar">
    <div class="card-body py-2 px-3">
        <div class="row align-items-end g-2">

            {{-- Date Range --}}
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">Date From</label>
                <input type="date" class="form-control form-control-sm ewb-filter" id="ewb-f-date-from"
                    value="{{ date('Y-m-d', strtotime('-30 days')) }}">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">Date To</label>
                <input type="date" class="form-control form-control-sm ewb-filter" id="ewb-f-date-to"
                    value="{{ date('Y-m-d') }}">
            </div>

            {{-- Quick Range --}}
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">Quick Range</label>
                <select class="form-control form-control-sm ewb-filter" id="ewb-f-quick-range">
                    <option value="">Custom</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month" selected>This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="quarter">This Quarter</option>
                    <option value="year">This Year</option>
                </select>
            </div>

            {{-- Clinic --}}
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">Clinic</label>
                <select class="form-control form-control-sm ewb-filter" id="ewb-f-clinic">
                    <option value="">All Clinics</option>
                    @foreach($clinics as $clinic)
                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Doctor (admin/accounts only) --}}
            @if($canViewAnalytics || $canViewRevenue)
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">Doctor</label>
                <select class="form-control form-control-sm ewb-filter" id="ewb-f-doctor">
                    <option value="">All Doctors</option>
                    @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ trim($doc->surname . ' ' . $doc->firstname . ' ' . ($doc->othername ?? '')) }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- HMO --}}
            <div class="col-md-2 col-sm-6">
                <label class="ewb-filter-label">HMO / Payer</label>
                <select class="form-control form-control-sm ewb-filter" id="ewb-f-hmo">
                    <option value="">All Payers</option>
                    @foreach($hmos as $hmo)
                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Status --}}
            <div class="col-md-1 col-sm-4">
                <label class="ewb-filter-label">Status</label>
                <select class="form-control form-control-sm ewb-filter" id="ewb-f-status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

        </div>

        {{-- Tag Filters Row --}}
        <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
            <span class="ewb-filter-label me-1">Filter by tag:</span>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-has-lab" data-key="has_lab"> <span class="ewb-tag-pill ewb-tag-lab"><i class="mdi mdi-flask-outline"></i> Lab</span></label>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-has-imaging" data-key="has_imaging"> <span class="ewb-tag-pill ewb-tag-imaging"><i class="mdi mdi-radiobox-marked"></i> Imaging</span></label>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-has-rx" data-key="has_prescription"> <span class="ewb-tag-pill ewb-tag-rx"><i class="mdi mdi-pill"></i> Rx</span></label>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-has-referral" data-key="has_referral"> <span class="ewb-tag-pill ewb-tag-referral"><i class="mdi mdi-share-variant"></i> Referral</span></label>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-has-admission" data-key="has_admission"> <span class="ewb-tag-pill ewb-tag-admission"><i class="mdi mdi-bed"></i> Admitted</span></label>
            <label class="ewb-tag-check"><input type="checkbox" class="ewb-filter-tag" id="ewb-f-new-patient" data-key="is_new_patient"> <span class="ewb-tag-pill ewb-tag-new"><i class="mdi mdi-account-plus-outline"></i> New Patient</span></label>

            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" id="ewb-btn-clear">
                    <i class="mdi mdi-close-circle-outline"></i> Clear
                </button>
                <button class="btn btn-sm btn-primary ewb-apply-btn" id="ewb-btn-apply">
                    <i class="mdi mdi-filter"></i> Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>

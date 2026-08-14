<div class="card shadow-sm border-0 mb-4 bg-white rounded-3">
    <div class="card-body p-4">
        <form id="audit_period_form" method="GET" action="{{ url()->current() }}" class="m-0">
            
            <div class="row g-3 mb-3">
                {{-- Quick Shift Selector --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-clock-outline"></i> Shift / Preset</label>
                    <select id="quick_shift_selector" class="form-select form-select-sm form-control-modern border-secondary text-dark">
                        <option value="">-- Custom Range --</option>
                        <option value="morning">Today Morning (08:00 - 15:59)</option>
                        <option value="evening">Today Evening (16:00 - 23:59)</option>
                        <option value="night_yesterday">Yesterday Night (00:00 - 07:59)</option>
                        <option value="night_today">Today Night (00:00 - 07:59)</option>
                        <option value="today">Entire Today (00:00 - 23:59)</option>
                        <option value="yesterday">Entire Yesterday (00:00 - 23:59)</option>
                    </select>
                </div>

                {{-- From Date & Time --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold">From</label>
                    <input type="datetime-local" name="start_date" id="filter_start_date" class="form-control form-control-sm form-control-modern border-secondary" value="{{ request('start_date', isset($startDate) ? $startDate->format('Y-m-d\TH:i') : now()->startOfDay()->format('Y-m-d\TH:i')) }}">
                </div>

                {{-- To Date & Time --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold">To</label>
                    <input type="datetime-local" name="end_date" id="filter_end_date" class="form-control form-control-sm form-control-modern border-secondary" value="{{ request('end_date', isset($endDate) ? $endDate->format('Y-m-d\TH:i') : now()->endOfDay()->format('Y-m-d\TH:i')) }}">
                </div>

                {{-- Audit Status --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-list-status"></i> Audit Status</label>
                    <select name="audit_status" id="filter_audit_status" class="form-select form-select-sm form-control-modern border-secondary" onchange="this.form.submit()">
                        <option value="all" {{ request('audit_status') == 'all' ? 'selected' : '' }}>All Records</option>
                        <option value="not_audited" {{ request('audit_status') == 'not_audited' ? 'selected' : '' }}>Not Audited</option>
                        <option value="audited" {{ request('audit_status') == 'audited' ? 'selected' : '' }}>Audited</option>
                        <option value="queried" {{ request('audit_status') == 'queried' ? 'selected' : '' }}>Queried</option>
                        <option value="resolved_audited" {{ request('audit_status') == 'resolved_audited' ? 'selected' : '' }}>Resolved</option>
                    </select>
                </div>
            </div>

            <div class="row g-3">
                {{-- HMO Scheme Filter --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-layers-outline"></i> HMO Scheme</label>
                    <select name="hmo_scheme_id" id="filter_hmo_scheme_id" class="form-select form-select-sm form-control-modern border-secondary">
                        <option value="">All Schemes</option>
                        @if(isset($hmoSchemes))
                            @foreach($hmoSchemes as $scheme)
                                <option value="{{ $scheme->id }}" {{ request('hmo_scheme_id') == $scheme->id ? 'selected' : '' }}>
                                    {{ $scheme->name }} ({{ $scheme->code }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                {{-- HMO Provider Filter with optgroups by Scheme --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-hospital-building"></i> HMO Provider</label>
                    <select name="hmo_id" id="filter_hmo_id" class="form-select form-select-sm form-control-modern border-secondary">
                        <option value="">All HMOs</option>
                        @if(isset($hmoSchemes) && $hmoSchemes->count() > 0)
                            @foreach($hmoSchemes as $scheme)
                                @if($scheme->hmos && $scheme->hmos->count() > 0)
                                    <optgroup label="{{ $scheme->name }} ({{ $scheme->code }})">
                                        @foreach($scheme->hmos as $hmoItem)
                                            <option value="{{ $hmoItem->id }}" {{ request('hmo_id') == $hmoItem->id ? 'selected' : '' }}>
                                                {{ $hmoItem->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        @endif
                        @if(isset($unassignedHmos) && $unassignedHmos->count() > 0)
                            <optgroup label="General / Unassigned">
                                @foreach($unassignedHmos as $unHmo)
                                    <option value="{{ $unHmo->id }}" {{ request('hmo_id') == $unHmo->id ? 'selected' : '' }}>
                                        {{ $unHmo->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @elseif(isset($hmos) && (!isset($hmoSchemes) || $hmoSchemes->count() == 0))
                            @foreach($hmos as $hmoItem)
                                <option value="{{ $hmoItem->id }}" {{ request('hmo_id') == $hmoItem->id ? 'selected' : '' }}>
                                    {{ $hmoItem->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                {{-- Gender Filter --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-gender-male-female"></i> Gender</label>
                    <select name="gender" id="filter_gender" class="form-select form-select-sm form-control-modern border-secondary">
                        <option value="">All</option>
                        <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>

                {{-- Age Range Filter --}}
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted font-weight-bold"><i class="mdi mdi-account-clock-outline"></i> Age Range</label>
                    <select name="age_range" id="filter_age_range" class="form-select form-select-sm form-control-modern border-secondary">
                        <option value="">All</option>
                        <option value="pediatric" {{ request('age_range') == 'pediatric' ? 'selected' : '' }}>Pediatric</option>
                        <option value="adult" {{ request('age_range') == 'adult' ? 'selected' : '' }}>Adult</option>
                        <option value="senior" {{ request('age_range') == 'senior' ? 'selected' : '' }}>Senior</option>
                    </select>
                </div>
            </div>

            {{-- Filter Action Buttons & Bulk Stamp --}}
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-sm btn-primary shadow-sm px-3"><i class="mdi mdi-filter"></i> Apply Filters</button>
                    <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary px-3"><i class="mdi mdi-refresh"></i> Reset</a>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm shadow-sm px-3" onclick="openUniversalStampModal('bulk')" id="btnBulkStamp" title="Mark all filtered items on this page as audited">
                        <i class="mdi mdi-check-all"></i> Stamp View
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

@push('audit_scripts')
<script>
    document.getElementById('quick_shift_selector').addEventListener('change', function() {
        const val = this.value;
        if (!val) return;

        const now = new Date();
        let start = new Date();
        let end = new Date();

        switch (val) {
            case 'morning':
                start.setHours(8, 0, 0, 0);
                end.setHours(15, 59, 59, 999);
                break;
            case 'evening':
                start.setHours(16, 0, 0, 0);
                end.setHours(23, 59, 59, 999);
                break;
            case 'night_yesterday':
                start.setDate(start.getDate() - 1);
                start.setHours(0, 0, 0, 0);
                end.setDate(end.getDate() - 1);
                end.setHours(7, 59, 59, 999);
                break;
            case 'night_today':
                start.setHours(0, 0, 0, 0);
                end.setHours(7, 59, 59, 999);
                break;
            case 'today':
                start.setHours(0, 0, 0, 0);
                end.setHours(23, 59, 59, 999);
                break;
            case 'yesterday':
                start.setDate(start.getDate() - 1);
                start.setHours(0, 0, 0, 0);
                end.setDate(end.getDate() - 1);
                end.setHours(23, 59, 59, 999);
                break;
        }

        const formatDt = (dt) => {
            const pad = (n) => n < 10 ? '0' + n : n;
            return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
        };

        document.getElementById('filter_start_date').value = formatDt(start);
        document.getElementById('filter_end_date').value = formatDt(end);
        document.getElementById('audit_period_form').submit();
    });


</script>
@endpush

@push('audit_styles')
<style>
    .form-control-modern {
        height: 45px !important;
        border-radius: 8px !important;
        border: 1px solid #E2E8F0 !important;
        background-color: #F9FAFB !important;
        font-size: 14px !important;
        color: #1F2937 !important;
        transition: all 0.2s ease-in-out !important;
    }
    .form-control-modern:focus {
        background-color: #ffffff !important;
        border-color: var(--primary-color, #0d6efd) !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15) !important;
        outline: none !important;
    }
    
    /* Ensure select arrow remains visible and properly aligned */
    select.form-control-modern {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
    }
</style>
@endpush

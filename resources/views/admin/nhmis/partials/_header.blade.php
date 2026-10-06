<div class="nhmis-header-card">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-2 border-bottom mb-3">
        <div class="nhmis-title-block">
            <h4>
                <i class="mdi mdi-file-chart text-primary"></i> 
                NHMIS Monthly Summary Form (NHMIS/HF/MSF)
            </h4>
            <p>
                <strong>Facility:</strong> {{ $report->facility_name ?? appsettings('hospitalname', 'CoreHealth Facility') }} |
                <strong>LGA:</strong> {{ $report->lga ?? 'Local Government Area' }} |
                <strong>State:</strong> {{ $report->state ?? 'Federal Republic of Nigeria' }} |
                <strong>Reporting Period:</strong> <span class="text-primary font-weight-bold">{{ $report->month_name }} {{ $report->year }}</span>
            </p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center gap-2">
            <span class="nhmis-badge-status nhmis-badge-{{ $report->status }}" id="nhmis-status-badge">
                <i class="mdi {{ $report->status === 'locked' ? 'mdi-lock' : ($report->status === 'verified' ? 'mdi-check-decagram' : ($report->status === 'compiled' ? 'mdi-calculator' : 'mdi-file-edit')) }}"></i>
                <span id="nhmis-status-text">{{ strtoupper($report->status) }}</span>
            </span>
        </div>
    </div>

    <div class="row align-items-end g-2">
        <div class="col-md-2 col-sm-4">
            <label for="nhmis-year-select" class="form-label small text-muted mb-1 font-weight-bold">Reporting Year</label>
            <select class="form-control form-control-sm" id="nhmis-year-select">
                @for ($y = now()->year + 1; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="col-md-2 col-sm-4">
            <label for="nhmis-month-select" class="form-label small text-muted mb-1 font-weight-bold">Reporting Period</label>
            <select class="form-control form-control-sm" id="nhmis-month-select">
                <option value="0" {{ $month == 0 ? 'selected' : '' }}>Full Year (Annual Summary)</option>
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}
                    </option>
                @endfor
                <option value="255" {{ $month == 255 ? 'selected' : '' }}>Custom Date Range</option>
            </select>
        </div>
        <div class="col-md-2 col-sm-4 custom-date-container" id="custom-date-start-col" style="{{ $month == 255 ? '' : 'display: none;' }}">
            <label for="nhmis-start-date" class="form-label small text-muted mb-1 font-weight-bold">Start Date</label>
            <input type="date" class="form-control form-control-sm" id="nhmis-start-date" value="{{ $report->start_date ? \Carbon\Carbon::parse($report->start_date)->format('Y-m-d') : '' }}">
        </div>
        <div class="col-md-2 col-sm-4 custom-date-container" id="custom-date-end-col" style="{{ $month == 255 ? '' : 'display: none;' }}">
            <label for="nhmis-end-date" class="form-label small text-muted mb-1 font-weight-bold">End Date</label>
            <input type="date" class="form-control form-control-sm" id="nhmis-end-date" value="{{ $report->end_date ? \Carbon\Carbon::parse($report->end_date)->format('Y-m-d') : '' }}">
        </div>
        <div class="col-md-2 col-sm-4">
            <label for="nhmis-version-select" class="form-label small text-muted mb-1 font-weight-bold">Form Revision</label>
            <select class="form-control form-control-sm" id="nhmis-version-select">
                @foreach ($availableVersions as $vKey => $vMeta)
                    <option value="{{ $vKey }}" {{ $vKey == $version ? 'selected' : '' }}>
                        {{ $vMeta['title'] }} ({{ $vMeta['code'] }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 col-sm-12 text-md-right mt-2 mt-md-0">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-dark" id="btn-service-mappings" title="Configure facility service delegations for lab investigations">
                    <i class="mdi mdi-flask-round-bottom"></i> Delegations
                </button>
                <button type="button" class="btn btn-outline-info" id="btn-clinical-audit" title="Search clinical encounters and diagnostic definitions">
                    <i class="mdi mdi-microscope"></i> Audit
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-print-report" title="Print this summary in official NHMIS format">
                    <i class="mdi mdi-printer"></i> Print
                </button>
                @if ($report->status !== 'locked')
                    <button type="button" class="btn btn-primary" id="btn-compile-report" title="Auto-populate counts from CoreHealth clinical encounters and registers">
                        <i class="mdi mdi-calculator"></i> 1-Click Auto Compile
                    </button>
                    <button type="button" class="btn btn-success" id="btn-save-report" title="Save any manually adjusted counts">
                        <i class="mdi mdi-content-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-warning" id="btn-lock-report" title="Verify numbers and lock report from further editing">
                        <i class="mdi mdi-lock-check"></i> Verify & Lock
                    </button>
                @else
                    <button type="button" class="btn btn-secondary" disabled title="Report is locked">
                        <i class="mdi mdi-lock"></i> Report Locked
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if ($report->compiled_at || $report->verified_at)
        <div class="nhmis-metadata-bar mt-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                @if ($report->compiled_at)
                    <span class="mr-3">
                        <i class="mdi mdi-clock-check-outline text-success"></i> 
                        <strong>Compiled:</strong> {{ $report->compiled_at->format('d M Y, h:i A') }} 
                        @if ($report->compiler) by {{ $report->compiler->surname }} {{ $report->compiler->firstname }} {{ $report->compiler->othername }} @endif
                    </span>
                @endif
                @if ($report->verified_at)
                    <span>
                        <i class="mdi mdi-shield-check text-primary"></i> 
                        <strong>Verified:</strong> {{ $report->verified_at->format('d M Y, h:i A') }} 
                        @if ($report->verifier) by {{ $report->verifier->surname }} {{ $report->verifier->firstname }} {{ $report->verifier->othername }} @endif
                    </span>
                @endif
            </div>
            <div id="nhmis-unsaved-badge" class="badge badge-warning" style="display: none;">
                <i class="mdi mdi-alert-circle"></i> Unsaved manual adjustments
            </div>
        </div>
    @endif
</div>

{{-- show1_nurse_chart.blade.php — Read-only nurse chart for patient profile --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="mdi mdi-heart-pulse me-2 text-primary"></i>Nurse Chart</h5>
    @if(\Route::has('nursing-workbench.index'))
        <a href="{{ route('nursing-workbench.index') }}?patient_id={{ $patient->id }}"
           class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="mdi mdi-heart-pulse me-1"></i> Open Nursing Workbench
        </a>
    @endif
</div>

<div class="alert alert-light border mb-3">
    <i class="mdi mdi-information-outline me-1 text-muted"></i>
    Read-only view of medication charts, intake/output records and nursing notes. Use the Nursing Workbench to record new entries.
</div>

{{-- Date Range Filter --}}
<div class="card border mb-3">
    <div class="card-header bg-light py-2">
        <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i>Date Range Filter</h6>
    </div>
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="s1nc_date_from" class="form-label small mb-1">From Date</label>
                <input type="date" class="form-control form-control-sm" id="s1nc_date_from">
            </div>
            <div class="col-md-4">
                <label for="s1nc_date_to" class="form-label small mb-1">To Date</label>
                <input type="date" class="form-control form-control-sm" id="s1nc_date_to">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="button" id="s1nc_apply_filter" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-filter me-1"></i> Apply
                </button>
                <button type="button" id="s1nc_reset_filter" class="btn btn-outline-secondary btn-sm">
                    <i class="mdi mdi-refresh me-1"></i> Reset
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Summary Stats --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small">
        <i class="mdi mdi-calendar me-1"></i>
        <span id="s1nc_date_summary">Showing data from the last 30 days</span>
    </div>
    <div id="s1nc_summary_stats" class="d-flex gap-2 flex-wrap"></div>
</div>

{{-- Sub-tabs --}}
<ul class="nav nav-tabs mb-3" id="s1nc_tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link active" id="s1nc_med_tab" data-toggle="tab" href="#s1nc_med_panel"
           role="tab" aria-selected="true">
            <i class="mdi mdi-pill me-1"></i> Medication Chart
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="s1nc_fluid_tab" data-toggle="tab" href="#s1nc_fluid_panel"
           role="tab" aria-selected="false">
            <i class="mdi mdi-water me-1"></i> Fluid I/O
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="s1nc_solid_tab" data-toggle="tab" href="#s1nc_solid_panel"
           role="tab" aria-selected="false">
            <i class="mdi mdi-food-apple me-1"></i> Solid I/O
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link" id="s1nc_notes_tab" data-toggle="tab" href="#s1nc_notes_panel"
           role="tab" aria-selected="false">
            <i class="mdi mdi-notebook me-1"></i> Nursing Notes
        </a>
    </li>
</ul>

<div class="tab-content" id="s1nc_tab_content">

    {{-- Medication Chart --}}
    <div class="tab-pane fade show active" id="s1nc_med_panel" role="tabpanel">
        <div id="s1nc_med_content">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Loading medication chart...</p>
            </div>
        </div>
    </div>

    {{-- Fluid I/O --}}
    <div class="tab-pane fade" id="s1nc_fluid_panel" role="tabpanel">
        <div id="s1nc_fluid_content">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Loading fluid intake/output chart...</p>
            </div>
        </div>
    </div>

    {{-- Solid I/O --}}
    <div class="tab-pane fade" id="s1nc_solid_panel" role="tabpanel">
        <div id="s1nc_solid_content">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Loading solid intake/output chart...</p>
            </div>
        </div>
    </div>

    {{-- Nursing Notes (all 5 types) --}}
    <div class="tab-pane fade" id="s1nc_notes_panel" role="tabpanel">
        <ul class="nav nav-pills mb-3" id="s1nc_note_type_tabs" role="tablist">
            @foreach([1 => 'General Notes', 2 => 'Fluid Notes', 3 => 'Input Notes', 4 => 'Output Notes', 5 => 'Other Notes'] as $typeId => $typeName)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $typeId == 1 ? 'active' : '' }}" id="s1nc_note_type_{{ $typeId }}_tab"
                       data-toggle="tab" href="#s1nc_note_type_{{ $typeId }}_panel"
                       role="tab" aria-selected="{{ $typeId == 1 ? 'true' : 'false' }}">
                        {{ $typeName }}
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="tab-content">
            @foreach([1 => 'General Notes', 2 => 'Fluid Notes', 3 => 'Input Notes', 4 => 'Output Notes', 5 => 'Other Notes'] as $typeId => $typeName)
                <div class="tab-pane fade {{ $typeId == 1 ? 'show active' : '' }}" id="s1nc_note_type_{{ $typeId }}_panel" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped"
                               style="width: 100%"
                               id="nurse_note_hist_{{ $typeId }}">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Note Type</th>
                                    <th>Details</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';

    var s1ncPatientId = {{ $patient->id }};

    // ── Date helpers ──────────────────────────────────────────────────────────
    function s1ncDefaultStart() {
        var d = new Date(); d.setDate(d.getDate() - 15);
        return d.toISOString().split('T')[0];
    }
    function s1ncDefaultEnd() {
        var d = new Date(); d.setDate(d.getDate() + 15);
        return d.toISOString().split('T')[0];
    }
    function s1ncFmtDisplay(str) {
        if (!str) return '';
        return new Date(str).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
    }
    function s1ncInitDates() {
        document.getElementById('s1nc_date_from').value = s1ncDefaultStart();
        document.getElementById('s1nc_date_to').value   = s1ncDefaultEnd();
    }
    function s1ncUpdateSummary(start, end) {
        var el = document.getElementById('s1nc_date_summary');
        if (start && end) el.textContent = 'Showing data from ' + s1ncFmtDisplay(start) + ' to ' + s1ncFmtDisplay(end);
        else if (start)   el.textContent = 'Showing data from ' + s1ncFmtDisplay(start) + ' onwards';
        else if (end)     el.textContent = 'Showing data up to ' + s1ncFmtDisplay(end);
        else              el.textContent = 'Showing all data';
    }

    // ── Load all charts ───────────────────────────────────────────────────────
    function s1ncLoad() {
        if (!document.getElementById('s1nc_date_from').value) s1ncInitDates();
        var start = document.getElementById('s1nc_date_from').value;
        var end   = document.getElementById('s1nc_date_to').value;
        s1ncUpdateSummary(start, end);
        s1ncLoadMedication(start, end);
        s1ncLoadIO(start, end);
    }

    // ── Medication chart ──────────────────────────────────────────────────────
    function s1ncLoadMedication(start, end) {
        var el = document.getElementById('s1nc_med_content');
        el.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
        var url = new URL('{{ url("/") }}/patients/' + s1ncPatientId + '/nurse-chart/medication');
        if (start) url.searchParams.append('start_date', start);
        if (end)   url.searchParams.append('end_date', end);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) { s1ncRenderMedCalendar(data, start, end); })
            .catch(function () { el.innerHTML = '<div class="alert alert-danger">Failed to load medication chart.</div>'; });
    }

    // ── I/O charts ────────────────────────────────────────────────────────────
    function s1ncLoadIO(start, end) {
        var fluidEl = document.getElementById('s1nc_fluid_content');
        var solidEl = document.getElementById('s1nc_solid_content');
        fluidEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
        solidEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></div>';
        var url = new URL('{{ url("/") }}/patients/' + s1ncPatientId + '/nurse-chart/intake-output');
        if (start) url.searchParams.append('start_date', start);
        if (end)   url.searchParams.append('end_date', end);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) { s1ncRenderIO(data, fluidEl, solidEl); })
            .catch(function () {
                fluidEl.innerHTML = '<div class="alert alert-danger">Failed to load fluid I/O chart.</div>';
                solidEl.innerHTML = '<div class="alert alert-danger">Failed to load solid I/O chart.</div>';
            });
    }

    function s1ncRenderIO(data, fluidEl, solidEl) {
        var fluidPeriods = (data && data.fluidPeriods) ? data.fluidPeriods : [];
        var solidPeriods = (data && data.solidPeriods) ? data.solidPeriods : [];
        fluidEl.innerHTML = s1ncBuildPeriodsTable(fluidPeriods, 'Fluid');
        solidEl.innerHTML = s1ncBuildPeriodsTable(solidPeriods, 'Solid');
    }

    function s1ncBuildPeriodsTable(periods, label) {
        if (!periods.length) return '<div class="alert alert-info">No ' + label.toLowerCase() + ' I/O records found for this period.</div>';
        var html = '';
        periods.forEach(function (period) {
            var startedAt = period.started_at ? new Date(period.started_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : 'N/A';
            var endedAt = period.ended_at ? new Date(period.ended_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '<span class="text-warning">Active</span>';
            var records = period.records || [];
            var rows = records.length ? records.map(function (r) {
                var dt = r.recorded_at ? new Date(r.recorded_at).toLocaleDateString('en-US',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : 'N/A';
                var typeBadge = r.type === 'intake'
                    ? '<span class="badge badge-success">Intake</span>'
                    : '<span class="badge badge-danger">Output</span>';
                return '<tr>'
                    + '<td>' + typeBadge + '</td>'
                    + '<td>' + (r.item_name || r.name || 'N/A') + '</td>'
                    + '<td><strong>' + (r.amount || '0') + '</strong> ' + (r.unit || 'mL') + '</td>'
                    + '<td>' + (r.notes || '<span class="text-muted">—</span>') + '</td>'
                    + '<td>' + dt + '</td>'
                    + '<td>' + (r.nurse_name || 'N/A') + '</td>'
                    + '</tr>';
            }).join('') : '<tr><td colspan="6" class="text-center text-muted">No records in this period</td></tr>';
            html += '<div class="card border mb-3">'
                + '<div class="card-header py-2 d-flex justify-content-between align-items-center">'
                + '<span><i class="mdi mdi-timer-outline me-1"></i>' + startedAt + ' &rarr; ' + endedAt + '</span>'
                + '<span class="ml-auto"><span class="badge badge-success mr-1">In: ' + (period.total_intake || 0) + ' mL</span> <span class="badge badge-danger">Out: ' + (period.total_output || 0) + ' mL</span></span>'
                + '<span class="text-muted small ml-2">Nurse: ' + (period.nurse_name || 'N/A') + '</span>'
                + '</div>'
                + '<div class="card-body p-0">'
                + '<div class="table-responsive">'
                + '<table class="table table-sm table-striped mb-0">'
                + '<thead class="table-light"><tr><th>Type</th><th>Item</th><th>Amount</th><th>Notes</th><th>Time</th><th>Nurse</th></tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '</table></div></div></div>';
        });
        return html;
    }

    // ── Medication calendar renderer ──────────────────────────────────────────
    var s1ncColors = [
        {bg:'#e3f2fd',border:'#1976d2',text:'#0d47a1'},
        {bg:'#e8f5e9',border:'#388e3c',text:'#1b5e20'},
        {bg:'#fff3e0',border:'#f57c00',text:'#e65100'},
        {bg:'#f3e5f5',border:'#8e24aa',text:'#6a1b9a'},
        {bg:'#e0f7fa',border:'#0097a7',text:'#006064'},
        {bg:'#fce4ec',border:'#c2185b',text:'#880e4f'},
        {bg:'#fff8e1',border:'#ffa000',text:'#ff6f00'},
        {bg:'#e8eaf6',border:'#3f51b5',text:'#1a237e'},
    ];

    function s1ncRenderMedCalendar(data, startStr, endStr) {
        var el = document.getElementById('s1nc_med_content');
        var prescriptions = data.prescriptions || [];
        var admins = data.administrations || [];

        // Build summary stats
        var statsHtml = '<span class="badge bg-primary rounded-pill">'
            + '<i class="mdi mdi-pill me-1"></i>' + prescriptions.length + ' medications</span>'
            + '<span class="badge bg-info rounded-pill">'
            + '<i class="mdi mdi-history me-1"></i>' + admins.length + ' administrations</span>';
        document.getElementById('s1nc_summary_stats').innerHTML = statsHtml;

        if (!prescriptions.length && !admins.length) {
            el.innerHTML = '<div class="alert alert-info">No medication records found for this period.</div>';
            return;
        }

        // Color map
        var colorMap = {};
        prescriptions.forEach(function (p, i) { colorMap[p.id] = s1ncColors[i % s1ncColors.length]; });

        // Build date range
        var start = startStr ? new Date(startStr) : new Date(new Date().setDate(new Date().getDate() - 15));
        var end   = endStr   ? new Date(endStr)   : new Date(new Date().setDate(new Date().getDate() + 15));
        start.setHours(0,0,0,0); end.setHours(23,59,59,999);
        var today = new Date(); today.setHours(0,0,0,0);

        // Build admin map: dateStr -> list of prescriptionId
        var adminMap = {};
        admins.forEach(function (a) {
            var d = a.administered_at || a.created_at;
            if (!d) return;
            var dateKey = d.split('T')[0].split(' ')[0];
            if (!adminMap[dateKey]) adminMap[dateKey] = [];
            adminMap[dateKey].push(a.product_or_service_request_id || a.id);
        });

        // Generate calendar weeks
        var calStart = new Date(start);
        calStart.setDate(calStart.getDate() - calStart.getDay()); // go to Sunday
        var calEnd = new Date(end);
        calEnd.setDate(calEnd.getDate() + (6 - calEnd.getDay())); // go to Saturday

        var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var headerHtml = '<div class="d-flex mb-1">'
            + dayNames.map(function (d) { return '<div class="flex-fill text-center small fw-bold text-muted border-bottom py-1" style="width:14.28%">' + d + '</div>'; }).join('')
            + '</div>';

        var weeksHtml = '';
        var cur = new Date(calStart);
        while (cur <= calEnd) {
            weeksHtml += '<div class="d-flex mb-1">';
            for (var dow = 0; dow < 7; dow++) {
                var inRange = cur>= start && cur <= end;
                var isToday = cur.getTime() === today.getTime();
                var dateKey = cur.toISOString().split('T')[0];
                var dayMeds = adminMap[dateKey] || [];
                var cellBg = !inRange ? '#f8f9fa' : isToday ? '#e3f2fd' : 'white';
                var border  = isToday ? '2px solid #2196F3' : '1px solid #dee2e6';

                var medBadges = '';
                if (inRange) {
                    // Show which prescriptions were administered
                    var shownPrescIds = {};
                    dayMeds.forEach(function (pid) {
                        if (shownPrescIds[pid]) return;
                        shownPrescIds[pid] = true;
                        var presc = prescriptions.find(function (p) { return p.id === pid; });
                        var color = colorMap[pid] || {bg:'#e9ecef',border:'#6c757d',text:'#495057'};
                        var name = presc ? ((presc.product && presc.product.product_name) || presc.product_name || presc.name || 'Med') : 'Med';
                        medBadges += '<div style="background:' + color.bg + ';border-left:3px solid ' + color.border + ';color:' + color.text + ';font-size:10px;padding:2px 4px;margin-bottom:2px;border-radius:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="' + name + '">'
                            + name + '</div>';
                    });
                }

                weeksHtml += '<div class="flex-fill" style="min-height:90px;background:' + cellBg + ';border:' + border + ';padding:4px;width:14.28%">'
                    + '<div class="d-flex justify-content-between mb-1">'
                    + '<span style="font-size:10px;color:#999">' + dayNames[cur.getDay()] + '</span>'
                    + '<span style="font-size:13px;font-weight:' + (isToday ? 'bold' : 'normal') + ';color:' + (isToday ? '#1976d2' : '#333') + '">' + cur.getDate() + '</span>'
                    + '</div>'
                    + '<div style="overflow-y:auto;max-height:120px">' + medBadges + '</div>'
                    + '</div>';

                cur.setDate(cur.getDate() + 1);
            }
            weeksHtml += '</div>';
        }

        // Legend
        var legendHtml = '<div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">';
        prescriptions.forEach(function (p, i) {
            var color = s1ncColors[i % s1ncColors.length];
            legendHtml += '<span style="background:' + color.bg + ';border-left:3px solid ' + color.border + ';color:' + color.text + ';font-size:11px;padding:2px 8px;border-radius:3px">'
                + ((p.product && p.product.product_name) || p.product_name || p.name || 'Unknown') + '</span>';
        });
        legendHtml += '</div>';

        el.innerHTML = '<div class="card-modern p-3">'
            + headerHtml + weeksHtml + legendHtml
            + '</div>';
    }

    // ── Bootstrap tab activation ──────────────────────────────────────────────
    document.querySelectorAll('#s1nc_tabs a[data-toggle="tab"], #s1nc_note_type_tabs a[data-toggle="tab"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            new bootstrap.Tab(this).show();
        });
    });

    // ── Event listeners ───────────────────────────────────────────────────────
    document.getElementById('s1nc_apply_filter').addEventListener('click', s1ncLoad);
    document.getElementById('s1nc_reset_filter').addEventListener('click', function () {
        s1ncInitDates();
        s1ncLoad();
    });

    // Auto-load when nurse chart tab is activated
    var nurseTab = document.getElementById('nurseChart-tab');
    if (nurseTab) {
        nurseTab.addEventListener('shown.bs.tab', function () { s1ncLoad(); });
        if (nurseTab.classList.contains('active')) s1ncLoad();
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    s1ncInitDates();
    // Lazy-load only when tab is first shown; fallback: load now if tab already active
    var activePane = document.querySelector('#nurseChartCardBody.show.active');
    if (activePane) s1ncLoad();

}());
</script>

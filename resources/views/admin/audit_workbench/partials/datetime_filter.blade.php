<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h6 class="mb-0 text-muted font-weight-bold mr-3"><i class="mdi mdi-filter-variant"></i> Period Filter</h6>
            
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted font-weight-bold">Quick Shift:</span>
                <select id="quick_shift_selector" class="form-select form-select-sm border-secondary text-dark" style="min-width: 200px;">
                    <option value="">-- Custom Range --</option>
                    <option value="morning">Today Morning (08:00 - 15:59)</option>
                    <option value="evening">Today Evening (16:00 - 23:59)</option>
                    <option value="night_yesterday">Yesterday Night (00:00 - 07:59)</option>
                    <option value="night_today">Today Night (00:00 - 07:59)</option>
                    <option value="today">Entire Today (00:00 - 23:59)</option>
                    <option value="yesterday">Entire Yesterday (00:00 - 23:59)</option>
                </select>
            </div>

            <form id="audit_period_form" class="d-flex align-items-center gap-2 m-0" method="GET" action="{{ url()->current() }}">
                <div class="d-flex align-items-center gap-1">
                    <span class="small text-muted font-weight-bold">From:</span>
                    <input type="datetime-local" name="start_date" id="filter_start_date" class="form-control form-control-sm border-secondary" value="{{ request('start_date', now()->startOfDay()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <span class="small text-muted font-weight-bold">To:</span>
                    <input type="datetime-local" name="end_date" id="filter_end_date" class="form-control form-control-sm border-secondary" value="{{ request('end_date', now()->endOfDay()->format('Y-m-d\TH:i')) }}">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <span class="small text-muted font-weight-bold">Status:</span>
                    <select name="audit_status" id="filter_audit_status" class="form-select form-select-sm border-secondary" onchange="this.form.submit()">
                        <option value="all" {{ request('audit_status') == 'all' ? 'selected' : '' }}>All</option>
                        <option value="not_audited" {{ request('audit_status') == 'not_audited' ? 'selected' : '' }}>Not Audited</option>
                        <option value="audited" {{ request('audit_status') == 'audited' ? 'selected' : '' }}>Audited</option>
                        <option value="queried" {{ request('audit_status') == 'queried' ? 'selected' : '' }}>Queried</option>
                        <option value="resolved_audited" {{ request('audit_status') == 'resolved_audited' ? 'selected' : '' }}>Resolved then Audited</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary shadow-sm"><i class="mdi mdi-refresh"></i> Update View</button>
            </form>
        </div>

        <div>
            @if(isset($zoneKey) && $zoneKey !== '')
                <button type="button" class="btn btn-indigo shadow-sm font-weight-bold" onclick="openBulkStampModal('{{ $zoneKey }}', '{{ $zoneLabel ?? 'Selected Zone' }}')">
                    <i class="mdi mdi-stamper"></i> Stamp Filtered Period
                </button>
            @endif
        </div>
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

        // Format to YYYY-MM-DDThh:mm
        const formatDt = (dt) => {
            const pad = (n) => n < 10 ? '0' + n : n;
            return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
        };

        document.getElementById('filter_start_date').value = formatDt(start);
        document.getElementById('filter_end_date').value = formatDt(end);
        
        // Auto submit form to refresh data
        document.getElementById('audit_period_form').submit();
    });

    // Bulk Stamp Modal Open function
    function openBulkStampModal(zoneKey, zoneLabel) {
        let sd = document.getElementById('filter_start_date').value;
        let ed = document.getElementById('filter_end_date').value;

        if (!sd || !ed) {
            alert('Please select a valid start and end date for the period stamp.');
            return;
        }

        document.getElementById('stamp_zone_key').value = zoneKey;
        document.getElementById('stamp_zone_label').textContent = zoneLabel;
        document.getElementById('stamp_start_date').value = sd;
        document.getElementById('stamp_end_date').value = ed;

        // Display pretty dates
        document.getElementById('stamp_display_start').textContent = sd.replace('T', ' ');
        document.getElementById('stamp_display_end').textContent = ed.replace('T', ' ');

        var myModal = new bootstrap.Modal(document.getElementById('bulkStampModal'));
        myModal.show();
    }
</script>
@endpush

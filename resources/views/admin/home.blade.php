@extends('admin.layouts.app')
@section('title', 'Dashboard')
@section('page_name', 'Dashboard')
@section('content')
    @php
        $user = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();

        // Define role tabs mapping based on sidebar structure
        $roleTabs = [];

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'RECEPTIONIST'])) {
            $roleTabs['receptionist'] = [
                'name' => 'Receptionist',
                'icon' => 'mdi-account-tie',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.receptionist-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])) {
            $roleTabs['biller'] = [
                'name' => 'Biller',
                'icon' => 'mdi-cash-multiple',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.biller-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN'])) {
            $roleTabs['admin'] = [
                'name' => 'Administration',
                'icon' => 'mdi-shield-account',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.admin-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'STORE', 'PHARMACIST'])) {
            $roleTabs['pharmacy'] = [
                'name' => 'Pharmacy/Store',
                'icon' => 'mdi-pill',
                'color' => 'warning',
                'partial' => 'admin.dashboards.tabs.pharmacy-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'NURSE'])) {
            $roleTabs['nursing'] = [
                'name' => 'Nursing',
                'icon' => 'mdi-heart-pulse',
                'color' => 'danger',
                'partial' => 'admin.dashboards.tabs.nursing-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'LAB SCIENTIST', 'RADIOLOGIST'])) {
            $roleTabs['lab'] = [
                'name' => 'Lab/Imaging',
                'icon' => 'mdi-flask',
                'color' => 'info',
                'partial' => 'admin.dashboards.tabs.lab-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'DOCTOR'])) {
            $roleTabs['doctor'] = [
                'name' => 'Doctor',
                'icon' => 'mdi-stethoscope',
                'color' => 'success',
                'partial' => 'admin.dashboards.tabs.doctor-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HMO Executive'])) {
            $roleTabs['hmo'] = [
                'name' => 'HMO Executive',
                'icon' => 'mdi-medical-bag',
                'color' => 'primary',
                'partial' => 'admin.dashboards.tabs.hmo-tab'
            ];
        }

        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'BILLER'])) {
            $roleTabs['accounts'] = [
                'name' => 'Accounts',
                'icon' => 'mdi-chart-areaspline',
                'color' => 'dark',
                'partial' => 'admin.dashboards.tabs.accounts-tab'
            ];
        }

        // Get first tab as default
        $firstTabKey = array_key_first($roleTabs);
    @endphp

    @if(count($roleTabs) > 0)
        @if(count($roleTabs) > 1)
            {{-- Multi-Role Tab Layout --}}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dash-tab-bar">
                        <ul class="nav nav-pills nav-fill mb-0" id="dashboardTabs" role="tablist">
                            @foreach($roleTabs as $key => $tab)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }} d-flex align-items-center justify-content-center"
                                            id="{{ $key }}-tab"
                                            data-bs-toggle="pill"
                                            data-bs-target="#{{ $key }}-content"
                                            type="button"
                                            role="tab"
                                            aria-controls="{{ $key }}-content"
                                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="mdi {{ $tab['icon'] }} me-2"></i>
                                        <span>{{ $tab['name'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="tab-content" id="dashboardTabContent">
                @foreach($roleTabs as $key => $tab)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                         id="{{ $key }}-content"
                         role="tabpanel"
                         aria-labelledby="{{ $key }}-tab">
                        @include($tab['partial'])
                    </div>
                @endforeach
            </div>
        @else
            {{-- Single Role - No Tabs --}}
            @include($roleTabs[$firstTabKey]['partial'])
        @endif
    @else
        {{-- Fallback for users with no recognized roles --}}
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    No dashboard available for your role. Please contact your administrator.
                </div>
            </div>
        </div>
    @endif
@endsection

@section('style')
<style>
    /* =========================================
       DASHBOARD DESIGN SYSTEM
       Mature · Spacious · High-Contrast
       ========================================= */

    /* Tab Bar */
    .dash-tab-bar {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    .dash-tab-bar .nav-link {
        border-radius: 0 !important;
        padding: 16px 20px;
        font-weight: 600;
        font-size: 0.875rem;
        color: #6b7280;
        letter-spacing: 0.01em;
        transition: all 0.25s ease;
        border-bottom: 3px solid transparent;
        position: relative;
    }

    .dash-tab-bar .nav-link i {
        font-size: 1.35rem;
    }

    .dash-tab-bar .nav-link:hover {
        color: #374151;
        background: #f9fafb;
    }

    .dash-tab-bar .nav-link.active {
        color: #1d4ed8 !important;
        background: #eff6ff !important;
        border-bottom: 3px solid #1d4ed8 !important;
    }

    /* Welcome Card */
    .dash-welcome {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    }

    .dash-welcome-body {
        padding: 32px 36px;
    }

    .dash-welcome-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
        flex-shrink: 0;
    }

    .dash-welcome-avatar i {
        font-size: 1.75rem;
        color: #fff;
    }

    .dash-welcome-title {
        font-weight: 700;
        font-size: 1.5rem;
        color: #fff;
        margin-bottom: 4px;
        line-height: 1.3;
    }

    .dash-welcome-sub {
        display: flex;
        align-items: center;
        color: rgba(255,255,255,0.7);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .dash-welcome-badge {
        padding: 8px 18px;
        background: rgba(255,255,255,0.15);
        border-radius: 10px;
        color: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        backdrop-filter: blur(8px);
        white-space: nowrap;
    }

    /* Stat Cards */
    .dash-stat-card {
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .dash-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 28px rgba(0,0,0,0.15);
    }

    .dash-stat-body {
        padding: 28px 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .dash-stat-label {
        color: rgba(255,255,255,0.7);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }

    .dash-stat-value {
        font-weight: 800;
        color: #fff;
        font-size: 2.25rem;
        margin-bottom: 8px;
        line-height: 1;
    }

    .dash-stat-hint {
        color: rgba(255,255,255,0.65);
        font-size: 0.8rem;
        font-weight: 500;
    }

    .dash-stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: rgba(255,255,255,0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .dash-stat-icon i {
        font-size: 1.75rem;
        color: #fff;
    }

    /* Section Cards (shortcuts, tables) */
    .dash-section-card {
        background: #fff;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        border: 1px solid #f0f0f0;
    }

    .dash-section-header {
        display: flex;
        align-items: center;
        margin-bottom: 24px;
        gap: 14px;
    }

    .dash-section-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .dash-section-icon i {
        font-size: 1.4rem;
    }

    .dash-section-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: #1a1a2e;
        margin-bottom: 0;
        line-height: 1.3;
    }

    /* Shortcut Cards */
    .dash-shortcut {
        padding: 22px 16px;
        border-radius: 16px;
        text-align: center;
        height: 100%;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 130px;
    }

    .dash-shortcut:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }

    .dash-shortcut-icon {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .dash-shortcut-title {
        font-weight: 700;
        font-size: 0.85rem;
        margin-bottom: 2px;
    }

    .dash-shortcut small {
        font-size: 0.75rem;
        font-weight: 500;
    }

    /* Chart Cards */
    .dash-chart-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        border: 1px solid #f0f0f0;
        height: 100%;
    }

    .dash-chart-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 28px 32px 0;
    }

    .dash-chart-body {
        padding: 24px 32px 28px;
        height: 300px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dash-welcome-body { padding: 24px 20px; }
        .dash-welcome-title { font-size: 1.15rem; }
        .dash-stat-body { padding: 20px 18px; }
        .dash-stat-value { font-size: 1.75rem; }
        .dash-section-card { padding: 20px; }
        .dash-shortcut { padding: 16px 12px; min-height: 110px; }
        .dash-shortcut-icon { font-size: 1.6rem; }
        .dash-chart-body { padding: 16px 20px 20px; height: 240px; }
        .dash-chart-header { padding: 20px 20px 0; }
    }

    /* =========================================
       ENHANCED: Queue Widget Items
       ========================================= */
    .dash-queue-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        border-radius: 14px;
        background: #f9fafb;
        border: 1px solid #f0f0f0;
        transition: all 0.2s ease;
        cursor: default;
    }
    .dash-queue-item:hover {
        background: #f3f4f6;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .dash-queue-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .dash-queue-icon i { font-size: 1.3rem; color: #fff; }
    .dash-queue-name { font-weight: 600; font-size: 0.82rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .dash-queue-count { font-weight: 800; font-size: 1.6rem; color: #1f2937; line-height: 1; }

    /* Clickable activity table rows */
    .hover-highlight:hover { background: #f0f4ff !important; }
    .hover-highlight td { transition: background 0.15s ease; }

    /* =========================================
       ENHANCED: Insights Strip
       ========================================= */
    .dash-insights-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .dash-insight-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 12px;
        border: 1px solid;
        flex: 1 1 220px;
        min-width: 220px;
    }
    .dash-insight-item.severity-success { background: #f0fdf4; border-color: #bbf7d0; }
    .dash-insight-item.severity-warning { background: #fffbeb; border-color: #fde68a; }
    .dash-insight-item.severity-danger  { background: #fef2f2; border-color: #fecaca; }
    .dash-insight-item.severity-info    { background: #eff6ff; border-color: #bfdbfe; }
    .dash-insight-icon {
        width: 36px; height: 36px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .dash-insight-icon.severity-success { background: #dcfce7; color: #16a34a; }
    .dash-insight-icon.severity-warning { background: #fef3c7; color: #d97706; }
    .dash-insight-icon.severity-danger  { background: #fee2e2; color: #dc2626; }
    .dash-insight-icon.severity-info    { background: #dbeafe; color: #2563eb; }
    .dash-insight-icon i { font-size: 1.15rem; }
    .dash-insight-title { font-weight: 700; font-size: 0.8rem; color: #374151; margin-bottom: 1px; }
    .dash-insight-msg   { font-size: 0.78rem; color: #6b7280; font-weight: 500; }

    /* =========================================
       ENHANCED: Mini Table
       ========================================= */
    .dash-mini-table th {
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
        padding: 10px 14px;
    }
    .dash-mini-table td {
        font-size: 0.82rem;
        color: #374151;
        padding: 10px 14px;
        vertical-align: middle;
    }
    .dash-mini-table .badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }

    /* =========================================
       ENHANCED: Aging Bars (accounts)
       ========================================= */
    .dash-aging-bar {
        margin-bottom: 14px;
    }
    .dash-aging-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.82rem;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .dash-aging-label span:first-child { color: #374151; }
    .dash-aging-label span:last-child  { color: #6b7280; }
    .dash-aging-track {
        height: 10px;
        background: #f3f4f6;
        border-radius: 6px;
        overflow: hidden;
    }
    .dash-aging-fill {
        height: 100%;
        border-radius: 6px;
        transition: width 0.5s ease;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('plugins/chartjs/Chart.js') }}"></script>
<script>
$(document).ready(function () {

    // ========================================
    // Date/Time Display
    // ========================================
    function updateDateTime() {
        var now = new Date();
        var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        $('[id="currentDateTime"]').text(now.toLocaleDateString('en-US', options));
    }
    updateDateTime();
    setInterval(updateDateTime, 30000);

    // ========================================
    // Helpers
    // ========================================
    function getToday() { return new Date().toISOString().slice(0, 10); }
    function getThisMonthStart() {
        var now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    }
    function fmt(n) { return '\u20A6' + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    var queueBgColors = {
        warning: '#f59e0b', danger: '#ef4444', info: '#3b82f6',
        success: '#10b981', primary: '#6366f1', purple: '#8b5cf6', dark: '#334155'
    };

    // ========================================
    // Generic UI Renderers
    // ========================================

    // Queue container → workbench URL mapping
    var queueWorkbenchMap = {
        'recep-queues':  { url: '{{ route("reception.workbench") }}', filterMap: null },
        'biller-queues': { url: '{{ route("billing.workbench") }}', filterMap: null },
        'pharm-queues':  { url: '{{ route("pharmacy.workbench") }}', filterMap: null },
        'nurs-queues':   { url: '{{ route("nursing-workbench.index") }}', filterMap: null },
        'lab-queues':    {
            url: null, // Lab tab has mixed lab + imaging queues
            filterMap: {
                'lab-billing':   { url: '{{ route("lab.workbench") }}',     filter: 'billing' },
                'lab-sample':    { url: '{{ route("lab.workbench") }}',     filter: 'sample' },
                'lab-results':   { url: '{{ route("lab.workbench") }}',     filter: 'results' },
                'lab-completed': { url: '{{ route("lab.workbench") }}',     filter: 'completed' },
                'img-billing':   { url: '{{ route("imaging.workbench") }}', filter: 'billing' },
                'img-results':   { url: '{{ route("imaging.workbench") }}', filter: 'results' }
            }
        },
        'hmo-queues':    { url: '{{ route("hmo.workbench") }}', filterMap: null },
        'doc-queues':    { url: null, filterMap: null } // No doctor workbench
    };

    function renderQueues(containerId, queues) {
        var $c = $('#' + containerId);
        if (!$c.length || !queues || !queues.length) { $c.html('<div class="col-12 text-muted text-center py-3">No queue data</div>'); return; }
        var wb = queueWorkbenchMap[containerId] || {};
        var html = '';
        queues.forEach(function(q) {
            var bg = queueBgColors[q.color] || '#6b7280';
            // Determine link URL based on filter mapping
            var href = '#';
            if (wb.filterMap && wb.filterMap[q.filter]) {
                href = wb.filterMap[q.filter].url + '?queue_filter=' + wb.filterMap[q.filter].filter;
            } else if (wb.url) {
                href = wb.url + '?queue_filter=' + q.filter;
            }
            var tag = href !== '#' ? 'a' : 'div';
            var linkAttr = href !== '#' ? ' href="' + href + '" title="Open ' + q.name + ' queue"' : '';
            html += '<div class="col-xl-2 col-lg-3 col-md-4 col-6">' +
                '<' + tag + linkAttr + ' class="text-decoration-none">' +
                '<div class="dash-queue-item" style="cursor:' + (href !== '#' ? 'pointer' : 'default') + ';">' +
                '<div class="dash-queue-icon" style="background:' + bg + ';"><i class="mdi ' + q.icon + '"></i></div>' +
                '<div><div class="dash-queue-name">' + q.name + '</div>' +
                '<div class="dash-queue-count">' + q.count + '</div></div>' +
                '</div></' + tag + '></div>';
        });
        $c.html(html);
    }

    function renderInsights(containerId, insights) {
        var $c = $('#' + containerId);
        if (!$c.length || !insights || !insights.length) { $c.html(''); return; }
        var html = '';
        insights.forEach(function(i) {
            html += '<div class="dash-insight-item severity-' + i.severity + '">' +
                '<div class="dash-insight-icon severity-' + i.severity + '"><i class="mdi ' + i.icon + '"></i></div>' +
                '<div><div class="dash-insight-title">' + i.title + '</div>' +
                '<div class="dash-insight-msg">' + i.message + '</div></div>' +
                '</div>';
        });
        $c.html(html);
    }

    // Activity container → workbench URL mapping for clickable rows
    var activityWorkbenchMap = {
        'recep-activity':  '{{ route("reception.workbench") }}',
        'biller-activity': '{{ route("billing.workbench") }}',
        'pharm-activity':  '{{ route("pharmacy.workbench") }}',
        'nurs-activity':   '{{ route("nursing-workbench.index") }}',
        'lab-activity':    '{{ route("lab.workbench") }}',
        'doc-activity':    null, // No doctor workbench
        'hmo-activity':    '{{ route("hmo.workbench") }}'
    };

    function renderActivityTable(containerId, rows, columns) {
        var $head = $('#' + containerId + '-head');
        var $body = $('#' + containerId + '-body');
        if (!$head.length || !rows) return;
        var wbUrl = activityWorkbenchMap[containerId] || null;
        // Build header
        var hdr = '<tr>';
        columns.forEach(function(c) { hdr += '<th>' + c.label + '</th>'; });
        hdr += '</tr>';
        $head.html(hdr);
        // Build rows
        if (!rows.length) { $body.html('<tr><td colspan="' + columns.length + '" class="text-center text-muted py-3">No activity yet today</td></tr>'); return; }
        var rhtml = '';
        rows.forEach(function(r) {
            var rowClick = '';
            if (wbUrl && r.patient_id) {
                rowClick = ' style="cursor:pointer;" onclick="window.location.href=\'' + wbUrl + '?patient_id=' + r.patient_id + '\'" title="Open patient in workbench"';
            }
            rhtml += '<tr class="' + (rowClick ? 'hover-highlight' : '') + '"' + rowClick + '>';
            columns.forEach(function(c) {
                var val = c.render ? c.render(r) : (r[c.key] || '—');
                rhtml += '<td>' + val + '</td>';
            });
            rhtml += '</tr>';
        });
        $body.html(rhtml);
    }

    function badgeHtml(label, color) {
        return '<span class="badge bg-' + color + '">' + label + '</span>';
    }

    // ========================================
    // Chart Engine (unchanged)
    // ========================================
    var chartInstances = {};
    var chartDefaults = {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#1e293b', titleFont: { size: 13, weight: '600' }, bodyFont: { size: 12 }, padding: 12, cornerRadius: 8, displayColors: false }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { color: '#94a3b8', font: { size: 11, weight: '500' }, padding: 8 } },
            x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 }, maxRotation: 45, padding: 6 } }
        }
    };

    function renderLineChart(canvasId, data, label, color) {
        if (!document.getElementById(canvasId)) return;
        if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
        var ctx = document.getElementById(canvasId).getContext('2d');
        var gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, color + '30');
        gradient.addColorStop(1, color + '05');
        chartInstances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(function(d) { return d.date; }),
                datasets: [{ label: label, data: data.map(function(d) { return parseFloat(d.total); }), borderColor: color, backgroundColor: gradient, fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: color, pointBorderWidth: 2 }]
            },
            options: chartDefaults
        });
    }

    function renderBarChart(canvasId, data, label, color) {
        if (!document.getElementById(canvasId)) return;
        if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
        var ctx = document.getElementById(canvasId).getContext('2d');
        chartInstances[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(function(d) { return d.date || d.hour || d.label; }),
                datasets: [{ label: label, data: data.map(function(d) { return parseFloat(d.total || d.count || d.value); }), backgroundColor: color + '80', borderColor: color, borderWidth: 1.5, borderRadius: 6 }]
            },
            options: chartDefaults
        });
    }

    function renderDoughnutChart(canvasId, data, colors) {
        if (!document.getElementById(canvasId)) return;
        if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
        var labelsArr = data.map(function(d) { return d.label || d.name || d.status; });
        var valuesArr = data.map(function(d) { return parseFloat(d.total || d.count || d.value); });
        var colorsArr = colors || data.map(function(d) { return d.color; });
        chartInstances[canvasId] = new Chart(document.getElementById(canvasId).getContext('2d'), {
            type: 'doughnut',
            data: { labels: labelsArr, datasets: [{ data: valuesArr, backgroundColor: colorsArr, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12, weight: '500' }, color: '#475569' } }, tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8, displayColors: true } } }
        });
    }

    // ========================================
    // Legacy Stats Fetchers (kept for backward compat)
    // ========================================
    function fetchReceptionistStats() {
        $.get("{{ route('dashboard.receptionist-stats') }}", function(data) {
            $('#recep-stat-new-patients').text(data.new_patients || '0');
            $('#recep-stat-returning-patients').text(data.returning_patients || '0');
            $('#recep-stat-admissions').text(data.admissions || '0');
            $('#recep-stat-bookings').text(data.bookings || '0');
        }).fail(function() {});
    }
    function fetchBillerStats() {
        $.get("{{ route('dashboard.biller-stats') }}", function(data) {
            $('#biller-stat-revenue').text(fmt(data.today_revenue));
            $('#biller-stat-payment-requests').text(data.payment_requests || '0');
            $('#biller-stat-my-payments').text(data.my_payments || '0');
            $('#biller-stat-consultations').text(data.consultations || '0');
        }).fail(function() {});
    }
    function fetchAdminStats() {
        $.get("{{ route('dashboard.admin-stats') }}", function(data) {
            $('#admin-stat-staff').text(data.total_staff || '0');
            $('#admin-stat-patients').text(data.total_patients || '0');
            $('#admin-stat-clinics').text(data.total_clinics || '0');
            $('#admin-stat-revenue').text(fmt(data.total_revenue));
        }).fail(function() {});
    }
    function fetchPharmacyStats() {
        $.get("{{ route('dashboard.pharmacy-stats') }}", function(data) {
            $('#pharm-stat-queue').text(data.queue_today || '0');
            $('#pharm-stat-dispensed').text(data.dispensed_today || '0');
            $('#pharm-stat-products').text(data.total_products || '0');
            $('#pharm-stat-low-stock').text(data.low_stock || '0');
        }).fail(function() {});
    }
    function fetchNursingStats() {
        $.get("{{ route('dashboard.nursing-stats') }}", function(data) {
            $('#nurs-stat-vitals-queue').text(data.vitals_queue || '0');
            $('#nurs-stat-bed-requests').text(data.bed_requests || '0');
            $('#nurs-stat-medication-due').text(data.medication_due || '0');
            $('#nurs-stat-admitted').text(data.admitted_patients || '0');
        }).fail(function() {});
    }
    function fetchLabStats() {
        $.get("{{ route('dashboard.lab-stats') }}", function(data) {
            $('#lab-stat-queue').text(data.lab_queue || '0');
            $('#lab-stat-imaging').text(data.imaging_queue || '0');
            $('#lab-stat-completed').text(data.completed_today || '0');
            $('#lab-stat-services').text(data.total_services || '0');
        }).fail(function() {});
    }
    function fetchDoctorStats() {
        $.get("{{ route('dashboard.doctor-stats') }}", function(data) {
            $('#doc-stat-consultations').text(data.consultations_today || '0');
            $('#doc-stat-ward-rounds').text(data.ward_rounds || '0');
            $('#doc-stat-patients').text(data.my_patients || '0');
            $('#doc-stat-appointments').text(data.appointments_today || '0');
        }).fail(function() {});
    }
    function fetchHmoStats() {
        $.get("{{ route('dashboard.hmo-stats') }}", function(data) {
            $('#hmo-stat-patients').text(data.hmo_patients || '0');
            $('#hmo-stat-pending-claims').text(data.pending_claims || '0');
            $('#hmo-stat-approved-claims').text(data.approved_claims || '0');
            $('#hmo-stat-total-hmos').text(data.total_hmos || '0');
        }).fail(function() {});
    }

    // ========================================
    // Enhanced Data Fetchers (queues, insights, charts, activity)
    // ========================================
    function loadEnhancedReception() {
        $.get("{{ route('dashboard.data.reception') }}", function(d) {
            renderQueues('recep-queues', d.queues);
            renderInsights('recep-insights', d.insights);
            // Hourly flow bar chart
            if (d.hourlyFlow) renderBarChart('recepHourlyFlowChart', d.hourlyFlow, 'Patients', '#3b82f6');
            // Patient breakdown donut
            if (d.patientBreakdown && d.patientBreakdown.length) {
                renderDoughnutChart('recepPatientBreakdownChart', d.patientBreakdown, d.patientBreakdown.map(function(x){return x.color;}));
            }
            // Activity table
            renderActivityTable('recep-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Type', key: 'visit_type' },
                { label: 'Status', render: function(r) { return badgeHtml(r.status_label, r.status_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedBilling() {
        $.get("{{ route('dashboard.data.billing') }}", function(d) {
            renderQueues('biller-queues', d.queues);
            renderInsights('biller-insights', d.insights);
            // Revenue trend
            if (d.revenueTrend && d.revenueTrend.length) renderLineChart('billerRevenueChart', d.revenueTrend, 'Revenue (\u20A6)', '#10b981');
            // Payment methods donut
            if (d.paymentMethods && d.paymentMethods.length) {
                renderDoughnutChart('billerPaymentMethodsChart', d.paymentMethods, d.paymentMethods.map(function(x){return x.color;}));
            }
            // Activity table
            renderActivityTable('biller-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Amount', key: 'amount_formatted' },
                { label: 'Method', key: 'method' },
                { label: 'Processed By', key: 'processed_by' }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedPharmacy() {
        $.get("{{ route('dashboard.data.pharmacy') }}", function(d) {
            renderQueues('pharm-queues', d.queues);
            renderInsights('pharm-insights', d.insights);
            if (d.dispensingTrend && d.dispensingTrend.length) renderLineChart('pharmacyDispensingChart', d.dispensingTrend, 'Dispensed', '#16a34a');
            if (d.stockHealth && d.stockHealth.length) renderDoughnutChart('pharmacyStockChart', d.stockHealth, d.stockHealth.map(function(x){return x.color;}));
            renderActivityTable('pharm-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Product', key: 'product' },
                { label: 'Status', render: function(r) { return badgeHtml(r.status_label, r.status_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedNursing() {
        $.get("{{ route('dashboard.data.nursing') }}", function(d) {
            renderQueues('nurs-queues', d.queues);
            renderInsights('nurs-insights', d.insights);
            if (d.vitalsTrend && d.vitalsTrend.length) renderLineChart('nursingVitalsChart', d.vitalsTrend, 'Vitals', '#ef4444');
            if (d.bedOccupancy && d.bedOccupancy.length) renderDoughnutChart('nursingBedChart', d.bedOccupancy, d.bedOccupancy.map(function(x){return x.color;}));
            renderActivityTable('nurs-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Activity', render: function(r) { return badgeHtml(r.activity, r.activity_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedLab() {
        $.get("{{ route('dashboard.data.lab') }}", function(d) {
            renderQueues('lab-queues', d.queues);
            renderInsights('lab-insights', d.insights);
            if (d.requestTrend && d.requestTrend.length) renderLineChart('labRequestsChart', d.requestTrend, 'Requests', '#0891b2');
            if (d.categoryBreakdown && d.categoryBreakdown.length) renderDoughnutChart('labCategoriesChart', d.categoryBreakdown, d.categoryBreakdown.map(function(x){return x.color;}));
            renderActivityTable('lab-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Test', key: 'test_name' },
                { label: 'Status', render: function(r) { return badgeHtml(r.status_label, r.status_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedDoctor() {
        $.get("{{ route('dashboard.data.doctor') }}", function(d) {
            renderQueues('doc-queues', d.queues);
            renderInsights('doc-insights', d.insights);
            renderActivityTable('doc-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'Clinic', key: 'clinic' },
                { label: 'Status', render: function(r) { return badgeHtml(r.status_label, r.status_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedHmo() {
        $.get("{{ route('dashboard.data.hmo') }}", function(d) {
            renderQueues('hmo-queues', d.queues);
            renderInsights('hmo-insights', d.insights);
            if (d.claimsTrend && d.claimsTrend.length) renderLineChart('hmoClaimsChart', d.claimsTrend, 'Claims (\u20A6)', '#7c3aed');
            if (d.providerDistribution && d.providerDistribution.length) renderDoughnutChart('hmoDistributionChart', d.providerDistribution, d.providerDistribution.map(function(x){return x.color;}));
            renderActivityTable('hmo-activity', d.activity, [
                { label: 'Time', key: 'time' },
                { label: 'Patient', key: 'patient_name' },
                { label: 'HMO', key: 'hmo_name' },
                { label: 'Amount', key: 'amount_formatted' },
                { label: 'Status', render: function(r) { return badgeHtml(r.status_label, r.status_color); } }
            ]);
        }).fail(function() {});
    }

    function loadEnhancedAccounts() {
        $.get("{{ route('dashboard.data.accounts') }}", function(d) {
            // KPI stats
            if (d.summary) {
                $('#acct-stat-today-revenue').text(fmt(d.summary.revenue_today));
                $('#acct-stat-month-revenue').text(fmt(d.summary.revenue_month));
                $('#acct-stat-outstanding').text(fmt(d.summary.outstanding));
            }
            if (d.kpis) {
                $('#acct-stat-collection-rate').text(d.kpis.collection_rate + '%');
            }
            renderInsights('acct-insights', d.insights);
            // Revenue trend chart
            if (d.revenueTrend && d.revenueTrend.length) renderLineChart('acctRevenueChart', d.revenueTrend, 'Revenue (\u20A6)', '#10b981');
            // Payment methods donut
            if (d.paymentMethods && d.paymentMethods.length) renderDoughnutChart('acctPaymentMethodsChart', d.paymentMethods, d.paymentMethods.map(function(x){return x.color;}));
            // Department revenue bars
            if (d.departmentRevenue && d.departmentRevenue.length) {
                var html = '';
                var maxVal = Math.max.apply(null, d.departmentRevenue.map(function(x){return parseFloat(x.total);}));
                d.departmentRevenue.forEach(function(dept) {
                    var pct = maxVal > 0 ? (parseFloat(dept.total) / maxVal * 100) : 0;
                    html += '<div class="dash-aging-bar"><div class="dash-aging-label"><span>' + dept.department + '</span><span>' + fmt(dept.total) + '</span></div>' +
                        '<div class="dash-aging-track"><div class="dash-aging-fill" style="width:' + pct + '%;background:#3b82f6;"></div></div></div>';
                });
                $('#acct-dept-bars').html(html);
            }
            // Outstanding aging
            if (d.kpis && d.kpis.outstanding_aging) {
                var agingColors = { '0-30 days': '#10b981', '30-60 days': '#f59e0b', '60-90 days': '#f97316', '90+ days': '#ef4444' };
                var agingData = d.kpis.outstanding_aging;
                var agingMax = Math.max.apply(null, Object.values(agingData).map(Number));
                var ahtml = '';
                Object.keys(agingData).forEach(function(key) {
                    var val = parseFloat(agingData[key]);
                    var pct = agingMax > 0 ? (val / agingMax * 100) : 0;
                    ahtml += '<div class="dash-aging-bar"><div class="dash-aging-label"><span>' + key + '</span><span>' + fmt(val) + '</span></div>' +
                        '<div class="dash-aging-track"><div class="dash-aging-fill" style="width:' + pct + '%;background:' + (agingColors[key] || '#6b7280') + ';"></div></div></div>';
                });
                $('#acct-aging-bars').html(ahtml);
            }
        }).fail(function() {});

        // Audit log
        $.get("{{ route('dashboard.data.audit-log') }}", { limit: 15 }, function(d) {
            var $body = $('#acct-audit-body');
            if (!d.log || !d.log.length) { $body.html('<tr><td colspan="4" class="text-center text-muted py-3">No audit data</td></tr>'); return; }
            var html = '';
            d.log.forEach(function(row) {
                html += '<tr><td>' + row.time + '</td><td>' + (row.user_name || 'System') + '</td>' +
                    '<td>' + badgeHtml(row.event, row.event_color) + '</td>' +
                    '<td>' + (row.module || '—') + '</td></tr>';
            });
            $body.html(html);
        }).fail(function() {});
    }

    // ========================================
    // Chart Data Fetchers (legacy charts that load from old endpoints)
    // ========================================
    function renderRegistrationsChart() {
        $.get("{{ route('dashboard.chart.registrations') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('adminRegistrationsChart', data, 'Registrations', '#3b82f6');
            renderLineChart('recepRegistrationsChart', data, 'Registrations', '#3b82f6');
        });
    }

    function renderDoctorCharts() {
        $.get("{{ route('api.chart.clinic.timeline') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart('doctorConsultationsChart', data, 'Consultations', '#3b82f6');
        });
        $.get("{{ route('api.chart.clinic.status') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            if (data && data.length) {
                renderDoughnutChart('doctorOutcomesChart', data.map(function(d) {
                    return { label: d.status, value: d.total };
                }), ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']);
            }
        });
    }

    function renderRevenueChart(targetId) {
        $.get("{{ route('dashboard.chart.revenue') }}", { start: getThisMonthStart(), end: getToday() }, function(data) {
            renderLineChart(targetId, data, 'Revenue (\u20A6)', '#10b981');
        });
    }

    // ========================================
    // Tab Loader
    // ========================================
    function loadTabData(tabName) {
        switch(tabName) {
            case 'receptionist':
                fetchReceptionistStats();
                loadEnhancedReception();
                renderRegistrationsChart();
                break;
            case 'biller':
                fetchBillerStats();
                loadEnhancedBilling();
                break;
            case 'admin':
                fetchAdminStats();
                renderRevenueChart('adminRevenueChart');
                renderRegistrationsChart();
                break;
            case 'pharmacy':
                fetchPharmacyStats();
                loadEnhancedPharmacy();
                break;
            case 'nursing':
                fetchNursingStats();
                loadEnhancedNursing();
                break;
            case 'lab':
                fetchLabStats();
                loadEnhancedLab();
                break;
            case 'doctor':
                fetchDoctorStats();
                loadEnhancedDoctor();
                renderDoctorCharts();
                break;
            case 'hmo':
                fetchHmoStats();
                loadEnhancedHmo();
                break;
            case 'accounts':
                loadEnhancedAccounts();
                break;
        }
    }

    // ========================================
    // Initialize & Tab Switching
    // ========================================
    function initializeAllStats() {
        var activeTab = $('#dashboardTabs .nav-link.active').attr('id');
        if (activeTab) {
            loadTabData(activeTab.replace('-tab', ''));
        } else {
            var firstKey = '{{ $firstTabKey ?? "" }}';
            if (firstKey) loadTabData(firstKey);
        }
    }

    initializeAllStats();

    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        var targetTab = $(e.target).attr('id').replace('-tab', '');
        loadTabData(targetTab);
    });

    // Auto-refresh every 60 seconds
    setInterval(function() {
        var activeTab = $('#dashboardTabs .nav-link.active').attr('id');
        if (activeTab) {
            loadTabData(activeTab.replace('-tab', ''));
        } else {
            initializeAllStats();
        }
    }, 60000);
});
</script>
@endsection

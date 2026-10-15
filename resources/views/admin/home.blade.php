@extends('admin.layouts.app')
@section('title', 'Operational Cockpit — ' . appsettings('hospital_name', 'CoreHealth'))
@section('page_name', 'Operational Cockpit')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/dashboard-cockpit.css') }}">
@endpush

@section('content')
<div class="dash-cockpit-viewport">
    {{-- 1. Viewport Segmented Switcher (Visible on Tablets & Mobile < 992px) --}}
    <div class="dash-viewport-tabs dash-mobile-tabs d-lg-none" id="dash-viewport-tabs">
        <button type="button" class="dash-viewport-tab-btn dash-mobile-tab-btn active" data-target-col="dash-col-pipeline">
            <i class="mdi mdi-ray-start-arrow me-1"></i>
            <span>Pipeline</span>
            <span class="dash-tab-pill-badge" id="tab-badge-pipeline">Active</span>
        </button>
        <button type="button" class="dash-viewport-tab-btn dash-mobile-tab-btn" data-target-col="dash-col-telemetry">
            <i class="mdi mdi-chart-areaspline me-1"></i>
            <span>Telemetry</span>
        </button>
        <button type="button" class="dash-viewport-tab-btn dash-mobile-tab-btn" data-target-col="dash-col-ledger">
            <i class="mdi mdi-format-list-checks me-1"></i>
            <span id="tab-label-queue">Live Queue</span>
            <span class="dash-tab-pill-badge" id="tab-badge-queue">Live</span>
        </button>
    </div>

    {{-- 2. Top Utility & Role Perspective Header Ribbon --}}
    @include('admin.dashboard.cockpit._header_ribbon')

    {{-- 3. Tri-Column High-Density Operational Grid --}}
    <div class="dash-grid dash-grid-3col">
        {{-- Column 1: Operational Pipeline & Triage Queues (28% width) --}}
        @include('admin.dashboard.cockpit._col_pipeline')

        {{-- Column 2: Clinical Telemetry & Analytics (44% width) --}}
        @include('admin.dashboard.cockpit._col_telemetry')

        {{-- Column 3: Live Activity Ledger & Role Workbenches Launchpad (28% width) --}}
        @include('admin.dashboard.cockpit._col_ledger')
    </div>
</div>

{{-- Modals: Emergency Intake & Rapid Registration --}}
@include('admin.dashboard.cockpit._modals')
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/chart.js/Chart.min.js') }}"></script>
<script>
window.COCKPIT_CONFIG = {
    summaryUrl: '{{ route("dashboard.data.cockpit-summary") }}',
    refreshIntervalMs: 60000,
    userRoles: @json(Auth::user() ? Auth::user()->roles->pluck('name')->toArray() : [])
};
</script>
<script src="{{ versioned_asset('js/dashboard-cockpit.js') }}"></script>
<script>
$(document).ready(function () {
    window.DashboardCockpit.init(window.COCKPIT_CONFIG);
});
</script>
@endpush

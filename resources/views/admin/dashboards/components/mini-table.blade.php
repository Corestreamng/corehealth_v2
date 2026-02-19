{{-- Mini Activity Table Component --}}
{{-- Usage: @include('admin.dashboards.components.mini-table', ['containerId' => 'recep-activity', 'title' => 'Recent Activity', 'icon' => 'mdi-history', 'iconBg' => 'info']) --}}

<div class="dash-section-card mb-4">
    <div class="dash-section-header">
        <div class="dash-section-icon bg-{{ $iconBg ?? 'info' }} bg-opacity-10">
            <i class="mdi {{ $icon ?? 'mdi-history' }} text-{{ $iconBg ?? 'info' }}"></i>
        </div>
        <div>
            <h5 class="dash-section-title">{{ $title ?? 'Recent Activity' }}</h5>
            <small class="text-muted">{{ $subtitle ?? 'Latest operations' }}</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 dash-mini-table">
            <thead id="{{ $containerId }}-head">
                <tr><th colspan="4" class="text-center text-muted py-3">Loading...</th></tr>
            </thead>
            <tbody id="{{ $containerId }}-body">
            </tbody>
        </table>
    </div>
</div>

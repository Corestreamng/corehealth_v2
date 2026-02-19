{{-- Queue Widget Component --}}
{{-- Usage: @include('admin.dashboards.components.queue-widget', ['containerId' => 'recep-queues', 'workbenchRoute' => 'reception.workbench']) --}}

<div class="dash-section-card mb-4">
    <div class="dash-section-header">
        <div class="dash-section-icon bg-warning bg-opacity-10">
            <i class="mdi mdi-view-list text-warning"></i>
        </div>
        <div>
            <h5 class="dash-section-title">Live Queues</h5>
            <small class="text-muted">Real-time patient pipeline</small>
        </div>
    </div>
    <div class="row g-3" id="{{ $containerId }}">
        {{-- Queue items populated via JS --}}
        <div class="col-12 text-center py-3">
            <div class="spinner-border spinner-border-sm text-muted" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
</div>

{{--
    CoreHealth v2 — Universal Workbench Empty State Partial
    Clean placeholder shown when no active patient is selected.
--}}
<div class="empty-state workbench-empty-state" id="empty-state">
    <div class="empty-state-icon mb-3">
        <i class="mdi {{ $icon ?? 'mdi-account-search' }}"></i>
    </div>
    <h4 class="empty-state-title">{{ $title ?? 'No Patient Selected' }}</h4>
    <p class="empty-state-desc text-muted mb-3">{{ $description ?? 'Search and select a patient from the queue or search bar to begin' }}</p>
    @if(($showQueueButton ?? true))
    <button type="button" class="btn btn-primary btn-lg" id="{{ $buttonId ?? 'view-queue-btn' }}">
        <i class="mdi {{ $buttonIcon ?? 'mdi-format-list-bulleted' }}"></i> <span>{{ $buttonText ?? 'View Queue' }}</span>
    </button>
    @endif
</div>

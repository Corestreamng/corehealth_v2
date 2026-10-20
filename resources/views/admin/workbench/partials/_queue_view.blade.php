{{--
    CoreHealth v2 — Universal Workbench Queue View Drawer
    Full-workspace slide-in drawer for departmental queue inspection and datatable triage.
--}}
<div class="queue-view workbench-queue-view" id="queue-view" style="display: none;">
    <div class="queue-view-header">
        <h4 id="queue-view-title" class="mb-0">
            <i class="mdi {{ $icon ?? 'mdi-format-list-bulleted' }}"></i> 
            <span>{{ $title ?? 'Departmental Queue' }}</span>
        </h4>
        <button type="button" class="btn btn-sm btn-outline-secondary btn-close-queue" id="btn-close-queue" title="Close Queue View">
            <i class="mdi mdi-close"></i> <span>Close</span>
        </button>
    </div>
    <div class="queue-view-content" style="flex: 1 1 auto; min-height: 0; overflow-y: auto;">
        <table class="table table-hover" id="{{ $tableId ?? 'queue-datatable' }}" style="width: 100%;">
            <thead>
                @if(!empty($thead))
                    {!! $thead !!}
                @else
                    <tr>
                        <th>Queue Items</th>
                    </tr>
                @endif
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

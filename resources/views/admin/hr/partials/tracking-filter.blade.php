{{-- Tracking Module Filter Bar - Accepts $staffList, $filterRoute, optional $extraFilters slot --}}
<div class="filter-bar d-flex align-items-center gap-2 flex-wrap mb-3" style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
    <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filter:</label>
    <form method="GET" class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
        <select class="form-control form-control-sm select2" name="staff_id" style="min-width: 220px;">
            <option value="">All Staff</option>
            @foreach($staffList as $s)
                <option value="{{ $s->id }}" {{ request('staff_id') == $s->id ? 'selected' : '' }}>{{ $s->user?->surname }} {{ $s->user?->firstname }}</option>
            @endforeach
        </select>

        {{ $slot ?? '' }}

        <button class="btn btn-primary btn-sm" style="border-radius: 8px;"><i class="mdi mdi-filter mr-1"></i> Filter</button>
        @if(request()->hasAny(['staff_id', 'type', 'status', 'result']))
            <a href="{{ $filterRoute }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">Clear</a>
        @endif
    </form>

    {{-- Export --}}
    <div class="ms-auto">
        <a href="{{ $filterRoute }}?{{ http_build_query(array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-outline-success btn-sm" style="border-radius: 8px;" title="Export CSV">
            <i class="mdi mdi-file-excel mr-1"></i> Export
        </a>
    </div>
</div>

@extends('admin.layouts.app')

@section('title', 'NHMIS Monthly Summary Workbench')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/nhmis-workbench.css') }}">
@endpush

@section('content')
<div class="nhmis-workbench-wrapper">
    {{-- Header & Action Bar --}}
    @include('admin.nhmis.partials._header')

    {{-- Form Pages Tab Navigation --}}
    @include('admin.nhmis.partials._tabs')

    {{-- Form Pages Content Panes --}}
    <div class="tab-content" id="nhmisPageTabsContent">
        @php
            $pages = $schema->getPages();
        @endphp

        <div class="tab-pane fade show active" id="page-1-pane" role="tabpanel" aria-labelledby="tab-page-1">
            @include('admin.nhmis.partials._page_content', ['page' => $pages[0], 'values' => $values, 'report' => $report])
        </div>

        <div class="tab-pane fade" id="page-2-pane" role="tabpanel" aria-labelledby="tab-page-2">
            @include('admin.nhmis.partials._page_content', ['page' => $pages[1], 'values' => $values, 'report' => $report])
        </div>

        <div class="tab-pane fade" id="page-3-pane" role="tabpanel" aria-labelledby="tab-page-3">
            @include('admin.nhmis.partials._page_content', ['page' => $pages[2], 'values' => $values, 'report' => $report])
        </div>

        <div class="tab-pane fade" id="page-4-pane" role="tabpanel" aria-labelledby="tab-page-4">
            @include('admin.nhmis.partials._page_content', ['page' => $pages[3], 'values' => $values, 'report' => $report])
        </div>

        <div class="tab-pane fade" id="page-5-pane" role="tabpanel" aria-labelledby="tab-page-5">
            @include('admin.nhmis.partials._page_content', ['page' => $pages[4], 'values' => $values, 'report' => $report])
        </div>
    </div>

    {{-- Interactive Modals (Clinical Diagnosis Audit, Cell Overrides, Lock Verification) --}}
    @include('admin.nhmis.partials._modals')
</div>
@endsection

@push('scripts')
@include('admin.nhmis.partials._scripts')
@endpush

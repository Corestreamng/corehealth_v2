@extends('admin.layouts.app')
@section('title', 'Promotion Detail')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Promotion Detail')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>:root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }</style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-arrow-up-bold-circle mr-2"></i>Promotion Detail
                    </h3>
                    <p class="text-muted mb-0">{{ $promotion->staff?->user?->surname }} {{ $promotion->staff?->user?->firstname }}</p>
                </div>
                <a href="{{ route('hr.promotions.index') }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back
                </a>
            </div>

            <div class="card-modern" style="border-radius: 12px;">
                <div class="card-body" style="padding: 2rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Staff Name</label>
                            <p class="font-weight-bold mb-0">{{ $promotion->staff?->user?->surname }} {{ $promotion->staff?->user?->firstname }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Employee ID</label>
                            <p class="font-weight-bold mb-0">{{ $promotion->staff?->employee_id ?? '—' }}</p>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">From Grade Level</label>
                            <p class="mb-0">{{ $promotion->fromGradeLevel?->name ?? '—' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">To Grade Level</label>
                            <p class="mb-0"><span class="badge badge-success">{{ $promotion->toGradeLevel?->name ?? '—' }}</span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Previous Title</label>
                            <p class="mb-0">{{ $promotion->from_job_title ?? '—' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">New Title</label>
                            <p class="mb-0 font-weight-bold">{{ $promotion->to_job_title ?? '—' }}</p>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">Promotion Date</label>
                            <p class="mb-0">{{ $promotion->promotion_date?->format('d M Y') }}</p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">Effective Date</label>
                            <p class="mb-0">{{ $promotion->effective_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="text-muted small">Next Promotion Due</label>
                            <p class="mb-0">{{ $promotion->next_promotion_due_date?->format('d M Y') ?? '—' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Authority</label>
                            <p class="mb-0">{{ $promotion->authority ?? '—' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Processed By</label>
                            <p class="mb-0">{{ $promotion->processedBy?->surname }} {{ $promotion->processedBy?->firstname }}</p>
                        </div>
                        @if($promotion->remarks)
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Remarks</label>
                            <p class="mb-0">{{ $promotion->remarks }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

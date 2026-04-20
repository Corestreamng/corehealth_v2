@extends('admin.layouts.app')
@section('title', 'Promotion History')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Promotion History')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>:root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }</style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-history mr-2"></i>Promotion History
                    </h3>
                    <p class="text-muted mb-0">{{ $staff->user?->surname }} {{ $staff->user?->firstname }} {{ $staff->user?->othername }} — {{ $staff->employee_id }}</p>
                </div>
                <a href="{{ route('hr.promotions.index') }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back
                </a>
            </div>

            <!-- Current Status -->
            <div class="card-modern mb-4" style="border-radius: 12px;">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Current Grade</label>
                            <span class="badge badge-primary px-3 py-2">{{ $staff->gradeLevel?->name ?? 'Not Set' }}</span>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Job Title</label>
                            <span class="font-weight-bold">{{ $staff->job_title ?? '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Last Promotion</label>
                            <span>{{ $staff->last_promotion_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small d-block">Next Due</label>
                            <span>{{ $staff->next_promotion_due_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card-modern" style="border-radius: 12px;">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="mb-0" style="font-weight: 600;"><i class="mdi mdi-timeline mr-2" style="color: var(--primary-color);"></i>Promotion Timeline</h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    @forelse($promotions as $promo)
                    <div class="d-flex mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="mr-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: linear-gradient(135deg, #48bb78, #38a169); color: white;">
                                <i class="mdi mdi-arrow-up-bold"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1 font-weight-bold">{{ $promo->toGradeLevel?->name ?? $promo->to_job_title ?? 'Promotion' }}</h6>
                                <small class="text-muted">{{ $promo->promotion_date?->format('d M Y') }}</small>
                            </div>
                            <p class="mb-1 text-muted">
                                {{ $promo->fromGradeLevel?->name ?? $promo->from_job_title ?? '—' }} →
                                <strong>{{ $promo->toGradeLevel?->name ?? '—' }}</strong>
                                @if($promo->to_job_title) ({{ $promo->to_job_title }}) @endif
                            </p>
                            @if($promo->authority)<small class="text-muted"><i class="mdi mdi-certificate mr-1"></i>{{ $promo->authority }}</small>@endif
                            @if($promo->remarks)<p class="small text-muted mt-1 mb-0">{{ $promo->remarks }}</p>@endif
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-history mdi-48px d-block mb-2"></i>
                        No promotion history found for this staff member.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

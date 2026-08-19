@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — ' . ($module ?? 'Coming Soon'))

@section('ops_audit_content')
<div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
    <div class="text-center">
        <i class="mdi mdi-cog-transfer-outline text-primary" style="font-size: 5rem; opacity: 0.5;"></i>
        <h3 class="font-weight-bold text-dark mt-3">{{ $module ?? 'Module' }} Audit</h3>
        <p class="text-muted mb-0">This module is being built. Check back soon.</p>
        <a href="{{ route('ops-audit.reception') }}" class="btn btn-outline-primary mt-3">
            <i class="mdi mdi-arrow-left me-1"></i> Go to Reception
        </a>
    </div>
</div>
@endsection

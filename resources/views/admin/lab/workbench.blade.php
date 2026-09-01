@extends('admin.layouts.app')

@section('title', 'Lab Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/lab-workbench.css') }}?v={{ filemtime(public_path('css/lab-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
    @include('admin.partials.procedure_outcome_modal')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp


<div class="lab-workbench-container">
    <!-- Left Panel: Patient Search & Queue -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="fa fa-search"></i> Patient Search</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        @include('admin.partials.patient_search_html')

        <div class="queue-widget">
            <h6>📊 PENDING QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label">🚨 <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="billing">
                <span class="queue-item-label">🟡 Awaiting Billing</span>
                <span class="queue-count billing" id="queue-billing-count">0</span>
            </div>
            <div class="queue-item" data-filter="sample">
                <span class="queue-item-label">🟠 Sample Collection</span>
                <span class="queue-count sample" id="queue-sample-count">0</span>
            </div>
            <div class="queue-item" data-filter="results">
                <span class="queue-item-label">🔴 Result Entry</span>
                <span class="queue-count results" id="queue-results-count">0</span>
            </div>
            <div class="queue-item" data-filter="completed">
                <span class="queue-item-label">🟢 Completed</span>
                <span class="queue-count completed" id="queue-completed-count">0</span>
            </div>
            <div class="queue-item" data-filter="freeform">
                <span class="queue-item-label">⚪ Free-Form / External</span>
                <span class="queue-count" id="queue-freeform-count" style="background: #6c757d; color: white;">0</span>
            </div>
            @if(($isApprover ?? false) && ($requiresApproval ?? false))
            <div class="queue-item" data-filter="approval" style="background: #f3f0ff; border-left: 3px solid #6f42c1;">
                <span class="queue-item-label">🟣 <strong class="text-purple">Awaiting Approval</strong></span>
                <span class="queue-count" id="queue-approval-count" style="background: #6f42c1; color: #fff;">0</span>
            </div>
            @endif
            <button class="btn-queue-all" id="show-all-queue-btn">
                📋 Show All Queue →
            </button>
        </div>

        <div class="quick-actions">
            <h6>⚡ QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-register-walkin">
                <i class="mdi mdi-account-plus text-success"></i>
                <span>Register Walk-in</span>
            </button>
            <button class="quick-action-btn" id="btn-new-request" style="display: none;">
                <i class="mdi mdi-plus-circle"></i>

@include("admin.lab.partials._modals")
@endsection

@include("admin.lab.partials._scripts")

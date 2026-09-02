@extends('admin.layouts.app')
@section('title', 'New Encounter')
@if (request()->get('admission_req_id') != '')
    @section('page_name', 'Ward Round')
@else
    @section('page_name', 'Consultations')
@endif
@section('subpage_name', 'New Encounter')
@section('content')
    @include('admin.partials.procedure_outcome_modal')
    <link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nursing-workbench.css') }}">
    <style>
        /* Fix for modals inside overflow containers */
        .modal {
            position: fixed !important;
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-dialog {
            z-index: 1056 !important;
        }
    </style>

    {{-- Emergency Triage Alert Banner --}}
    @if (isset($doctorQueue) && $doctorQueue && $doctorQueue->priority === 'emergency')
        <div class="alert alert-danger border-danger shadow-sm mb-3" role="alert">
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="fa fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">
                        <i class="fa fa-bolt"></i> Emergency Patient
                        @if ($doctorQueue->source === 'emergency_intake')
                            <span class="badge bg-danger ms-2">Via Emergency Intake</span>
                        @endif
                    </h5>
                    @if ($doctorQueue->triage_note)
                        <hr class="my-2">
                        <p class="mb-0 small" style="white-space: pre-line;">{{ $doctorQueue->triage_note }}</p>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    <!-- Sticky Consultation Header Component -->
    @include('admin.doctors.partials.sticky_header')

    
    @push('styles')
    <style>
        .content-header {
            display: none !important;
        }

        .encounter-workspace-layout {
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 65px;
            --hos-color-var: {{ appsettings('hos_color', '#007bff') }};
            display: grid;
            grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
            width: 100%;
        }

        .encounter-workspace-layout.sidebar-collapsed {
            grid-template-columns: var(--sidebar-collapsed-width) minmax(0, 1fr);
        }
        
        .encounter-sidebar-wrapper {
            transition: all 0.3s ease;
            position: sticky;
            top: 115px; /* navbar (~60px) + compact header bar (~55px) */
            height: calc(100vh - 130px);
            max-height: calc(100vh - 130px);
            overflow-y: auto !important;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #dee2e6;
            padding: 0.5rem 0;
        }

        .encounter-sidebar-wrapper::-webkit-scrollbar {
            width: 4px;
        }
        .encounter-sidebar-wrapper::-webkit-scrollbar-thumb {
            background-color: #ddd;
            border-radius: 4px;
        }

        .encounter-sidebar {
            padding: 0.35rem 0.5rem;
            margin: 0;
            list-style: none;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
        }

        .encounter-sidebar .nav-item {
            margin-bottom: 3px;
        }

        .encounter-sidebar .nav-link {
            display: flex;
            align-items: center;
            color: #495057;
            padding: 0.65rem 1rem;
            border-radius: 8px !important;
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: all 0.2s ease;
            cursor: pointer !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            text-decoration: none !important;
        }

        /* Force pointer-events:none on all children so hover hit-testing sees ONLY the parent <a> element */
        .encounter-sidebar .nav-link *,
        .encounter-sidebar .nav-link .sidebar-text,
        .encounter-sidebar .nav-link i,
        .encounter-sidebar .nav-link .badge {
            pointer-events: none !important;
            cursor: pointer !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
        }

        .encounter-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
            color: #6c757d;
            transition: margin 0.3s ease;
        }

        .encounter-sidebar .nav-link:hover {
            background-color: #f1f3f5;
            color: var(--hos-color-var);
        }

        .encounter-sidebar .nav-link:hover i {
            color: var(--hos-color-var);
        }

        .encounter-sidebar .nav-link.active {
            background-color: var(--hos-color-var) !important;
            color: #ffffff !important;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

        .encounter-sidebar .nav-link.active i,
        .encounter-sidebar .nav-link.active .sidebar-text {
            color: #fff;
        }

        @media (max-width: 767.98px) {
            #chat-floating-btn {
                bottom: 135px !important;
                right: 15px !important;
                transform: none !important;
            }
            #ai-quick-actions-fab {
                bottom: 195px !important;
                right: 15px !important;
                transform: none !important;
            }
        }

        /* --- MOBILE LAYOUT START --- */
        @media (max-width: 767.98px) {
            .encounter-workspace-layout {
                display: block !important;
            }
            .mobile-bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 64px;
                background: #fff;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                z-index: 1040;
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: space-around;
                padding: 0 4px;
                border-top: 1px solid #dee2e6;
            }
            .mobile-bottom-nav .nav-item {
                flex: 1 1 auto;
                text-align: center;
            }
            .mobile-bottom-nav .nav-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 6px 4px;
                color: #6c757d;
                font-size: 0.6rem;
                font-weight: 600;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                letter-spacing: 0.3px;
            }
            .mobile-bottom-nav .nav-link.active {
                color: var(--hos-color-var);
            }
            .mobile-bottom-nav .nav-link i {
                font-size: 1.25rem;
                margin-bottom: 2px;
                color: inherit;
            }
            .mobile-bottom-nav .nav-link .sidebar-text {
                white-space: nowrap;
                line-height: 1;
            }
            /* "More" button — distinctive teal pill */
            .mobile-bottom-nav .mobile-more-btn .nav-link {
                background: var(--hos-color-var, #00897b);
                color: #fff !important;
                border-radius: 14px;
                padding: 6px 10px;
                margin: 4px 2px;
            }
            .mobile-bottom-nav .mobile-more-btn .nav-link i {
                color: #fff !important;
            }
            .encounter-main-content {
                padding-bottom: 80px !important;
            }
            /* More Sheet — tile grid */
            .mobile-more-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 16px;
            }
            .mobile-more-tile {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 12px;
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 12px;
                text-decoration: none;
                color: #343a40;
                font-weight: 600;
                font-size: 0.85rem;
                transition: all 0.15s ease;
                cursor: pointer;
            }
            .mobile-more-tile:hover,
            .mobile-more-tile:active {
                background: color-mix(in srgb, var(--hos-color-var, #007bff) 10%, white);
                border-color: color-mix(in srgb, var(--hos-color-var, #007bff) 40%, white);
                color: var(--hos-color-var, #007bff);
                transform: scale(0.97);
            }
            .mobile-more-tile .tile-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.15rem;
                flex-shrink: 0;
                color: #fff;
            }
            .mobile-more-tile .tile-label {
                flex: 1;
                line-height: 1.2;
            }
            .mobile-more-tile .tile-badge {
                margin-left: auto;
            }

            /* ═══ MOBILE NATIVE: Flatten card nesting ═══ */

            /* Remove outer card chrome on mobile — edge-to-edge content */
            .encounter-main-content .card-modern {
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                margin-bottom: 0.5rem !important;
            }
            .encounter-main-content .card-modern > .card-body {
                padding: 10px 6px !important;
            }
            .encounter-main-content .card-modern > .card-header {
                padding: 10px 8px !important;
                border-radius: 0 !important;
            }

            /* Nested card-in-card: minimal chrome */
            .encounter-main-content .card-modern .card-modern {
                border: 1px solid #e9ecef !important;
                border-radius: 10px !important;
                margin-bottom: 0.75rem !important;
            }
            .encounter-main-content .card-modern .card-modern > .card-body {
                padding: 8px !important;
            }

            /* Full-bleed tab content */
            .encounter-main-content > .tab-content {
                padding: 0 !important;
            }

            /* Clinical story edge-to-edge */
            .clinical-story-wrapper {
                border-radius: 0 !important;
                padding: 12px 8px !important;
                box-shadow: none !important;
                margin-bottom: 0.5rem !important;
            }

            /* Section titles as mobile dividers */
            .encounter-main-content .card-modern > .card-header h5,
            .encounter-main-content .card-modern > .card-header h6 {
                font-size: 0.8rem !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-weight: 700;
            }

            /* ═══ MOBILE NATIVE: Touch-friendly form controls ═══ */
            .encounter-main-content .form-control,
            .encounter-main-content .form-select {
                min-height: 44px !important;
                font-size: 16px !important; /* Prevents iOS zoom on focus */
            }
            .encounter-main-content textarea.form-control {
                min-height: 80px !important;
            }
            .encounter-main-content .btn:not(.btn-sm):not(.btn-xs) {
                min-height: 44px;
            }

            /* ═══ MOBILE NATIVE: Scale text for density ═══ */
            .encounter-main-content .form-label,
            .encounter-main-content label {
                font-size: 0.85rem;
            }
            .encounter-main-content p,
            .encounter-main-content .small,
            .encounter-main-content small {
                font-size: 0.85rem;
            }
            .encounter-main-content h5 {
                font-size: 1rem;
            }
            .encounter-main-content h6 {
                font-size: 0.9rem;
            }

            /* ═══ MOBILE NATIVE: DataTable responsiveness ═══ */
            .encounter-main-content .dataTables_wrapper {
                overflow-x: hidden !important;
            }
            .encounter-main-content .dataTables_wrapper table {
                width: 100% !important;
            }
            .encounter-main-content .dataTables_wrapper .dt-buttons {
                display: none !important;
            }

            /* ═══ MOBILE NATIVE: Tab sub-navigation (inner tabs) ═══ */
            .encounter-main-content .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-bottom: 2px solid #e9ecef;
            }
            .encounter-main-content .nav-tabs::-webkit-scrollbar { display: none; }
            .encounter-main-content .nav-tabs .nav-link {
                white-space: nowrap;
                font-size: 0.8rem;
                padding: 8px 14px;
            }

            /* ═══ Treatment Plans warning modal highlight ═══ */
            .tp-highlight-pulse {
                animation: tpPulse 0.6s ease-in-out 3;
            }
            @keyframes tpPulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4); }
                50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
            }
        }
        /* --- MOBILE LAYOUT END --- */

        /* ═══ Universal Floating Action Bar (Desktop Base) ═══ */
        .encounter-floating-nav {
            position: fixed !important;
            bottom: 75px !important; /* Raised higher so it floats cleanly above the Copyright page footer */
            left: calc(var(--sidebar-width, 240px) + 290px) !important; /* Shy of the sub-sidebar with clean gap */
            right: 105px !important; /* Ends 105px from right edge so it doesn't overlap floating message/wand icons */
            z-index: 1040 !important;
            background: rgba(255, 255, 255, 0.96) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(203, 213, 225, 0.8) !important;
            border-radius: 14px !important;
            padding: 10px 18px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.06) !important;
            transition: left 0.3s ease, right 0.3s ease, bottom 0.3s ease !important;
        }

        .encounter-workspace-layout.sidebar-collapsed .encounter-floating-nav,
        .encounter-workspace-layout.sidebar-collapsed ~ .encounter-floating-nav {
            left: calc(var(--sidebar-collapsed-width, 65px) + 290px) !important;
        }

        body.sidebar-collapse .encounter-floating-nav {
            left: calc(var(--sidebar-width, 240px) + 110px) !important;
        }

        body.sidebar-collapse .encounter-workspace-layout.sidebar-collapsed .encounter-floating-nav,
        body.sidebar-collapse .encounter-workspace-layout.sidebar-collapsed ~ .encounter-floating-nav {
            left: calc(var(--sidebar-collapsed-width, 65px) + 110px) !important;
        }

        .encounter-floating-nav .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            padding: 9px 20px;
            border-radius: 10px;
        }

        .encounter-main-content {
            padding-bottom: 140px !important;
        }

        /* ═══ MOBILE OVERRIDES ═══ */
        @media (max-width: 767.98px) {
            .encounter-floating-nav,
            body.sidebar-collapse .encounter-floating-nav,
            .encounter-workspace-layout.sidebar-collapsed .encounter-floating-nav,
            .encounter-workspace-layout.sidebar-collapsed ~ .encounter-floating-nav,
            body.sidebar-collapse .encounter-workspace-layout.sidebar-collapsed .encounter-floating-nav,
            body.sidebar-collapse .encounter-workspace-layout.sidebar-collapsed ~ .encounter-floating-nav {
                position: fixed !important;
                bottom: 70px !important; /* Docked cleanly above mobile bottom tab bar (64px) */
                left: 10px !important;
                right: 10px !important;
                z-index: 1040 !important;
                margin-top: 0 !important;
                padding: 8px 12px !important;
                border-radius: 12px !important;
            }
            .encounter-floating-nav .btn {
                font-size: 0.82rem;
                padding: 10px 14px;
                min-height: 44px;
            }
            .encounter-main-content {
                padding-bottom: 145px !important;
            }


            /* ═══ MOBILE NATIVE: Touch-friendly form controls ═══ */
            .encounter-main-content .form-control,
            .encounter-main-content .form-select {
                min-height: 44px !important;
                font-size: 16px !important; /* Prevents iOS zoom on focus */
            }
            .encounter-main-content textarea.form-control {
                min-height: 80px !important;
            }
            .encounter-main-content .btn:not(.btn-sm):not(.btn-xs) {
                min-height: 44px;
            }

            /* ═══ MOBILE NATIVE: Scale text for density ═══ */
            .encounter-main-content .form-label,
            .encounter-main-content label {
                font-size: 0.85rem;
            }
            .encounter-main-content p,
            .encounter-main-content .small,
            .encounter-main-content small {
                font-size: 0.85rem;
            }
            .encounter-main-content h5 {
                font-size: 1rem;
            }
            .encounter-main-content h6 {
                font-size: 0.9rem;
            }

            /* ═══ MOBILE NATIVE: DataTable responsiveness ═══ */
            .encounter-main-content .dataTables_wrapper {
                overflow-x: hidden !important;
            }
            .encounter-main-content .dataTables_wrapper table {
                width: 100% !important;
            }
            .encounter-main-content .dataTables_wrapper .dt-buttons {
                display: none !important;
            }

            /* ═══ MOBILE NATIVE: Tab sub-navigation (inner tabs) ═══ */
            .encounter-main-content .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-bottom: 2px solid #e9ecef;
            }
            .encounter-main-content .nav-tabs::-webkit-scrollbar { display: none; }
            .encounter-main-content .nav-tabs .nav-link {
                white-space: nowrap;
                font-size: 0.8rem;
                padding: 8px 14px;
            }

            /* ═══ Treatment Plans warning modal highlight ═══ */
            .tp-highlight-pulse {
                animation: tpPulse 0.6s ease-in-out 3;
            }
            @keyframes tpPulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.4); }
                50% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
            }
        }
        /* --- MOBILE LAYOUT END --- */

        /* Collapsed state styles */
        .encounter-workspace-layout.sidebar-collapsed .sidebar-text,
        .encounter-workspace-layout.sidebar-collapsed .badge {
            display: none !important;
        }

        .encounter-workspace-layout.sidebar-collapsed .nav-link {
            padding: 0.75rem 0;
            justify-content: center;
        }

        .encounter-workspace-layout.sidebar-collapsed .nav-link i {
            margin: 0 !important;
            font-size: 1.25rem;
        }

        /* Responsive behavior */
        @media (max-width: 991.98px) {
            .encounter-sidebar-wrapper {
                top: 70px;
                height: calc(100vh - 85px);
                max-height: calc(100vh - 85px);
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch;
            }
            .encounter-workspace-layout.sidebar-collapsed .nav-link {
                padding: 0.65rem 0;
                justify-content: center;
            }
            .encounter-workspace-layout.sidebar-collapsed .nav-link i {
                margin: 0 !important;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        function toggleSidebar() {
            const layout = document.querySelector('.encounter-workspace-layout');
            const icons = document.querySelectorAll('.toggle-sidebar-icon');
            layout.classList.toggle('sidebar-collapsed');
            
            const isCollapsed = layout.classList.contains('sidebar-collapsed');
            icons.forEach(function(icon) {
                if (isCollapsed) {
                    icon.classList.replace('fa-angle-double-left', 'fa-angle-double-right');
                } else {
                    icon.classList.replace('fa-angle-double-right', 'fa-angle-double-left');
                }
            });
            localStorage.setItem('encounterSidebarCollapsed', isCollapsed ? 'true' : 'false');
        }

        // Initialize state on load
        document.addEventListener('DOMContentLoaded', function() {
            const savedState = localStorage.getItem('encounterSidebarCollapsed');
            // Default: collapsed on mobile unless explicitly expanded, or restore saved state
            const isCollapsed = savedState !== null ? savedState === 'true' : window.innerWidth <= 991.98;
            
            const layout = document.querySelector('.encounter-workspace-layout');
            if (isCollapsed && layout) {
                layout.classList.add('sidebar-collapsed');
                document.querySelectorAll('.toggle-sidebar-icon').forEach(function(icon) {
                    icon.classList.replace('fa-angle-double-left', 'fa-angle-double-right');
                });
            }
            
            // Set title attributes for tooltips when sidebar is collapsed
            document.querySelectorAll('.encounter-sidebar .nav-link').forEach(function(link) {
                const sidebarText = link.querySelector('.sidebar-text');
                if (sidebarText && !link.getAttribute('title')) {
                    link.setAttribute('title', sidebarText.innerText.trim());
                }

                // Mobile touch fix: trigger $(link).click() on touchend (matching subtabs behavior)
                link.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    $(link).click();
                }, { passive: false });
            });
        });
    </script>
    @endpush

    <div class="encounter-workspace-layout" style="gap: 15px;">
        <!-- Sidebar Navigation (Desktop) -->
        <div class="encounter-sidebar-wrapper d-none d-md-block">
            <ul class="nav nav-pills flex-column encounter-sidebar" id="myTab" role="tablist">
                {{-- Top Collapse Header Button --}}
                <li class="nav-item mb-2 pb-2 border-bottom w-100 text-center toggle-sidebar-btn">
                    <button type="button" class="btn btn-sm btn-light w-75 rounded-pill shadow-sm" onclick="toggleSidebar()" style="color: var(--hos-color-var);">
                        <i class="fa fa-angle-double-left toggle-sidebar-icon"></i> <span class="sidebar-text ms-1 fw-bold">Collapse</span>
                    </button>
                </li>

                @php $plansEnabled = appsettings('enable_treatment_plans_in_consult', true); @endphp
                @if($plansEnabled)
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="treatment_plans_tab" data-toggle="tab" href="#treatment_plans" data-target="#treatment_plans" data-bs-toggle="tab" data-bs-target="#treatment_plans" role="tab" aria-controls="treatment_plans" aria-selected="true">
                        <i class="mdi mdi-clipboard-pulse me-3"></i><span class="sidebar-text">Treatment Plans</span>
                        <span class="tp-tab-pulse" id="tp-tab-pulse" style="display:none;"></span>
                        <span class="badge bg-teal rounded-pill ms-auto tp-plan-count-badge" id="tp-plan-count-badge" style="display:none;"></span>
                    </a>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ !$plansEnabled ? 'active' : '' }}" id="clinical_story_tab" data-toggle="tab" href="#clinical_story" data-target="#clinical_story" data-bs-toggle="tab" data-bs-target="#clinical_story" role="tab" aria-controls="clinical_story" aria-selected="{{ !$plansEnabled ? 'true' : 'false' }}">
                        <i class="mdi mdi-history me-3"></i><span class="sidebar-text">Clinical Story</span><span class="badge bg-success rounded-pill ms-auto" style="font-size: 0.65rem;">NEW</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="vitals_data_tab" data-toggle="tab" href="#vitals" data-target="#vitals" data-bs-toggle="tab" data-bs-target="#vitals" role="tab" aria-controls="vitals_data" aria-selected="false">
                        <i class="mdi mdi-heart-pulse me-3"></i><span class="sidebar-text">Vitals / Allergies</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="nurse_charts_tab" data-toggle="tab" href="#nurse_charts" data-target="#nurse_charts" data-bs-toggle="tab" data-bs-target="#nurse_charts" role="tab" aria-controls="nurse_charts" aria-selected="false">
                        <i class="mdi mdi-notebook me-3"></i><span class="sidebar-text">Nurse Charts</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="inj_imm_history_tab" data-toggle="tab" href="#inj_imm_history" data-target="#inj_imm_history" data-bs-toggle="tab" data-bs-target="#inj_imm_history" role="tab" aria-controls="inj_imm_history" aria-selected="false">
                        <i class="mdi mdi-needle me-3"></i><span class="sidebar-text">Inj / Imm History</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="clinical_notes_tab" data-toggle="tab" href="#clinical_notes" data-target="#clinical_notes" data-bs-toggle="tab" data-bs-target="#clinical_notes" role="tab" aria-controls="clinical_notes" aria-selected="false">
                        <i class="mdi mdi-note-text me-3"></i><span class="sidebar-text">Clinical Notes / Diagnosis</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="laboratory_services_tab" data-toggle="tab" href="#laboratory_services" data-target="#laboratory_services" data-bs-toggle="tab" data-bs-target="#laboratory_services" role="tab" aria-controls="laboratory_services" aria-selected="false">
                        <i class="mdi mdi-flask me-3"></i><span class="sidebar-text">Laboratory Services</span>
                        <span class="badge bg-danger rounded-pill ms-auto lab-unviewed-badge" id="lab-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="imaging_services_tab" data-toggle="tab" href="#imaging_services" data-target="#imaging_services" data-bs-toggle="tab" data-bs-target="#imaging_services" role="tab" aria-controls="imaging_services" aria-selected="false">
                        <i class="mdi mdi-radioactive me-3"></i><span class="sidebar-text">Imaging Services</span>
                        <span class="badge bg-danger rounded-pill ms-auto imaging-unviewed-badge" id="imaging-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="medications_tab" data-toggle="tab" href="#medications" data-target="#medications" data-bs-toggle="tab" data-bs-target="#medications" role="tab" aria-controls="medications" aria-selected="false">
                        <i class="mdi mdi-pill me-3"></i><span class="sidebar-text">Medications</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="non_pharm_tab" data-toggle="tab" href="#non_pharm" data-target="#non_pharm" data-bs-toggle="tab" data-bs-target="#non_pharm" role="tab" aria-controls="non_pharm" aria-selected="false">
                        <i class="mdi mdi-heart-pulse me-3"></i><span class="sidebar-text">Care Plan / Non-Pharm</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="procedures_tab" data-toggle="tab" href="#procedures" data-target="#procedures" data-bs-toggle="tab" data-bs-target="#procedures" role="tab" aria-controls="procedures" aria-selected="false">
                        <i class="mdi mdi-medical-bag me-3"></i><span class="sidebar-text">Procedures</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="admissions_tab" data-toggle="tab" href="#admissions" data-target="#admissions" data-bs-toggle="tab" data-bs-target="#admissions" role="tab" aria-controls="admissions" aria-selected="false">
                        <i class="mdi mdi-bed me-3"></i><span class="sidebar-text">Admission History</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="referrals_tab" data-toggle="tab" href="#referrals" data-target="#referrals" data-bs-toggle="tab" data-bs-target="#referrals" role="tab" aria-controls="referrals" aria-selected="false">
                        <i class="mdi mdi-account-switch me-3"></i><span class="sidebar-text">Referrals</span><span class="badge bg-purple ms-auto" id="referral-count-badge" style="display:none;">0</span>
                    </a>
                </li>

                {{-- Bottom Collapse Footer Button --}}
                <li class="nav-item mt-auto pt-3 border-top w-100 text-center toggle-sidebar-btn" style="margin-top: 1rem !important;">
                    <button type="button" class="btn btn-sm btn-light w-75 rounded-pill shadow-sm" onclick="toggleSidebar()" style="color: var(--hos-color-var);">
                        <i class="fa fa-angle-double-left toggle-sidebar-icon"></i> <span class="sidebar-text ms-1 fw-bold">Collapse</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Mobile Bottom Navigation -->
        <div class="mobile-bottom-nav d-md-none">
            @if($plansEnabled)
            <!-- 1. Treatment Plans (Conditional) -->
            <div class="nav-item" role="presentation">
                <a class="nav-link active" id="mobile_treatment_plans_tab" data-toggle="tab" href="#treatment_plans" data-target="#treatment_plans" data-bs-toggle="tab" data-bs-target="#treatment_plans" role="tab" aria-controls="treatment_plans" aria-selected="true">
                    <i class="mdi mdi-clipboard-pulse"></i><span class="sidebar-text">Plans</span>
                    <span class="badge bg-teal rounded-circle position-absolute tp-plan-count-badge" style="display:none; top: 5px; right: 25%;"></span>
                </a>
            </div>
            @endif
            <!-- 2. Story -->
            <div class="nav-item" role="presentation">
                <a class="nav-link {{ !$plansEnabled ? 'active' : '' }}" id="mobile_clinical_story_tab" data-toggle="tab" href="#clinical_story" data-target="#clinical_story" data-bs-toggle="tab" data-bs-target="#clinical_story" role="tab" aria-controls="clinical_story" aria-selected="{{ !$plansEnabled ? 'true' : 'false' }}">
                    <i class="mdi mdi-history"></i><span class="sidebar-text">Story</span>
                </a>
            </div>
            <!-- 3. Vitals -->
            <div class="nav-item" role="presentation">
                <a class="nav-link" id="mobile_vitals_data_tab" data-toggle="tab" href="#vitals" data-target="#vitals" data-bs-toggle="tab" data-bs-target="#vitals" role="tab" aria-controls="vitals_data" aria-selected="false">
                    <i class="mdi mdi-heart-pulse"></i><span class="sidebar-text">Vitals</span>
                </a>
            </div>
            <!-- 4. Notes -->
            <div class="nav-item" role="presentation">
                <a class="nav-link" id="mobile_clinical_notes_tab" data-toggle="tab" href="#clinical_notes" data-target="#clinical_notes" data-bs-toggle="tab" data-bs-target="#clinical_notes" role="tab" aria-controls="clinical_notes" aria-selected="false">
                    <i class="mdi mdi-note-text"></i><span class="sidebar-text">Notes</span>
                </a>
            </div>
            <!-- 5. More (Opens Bottom Sheet) -->
            <div class="nav-item mobile-more-btn">
                <a class="nav-link" data-bs-toggle="modal" data-bs-target="#mobileMoreSheet" style="cursor:pointer;">
                    <i class="mdi mdi-dots-horizontal"></i><span class="sidebar-text">More</span>
                </a>
            </div>
        </div>

        <!-- Mobile More Sheet Modal -->
        <div class="modal fade d-md-none" id="mobileMoreSheet" tabindex="-1" role="dialog" aria-labelledby="mobileMoreSheetLabel" aria-hidden="true" style="padding-right: 0 !important;">
            <div class="modal-dialog m-0 h-100 d-flex flex-column justify-content-end" role="document" style="max-width: 100%;">
                <div class="modal-content" style="border-radius: 1.5rem 1.5rem 0 0; border: none; padding-bottom: 75px; max-height: 75vh;">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold text-uppercase" id="mobileMoreSheetLabel" style="letter-spacing: 0.5px; font-size: 0.8rem; color: #6c757d;">Additional Sections</h6>
                        <button type="button" class="close btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; font-size: 1.5rem; line-height: 1;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-0" style="overflow-y: auto;">
                        <div class="mobile-more-grid">
                            <a class="mobile-more-tile" id="mobile_laboratory_services_tab" data-toggle="tab" href="#laboratory_services" data-target="#laboratory_services" data-bs-toggle="tab" data-bs-target="#laboratory_services" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #1565c0;"><i class="mdi mdi-flask"></i></span>
                                <span class="tile-label">Lab Services</span>
                                <span class="badge bg-danger rounded-pill tile-badge lab-unviewed-badge" style="display: none;"></span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_imaging_services_tab" data-toggle="tab" href="#imaging_services" data-target="#imaging_services" data-bs-toggle="tab" data-bs-target="#imaging_services" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #6a1b9a;"><i class="mdi mdi-radioactive"></i></span>
                                <span class="tile-label">Imaging</span>
                                <span class="badge bg-danger rounded-pill tile-badge imaging-unviewed-badge" style="display: none;"></span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_medications_tab" data-toggle="tab" href="#medications" data-target="#medications" data-bs-toggle="tab" data-bs-target="#medications" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #00897b;"><i class="mdi mdi-pill"></i></span>
                                <span class="tile-label">Medications</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_nurse_charts_tab" data-toggle="tab" href="#nurse_charts" data-target="#nurse_charts" data-bs-toggle="tab" data-bs-target="#nurse_charts" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #ef6c00;"><i class="mdi mdi-notebook"></i></span>
                                <span class="tile-label">Nurse Charts</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_inj_imm_history_tab" data-toggle="tab" href="#inj_imm_history" data-target="#inj_imm_history" data-bs-toggle="tab" data-bs-target="#inj_imm_history" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #c62828;"><i class="mdi mdi-needle"></i></span>
                                <span class="tile-label">Inj / Imm</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_non_pharm_tab" data-toggle="tab" href="#non_pharm" data-target="#non_pharm" data-bs-toggle="tab" data-bs-target="#non_pharm" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #e91e63;"><i class="mdi mdi-heart-pulse"></i></span>
                                <span class="tile-label">Care Plan</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_procedures_tab" data-toggle="tab" href="#procedures" data-target="#procedures" data-bs-toggle="tab" data-bs-target="#procedures" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #2e7d32;"><i class="mdi mdi-medical-bag"></i></span>
                                <span class="tile-label">Procedures</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_admissions_tab" data-toggle="tab" href="#admissions" data-target="#admissions" data-bs-toggle="tab" data-bs-target="#admissions" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #37474f;"><i class="mdi mdi-bed"></i></span>
                                <span class="tile-label">Admissions</span>
                            </a>
                            <a class="mobile-more-tile" id="mobile_referrals_tab" data-toggle="tab" href="#referrals" data-target="#referrals" data-bs-toggle="tab" data-bs-target="#referrals" role="tab" data-bs-dismiss="modal">
                                <span class="tile-icon" style="background: #4527a0;"><i class="mdi mdi-account-switch"></i></span>
                                <span class="tile-label">Referrals</span>
                                <span class="badge bg-purple rounded-pill tile-badge referral-count-badge" style="display:none;">0</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="encounter-main-content" style="min-width: 0; position: relative;">
    <div id="tab-top-scroll-anchor" style="position: absolute; top: -150px; left: 0;"></div>
    <div class="tab-content tp-context-borderable" id="myTabContent">
        {{-- Patient Clinical Story Tab --}}
        <div class="tab-pane fade {{ !$plansEnabled ? 'show active' : '' }}" id="clinical_story" role="tabpanel" aria-labelledby="clinical_story_tab">


            <div class="card-modern mt-2">
                <div class="card-body">
                    @include('admin.partials.clinical_story', ['encounter' => $encounter])
                </div>
            </div>


        </div>

        <div class="tab-pane fade" id="vitals" role="tabpanel" aria-labelledby="vitals_tab">


            <div class="mt-2">
                @include('admin.partials.unified_vitals', ['patient' => $patient])
            </div>
            <div class="card-modern mt-2 border-0">
                 <div class="card-body px-0">

                 </div>
            </div>
        </div>
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var vitalsTab = document.getElementById('vitals_data_tab');
                if(vitalsTab){
                     // Bootstrap 5
                    vitalsTab.addEventListener('shown.bs.tab', function (event) {
                        if(window.initUnifiedVitals) {
                            window.initUnifiedVitals({{ $patient->id }}, null, @json($clinic_name ?? null), @json($vitals_template ?? null), @json($dynamic_ranges ?? []));
                        }
                    });
                    // Fallback/Others
                    $(vitalsTab).on('shown.bs.tab', function (e) {
                         if(window.initUnifiedVitals) {
                            window.initUnifiedVitals({{ $patient->id }}, null, @json($clinic_name ?? null), @json($vitals_template ?? null), @json($dynamic_ranges ?? []));
                        }
                    });
                }
            });

            // Fallback for BS4 modal hide on mobile sheet
            $('#mobileMoreSheet').on('click', '.nav-link, .mobile-more-tile', function() {
                $('#mobileMoreSheet').modal('hide');
            });

            // Global fix for multiple tab triggers (sidebar, mobile nav, mobile sheet)
            // This syncs active links across all navs so Bootstrap can correctly find and hide the previous pane!
            $('a[data-toggle="tab"], a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var targetId = $(e.target).attr('href');
                if (targetId && targetId.startsWith('#')) {
                    // Sync all tab links pointing to this target
                    $('a[data-toggle="tab"], a[data-bs-toggle="tab"]').removeClass('active');
                    $('a[data-toggle="tab"][href="'+targetId+'"], a[data-bs-toggle="tab"][href="'+targetId+'"]').addClass('active');
                    
                    // Scroll to the top smoothly using jQuery to target all possible scroll containers
                    $('html, body, .content-wrapper, .encounter-sidebar-wrapper').animate({ scrollTop: 0 }, 'fast');
                }
            });
        </script>
        @endpush
        <div class="tab-pane fade" id="laboratory_services" role="tabpanel" aria-labelledby="laboratory_services_tab">
            @include('admin.doctors.partials.laboratory_services')
        </div>

        {{-- Imaging Services: Combined Imaging History + Imaging Request --}}
        <div class="tab-pane fade" id="imaging_services" role="tabpanel" aria-labelledby="imaging_services_tab">
            @include('admin.doctors.partials.imaging_services')
        </div>

        {{-- Medications: Combined Prescription History + New Prescription --}}
        <div class="tab-pane fade" id="medications" role="tabpanel" aria-labelledby="medications_tab">
            @include('admin.doctors.partials.medications')
        </div>

        {{-- Non-Pharmacological Care Orders / Care Plan --}}
        <div class="tab-pane fade" id="non_pharm" role="tabpanel" aria-labelledby="non_pharm_tab">


            <div class="card-modern mt-2 tp-context-borderable">
                <div class="card-body">
                    {{-- Active Plan Context Bar (Phase 9) --}}

                    <div id="non-pharm-encounter-container"></div>
                </div>
            </div>


        </div>

        {{-- Procedures Tab --}}
        <div class="tab-pane fade" id="procedures" role="tabpanel" aria-labelledby="procedures_tab">
            @include('admin.doctors.partials.procedures')
        </div>

        {{-- Admission History --}}
        <div class="tab-pane fade" id="admissions" role="tabpanel" aria-labelledby="admissions_tab">


            <div class="card-modern mt-2 tp-context-borderable">
                <div class="card-body">
                    {{-- Active Plan Context Bar (Phase 9) --}}

                    @include('admin.patients.partials.admissions')
                </div>
            </div>


        </div>

        <div class="tab-pane fade" id="referrals" role="tabpanel" aria-labelledby="referrals_tab">


            <div class="card-modern mt-2 tp-context-borderable">
                <div class="card-body">
                    {{-- Active Plan Context Bar (Phase 9) --}}

                    {{-- Create / Edit Referral Section --}}
                    <div class="card-modern border-0 shadow-sm mb-3">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="mdi mdi-account-switch me-1 text-primary"></i> Specialist Referrals</h6>
                            <button type="button" class="btn btn-sm btn-primary" id="toggle-referral-form-btn">
                                <i class="mdi mdi-plus"></i> New Referral
                            </button>
                        </div>
                        <div class="card-body d-none" id="referral-form-card">
                            <form id="create-referral-form">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_referral_id" id="referral-edit-id" value="">

                                {{-- Row 1: Type + Urgency --}}
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="mdi mdi-swap-horizontal-circle text-primary me-1"></i>
                                            Referral Type <span class="text-danger">*</span>
                                        </label>
                                        <select name="referral_type" class="form-select form-select-sm" id="referral-type-select">
                                            <option value="internal">Internal (Within Hospital)</option>
                                            <option value="external">External (Outside Hospital)</option>
                                        </select>
                                        <small class="form-text text-muted"><i class="mdi mdi-information-outline"></i> Internal referrals can be booked directly by reception</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="mdi mdi-alert-circle text-warning me-1"></i>
                                            Urgency <span class="text-danger">*</span>
                                        </label>
                                        <select name="urgency" class="form-select form-select-sm">
                                            <option value="routine">&#x1F7E2; Routine</option>
                                            <option value="urgent">&#x1F7E1; Urgent</option>
                                            <option value="emergency">&#x1F534; Emergency</option>
                                        </select>
                                        <small class="form-text text-muted"><i class="mdi mdi-information-outline"></i> Emergency referrals are flagged &amp; prioritized at reception</small>
                                    </div>
                                </div>

                                {{-- Internal fields: Clinic + Doctor --}}
                                <div id="referral-internal-fields">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-hospital-building text-info me-1"></i>
                                                Target Clinic <span class="text-danger">*</span>
                                            </label>
                                            <select name="target_clinic_id" class="form-select form-select-sm">
                                                <option value="">-- Select Clinic --</option>
                                                @foreach($allClinics as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">Required for internal referrals</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-doctor text-success me-1"></i>
                                                Target Doctor
                                            </label>
                                            <select name="target_doctor_id" class="form-select form-select-sm">
                                                <option value="">-- Any Available Doctor --</option>
                                                @foreach($doctorStaffList as $staff)
                                                    <option value="{{ $staff->id }}">{{ $staff->user ? trim(($staff->user->surname ?? '').' '.($staff->user->firstname ?? '')) : 'Staff #'.$staff->id }}</option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">Optional &mdash; leave blank for any available doctor</small>
                                        </div>
                                    </div>
                                </div>

                                {{-- External fields: Facility + Doctor + Address + Phone --}}
                                <div id="referral-external-fields" style="display:none;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-hospital-marker text-danger me-1"></i>
                                                External Facility <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="external_facility_name" class="form-control form-control-sm" placeholder="e.g., City General Hospital">
                                            <small class="form-text text-muted">Name of the hospital or clinic being referred to</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-account-tie text-secondary me-1"></i>
                                                External Doctor
                                            </label>
                                            <input type="text" name="external_doctor_name" class="form-control form-control-sm" placeholder="e.g., Dr. John Smith">
                                            <small class="form-text text-muted">Optional &mdash; name of the receiving doctor</small>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-map-marker text-muted me-1"></i>
                                                Facility Address
                                            </label>
                                            <input type="text" name="external_facility_address" class="form-control form-control-sm" placeholder="Address of the external facility">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold mb-1">
                                                <i class="mdi mdi-phone text-muted me-1"></i>
                                                Facility Phone
                                            </label>
                                            <input type="text" name="external_facility_phone" class="form-control form-control-sm" placeholder="+234...">
                                        </div>
                                    </div>
                                </div>

                                {{-- Clinical Details --}}
                                <hr class="my-2">
                                <p class="mb-2 text-muted" style="font-size:.8rem;"><i class="mdi mdi-stethoscope me-1"></i> Clinical Information</p>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="mdi mdi-clipboard-pulse text-primary me-1"></i>
                                            Provisional Diagnosis
                                        </label>
                                        <input type="text" name="provisional_diagnosis" class="form-control form-control-sm" placeholder="e.g., Suspected fracture, chronic renal disease">
                                        <small class="form-text text-muted">Helps the receiving doctor prepare</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="mdi mdi-file-document-outline text-secondary me-1"></i>
                                            Clinical Summary
                                        </label>
                                        <input type="text" name="clinical_summary" class="form-control form-control-sm" placeholder="Brief overview of patient's condition">
                                        <small class="form-text text-muted">Optional &mdash; relevant history &amp; findings for the specialist</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-bold mb-1">
                                            <i class="mdi mdi-text-box-outline text-dark me-1"></i>
                                            Reason for Referral <span class="text-danger">*</span>
                                        </label>
                                        <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Why is this patient being referred? Include relevant clinical findings..." required></textarea>
                                        <small class="form-text text-muted">Provide clear reasoning so the receiving team can prioritize appropriately</small>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted"><span class="text-danger">*</span> = Required field</small>
                                    <div>
                                        <button type="button" class="btn btn-secondary btn-sm" id="cancel-referral-form-btn">
                                            <i class="mdi mdi-close"></i> Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-sm" id="referral-submit-btn">
                                            <i class="mdi mdi-send"></i> Submit Referral
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ── Referral List Sub-Tabs ── --}}
                    <ul class="nav nav-pills nav-fill mt-2 mb-2" id="referralSubTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2" id="refSubTab-encounter" data-bs-toggle="pill" data-bs-target="#refPane-encounter" type="button" role="tab">
                                <i class="mdi mdi-file-document-outline me-1"></i>This Encounter <span class="badge bg-secondary ms-1" id="ref-encounter-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2" id="refSubTab-patient" data-bs-toggle="pill" data-bs-target="#refPane-patient" type="button" role="tab">
                                <i class="mdi mdi-history me-1"></i>All Patient Referrals <span class="badge bg-secondary ms-1" id="ref-patient-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2" id="refSubTab-incoming" data-bs-toggle="pill" data-bs-target="#refPane-incoming" type="button" role="tab">
                                <i class="mdi mdi-arrow-down-bold-circle me-1"></i>Incoming To Me <span class="badge bg-info ms-1" id="incoming-referral-count" style="display:none;">0</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="referralSubTabContent">
                        {{-- Sub-pane: This Encounter --}}
                        <div class="tab-pane fade show active" id="refPane-encounter" role="tabpanel">
                            <div id="referrals-list-container">
                                <div class="text-center text-muted py-3" id="referrals-loading">
                                    <i class="fa fa-spinner fa-spin"></i> Loading referrals...
                                </div>
                                <div id="referrals-list"></div>
                            </div>
                        </div>

                        {{-- Sub-pane: All Patient Referrals --}}
                        <div class="tab-pane fade" id="refPane-patient" role="tabpanel">
                            <small class="text-muted d-block mb-2"><i class="mdi mdi-information-outline me-1"></i>Complete referral history for this patient across all encounters. You can edit/delete your own pending referrals.</small>
                            <div class="text-center text-muted py-3" id="patient-referrals-loading" style="display:none;">
                                <i class="fa fa-spinner fa-spin"></i> Loading patient referrals...
                            </div>
                            <div id="patient-referrals-list"></div>
                        </div>

                        {{-- Sub-pane: Incoming To Me --}}
                        <div class="tab-pane fade" id="refPane-incoming" role="tabpanel">
                            <small class="text-muted d-block mb-2"><i class="mdi mdi-information-outline me-1"></i>Pending referrals from other doctors directed to you or your clinic. You can accept and start a new encounter.</small>
                            <div class="text-center text-muted py-2" id="incoming-referrals-loading" style="display:none;">
                                <i class="fa fa-spinner fa-spin"></i> Loading...
                            </div>
                            <div id="incoming-referrals-list"></div>
                        </div>
                    </div>

                    <!-- Bottom Tab Navigation -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="switch_tab(event, 'admissions_tab')" style="border-radius: 8px; font-weight: 600;">
                                <i class="fa fa-arrow-left me-1"></i> Previous (Admission History)
                            </button>
                        </div>
                        <div>
                            <span class="text-muted small"><i class="fa fa-check-circle me-1"></i> All sections reviewed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Clinical Notes/Diagnosis: Combined History + New Entry --}}
        <div class="tab-pane fade" id="clinical_notes" role="tabpanel" aria-labelledby="clinical_notes_tab">
            @include('admin.doctors.partials.clinical_notes')
        </div>

        {{-- Treatment Plans Tab --}}
        @if($plansEnabled)
        <div class="tab-pane fade show active" id="treatment_plans" role="tabpanel" aria-labelledby="treatment_plans_tab">
            @include('admin.doctors.partials.treatment_plans_tab')
        </div>
        @endif



        <div class="tab-pane fade" id="nurse_charts" role="tabpanel" aria-labelledby="nurse_charts_tab">


            <div class="card-modern mt-2">
                <div class="card-body">
                    <!-- Date Range Filter -->
                    <div class="card-modern mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i> Date Range Filter</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="chart-date-from" class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="chart-date-from">
                                </div>
                                <div class="col-md-4">
                                    <label for="chart-date-to" class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="chart-date-to">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" id="apply-date-filter" class="btn btn-primary me-2">
                                        <i class="mdi mdi-filter"></i> Apply Filter
                                    </button>
                                    <button type="button" id="reset-date-filter" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="text-muted mb-0">
                                <i class="mdi mdi-calendar me-1"></i>
                                <span id="date-range-summary">Showing data from the last 30 days</span>
                            </h5>
                        </div>
                        <div id="data-summary-stats" class="d-flex gap-3">
                            <!-- Will be populated with JavaScript -->
                        </div>
                    </div>

                    <!-- Tabs for Medication, Intake/Output Charts, and Nursing Notes -->
                    <ul class="nav nav-tabs mb-3" id="nurseChartTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="medication-chart-tab" data-toggle="tab" href="#medication-chart"
                               role="tab" aria-controls="medication-chart" aria-selected="true">
                               <i class="mdi mdi-pill me-1"></i> Medication Chart
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="fluid-io-chart-tab" data-toggle="tab" href="#fluid-io-chart"
                               role="tab" aria-controls="fluid-io-chart" aria-selected="false">
                               <i class="mdi mdi-water me-1"></i> Fluid Intake/Output
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="solid-io-chart-tab" data-toggle="tab" href="#solid-io-chart"
                               role="tab" aria-controls="solid-io-chart" aria-selected="false">
                               <i class="mdi mdi-food-apple me-1"></i> Solid Intake/Output
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="nursing-notes-history-tab" data-toggle="tab" href="#nursing-notes-history"
                               role="tab" aria-controls="nursing-notes-history" aria-selected="false">
                               <i class="mdi mdi-notebook me-1"></i> Nursing Notes History
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="nurseChartTabContent">
                        <!-- Medication Chart Tab -->
                        <div class="tab-pane fade show active" id="medication-chart" role="tabpanel" aria-labelledby="medication-chart-tab">
                            <div class="medication-chart-container">
                                <div id="medication-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading medication chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fluid I/O Chart Tab -->
                        <div class="tab-pane fade" id="fluid-io-chart" role="tabpanel" aria-labelledby="fluid-io-chart-tab">
                            <div class="fluid-io-chart-container">
                                <div id="fluid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading fluid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Solid I/O Chart Tab -->
                        <div class="tab-pane fade" id="solid-io-chart" role="tabpanel" aria-labelledby="solid-io-chart-tab">
                            <div class="solid-io-chart-container">
                                <div id="solid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading solid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nursing Notes History Tab -->
                        <div class="tab-pane fade" id="nursing-notes-history" role="tabpanel" aria-labelledby="nursing-notes-history-tab">
                            <div class="alert alert-info">
                                <i class="mdi mdi-information-outline me-2"></i>
                                View nursing notes history for the patient.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%" id="nurse_note_hist_5">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Note type</th>
                                            <th>Details</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>
        {{-- Injection & Immunization History Tab --}}
        <div class="tab-pane fade" id="inj_imm_history" role="tabpanel" aria-labelledby="inj_imm_history_tab">


            <div class="card-modern mt-2">
                <div class="card-body">
                    @include('admin.patients.partials.injection_immunization_history', ['patient' => $patient])


                </div>
            </div>
        </div>
        <form action="{{ route('encounters.store') }}" method="post">
            @csrf
            <div class="tab-pane fade d-none" id="my_notes_old" role="tabpanel" aria-labelledby="my_notes_old_tab">
                <div class="card-modern mt-2">
                    <div class="card-body table-responsive">

                        <input type="hidden" value="{{ $req_entry->service_id ?? 'ward_round' }}"
                            name="req_entry_service_id" required>
                        <input type="hidden" value="{{ $req_entry->id ?? 'ward_round' }}" name="req_entry_id">
                        <input type="hidden" value="{{ request()->get('patient_id') }}" name="patient_id"
                            id="encounter_patient_id__">
                        <input type="hidden" value="{{ request()->get('queue_id') ?? 'ward_round' }}" name="queue_id">
                        <input type="hidden" id="encounter_id__" name="encounter_id" value="{{ $encounter->id }}"
                            required>
                        @if (request()->get('admission_req_id') != '')
                            <input type="hidden" value="{{ request()->get('admission_req_id') }}" name="queue_id">
                        @endif
                        <div class="form-group">
                            <div class="container">
                                <div class="accordion" id="accordionForProfile">
                                    <div class="accordion-item">
                                        <h4 class="accordion-header" id="flush-headingOne">
                                            <span class="collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#flush-collapseOne" aria-expanded="false"
                                                aria-controls="flush-collapseOne">
                                                <span class="fa fa-eye"></span>
                                                See Patient Profiles</span>
                                            <span class="fa fa-caret-down"></span>
                                        </h4>
                                        <div id="flush-collapseOne" class="accordion-collapse collapse"
                                            aria-labelledby="flush-headingOne" data-bs-parent="#accordionForProfile">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-between">
                                                    <h5>Forms/Profiles</h5>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#profileModal"> <span class="fa fa-plus"></span>
                                                        Fill New patient Profile
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table" id="profile_forms_table" style="width: 100%">
                                                        <thead>
                                                            <th>#</th>
                                                            <th>Form Data</th>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- {!!generateForm($formdata)!!} --}}

                            </div>
                        </div>
                        <hr>
                        {{-- clinical_notes partial already included in #clinical_notes tab --}}
            {{-- Conclusion tab removed - now handled by modal --}}
                <style>
                    /* Custom Toggle Switch Styles */
                    .toggle-switch {
                        position: relative;
                        display: inline-block;
                        width: 60px;
                        height: 34px;
                    }

                    .toggle-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }

                    .toggle-slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #ccc;
                        transition: .4s;
                        border-radius: 34px;
                    }

                    .toggle-slider:before {
                        position: absolute;
                        content: "";
                        height: 26px;
                        width: 26px;
                        left: 4px;
                        bottom: 4px;
                        background-color: white;
                        transition: .4s;
                        border-radius: 50%;
                    }

                    input:checked + .toggle-slider {
                        background-color: {{ appsettings('hos_color', '#007bff') }};
                    }

                    input:focus + .toggle-slider {
                        box-shadow: 0 0 1px {{ appsettings('hos_color', '#007bff') }};
                    }

                    input:checked + .toggle-slider:before {
                        transform: translateX(26px);
                    }
                </style>
                {{-- Old conclusion tab content removed - now using modal instead --}}
                    </div>
                </div>
            </div>
        </form>
        
    </div> <!-- end #myTabContent -->

    </div> <!-- end encounter-main-content -->
</div> <!-- end encounter-workspace-layout -->

<!-- Universal Floating Action Bar (Desktop + Mobile) -->
<div class="encounter-floating-nav" id="encounterFloatingNav">
    <div class="d-flex align-items-center justify-content-between w-100">
        <div id="floating-nav-prev-container"></div>
        <div id="floating-nav-save-container"></div>
        <div id="floating-nav-next-container"></div>
    </div>
</div>

    <!-- Medication Details Modal (Read-Only for Doctors) -->
    <div class="modal fade" id="medDetailsModal" tabindex="-1" aria-labelledby="medDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="medDetailsModalLabel">
                        <i class="mdi mdi-pill me-2"></i>Medication Details
                    </h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medDetailsModalBody">
                    <!-- Content populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!--Profile / Form  Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Fill Profile / Form</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card-modern">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-10">
                                    <label for="form_type">Form Type</label>
                                    <select id="form_type" class="form-control">
                                        <option value="">--select form--</option>
                                        <option value="test">Test</option>
                                        <option value="anc">ANC</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button onclick="getForm()" class="btn btn-primary mt-4">Get Form</button>
                                </div>
                            </div>
                            <hr>
                            <form action="{{ route('patient-form.store') }}" method="post" id="patient_form_form">


                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="nursingNoteModal" tabindex="-1" role="dialog" aria-labelledby="nursingNoteModal"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Nursing Note Result (<span
                            id="note_type_name_"></span>)</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
                </form>
            </div>
        </div>
    </div>

@endsection
@include('admin.doctors.partials._encounter_scripts')

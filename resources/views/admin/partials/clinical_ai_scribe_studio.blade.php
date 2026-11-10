{{-- Clinical AI Scribe Studio ("Full AI Mode") Cockpit Overlay --}}
@php
    $role = $role ?? 'doctor';
    $targetEditorId = $targetEditorId ?? 'doctor_diagnosis_text';
    $editorType = $editorType ?? 'ckeditor';
    $patientName = isset($patient) && isset($patient->user) 
        ? ($patient->user->surname . ' ' . $patient->user->firstname . ($patient->user->othername ? ' ' . $patient->user->othername : '')) 
        : 'Patient Record';
    $patientFileNo = isset($patient) ? ($patient->file_no ?? $patient->id) : '000-000';
    $clinicOrWard = isset($clinic) ? $clinic->name : (isset($ward) ? $ward->name : 'General Medicine / Ward');
    
    // Dynamic titles
    $studioTitle = match($role) {
        'nurse' => 'Nursing Clinical Progress & Shift Review',
        'maternity' => (isset($enrollment) && $enrollment->status === 'postnatal' ? 'Maternity · Postnatal Care (PNC) Follow-up' : 'Maternity · Antenatal Care (ANC) Consultation'),
        default => 'Clinical Consultation',
    };

    $clinicianBadge = match($role) {
        'nurse' => 'RN',
        'maternity' => 'MW',
        default => 'Dr',
    };
@endphp

<div id="clinical-ai-scribe-overlay" class="clinical-ai-scribe-overlay" role="dialog" aria-modal="true" aria-labelledby="scribe-studio-title">
    {{-- Header Ribbon with Master Action Controls (Always Available & Persistent) --}}
    <div class="scribe-studio-header">
        <div class="scribe-header-title-box">
            <h2 id="scribe-studio-title" class="scribe-studio-title">{{ $studioTitle }}</h2>
            <div class="scribe-header-badges">
                <span class="scribe-pill-badge" id="scribe-patient-badge">{{ $patientName }} · ID {{ $patientFileNo }}</span>
                <span class="scribe-pill-badge" id="scribe-location-badge">{{ $clinicOrWard }}</span>
                <span class="scribe-pill-badge active-session"><i class="mdi mdi-shield-check"></i> Live Clinical Data</span>
                <div class="scribe-user-avatar" title="Attending Clinician">{{ $clinicianBadge }}</div>
            </div>
        </div>
        <div class="scribe-header-actions d-flex align-items-center gap-2">
            <span class="scribe-draft-notice me-2 d-none d-lg-inline" id="scribe-draft-notice"></span>
            <button type="button" class="btn-scribe-reject" id="btn-master-reject" title="Discard draft notes and uncommitted orders">
                <i class="mdi mdi-trash-can-outline"></i> <span>Discard</span>
            </button>
            <button type="button" class="btn-scribe-edit" id="btn-master-edit" title="Toggle edit mode on structured note">
                <i class="mdi mdi-pencil-outline"></i> <span>Edit</span>
            </button>
            <button type="button" class="btn-scribe-sign" id="btn-master-sign" title="Commit polished note and dispatch all orders to hospital queues">
                <i class="mdi mdi-check-circle"></i> <span class="sign-btn-label">Accept & sign</span>
                <span class="badge bg-white text-success rounded-pill ms-1 scribe-sign-orders-badge" style="display: none;">0</span>
            </button>
            <div class="scribe-header-divider"></div>
            <button type="button" class="btn-close-scribe" aria-label="Close Studio" title="Close (Esc)">
                <i class="mdi mdi-close"></i>
            </button>
        </div>
    </div>

    {{-- Workflow Step Switcher Ribbon (1. Summary -> 2. Dictate -> 3. Note -> 4. Orders -> Split) --}}
    <div class="scribe-workflow-bar" id="scribe-workflow-bar">
        <div class="scribe-step-nav" id="scribe-step-nav">
            <button type="button" class="scribe-step-btn active" data-step="summary" title="Step 1: Patient Longitudinal Summary & EHR Brief">
                <i class="mdi mdi-clipboard-account-outline"></i> <span>1. Summary</span>
            </button>
            <button type="button" class="scribe-step-btn" data-step="dictate" title="Step 2: Ambient Voice Dictation & Audio Stream">
                <i class="mdi mdi-microphone"></i> <span>2. Dictate</span>
            </button>
            <button type="button" class="scribe-step-btn" data-step="note" title="Step 3: Structured Clinical Note & Polish">
                <i class="mdi mdi-text-box-edit-outline"></i> <span>3. Note</span>
            </button>
            <button type="button" class="scribe-step-btn" data-step="orders" title="Step 4: Clinical Orders & Prescriptions Deck">
                <i class="mdi mdi-cart-outline"></i> <span>4. Orders</span>
                <span class="badge bg-secondary rounded-pill ms-1" id="scribe-step-orders-count">0</span>
            </button>
            <button type="button" class="scribe-step-btn" data-step="balanced" title="Balanced Cockpit View">
                <i class="mdi mdi-view-split-vertical"></i> <span>Split</span>
            </button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2" style="font-size: 0.72rem;">
                <i class="mdi mdi-shield-check"></i> Closed-Loop Clinical Safety
            </span>
        </div>
    </div>

    {{-- Pillar 1: Patient Longitudinal Summary Deck (Foldable, Dockable, Maximizable) --}}
    <div class="scribe-summary-deck summary-docked" id="scribe-summary-deck">
        <div class="scribe-summary-header">
            <div class="scribe-summary-title" id="btn-toggle-summary-deck-header" style="cursor: pointer;" title="Click to fold or expand patient summary deck">
                <i class="mdi mdi-creation text-primary"></i>
                <span>Patient Longitudinal Summary</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-2" style="font-size: 0.68rem;"><i class="mdi mdi-check-circle"></i> AI Context Active</span>
                <span class="text-muted small ms-2 d-none d-md-inline" id="scribe-summary-preview-ticker">Docked · Click to review patient history</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" id="btn-summary-audio-narrate" title="Audio Narration (Text-to-Speech)">
                    <i class="mdi mdi-volume-high" id="icon-summary-audio"></i> <span id="text-summary-audio">Listen</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" id="btn-fold-summary-deck" title="Toggle Fold/Expand">
                    <i class="mdi mdi-chevron-down" id="icon-fold-summary-deck"></i> <span id="text-fold-summary-deck">Expand</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" id="btn-maximize-summary-deck" title="Maximize Summary View">
                    <i class="mdi mdi-arrow-expand" id="icon-max-summary-deck"></i> Focus
                </button>
            </div>
        </div>

        <div class="scribe-summary-body-wrap" id="scribe-summary-body-wrap">
            <div class="scribe-summary-scroll-area" id="scribe-summary-content">
                <div class="text-muted text-center py-3">Loading longitudinal records synthesis...</div>
            </div>
        </div>
    </div>

    {{-- Dual Pane Cockpit Grid (Pillars 2 & 3: Dictation & Structured Note) --}}
    <div class="scribe-workspace-grid grid-balanced" id="scribe-workspace-grid">
        {{-- Left Pane: Voice Dictation & Ambient Audio Stream (Pillar 2) --}}
        <div class="scribe-card" id="scribe-card-dictation">
            <div class="scribe-card-header">
                <h3 class="scribe-card-title">
                    <i class="mdi mdi-microphone" style="color: var(--hospital-primary, #0066cc);"></i> <span>Voice Dictation</span>
                </h3>
                <div class="d-flex align-items-center gap-2">
                    <span class="scribe-recording-badge" id="scribe-recording-badge" style="display: none;">
                        <span class="recording-dot"></span> Recording <span id="scribe-recording-timer">00:00</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" id="btn-scribe-toggle-rec">
                        <i class="mdi mdi-pause"></i> Pause
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2" id="btn-scribe-restart-rec" title="Reset Dictation">
                        <i class="mdi mdi-refresh"></i> Reset
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-card-focus" id="btn-focus-dictate" title="Expand Dictation View">
                        <i class="mdi mdi-arrow-expand"></i> Focus
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 rounded-pill btn-card-fold" id="btn-fold-dictate" title="Fold Dictation">
                        <i class="mdi mdi-chevron-left"></i>
                    </button>
                </div>
            </div>

            {{-- Clerking Method Selector inside Dictate Card --}}
            <div class="scribe-card-clerking-bar" id="scribe-card-clerking-bar">
                <span class="clerking-label"><i class="mdi mdi-format-list-bulleted-type me-1"></i>Clerking Method:</span>
                <div class="scribe-taxonomy-pills d-inline-flex">
                    @if($role === 'doctor')
                        <button type="button" class="taxonomy-pill-btn active" data-taxonomy="clerking" title="Traditional Nigerian Clinical Clerking (NMDC / Teaching Hospital Standard)">Clerking (Default)</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="soap" title="Subjective, Objective, Assessment, Plan">SOAP</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="pomr" title="Problem-Oriented Medical Record">POMR</button>
                    @elseif($role === 'nurse')
                        <button type="button" class="taxonomy-pill-btn active" data-taxonomy="dar" title="Focus Charting: Data, Action, Response">Focus DAR (Default)</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="sbar" title="Situation, Background, Assessment, Recommendation">SBAR Handover</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="soapie" title="NMCN Nursing Process: SOAPIE">SOAPIE</button>
                    @else
                        <button type="button" class="taxonomy-pill-btn active" data-taxonomy="anc">Antenatal (ANC)</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="labor">Labor & Delivery</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="pnc">Postnatal (PNC)</button>
                        <button type="button" class="taxonomy-pill-btn" data-taxonomy="neonatal">Neonatal</button>
                    @endif
                </div>
            </div>

            {{-- 60 FPS Real-time Waveform (Hospital Primary Color) --}}
            <div class="scribe-waveform-container" id="scribe-waveform-container">
                @for ($i = 0; $i < 24; $i++)
                    <div class="scribe-waveform-bar"></div>
                @endfor
            </div>

            {{-- Combined Baseline Notes Context & Live Streaming Dictation --}}
            <div class="scribe-transcript-scroll-area">
                {{-- Existing Note Context (Shown if editor already has baseline notes) --}}
                <div class="scribe-baseline-box" id="scribe-baseline-box" style="display: none;">
                    <div class="scribe-baseline-header">
                        <span><i class="mdi mdi-file-document-outline"></i> Existing Note in Active Chart</span>
                        <span class="scribe-baseline-badge">Prior Context</span>
                    </div>
                    <div class="scribe-baseline-body" id="scribe-baseline-body"></div>
                </div>

                {{-- Context Divider Line --}}
                <div class="scribe-context-divider" id="scribe-context-divider" style="display: none;">
                    <span class="scribe-divider-label"><i class="mdi mdi-microphone-outline"></i> Live Voice Dictation Stream</span>
                </div>

                {{-- Live Streaming Transcription Box (Voice-Streamed & Directly Editable) --}}
                <div class="scribe-transcript-box" id="scribe-live-transcript" contenteditable="true" spellcheck="true" title="Live transcribed text appears here. You can also edit or type directly.">
                    <span class="text-muted">Listening for consultation dialogue... (Speak or type directly here)</span> <span class="transcript-caret"></span>
                </div>
            </div>

            {{-- Left Pane Footer / Language Switcher --}}
            <div class="scribe-left-footer">
                <div>
                    <span>Live transcript, synchronised with audio · </span>
                    <select class="scribe-lang-select" id="scribe-lang-select" title="Speech Recognition Language">
                        <option value="en-NG" selected>Nigerian English (en-NG)</option>
                        <option value="en-GB">British English (en-GB)</option>
                        <option value="en-US">American English (en-US)</option>
                        <option value="pcm-NG">Nigerian Pidgin (pcm)</option>
                        <option value="ha-NG">Hausa (ha-NG)</option>
                        <option value="yo-NG">Yoruba (yo-NG)</option>
                        <option value="ig-NG">Igbo (ig-NG)</option>
                        <option value="fr-FR">Français (fr-FR)</option>
                        <option value="es-ES">Español (es-ES)</option>
                        <option value="ar-SA">العربية (ar-SA)</option>
                        <option value="pt-PT">Português (pt-PT)</option>
                        <option value="nl-NL">Nederlands (nl-NL)</option>
                        <option value="zh-CN">中文 (zh-CN)</option>
                    </select>
                </div>
                <button type="button" class="btn-scribe-process" id="btn-scribe-process">
                    <i class="mdi mdi-auto-fix"></i> Structure & Polish
                </button>
            </div>
        </div>

        {{-- Right Pane: AI-Generated Structured Summary (Pillar 3) --}}
        <div class="scribe-card" id="scribe-card-note">
            <div class="scribe-card-header">
                <h3 class="scribe-card-title">
                    <i class="mdi mdi-creation" style="color: #f59e0b;"></i> <span id="scribe-headline-text">AI-Generated Structured Note</span>
                </h3>
                <div class="d-flex align-items-center gap-2">
                    <span class="scribe-review-badge">Draft · clinician review required</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill btn-card-focus" id="btn-focus-note" title="Expand Note View">
                        <i class="mdi mdi-arrow-expand"></i> Focus
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-1 rounded-pill btn-card-fold" id="btn-fold-note" title="Fold Note">
                        <i class="mdi mdi-chevron-right"></i>
                    </button>
                </div>
            </div>

            {{-- Structured Note Sections Container --}}
            <div class="scribe-sections-container" id="scribe-sections-container">
                <div class="text-muted p-4 text-center">
                    <i class="mdi mdi-text-box-search-outline fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    No structured note generated yet. Dictate speech or click "Structure & Polish" to process current notes.
                </div>
            </div>

            {{-- Safety Warnings Container --}}
            <div id="scribe-safety-warnings"></div>

            {{-- Actions Bar --}}
            <div class="scribe-actions-row">
                <button type="button" class="btn-scribe-sign" id="btn-scribe-sign">
                    <i class="mdi mdi-check-circle"></i> Accept & sign
                </button>
                <button type="button" class="btn-scribe-edit" id="btn-scribe-edit">Edit</button>
                <button type="button" class="btn-scribe-reject" id="btn-scribe-reject">Discard</button>
            </div>
        </div>
    </div>

    {{-- Foldable Recommended Orders & Prescriptions Deck (Pillar 4) --}}
    <div class="scribe-orders-deck orders-docked" id="scribe-orders-deck">
        <div class="scribe-orders-header">
            <div class="scribe-orders-title" id="btn-toggle-orders-deck-header" style="cursor: pointer;" title="Click to fold or expand orders deck">
                <i class="mdi mdi-cart-outline" style="color: var(--hospital-primary, #0066cc);"></i>
                <span>Clinical Orders & Prescriptions</span>
                <span class="badge bg-primary rounded-pill py-1 px-2 ms-2" id="scribe-orders-count-badge">0 RECOMMENDED</span>
                <span class="badge bg-warning text-dark rounded-pill py-1 px-2 ms-1" id="scribe-orders-freeform-count" style="display: none;">0 Free-form</span>
                <span class="text-muted small ms-2 d-none d-md-inline" id="scribe-orders-preview-ticker">Docked · Click to expand</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-success rounded-pill py-0 px-3 btn-scribe-sign d-none d-md-inline-flex align-items-center gap-1" title="Accept note and dispatch all selected orders">
                    <i class="mdi mdi-check-circle"></i> <span>Accept & Sign All</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill" id="btn-fold-orders-deck" title="Toggle Fold/Expand">
                    <i class="mdi mdi-chevron-up" id="icon-fold-orders-deck"></i> <span id="text-fold-orders-deck">Expand</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" id="btn-maximize-orders-deck" title="Maximize Orders View">
                    <i class="mdi mdi-arrow-expand" id="icon-max-orders-deck"></i> Focus
                </button>
            </div>
        </div>

        <div class="scribe-orders-body-wrap" id="scribe-orders-body-wrap">
            {{-- High-visibility banner when any recommended orders are not in catalog --}}
            <div id="scribe-free-form-warning-banner" style="display: none;" class="alert alert-warning py-1 px-3 mx-2 mb-2 rounded-2 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="mdi mdi-alert-circle-outline fs-5 me-2 text-warning"></i>
                    <span style="font-size: 0.82rem;"><strong>Notice:</strong> One or more items below are marked <strong>[FREE-FORM]</strong>. Use <strong>[Match Catalog]</strong> on any item to link it to an official hospital tariff.</span>
                </div>
            </div>

            <div class="table-responsive scribe-orders-table-wrapper">
                <table class="scribe-order-table">
                    <thead>
                        <tr style="font-size: 0.74rem; color: var(--scribe-text-secondary); text-transform: uppercase;">
                            <th style="width: 35px; text-align: center;"><input type="checkbox" id="chk-all-orders" checked title="Select All"></th>
                            <th style="width: 130px;">Category</th>
                            <th style="min-width: 200px;">Item Details & Catalog Link</th>
                            <th style="min-width: 240px; width: 280px;">Dosage / Regimen / Indication</th>
                            <th style="min-width: 260px; width: 320px;">Clinical Instructions & Notes</th>
                            <th style="width: 45px; text-align: center;">Del</th>
                        </tr>
                    </thead>
                    <tbody id="scribe-orders-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Catalog Search & Link Modal / Popover for Free-Form Items --}}
    <div id="scribe-catalog-matcher-modal" class="scribe-catalog-matcher-modal" style="display: none;">
        <div class="scribe-matcher-backdrop" id="scribe-matcher-backdrop"></div>
        <div class="scribe-matcher-dialog">
            <div class="scribe-matcher-header">
                <div>
                    <h4 class="scribe-matcher-title mb-0"><i class="mdi mdi-database-search text-primary me-2"></i> Match Hospital Catalog</h4>
                    <small class="text-muted" id="scribe-matcher-subtitle">Link order to official services and medications</small>
                </div>
                <button type="button" class="btn-close-matcher" id="btn-close-matcher"><i class="mdi mdi-close"></i></button>
            </div>
            <div class="scribe-matcher-body">
                <div class="scribe-matcher-search-bar">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="mdi mdi-magnify text-muted"></i></span>
                        <input type="text" id="scribe-matcher-search-input" class="form-control border-start-0" placeholder="Type test or medication name...">
                        <button class="btn btn-primary" type="button" id="btn-matcher-search-exec">Search</button>
                    </div>
                </div>
                <div class="scribe-matcher-filter-chips">
                    <button type="button" class="matcher-filter-chip active" data-filter="all">All</button>
                    <button type="button" class="matcher-filter-chip" data-filter="lab">Laboratory</button>
                    <button type="button" class="matcher-filter-chip" data-filter="imaging">Imaging</button>
                    <button type="button" class="matcher-filter-chip" data-filter="procedure">Procedures</button>
                    <button type="button" class="matcher-filter-chip" data-filter="product">Medications (Rx)</button>
                </div>
                <div class="scribe-matcher-results" id="scribe-matcher-results">
                    <div class="text-muted text-center py-4">Search hospital catalog by name, code, or abbreviation.</div>
                </div>
            </div>
        </div>
    </div>
</div>

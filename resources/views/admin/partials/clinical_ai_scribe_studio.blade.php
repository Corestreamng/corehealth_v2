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
    {{-- Header Ribbon --}}
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
        <div>
            <button type="button" class="btn-close-scribe" aria-label="Close Studio" title="Close (Esc)">
                <i class="mdi mdi-close"></i>
            </button>
        </div>
    </div>

    {{-- Taxonomy Switcher Ribbon --}}
    <div class="scribe-taxonomy-bar">
        <div class="scribe-taxonomy-pills">
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
        <div class="scribe-draft-notice" id="scribe-draft-notice"></div>
    </div>

    {{-- Dual Pane Cockpit Grid --}}
    <div class="scribe-workspace-grid">
        {{-- Left Pane: Voice Dictation & Ambient Audio Stream --}}
        <div class="scribe-card">
            <div class="scribe-card-header">
                <h3 class="scribe-card-title">
                    <i class="mdi mdi-microphone" style="color: var(--hospital-primary, #0066cc);"></i> Voice Dictation
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

                {{-- Live Streaming Transcription Box --}}
                <div class="scribe-transcript-box" id="scribe-live-transcript">
                    <span class="text-muted">Listening for consultation dialogue...</span> <span class="transcript-caret"></span>
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

        {{-- Right Pane: AI-Generated Structured Summary --}}
        <div class="scribe-card">
            <div class="scribe-card-header">
                <h3 class="scribe-card-title">
                    <i class="mdi mdi-creation" style="color: #f59e0b;"></i> <span id="scribe-headline-text">AI-Generated Structured Note</span>
                </h3>
                <span class="scribe-review-badge">Draft · clinician review required</span>
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

    {{-- Detailed Orders & Prescriptions Deck (Collapsible Table) --}}
    <div class="scribe-orders-deck" id="scribe-orders-deck" style="display: none;">
        <div class="scribe-orders-header">
            <div class="scribe-orders-title">
                <i class="mdi mdi-cart-outline" style="color: var(--hospital-primary, #0066cc);"></i>
                <span>Clinical Orders & Prescriptions</span>
                <span class="badge bg-primary rounded-pill py-1 px-2 ms-2" id="scribe-orders-count-badge" style="font-size: 0.68rem;">0 RECOMMENDED</span>
            </div>
            <small class="text-muted">Review and adjust dosages or instructions before signing</small>
        </div>

        <div class="table-responsive">
            <table class="scribe-order-table">
                <thead>
                    <tr style="font-size: 0.74rem; color: var(--scribe-text-secondary); text-transform: uppercase;">
                        <th style="width: 35px; text-align: center;"><input type="checkbox" id="chk-all-orders" checked title="Select All"></th>
                        <th style="width: 120px;">Category</th>
                        <th style="min-width: 180px;">Item Details</th>
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


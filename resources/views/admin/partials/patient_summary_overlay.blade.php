{{-- Patient Brief Offcanvas Drawer (Longitudinal Clinical AI Briefing) --}}
@php
    $hosColor = appsettings('hos_color') ?? '#0066cc';
    $llmConfig = is_string(appsettings('llm_config')) ? json_decode(appsettings('llm_config'), true) : (is_array(appsettings('llm_config')) ? appsettings('llm_config') : []);
    $voiceEnabled = $llmConfig['summary_voice_enabled'] ?? true;
    $voiceRate = $llmConfig['summary_voice_rate'] ?? 1.0;
@endphp

{{-- Semi-transparent backdrop for smooth dismissal --}}
<div id="patient-summary-backdrop" class="patient-brief-backdrop d-none"></div>

{{-- Offcanvas Slide-Out Clinical Drawer --}}
<div id="patient-summary-overlay" class="patient-brief-drawer d-none" style="--hos-color: {{ $hosColor }};" role="dialog" aria-modal="true" aria-labelledby="patient-brief-drawer-title">
    {{-- Header --}}
    <div class="patient-brief-header">
        <div class="patient-brief-header-title">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="patient-brief-pill"><i class="mdi mdi-creation"></i> Longitudinal AI Brief</span>
                @if(isset($patient))
                    <span class="patient-brief-tag">{{ $patient->first_name ?? $patient->firstname ?? '' }} {{ $patient->last_name ?? $patient->surname ?? '' }} &bull; #{{ $patient->file_no ?? $patient->id }}</span>
                @endif
            </div>
            <h5 class="patient-brief-heading" id="patient-brief-drawer-title">Longitudinal Patient Summary</h5>
        </div>
        <div class="patient-brief-header-actions">
            <button type="button" class="btn btn-sm btn-outline-primary btn-regenerate-summary" id="btn-regenerate-summary" title="Regenerate brief with fresh records">
                <i class="mdi mdi-refresh"></i>
                <span>Refresh</span>
            </button>
            <button type="button" class="btn btn-sm btn-light btn-close-summary" aria-label="Close" title="Close drawer (Esc)">
                <i class="mdi mdi-close fs-5"></i>
            </button>
        </div>
    </div>
    
    {{-- Drawer Body --}}
    <div class="patient-brief-body">
        {{-- Loading Skeleton State --}}
        <div id="summary-loading" class="patient-brief-loading" style="display: none;">
            <div class="spinner-border text-primary" role="status" style="width: 2.2rem; height: 2.2rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h6 class="mt-3 font-weight-bold text-dark">Synthesizing Longitudinal EHR Records...</h6>
            <p class="small text-muted mb-0">Analyzing past encounters, active diagnoses, lab trends, vitals, and medication history.</p>
        </div>

        {{-- Error State --}}
        <div id="summary-error" class="alert alert-danger mx-3 my-3" style="display: none;">
            <i class="mdi mdi-alert-circle me-2"></i> <span id="summary-error-text">Failed to generate patient brief.</span>
        </div>

        {{-- Content State --}}
        <div id="summary-content" class="patient-brief-content-wrapper" style="display: none;">
            <div class="patient-brief-card">
                <div id="summary-text-container" class="markdown-content"></div>
                
                <div class="patient-brief-card-meta mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span id="summary-meta-info" class="text-muted small"><i class="mdi mdi-robot-outline"></i> Synthesized by Clinical AI</span>
                    <button type="button" class="btn btn-xs btn-outline-secondary btn-copy-summary">
                        <i class="mdi mdi-content-copy me-1"></i> Copy to Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Controls (TTS + Scribe Studio Link) --}}
    <div class="patient-brief-footer" id="summary-capsule-bar" style="display: none;">
        @if($voiceEnabled)
        <div class="patient-brief-voice-strip">
            <div class="voice-status">
                <span class="voice-status-icon" id="summary-voice-status-icon"><i class="mdi mdi-volume-high"></i></span>
                <span class="voice-status-text" id="summary-voice-status-text">Audio Ready</span>
            </div>
            
            <div class="voice-controls">
                <button type="button" class="btn btn-icon btn-sm voice-action-btn" id="btn-summary-play" title="Play Narration">
                    <i class="mdi mdi-play"></i>
                </button>
                <button type="button" class="btn btn-icon btn-sm voice-action-btn" id="btn-summary-stop" title="Stop Narration">
                    <i class="mdi mdi-stop"></i>
                </button>
                <div class="voice-speed-wrapper">
                    <label for="summary-voice-rate" class="voice-speed-label">Speed</label>
                    <select id="summary-voice-rate" class="form-select form-select-sm voice-speed-select">
                        <option value="0.8">0.8x</option>
                        <option value="1.0" selected>1.0x</option>
                        <option value="1.2">1.2x</option>
                        <option value="1.5">1.5x</option>
                    </select>
                </div>
            </div>
        </div>
        @endif

        <div class="patient-brief-bottom-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-close-summary flex-grow-1">
                Dismiss
            </button>
            <button type="button" class="btn btn-sm btn-primary flex-grow-1 btn-open-studio-from-brief" id="btn-open-studio-from-brief">
                <i class="mdi mdi-microphone me-1"></i> Open Scribe Studio (F9)
            </button>
        </div>
    </div>
</div>

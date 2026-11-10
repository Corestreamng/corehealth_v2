{{-- Reusable Clinical AI Toolbar Partial --}}
@php
    $targetId = $targetId ?? 'global_dictation_target';
    $uniqueId = $targetId . '_' . uniqid();
    $editorType = $editorType ?? 'textarea';
    $noteType = $noteType ?? 'auto';
    $llmConfig = is_string(appsettings('llm_config')) ? json_decode(appsettings('llm_config'), true) : (is_array(appsettings('llm_config')) ? appsettings('llm_config') : []);
    $aiEnabled = $llmConfig['enabled'] ?? false;
    $polishEnabled = $llmConfig['polish_note_enabled'] ?? true;
@endphp

@if($aiEnabled)
<div class="speech-dictation-wrapper d-inline-flex align-items-center gap-2 {{ $class ?? '' }}" id="speech-wrapper-{{ $uniqueId }}">
    {{-- Full Clinical AI Scribe Studio Button (Flagship Hero) --}}
    <button type="button" 
            id="btn-ai-scribe-studio-{{ $uniqueId }}" 
            class="btn btn-speech-kit btn-speech-studio d-flex align-items-center gap-2 shadow-sm"
            data-target-editor="#{{ $targetId }}"
            title="Open Clinical AI Scribe Studio (F9) — Ambient Dictation, Multi-Taxonomy Structuring & Orders">
        <span class="speech-studio-icon-wrapper"><i class="mdi mdi-creation"></i></span>
        <span>AI Scribe Studio</span>
        <span class="badge bg-light text-dark rounded-pill px-1.5 py-0.5 ms-1" style="font-size: 0.60rem; font-weight: 700; opacity: 0.85;">F9</span>
    </button>

    {{-- Quick Polish Note Button (1-Click In-place Formatting for Typed Notes) --}}
    @if($polishEnabled)
    <button type="button" 
            id="btn-manual-format-{{ $uniqueId }}" 
            class="btn btn-speech-kit btn-speech-format d-flex align-items-center gap-1.5 border shadow-sm"
            data-target-editor="#{{ $targetId }}"
            title="Quick Polish: Format existing typed note, clean stutters, and standardize medical acronyms in-place">
        <span class="speech-format-icon-wrapper"><i class="fa fa-magic"></i></span>
        <span>Quick Polish</span>
    </button>
    @endif
</div>
@endif

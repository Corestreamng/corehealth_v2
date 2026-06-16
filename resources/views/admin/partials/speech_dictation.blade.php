{{-- Reusable Speech Dictation Kit Partial --}}
@php
    $targetId = $targetId ?? 'global_dictation_target';
    $uniqueId = $targetId . '_' . uniqid();
    $showLangSelect = $showLangSelect ?? true;
    $editorType = $editorType ?? 'textarea';
    $defaultLang = $defaultLang ?? 'en-US';
    $hosColor = appsettings('hos_color') ?? '#0066cc';
@endphp

<div class="speech-dictation-wrapper d-inline-flex flex-column {{ $class ?? '' }}" id="speech-wrapper-{{ $uniqueId }}">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{-- Dictate Button --}}
        <button type="button" 
                id="btn-voice-dictate-{{ $uniqueId }}" 
                class="btn btn-speech-kit btn-speech-idle d-flex align-items-center gap-2">
            <span class="speech-mic-icon-wrapper"><i class="fa fa-microphone"></i></span> Start Dictation <span class="badge bg-danger rounded-pill py-0.5 px-1 ms-1" style="font-size: 0.55rem; line-height: 1;">BETA</span>
        </button>
        
        {{-- Polish Note Button (NLP Offline Retext Tool) --}}
        <button type="button" 
                id="btn-manual-format-{{ $uniqueId }}" 
                class="btn btn-speech-kit btn-speech-format d-flex align-items-center gap-2"
                title="Polish Note (Clean stutters, repeated words, and format medical headings)">
            <span class="speech-format-icon-wrapper"><i class="fa fa-magic"></i></span> Polish Note
        </button>
        
        @if($showLangSelect)
            <select id="select-speech-lang-{{ $uniqueId }}" 
                    class="form-select form-select-sm rounded-pill py-0 px-2 select-speech-lang" 
                    style="width: 105px; height: 32px; font-size: 0.75rem; cursor: pointer;" 
                    title="Select Dictation Language">
                <option value="en-US" {{ $defaultLang === 'en-US' ? 'selected' : '' }}>English (US)</option>
                <option value="en-GB" {{ $defaultLang === 'en-GB' ? 'selected' : '' }}>English (UK)</option>
                <option value="en-NG" {{ $defaultLang === 'en-NG' ? 'selected' : '' }}>English (NG)</option>
                <option value="es-ES" {{ $defaultLang === 'es-ES' ? 'selected' : '' }}>Español</option>
                <option value="fr-FR" {{ $defaultLang === 'fr-FR' ? 'selected' : '' }}>Français</option>
            </select>
        @endif
    </div>

    {{-- Micro-Alert Section: AI Development Warning --}}
    <div class="speech-ai-alert mt-2 p-2 border rounded-3 text-muted" style="background: rgba(255, 193, 7, 0.05); border-color: rgba(255, 193, 7, 0.15) !important; font-size: 0.72rem; max-width: 500px;">
        <i class="fa fa-info-circle text-warning me-1"></i>
        <strong>AI Assistant Notice:</strong> Offline Speech Dictation is active. Note formatting, templates, and clinical recommendation features are currently in active beta development and not fully complete.
    </div>

    {{-- Fullscreen Overlay --}}
    <div id="speech-overlay-{{ $uniqueId }}" class="speech-overlay-fullscreen d-none" style="--hos-color: {{ $hosColor }};">
        {{-- Muted Header Info --}}
        <div class="speech-overlay-header">
            <div class="speech-header-left">ENCOUNTER • DICTATION</div>
            <div class="speech-header-center">CLINICAL AUDIO CAPTURE</div>
        </div>
        
        <div class="speech-overlay-content">
            {{-- Real-time Audio Waveform --}}
            <div class="speech-audio-wave">
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
                <div class="wave-bar"></div>
            </div>
            
            <div id="speech-history-text-{{ $uniqueId }}" class="speech-overlay-history d-none"></div>
            
            {{-- Floating Capsule Control Bar --}}
            <div class="speech-capsule-bar">
                <div class="capsule-left">
                    <span class="speech-sparkle-icon"><i class="fa fa-magic"></i></span>
                    <div id="speech-preview-text-{{ $uniqueId }}" class="speech-overlay-text">
                        Listening...
                    </div>
                </div>
                <div class="capsule-right">
                    <span class="status-dot"></span>
                    <button type="button" id="btn-stop-overlay-{{ $uniqueId }}" class="btn-capsule-stop" title="Stop Dictation">
                        <i class="fa fa-stop"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Bubbly Speech Kit Styling */
.btn-speech-kit {
    border-radius: 50px !important;
    padding: 4px 16px 4px 6px !important;
    font-weight: 700 !important;
    font-size: 0.78rem !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    height: 32px;
    border: 2px solid transparent !important;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
}

/* Standout Microphone Icon Wrapper */
.speech-mic-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background: #ffffff;
    border-radius: 50%;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* State: Idle */
.btn-speech-idle {
    background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%) !important;
    color: #0d6efd !important;
    border-color: rgba(13, 110, 253, 0.15) !important;
}
.btn-speech-idle .speech-mic-icon-wrapper {
    color: #0d6efd;
}
.btn-speech-idle:hover {
    transform: scale(1.08) translateY(-1px);
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
    color: #ffffff !important;
    border-color: #0d6efd !important;
    box-shadow: 0 6px 18px rgba(13, 110, 253, 0.25) !important;
}
.btn-speech-idle:hover .speech-mic-icon-wrapper {
    color: #0d6efd;
    transform: scale(1.15) rotate(15deg);
}

/* State: Connecting */
.btn-speech-connecting {
    background: linear-gradient(135deg, #fce38a 0%, #f38181 100%) !important;
    color: #ffffff !important;
    border-color: transparent !important;
    box-shadow: 0 6px 15px rgba(243, 129, 129, 0.25) !important;
    cursor: wait;
}
.btn-speech-connecting .speech-mic-icon-wrapper {
    color: #f38181;
}

/* State: Listening (Flashing & Pulsing Capsule) */
.btn-speech-listening {
    background: linear-gradient(135deg, #f857a6 0%, #ff5858 100%) !important;
    color: #ffffff !important;
    border-color: rgba(255, 88, 88, 0.25) !important;
    box-shadow: 0 8px 24px rgba(255, 88, 88, 0.35) !important;
    animation: dictatingPulse 1.8s infinite alternate cubic-bezier(0.455, 0.03, 0.515, 0.955);
}
.btn-speech-listening .speech-mic-icon-wrapper {
    color: #ff5858;
    animation: micBreathing 1.2s infinite ease-in-out;
    box-shadow: 0 2px 8px rgba(255, 88, 88, 0.3);
}
.btn-speech-listening:hover {
    transform: scale(1.05);
}

/* State: Polish/Format Button */
.btn-speech-format {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
    color: #333333 !important;
    border-color: rgba(0, 0, 0, 0.08) !important;
}
.btn-speech-format .speech-format-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    background: #ffffff;
    border-radius: 50%;
    color: #6f42c1; /* Purple magic wand */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.btn-speech-format:hover {
    transform: scale(1.08) translateY(-1px);
    background: linear-gradient(135deg, #6f42c1 0%, #59359a 100%) !important;
    color: #ffffff !important;
    border-color: #6f42c1 !important;
    box-shadow: 0 6px 18px rgba(111, 66, 193, 0.25) !important;
}
.btn-speech-format:hover .speech-format-icon-wrapper {
    color: #6f42c1;
    transform: scale(1.15) rotate(20deg);
}

@keyframes dictatingPulse {
    0% {
        box-shadow: 0 6px 18px rgba(255, 88, 88, 0.25);
    }
    100% {
        box-shadow: 0 12px 30px rgba(255, 88, 88, 0.55);
    }
}
@keyframes micBreathing {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.22); }
}

/* Bubbly Select Dropdown */
.select-speech-lang {
    border-radius: 50px !important;
    border: 2px solid rgba(13, 110, 253, 0.15) !important;
    background-color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 700 !important;
    color: #495057 !important;
    transition: all 0.35s ease !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03) !important;
}
.select-speech-lang:hover, .select-speech-lang:focus {
    border-color: #0d6efd !important;
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(13, 110, 253, 0.12) !important;
}

/* High Fidelity 5-bar Audio Visualizer */
.speech-wave-indicator {
    height: 16px;
    padding-left: 4px;
}
.speech-wave-indicator span {
    display: block;
    width: 3px;
    height: 5px;
    background: #ff5858;
    border-radius: 4px;
    animation: waveRise 1.1s infinite ease-in-out alternate;
}
.speech-wave-indicator span:nth-child(1) { height: 5px; animation-delay: 0.1s; }
.speech-wave-indicator span:nth-child(2) { height: 11px; animation-delay: 0.4s; }
.speech-wave-indicator span:nth-child(3) { height: 7px; animation-delay: 0.2s; }
.speech-wave-indicator span:nth-child(4) { height: 13px; animation-delay: 0.5s; }
.speech-wave-indicator span:nth-child(5) { height: 6px; animation-delay: 0.3s; }

@keyframes waveRise {
    0% { transform: scaleY(0.6); }
    100% { transform: scaleY(2.2); }
}

/* Fullscreen Overlay Styling (Sleek Theme & Ambient Glow) */
.speech-overlay-fullscreen {
    /* Light Mode (Default) Theme Variables */
    --overlay-bg: #f8fafc;
    --overlay-bg-gradient: radial-gradient(circle at 50% 110%, color-mix(in srgb, var(--hos-color) 20%, transparent) 0%, transparent 60%), #f8fafc;
    --header-text-color: rgba(0, 0, 0, 0.45);
    --wave-bar-bg: #2d3748;
    --wave-bar-opacity: 0.2;
    --capsule-bg: rgba(255, 255, 255, 0.85);
    --capsule-border: 1px solid rgba(0, 0, 0, 0.08);
    --capsule-shadow: 0 20px 40px rgba(0, 0, 0, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    --text-color: #1a202c;
    --history-bg: rgba(255, 255, 255, 0.7);
    --history-border: 1px solid rgba(0, 0, 0, 0.06);
    --history-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
    --history-text-color: #2d3748;
    --btn-stop-bg: rgba(0, 0, 0, 0.04);
    --btn-stop-border: 1px solid rgba(0, 0, 0, 0.08);
    --btn-stop-color: #1a202c;

    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: var(--overlay-bg);
    background: var(--overlay-bg-gradient);
    z-index: 999999;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    padding: 3rem 0;
    opacity: 0;
    transition: opacity 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
    overflow: hidden;
}

/* Dark Mode Theme Variables */
body.dark-mode .speech-overlay-fullscreen {
    --overlay-bg: #0a0c10;
    --overlay-bg-gradient: radial-gradient(circle at 50% 110%, color-mix(in srgb, var(--hos-color) 25%, transparent) 0%, transparent 60%), #0a0c10;
    --header-text-color: rgba(255, 255, 255, 0.4);
    --wave-bar-bg: #ffffff;
    --wave-bar-opacity: 0.65;
    --capsule-bg: rgba(20, 22, 28, 0.85);
    --capsule-border: 1px solid rgba(255, 255, 255, 0.08);
    --capsule-shadow: 0 20px 40px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    --text-color: #ffffff;
    --history-bg: rgba(255, 255, 255, 0.02);
    --history-border: 1px solid rgba(255, 255, 255, 0.05);
    --history-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    --history-text-color: rgba(255, 255, 255, 0.7);
    --btn-stop-bg: rgba(255, 255, 255, 0.05);
    --btn-stop-border: 1px solid rgba(255, 255, 255, 0.1);
    --btn-stop-color: #ffffff;
}

.speech-overlay-fullscreen:not(.d-none) {
    opacity: 1;
}

.speech-overlay-header {
    width: 90%;
    max-width: 800px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--header-text-color);
    font-size: 0.8rem;
    letter-spacing: 2px;
    font-family: monospace;
}

.speech-overlay-content {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3.5rem;
    max-width: 800px;
    width: 90%;
    flex-grow: 1;
    margin-bottom: 2rem;
}

/* Audio Waveform */
.speech-audio-wave {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 150px;
}
.wave-bar {
    width: 6px;
    height: 15px;
    background: var(--wave-bar-bg);
    border-radius: 5px;
    transition: height 0.05s ease;
    opacity: var(--wave-bar-opacity);
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
}
.wave-bar:nth-child(even) {
    background: var(--hos-color, #0d6efd);
    opacity: 0.9;
    box-shadow: 0 0 15px color-mix(in srgb, var(--hos-color, #0d6efd) 50%, transparent);
}

/* Floating Capsule Control Bar */
.speech-capsule-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    max-width: 650px;
    background: var(--capsule-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: var(--capsule-border);
    border-radius: 40px;
    padding: 12px 24px;
    box-shadow: var(--capsule-shadow);
}

.capsule-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-grow: 1;
    overflow: hidden;
}

.speech-sparkle-icon {
    font-size: 1.3rem;
    color: var(--hos-color, #0d6efd);
    filter: drop-shadow(0 0 8px var(--hos-color, #0d6efd));
    animation: sparkleGlow 2s infinite alternate;
}

@keyframes sparkleGlow {
    0% { transform: scale(1); filter: drop-shadow(0 0 5px var(--hos-color, #0d6efd)); }
    100% { transform: scale(1.15); filter: drop-shadow(0 0 15px var(--hos-color, #0d6efd)); }
}

.speech-overlay-text {
    font-size: 1.25rem;
    font-weight: 300;
    color: var(--text-color);
    white-space: nowrap;
    overflow-x: auto;
    text-align: left;
    margin: 0;
    scrollbar-width: none;
}
.speech-overlay-text::-webkit-scrollbar {
    display: none;
}

.capsule-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-dot {
    width: 10px;
    height: 10px;
    background: var(--hos-color, #0d6efd);
    border-radius: 50%;
    box-shadow: 0 0 10px var(--hos-color, #0d6efd);
    animation: statusPulse 1.5s infinite alternate;
}

@keyframes statusPulse {
    0% { opacity: 0.4; }
    100% { opacity: 1; }
}

.btn-capsule-stop {
    background: var(--btn-stop-bg);
    color: var(--btn-stop-color);
    border: var(--btn-stop-border);
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-capsule-stop:hover {
    background: #ff5858;
    border-color: #ff5858;
    color: white;
    box-shadow: 0 0 15px rgba(255, 88, 88, 0.4);
    transform: translateY(-1px);
}

.speech-overlay-history {
    font-size: 1.15rem;
    color: var(--history-text-color);
    text-align: left;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    padding: 20px 25px;
    background: var(--history-bg);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: var(--history-border);
    box-shadow: var(--history-shadow);
    border-radius: 20px;
    margin-bottom: -1rem;
    line-height: 1.6;
    border-left: 5px solid var(--hos-color, #0d6efd);
}
.speech-overlay-history span {
    display: inline;
}
</style>

<script>
window.SPEECH_ASSET_BASE = "{{ asset('assets/js') }}";
document.addEventListener('DOMContentLoaded', function() {
    function initSpeechKit() {
        if (typeof SpeechDictationKit === 'undefined') {
            console.warn('SpeechDictationKit JS file not loaded yet. Retrying...');
            return;
        }
        
        new SpeechDictationKit({
            buttonSelector: '#btn-voice-dictate-{{ $uniqueId }}',
            formatButtonSelector: '#btn-manual-format-{{ $uniqueId }}',
            targetSelector: '#{{ $targetId }}',
            previewBubbleSelector: '#speech-overlay-{{ $uniqueId }}',
            overlayStopBtnSelector: '#btn-stop-overlay-{{ $uniqueId }}',
            previewTextSelector: '#speech-preview-text-{{ $uniqueId }}',
            historyTextSelector: '#speech-history-text-{{ $uniqueId }}',
            langSelectSelector: '#select-speech-lang-{{ $uniqueId }}',
            editorType: '{{ $editorType }}',
            defaultLang: '{{ $defaultLang }}',
            onResultCallback: function(text) {
                const targetEl = document.getElementById('{{ $targetId }}');
                if (targetEl) {
                    targetEl.dispatchEvent(new Event('input', { bubbles: true }));
                    if (typeof window.autosavenotes === 'function') {
                        window.autosavenotes();
                    }
                }
            }
        });
    }
    
    if (typeof SpeechDictationKit === 'undefined') {
        setTimeout(initSpeechKit, 500);
    } else {
        initSpeechKit();
    }
});
</script>

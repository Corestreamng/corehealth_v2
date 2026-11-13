<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoreHealth AI Scribe — Embeddable Studio</title>
    <link rel="stylesheet" href="{{ asset('css/corehealth-sdk.css') }}">
    <script>
        window.CH_EMBED_CONFIG = {
            token: @json($token),
            patientId: @json($patientId),
            encounterId: @json($encounterId),
            locale: @json($locale),
            theme: @json($theme)
        };
    </script>
</head>
<body class="ch-scribe-embed-root">
    <header class="ch-scribe-header">
        <div class="ch-scribe-brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
            </svg>
            <span>CoreHealth AI Scribe</span>
            @if($patientId)
                <span style="font-size: 12px; font-weight: normal; color: var(--ch-text-secondary); margin-left: 8px;">
                    Patient #{{ $patientId }} @if($encounterId)· Encounter #{{ $encounterId }}@endif
                </span>
            @endif
        </div>
        <div>
            <span id="ch-status-badge" class="badge" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #6c757d; color: #fff;">
                Ready
            </span>
        </div>
    </header>

    <main class="ch-scribe-body">
        <div class="ch-cockpit-pane">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label style="font-size: 12px; font-weight: 700; text-transform: uppercase;">1. Audio Dictation & Live Transcript</label>
                <button type="button" id="ch-btn-record" class="ch-btn ch-btn-record">Start Dictation</button>
            </div>
            <textarea id="ch-transcript-area" style="flex: 1; width: 100%; border: 1px solid var(--ch-border); border-radius: 4px; padding: 10px; font-size: 13px; resize: none; box-sizing: border-box;" placeholder="Dictate your clinical findings or type clinical notes here..."></textarea>
        </div>

        <div class="ch-cockpit-pane">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label style="font-size: 12px; font-weight: 700; text-transform: uppercase;">2. Structured SOAP Note & Orders</label>
            </div>
            <textarea id="ch-note-area" style="flex: 1; width: 100%; border: 1px solid var(--ch-border); border-radius: 4px; padding: 10px; font-size: 13px; resize: none; box-sizing: border-box;" placeholder="Structured SOAP sections and orders will appear here after dictation..."></textarea>
        </div>
    </main>

    <footer class="ch-scribe-footer">
        <div style="font-size: 11px; color: var(--ch-text-secondary);">
            openEHR & FHIR R4 Compliant · NDPA 2023 Statutory Privacy Protected
        </div>
        <div>
            <button type="button" id="ch-btn-sign" class="ch-btn ch-btn-primary" disabled>
                Accept & Sign
            </button>
        </div>
    </footer>

    <script src="{{ asset('js/corehealth-sdk-iframe-embed.js') }}"></script>
</body>
</html>


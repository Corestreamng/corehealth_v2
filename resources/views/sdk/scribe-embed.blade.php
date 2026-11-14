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
            role: @json($role ?? 'doctor'),
            taxonomy: @json($taxonomy ?? 'soap'),
            customCatalog: @json($customCatalog ?? request()->query('custom_catalog')),
            locale: @json($locale),
            theme: @json($theme)
        };
    </script>
</head>
<body class="ch-scribe-embed-root">
    <header class="ch-scribe-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
        <div class="ch-scribe-brand" style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #011b33;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
            </svg>
            <span>CoreHealth AI Scribe</span>
            @if($patientId)
                <span style="font-size: 11px; font-weight: 600; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">
                    Patient #{{ $patientId }} @if($encounterId)· Enc #{{ $encounterId }}@endif
                </span>
            @endif
        </div>

        <div id="ch-tax-switcher" style="display: flex; align-items: center; gap: 4px; background: #edeef2; padding: 2px 4px; border-radius: 6px;">
            <button type="button" class="ch-tax-pill active" data-tax="soap" style="border: none; background: #011b33; color: #fff; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; cursor: pointer;">SOAP</button>
            <button type="button" class="ch-tax-pill" data-tax="clerking" style="border: none; background: none; color: #475569; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; cursor: pointer;">Clerking</button>
            <button type="button" class="ch-tax-pill" data-tax="pomr" style="border: none; background: none; color: #475569; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; cursor: pointer;">POMR</button>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <span id="ch-status-badge" class="badge" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #64748b; color: #fff;">
                Ready
            </span>
        </div>
    </header>

    <main class="ch-scribe-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 14px; min-height: 320px;">
        <div class="ch-cockpit-pane" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">1. Audio Dictation & Live Transcript</label>
                <button type="button" id="ch-btn-record" class="ch-btn ch-btn-record" style="font-size: 12px; padding: 6px 12px; background: #011b33; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Start Dictation</button>
            </div>
            <textarea id="ch-transcript-area" style="flex: 1; width: 100%; min-height: 220px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Dictate your clinical findings, history, physical exam, or prescriptions..."></textarea>
        </div>

        <div class="ch-cockpit-pane" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label id="ch-note-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">2. Structured SOAP Note & Orders</label>
                <span style="font-size: 10px; color: #94a3b8;">Lossless Conversion Ready</span>
            </div>
            <textarea id="ch-note-area" style="flex: 1; width: 100%; min-height: 220px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Structured clinical sections and orders will appear here after dictation..."></textarea>
        </div>
    </main>

    <!-- Suggested Orders Deck -->
    <section id="ch-orders-deck" style="display: none; border-top: 1px solid #e2e8f0; background: #f8fafc; padding: 12px 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div>
                <strong style="font-size: 12px; color: #011b33;">CLINICAL ORDERS & PRESCRIPTIONS</strong>
                <span id="ch-orders-count-badge" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 10px; font-weight: 700; margin-left: 6px;">0 RECOMMENDED</span>
            </div>
            <div style="display: flex; gap: 4px;">
                <button type="button" class="ch-filter-btn active" data-filter="all" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #011b33; color: #fff; cursor: pointer;">All</button>
                <button type="button" class="ch-filter-btn" data-filter="rx" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Rx</button>
                <button type="button" class="ch-filter-btn" data-filter="lab" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Lab</button>
                <button type="button" class="ch-filter-btn" data-filter="rad" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Rad</button>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 11px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden;">
            <thead>
                <tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0; text-align: left;">
                    <th style="padding: 6px 10px; width: 25px;">[x]</th>
                    <th style="padding: 6px 10px; width: 90px;">Category</th>
                    <th style="padding: 6px 10px;">Item Name</th>
                    <th style="padding: 6px 10px;">Dosage / Indication</th>
                    <th style="padding: 6px 10px; width: 70px;">Tariff</th>
                </tr>
            </thead>
            <tbody id="ch-orders-tbody">
                <!-- Dynamically populated -->
            </tbody>
        </table>
        <div style="margin-top: 6px; font-size: 11px; color: #64748b; display: flex; justify-content: space-between;">
            <span id="ch-tariff-counter">Estimated: ₦0.00</span>
            <span>Grounded against hospital and partner catalogs</span>
        </div>
    </section>

    <footer class="ch-scribe-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
        <div style="font-size: 11px; color: #64748b;">
            openEHR & FHIR R4 Compliant · NDPA 2023 Statutory Privacy Protected
        </div>
        <div>
            <button type="button" id="ch-btn-sign" class="ch-btn ch-btn-primary" style="font-size: 12px; padding: 8px 16px; background: #16a34a; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;" disabled>
                Accept & Sign Note & Orders
            </button>
        </div>
    </footer>

    <script src="{{ asset('js/corehealth-sdk-iframe-embed.js') }}"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoreHealth AI Scribe — Embeddable Studio</title>
    <link rel="stylesheet" href="{{ asset('css/corehealth-sdk.css') }}">
    <style>
        .ch-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }
        .ch-modal-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2), 0 10px 10px -5px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: modalFadeIn 0.18s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .ch-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            font-size: 13px; color: #011b33;
        }
        .ch-modal-body {
            padding: 16px; display: flex; flex-direction: column; gap: 12px;
        }
        .ch-modal-footer {
            display: flex; justify-content: flex-end; gap: 8px;
            padding: 12px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0;
        }
        .ch-form-group { display: flex; flex-direction: column; gap: 4px; }
        .ch-form-group label { font-size: 11px; font-weight: 600; color: #475569; text-transform: uppercase; }
        .ch-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .ch-inline-input {
            width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12px; box-sizing: border-box;
        }
        .ch-inline-input:focus { outline: none; border-color: #0284c7; }
        .ch-btn-icon-del {
            background: none; border: none; color: #94a3b8; padding: 4px 6px;
            border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.15s ease;
        }
        .ch-btn-icon-del:hover { color: #dc2626; background: #fee2e2; }
        .ch-btn-icon-close {
            background: none; border: none; color: #64748b; cursor: pointer; padding: 4px;
            border-radius: 4px; display: inline-flex; align-items: center;
        }
        .ch-btn-icon-close:hover { background: #e2e8f0; color: #0f172a; }
        .ch-rec-pulse {
            width: 8px; height: 8px; border-radius: 50%; background: #ffffff;
            display: inline-block; animation: recPulse 1s infinite alternate;
        }
        @keyframes recPulse {
            0% { opacity: 0.3; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1.2); }
        }
    </style>
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
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
            <span id="ch-status-badge" class="badge" style="display: inline-flex; align-items: center; gap: 5px; font-size: 11px; padding: 4px 10px; border-radius: 4px; background: #64748b; color: #fff; font-weight: 600;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span>Ready</span>
            </span>
        </div>
    </header>

    <!-- Progressive Flow Stepper Navigation -->
    <div class="ch-flow-nav" style="display: flex; align-items: center; justify-content: center; gap: 12px; padding: 8px 18px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; font-size: 12px;">
        <div class="ch-step-node active" id="ch-st-node-1" style="display: flex; align-items: center; gap: 6px; color: #011b33; font-weight: 700;">
            <span class="ch-step-bubble" id="ch-bubble-1" style="width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; background: #011b33; color: #ffffff;">1</span>
            <span>Start Dictation or Load Sample</span>
        </div>
        <span class="ch-step-arrow" style="color: #94a3b8; font-size: 12px;">→</span>
        <div class="ch-step-node" id="ch-st-node-2" style="display: flex; align-items: center; gap: 6px; color: #64748b; font-weight: 500;">
            <span class="ch-step-bubble" id="ch-bubble-2" style="width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; background: #cbd5e1; color: #475569;">2</span>
            <span>Process Note & Extract Orders</span>
        </div>
        <span class="ch-step-arrow" style="color: #94a3b8; font-size: 12px;">→</span>
        <div class="ch-step-node" id="ch-st-node-3" style="display: flex; align-items: center; gap: 6px; color: #64748b; font-weight: 500;">
            <span class="ch-step-bubble" id="ch-bubble-3" style="width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; background: #cbd5e1; color: #475569;">3</span>
            <span>Review, Adjust & Sign</span>
        </div>
    </div>

    <main class="ch-scribe-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 14px; min-height: 320px;">
        <div class="ch-cockpit-pane" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">1. Audio Dictation & Live Transcript</label>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <button type="button" id="ch-btn-record" class="ch-btn ch-btn-record" style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; padding: 6px 12px; background: #011b33; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
                        <span id="ch-btn-record-text">Start Dictation</span>
                    </button>
                    <button type="button" id="ch-btn-sample" class="ch-btn" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 4px 8px; background: #fff; border: 1px solid #cbd5e1; color: #475569; border-radius: 4px; cursor: pointer;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Load Sample</span>
                    </button>
                    <button type="button" id="ch-btn-process" class="ch-btn" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 4px 10px; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>Process Note</span>
                    </button>
                </div>
            </div>
            <textarea id="ch-transcript-area" style="flex: 1; width: 100%; min-height: 220px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Dictate your clinical findings, history, physical exam, or prescriptions..."></textarea>
        </div>

        <div class="ch-cockpit-pane" style="display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label id="ch-note-label" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">2. Structured SOAP Note & Orders</label>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <button type="button" id="ch-btn-copy" style="display: none; align-items: center; gap: 3px; font-size: 10px; padding: 3px 6px; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; color: #475569; cursor: pointer;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                        <span id="ch-btn-copy-text">Copy</span>
                    </button>
                    <button type="button" id="ch-btn-clear" style="display: inline-flex; align-items: center; gap: 3px; font-size: 10px; padding: 3px 6px; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; color: #dc2626; cursor: pointer;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        <span>Clear</span>
                    </button>
                </div>
            </div>
            <!-- Empty Guide before processing -->
            <div id="ch-note-empty-guide" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 30px 20px; border: 2px dashed #e2e8f0; border-radius: 6px; background: #f8fafc; color: #64748b; min-height: 220px; box-sizing: border-box;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.8" style="margin-bottom: 8px;">
                    <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" y1="19" x2="12" y2="22"/>
                </svg>
                <strong style="color: #334155; font-size: 13px;">Step 1: Dictate or Load Sample</strong>
                <p style="font-size: 12px; color: #64748b; margin: 4px 0 0; max-width: 280px; line-height: 1.4;">
                    Speak into your microphone or click <strong>Load Sample</strong>, then click <strong>Process Note</strong> to structure clinical findings and ground orders.
                </p>
            </div>
            <textarea id="ch-note-area" style="display: none; flex: 1; width: 100%; min-height: 220px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Structured clinical sections and orders will appear here after dictation..."></textarea>
        </div>
    </main>

    <!-- Suggested Orders Deck -->
    <section id="ch-orders-deck" style="display: none; border-top: 1px solid #e2e8f0; background: #f8fafc; padding: 12px 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div>
                <strong style="font-size: 12px; color: #011b33;">CLINICAL ORDERS & PRESCRIPTIONS</strong>
                <span id="ch-orders-count-badge" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 10px; font-weight: 700; margin-left: 6px;">0 RECOMMENDED</span>
            </div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <button type="button" class="ch-filter-btn active" data-filter="all" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #011b33; color: #fff; cursor: pointer;">All</button>
                <button type="button" class="ch-filter-btn" data-filter="rx" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Rx</button>
                <button type="button" class="ch-filter-btn" data-filter="lab" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Lab</button>
                <button type="button" class="ch-filter-btn" data-filter="rad" style="font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #475569; cursor: pointer;">Rad</button>
                <button type="button" id="ch-btn-add-order" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff; color: #0369a1; cursor: pointer; font-weight: 600;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <span>Add Order</span>
                </button>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; font-size: 11px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden;">
            <thead>
                <tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0; text-align: left;">
                    <th style="padding: 6px 10px; width: 25px;"><input type="checkbox" id="ch-select-all" checked title="Select/Deselect All"></th>
                    <th style="padding: 6px 10px; width: 90px;">Category</th>
                    <th style="padding: 6px 10px;">Item Name</th>
                    <th style="padding: 6px 10px;">Dosage / Indication</th>
                    <th style="padding: 6px 10px; width: 70px;">Tariff</th>
                    <th style="padding: 6px 10px; width: 35px; text-align: center;"></th>
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
            <button type="button" id="ch-btn-sign" class="ch-btn ch-btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 8px 16px; background: #16a34a; color: #fff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                <span>Accept & Sign Note & Orders</span>
            </button>
        </div>
    </footer>

    <!-- Vanilla Modal: Add Clinical Order -->
    <div id="ch-modal-add-order" class="ch-modal-backdrop">
        <div class="ch-modal-card">
            <div class="ch-modal-header">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <strong>Stage Clinical Order</strong>
                </div>
                <button type="button" id="ch-btn-close-modal" class="ch-btn-icon-close" title="Close">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="ch-modal-body">
                <div class="ch-form-group">
                    <label>Order Category</label>
                    <select id="ch-mo-cat" class="ch-inline-input">
                        <option value="medication">Prescription / Medication (Rx)</option>
                        <option value="lab">Laboratory Investigation</option>
                        <option value="imaging">Radiology / Imaging</option>
                        <option value="procedure">Clinical Procedure</option>
                        <option value="nursing">Nursing Intervention</option>
                    </select>
                </div>
                <div class="ch-form-group">
                    <label>Item Name *</label>
                    <input type="text" id="ch-mo-name" class="ch-inline-input" placeholder="e.g. Paracetamol 500mg, Full Blood Count, Chest X-Ray">
                </div>
                <div class="ch-form-row">
                    <div class="ch-form-group">
                        <label>Dosage / Indication</label>
                        <input type="text" id="ch-mo-spec" class="ch-inline-input" placeholder="e.g. 500mg TDS, Confirm infection">
                    </div>
                    <div class="ch-form-group">
                        <label>Instructions / Route</label>
                        <input type="text" id="ch-mo-inst" class="ch-inline-input" placeholder="e.g. Oral after meals">
                    </div>
                </div>
                <div class="ch-form-group">
                    <label>Tariff / Estimated Cost (₦)</label>
                    <input type="number" id="ch-mo-tariff" class="ch-inline-input" value="1000" min="0">
                </div>
            </div>
            <div class="ch-modal-footer">
                <button type="button" id="ch-btn-cancel-modal" style="font-size: 11px; padding: 6px 12px; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; color: #475569; cursor: pointer;">Cancel</button>
                <button type="button" id="ch-btn-confirm-add" style="font-size: 11px; padding: 6px 14px; background: #011b33; border: none; border-radius: 4px; color: #fff; font-weight: 600; cursor: pointer;">Add to Staged Deck</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/corehealth-sdk-iframe-embed.js') }}"></script>
</body>
</html>

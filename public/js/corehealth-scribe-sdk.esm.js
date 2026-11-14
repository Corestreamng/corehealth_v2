/**
 * CoreHealth AI Scribe SDK v1.0.0 (ESM)
 * (c) 2026 Corehealth by CorestreamNG <hello@corestream.ng>
 */
/**
 * CoreHealth AI Scribe — iFrame Bridge
 * Manages sandboxed iframe creation and postMessage communication.
 */

class IFrameBridge {
  constructor(options) {
    this.options = options;
    this.iframe = null;
    this.messageHandler = this.handleMessage.bind(this);
    this.mounted = false;
  }

  async mount() {
    if (this.mounted) return;

    const target = typeof this.options.mountTarget === 'string'
      ? document.querySelector(this.options.mountTarget)
      : this.options.mountTarget;

    if (!target) {
      throw new Error(`[CoreHealthScribe] Mount target "${this.options.mountTarget}" not found in DOM.`);
    }

    const baseUrl = (this.options.baseUrl || '').replace(/\/+$/, '');
    const params = new URLSearchParams({
      token: this.options.sdkToken || '',
      locale: this.options.locale || 'en-NG',
      theme: this.options.theme || 'light',
    });

    if (this.options.patientId) params.set('patient_id', String(this.options.patientId));
    if (this.options.encounterId) params.set('encounter_id', String(this.options.encounterId));
    if (this.options.patientSummary) params.set('patient_summary', String(this.options.patientSummary));
    if (this.options.role) params.set('role', String(this.options.role));
    if (this.options.taxonomy) params.set('taxonomy', String(this.options.taxonomy));
    if (this.options.customCatalog) {
      params.set('custom_catalog', typeof this.options.customCatalog === 'string'
        ? this.options.customCatalog
        : JSON.stringify(this.options.customCatalog));
    }

    const iframe = document.createElement('iframe');
    iframe.src = `${baseUrl}/scribe?${params.toString()}`;
    iframe.setAttribute('allow', 'microphone');
    iframe.setAttribute('title', 'CoreHealth AI Scribe Studio');
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-modals');
    iframe.style.width = '100%';
    iframe.style.height = '780px';
    iframe.style.border = 'none';
    iframe.style.borderRadius = '8px';
    iframe.style.boxShadow = '0 2px 12px rgba(0,0,0,0.08)';

    this.iframe = iframe;
    target.innerHTML = '';
    target.appendChild(iframe);

    window.addEventListener('message', this.messageHandler);
    this.mounted = true;
  }

  handleMessage(event) {
    // Validate origin if possible
    if (this.options.baseUrl) {
      try {
        const expectedOrigin = new URL(this.options.baseUrl).origin;
        if (event.origin !== expectedOrigin && event.origin !== window.location.origin) {
          return;
        }
      } catch (e) {
        // Fall through
      }
    }

    const data = event.data;
    if (!data || typeof data !== 'object' || !data.type) return;

    switch (data.type) {
      case 'corehealth:ready':
        if (typeof this.options.onReady === 'function') {
          this.options.onReady(data.payload || {});
        }
        if (typeof this.options.onStateChange === 'function') {
          this.options.onStateChange('idle');
        }
        break;

      case 'corehealth:recording-started':
        if (typeof this.options.onStateChange === 'function') {
          this.options.onStateChange('recording');
        }
        break;

      case 'corehealth:processing':
        if (typeof this.options.onStateChange === 'function') {
          this.options.onStateChange('processing');
        }
        break;

      case 'corehealth:note-ready':
        if (typeof this.options.onStateChange === 'function') {
          this.options.onStateChange('reviewing');
        }
        break;

      case 'corehealth:orders-updated':
        if (typeof this.options.onOrdersUpdated === 'function') {
          this.options.onOrdersUpdated(data.payload || []);
        }
        break;

      case 'corehealth:signed-off':
        if (typeof this.options.onStateChange === 'function') {
          this.options.onStateChange('signed-off');
        }
        if (typeof this.options.onSignedOff === 'function') {
          this.options.onSignedOff(data.payload || {});
        }
        break;

      case 'corehealth:error':
        if (typeof this.options.onError === 'function') {
          this.options.onError(data.payload || { code: 'UNKNOWN_ERROR', message: 'An unknown error occurred.' });
        }
        break;
    }
  }

  unmount() {
    window.removeEventListener('message', this.messageHandler);
    if (this.iframe && this.iframe.parentNode) {
      this.iframe.parentNode.removeChild(this.iframe);
    }
    this.iframe = null;
    this.mounted = false;
  }
}


/**
 * CoreHealth AI Scribe — Shadow DOM Web Component (<corehealth-scribe-studio>)
 * Complete standalone custom element with shadow DOM style isolation,
 * progressive clinical workflow (Dictate/Load Sample -> Process -> Review & Sign),
 * crisp inline SVG icons, dynamic clinical taxonomies, super-compressed catalog grounding,
 * interactive suggested orders deck with clean vanilla modal, draft persistence,
 * and 60 FPS live waveform visualizer with dictation timer.
 */

const ICONS = {
  mic: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>`,
  stop: `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>`,
  sparkle: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>`,
  sample: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
  copy: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`,
  trash: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>`,
  plus: `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>`,
  check: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
  sign: `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>`,
  close: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
};

class CoreHealthScribeElement extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });

    // Internal State
    this._clinicalRole = 'doctor';
    this.taxonomy = 'soap';
    this.isRecording = false;
    this.recognition = null;
    this.audioContext = null;
    this.audioAnalyser = null;
    this.audioStream = null;
    this.animFrameId = null;
    this.secondsRecorded = 0;
    this.timerInterval = null;
    this.transcript = '';
    this.structuredSections = [];
    this.groundedOrders = [];
    this.activeFilter = 'all';
    this.customCatalog = null;
    this.debounceTimer = null;
    this.currentStep = 1; // 1: Dictate, 2: Processed / Reviewing, 3: Signed
  }

  get role() {
    return this._clinicalRole || this.getAttribute('role') || 'doctor';
  }

  set role(val) {
    this._clinicalRole = val || 'doctor';
  }

  static get observedAttributes() {
    return [
      'base-url',
      'sdk-token',
      'patient-id',
      'encounter-id',
      'role',
      'taxonomy',
      'locale',
      'theme',
      'patient-summary',
      'custom-catalog',
    ];
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue === newValue) return;
    if (name === 'role') this.role = newValue || 'doctor';
    if (name === 'taxonomy') this.taxonomy = newValue || 'soap';
    if (name === 'custom-catalog') this.customCatalog = newValue;
    if (this.isConnected) {
      this.updateTaxonomyPills();
    }
  }

  connectedCallback() {
    this.role = this.getAttribute('role') || 'doctor';
    this.taxonomy = this.getAttribute('taxonomy') || (this.role === 'nurse' ? 'dar' : (this.role === 'maternity' ? 'anc' : 'soap'));
    this.customCatalog = this.getAttribute('custom-catalog') || null;

    this.render();
    this.setupSpeech();
    this.bindEvents();
    this.restoreDraftIfAvailable();
  }

  disconnectedCallback() {
    this.stopAudioCapture();
  }

  render() {
    const theme = this.getAttribute('theme') || 'light';
    const patientId = this.getAttribute('patient-id') || '';
    const encounterId = this.getAttribute('encounter-id') || '';
    const summary = this.getAttribute('patient-summary') || '';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          position: relative;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          color: #1e293b;
          background: #ffffff;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
          overflow: hidden;
          box-shadow: 0 4px 16px rgba(0,0,0,0.06);
          box-sizing: border-box;
        }
        *, *::before, *::after { box-sizing: border-box; }

        /* Header */
        .ch-header {
          display: flex;
          flex-wrap: wrap;
          justify-content: space-between;
          align-items: center;
          gap: 12px;
          padding: 10px 18px;
          background: #f8fafc;
          border-bottom: 1px solid #e2e8f0;
        }
        .ch-brand {
          font-weight: 700;
          font-size: 13px;
          display: flex;
          align-items: center;
          gap: 8px;
          color: #011b33;
        }
        .ch-meta-pill {
          background: #e0f2fe;
          color: #0369a1;
          font-size: 11px;
          font-weight: 600;
          padding: 2px 8px;
          border-radius: 12px;
        }

        /* Flow Stepper Nav */
        .ch-flow-nav {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 12px;
          padding: 8px 18px;
          background: #f1f5f9;
          border-bottom: 1px solid #e2e8f0;
          font-size: 12px;
        }
        .ch-step-node {
          display: flex;
          align-items: center;
          gap: 6px;
          color: #64748b;
          font-weight: 500;
          transition: all 0.2s ease;
        }
        .ch-step-node.active {
          color: #011b33;
          font-weight: 700;
        }
        .ch-step-node.completed {
          color: #16a34a;
          font-weight: 600;
        }
        .ch-step-bubble {
          width: 20px;
          height: 20px;
          border-radius: 50%;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          font-size: 11px;
          font-weight: 700;
          background: #cbd5e1;
          color: #475569;
        }
        .ch-step-node.active .ch-step-bubble {
          background: #011b33;
          color: #ffffff;
        }
        .ch-step-node.completed .ch-step-bubble {
          background: #16a34a;
          color: #ffffff;
        }
        .ch-step-arrow {
          color: #94a3b8;
          font-size: 12px;
        }

        .ch-tax-switcher {
          display: flex;
          align-items: center;
          gap: 3px;
          background: #edeef2;
          padding: 2px 4px;
          border-radius: 6px;
        }
        .ch-tax-btn {
          border: none;
          background: none;
          font-size: 11px;
          font-weight: 600;
          padding: 3px 8px;
          border-radius: 4px;
          cursor: pointer;
          color: #475569;
          transition: all 0.15s ease;
        }
        .ch-tax-btn.active {
          background: #011b33;
          color: #ffffff;
        }
        .ch-badge {
          display: inline-flex;
          align-items: center;
          gap: 5px;
          padding: 3px 8px;
          font-size: 11px;
          font-weight: 600;
          border-radius: 4px;
          background: #e2e8f0;
          color: #475569;
        }
        .ch-badge.recording {
          background: #dc2626;
          color: #ffffff;
        }
        .ch-badge.success {
          background: #16a34a;
          color: #ffffff;
        }

        .ch-rec-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: #ffffff;
          display: inline-block;
          animation: recPulse 1s infinite alternate;
        }
        @keyframes recPulse {
          0% { opacity: 0.3; transform: scale(0.8); }
          100% { opacity: 1; transform: scale(1.2); }
        }

        /* Brief / Alert Strip */
        .ch-alert-strip {
          padding: 6px 18px;
          background: #fef3c7;
          border-bottom: 1px solid #fde68a;
          color: #92400e;
          font-size: 11px;
          display: flex;
          align-items: center;
          justify-content: space-between;
        }
        .ch-draft-notice {
          padding: 6px 18px;
          background: #f0fdf4;
          border-bottom: 1px solid #bbf7d0;
          color: #166534;
          font-size: 11px;
          display: none;
          align-items: center;
          justify-content: space-between;
        }

        /* Cockpit Body */
        .ch-body {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 16px;
          padding: 16px;
          min-height: 360px;
        }
        @media (max-width: 860px) {
          .ch-body { grid-template-columns: 1fr; }
        }
        .ch-pane {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        .ch-pane-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          font-size: 11px;
          font-weight: 700;
          text-transform: uppercase;
          color: #475569;
        }
        .ch-waveform {
          width: 100%;
          height: 30px;
          background: #0f172a;
          border-radius: 4px;
          display: none;
        }
        textarea.ch-textarea {
          flex: 1;
          width: 100%;
          min-height: 240px;
          border: 1px solid #cbd5e1;
          border-radius: 6px;
          padding: 12px;
          font-size: 13px;
          line-height: 1.5;
          font-family: inherit;
          resize: vertical;
        }
        textarea.ch-textarea:focus {
          outline: none;
          border-color: #0284c7;
          box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
        }

        /* Focused Empty State for Step 1 */
        .ch-empty-guide {
          flex: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          text-align: center;
          padding: 30px 20px;
          border: 2px dashed #e2e8f0;
          border-radius: 8px;
          background: #f8fafc;
          color: #64748b;
          min-height: 240px;
        }

        /* Suggested Orders Deck */
        .ch-orders-deck {
          border-top: 1px solid #e2e8f0;
          background: #f8fafc;
          padding: 14px 18px;
        }
        .ch-deck-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 12px;
        }
        .ch-filter-chips {
          display: flex;
          flex-wrap: wrap;
          gap: 6px;
          align-items: center;
        }
        .ch-chip {
          border: 1px solid #cbd5e1;
          background: #ffffff;
          padding: 3px 10px;
          font-size: 11px;
          font-weight: 600;
          border-radius: 14px;
          cursor: pointer;
          color: #475569;
          transition: all 0.15s ease;
        }
        .ch-chip.active {
          background: #011b33;
          color: #ffffff;
          border-color: #011b33;
        }
        .ch-orders-table {
          width: 100%;
          border-collapse: collapse;
          font-size: 12px;
          background: #ffffff;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          overflow: hidden;
        }
        .ch-orders-table th {
          background: #f1f5f9;
          padding: 8px 12px;
          text-align: left;
          font-weight: 600;
          color: #475569;
          font-size: 11px;
          border-bottom: 1px solid #e2e8f0;
        }
        .ch-orders-table td {
          padding: 8px 12px;
          border-bottom: 1px solid #f1f5f9;
          vertical-align: middle;
        }
        .cat-badge {
          display: inline-block;
          padding: 2px 6px;
          font-size: 10px;
          font-weight: 700;
          border-radius: 3px;
          text-transform: uppercase;
        }
        .cat-rx { background: #dbeafe; color: #1e40af; }
        .cat-lab { background: #ede9fe; color: #5b21b6; }
        .cat-rad { background: #fef3c7; color: #92400e; }
        .cat-proc { background: #fee2e2; color: #991b1b; }
        .cat-nurs { background: #dcfce7; color: #166534; }
        .cat-followup { background: #f1f5f9; color: #334155; }

        .ch-inline-input {
          width: 100%;
          padding: 4px 8px;
          border: 1px solid #cbd5e1;
          border-radius: 4px;
          font-size: 12px;
        }
        .ch-inline-input:focus {
          outline: none;
          border-color: #0284c7;
        }

        .ch-btn-icon-del {
          background: none;
          border: none;
          color: #94a3b8;
          padding: 4px 6px;
          border-radius: 4px;
          cursor: pointer;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          transition: all 0.15s ease;
        }
        .ch-btn-icon-del:hover {
          color: #dc2626;
          background: #fee2e2;
        }

        /* Footer */
        .ch-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 18px;
          background: #f8fafc;
          border-top: 1px solid #e2e8f0;
        }
        .ch-btn {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          font-size: 13px;
          font-weight: 600;
          padding: 7px 14px;
          border-radius: 6px;
          cursor: pointer;
          border: none;
          transition: all 0.15s ease;
        }
        .ch-btn-record {
          background: #011b33;
          color: #ffffff;
        }
        .ch-btn-record.recording {
          background: #dc2626;
          animation: pulse 1.5s infinite;
        }
        .ch-btn-process {
          background: #0284c7;
          color: #ffffff;
        }
        .ch-btn-process.ready {
          background: #0369a1;
          box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.3);
        }
        .ch-btn-sign {
          background: #16a34a;
          color: #ffffff;
        }
        .ch-btn-sign:disabled {
          background: #cbd5e1;
          cursor: not-allowed;
        }
        .ch-btn-secondary {
          background: #ffffff;
          border: 1px solid #cbd5e1;
          color: #475569;
          font-size: 12px;
          padding: 6px 12px;
        }
        .ch-btn-secondary:hover {
          background: #f1f5f9;
        }

        @keyframes pulse {
          0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.6); }
          70% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
          100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }

        /* Vanilla Modal Styles */
        .ch-modal-backdrop {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
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
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 16px;
          background: #f8fafc;
          border-bottom: 1px solid #e2e8f0;
          font-size: 13px;
          color: #011b33;
        }
        .ch-modal-body {
          padding: 16px;
          display: flex;
          flex-direction: column;
          gap: 12px;
        }
        .ch-modal-footer {
          display: flex;
          justify-content: flex-end;
          gap: 8px;
          padding: 12px 16px;
          background: #f8fafc;
          border-top: 1px solid #e2e8f0;
        }
        .ch-form-group {
          display: flex;
          flex-direction: column;
          gap: 4px;
        }
        .ch-form-group label {
          font-size: 11px;
          font-weight: 600;
          color: #475569;
          text-transform: uppercase;
        }
        .ch-form-row {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 10px;
        }
        .ch-btn-icon {
          background: none;
          border: none;
          color: #64748b;
          cursor: pointer;
          padding: 4px;
          border-radius: 4px;
          display: inline-flex;
          align-items: center;
        }
        .ch-btn-icon:hover {
          background: #e2e8f0;
          color: #0f172a;
        }
      </style>

      <!-- 1. Master Header -->
      <div class="ch-header">
        <div class="ch-brand">
          ${ICONS.mic}
          <span>CoreHealth AI Scribe</span>
          ${patientId ? `<span class="ch-meta-pill">Patient #${patientId} ${encounterId ? `· Enc #${encounterId}` : ''}</span>` : ''}
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <select id="lang-select" style="font-size: 11px; padding: 3px 6px; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff;">
            <option value="en-NG">Nigerian English (en-NG)</option>
            <option value="pcm-NG">Nigerian Pidgin (pcm-NG)</option>
            <option value="ha-NG">Hausa (ha-NG)</option>
            <option value="yo-NG">Yoruba (yo-NG)</option>
            <option value="ig-NG">Igbo (ig-NG)</option>
            <option value="en-GB">British English (en-GB)</option>
            <option value="en-US">American English (en-US)</option>
            <option value="fr-FR">Français (fr-FR)</option>
          </select>
          <span id="status-badge" class="ch-badge">${ICONS.check} Ready</span>
        </div>
      </div>

      <!-- 2. Progressive Flow Stepper Navigation -->
      <div class="ch-flow-nav">
        <div class="ch-step-node active" id="st-node-1">
          <span class="ch-step-bubble">1</span>
          <span>Start Dictation or Load Sample</span>
        </div>
        <span class="ch-step-arrow">→</span>
        <div class="ch-step-node" id="st-node-2">
          <span class="ch-step-bubble">2</span>
          <span>Process Note & Extract Orders</span>
        </div>
        <span class="ch-step-arrow">→</span>
        <div class="ch-step-node" id="st-node-3">
          <span class="ch-step-bubble">3</span>
          <span>Review, Adjust & Sign</span>
        </div>
      </div>

      <!-- 3. Draft Notice -->
      <div id="draft-notice" class="ch-draft-notice">
        <span><i id="draft-time-label">Restored uncommitted consultation draft</i></span>
        <div>
          <button type="button" id="btn-discard-draft" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 11px; font-weight: 600;">Discard</button>
        </div>
      </div>

      <!-- 4. Brief / Allergy Alert Strip -->
      ${summary ? `
      <div class="ch-alert-strip">
        <div>
          <strong>Patient Clinical Context:</strong> ${summary}
        </div>
      </div>
      ` : ''}

      <!-- 5. Dual Cockpit Body -->
      <div class="ch-body">
        <!-- Left: Audio Dictation & Capture -->
        <div class="ch-pane">
          <div class="ch-pane-header">
            <span>1. Audio Dictation & Live Transcript</span>
            <div style="display: flex; gap: 6px; align-items: center;">
              <button type="button" id="btn-record" class="ch-btn ch-btn-record">
                ${ICONS.mic} <span id="record-btn-text">Start Dictation</span>
              </button>
              <button type="button" id="btn-sample" class="ch-btn ch-btn-secondary" title="Load sample consultation dictation">
                ${ICONS.sample} <span>Load Sample</span>
              </button>
              <button type="button" id="btn-process" class="ch-btn ch-btn-process" title="Structure transcript into note & extract orders">
                ${ICONS.sparkle} <span id="process-btn-text">Process Note</span>
              </button>
            </div>
          </div>
          <canvas id="waveform-canvas" class="ch-waveform"></canvas>
          <textarea id="transcript-input" class="ch-textarea" placeholder="Click 'Start Dictation' to dictate clinical history, findings, and prescriptions, or click 'Load Sample' to begin..."></textarea>
        </div>

        <!-- Right: Structured Consultation Note (Revealed upon processing) -->
        <div class="ch-pane">
          <div class="ch-pane-header">
            <span id="note-pane-title">2. Structured Note (${this.taxonomy.toUpperCase()})</span>
            <div style="display: flex; gap: 6px; align-items: center;">
              <!-- Taxonomy Switcher -->
              <div class="ch-tax-switcher" id="tax-switcher" style="display: none;"></div>
              <button type="button" id="btn-copy-note" class="ch-btn ch-btn-secondary" style="font-size: 11px; padding: 4px 8px; display: none;">
                ${ICONS.copy} <span>Copy Note</span>
              </button>
              <button type="button" id="btn-clear-all" class="ch-btn ch-btn-secondary" style="font-size: 11px; padding: 4px 8px; color: #dc2626;">
                ${ICONS.trash} <span>Clear</span>
              </button>
            </div>
          </div>

          <!-- Empty Guide before processing -->
          <div id="note-empty-guide" class="ch-empty-guide">
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

          <!-- Structured note textarea (visible once note is populated) -->
          <textarea id="note-input" class="ch-textarea" style="display: none;" placeholder="AI structured note will appear here in selected taxonomy format..."></textarea>
        </div>
      </div>

      <!-- 6. Suggested Orders & Prescriptions Deck (Step 2 & 3) -->
      <div id="orders-deck" class="ch-orders-deck" style="display: none;">
        <div class="ch-deck-header">
          <div>
            <strong style="font-size: 12px; color: #011b33;">CLINICAL ORDERS & PRESCRIPTIONS</strong>
            <span id="orders-count-badge" style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 10px; font-weight: 700; margin-left: 6px;">0 RECOMMENDED</span>
          </div>
          <div class="ch-filter-chips">
            <button type="button" class="ch-chip active" data-filter="all">All</button>
            <button type="button" class="ch-chip" data-filter="rx">Prescriptions</button>
            <button type="button" class="ch-chip" data-filter="lab">Laboratory</button>
            <button type="button" class="ch-chip" data-filter="rad">Imaging</button>
            <button type="button" class="ch-chip" data-filter="proc">Procedures</button>
            <button type="button" id="btn-add-order" class="ch-btn-secondary" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 600;">
              ${ICONS.plus} <span>Add Order</span>
            </button>
          </div>
        </div>

        <table class="ch-orders-table">
          <thead>
            <tr>
              <th style="width: 30px;"><input type="checkbox" id="ch-select-all-orders" checked title="Select/Deselect All"></th>
              <th style="width: 110px;">Category</th>
              <th>Item Name & Details</th>
              <th>Dosage / Indication</th>
              <th>Instructions</th>
              <th style="width: 80px;">Tariff</th>
              <th style="width: 40px; text-align: center;"></th>
            </tr>
          </thead>
          <tbody id="orders-tbody">
            <!-- Dynamic rows -->
          </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 11px; color: #64748b;">
          <span id="tariff-preview">Estimated Tariff: ₦0.00</span>
          <span>Orders will be dispatched directly to laboratory, pharmacy, and radiology queues upon sign-off</span>
        </div>
      </div>

      <!-- 7. Footer -->
      <div class="ch-footer">
        <span style="font-size: 11px; color: #64748b;">openEHR & FHIR R4 Compliant · NDPA 2023 Statutory Privacy</span>
        <button type="button" id="btn-sign" class="ch-btn ch-btn-sign" disabled>
          ${ICONS.sign} <span>Accept & Sign Note & Orders</span>
        </button>
      </div>

      <!-- 8. Vanilla Modal: Add Clinical Order -->
      <div id="modal-add-order" class="ch-modal-backdrop">
        <div class="ch-modal-card">
          <div class="ch-modal-header">
            <div style="display: flex; align-items: center; gap: 6px;">
              ${ICONS.plus}
              <strong>Stage Clinical Order</strong>
            </div>
            <button type="button" id="btn-close-modal" class="ch-btn-icon" title="Close">${ICONS.close}</button>
          </div>
          <div class="ch-modal-body">
            <div class="ch-form-group">
              <label>Order Category</label>
              <select id="mo-cat" class="ch-inline-input">
                <option value="medication">Prescription / Medication (Rx)</option>
                <option value="lab">Laboratory Investigation</option>
                <option value="imaging">Radiology / Imaging</option>
                <option value="procedure">Clinical Procedure</option>
                <option value="nursing">Nursing Intervention</option>
              </select>
            </div>
            <div class="ch-form-group">
              <label>Item Name *</label>
              <input type="text" id="mo-name" class="ch-inline-input" placeholder="e.g. Paracetamol 500mg Tablet, Full Blood Count, Chest X-Ray">
            </div>
            <div class="ch-form-row">
              <div class="ch-form-group">
                <label>Dosage / Indication</label>
                <input type="text" id="mo-spec" class="ch-inline-input" placeholder="e.g. 500mg TDS, Confirm malaria">
              </div>
              <div class="ch-form-group">
                <label>Instructions / Route</label>
                <input type="text" id="mo-inst" class="ch-inline-input" placeholder="e.g. Oral after meals, Fasting EDTA">
              </div>
            </div>
            <div class="ch-form-group">
              <label>Tariff / Estimated Cost (₦)</label>
              <input type="number" id="mo-tariff" class="ch-inline-input" value="1000" min="0">
            </div>
          </div>
          <div class="ch-modal-footer">
            <button type="button" id="btn-cancel-modal" class="ch-btn ch-btn-secondary">${ICONS.close} Cancel</button>
            <button type="button" id="btn-confirm-add-order" class="ch-btn" style="background: #011b33; color: #fff;">${ICONS.plus} Add to Staged Deck</button>
          </div>
        </div>
      </div>
    `;

    this.updateTaxonomyPills();
  }

  updateTaxonomyPills() {
    const switcher = this.shadowRoot.getElementById('tax-switcher');
    if (!switcher) return;

    let options = [];
    if (this.role === 'nurse') {
      options = [
        { id: 'dar', label: 'Focus DAR' },
        { id: 'sbar', label: 'SBAR' },
        { id: 'soapie', label: 'SOAPIE' },
      ];
    } else if (this.role === 'maternity') {
      options = [
        { id: 'anc', label: 'ANC' },
        { id: 'labor', label: 'Labor' },
        { id: 'pnc', label: 'PNC' },
      ];
    } else {
      options = [
        { id: 'soap', label: 'SOAP' },
        { id: 'clerking', label: 'Clerking' },
        { id: 'pomr', label: 'POMR' },
      ];
    }

    switcher.innerHTML = options.map(opt => `
      <button type="button" class="ch-tax-btn ${this.taxonomy === opt.id ? 'active' : ''}" data-tax="${opt.id}">
        ${opt.label}
      </button>
    `).join('');

    switcher.querySelectorAll('.ch-tax-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const targetTax = e.target.getAttribute('data-tax');
        if (targetTax && targetTax !== this.taxonomy) {
          this.switchTaxonomy(targetTax);
        }
      });
    });
  }

  setFlowStep(step) {
    this.currentStep = step;
    const n1 = this.shadowRoot.getElementById('st-node-1');
    const n2 = this.shadowRoot.getElementById('st-node-2');
    const n3 = this.shadowRoot.getElementById('st-node-3');

    if (n1) {
      n1.className = step >= 1 ? (step > 1 ? 'ch-step-node completed' : 'ch-step-node active') : 'ch-step-node';
    }
    if (n2) {
      n2.className = step >= 2 ? (step > 2 ? 'ch-step-node completed' : 'ch-step-node active') : 'ch-step-node';
    }
    if (n3) {
      n3.className = step >= 3 ? 'ch-step-node active' : 'ch-step-node';
    }
  }

  setupSpeech() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      this.recognition = new SpeechRecognition();
      this.recognition.continuous = true;
      this.recognition.interimResults = true;
      this.recognition.lang = this.getAttribute('locale') || 'en-NG';

      this.recognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
          if (event.results[i].isFinal) {
            this.transcript += event.results[i][0].transcript + ' ';
          } else {
            interim += event.results[i][0].transcript;
          }
        }
        const input = this.shadowRoot.getElementById('transcript-input');
        if (input) {
          input.value = this.transcript + (interim ? '(' + interim + ')' : '');
          this.onTranscriptChanged(input.value);
        }
        this.saveDraft();
      };

      this.recognition.onerror = (e) => {
        console.warn('Speech recognition warning', e);
      };
    }
  }

  onTranscriptChanged(text) {
    const processBtn = this.shadowRoot.getElementById('btn-process');
    if (processBtn) {
      if (text && text.trim().length > 10) {
        processBtn.classList.add('ready');
        this.setFlowStep(2);
      } else {
        processBtn.classList.remove('ready');
        if (!this.structuredSections || this.structuredSections.length === 0) {
          this.setFlowStep(1);
        }
      }
    }
  }

  bindEvents() {
    const recordBtn = this.shadowRoot.getElementById('btn-record');
    const processBtn = this.shadowRoot.getElementById('btn-process');
    const sampleBtn = this.shadowRoot.getElementById('btn-sample');
    const copyNoteBtn = this.shadowRoot.getElementById('btn-copy-note');
    const clearAllBtn = this.shadowRoot.getElementById('btn-clear-all');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const langSelect = this.shadowRoot.getElementById('lang-select');
    const addOrderBtn = this.shadowRoot.getElementById('btn-add-order');
    const discardDraftBtn = this.shadowRoot.getElementById('btn-discard-draft');
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const noteInput = this.shadowRoot.getElementById('note-input');
    const selectAllCheckbox = this.shadowRoot.getElementById('ch-select-all-orders');

    // Add Order Modal Controls
    const modalAddOrder = this.shadowRoot.getElementById('modal-add-order');
    const btnCloseModal = this.shadowRoot.getElementById('btn-close-modal');
    const btnCancelModal = this.shadowRoot.getElementById('btn-cancel-modal');
    const btnConfirmAddOrder = this.shadowRoot.getElementById('btn-confirm-add-order');
    const moName = this.shadowRoot.getElementById('mo-name');
    const moCat = this.shadowRoot.getElementById('mo-cat');
    const moSpec = this.shadowRoot.getElementById('mo-spec');
    const moInst = this.shadowRoot.getElementById('mo-inst');
    const moTariff = this.shadowRoot.getElementById('mo-tariff');

    const closeModal = () => {
      if (modalAddOrder) modalAddOrder.style.display = 'none';
    };

    const openModal = () => {
      if (modalAddOrder) {
        modalAddOrder.style.display = 'flex';
        if (moName) {
          moName.value = '';
          moName.focus();
        }
        if (moSpec) moSpec.value = '';
        if (moInst) moInst.value = '';
        if (moTariff) moTariff.value = '1000';
      }
    };

    if (addOrderBtn) {
      addOrderBtn.addEventListener('click', openModal);
    }
    if (btnCloseModal) {
      btnCloseModal.addEventListener('click', closeModal);
    }
    if (btnCancelModal) {
      btnCancelModal.addEventListener('click', closeModal);
    }
    if (modalAddOrder) {
      modalAddOrder.addEventListener('click', (e) => {
        if (e.target === modalAddOrder) closeModal();
      });
    }

    const submitNewOrder = () => {
      const name = moName ? moName.value.trim() : '';
      if (!name) {
        if (moName) {
          moName.style.borderColor = '#dc2626';
          moName.focus();
        }
        return;
      }
      if (moName) moName.style.borderColor = '#cbd5e1';

      const cat = moCat ? moCat.value : 'medication';
      const spec = moSpec ? moSpec.value.trim() : '';
      const inst = moInst ? moInst.value.trim() : '';
      const tariff = moTariff ? parseFloat(moTariff.value) || 0 : 0;

      const isMed = cat === 'medication';
      const newOrder = {
        id: 'ord_' + (this.groundedOrders.length + 1),
        order_type: isMed ? 'PRODUCT' : 'SERVICE',
        service_type: isMed ? null : cat,
        category_id: isMed ? 4 : 5,
        category_name: isMed ? 'Prescription' : cat.toUpperCase(),
        catalog_id: null,
        item_name: name,
        dosage: isMed ? (spec || '1 tab') : null,
        indication: !isMed ? spec : null,
        frequency: isMed ? 'OD' : null,
        duration: isMed ? '3 days' : null,
        route: isMed ? 'oral' : null,
        quantity: 1,
        instructions: inst || (isMed ? 'Take as directed by clinician' : 'Standard clinical protocol'),
        unit_price: tariff,
        target_queue: isMed ? 'dispensing_queue' : (cat === 'lab' ? 'laboratory_queue' : 'radiology_queue'),
        is_selected: true,
        selected: true,
        is_matched: false,
        is_free_form: true,
        safety_warning: null
      };

      this.groundedOrders.push(newOrder);
      this.renderOrdersTable();
      this.saveDraft();
      this.dispatchEvent(new CustomEvent('corehealth:orders-updated', { detail: this.groundedOrders }));
      closeModal();
    };

    if (btnConfirmAddOrder) {
      btnConfirmAddOrder.addEventListener('click', submitNewOrder);
    }
    if (moName) {
      moName.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') submitNewOrder();
        if (e.key === 'Escape') closeModal();
      });
    }

    if (recordBtn) {
      recordBtn.addEventListener('click', () => this.toggleRecording());
    }

    if (processBtn) {
      processBtn.addEventListener('click', () => {
        const statusBadge = this.shadowRoot.getElementById('status-badge');
        if (statusBadge) {
          statusBadge.innerHTML = `${ICONS.sparkle} Structuring note & orders...`;
          statusBadge.className = 'ch-badge';
        }
        this.processDictation();
      });
    }

    if (sampleBtn) {
      sampleBtn.addEventListener('click', () => {
        if (transcriptInput) {
          transcriptInput.value = 'Patient presents with a 3-day history of high-grade fever, chills, severe headache, and general body weakness. Physical examination reveals: Temperature 38.6°C, BP 124/82 mmHg, Pulse 88 bpm. Chest is clear on auscultation, abdomen is soft and non-tender. Assessment: Acute Uncomplicated Malaria. Prescribe Artemether-Lumefantrine 80/480mg twice daily for 3 days and Paracetamol 500mg TDS for 3 days. Request Urgent Malaria Parasite (MP) Giemsa microscopy and Full Blood Count (FBC). Advise adequate bed rest and oral fluid intake.';
          this.transcript = transcriptInput.value;
          this.onTranscriptChanged(transcriptInput.value);
          this.saveDraft();
        }
      });
    }

    if (copyNoteBtn) {
      copyNoteBtn.addEventListener('click', async () => {
        if (noteInput && noteInput.value) {
          try {
            await navigator.clipboard.writeText(noteInput.value);
            const origHtml = copyNoteBtn.innerHTML;
            copyNoteBtn.innerHTML = `${ICONS.check} <span>Copied!</span>`;
            setTimeout(() => { copyNoteBtn.innerHTML = origHtml; }, 1500);
          } catch (e) {
            console.warn('Clipboard write error', e);
          }
        }
      });
    }

    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', () => {
        if (confirm('Clear current transcript and clinical orders?')) {
          if (transcriptInput) transcriptInput.value = '';
          if (noteInput) noteInput.value = '';
          this.transcript = '';
          this.structuredSections = [];
          this.groundedOrders = [];
          this.renderOrdersTable();
          if (signBtn) signBtn.disabled = true;

          // Reset focused flow state to Step 1
          const guide = this.shadowRoot.getElementById('note-empty-guide');
          const taxSwitcher = this.shadowRoot.getElementById('tax-switcher');
          const copyBtn = this.shadowRoot.getElementById('btn-copy-note');
          if (guide) guide.style.display = 'flex';
          if (noteInput) noteInput.style.display = 'none';
          if (taxSwitcher) taxSwitcher.style.display = 'none';
          if (copyBtn) copyBtn.style.display = 'none';
          this.setFlowStep(1);

          this.discardDraft();
        }
      });
    }

    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', (e) => {
        const checked = e.target.checked;
        this.groundedOrders.forEach(ord => {
          ord.selected = checked;
          ord.is_selected = checked;
        });
        this.shadowRoot.querySelectorAll('.order-chk').forEach(cb => { cb.checked = checked; });
        this.updateTariffPreview();
        this.dispatchEvent(new CustomEvent('corehealth:orders-updated', { detail: this.groundedOrders }));
      });
    }

    if (signBtn) {
      signBtn.addEventListener('click', () => this.handleSignOff());
    }

    if (langSelect) {
      langSelect.addEventListener('change', (e) => {
        if (this.recognition) {
          this.recognition.lang = e.target.value;
        }
      });
    }

    if (discardDraftBtn) {
      discardDraftBtn.addEventListener('click', () => this.discardDraft());
    }

    if (transcriptInput) {
      transcriptInput.addEventListener('input', () => {
        this.onTranscriptChanged(transcriptInput.value);
        this.saveDraft();
      });
    }

    if (noteInput) {
      noteInput.addEventListener('input', () => this.saveDraft());
    }

    // Filter Chips
    this.shadowRoot.querySelectorAll('.ch-chip').forEach(chip => {
      chip.addEventListener('click', (e) => {
        const filter = e.target.getAttribute('data-filter');
        if (filter) {
          this.shadowRoot.querySelectorAll('.ch-chip').forEach(c => c.classList.remove('active'));
          e.target.classList.add('active');
          this.activeFilter = filter;
          this.renderOrdersTable();
        }
      });
    });
  }

  toggleRecording() {
    const recordBtn = this.shadowRoot.getElementById('btn-record');
    const statusBadge = this.shadowRoot.getElementById('status-badge');
    const waveformCanvas = this.shadowRoot.getElementById('waveform-canvas');

    if (!this.isRecording) {
      this.isRecording = true;
      this.secondsRecorded = 0;
      recordBtn.classList.add('recording');
      recordBtn.innerHTML = `${ICONS.stop} <span>Stop Dictation (<span id="rec-btn-timer">00:00</span>)</span>`;
      statusBadge.innerHTML = `<span class="ch-rec-dot"></span> <span>Recording (<span id="rec-badge-timer">00:00</span>)</span>`;
      statusBadge.className = 'ch-badge recording';
      if (waveformCanvas) waveformCanvas.style.display = 'block';

      this.timerInterval = setInterval(() => {
        this.secondsRecorded++;
        const m = String(Math.floor(this.secondsRecorded / 60)).padStart(2, '0');
        const s = String(this.secondsRecorded % 60).padStart(2, '0');
        const timeStr = `${m}:${s}`;
        const btnTimer = this.shadowRoot.getElementById('rec-btn-timer');
        const badgeTimer = this.shadowRoot.getElementById('rec-badge-timer');
        if (btnTimer) btnTimer.innerText = timeStr;
        if (badgeTimer) badgeTimer.innerText = timeStr;
      }, 1000);

      this.startAudioVisualizer();
      if (this.recognition) {
        try { this.recognition.start(); } catch (e) {}
      }
      this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'recording' }));
    } else {
      this.isRecording = false;
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
      recordBtn.classList.remove('recording');
      recordBtn.innerHTML = `${ICONS.mic} <span>Start Dictation</span>`;
      statusBadge.innerHTML = `${ICONS.sparkle} <span>Structuring note & orders...</span>`;
      statusBadge.className = 'ch-badge';
      if (waveformCanvas) waveformCanvas.style.display = 'none';

      this.stopAudioCapture();
      this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'processing' }));
      this.processDictation();
    }
  }

  startAudioVisualizer() {
    const canvas = this.shadowRoot.getElementById('waveform-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    canvas.width = canvas.parentElement ? canvas.parentElement.clientWidth : 400;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      let phase = 0;
      const drawSynthetic = () => {
        if (!this.isRecording) return;
        this.animFrameId = requestAnimationFrame(drawSynthetic);
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        phase += 0.08;
        const barWidth = canvas.width / 36;
        for (let i = 0; i < 36; i++) {
          const h = (Math.sin(phase + i * 0.3) * 0.5 + 0.5) * (canvas.height * 0.8) + 4;
          ctx.fillStyle = '#38bdf8';
          ctx.fillRect(i * barWidth + 2, (canvas.height - h) / 2, barWidth - 4, h);
        }
      };
      drawSynthetic();
      return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true }).then((stream) => {
      this.audioStream = stream;
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      this.audioContext = new AudioCtx();
      this.audioAnalyser = this.audioContext.createAnalyser();
      const source = this.audioContext.createMediaStreamSource(stream);
      source.connect(this.audioAnalyser);

      const dataArray = new Uint8Array(this.audioAnalyser.frequencyBinCount);
      const draw = () => {
        if (!this.isRecording) return;
        this.animFrameId = requestAnimationFrame(draw);
        this.audioAnalyser.getByteFrequencyData(dataArray);

        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const barWidth = (canvas.width / 40);
        let x = 0;
        for (let i = 0; i < 40; i++) {
          const barHeight = Math.max(3, (dataArray[i * 4] / 255) * canvas.height);
          ctx.fillStyle = '#38bdf8';
          ctx.fillRect(x + 1, canvas.height - barHeight, barWidth - 2, barHeight);
          x += barWidth;
        }
      };
      draw();
    }).catch(err => {
      console.warn('Microphone stream error for visualizer', err);
    });
  }

  stopAudioCapture() {
    if (this.animFrameId) cancelAnimationFrame(this.animFrameId);
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
    if (this.recognition) {
      try { this.recognition.stop(); } catch (e) {}
    }
    if (this.audioStream) {
      this.audioStream.getTracks().forEach(t => t.stop());
      this.audioStream = null;
    }
    if (this.audioContext) {
      try { this.audioContext.close(); } catch (e) {}
      this.audioContext = null;
    }
  }

  async processDictation() {
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const noteInput = this.shadowRoot.getElementById('note-input');
    const guide = this.shadowRoot.getElementById('note-empty-guide');
    const taxSwitcher = this.shadowRoot.getElementById('tax-switcher');
    const copyBtn = this.shadowRoot.getElementById('btn-copy-note');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const statusBadge = this.shadowRoot.getElementById('status-badge');

    const text = transcriptInput ? transcriptInput.value.trim() : '';
    if (!text) {
      statusBadge.innerHTML = `${ICONS.check} Ready`;
      statusBadge.className = 'ch-badge';
      return;
    }

    const baseUrl = (this.getAttribute('base-url') || '').replace(/\/+$/, '');
    const token = this.getAttribute('sdk-token') || '';

    try {
      const res = await fetch(`${baseUrl}/api/sdk/scribe/process`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          transcript: text,
          patient_id: this.getAttribute('patient-id'),
          encounter_id: this.getAttribute('encounter-id'),
          role: this.role,
          taxonomy: this.taxonomy,
          patient_summary: this.getAttribute('patient-summary') || '',
          custom_catalog: this.customCatalog || undefined,
          existing_sections: this.structuredSections,
          existing_orders: this.groundedOrders,
        })
      });

      const data = await res.json();
      if (data.success) {
        this.structuredSections = data.structured_sections || [];
        this.groundedOrders = data.grounded_orders || [];

        // Reveal Structured Note & Tax Switcher (Transition to Step 2 & 3)
        if (guide) guide.style.display = 'none';
        if (noteInput) {
          noteInput.style.display = 'block';
          noteInput.value = data.formatted_markdown || '';
        }
        if (taxSwitcher) taxSwitcher.style.display = 'flex';
        if (copyBtn) copyBtn.style.display = 'inline-flex';

        // Render Orders Deck
        this.renderOrdersTable();

        // Advance stepper to Step 3
        this.setFlowStep(3);

        signBtn.disabled = false;
        statusBadge.innerHTML = `${ICONS.check} Ready for Review`;
        statusBadge.className = 'ch-badge success';
        this.saveDraft();

        this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'reviewing' }));
        this.dispatchEvent(new CustomEvent('corehealth:orders-updated', { detail: this.groundedOrders }));
      }
    } catch (e) {
      console.error('Error processing dictation', e);
      statusBadge.innerText = 'Error';
    }
  }

  renderOrdersTable() {
    const deck = this.shadowRoot.getElementById('orders-deck');
    const tbody = this.shadowRoot.getElementById('orders-tbody');
    const countBadge = this.shadowRoot.getElementById('orders-count-badge');
    const tariffEl = this.shadowRoot.getElementById('tariff-preview');

    if (!deck || !tbody) return;

    if (this.groundedOrders.length === 0) {
      deck.style.display = 'none';
      return;
    }

    deck.style.display = 'block';
    countBadge.innerText = `${this.groundedOrders.length} RECOMMENDED`;

    // Filter items
    const filtered = this.groundedOrders.filter(ord => {
      if (this.activeFilter === 'all') return true;
      if (this.activeFilter === 'rx') return ord.order_type === 'PRODUCT';
      if (this.activeFilter === 'lab') return ord.service_type === 'lab';
      if (this.activeFilter === 'rad') return ord.service_type === 'imaging';
      if (this.activeFilter === 'proc') return ord.service_type === 'procedure';
      return true;
    });

    let totalTariff = 0;
    tbody.innerHTML = filtered.map((ord, idx) => {
      const realIdx = this.groundedOrders.indexOf(ord);
      if (ord.is_selected) {
        totalTariff += (ord.unit_price || 0) * (ord.quantity || 1);
      }

      let catBadgeClass = 'cat-rx';
      let catLabel = 'PRESCRIPTION';
      if (ord.order_type === 'SERVICE') {
        if (ord.service_type === 'lab') { catBadgeClass = 'cat-lab'; catLabel = 'LABORATORY'; }
        else if (ord.service_type === 'imaging') { catBadgeClass = 'cat-rad'; catLabel = 'IMAGING'; }
        else if (ord.service_type === 'procedure') { catBadgeClass = 'cat-proc'; catLabel = 'PROCEDURE'; }
        else { catBadgeClass = 'cat-nurs'; catLabel = 'NURSING'; }
      } else if (ord.order_type === 'FOLLOW_UP') {
        catBadgeClass = 'cat-followup'; catLabel = 'REVIEW';
      }

      return `
        <tr data-idx="${realIdx}">
          <td>
            <input type="checkbox" class="order-chk" data-idx="${realIdx}" ${ord.is_selected ? 'checked' : ''}>
          </td>
          <td>
            <span class="cat-badge ${catBadgeClass}">${catLabel}</span>
          </td>
          <td>
            <strong>${ord.item_name}</strong>
            ${ord.catalog_id ? `<span style="font-size: 10px; color: #16a34a; margin-left: 4px;">#${ord.catalog_id}</span>` : '<span style="font-size: 10px; color: #d97706; margin-left: 4px;">FREE-FORM</span>'}
            ${ord.safety_warning ? `<div style="color: #dc2626; font-size: 10px; font-weight: 700;">${ord.safety_warning}</div>` : ''}
          </td>
          <td>
            <input type="text" class="ch-inline-input order-spec" data-idx="${realIdx}" value="${ord.dosage || ord.indication || ''}" placeholder="Dosage / Indication">
          </td>
          <td>
            <input type="text" class="ch-inline-input order-inst" data-idx="${realIdx}" value="${ord.instructions || ''}" placeholder="Instructions / Route">
          </td>
          <td style="font-weight: 600;">₦${((ord.unit_price || 0) * (ord.quantity || 1)).toLocaleString()}</td>
          <td style="text-align: center;">
            <button type="button" class="btn-del-order ch-btn-icon-del" data-idx="${realIdx}" title="Remove order from staging">
              ${ICONS.trash}
            </button>
          </td>
        </tr>
      `;
    }).join('');

    tariffEl.innerText = `Total Staged: ${this.groundedOrders.filter(o => o.is_selected).length} items · Estimated Tariff: ₦${totalTariff.toLocaleString()}`;

    // Bind checkboxes & inputs
    tbody.querySelectorAll('.order-chk').forEach(chk => {
      chk.addEventListener('change', (e) => {
        const i = parseInt(e.target.getAttribute('data-idx'), 10);
        this.groundedOrders[i].is_selected = e.target.checked;
        this.groundedOrders[i].selected = e.target.checked;
        this.updateTariffPreview();
        this.dispatchEvent(new CustomEvent('corehealth:orders-updated', { detail: this.groundedOrders }));
      });
    });

    tbody.querySelectorAll('.order-spec').forEach(inp => {
      inp.addEventListener('input', (e) => {
        const i = parseInt(e.target.getAttribute('data-idx'), 10);
        if (this.groundedOrders[i].order_type === 'PRODUCT') {
          this.groundedOrders[i].dosage = e.target.value;
        } else {
          this.groundedOrders[i].indication = e.target.value;
        }
        this.saveDraft();
      });
    });

    tbody.querySelectorAll('.order-inst').forEach(inp => {
      inp.addEventListener('input', (e) => {
        const i = parseInt(e.target.getAttribute('data-idx'), 10);
        this.groundedOrders[i].instructions = e.target.value;
        this.saveDraft();
      });
    });

    tbody.querySelectorAll('.btn-del-order').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const targetBtn = e.target.closest('.btn-del-order');
        if (!targetBtn) return;
        const i = parseInt(targetBtn.getAttribute('data-idx'), 10);
        this.groundedOrders.splice(i, 1);
        this.renderOrdersTable();
        this.saveDraft();
        this.dispatchEvent(new CustomEvent('corehealth:orders-updated', { detail: this.groundedOrders }));
      });
    });
  }

  updateTariffPreview() {
    const tariffEl = this.shadowRoot.getElementById('tariff-preview');
    if (!tariffEl) return;
    let totalTariff = 0;
    this.groundedOrders.forEach(ord => {
      if (ord.is_selected) {
        totalTariff += (ord.unit_price || 0) * (ord.quantity || 1);
      }
    });
    tariffEl.innerText = `Total Staged: ${this.groundedOrders.filter(o => o.is_selected).length} items · Estimated Tariff: ₦${totalTariff.toLocaleString()}`;
  }

  async switchTaxonomy(newTaxonomy) {
    const baseUrl = (this.getAttribute('base-url') || '').replace(/\/+$/, '');
    const token = this.getAttribute('sdk-token') || '';
    const noteInput = this.shadowRoot.getElementById('note-input');
    const title = this.shadowRoot.getElementById('note-pane-title');

    try {
      const res = await fetch(`${baseUrl}/api/sdk/scribe/convert-format`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          from_taxonomy: this.taxonomy,
          to_taxonomy: newTaxonomy,
          role: this.role,
          current_sections: this.structuredSections,
        })
      });

      const data = await res.json();
      if (data.success) {
        this.taxonomy = newTaxonomy;
        this.structuredSections = data.structured_sections || [];
        if (noteInput) noteInput.value = data.formatted_markdown || '';
        if (title) title.innerText = `2. Structured Note (${newTaxonomy.toUpperCase()})`;
        this.updateTaxonomyPills();
        this.saveDraft();
      }
    } catch (e) {
      console.error('Error switching taxonomy', e);
    }
  }

  promptAddOrder() {
    const modal = this.shadowRoot.getElementById('modal-add-order');
    const moName = this.shadowRoot.getElementById('mo-name');
    if (modal) {
      modal.style.display = 'flex';
      if (moName) {
        moName.value = '';
        moName.focus();
      }
    }
  }

  async handleSignOff() {
    const noteInput = this.shadowRoot.getElementById('note-input');
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const statusBadge = this.shadowRoot.getElementById('status-badge');

    const finalNote = noteInput ? noteInput.value.trim() : '';
    if (!finalNote) return;

    signBtn.disabled = true;
    signBtn.innerHTML = `<span>Signing Note & Orders...</span>`;

    const baseUrl = (this.getAttribute('base-url') || '').replace(/\/+$/, '');
    const token = this.getAttribute('sdk-token') || '';
    const selectedOrders = this.groundedOrders.filter(o => o.is_selected);

    try {
      // 1. Sign
      const signRes = await fetch(`${baseUrl}/api/sdk/scribe/sign`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          patient_id: this.getAttribute('patient-id'),
          encounter_id: this.getAttribute('encounter-id'),
          role: this.role,
          taxonomy_type: this.taxonomy,
          final_signed_note: finalNote,
          original_transcript: transcriptInput ? transcriptInput.value : '',
          approved_orders: selectedOrders,
        })
      });
      const signData = await signRes.json();

      // 2. Fetch full compositions
      const compRes = await fetch(`${baseUrl}/api/sdk/scribe/compositions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ cross_border: false })
      });
      const compData = await compRes.json();

      signBtn.innerHTML = `${ICONS.check} <span>Signed</span>`;
      statusBadge.innerHTML = `${ICONS.check} <span>Signed Off</span>`;
      statusBadge.className = 'ch-badge success';

      this.clearDraft();

      const payload = {
        audit_id: signData.audit_id,
        patient_id: this.getAttribute('patient-id'),
        encounter_id: this.getAttribute('encounter-id'),
        role: this.role,
        taxonomy: this.taxonomy,
        signed_at: signData.signed_at,
        final_signed_note: finalNote,
        approved_orders: selectedOrders,
        openehr_composition: compData.openehr_composition || null,
        fhir_bundle: compData.fhir_bundle || null,
      };

      this.dispatchEvent(new CustomEvent('corehealth:signed-off', { detail: payload }));
      this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'signed-off' }));
    } catch (e) {
      console.error('Error signing scribe session', e);
      signBtn.disabled = false;
      signBtn.innerHTML = `${ICONS.sign} <span>Accept & Sign Note & Orders</span>`;
    }
  }

  // Draft Persistence
  getDraftKey() {
    const pId = this.getAttribute('patient-id') || '0';
    const eId = this.getAttribute('encounter-id') || '0';
    return `ch_scribe_draft_${pId}_${eId}`;
  }

  saveDraft() {
    clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(() => {
      try {
        const transcriptInput = this.shadowRoot.getElementById('transcript-input');
        const noteInput = this.shadowRoot.getElementById('note-input');
        const draft = {
          transcript: transcriptInput ? transcriptInput.value : '',
          note: noteInput ? noteInput.value : '',
          taxonomy: this.taxonomy,
          structuredSections: this.structuredSections,
          groundedOrders: this.groundedOrders,
          savedAt: new Date().toISOString(),
        };
        localStorage.setItem(this.getDraftKey(), JSON.stringify(draft));
      } catch (e) {}
    }, 500);
  }

  restoreDraftIfAvailable() {
    try {
      const raw = localStorage.getItem(this.getDraftKey());
      if (!raw) return;
      const draft = JSON.parse(raw);
      if (!draft) return;

      const transcriptInput = this.shadowRoot.getElementById('transcript-input');
      const noteInput = this.shadowRoot.getElementById('note-input');
      const guide = this.shadowRoot.getElementById('note-empty-guide');
      const taxSwitcher = this.shadowRoot.getElementById('tax-switcher');
      const copyBtn = this.shadowRoot.getElementById('btn-copy-note');
      const notice = this.shadowRoot.getElementById('draft-notice');
      const label = this.shadowRoot.getElementById('draft-time-label');

      if (draft.transcript && transcriptInput) {
        transcriptInput.value = draft.transcript;
        this.onTranscriptChanged(draft.transcript);
      }
      if (draft.note && noteInput) {
        noteInput.value = draft.note;
        noteInput.style.display = 'block';
        if (guide) guide.style.display = 'none';
        if (taxSwitcher) taxSwitcher.style.display = 'flex';
        if (copyBtn) copyBtn.style.display = 'inline-flex';
        this.setFlowStep(3);
      }
      if (draft.taxonomy) this.taxonomy = draft.taxonomy;
      if (draft.structuredSections) this.structuredSections = draft.structuredSections;
      if (draft.groundedOrders) {
        this.groundedOrders = draft.groundedOrders;
        this.renderOrdersTable();
      }

      if (notice && label && (draft.transcript || draft.note)) {
        notice.style.display = 'flex';
        label.innerText = `Restored uncommitted consultation draft (${new Date(draft.savedAt).toLocaleTimeString()})`;
      }
    } catch (e) {}
  }

  discardDraft() {
    try {
      localStorage.removeItem(this.getDraftKey());
      const transcriptInput = this.shadowRoot.getElementById('transcript-input');
      const noteInput = this.shadowRoot.getElementById('note-input');
      const notice = this.shadowRoot.getElementById('draft-notice');

      if (transcriptInput) transcriptInput.value = '';
      if (noteInput) noteInput.value = '';
      this.groundedOrders = [];
      this.renderOrdersTable();
      if (notice) notice.style.display = 'none';
    } catch (e) {}
  }

  clearDraft() {
    try {
      localStorage.removeItem(this.getDraftKey());
      const notice = this.shadowRoot.getElementById('draft-notice');
      if (notice) notice.style.display = 'none';
    } catch (e) {}
  }
}

/**
 * CoreHealth AI Scribe — Master Controller Class
 * Unified API supporting both Track A (Web Component) and Track B (iFrame Bridge).
 */




class CoreHealthScribe {
  constructor(options) {
    if (!options || !options.baseUrl) {
      throw new Error('[CoreHealthScribe] "baseUrl" option is required.');
    }
    if (!options.sdkToken) {
      throw new Error('[CoreHealthScribe] "sdkToken" option is required.');
    }
    if (!options.mountTarget) {
      throw new Error('[CoreHealthScribe] "mountTarget" option is required.');
    }

    this.options = Object.assign({
      track: 'web-component',
      locale: 'en-NG',
      theme: 'light',
    }, options);

    this.bridge = null;
    this.element = null;
    this.mounted = false;
  }

  async mount() {
    if (this.mounted) return;

    if (this.options.track === 'iframe') {
      this.bridge = new IFrameBridge(this.options);
      await this.bridge.mount();
    } else {
      // Ensure custom element is registered
      if (typeof customElements !== 'undefined' && !customElements.get('corehealth-scribe-studio')) {
        customElements.define('corehealth-scribe-studio', CoreHealthScribeElement);
      }

      const target = typeof this.options.mountTarget === 'string'
        ? document.querySelector(this.options.mountTarget)
        : this.options.mountTarget;

      if (!target) {
        throw new Error(`[CoreHealthScribe] Mount target "${this.options.mountTarget}" not found in DOM.`);
      }

      const el = document.createElement('corehealth-scribe-studio');
      el.setAttribute('base-url', this.options.baseUrl);
      el.setAttribute('sdk-token', this.options.sdkToken);
      if (this.options.patientId) el.setAttribute('patient-id', String(this.options.patientId));
      if (this.options.encounterId) el.setAttribute('encounter-id', String(this.options.encounterId));
      if (this.options.patientSummary) el.setAttribute('patient-summary', String(this.options.patientSummary));
      if (this.options.role) el.setAttribute('role', String(this.options.role));
      if (this.options.taxonomy) el.setAttribute('taxonomy', String(this.options.taxonomy));
      if (this.options.customCatalog) {
        el.setAttribute('custom-catalog', typeof this.options.customCatalog === 'string'
          ? this.options.customCatalog
          : JSON.stringify(this.options.customCatalog));
      }
      el.setAttribute('locale', this.options.locale || 'en-NG');
      el.setAttribute('theme', this.options.theme || 'light');

      // Bind custom element event listeners
      if (typeof this.options.onSignedOff === 'function') {
        el.addEventListener('corehealth:signed-off', (e) => this.options.onSignedOff(e.detail));
      }
      if (typeof this.options.onStateChange === 'function') {
        el.addEventListener('corehealth:state-change', (e) => this.options.onStateChange(e.detail));
      }
      if (typeof this.options.onOrdersUpdated === 'function') {
        el.addEventListener('corehealth:orders-updated', (e) => this.options.onOrdersUpdated(e.detail));
      }

      target.innerHTML = '';
      target.appendChild(el);
      this.element = el;

      if (typeof this.options.onReady === 'function') {
        this.options.onReady({
          patientId: this.options.patientId,
          encounterId: this.options.encounterId
        });
      }
    }

    this.mounted = true;
  }

  unmount() {
    if (this.bridge) {
      this.bridge.unmount();
      this.bridge = null;
    }
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
      this.element = null;
    }
    this.mounted = false;
  }

  destroy() {
    this.unmount();
  }

  setToken(sdkToken) {
    this.options.sdkToken = sdkToken;
    if (this.element) {
      this.element.setAttribute('sdk-token', sdkToken);
    }
  }

  /**
   * Super-Compress partner EMR categories and items into CSCP compact format.
   * Compresses 500+ hospital items into ~1,500 LLM tokens (10x-15x smaller than raw JSON).
   */
  static compressCatalog(categories = []) {
    const lines = [];
    for (const cat of categories) {
      const catId = cat.id || 'cat';
      const catType = (cat.type || 'SERVICE').toUpperCase();
      const catName = cat.name || catId;
      lines.push(`#CAT:${catId}|${catType}|${catName}`);

      const itemTokens = [];
      const items = cat.items || [];
      for (const it of items) {
        if (typeof it === 'string') {
          itemTokens.push(it);
        } else {
          const id = it.id || it.catalog_id || '';
          const name = it.name || it.item_name || '';
          const code = it.code || '';
          const price = it.price ?? it.unit_price ?? '';
          if (price !== '' && code !== '') {
            itemTokens.push(`${id}:${code}:${name}:${price}`);
          } else if (code !== '') {
            itemTokens.push(`${id}:${code}:${name}`);
          } else {
            itemTokens.push(`${id}:${name}`);
          }
        }
      }
      if (itemTokens.length > 0) {
        lines.push(itemTokens.join('|'));
      }
    }
    return lines.join('\n');
  }
}



if (typeof window !== 'undefined' && typeof customElements !== 'undefined') {
  if (!customElements.get('corehealth-scribe-studio')) {
    customElements.define('corehealth-scribe-studio', CoreHealthScribeElement);
  }
}

export { CoreHealthScribe, IFrameBridge, CoreHealthScribeElement };
export default CoreHealthScribe;

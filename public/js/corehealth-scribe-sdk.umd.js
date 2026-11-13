(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? factory(exports) :
  typeof define === 'function' && define.amd ? define(['exports'], factory) :
  (global = typeof globalThis !== 'undefined' ? globalThis : global || self, factory(global.CoreHealth = global.CoreHealth || {}));
})(this, (function (exports) { 'use strict';

/**
 * CoreHealth AI Scribe — iFrame Bridge
 * Manages sandboxed iframe creation and postMessage communication.
 */

var IFrameBridge = class IFrameBridge {
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

    const iframe = document.createElement('iframe');
    iframe.src = `${baseUrl}/scribe?${params.toString()}`;
    iframe.setAttribute('allow', 'microphone');
    iframe.setAttribute('title', 'CoreHealth AI Scribe Studio');
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-microphone');
    iframe.style.width = '100%';
    iframe.style.height = '620px';
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
 * Complete standalone custom element with shadow DOM style isolation.
 */

var CoreHealthScribeElement = class CoreHealthScribeElement extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.isRecording = false;
    this.recognition = null;
    this.transcript = '';
    this.options = {};
  }

  static get observedAttributes() {
    return ['base-url', 'sdk-token', 'patient-id', 'encounter-id', 'locale', 'theme', 'patient-summary'];
  }

  connectedCallback() {
    this.render();
    this.setupSpeech();
    this.bindEvents();
  }

  disconnectedCallback() {
    if (this.recognition && this.isRecording) {
      try { this.recognition.stop(); } catch (e) {}
    }
  }

  render() {
    const theme = this.getAttribute('theme') || 'light';
    const patientId = this.getAttribute('patient-id') || '';
    const encounterId = this.getAttribute('encounter-id') || '';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          color: #212529;
          background: #ffffff;
          border-radius: 8px;
          border: 1px solid #dee2e6;
          overflow: hidden;
          box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 16px;
          background: #f8f9fa;
          border-bottom: 1px solid #dee2e6;
        }
        .brand {
          font-weight: 700;
          font-size: 14px;
          display: flex;
          align-items: center;
          gap: 8px;
          color: #011b33;
        }
        .meta {
          font-size: 12px;
          color: #6c757d;
        }
        .body {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 16px;
          padding: 16px;
          min-height: 380px;
        }
        @media (max-width: 768px) {
          .body { grid-template-columns: 1fr; }
        }
        .pane {
          display: flex;
          flex-direction: column;
        }
        .pane-label {
          font-size: 11px;
          font-weight: 700;
          text-transform: uppercase;
          color: #495057;
          margin-bottom: 8px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        textarea {
          flex: 1;
          width: 100%;
          min-height: 260px;
          border: 1px solid #ced4da;
          border-radius: 6px;
          padding: 10px;
          font-size: 13px;
          line-height: 1.5;
          box-sizing: border-box;
          resize: vertical;
          font-family: inherit;
        }
        textarea:focus {
          outline: none;
          border-color: #80bdff;
          box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 12px 16px;
          background: #f8f9fa;
          border-top: 1px solid #dee2e6;
        }
        .btn {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          padding: 8px 16px;
          border-radius: 6px;
          font-size: 13px;
          font-weight: 600;
          border: 1px solid transparent;
          cursor: pointer;
          transition: background-color 0.15s ease-in-out;
        }
        .btn-record {
          background: #dc3545;
          color: #fff;
          border-radius: 20px;
        }
        .btn-record.recording {
          animation: pulse 1.5s infinite;
        }
        .btn-sign {
          background: #011b33;
          color: #fff;
        }
        .btn-sign:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
        .badge {
          display: inline-block;
          padding: 3px 8px;
          font-size: 11px;
          border-radius: 4px;
          background: #e9ecef;
          color: #495057;
        }
        @keyframes pulse {
          0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.6); }
          70% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
          100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
      </style>
      <div class="header">
        <div class="brand">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="22"/>
          </svg>
          <span>CoreHealth AI Scribe</span>
          ${patientId ? `<span class="meta">· Patient #${patientId} ${encounterId ? `(Encounter #${encounterId})` : ''}</span>` : ''}
        </div>
        <div>
          <span id="status-badge" class="badge">Ready</span>
        </div>
      </div>
      <div class="body">
        <div class="pane">
          <div class="pane-label">
            <span>1. Audio Dictation</span>
            <button type="button" id="btn-record" class="btn btn-record">Start Dictation</button>
          </div>
          <textarea id="transcript-input" placeholder="Dictate your clinical consultation or type notes here..."></textarea>
        </div>
        <div class="pane">
          <div class="pane-label">
            <span>2. Structured SOAP Note & Orders</span>
          </div>
          <textarea id="soap-input" placeholder="AI structured SOAP note and extracted prescription orders will appear here..."></textarea>
        </div>
      </div>
      <div class="footer">
        <span class="meta">openEHR & FHIR R4 Compliant · NDPA 2023 Statutory Privacy</span>
        <button type="button" id="btn-sign" class="btn btn-sign" disabled>Accept & Sign Note</button>
      </div>
    `;
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
        }
      };
    }
  }

  bindEvents() {
    const recordBtn = this.shadowRoot.getElementById('btn-record');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const statusBadge = this.shadowRoot.getElementById('status-badge');
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const soapInput = this.shadowRoot.getElementById('soap-input');

    if (recordBtn) {
      recordBtn.addEventListener('click', () => {
        if (!this.isRecording) {
          this.isRecording = true;
          recordBtn.classList.add('recording');
          recordBtn.innerText = 'Stop Dictation';
          statusBadge.innerText = '● Recording';
          statusBadge.style.background = '#dc3545';
          statusBadge.style.color = '#fff';
          if (this.recognition) {
            try { this.recognition.start(); } catch (e) {}
          }
          this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'recording' }));
        } else {
          this.isRecording = false;
          recordBtn.classList.remove('recording');
          recordBtn.innerText = 'Start Dictation';
          statusBadge.innerText = 'Structuring note...';
          statusBadge.style.background = '#ffc107';
          statusBadge.style.color = '#000';
          if (this.recognition) {
            try { this.recognition.stop(); } catch (e) {}
          }
          this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'processing' }));
          this.processDictation();
        }
      });
    }

    if (signBtn) {
      signBtn.addEventListener('click', () => this.handleSignOff());
    }
  }

  async processDictation() {
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const soapInput = this.shadowRoot.getElementById('soap-input');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const statusBadge = this.shadowRoot.getElementById('status-badge');

    const text = transcriptInput ? transcriptInput.value.trim() : '';
    if (!text) {
      statusBadge.innerText = 'Ready';
      statusBadge.style.background = '#e9ecef';
      statusBadge.style.color = '#495057';
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
          patient_summary: this.getAttribute('patient-summary') || ''
        })
      });

      const data = await res.json();
      if (data.success && data.structured_sections) {
        let formatted = '';
        data.structured_sections.forEach(sec => {
          formatted += `### ${sec.title}\n${sec.content}\n\n`;
        });
        if (soapInput) soapInput.value = formatted.trim();
        signBtn.disabled = false;
        statusBadge.innerText = 'Ready for Review';
        statusBadge.style.background = '#17a2b8';
        statusBadge.style.color = '#fff';
        this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'reviewing' }));
      }
    } catch (e) {
      console.error('Error structuring dictation', e);
      statusBadge.innerText = 'Error';
    }
  }

  async handleSignOff() {
    const soapInput = this.shadowRoot.getElementById('soap-input');
    const transcriptInput = this.shadowRoot.getElementById('transcript-input');
    const signBtn = this.shadowRoot.getElementById('btn-sign');
    const statusBadge = this.shadowRoot.getElementById('status-badge');

    const finalNote = soapInput ? soapInput.value.trim() : '';
    if (!finalNote) return;

    signBtn.disabled = true;
    signBtn.innerText = 'Signing...';

    const baseUrl = (this.getAttribute('base-url') || '').replace(/\/+$/, '');
    const token = this.getAttribute('sdk-token') || '';

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
          final_signed_note: finalNote,
          original_transcript: transcriptInput ? transcriptInput.value : ''
        })
      });
      const signData = await signRes.json();

      // 2. Fetch full compositions
      const compRes = await fetch(`${baseUrl}/api/sdk/scribe/session/${encodeURIComponent(token)}/compositions`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ cross_border: false })
      });
      const compData = await compRes.json();

      signBtn.innerText = '✓ Signed';
      statusBadge.innerText = '✓ Signed Off';
      statusBadge.style.background = '#28a745';
      statusBadge.style.color = '#fff';

      const payload = {
        audit_id: signData.audit_id,
        patient_id: this.getAttribute('patient-id'),
        encounter_id: this.getAttribute('encounter-id'),
        signed_at: signData.signed_at,
        final_signed_note: finalNote,
        original_transcript: transcriptInput ? transcriptInput.value : '',
        openehr_composition: compData.openehr_composition || null,
        fhir_bundle: compData.fhir_bundle || null
      };

      this.dispatchEvent(new CustomEvent('corehealth:signed-off', { detail: payload }));
      this.dispatchEvent(new CustomEvent('corehealth:state-change', { detail: 'signed-off' }));
    } catch (e) {
      console.error('Error signing scribe note', e);
      signBtn.disabled = false;
      signBtn.innerText = 'Accept & Sign Note';
    }
  }
}


/**
 * CoreHealth AI Scribe — Master Controller Class
 * Unified API supporting both Track A (Web Component) and Track B (iFrame Bridge).
 */




var CoreHealthScribe = class CoreHealthScribe {
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
      el.setAttribute('locale', this.options.locale);
      el.setAttribute('theme', this.options.theme);

      // Bind custom element event listeners
      if (typeof this.options.onSignedOff === 'function') {
        el.addEventListener('corehealth:signed-off', (e) => this.options.onSignedOff(e.detail));
      }
      if (typeof this.options.onStateChange === 'function') {
        el.addEventListener('corehealth:state-change', (e) => this.options.onStateChange(e.detail));
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
}



  if (typeof window !== 'undefined' && typeof customElements !== 'undefined') {
    if (!customElements.get('corehealth-scribe-studio')) {
      customElements.define('corehealth-scribe-studio', CoreHealthScribeElement);
    }
  }

  exports.CoreHealthScribe = CoreHealthScribe;
  exports.IFrameBridge = IFrameBridge;
  exports.CoreHealthScribeElement = CoreHealthScribeElement;
  exports.default = CoreHealthScribe;

  Object.defineProperty(exports, '__esModule', { value: true });
}));

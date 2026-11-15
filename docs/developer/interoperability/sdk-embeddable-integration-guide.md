# CoreHealth Scribe SDK — Embeddable Integration Guide

> **Who is this for?** Partner EMR engineering teams who want to embed the CoreHealth AI Scribe directly into their clinical encounter interfaces without page redirects or modal pop-outs.

---

## 1. Overview & Architecture

The CoreHealth AI Scribe SDK provides zero-disruption voice dictation and automated clinical structuring. Both tracks communicate with the same backend API and produce identical canonical **openEHR COMPOSITION** and **HL7 FHIR R4 Transaction Bundle** outputs.

| Track | Delivery Method | Best For | Security Boundary |
|---|---|---|---|
| **A — Web Component** | `<corehealth-scribe-studio>` Custom Element | Modern EMRs without strict CSP restrictions | Shadow DOM encapsulation |
| **B — Sandboxed iFrame** | Sandboxed `<iframe>` + `window.postMessage` bridge | Strict-CSP environments (`script-src 'self'`) | Complete origin isolation |

> [!IMPORTANT]
> **Zero Data Retention Guarantee & Ephemeral Token Model**:
> 1. **Zero Gateway Storage**: No patient data is stored or cached in the SDK Gateway beyond the active in-memory session. Session context terminates immediately upon completion of the HTTP request.
> 2. **Capture the Callback**: If your application fails to capture the `onSignedOff` callback or `corehealth:signed-off` event, the session is discarded; a fresh session must be initiated.
> 3. **PII Sanitization**: All clinical context (`patientSummary`) is automatically filtered per NDPA 2023 statutory privacy standards.

---

## 2. Clinical Encounter Workflow & Embedded Studio UI

The screenshot below illustrates the progressive clinical workflow rendered by `<corehealth-scribe-studio>` within an external host EMR:

![CoreHealth AI Scribe Web Component Clinical Workflow](/images/workbenches/scribe-sdk-verified-journey.png)

### Key Interface Controls & Clinical Phases:
- **Progressive Flow Stepper**: `1: Start Dictation or Load Sample` &rarr; `2: Process Note & Extract Orders` &rarr; `3: Review, Adjust & Sign`.
- **Dictation & Audio Cockpit**: `Start Dictation` with live waveform visualizer and elapsed timer (`00:00`), `Load Sample` button, and `Process Note` trigger. Supports Nigerian English (`en-NG`), Pidgin (`pcm-NG`), Hausa (`ha-NG`), Yoruba (`yo-NG`), and Igbo (`ig-NG`).
- **Structured Note Editor**: Formats dictated findings into canonical clinical sections (`SOAP`, `Clerking`, `POMR`, `Focus DAR`, `ANC`) with `Copy Note` and `Clear` controls.
- **Interactive Suggested Orders Deck**: Categorizes clinical intent into prescriptions, laboratory, imaging, and procedures with individual and bulk selection, inline dosage and instructions editing, and clean vanilla `+ Add Order` modal staging.
- **Instant Removal**: Trash-can SVG button on each order row with immediate removal and estimated tariff recalculation.

---

## 3. Installation & Distribution

The SDK is available through three distribution channels. All public deployments and CDN scripts link to `https://corehealth.corestream.ng`.

### Option A — CDN Script Tag (Quickest)
```html
<script src="https://corehealth.corestream.ng/js/corehealth-scribe-sdk.umd.js"></script>
```

### Option B — Git Submodule (Recommended for Vendor Trees)
```bash
git submodule add https://github.com/Corestream-Networks-and-Robotics-LTD/corehealth-scribe-js-sdk.git vendor/corehealth-scribe-sdk
git submodule update --init --recursive
cd vendor/corehealth-scribe-sdk && npm install && npm run build
```

### Option C — NPM Package
```bash
npm install @corehealth/scribe-sdk
```

---

## 4. Backend: Issue a Session Token & Environment Setup

Your backend securely holds your `client_id` and `client_secret` in an environment file (`.env`) and requests an ephemeral `SKT_` JWT (valid 1 hour) when a clinician opens an encounter. Never expose client credentials to client browsers.

### 4.1 Admin UI: Managing Partner Clients & Keys

Hospital SuperAdmins and Admins manage and audit all external partner EMR credentials directly in CoreHealth under **Hospital Configuration &rarr; AI Scribe & SDK** (`/hospital-config#sdk-settings`).

![Partner EMR SDK & AI Scribe Management](/images/workbenches/hospital-config-sdk-clients-monitoring.png)

From this administration panel, hospital administrators can:
- **Provision Partner Clients**: Click **+ Register Client** to configure external EMR identities, assign granular scopes (`*`, `scribe:session`, `scribe:process`, `scribe:sign`, `orders:dispatch`, `interop:compositions`), and enforce custom rate limits (requests per minute).
- **One-Click `.env` Snippet**: Click the **.env** button beside any client row to open a ready-to-copy configuration block pre-filled with the instance Base URL and Client ID.
- **Instant Secret Rotation**: Click the **Rotate Secret** button to mint a fresh cryptographic secret key without disrupting active token sessions.
- **LLM Token Tracking & Telemetry**: Monitor real-time prompt tokens, candidate tokens, total consumption, and inference latency across both internal Local Scribe hospital clinicians and external Partner EMR API clients via the auxiliary `llm_token_audits` telemetry engine.
- **Session Revocation & Real-Time Auditing**: Monitor live `SdkTokenAudit` bearer sessions with immediate revocation controls, alongside historical `AiScribeAudit` sign-offs.

![Real-Time LLM Token Usage Telemetry](/images/workbenches/hospital-config-llm-token-telemetry.png)

![New Client Secret Alert & 1-Click Copy](/images/workbenches/hospital-config-sdk-new-client-secret.png)

![Partner EMR .env Snippet Modal](/images/workbenches/hospital-config-sdk-env-snippet.png)

### 4.2 Environment Configuration (`.env.example`)

Both JavaScript and Python SDK distributions include standard `.env.example` templates:

```ini
# CoreHealth Cloud or Edge Host (without trailing slash)
CH_BASE_URL=https://corehealth.corestream.ng

# Client Credentials (obtained in CoreHealth Admin -> Hospital Configuration -> AI Scribe & SDK)
CH_CLIENT_ID=partner-emr-dev
CH_CLIENT_SECRET=partner_dev_secret_2026

# Local Backend Proxy Port
PORT=3001

# Default Consultation Parameters
CH_DEFAULT_LOCALE=en-NG
CH_DEFAULT_PATIENT_ID=492
CH_DEFAULT_ENCOUNTER_ID=1017
CH_DEFAULT_CLINICIAN_ID=38
```

Always initialize your local environment from the template:

```bash
cp .env.example .env
```

### 4.3 Python Virtual Environment (`venv`) Setup

When integrating with Python backends (FastAPI, Django, Flask), isolate dependencies in a dedicated virtual environment:

```bash
# 1. Create a clean virtual environment using Python 3.10+
python3 -m venv venv

# 2. Activate the virtual environment
source venv/bin/activate  # On Windows: .\venv\Scripts\Activate.ps1

# 3. Upgrade pip and install dependencies
pip install --upgrade pip
pip install "corehealth-scribe-sdk[fastapi]" python-dotenv
```

### 4.4 Ephemeral Token Minting Endpoint

**Request (`POST /api/sdk/auth/token`):**
```json
{
  "client_id":     "partner-emr-dev",
  "client_secret": "YOUR_CLIENT_SECRET",
  "patient_id":    492,
  "encounter_id":  1017,
  "clinician_id":  38,
  "scope":         "scribe:process scribe:sign interop:compositions"
}
```

**Response (200 OK):**
```json
{
  "sdk_token":  "SKT_eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope":      "scribe:process scribe:sign interop:compositions",
  "patient_id": 492,
  "encounter_id": 1017
}
```

---

## 5. Frontend Implementation: Track A (Web Component)

Mount the `<corehealth-scribe-studio>` custom element within your DOM:

```html
<div id="scribe-container"></div>

<script type="module">
import { CoreHealthScribe } from 'https://corehealth.corestream.ng/js/corehealth-scribe-sdk.esm.js';

// 1. Fetch ephemeral session token from your backend proxy
const { sdk_token } = await fetch('/my-backend/ch-scribe-token?patient_id=492&encounter_id=1017')
  .then(r => r.json());

// 2. Optional: Compress local formulary using CSCP for grounding
const customCatalog = CoreHealthScribe.compressCatalog([
  { id: 'rx-201', type: 'medication', name: 'Paracetamol 500mg Tablet' },
  { id: 'rx-203', type: 'medication', name: 'Artemether-Lumefantrine 80/480mg' },
  { id: 'lab-101', type: 'laboratory', name: 'Full Blood Count (FBC)' }
]);

// 3. Initialize and mount the scribe
const scribe = new CoreHealthScribe({
  baseUrl:        'https://corehealth.corestream.ng',
  sdkToken:       sdk_token,
  track:          'web-component',
  mountTarget:    '#scribe-container',
  locale:         'en-NG',
  role:           'doctor',    // 'doctor' | 'nurse' | 'maternity'
  taxonomy:       'soap',      // 'soap' | 'traditional' | 'pomr'
  customCatalog:  customCatalog,
  patientSummary: 'Known asthmatic, allergic to Penicillin. Active meds: Salbutamol inhaler.',
  onOrdersUpdated: (orders) => console.log('Staged orders updated:', orders),
  onSignedOff:     async (payload) => {
    // Forward signed clinical note and approved orders to your backend
    await fetch('/my-backend/encounters/1017/scribe-signoff', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
  },
  onError: (err) => console.error('Scribe error:', err)
});

await scribe.mount();
</script>
```

---

## 6. Compressed Scribe Catalog Protocol (CSCP)

To ground clinical extractions against your EMR's formulary and tariffs without exceeding model context windows, the SDK uses the **Compressed Scribe Catalog Protocol (CSCP)**:
- Items are encoded as compact pipe-delimited records: `#CAT:id|type|name\n...`
- Reduces payload size by **10x to 15x** compared to verbose JSON (~3 tokens per item).
- Supported categories: `medication`, `laboratory`, `imaging`, `procedure`, `nursing`, `consultation`.

```javascript
// JavaScript compression helper
const cscpString = CoreHealthScribe.compressCatalog(partnerItems);
```

```python
# Python compression helper
from corehealth_sdk import CoreHealthClient
cscp_string = CoreHealthClient.compress_catalog(partner_items, method="dsl")
```

---

## 7. Suggested Orders & Prescriptions Deck

The AI Scribe classifies clinical intent from speech and extracts structured orders into an interactive staging deck:
1. **Multi-Category Staging**: Automatically sorts orders into medications, labs, radiology, and procedures.
2. **Inline Adjustments**: Clinicians can check/uncheck items, edit dosage/frequency/instructions, and toggle individual items.
3. **Safety & Allergy Alerts**: Cross-references medications against active patient allergies in real time.
4. **Ad-Hoc Additions**: Clinicians can click `+ Add Order` to append custom items.
5. **Approved Orders Dispatch**: Only selected items are submitted and mapped to openEHR `INSTRUCTION` archetypes and FHIR `MedicationRequest`/`ServiceRequest` entries.

---

## 8. Dynamic Medical Taxonomies

Clinicians can switch documentation structures dynamically without losing notes:
- **Doctor / Physician**: `soap` (SOAP), `traditional` (Clerking), `pomr` (Problem-Oriented).
- **Nursing**: `dar` (Focus DAR), `sbar` (SBAR), `soapie` (SOAPIE).
- **Maternity & Obstetrics**: `anc` (Antenatal), `labor` (Intrapartum), `pnc` (Postnatal).

---

## 9. Downstream Handover & Standards Payload

When `onSignedOff` fires, the SDK delivers a standards-compliant payload:

```json
{
  "final_signed_note": "### Presenting Complaint (PC)\nPatient presents with fever...",
  "approved_orders": [
    {
      "id": "ord_1",
      "order_type": "PRODUCT",
      "catalog_id": "rx-201",
      "item_name": "Paracetamol 500mg Tablet",
      "dosage": "500mg",
      "frequency": "TDS (8-hourly)",
      "duration": "3 days",
      "route": "oral",
      "instructions": "Take as directed by clinician"
    }
  ],
  "openehr_composition": {
    "archetypeId": "openEHR-EHR-COMPOSITION.encounter.v1",
    "templateId": "corehealth_encounter_summary",
    "content": [
      { "archetypeId": "openEHR-EHR-INSTRUCTION.medication_order.v3", "activities": [...] }
    ]
  },
  "fhir_bundle": {
    "resourceType": "Bundle",
    "type": "transaction",
    "entry": [
      { "resource": { "resourceType": "Encounter", "status": "finished" } },
      { "resource": { "resourceType": "MedicationRequest", "status": "active" } }
    ]
  }
}
```

Persist `openehr_composition` to your openEHR CDR (`POST /ehr/{ehr_id}/composition`) and `fhir_bundle` to your FHIR repository (`POST /fhir`).

---

## 10. Support & Resources

- **Public Production CDN**: `https://corehealth.corestream.ng/js/corehealth-scribe-sdk.umd.js`
- **Technical Inquiries**: Contact CoreHealth Engineering at `hello@corestream.ng`.

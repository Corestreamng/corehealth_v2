# CoreHealth Scribe SDK — Embeddable Integration Guide

> **Who is this for?** Partner EMR engineering teams who want to embed the CoreHealth AI Scribe  
> into their existing clinical interface without redirecting users away from their screen.

---

## Overview

The CoreHealth AI Scribe SDK provides two integration tracks — both use the same backend API and produce the same openEHR + FHIR output. Only the frontend mounting method differs.

| Track | Method | Best For |
|---|---|---|
| **A — Web Component** | `<corehealth-scribe-studio>` Shadow DOM element | Most EMRs (no CSP restrictions) |
| **B — iFrame + postMessage** | Sandboxed iFrame; bidirectional bridge | Strict-CSP environments |

> [!IMPORTANT]
> **Backend-mediated token authentication is mandatory.** Your backend holds the `client_id` + `client_secret`. Your JS frontend receives only a short-lived `SKT_` JWT (1 hr). Never put long-lived secrets in browser code.
>
> **Data Retention & Ephemeral Session Lifecycle Guarantee**:
> 1. **Zero Data Retention in SDK Gateway**: No patient data is stored or cached in the CoreHealth SDK service beyond the active in-memory session. Session context terminates immediately upon completion of the HTTP request.
> 2. **Capture the Callback**: If your application fails to capture the `onSignedOff` JavaScript callback or `corehealth:signed-off` postMessage event, the session data is not held in any persistent staging table, and your clinician will need to initiate a fresh session.
> 3. **PII-Stripped Context Injection (`patient_summary`)**: Your application can optionally pass a de-identified, PII-stripped summary (`patient_summary` — chronic conditions, active medications, allergies) to guide the scribe. All context is automatically sanitized per NDPA 2023 statutory standards.

---

## Prerequisites

- CoreHealth SDK credentials (`client_id` + `client_secret`) from your CoreHealth account manager
- A backend service capable of making server-to-server HTTP calls (Node.js, Python, PHP, etc.)
- The SDK submodule or CDN script (see §1 below)

---

## 1. Install the SDK

### Option A — Git Submodule (recommended)
```bash
git submodule add https://github.com/corehealth-ng/scribe-sdk.git vendor/corehealth-sdk
git submodule update --init --recursive
cd vendor/corehealth-sdk && npm install && npm run build
```

### Option B — CDN `<script>` tag
```html
<script src="https://corehealth.corestream.ng/js/corehealth-scribe-sdk.umd.js"></script>
```

### Option C — npm
```bash
npm install @corehealth/scribe-sdk
```

---

## 2. Backend: Issue a Session Token

Your backend exchanges credentials for a short-lived `SKT_` token every time a clinician opens an encounter.

**Request**
```
POST /api/sdk/auth/token
Content-Type: application/json
```
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

**Response — 200 OK**
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

**Next step:** Return `sdk_token` to your frontend. Never log or store it beyond the session.

---

## 3. Frontend: Track A — Web Component

```html
<!-- In your encounter template -->
<div id="scribe-container"></div>

<script type="module">
import { CoreHealthScribe } from '/vendor/corehealth-sdk/dist/corehealth-scribe-sdk.esm.js';

const { sdk_token } = await fetch(`/my-backend/ch-scribe-token?patient_id=492&encounter_id=1017`)
  .then(r => r.json());

// Optional: Compress your EMR's products and services for clinical grounding
const customCatalog = CoreHealthScribe.compressCatalog([
  { id: 101, type: 'medication', name: 'Artemether-Lumefantrine 80/480mg' },
  { id: 102, type: 'medication', name: 'Paracetamol 500mg Tab' },
  { id: 201, type: 'laboratory', name: 'Malaria Parasite (MP) Giemsa' },
  { id: 301, type: 'imaging',    name: 'Chest X-Ray AP/Lat' }
]);

const scribe = new CoreHealthScribe({
  baseUrl:       'https://corehealth.corestream.ng',
  sdkToken:      sdk_token,
  track:         'web-component',
  mountTarget:   '#scribe-container',
  locale:        'en-NG',
  role:          'doctor',   // 'doctor' | 'nurse' | 'maternity'
  taxonomy:      'soap',     // 'soap' | 'traditional' | 'pomr' | 'dar' | 'sbar' | 'soapie' | 'anc'
  customCatalog: customCatalog,
  patientSummary: 'Hypertensive, allergic to Penicillin. Active meds: Amlodipine 5mg OD.',
  onOrdersUpdated: (orders) => {
    console.log('Suggested orders deck updated:', orders);
  },
  onSignedOff: (payload) => handleSignOff(payload),
  onError:     (err)     => console.error('Scribe error:', err)
});

await scribe.mount();

async function handleSignOff(payload) {
  // payload.transcript, payload.soap_note, payload.approved_orders,
  // payload.openehr_composition, payload.fhir_bundle
  await fetch('/my-backend/encounters/1017/scribe-signoff', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
}
</script>
```

**Next step:** Clinician dictates, reviews suggested orders deck, adjusts dosages or checkboxes, and clicks Accept & Sign → `onSignedOff` fires with the full payload.

---

## 3.1 Compressed Scribe Catalog Protocol (CSCP)

In clinical settings, hospital formularies and diagnostic service tariffs can encompass thousands of items. Passing verbose JSON structures directly to AI models quickly consumes tens of thousands of tokens, inflates latency, and risks exceeding model context limits.

To solve this, CoreHealth uses the **Compressed Scribe Catalog Protocol (CSCP)**:
- Items are represented using a compact pipe-delimited syntax: `#CAT:id|type|name\n...`
- Reduces token payload by **10x to 15x** compared to verbose JSON (averaging ~3 tokens per catalog entry).
- Supported catalog types: `medication`, `laboratory`, `imaging`, `procedure`, `nursing`, `consultation`.

### Compressing Catalogs in JavaScript:
```javascript
import { CoreHealthScribe } from '@corehealth/scribe-sdk';

const partnerCatalog = [
  { id: 101, type: 'medication', name: 'Artemether-Lumefantrine 80/480mg' },
  { id: 201, type: 'laboratory', name: 'Malaria Rapid Diagnostic Test (mRDT)' }
];

// Returns: "#CAT:101|medication|Artemether-Lumefantrine 80/480mg\n#CAT:201|laboratory|Malaria Rapid Diagnostic Test (mRDT)"
const compressedCscp = CoreHealthScribe.compressCatalog(partnerCatalog);
```

### Compressing Catalogs in Python:
```python
from corehealth_sdk import CoreHealthClient

items = [
    {"id": 101, "type": "medication", "name": "Artemether-Lumefantrine 80/480mg"},
    {"id": 201, "type": "laboratory", "name": "Malaria Rapid Diagnostic Test (mRDT)"}
]

# Gzip-base64 or DSL string for instant injection
cscp_dsl = CoreHealthClient.compress_catalog(items, method="dsl")
```

---

## 3.2 Suggested Orders & Prescriptions Deck

The AI Scribe automatically extracts clinical intentions from voice dictation and categorizes them into an interactive **Suggested Orders Deck**:
1. **Multi-Category Staging**: Automatically classifies orders into Medications, Laboratory investigations, Imaging requests, Nursing interventions, Procedures, and Consultations.
2. **Inline Adjustments**: Clinicians can check/uncheck orders, modify dosage, frequency, duration, administration route, clinical indication, and instructions inline.
3. **Allergy Safety Checks**: Prescribed medications are automatically cross-referenced against the patient's active allergies (from `patientSummary` or EMR records), displaying prominent safety warnings.
4. **On-the-Fly Additions**: Clinicians can click `+ Add Order` to manually add new investigations or prescriptions to the staged deck before sign-off.
5. **Approved Orders Dispatch**: Only selected orders are signed off and mapped into openEHR `INSTRUCTION` archetypes and FHIR `MedicationRequest`/`ServiceRequest` resources.

---

## 3.3 Dynamic Medical Taxonomies & Lossless Switching

The SDK provides clinical taxonomy switching suited to different medical specialties:
- **Doctor / Physician**:
  - `soap`: Subjective, Objective, Assessment, Plan.
  - `traditional`: History of Presenting Complaint, Systematic Review, Physical Examination, Working Diagnosis, Management Plan.
  - `pomr`: Problem-Oriented Medical Record (Subjective, Objective, Assessment, Plan per problem).
- **Nursing**:
  - `dar`: Focus DAR (Data, Action, Response).
  - `sbar`: Situation, Background, Assessment, Recommendation.
  - `soapie`: Subjective, Objective, Assessment, Plan, Intervention, Evaluation.
- **Maternity & Obstetrics**:
  - `anc`: Antenatal Care (Obstetric History, Fundal Height, Fetal Heart Rate, Presentation, Plan).
  - `labor`: Intrapartum Monitoring (Cervical Dilatation, Contractions, Fetal Descent).
  - `pnc`: Postnatal Care (Maternal Vitals, Lochia, Involution, Neonatal Vitals).

Clinicians can toggle between formats in real-time. When switching taxonomies, the note is converted losslessly via the `/api/sdk/scribe/convert-format` API while preserving all observations and measurements.

---

## 3.4 Draft Persistence & In-Session Recovery

To safeguard against accidental browser reloads or network drops:
- Consultations and suggested orders auto-save continuously to encrypted `localStorage` (`corehealth_scribe_draft_*`).
- If a reload occurs, a restoration banner appears allowing the clinician to restore their note and order deck with a single click.
- Upon successful sign-off, the local draft is automatically cleared.

---

## 4. Frontend: Track B — iFrame + postMessage

Use this if your app has a strict Content Security Policy that blocks custom elements.

```html
<div id="scribe-mount"></div>

<script>
(async () => {
  const { sdk_token } = await fetch('/my-backend/ch-scribe-token?patient_id=492&encounter_id=1017')
    .then(r => r.json());

  const frame = document.createElement('iframe');
  frame.src = `https://corehealth.corestream.ng/scribe?token=${sdk_token}&locale=en-NG&theme=light`;
  frame.setAttribute('allow', 'microphone');
  frame.style.cssText = 'width:100%;height:600px;border:none;border-radius:8px;';
  document.getElementById('scribe-mount').appendChild(frame);

  // Listen for sign-off
  window.addEventListener('message', async (event) => {
    if (event.origin !== 'https://corehealth.corestream.ng') return; // ALWAYS verify origin
    if (event.data.type !== 'corehealth:signed-off') return;

    await fetch('/my-backend/encounters/1017/scribe-signoff', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(event.data.payload)
    });

    document.getElementById('scribe-mount').innerHTML = '';
  });
})();
</script>
```

> [!IMPORTANT]
> Always check `event.origin` before processing postMessage events. Use your exact CoreHealth SDK domain — never `'*'`.

**postMessage event types emitted by the SDK:**

| `event.data.type` | When | `event.data.payload` |
|---|---|---|
| `corehealth:ready` | iFrame mounted, token validated | `{ patient_id, encounter_id }` |
| `corehealth:recording-started` | Mic active | — |
| `corehealth:processing` | NLP running | — |
| `corehealth:signed-off` | Clinician accepted | Full payload (see §6) |
| `corehealth:error` | Any failure | `{ code, message }` |

**Next step:** Your backend receives the payload and calls CoreHealth to get the enriched compositions.

---

## 5. Backend: Fetch openEHR + FHIR Compositions

After sign-off, call this endpoint to get the full standards-compliant payload. This is optional if the sign-off event already returned the compositions inline — use it for server-side verification or enrichment.

**Request**
```
POST /api/sdk/scribe/session/{sdk_token}/compositions
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/json
```
```json
{
  "cross_border": false
}
```

**Response — 200 OK**
```json
{
  "openehr_composition": {
    "archetypeId": "openEHR-EHR-COMPOSITION.encounter.v1",
    "templateId": "corehealth_encounter_summary",
    "composer": { "name": "Dr. Chukwuemeka Obi" },
    "content": [
      {
        "archetypeId": "openEHR-EHR-OBSERVATION.blood_pressure.v2",
        "data": {
          "systolic":  { "magnitude": 128, "units": "mm[Hg]" },
          "diastolic": { "magnitude": 82,  "units": "mm[Hg]" }
        }
      },
      {
        "archetypeId": "openEHR-EHR-INSTRUCTION.medication_order.v3",
        "activities": [
          { "medicine": "Metformin 500mg", "dose": "1 tab", "route": "oral", "frequency": "BD" }
        ]
      }
    ]
  },
  "fhir_bundle": {
    "resourceType": "Bundle",
    "type": "transaction",
    "entry": [
      {
        "resource": {
          "resourceType": "Encounter",
          "status": "finished",
          "subject": { "reference": "Patient/492" }
        }
      },
      {
        "resource": {
          "resourceType": "MedicationRequest",
          "status": "active",
          "intent": "order",
          "medicationCodeableConcept": {
            "coding": [{ "system": "http://www.whocc.no/atc", "code": "A10BA02", "display": "Metformin" }]
          },
          "subject": { "reference": "Patient/492" },
          "dosageInstruction": [{ "text": "1 tab BD oral" }]
        }
      }
    ]
  },
  "transcript_summary": "Patient presented with mild headache and elevated blood pressure...",
  "signed_at": "2026-11-13T15:42:00+01:00"
}
```

**Next step:** Persist `openehr_composition` to your CDR and `fhir_bundle` to your FHIR server. The SDK's work is done.

---

## 6. Full Signed-Off Payload Reference

```json
{
  "transcript":          "Full raw transcript text from the dictation session",
  "soap_note": {
    "S": "Patient complains of headache for 2 days...",
    "O": "BP 128/82, Temp 36.8°C, SpO2 98%",
    "A": "Hypertension stage 1, well-controlled on current regimen",
    "P": "Continue Amlodipine 5mg OD, review in 4 weeks, check FBC"
  },
  "approved_orders": [
    {
      "type": "medication",
      "name": "Amlodipine 5mg Tab",
      "matched_id": 105,
      "matched_tariff": 1200.0,
      "dosage": "5mg",
      "frequency": "OD (Once daily)",
      "duration": "28 days",
      "route": "Oral",
      "instructions": "Take each morning"
    },
    {
      "type": "laboratory",
      "name": "Full Blood Count (FBC)",
      "matched_id": 204,
      "matched_tariff": 4500.0,
      "instructions": "Routine EDTA sample"
    }
  ],
  "allergy_alerts": [],
  "taxonomy":             "soap",
  "role":                 "doctor",
  "openehr_composition": { "...": "see §5" },
  "fhir_bundle":          { "...": "see §5" },
  "sdk_token":            "SKT_eyJ...",
  "patient_id":           492,
  "encounter_id":         1017,
  "signed_at":            "2026-11-13T15:42:00+01:00"
}
```

---

## 7. Reference Implementation

The SDK submodule ships a complete working vanilla JS demo:

```
vendor/corehealth-sdk/examples/vanilla-integration/
├── index.html   ← demonstrates both Track A and Track B side-by-side
├── server.js    ← minimal Node.js backend proxy
└── README.md    ← run instructions
```

Run it in 3 commands:
```bash
cd vendor/corehealth-sdk/examples/vanilla-integration
cp .env.example .env   # add your credentials
node server.js &
npx serve . -p 5173
```

Open `http://localhost:5173` — the full scribe UI appears, backed by your local CoreHealth instance.

---

## 8. Troubleshooting

| Problem | Likely Cause | Fix |
|---|---|---|
| `401 Unauthenticated` on token endpoint | Wrong `client_id` or `client_secret` | Verify credentials in CoreHealth SDK client dashboard |
| `403 Forbidden — scope not allowed` | `client_id` not granted requested scope | Ask CoreHealth admin to extend allowed scopes |
| `Mic permission denied` | Browser blocks microphone | Ensure site is served over HTTPS; user must grant mic permission |
| postMessage events not received | Wrong `event.origin` check | Use exact SDK domain: `https://corehealth.corestream.ng` |
| `422 patient_id not found` | Patient not in CoreHealth system | Use FHIR Patient Create first to register patient |


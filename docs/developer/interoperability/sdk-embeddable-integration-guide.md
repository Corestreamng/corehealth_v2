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
<script src="https://sdk.corehealth.ng/js/corehealth-sdk.min.js"></script>
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
import { CoreHealthScribe } from '/vendor/corehealth-sdk/dist/corehealth-sdk.esm.js';

const { sdk_token } = await fetch(`/my-backend/ch-scribe-token?patient_id=492&encounter_id=1017`)
  .then(r => r.json());

const scribe = new CoreHealthScribe({
  baseUrl:     'https://your-corehealth-instance.ng',
  sdkToken:    sdk_token,
  track:       'web-component',
  mountTarget: '#scribe-container',
  locale:      'en-NG',
  theme:       'light',
  onSignedOff: (payload) => handleSignOff(payload),
  onError:     (err)     => console.error('Scribe error:', err)
});

await scribe.mount();

async function handleSignOff(payload) {
  // payload.transcript, payload.soap_note, payload.orders,
  // payload.openehr_composition, payload.fhir_bundle
  await fetch('/my-backend/encounters/1017/scribe-signoff', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
}
</script>
```

**Next step:** Clinician dictates, reviews, and clicks Accept & Sign → `onSignedOff` fires with the full payload.

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
  frame.src = `https://sdk.corehealth.ng/scribe?token=${sdk_token}&locale=en-NG&theme=light`;
  frame.setAttribute('allow', 'microphone');
  frame.style.cssText = 'width:100%;height:600px;border:none;border-radius:8px;';
  document.getElementById('scribe-mount').appendChild(frame);

  // Listen for sign-off
  window.addEventListener('message', async (event) => {
    if (event.origin !== 'https://sdk.corehealth.ng') return; // ALWAYS verify origin
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
  "orders": [
    { "type": "prescription", "drug": "Amlodipine 5mg",    "frequency": "OD",  "route": "oral" },
    { "type": "lab",          "test": "Full Blood Count",  "loinc": "58410-2" },
    { "type": "followup",     "days": 28,                   "note": "Review BP" }
  ],
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
| postMessage events not received | Wrong `event.origin` check | Use exact SDK domain: `https://sdk.corehealth.ng` |
| `422 patient_id not found` | Patient not in CoreHealth system | Use FHIR Patient Create first to register patient |


# OpenEMR Compatibility & C-CDA XML Endpoints Reference

> **Standard**: OpenEMR Standard REST API (v7.0+) & HL7 CDA R2 (C-CDA Continuity of Care Document)  
> **Base Path**: `/apis/default`  
> **Supported Formats**: JSON (`application/json`) & XML (`application/xml`)  

---

## 0. Authentication & Authorization Architecture

The OpenEMR compatibility layer allows existing OpenEMR integrations, clinical portals, and health information exchanges to interface with CoreHealth v2:

- **Bearer Token & Header Format**:
  ```http
  Authorization: Bearer <sanctum_or_oauth_token>
  Accept: application/json
  ```
- **Automated Pipeline Auth**: System-to-system extractors can authenticate using SHA-256 Basic Auth (`auth.basic.sha256`), mirroring the CoreHMS and WeCare integration contract patterns.
- **Session Cookie Forwarding**: Web clients wrapping OpenEMR interfaces pass standard session cookies under the `api` middleware group.
- **C-CDA Document Access Governance**: The `GET /apis/default/api/ccda/{patientId}` endpoint triggers both Spatie role verification (`DOCTOR`, `RECEPTIONIST`, `RECORD_OFFICER`, `ADMIN`) and NDPA Section 30 sensitive health record consent verification before releasing continuity-of-care XML payloads.

---

## 1. OpenEMR Direct Table Schema APIs

### `GET /apis/default/api/patient`
Returns patient demographics matching OpenEMR's canonical `patient_data` database table schema.

- **Query Parameters**:
  - `limit` *(int, optional)*: Records per page (default `20`, max `50`).
- **Response `200 OK` (`application/json`)**:
  ```json
  {
    "success": true,
    "total": 95974,
    "data": [
      {
        "pid": "105",
        "pubpid": "PAT-99120",
        "title": "Mr/Ms",
        "fname": "Chinedu",
        "mname": "Emeka",
        "lname": "Okonkwo",
        "DOB": "1990-05-15",
        "sex": "Male",
        "phone_cell": "08031234567",
        "email": "chinedu@example.com",
        "street": "14 Hospital Road",
        "city": "Lagos",
        "state": "Lagos",
        "postal_code": "100001",
        "country_code": "NG"
      }
    ]
  }
  ```

### `GET /apis/default/api/encounter`
Returns clinical encounter records formatted to match OpenEMR's `form_encounter` table.

- **Query Parameters**: `limit` *(int, optional)*
- **Response `200 OK` (`application/json`)**:
  ```json
  {
    "success": true,
    "total": 1420,
    "data": [
      {
        "encounter": "412",
        "pid": "105",
        "date": "2026-11-10 09:15:00",
        "reason": "Executive Cardiovascular Review",
        "facility": "Main Hospital Facility",
        "provider_id": "18"
      }
    ]
  }
  ```

### `GET /apis/default/api/vital_signs`
Returns recorded vital signs formatted to match OpenEMR's `form_vitals` table.

- **Query Parameters**: `limit` *(int, optional)*
- **Response `200 OK` (`application/json`)**:
  ```json
  {
    "success": true,
    "total": 850,
    "data": [
      {
        "id": "319",
        "pid": "105",
        "date": "2026-11-10 09:05:00",
        "bps": "120",
        "bpd": "80",
        "pulse": "75",
        "respiration": "18",
        "temperature": "36.5",
        "temp_method": "Axillary",
        "weight": "72.5",
        "height": "178",
        "BMI": "22.9",
        "oxygen_saturation": "98"
      }
    ]
  }
  ```

---

## 2. HL7 C-CDA Continuity of Care Document (CCD) Export

### `GET /apis/default/api/ccda/{patient_id}`
Dynamically generates an authentic, schema-valid **HL7 CDA R2 Continuity of Care Document (CCD)** XML file for cross-system transfers and patient discharge summaries.

- **Path Parameters**: `patient_id` *(int, required)*: CoreHealth patient numeric ID.
- **Response Headers**:
  - `Content-Type: application/xml; charset=UTF-8`
  - `Content-Disposition: inline; filename="ccda_patient_{patient_id}.xml"`
- **Response `200 OK` (C-CDA XML Example)**:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <ClinicalDocument xmlns="urn:hl7-org:v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" classCode="DOCCLIN" moodCode="EVN">
    <realmCode code="US"/>
    <typeId root="2.16.840.1.113883.1.3" extension="POCD_HD000040"/>
    <templateId root="2.16.840.1.113883.10.20.22.1.1"/>
    <templateId root="2.16.840.1.113883.10.20.22.1.2"/>
    <id root="2.16.840.1.113883.19.5.99999.1" extension="CCD-105-1794351276"/>
    <code code="34133-9" displayName="Summarization of Episode Note" codeSystem="2.16.840.1.113883.6.1" codeSystemName="LOINC"/>
    <title>CoreHealth v2 Continuity of Care Document (CCD)</title>
    <effectiveTime value="20261110235436+0100"/>
    <confidentialityCode code="N" codeSystem="2.16.840.1.113883.5.25"/>
    <languageCode code="en-US"/>
    <recordTarget>
      <patientRole>
        <id root="2.16.840.1.113883.19.5" extension="PAT-99120"/>
        <patient>
          <name>
            <family>Okonkwo</family>
            <given>Chinedu</given>
            <given>Emeka</given>
          </name>
          <administrativeGenderCode code="M" codeSystem="2.16.840.1.113883.5.1"/>
          <birthTime value="19900515"/>
        </patient>
      </patientRole>
    </recordTarget>
    <author>
      <time value="20261110235436+0100"/>
      <assignedAuthor>
        <id root="2.16.840.1.113883.19.5" extension="COREHEALTH-SYS"/>
        <assignedPerson>
          <name><prefix>Dr.</prefix><family>Physician</family><given>Hospital</given></name>
        </assignedPerson>
      </assignedAuthor>
    </author>
    <custodian>
      <assignedCustodian>
        <representedCustodianOrganization>
          <id root="2.16.840.1.113883.19.5" extension="HOSPITAL-1"/>
          <name>CoreHealth Teaching Hospital</name>
        </representedCustodianOrganization>
      </assignedCustodian>
    </custodian>
    <component>
      <structuredBody>
        <!-- Vital Signs Section -->
        <component>
          <section>
            <templateId root="2.16.840.1.113883.10.20.22.2.4.1"/>
            <code code="8716-3" codeSystem="2.16.840.1.113883.6.1" displayName="VITAL SIGNS"/>
            <title>Vital Signs</title>
            <text>
              <table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>
                <tr><td>Blood Pressure</td><td>120/80 mmHg</td></tr>
                <tr><td>Heart Rate</td><td>75 bpm</td></tr>
                <tr><td>Temperature</td><td>36.5 °C</td></tr>
              </tbody></table>
            </text>
          </section>
        </component>
        <!-- Active Problems & Diagnoses Section -->
        <component>
          <section>
            <templateId root="2.16.840.1.113883.10.20.22.2.5.1"/>
            <code code="11450-4" codeSystem="2.16.840.1.113883.6.1" displayName="PROBLEM LIST"/>
            <title>Active Problems &amp; Diagnoses</title>
            <text>
              <table><thead><tr><th>Condition / Diagnosis</th><th>Recorded Date</th></tr></thead><tbody>
                <tr><td>Essential (Primary) Hypertension</td><td>2026-11-10</td></tr>
              </tbody></table>
            </text>
          </section>
        </component>
        <!-- Medications Section -->
        <component>
          <section>
            <templateId root="2.16.840.1.113883.10.20.22.2.1.1"/>
            <code code="10160-0" codeSystem="2.16.840.1.113883.6.1" displayName="HISTORY OF MEDICATION USE"/>
            <title>Medications</title>
            <text>
              <table><thead><tr><th>Medication</th><th>Dosage Instructions</th><th>Status</th></tr></thead><tbody>
                <tr><td>Amlodipine 5mg Tablets</td><td>5mg OD Mane for 30 days</td><td>Dispensed</td></tr>
              </tbody></table>
            </text>
          </section>
        </component>
        <!-- Diagnostic Laboratory Results Section -->
        <component>
          <section>
            <templateId root="2.16.840.1.113883.10.20.22.2.3.1"/>
            <code code="30954-2" codeSystem="2.16.840.1.113883.6.1" displayName="STUDIES SUMMARY"/>
            <title>Diagnostic Investigations &amp; Laboratory Results</title>
            <text>
              <table><thead><tr><th>Test Name</th><th>Result</th><th>Status</th></tr></thead><tbody>
                <tr><td>Full Blood Count</td><td>Normal indices</td><td>Approved</td></tr>
              </tbody></table>
            </text>
          </section>
        </component>
      </structuredBody>
    </component>
  </ClinicalDocument>
  ```

---

## 3. OpenEMR FHIR Compatibility Facade

### `GET /apis/default/fhir/Patient`
Provides an alias matching OpenEMR's standard FHIR URL path `/apis/default/fhir/Patient`, proxying directly to CoreHealth's FHIR R4 Patient search controller.

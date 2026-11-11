# openEHR ITS-REST Endpoints Detailed Reference

> **Standard**: openEHR REST API (Release 1.1.0+)  
> **Base Path**: `/api/openehr/v1`  
> **Content-Type**: `application/json; charset=UTF-8`  
> **Architectural Pattern**: Archetype Serialization & Inbound Composition Extraction Proxy  

---

## 0. Authentication & Authorization Architecture

All openEHR ITS-REST endpoints operate within CoreHealth's unified authentication context:

- **Bearer Token & Session Identification**: Requests require an authenticated API Bearer token:
  ```http
  Authorization: Bearer <sanctum_or_oauth_token>
  Accept: application/json
  ```
- **openEHR Session Tokens**: Integration clients passing `Ehr-Session: <token>` headers are resolved to authenticated system identities.
- **Role Guards**:
  - `EHR` creation & queries: Accessible by `RECEPTIONIST`, `DOCTOR`, `NURSE`, `ADMIN`.
  - `COMPOSITION` creation & ingestion: Restricted to clinical roles (`DOCTOR`, `NURSE`).
  - `AQL` querying: Restricted to authorized clinical and records staff with query auditing.
- **Subject Privacy Check**: EHR lookup by subject (`/api/openehr/v1/ehr?subject_id=...`) enforces NDPA Section 30 compliance, verifying consent status or vital interest grounds.

---

## 1. Electronic Health Record (EHR) Management

### `POST /api/openehr/v1/ehr`
Creates or links an openEHR `EHR` container for a CoreHealth patient.

- **Request Headers**:
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body**:
  ```json
  {
    "_type": "EHR_STATUS",
    "name": {"_type": "DV_TEXT", "value": "EHR Status"},
    "subject": {
      "external_ref": {
        "id": {"_type": "GENERIC_ID", "value": "105", "scheme": "corehealth_patient_id"},
        "namespace": "corehealth",
        "type": "PERSON"
      }
    },
    "is_queryable": true,
    "is_modifiable": true
  }
  ```
- **Response `201 Created`**:
  - Header: `Location: /api/openehr/v1/ehr/018c1b2f-7622-7934-8b65-e9754f9a4e32`
  - Header: `ETag: "9b3c48...7"`
  - Body:
    ```json
    {
      "system_id": {"value": "corehealth.hospital.ehr"},
      "ehr_id": {"value": "018c1b2f-7622-7934-8b65-e9754f9a4e32"},
      "ehr_status": {
        "_type": "EHR_STATUS",
        "name": {"_type": "DV_TEXT", "value": "EHR Status for Patient PAT-99120"},
        "uid": {"_type": "OBJECT_VERSION_ID", "value": "status-uuid::corehealth.hospital.ehr::1"},
        "is_queryable": true,
        "is_modifiable": true
      },
      "time_created": {"value": "2026-11-10T22:30:00+01:00"}
    }
    ```

### `GET /api/openehr/v1/ehr/{ehr_id}`
Retrieves the EHR status and metadata for a given `ehr_id`.

- **Path Parameters**: `ehr_id` (openEHR persistent UUID)
- **Response `200 OK`**: Returns EHR representation with system ID and timestamps.
- **Error `404 Not Found`**: Returns `{"message": "EHR with id '{ehr_id}' does not exist."}`

### `GET /api/openehr/v1/ehr` (Subject Search)
Searches an EHR by subject identifier and namespace.

- **Query Parameters**:
  - `subject_id` *(string, required)*: CoreHealth patient numeric ID or hospital MRN (`file_no`).
  - `subject_namespace` *(string, optional)*: Defaults to `corehealth`.
- **Response `200 OK`**: Returns matched EHR object.

---

## 2. Clinical Compositions (`COMPOSITION.encounter.v1`)

### `GET /api/openehr/v1/ehr/{ehr_id}/composition/{versioned_object_uid}`
Retrieves a canonical openEHR composition representing an encounter and all nested clinical archetypes.

- **Path Parameters**:
  - `ehr_id`: Target patient's openEHR identifier.
  - `versioned_object_uid`: Format `{composition_uuid}::corehealth.hospital.ehr::1`.
- **Response `200 OK`**:
  ```json
  {
    "_type": "COMPOSITION",
    "archetype_node_id": "openEHR-EHR-COMPOSITION.encounter.v1",
    "name": {"_type": "DV_TEXT", "value": "Clinical Encounter"},
    "uid": {"_type": "OBJECT_VERSION_ID", "value": "comp-uuid::corehealth.hospital.ehr::1"},
    "composer": {
      "_type": "PARTY_IDENTIFIED",
      "name": "Dr. Chinedu Eze"
    },
    "context": {
      "_type": "EVENT_CONTEXT",
      "start_time": {"_type": "DV_DATE_TIME", "value": "2026-11-10T09:15:00+01:00"}
    },
    "content": [
      {
        "_type": "OBSERVATION",
        "archetype_node_id": "openEHR-EHR-OBSERVATION.blood_pressure.v2",
        "name": {"_type": "DV_TEXT", "value": "Blood pressure"},
        "data": {
          "_type": "HISTORY",
          "archetype_node_id": "at0001",
          "events": [{
            "_type": "POINT_EVENT",
            "archetype_node_id": "at0002",
            "data": {
              "_type": "ITEM_TREE",
              "archetype_node_id": "at0003",
              "items": [
                {"_type": "ELEMENT", "archetype_node_id": "at0004", "name": {"_type": "DV_TEXT", "value": "Systolic"}, "value": {"_type": "DV_QUANTITY", "magnitude": 120, "units": "mm[Hg]"}},
                {"_type": "ELEMENT", "archetype_node_id": "at0005", "name": {"_type": "DV_TEXT", "value": "Diastolic"}, "value": {"_type": "DV_QUANTITY", "magnitude": 80, "units": "mm[Hg]"}}
              ]
            }
          }]
        }
      }
    ]
  }
  ```

### `POST /api/openehr/v1/ehr/{ehr_id}/composition` (Inbound Archetype Extraction)
Receives an external openEHR `COMPOSITION.encounter.v1`, traverses nested observation archetypes, and persists data into CoreHealth models, proxying `VitalSignController@store` (`app/Http/Controllers/VitalSignController.php`).

- **Inbound Mutator**: `InboundOpenEhrCompositionMutator::mutateAndPersist()`
- **Proxy Controller**: `app(VitalSignController::class)->store($mutatedRequest)`
- **Archetype Parsing**:
  - `openEHR-EHR-OBSERVATION.blood_pressure.v2`: Extracts `at0004` (Systolic) and `at0005` (Diastolic).
  - `openEHR-EHR-OBSERVATION.pulse.v2`: Extracts `at0004` (Rate).
  - `openEHR-EHR-OBSERVATION.body_temperature.v2`: Extracts `at0004` (Temperature).
- **Core Models Mutated**: `Encounter` (`app/Models/Encounter.php`), `VitalSign` (`app/Models/VitalSign.php`).
- **Response `201 Created`**: Returns created `COMPOSITION` with version UID and `Location` header.

---

## 3. Archetype Query Language (AQL) Engine

### `POST /api/openehr/v1/query/aql`
Executes an Archetype Query Language (AQL) statement across the CoreHealth clinical database.

- **Request Body**:
  ```json
  {
    "q": "SELECT o/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value/magnitude AS systolic, o/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value/magnitude AS diastolic, o/data[at0001]/events[at0002]/time/value AS time_taken FROM EHR e CONTAINS COMPOSITION c CONTAINS OBSERVATION o [openEHR-EHR-OBSERVATION.blood_pressure.v2]"
  }
  ```
- **Response `200 OK`**:
  ```json
  {
    "meta": {
      "_type": "RESULT_SET",
      "_schema_version": "1.1.0",
      "_created": "2026-11-10T23:55:00+01:00",
      "_generator": "CoreHealth openEHR AQL Engine"
    },
    "q": "SELECT o/data[at0001]/... AS systolic FROM ...",
    "columns": [
      {"name": "systolic", "path": "/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value/magnitude"},
      {"name": "diastolic", "path": "/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value/magnitude"},
      {"name": "time_taken", "path": "/data[at0001]/events[at0002]/time/value"}
    ],
    "rows": [
      [120, 80, "2026-11-10T14:30:00+01:00"],
      [135, 88, "2026-11-09T10:15:00+01:00"]
    ]
  }
  ```

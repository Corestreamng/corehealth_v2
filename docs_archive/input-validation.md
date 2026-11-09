# Input Validation in CoreHealth v2

CoreHealth v2 is a Laravel-based Hospital Management Information System. All user inputs are validated before processing using Laravel's built-in validation framework.

## Validation Strategy

### 1. HTTP Request Validation (Primary Layer)

All controller methods that accept user input call `$request->validate()` before any business logic:

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'patient_id'  => 'required|exists:patients,id',
        'service_id'  => 'required|exists:services,id',
        'quantity'    => 'required|integer|min:1|max:9999',
        'notes'       => 'nullable|string|max:2000',
    ]);

    // Only validated data proceeds
    PatientServiceRequest::create($validated);
}
```

### 2. Form Request Classes

Complex validation scenarios use dedicated `FormRequest` classes in `app/Http/Requests/`:

```php
class StorePatientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'surname'    => 'required|string|max:100',
            'firstname'  => 'required|string|max:100',
            'dob'        => 'required|date|before:today',
            'gender'     => 'required|in:male,female,other',
            'phone'      => 'required|string|max:20',
            'hmo_id'     => 'nullable|exists:hmos,id',
        ];
    }
}
```

### 3. Database-Level Protection

- All database queries use **Eloquent ORM** with parameterized bindings — raw string interpolation into SQL is prohibited
- The `$fillable` property is explicitly set on all models to prevent mass-assignment vulnerabilities
- Soft deletes are used on clinical records to prevent accidental data loss

### 4. File Upload Validation

File uploads (attachments, documents, lab result files) are validated for:
- MIME type (`mimes:pdf,jpg,jpeg,png,doc,docx`)
- Maximum size (`max:10240` — 10MB)
- Filename sanitization via `hashName()` before storage

### 5. API Endpoints

Mobile API endpoints (`app/Http/Controllers/API/`) additionally:
- Require Bearer token authentication (`auth:sanctum`)
- Validate all JSON payloads with the same `$request->validate()` pattern
- Return structured JSON error responses with HTTP 422 on validation failure

## Module-by-Module Coverage

| Module | Validation Mechanism |
|---|---|
| Patient Registration | `PatientController@store` — `$request->validate()` with 15+ rules |
| Billing / Payments | `BillingWorkbenchController` — validates patient, amount, payment mode |
| Lab Requests | `LabWorkbenchController` — validates patient, service, lab number format |
| Pharmacy Dispense | `PharmacyWorkbenchController` — validates product, quantity, batch |
| Nursing Vitals | `VitalSignController` — validates numeric ranges for BP, temp, HR, etc. |
| HMO Claims | `HmoWorkbenchController` — validates claim amounts and authorization codes |
| Inventory | `PurchaseOrderController` — validates supplier, product, quantities |
| HR / Payroll | `HR\StaffRegistryController` — validates staff profile completeness |

## Error Response Format

Validation failures return HTTP 422 with structured JSON:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "patient_id": ["The selected patient id is invalid."],
        "quantity": ["The quantity must be at least 1."]
    }
}
```

## Security Hygiene

- No hardcoded secrets or credentials anywhere in the codebase
- All sensitive environment variables are in `.env` (gitignored) — see `.env.example`
- CSRF tokens are verified on all POST/PUT/PATCH/DELETE requests
- Role-based access is enforced via **Spatie Laravel Permission** (`@can`, `@role` directives)
- See [SECURITY.md](./SECURITY.md) for the full security policy and vulnerability reporting process

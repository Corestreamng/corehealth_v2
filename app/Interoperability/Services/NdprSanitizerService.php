<?php

namespace App\Interoperability\Services;

use App\Interoperability\Models\InteropExternalIdentifier;
use App\Models\Patient;
use Illuminate\Support\Facades\Log;

class NdprSanitizerService
{
    /**
     * De-identify / sanitize clinical text by redacting direct PII identifiers
     * per Nigeria Data Protection Act (NDPA 2023) Sections 30 & 41-43.
     */
    public function sanitizeClinicalText(string $text, ?Patient $patient = null): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        $sanitized = $text;

        // If specific patient is passed, redact exact names and identifiers
        if ($patient && $patient->user) {
            $user = $patient->user;
            $names = array_filter([$user->surname, $user->firstname, $user->othername]);
            foreach ($names as $name) {
                if (mb_strlen($name) >= 3) {
                    $sanitized = preg_replace('/\b' . preg_quote($name, '/') . '\b/i', '[REDACTED_NAME]', $sanitized);
                }
            }

            if (!empty($patient->file_no)) {
                $sanitized = preg_replace('/\b' . preg_quote($patient->file_no, '/') . '\b/i', '[REDACTED_MRN]', $sanitized);
            }

            if (!empty($user->phone_number)) {
                $sanitized = preg_replace('/\b' . preg_quote($user->phone_number, '/') . '\b/', '[REDACTED_PHONE]', $sanitized);
            }

            if (!empty($user->email)) {
                $sanitized = preg_replace('/\b' . preg_quote($user->email, '/') . '\b/i', '[REDACTED_EMAIL]', $sanitized);
            }
        }

        // Generic regex filters for PII patterns
        // 1. Nigerian Phone Numbers (+234 or 070/080/081/090/091 followed by digits)
        $sanitized = preg_replace('/(\+?234|0)[789][01]\d{8}/', '[REDACTED_PHONE]', $sanitized);

        // 2. Email Addresses
        $sanitized = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $sanitized);

        // 3. National Identity Numbers (NIN: 11 consecutive digits) & Bank Verification Numbers (BVN)
        $sanitized = preg_replace_callback('/\b(NIN|BVN)[\s:#]*\d{11}\b/i', function ($m) {
            return '[REDACTED_' . strtoupper($m[1]) . ']';
        }, $sanitized);
        $sanitized = preg_replace('/\b\d{11}\b/', '[REDACTED_NIN]', $sanitized);

        // 4. Bank Account Numbers (NUBAN: 10 digits with keyword context)
        $sanitized = preg_replace('/\b(acct|account|nuban)[\s#:]*\d{10}\b/i', '[REDACTED_ACCOUNT]', $sanitized);

        // 5. File / Chart Number Patterns (e.g., PAT-12345, MRN/2026/001)
        $sanitized = preg_replace('/\b(PAT|MRN|FILE|CH2)[-\/]\w+\b/i', '[REDACTED_FILE_NO]', $sanitized);

        // 6. Honorific + Personal Name Patterns (e.g., Mr. John Doe, Dr. Jane)
        $sanitized = preg_replace('/\b(Mr|Mrs|Ms|Miss|Dr|Prof|Professor|Alhaji|Hajia|Chief|Pastor|Elder|Reverend|Rev|Sister|Brother)\.?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\b/', '[REDACTED_NAME]', $sanitized);

        // 7. Kinship / Next of Kin Named References
        $sanitized = preg_replace_callback('/\b(next of kin|father|mother|brother|sister|uncle|aunt|husband|wife|son|daughter|guardian|sponsor)[\s:]+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\b/i', function ($matches) {
            return $matches[1] . ': [REDACTED_NAME]';
        }, $sanitized);

        return $sanitized;
    }

    /**
     * Generate or fetch a deterministic pseudonym for a patient ID
     * for secure cross-border cloud processing (e.g. Gemini AI).
     */
    public function getPatientPseudonym(int $patientId): string
    {
        return InteropExternalIdentifier::getOrCreateUuid(
            'NDPA',
            'PatientPseudonym',
            $patientId,
            config('app.url') . '/api/ndpa/pseudonym'
        );
    }

    /**
     * Resolve a patient pseudonym back to local patient ID.
     */
    public function resolvePseudonym(string $pseudonym): ?int
    {
        return InteropExternalIdentifier::resolveLocalId('NDPA', 'PatientPseudonym', $pseudonym);
    }

    /**
     * Audit cross-border data transfer event per NDPA 2023 Sections 41-43.
     */
    public function auditCrossBorderTransfer(int $patientId, string $targetService, string $dataCategory, string $purpose): void
    {
        Log::channel('daily')->info('NDPA Cross-Border Clinical Data Transfer Audit', [
            'patient_id' => $patientId,
            'pseudonym' => $this->getPatientPseudonym($patientId),
            'target_service' => $targetService,
            'data_category' => $dataCategory,
            'purpose' => $purpose,
            'timestamp' => now()->toIso8601String(),
            'jurisdiction_safeguard' => 'Standard Contractual Clauses & Anonymization per NDPA Sec 42',
        ]);
    }
}

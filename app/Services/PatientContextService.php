<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\NursingNote;
use App\Models\MedicationAdministration;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\Procedure;
use App\Models\AdmissionRequest;
use App\Models\SpecialistReferral;
use App\Models\NonPharmOrder;
use App\Models\InjectionAdministration;
use App\Models\ImmunizationRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for building semantic patient context for LLM prompts.
 * Uses aggressive write-through caching for performance.
 */
class PatientContextService
{
    protected array $config;

    public function __construct()
    {
        $settings = appsettings();
        $this->config = is_string($settings->llm_config) 
            ? json_decode($settings->llm_config, true) 
            : (is_array($settings->llm_config) ? $settings->llm_config : []);
    }

    /**
     * Get the semantic context for a patient, utilizing the cache.
     */
    public function getPatientContext(int $patientId, ?int $encounterId = null, array $options = []): string
    {
        $cacheKey = $this->getCacheKey($patientId);
        $ttlHours = $this->config['rag_settings']['cache_ttl_hours'] ?? 24;

        $patientContext = Cache::remember($cacheKey, now()->addHours($ttlHours), function () use ($patientId, $options) {
            return $this->buildContext($patientId, $options);
        });

        // Append the current encounter context dynamically (not cached, as it changes frequently)
        if ($encounterId) {
            $encounterContext = $this->buildCurrentEncounterChunk($encounterId);
            if ($encounterContext) {
                return $patientContext . "\n\n" . $encounterContext;
            }
        }

        return $patientContext;
    }

    /**
     * Invalidate the cache for a given patient.
     * Called by model observers when clinical data changes.
     */
    public function invalidateContextCache(int $patientId): void
    {
        Cache::forget($this->getCacheKey($patientId));
    }

    protected function getCacheKey(int $patientId): string
    {
        return "llm_context:{$patientId}";
    }

    /**
     * Deep harvest clinical data and build the context string.
     */
    protected function buildContext(int $patientId, array $options): string
    {
        $patient = Patient::with('user')->find($patientId);
        if (!$patient) {
            return "Patient not found.";
        }

        $months = $this->config['summary_scope_months'] ?? 3;
        $maxEntries = $this->config['summary_scope_max_entries'] ?? 50;
        $dateLimit = now()->subMonths($months);

        $context = [];
        $context[] = $this->buildDemographicsChunk($patient);
        $context[] = $this->buildAllergiesChunk($patient);
        
        // Fetch specific categories with dual scope limiter
        $context[] = $this->buildVitalsChunk($patientId, $dateLimit, 10); // Last 10 vitals
        $context[] = $this->buildActiveMedicationsChunk($patientId);
        $context[] = $this->buildLabResultsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildImagingResultsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildClinicalNotesChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildNursingNotesChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildProcedureChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildAdmissionsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildPrescriptionsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildIntakeOutputChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildInjectionsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildCarePlansChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildReferralsChunk($patientId, $dateLimit, $maxEntries);
        $context[] = $this->buildMaternityChunk($patientId);

        // Filter out empty chunks and join
        return implode("\n\n", array_filter($context));
    }

    protected function buildDemographicsChunk(Patient $patient): string
    {
        $age = $patient->dob ? Carbon::parse($patient->dob)->age : 'Unknown';
        $gender = $patient->gender ?? 'Unknown';
        $bg = $patient->blood_group ?? 'Unknown';
        $geno = $patient->genotype ?? 'Unknown';

        return "--- PATIENT DEMOGRAPHICS ---\n" .
               "Age: {$age} | Gender: {$gender} | Blood Group: {$bg} | Genotype: {$geno}";
    }

    protected function buildAllergiesChunk(Patient $patient): ?string
    {
        // Assuming allergies are stored in a field or related table.
        // For now, checking typical fields:
        $allergies = $patient->allergies ?? 'None recorded';
        if (empty($allergies)) return null;

        if (is_array($allergies)) {
            $allergies = implode(', ', $allergies);
        }

        return "--- ALLERGIES & WARNINGS ---\n" . $allergies;
    }

    protected function buildVitalsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $vitals = VitalSign::where('patient_id', $patientId)
            ->where('time_taken', '>=', $dateLimit)
            ->orderBy('time_taken', 'desc')
            ->take($limit)
            ->get();

        if ($vitals->isEmpty()) return null;

        $lines = ["--- RECENT VITALS (Last {$vitals->count()} readings) ---"];
        foreach ($vitals as $v) {
            $date = $v->time_taken->format('Y-m-d H:i');
            $readings = [];
            if ($v->blood_pressure) $readings[] = "BP: {$v->blood_pressure}";
            if ($v->temp) $readings[] = "Temp: {$v->temp}°C";
            if ($v->heart_rate) $readings[] = "HR: {$v->heart_rate}";
            if ($v->spo2) $readings[] = "SpO2: {$v->spo2}%";
            if ($v->resp_rate) $readings[] = "RR: {$v->resp_rate}";
            if ($v->weight) $readings[] = "Wt: {$v->weight}kg";

            $lines[] = "[{$date}] " . implode(' | ', $readings);
        }

        return implode("\n", $lines);
    }

    protected function buildActiveMedicationsChunk(int $patientId): ?string
    {
        // We look at recent prescriptions or medication administrations
        $meds = MedicationAdministration::where('patient_id', $patientId)
            ->with('product')
            ->orderBy('administered_at', 'desc')
            ->take(20)
            ->get()
            ->unique('product_id');

        if ($meds->isEmpty()) return null;

        $lines = ["--- RECENT MEDICATIONS ---"];
        foreach ($meds as $m) {
            $name = $m->product ? $m->product->product_name : ($m->external_drug_name ?? 'Unknown');
            $date = $m->administered_at ? Carbon::parse($m->administered_at)->format('Y-m-d') : 'Unknown Date';
            $lines[] = "- {$name} (Dose: {$m->dose}, Route: {$m->route}) [Last administered: {$date}]";
        }

        return implode("\n", $lines);
    }

    protected function buildLabResultsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        // Only include completed labs (status >= 4 typically means result available/approved)
        $labs = LabServiceRequest::where('patient_id', $patientId)
            ->with('service')
            ->where('status', '>=', 4)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($labs->isEmpty()) return null;

        $lines = ["--- LAB RESULTS ---"];
        foreach ($labs as $lab) {
            $name = $lab->service ? $lab->service->service_name : 'Unknown Test';
            $date = $lab->created_at->format('Y-m-d');
            
            // Handle V1 (string) vs V2 (JSON/array) result data
            $resultText = '';
            if (!empty($lab->result_data) && is_array($lab->result_data)) {
                $parts = [];
                foreach ($lab->result_data as $key => $val) {
                    if (is_string($key) && is_string($val)) {
                        $parts[] = "{$key}: {$val}";
                    } elseif (is_array($val) && isset($val['parameter'], $val['result'])) {
                        // Handle standard lab data format if applicable
                        $parts[] = "{$val['parameter']}: {$val['result']} " . ($val['unit'] ?? '');
                    }
                }
                $resultText = implode(', ', $parts);
            } else {
                $resultText = strip_tags($lab->result ?? 'See attachment/notes');
            }

            if (!empty($resultText)) {
                $lines[] = "[{$date}] {$name}: {$resultText}";
            } else {
                $lines[] = "[{$date}] {$name}: Completed (details unavailable in text format)";
            }
        }

        return implode("\n", $lines);
    }

    protected function buildImagingResultsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $imaging = ImagingServiceRequest::where('patient_id', $patientId)
            ->with('service')
            ->where('status', '>=', 4)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($imaging->isEmpty()) return null;

        $lines = ["--- IMAGING RESULTS ---"];
        foreach ($imaging as $img) {
            $name = $img->service ? $img->service->service_name : 'Unknown Scan';
            $date = $img->created_at->format('Y-m-d');
            
            // Handle V1/V2 similar to labs
            $resultText = '';
            if (!empty($img->result_data) && is_array($img->result_data)) {
                $resultText = "Structured report available";
                // Add structured parsing if needed
                if (isset($img->result_data['conclusion'])) {
                     $resultText .= " - Conclusion: " . strip_tags($img->result_data['conclusion']);
                }
            } else {
                $resultText = mb_substr(strip_tags($img->result ?? 'Report available'), 0, 200) . '...';
            }

            $lines[] = "[{$date}] {$name}:\n  Report: {$resultText}";
        }

        return implode("\n", $lines);
    }

    protected function buildClinicalNotesChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $encounters = Encounter::where('patient_id', $patientId)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($encounters->isEmpty()) return null;

        $lines = ["--- CLINICAL NOTES (Past Encounters) ---"];
        foreach ($encounters as $enc) {
            $date = $enc->created_at->format('Y-m-d H:i');
            $diag = $this->parseDiagnosis($enc->reasons_for_encounter);
            
            $lines[] = "[{$date}] Encounter";
            if ($diag) $lines[] = "Diagnosis/Reason: {$diag}";
            $lines[] = "Notes: " . strip_tags($enc->notes);
            $lines[] = "-";
        }

        return implode("\n", $lines);
    }
    
    protected function buildNursingNotesChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $notes = NursingNote::where('patient_id', $patientId)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($notes->isEmpty()) return null;

        $lines = ["--- NURSING NOTES ---"];
        foreach ($notes as $note) {
            $date = $note->created_at->format('Y-m-d H:i');
            $cleanNote = strip_tags($note->note);
            $lines[] = "[{$date}] {$cleanNote}";
        }

        return implode("\n", $lines);
    }

    protected function buildProcedureChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $procedures = Procedure::where('patient_id', $patientId)
            ->with('service')
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($procedures->isEmpty()) return null;

        $lines = ["--- PROCEDURES ---"];
        foreach ($procedures as $proc) {
            $date = $proc->created_at->format('Y-m-d');
            $name = $proc->service ? $proc->service->service_name : 'Unknown Procedure';
            $status = $proc->getStatusDisplayAttribute();
            $outcome = $proc->getOutcomeDisplayAttribute();
            
            $details = "[{$date}] {$name} (Status: {$status}";
            if ($proc->outcome) {
                $details .= ", Outcome: {$outcome}";
            }
            $details .= ")";
            
            $lines[] = $details;
            
            if ($proc->post_notes) {
                $cleanNotes = mb_substr(strip_tags($proc->post_notes), 0, 150) . '...';
                $lines[] = "  Notes: {$cleanNotes}";
            }
        }

        return implode("\n", $lines);
    }

    protected function buildAdmissionsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $admissions = AdmissionRequest::where('patient_id', $patientId)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($admissions->isEmpty()) return null;

        $lines = ["--- ADMISSIONS ---"];
        foreach ($admissions as $adm) {
            $date = $adm->created_at->format('Y-m-d');
            $status = $adm->status_label ?? 'Unknown';
            $reason = strip_tags($adm->admission_reason ?? 'No reason provided');
            $lines[] = "[{$date}] Status: {$status} | Reason: {$reason}";
        }

        return implode("\n", $lines);
    }

    protected function buildCurrentEncounterChunk(int $encounterId): ?string
    {
        $enc = Encounter::find($encounterId);
        if (!$enc) return null;

        $lines = ["--- CURRENT ENCOUNTER (Active) ---"];
        $date = $enc->created_at->format('Y-m-d H:i');
        $lines[] = "Started: {$date}";
        
        $diag = $this->parseDiagnosis($enc->reasons_for_encounter);
        if ($diag) $lines[] = "Working Diagnosis/Reason: {$diag}";
        
        if ($enc->notes) {
            $lines[] = "Current Notes Draft: " . strip_tags($enc->notes);
        }

        return implode("\n", $lines);
    }

    protected function parseDiagnosis(?string $reasons): string
    {
        if (empty($reasons)) return '';

        $decoded = json_decode($reasons, true);
        if (is_array($decoded) && isset($decoded[0]['code'])) {
            $parts = [];
            foreach ($decoded as $dx) {
                $status = !empty($dx['comment_1']) ? "({$dx['comment_1']})" : '';
                $parts[] = "{$dx['name']} {$status}";
            }
            return implode(', ', $parts);
        }
        
        return $reasons;
    }

    protected function buildPrescriptionsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $prescriptions = \App\Models\ProductRequest::where('patient_id', $patientId)
            ->with('product')
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($prescriptions->isEmpty()) return null;

        $lines = ["--- PRESCRIPTIONS ---"];
        foreach ($prescriptions as $p) {
            $name = $p->product ? $p->product->product_name : 'Unknown Product';
            $date = $p->created_at->format('Y-m-d H:i');
            $lines[] = "[{$date}] {$name} (Qty: {$p->qty}, Dosage: " . ($p->dosage ?? 'N/A') . ")";
        }

        return implode("\n", $lines);
    }

    protected function buildIntakeOutputChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $io = \App\Models\IntakeOutputPeriod::where('patient_id', $patientId)
            ->where('started_at', '>=', $dateLimit)
            ->orderBy('started_at', 'desc')
            ->take($limit)
            ->get();

        if ($io->isEmpty()) return null;

        $lines = ["--- INTAKE & OUTPUT FLUID BALANCE ---"];
        foreach ($io as $period) {
            $date = $period->started_at ? Carbon::parse($period->started_at)->format('Y-m-d H:i') : 'Unknown';
            $totalIn = $period->total_intake ?? 0;
            $totalOut = $period->total_output ?? 0;
            $balance = $totalIn - $totalOut;
            $lines[] = "[{$date}] Total Intake: {$totalIn}ml | Total Output: {$totalOut}ml | Balance: {$balance}ml";
        }

        return implode("\n", $lines);
    }

    protected function buildInjectionsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $injections = \App\Models\InjectionAdministration::where('patient_id', $patientId)
            ->where('administered_at', '>=', $dateLimit)
            ->orderBy('administered_at', 'desc')
            ->take($limit)
            ->get();

        $immunizations = \App\Models\ImmunizationRecord::where('patient_id', $patientId)
            ->where('administered_at', '>=', $dateLimit)
            ->orderBy('administered_at', 'desc')
            ->take($limit)
            ->get();

        if ($injections->isEmpty() && $immunizations->isEmpty()) return null;

        $lines = ["--- INJECTIONS & IMMUNIZATIONS ---"];
        foreach ($injections as $inj) {
            $date = $inj->administered_at ? Carbon::parse($inj->administered_at)->format('Y-m-d H:i') : 'Unknown';
            $lines[] = "[{$date}] Injection: " . ($inj->injection_name ?? 'Unknown') . " (" . ($inj->dose ?? 'N/A') . ")";
        }
        foreach ($immunizations as $imm) {
            $date = $imm->administered_at ? Carbon::parse($imm->administered_at)->format('Y-m-d H:i') : 'Unknown';
            $lines[] = "[{$date}] Immunization: " . ($imm->vaccine_name ?? 'Unknown') . " (" . ($imm->dose ?? 'N/A') . ")";
        }

        return implode("\n", $lines);
    }

    protected function buildCarePlansChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $plans = \App\Models\NonPharmOrder::where('patient_id', $patientId)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($plans->isEmpty()) return null;

        $lines = ["--- CARE PLANS & NON-PHARM ORDERS ---"];
        foreach ($plans as $plan) {
            $date = $plan->created_at->format('Y-m-d H:i');
            $lines[] = "[{$date}] " . strip_tags($plan->order_details ?? 'No details');
        }

        return implode("\n", $lines);
    }

    protected function buildReferralsChunk(int $patientId, Carbon $dateLimit, int $limit): ?string
    {
        $referrals = \App\Models\SpecialistReferral::where('patient_id', $patientId)
            ->where('created_at', '>=', $dateLimit)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        if ($referrals->isEmpty()) return null;

        $lines = ["--- SPECIALIST REFERRALS ---"];
        foreach ($referrals as $ref) {
            $date = $ref->created_at->format('Y-m-d H:i');
            $lines[] = "[{$date}] Referral Reason: " . strip_tags($ref->reason ?? 'Consultation request');
        }

        return implode("\n", $lines);
    }

    protected function buildMaternityChunk(int $patientId): ?string
    {
        $enrollment = \App\Models\MaternityEnrollment::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$enrollment) return null;

        $lines = ["--- MATERNITY STATUS ---"];
        $lines[] = "Enrolled on: " . $enrollment->created_at->format('Y-m-d');
        if ($enrollment->edd) $lines[] = "Estimated Date of Delivery (EDD): " . Carbon::parse($enrollment->edd)->format('Y-m-d');
        if ($enrollment->lmp) $lines[] = "Last Menstrual Period (LMP): " . Carbon::parse($enrollment->lmp)->format('Y-m-d');
        
        $deliveries = \App\Models\DeliveryRecord::where('patient_id', $patientId)->orderBy('delivery_date', 'desc')->get();
        if ($deliveries->isNotEmpty()) {
            foreach ($deliveries as $del) {
                $lines[] = "Delivery Date: " . ($del->delivery_date ? Carbon::parse($del->delivery_date)->format('Y-m-d H:i') : 'Unknown') . " | Method: " . ($del->delivery_method ?? 'Unknown');
            }
        }

        return implode("\n", $lines);
    }
}

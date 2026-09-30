<?php

namespace App\Services;

use App\Models\NursingShift;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ClinicalHandoverFormatter
 *
 * Transforms raw OwenIt\Auditing audit logs into structured, human-readable
 * clinical summaries for nursing shift handovers. Each tracked model in
 * NursingShift::NURSING_AUDITABLE_TYPES has a dedicated formatter that
 * extracts the clinically relevant fields and produces a natural-language
 * summary line (e.g. "💊 Medication (11:00): Ceftriaxone 1g IV — Administered").
 *
 * Replaces the raw database-column diff approach previously used in
 * NursingShift::parseAuditChanges() / generateDetailedSummary().
 */
class ClinicalHandoverFormatter
{
    /**
     * Cache for resolved foreign key lookups (products, services, patients, wards, etc.)
     * to avoid repeated queries across audit entries.
     */
    protected array $resolveCache = [
        'products' => [],
        'services' => [],
        'patients' => [],
        'users' => [],
        'wards' => [],
        'beds' => [],
        'note_types' => [],
    ];

    /**
     * Vital sign ranges for clinical assessment.
     * Loaded from vital_ranges table on first access.
     */
    protected ?array $vitalRanges = null;

    /**
     * Format a single audit entry into a clinical summary object.
     *
     * @param string $modelType  The fully-qualified model class name
     * @param string $event      'created', 'updated', or 'deleted'
     * @param array  $oldValues  The audit's old_values
     * @param array  $newValues  The audit's new_values
     * @param Carbon $timestamp  When the audit was created
     * @return array  Structured clinical summary with 'line', 'icon', 'color', 'category', 'details', 'alerts'
     */
    public function formatAudit(string $modelType, string $event, array $oldValues, array $newValues, Carbon $timestamp): array
    {
        $config = NursingShift::NURSING_AUDITABLE_TYPES[$modelType]
            ?? ['label' => class_basename($modelType), 'icon' => 'mdi-file', 'color' => 'secondary'];

        $formatter = match ($modelType) {
            'App\\Models\\VitalSign' => 'formatVitalSign',
            'App\\Models\\NursingNote' => 'formatNursingNote',
            'App\\Models\\MedicationAdministration' => 'formatMedicationAdmin',
            'App\\Models\\MedicationSchedule' => 'formatMedicationSchedule',
            'App\\Models\\InjectionAdministration' => 'formatInjection',
            'App\\Models\\ImmunizationRecord' => 'formatImmunization',
            'App\\Models\\PatientImmunizationSchedule' => 'formatImmunizationSchedule',
            'App\\Models\\IntakeOutputPeriod' => 'formatIOPeriod',
            'App\\Models\\IntakeOutputRecord' => 'formatIORecord',
            'App\\Models\\ProductOrServiceRequest' => 'formatBilling',
            'App\\Models\\AdmissionRequest' => 'formatAdmission',
            'App\\Models\\Bed' => 'formatBed',
            'App\\Models\\AdmissionChecklist' => 'formatAdmissionChecklist',
            'App\\Models\\AdmissionChecklistItem' => 'formatChecklistItem',
            'App\\Models\\DischargeChecklist' => 'formatDischargeChecklist',
            'App\\Models\\DischargeChecklistItem' => 'formatChecklistItem',
            default => 'formatGeneric',
        };

        $result = $this->{$formatter}($event, $oldValues, $newValues, $timestamp);

        return array_merge([
            'category' => $config['label'],
            'type' => $config['label'],
            'icon' => $config['icon'],
            'color' => $config['color'],
            'event' => $event,
            'time' => $timestamp->format('H:i'),
            'time_full' => $timestamp->format('M j, Y g:i A'),
            'patient_id' => $this->extractPatientId($oldValues, $newValues),
            'alerts' => [],
        ], $result);
    }

    /**
     * Format an entire shift's audit logs into a structured clinical handover payload.
     *
     * @return array{
     *     executive_summary: string,
     *     patient_summaries: array,
     *     activity_timeline: array,
     *     category_counts: array,
     *     alerts: array
     * }
     */
    public function formatShiftHandover(NursingShift $shift): array
    {
        $audits = $shift->getShiftAuditLogs();

        $timeline = [];
        $patientData = [];
        $categoryCounts = [];
        $allAlerts = [];

        foreach ($audits as $audit) {
            $formatted = $this->formatAudit(
                $audit->auditable_type,
                $audit->event,
                $audit->old_values ?? [],
                $audit->new_values ?? [],
                $audit->created_at
            );

            $pid = $formatted['patient_id'];
            if ($pid) {
                if (!isset($patientData[$pid])) {
                    $patientInfo = $this->resolvePatient($pid);
                    $patientData[$pid] = [
                        'patient_id' => $pid,
                        'patient_name' => $patientInfo['name'],
                        'patient_no' => $patientInfo['file_no'],
                        'activities' => [],
                        'alerts' => [],
                    ];
                }
                $formatted['patient_name'] = $patientData[$pid]['patient_name'];
                $formatted['patient_no'] = $patientData[$pid]['patient_no'];
                $patientData[$pid]['activities'][] = $formatted;
                if (!empty($formatted['alerts'])) {
                    $patientData[$pid]['alerts'] = array_merge($patientData[$pid]['alerts'], $formatted['alerts']);
                }
            } else {
                $formatted['patient_name'] = 'General / Ward Activity';
                $formatted['patient_no'] = null;
            }

            $timeline[] = $formatted;

            // Aggregate per-category counts
            $cat = $formatted['category'];
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = [
                    'label' => $cat,
                    'count' => 0,
                    'total' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'deleted' => 0,
                    'icon' => $formatted['icon'],
                    'color' => $formatted['color'],
                    'events' => ['created' => 0, 'updated' => 0, 'deleted' => 0],
                    'patients' => [],
                ];
            }
            $categoryCounts[$cat]['total']++;
            $categoryCounts[$cat]['count']++;
            $categoryCounts[$cat][$audit->event] = ($categoryCounts[$cat][$audit->event] ?? 0) + 1;
            $categoryCounts[$cat]['events'][$audit->event] = ($categoryCounts[$cat]['events'][$audit->event] ?? 0) + 1;
            if ($pid && !isset($categoryCounts[$cat]['patients'][$pid])) {
                $categoryCounts[$cat]['patients'][$pid] = [
                    'name' => $formatted['patient_name'],
                    'patient_no' => $formatted['patient_no'],
                ];
            }

            // Collect global alerts
            if (!empty($formatted['alerts'])) {
                foreach ($formatted['alerts'] as $alert) {
                    $allAlerts[] = array_merge($alert, [
                        'patient_id' => $pid,
                        'time' => $formatted['time'],
                    ]);
                }
            }
        }

        // Build executive summary narrative
        $executiveSummary = $this->buildExecutiveSummary($shift, $categoryCounts, $patientData, $allAlerts);

        // Build per-patient clinical summaries
        $patientSummaries = [];
        foreach ($patientData as $pid => $data) {
            $patientSummaries[] = $this->buildPatientSummary($data);
        }

        // Sort by activity count descending
        usort($patientSummaries, fn ($a, $b) => count($b['activities']) - count($a['activities']));

        return [
            'executive_summary' => $executiveSummary,
            'patient_summaries' => $patientSummaries,
            'activity_timeline' => $timeline,
            'category_counts' => $categoryCounts,
            'alerts' => $allAlerts,
        ];
    }

    // ========================================================================
    // Model-specific formatters
    // ========================================================================

    /**
     * Format VitalSign audit into clinical summary.
     * Example: "🩺 Vitals: BP 120/80 mmHg · PR 78 bpm · Temp 37.1°C · SpO2 98%"
     */
    protected function formatVitalSign(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $alerts = [];
        $parts = [];

        // Blood Pressure
        $bp = $values['blood_pressure'] ?? null;
        if ($bp) {
            $parts[] = "BP {$bp} mmHg";
            $bpAlert = $this->assessBP($bp, $values['patient_id'] ?? null);
            if ($bpAlert) {
                $alerts[] = $bpAlert;
            }
        }

        // Heart Rate
        $hr = $values['heart_rate'] ?? null;
        if ($hr !== null && $hr !== '') {
            $parts[] = "PR {$hr} bpm";
            $hrAlert = $this->assessVital('heart_rate', (float) $hr, $values['patient_id'] ?? null);
            if ($hrAlert) {
                $alerts[] = $hrAlert;
            }
        }

        // Temperature
        $temp = $values['temp'] ?? $values['temperature'] ?? null;
        if ($temp !== null && $temp !== '') {
            $parts[] = "Temp {$temp}°C";
            $tempAlert = $this->assessVital('temp', (float) $temp, $values['patient_id'] ?? null);
            if ($tempAlert) {
                $alerts[] = $tempAlert;
            }
        }

        // Respiratory Rate
        $rr = $values['resp_rate'] ?? $values['respiratory_rate'] ?? null;
        if ($rr !== null && $rr !== '') {
            $parts[] = "RR {$rr}/min";
            $rrAlert = $this->assessVital('resp_rate', (float) $rr, $values['patient_id'] ?? null);
            if ($rrAlert) {
                $alerts[] = $rrAlert;
            }
        }

        // SpO2
        $spo2 = $values['spo2'] ?? null;
        if ($spo2 !== null && $spo2 !== '') {
            $parts[] = "SpO₂ {$spo2}%";
            $spo2Alert = $this->assessVital('spo2', (float) $spo2, $values['patient_id'] ?? null);
            if ($spo2Alert) {
                $alerts[] = $spo2Alert;
            }
        }

        // Blood Sugar
        $sugar = $values['blood_sugar'] ?? null;
        if ($sugar !== null && $sugar !== '') {
            $parts[] = "Sugar {$sugar} mg/dL";
        }

        // Pain Score
        $pain = $values['pain_score'] ?? null;
        if ($pain !== null && $pain !== '') {
            $parts[] = "Pain {$pain}/10";
            if ((int) $pain >= 7) {
                $alerts[] = ['level' => 'warning', 'message' => 'Severe pain (Score ' . $pain . '/10)'];
            }
        }

        // Weight/Height/BMI
        $weight = $values['weight'] ?? null;
        $height = $values['height'] ?? null;
        $bmi = $values['bmi'] ?? null;
        if ($weight) {
            $parts[] = "Wt {$weight} kg";
        }
        if ($height) {
            $parts[] = "Ht {$height} cm";
        }
        if ($bmi) {
            $parts[] = "BMI {$bmi}";
        }

        $eventLabel = $this->eventLabel($event);
        $line = empty($parts) ? "Vitals {$eventLabel}" : implode(' · ', $parts);

        // For updates, show what changed
        $changeDetails = [];
        if ($event === 'updated') {
            $changeDetails = $this->buildChangeSummary($old, $new, [
                'blood_pressure' => 'BP',
                'temp' => 'Temp',
                'heart_rate' => 'PR',
                'resp_rate' => 'RR',
                'spo2' => 'SpO₂',
                'blood_sugar' => 'Sugar',
                'pain_score' => 'Pain',
                'weight' => 'Wt',
            ]);
        }

        return [
            'line' => $line,
            'details' => $changeDetails,
            'alerts' => $alerts,
        ];
    }

    /**
     * Format MedicationAdministration audit.
     * Example: "💊 Medication: Ceftriaxone 1g IV — Administered"
     */
    protected function formatMedicationAdmin(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $alerts = [];

        // Resolve drug name
        $drugName = $this->resolveDrugName($values);

        $dose = $values['dose'] ?? '';
        $route = $values['route'] ?? '';
        $comment = $values['comment'] ?? '';
        $drugSource = $values['drug_source'] ?? '';

        $sourceLabel = match ($drugSource) {
            'pharmacy_dispensed' => 'Dispensed',
            'ward_stock' => 'Ward Stock',
            'patient_own' => 'Patient\'s Own',
            'external' => 'External',
            default => $drugSource ? ucfirst(str_replace('_', ' ', $drugSource)) : '',
        };

        $eventLabel = $this->eventLabel($event);
        $line = "{$drugName}";
        if ($dose) {
            $line .= " {$dose}";
        }
        if ($route) {
            $line .= " {$route}";
        }
        $line .= " — {$eventLabel}";
        if ($sourceLabel) {
            $line .= " ({$sourceLabel})";
        }

        if ($comment) {
            $line .= " — \"{$comment}\"";
        }

        $changeDetails = [];
        if ($event === 'updated') {
            $changeDetails = $this->buildChangeSummary($old, $new, [
                'dose' => 'Dose',
                'route' => 'Route',
                'administered_at' => 'Time',
                'comment' => 'Comment',
            ]);
        }

        return [
            'line' => $line,
            'details' => $changeDetails,
            'alerts' => $alerts,
        ];
    }

    /**
     * Format MedicationSchedule audit.
     * Example: "⏰ Scheduled: Ceftriaxone 1g IV at 08:00 AM"
     */
    protected function formatMedicationSchedule(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $drugName = $this->resolveDrugName($values);
        $dose = $values['dose'] ?? '';
        $route = $values['route'] ?? '';
        $scheduledTime = $values['scheduled_time'] ?? '';

        $timeDisplay = '';
        if ($scheduledTime) {
            try {
                $timeDisplay = Carbon::parse($scheduledTime)->format('M j, g:i A');
            } catch (\Exception $e) {
                $timeDisplay = $scheduledTime;
            }
        }

        $eventLabel = $this->eventLabel($event);
        $line = "{$drugName}";
        if ($dose) {
            $line .= " {$dose}";
        }
        if ($route) {
            $line .= " {$route}";
        }
        if ($timeDisplay) {
            $line .= " at {$timeDisplay}";
        }
        $line .= " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format NursingNote audit.
     * Example: "📝 Progress Note: \"Patient rested well after analgesics...\""
     */
    protected function formatNursingNote(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        // Resolve note type name
        $noteTypeId = $values['nursing_note_type_id'] ?? null;
        $noteTypeName = $noteTypeId ? $this->resolveNoteType($noteTypeId) : 'Note';

        // Clean note content (strip HTML, truncate)
        $rawNote = $values['note'] ?? '';
        $cleanNote = $this->cleanHtml($rawNote, 150);

        $eventLabel = $this->eventLabel($event);
        $line = "[{$noteTypeName}]";
        if ($cleanNote) {
            $line .= ": \"{$cleanNote}\"";
        }
        $line .= " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format InjectionAdministration audit.
     * Example: "💉 Injection: Tramadol 50mg IM (Left Gluteal) · Batch #TM241"
     */
    protected function formatInjection(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $drugName = $this->resolveDrugName($values);
        $dose = $values['dose'] ?? '';
        $route = $values['route'] ?? '';
        $site = $values['site'] ?? '';
        $batchNumber = $values['batch_number'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = "{$drugName}";
        if ($dose) {
            $line .= " {$dose}";
        }
        if ($route) {
            $line .= " {$route}";
        }
        if ($site) {
            $line .= " ({$site})";
        }
        if ($batchNumber) {
            $line .= " · Batch #{$batchNumber}";
        }
        $line .= " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format ImmunizationRecord audit.
     * Example: "🛡️ Immunization: Pentavalent (Dose #2) SC · Next due: Oct 26"
     */
    protected function formatImmunization(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $vaccineName = $values['vaccine_name'] ?? $this->resolveDrugName($values);
        $doseNumber = $values['dose_number'] ?? '';
        $dose = $values['dose'] ?? '';
        $route = $values['route'] ?? '';
        $site = $values['site'] ?? '';
        $nextDue = $values['next_due_date'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = $vaccineName;
        if ($doseNumber !== '' && $doseNumber !== null) {
            $line .= " (Dose #{$doseNumber})";
        }
        if ($dose) {
            $line .= " {$dose}";
        }
        if ($route) {
            $line .= " {$route}";
        }
        if ($site) {
            $line .= " ({$site})";
        }
        if ($nextDue) {
            try {
                $line .= " · Next due: " . Carbon::parse($nextDue)->format('M j, Y');
            } catch (\Exception $e) {
                $line .= " · Next due: {$nextDue}";
            }
        }
        $line .= " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format PatientImmunizationSchedule audit.
     * Example: "📅 Immunization Schedule: Pentavalent due Oct 15 — overdue"
     */
    protected function formatImmunizationSchedule(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $status = $values['status'] ?? '';
        $dueDate = $values['due_date'] ?? '';
        $skipReason = $values['skip_reason'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = "Immunization Schedule";
        if ($dueDate) {
            try {
                $line .= " due " . Carbon::parse($dueDate)->format('M j, Y');
            } catch (\Exception $e) {
                $line .= " due {$dueDate}";
            }
        }
        if ($status) {
            $line .= " — " . ucfirst($status);
        }
        if ($skipReason) {
            $line .= " (Skip: {$skipReason})";
        }

        $alerts = [];
        if ($status === 'overdue') {
            $alerts[] = ['level' => 'warning', 'message' => 'Immunization is overdue'];
        }

        return [
            'line' => $line,
            'details' => [],
            'alerts' => $alerts,
        ];
    }

    /**
     * Format IntakeOutputPeriod audit.
     * Example: "📊 I/O Period: Fluid monitoring started (Type: IV)"
     */
    protected function formatIOPeriod(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $type = $values['type'] ?? 'fluid';

        $eventLabel = $this->eventLabel($event);
        $line = "I/O Monitoring ({$type})";
        if (isset($values['total_intake']) || isset($values['total_output'])) {
            $intake = $values['total_intake'] ?? 0;
            $output = $values['total_output'] ?? 0;
            $line .= " [In: {$intake} mL / Out: {$output} mL]";
        }
        $line .= " — {$eventLabel}";

        if ($event === 'updated' && isset($new['ended_at']) && $new['ended_at']) {
            $line = "I/O Monitoring ({$type}) — Period Ended";
        }

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format IntakeOutputRecord audit.
     * Example: "💧 Intake: 500 mL (IV Normal Saline)" or "💧 Output: 350 mL (Urine via Foley)"
     */
    protected function formatIORecord(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $type = ucfirst($values['type'] ?? 'Record'); // 'intake' or 'output'
        $amount = $values['amount'] ?? '';
        $description = $values['description'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = "{$type}";
        if ($amount) {
            $line .= ": {$amount} mL";
        }
        if ($description) {
            $line .= " ({$description})";
        }
        $line .= " — {$eventLabel}";

        $changeDetails = [];
        if ($event === 'updated') {
            $changeDetails = $this->buildChangeSummary($old, $new, [
                'amount' => 'Amount',
                'description' => 'Description',
                'type' => 'Type',
            ]);
        }

        return [
            'line' => $line,
            'details' => $changeDetails,
            'alerts' => [],
        ];
    }

    /**
     * Format ProductOrServiceRequest (Billing) audit.
     * Example: "📦 Billing: IV Cannula 18G (Qty: 1) — Created"
     */
    protected function formatBilling(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        // Resolve product or service name
        $itemName = $values['product_name'] ?? $values['service_name'] ?? 'Billing Item';
        $productId = $values['product_id'] ?? null;
        $serviceId = $values['service_id'] ?? null;

        if ($productId) {
            $itemName = $this->resolveProduct($productId);
        } elseif ($serviceId) {
            $itemName = $this->resolveService($serviceId);
        }

        $qty = $values['qty'] ?? 1;
        $amount = $values['payable_amount'] ?? $values['amount'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = "{$itemName}";
        if ($qty > 1) {
            $line .= " (Qty: {$qty})";
        }
        if ($amount && (float) $amount > 0) {
            $line .= " — ₦" . number_format((float) $amount, 2);
        }
        $line .= " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format AdmissionRequest audit.
     * Example: "🛏️ Admission: Admitted to Female Medical Ward — Reason: Post-surgical care"
     */
    protected function formatAdmission(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $status = $values['admission_status'] ?? '';
        $reason = $values['admission_reason'] ?? $values['discharge_reason'] ?? '';
        $wardId = $values['preferred_ward_id'] ?? null;
        $bedId = $values['bed_id'] ?? null;

        $wardName = $wardId ? $this->resolveWard($wardId) : '';
        $bedName = $bedId ? $this->resolveBed($bedId) : '';

        $eventLabel = $this->eventLabel($event);
        $line = ucfirst($status ?: $eventLabel);
        if ($wardName) {
            $line .= " to {$wardName}";
        }
        if ($bedName) {
            $line .= " ({$bedName})";
        }
        if ($reason) {
            $line .= " — {$reason}";
        }

        // Handle discharge events
        if ($event === 'updated' && isset($new['admission_status']) && $new['admission_status'] === 'discharged') {
            $dischargeReason = $new['discharge_reason'] ?? '';
            $line = "Discharged";
            if ($dischargeReason) {
                $line .= " — {$dischargeReason}";
            }
        }

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format Bed audit.
     * Example: "🛏️ Bed: ER 1 (Emergency Ward) — Status: Occupied"
     */
    protected function formatBed(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $name = $values['name'] ?? 'Bed';
        $ward = $values['ward'] ?? '';
        $status = $values['bed_status'] ?? '';

        $eventLabel = $this->eventLabel($event);
        $line = "{$name}";
        if ($ward) {
            $line .= " ({$ward})";
        }
        if ($status) {
            $line .= " — " . ucfirst($status);
        } else {
            $line .= " — {$eventLabel}";
        }

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format AdmissionChecklist / DischargeChecklist audit.
     */
    protected function formatAdmissionChecklist(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $status = $values['status'] ?? '';
        $eventLabel = $this->eventLabel($event);

        $line = "Admission Checklist — " . ucfirst($status ?: $eventLabel);

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format DischargeChecklist audit.
     */
    protected function formatDischargeChecklist(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $status = $values['status'] ?? '';
        $eventLabel = $this->eventLabel($event);

        $line = "Discharge Checklist — " . ucfirst($status ?: $eventLabel);

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Format AdmissionChecklistItem / DischargeChecklistItem audit.
     * Example: "✅ Checklist: 'ID bracelet applied' — Completed"
     */
    protected function formatChecklistItem(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;

        $itemText = $values['item_text'] ?? 'Checklist Item';
        $isCompleted = $values['is_completed'] ?? false;
        $comment = $values['comment'] ?? '';

        $status = $isCompleted ? 'Completed' : 'Pending';
        if ($event === 'updated' && isset($new['is_completed'])) {
            $status = $new['is_completed'] ? 'Completed' : 'Unchecked';
        }

        $line = "\"{$itemText}\" — {$status}";
        if ($comment) {
            $line .= " ({$comment})";
        }

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    /**
     * Generic fallback formatter for unmapped model types.
     */
    protected function formatGeneric(string $event, array $old, array $new, Carbon $ts): array
    {
        $values = $event === 'deleted' ? $old : $new;
        $eventLabel = $this->eventLabel($event);

        // Show only non-internal fields
        $ignore = ['id', 'created_at', 'updated_at', 'deleted_at', 'user_id', 'staff_user_id'];
        $parts = [];
        foreach ($values as $field => $value) {
            if (in_array($field, $ignore) || $value === null || $value === '') {
                continue;
            }
            $label = ucwords(str_replace('_', ' ', $field));
            $parts[] = "{$label}: {$value}";
            if (count($parts) >= 5) {
                break;
            }
        }

        $line = empty($parts) ? $eventLabel : implode(' · ', $parts) . " — {$eventLabel}";

        return [
            'line' => $line,
            'details' => [],
            'alerts' => [],
        ];
    }

    // ========================================================================
    // Resolution helpers (Foreign key lookup with caching)
    // ========================================================================

    /**
     * Resolve drug name from a record's product_id or external_drug_name.
     */
    protected function resolveDrugName(array $values): string
    {
        // Check direct product name if present
        if (!empty($values['product_name'])) {
            return $values['product_name'];
        }

        // Check external drug name first (for non-pharmacy sources)
        if (!empty($values['external_drug_name'])) {
            return $values['external_drug_name'];
        }

        $productId = $values['product_id'] ?? null;
        if ($productId) {
            return $this->resolveProduct($productId);
        }

        // Try resolving via product_or_service_request_id
        $requestId = $values['product_or_service_request_id'] ?? null;
        if ($requestId) {
            if (!isset($this->resolveCache['billing_products'][$requestId])) {
                $request = DB::table('product_or_service_requests')
                    ->where('id', $requestId)
                    ->first(['product_id', 'service_id']);
                if ($request && $request->product_id) {
                    $this->resolveCache['billing_products'][$requestId] = $this->resolveProduct($request->product_id);
                } elseif ($request && $request->service_id) {
                    $this->resolveCache['billing_products'][$requestId] = $this->resolveService($request->service_id);
                } else {
                    $this->resolveCache['billing_products'][$requestId] = 'Unknown Item';
                }
            }

            return $this->resolveCache['billing_products'][$requestId];
        }

        return 'Unknown Medication';
    }

    /**
     * Resolve product name from product ID.
     */
    protected function resolveProduct(int $productId): string
    {
        if (!isset($this->resolveCache['products'][$productId])) {
            $product = DB::table('products')->where('id', $productId)->first(['product_name']);
            $this->resolveCache['products'][$productId] = $product->product_name ?? "Product #{$productId}";
        }

        return $this->resolveCache['products'][$productId];
    }

    /**
     * Resolve service name from service ID.
     */
    protected function resolveService(int $serviceId): string
    {
        if (!isset($this->resolveCache['services'][$serviceId])) {
            $service = DB::table('services')->where('id', $serviceId)->first(['service_name']);
            $this->resolveCache['services'][$serviceId] = $service->service_name ?? "Service #{$serviceId}";
        }

        return $this->resolveCache['services'][$serviceId];
    }

    /**
     * Resolve patient name and file number from patient ID.
     */
    protected function resolvePatient(int $patientId): array
    {
        if (!isset($this->resolveCache['patients'][$patientId])) {
            $patient = Patient::with('user')->find($patientId);
            if ($patient && $patient->user) {
                $name = trim(
                    ($patient->user->surname ?? '') . ' ' .
                    ($patient->user->firstname ?? '') . ' ' .
                    ($patient->user->othername ?? '')
                );
                $this->resolveCache['patients'][$patientId] = [
                    'name' => $name ?: "Patient #{$patientId}",
                    'file_no' => $patient->file_no ?? null,
                ];
            } else {
                $this->resolveCache['patients'][$patientId] = [
                    'name' => "Patient #{$patientId}",
                    'file_no' => null,
                ];
            }
        }

        return $this->resolveCache['patients'][$patientId];
    }

    /**
     * Resolve nursing note type name.
     */
    protected function resolveNoteType(int $typeId): string
    {
        if (!isset($this->resolveCache['note_types'][$typeId])) {
            $type = DB::table('nursing_note_types')->where('id', $typeId)->first(['name']);
            $this->resolveCache['note_types'][$typeId] = $type->name ?? 'Note';
        }

        return $this->resolveCache['note_types'][$typeId];
    }

    /**
     * Resolve ward name from ward ID.
     */
    protected function resolveWard(int $wardId): string
    {
        if (!isset($this->resolveCache['wards'][$wardId])) {
            $ward = DB::table('wards')->where('id', $wardId)->first(['name']);
            $this->resolveCache['wards'][$wardId] = $ward->name ?? "Ward #{$wardId}";
        }

        return $this->resolveCache['wards'][$wardId];
    }

    /**
     * Resolve bed name from bed ID.
     */
    protected function resolveBed(int $bedId): string
    {
        if (!isset($this->resolveCache['beds'][$bedId])) {
            $bed = DB::table('beds')->where('id', $bedId)->first(['name', 'ward']);
            $this->resolveCache['beds'][$bedId] = $bed ? "{$bed->name}" : "Bed #{$bedId}";
        }

        return $this->resolveCache['beds'][$bedId];
    }

    // ========================================================================
    // Clinical Assessment Helpers
    // ========================================================================

    /**
     * Load vital ranges from the database (lazy-loaded, cached per request).
     */
    protected function loadVitalRanges(): array
    {
        if ($this->vitalRanges === null) {
            try {
                $ranges = DB::table('vital_ranges')->get();
                $this->vitalRanges = [];
                foreach ($ranges as $range) {
                    $key = $range->vital_key;
                    if (!isset($this->vitalRanges[$key])) {
                        $this->vitalRanges[$key] = [];
                    }
                    $this->vitalRanges[$key][] = [
                        'age_min_days' => $range->age_min_days,
                        'age_max_days' => $range->age_max_days,
                        'gender' => $range->gender,
                        'normal_min' => (float) $range->normal_min,
                        'normal_max' => (float) $range->normal_max,
                        'warning_min' => (float) $range->warning_min,
                        'warning_max' => (float) $range->warning_max,
                        'critical_min' => (float) $range->critical_min,
                        'critical_max' => (float) $range->critical_max,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Could not load vital ranges: ' . $e->getMessage());
                $this->vitalRanges = [];
            }
        }

        return $this->vitalRanges;
    }

    /**
     * Assess a vital sign value against seeded ranges.
     * Returns an alert array if the value is outside normal range, or null.
     */
    protected function assessVital(string $vitalKey, float $value, ?int $patientId = null): ?array
    {
        $ranges = $this->loadVitalRanges();

        if (empty($ranges[$vitalKey])) {
            return null;
        }

        // Use the adult range as default (age_min_days >= 4381) if no patient context
        $applicableRange = null;
        foreach ($ranges[$vitalKey] as $range) {
            // Default to adult range without patient context
            if ($range['gender'] === null && $range['age_min_days'] >= 4381) {
                $applicableRange = $range;

                break;
            }
        }

        // Fallback to first available range
        if (!$applicableRange) {
            $applicableRange = $ranges[$vitalKey][0] ?? null;
        }

        if (!$applicableRange) {
            return null;
        }

        $labels = [
            'temp' => 'Temperature',
            'heart_rate' => 'Heart Rate',
            'resp_rate' => 'Respiratory Rate',
            'spo2' => 'SpO₂',
            'sugar' => 'Blood Sugar',
        ];
        $label = $labels[$vitalKey] ?? ucfirst($vitalKey);

        if ($value < $applicableRange['critical_min'] || $value > $applicableRange['critical_max']) {
            return ['level' => 'critical', 'message' => "{$label} critically abnormal ({$value})"];
        }

        if ($value < $applicableRange['warning_min'] || $value > $applicableRange['warning_max']) {
            return ['level' => 'warning', 'message' => "{$label} outside normal range ({$value})"];
        }

        return null;
    }

    /**
     * Assess blood pressure against standard ranges.
     */
    protected function assessBP(string $bpString, ?int $patientId = null): ?array
    {
        $parts = explode('/', $bpString);
        if (count($parts) !== 2) {
            return null;
        }

        $systolic = (float) trim($parts[0]);
        $diastolic = (float) trim($parts[1]);

        // Check systolic
        $sysAlert = $this->assessVital('bp_sys', $systolic, $patientId);
        if ($sysAlert) {
            $sysAlert['message'] = "BP {$bpString} — Systolic " . ($systolic > 140 ? 'elevated' : 'low');

            return $sysAlert;
        }

        // Check diastolic
        $diaAlert = $this->assessVital('bp_dia', $diastolic, $patientId);
        if ($diaAlert) {
            $diaAlert['message'] = "BP {$bpString} — Diastolic " . ($diastolic > 90 ? 'elevated' : 'low');

            return $diaAlert;
        }

        return null;
    }

    // ========================================================================
    // Summary & Narrative Builders
    // ========================================================================

    /**
     * Build the executive summary narrative for a shift.
     */
    protected function buildExecutiveSummary(
        NursingShift $shift,
        array $categoryCounts,
        array $patientData,
        array $alerts,
    ): string {
        $parts = [];

        // Duration line
        $durationStr = $shift->duration;
        if (empty($durationStr) || $durationStr === '0m') {
            $elapsed = $shift->elapsed_seconds ?? 0;
            $hours = floor($elapsed / 3600);
            $minutes = floor(($elapsed % 3600) / 60);
            $durationStr = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
        }

        $wardName = $shift->ward?->name ?? 'All Wards';
        $shiftLabel = NursingShift::SHIFT_TYPES[$shift->shift_type]['label'] ?? (ucfirst($shift->shift_type) . ' Shift');
        $cleanShiftLabel = preg_replace('/\s+Shift$/i', '', $shiftLabel);

        $parts[] = "<strong>{$cleanShiftLabel} Shift</strong> ({$wardName}) · Duration: {$durationStr}";

        // Patient count
        $patientCount = count($patientData);
        if ($patientCount > 0) {
            $parts[] = "<strong>Patients Attended:</strong> {$patientCount} patient" . ($patientCount > 1 ? 's' : '');
        }

        // Activity counts
        $activityLines = [];
        foreach ($categoryCounts as $cat => $counts) {
            if ($counts['total'] > 0) {
                $activityLines[] = "<i class=\"mdi {$counts['icon']} text-{$counts['color']}\"></i> {$cat}: {$counts['total']}";
            }
        }
        if (!empty($activityLines)) {
            $parts[] = "<strong>Activities:</strong> " . implode(' · ', $activityLines);
        }

        // Critical alerts
        $criticalAlerts = array_filter($alerts, fn ($a) => ($a['level'] ?? '') === 'critical');
        if (!empty($criticalAlerts)) {
            $alertMsgs = array_map(fn ($a) => $a['message'], $criticalAlerts);
            $parts[] = "<span class=\"text-danger\"><strong>⚠️ Critical Alerts:</strong> " . implode('; ', $alertMsgs) . "</span>";
        }

        if (empty($activityLines) && empty($criticalAlerts)) {
            $parts[] = "<span class=\"text-muted\">No significant nursing activities recorded during this shift.</span>";
        }

        return implode('<br>', $parts);
    }

    /**
     * Build a per-patient clinical summary.
     */
    protected function buildPatientSummary(array $patientData): array
    {
        $activities = $patientData['activities'];
        $grouped = [];

        foreach ($activities as $activity) {
            $cat = $activity['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'icon' => $activity['icon'],
                    'color' => $activity['color'],
                    'items' => [],
                ];
            }
            $grouped[$cat]['items'][] = [
                'line' => $activity['line'],
                'time' => $activity['time'],
                'event' => $activity['event'],
            ];
        }

        return [
            'patient_id' => $patientData['patient_id'],
            'patient_name' => $patientData['patient_name'],
            'patient_no' => $patientData['patient_no'],
            'total_events' => count($activities),
            'total_activities' => count($activities),
            'categories' => $grouped,
            'alerts' => $patientData['alerts'],
            'activities' => $activities,
        ];
    }

    // ========================================================================
    // Utility helpers
    // ========================================================================

    /**
     * Extract patient_id from audit old/new values.
     */
    protected function extractPatientId(array $old, array $new): ?int
    {
        $pid = $new['patient_id'] ?? $old['patient_id'] ?? null;

        return $pid ? (int) $pid : null;
    }

    /**
     * Get human-readable event label.
     */
    protected function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'Recorded',
            'updated' => 'Updated',
            'deleted' => 'Removed',
            default => ucfirst($event),
        };
    }

    /**
     * Build a concise change summary for updated events.
     */
    protected function buildChangeSummary(array $old, array $new, array $fieldMap): array
    {
        $changes = [];
        foreach ($fieldMap as $field => $label) {
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if ($oldVal !== $newVal && $newVal !== null) {
                $changes[] = [
                    'field' => $field,
                    'label' => $label,
                    'old' => $oldVal ?? '—',
                    'new' => $newVal,
                ];
            }
        }

        return $changes;
    }

    /**
     * Clean HTML content: strip tags and truncate for preview.
     */
    protected function cleanHtml(string $html, int $maxLength = 200): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) > $maxLength) {
            $text = Str::limit($text, $maxLength);
        }

        return $text;
    }
}

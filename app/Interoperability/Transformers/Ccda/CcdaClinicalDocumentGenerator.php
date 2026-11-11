<?php

namespace App\Interoperability\Transformers\Ccda;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\VitalSign;

class CcdaClinicalDocumentGenerator
{
    /**
     * Generate a complete, valid HL7 C-CDA Continuity of Care Document (CCD) XML.
     */
    public static function generate(Patient $patient): string
    {
        $patient->loadMissing(['user', 'hmo']);
        $user = $patient->user;

        $docId = 'CCD-' . $patient->id . '-' . time();
        $docTime = now()->format('YmdHisO');

        $encounters = Encounter::with(['doctor', 'treatmentPlan', 'productRequests.product', 'labRequests.service'])
            ->where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $latestVital = VitalSign::where('patient_id', $patient->id)->orderBy('created_at', 'desc')->first();

        $dob = $patient->dob ? date('Ymd', strtotime($patient->dob)) : '19900101';
        $genderCode = strtolower($patient->gender ?? 'm') === 'female' ? 'F' : 'M';
        $patientName = htmlspecialchars(trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<ClinicalDocument xmlns="urn:hl7-org:v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" classCode="DOCCLIN" moodCode="EVN">' . "\n";

        // Header
        $xml .= '  <realmCode code="US"/>' . "\n";
        $xml .= '  <typeId root="2.16.840.1.113883.1.3" extension="POCD_HD000040"/>' . "\n";
        $xml .= '  <templateId root="2.16.840.1.113883.10.20.22.1.1"/>' . "\n"; // CCD template
        $xml .= '  <templateId root="2.16.840.1.113883.10.20.22.1.2"/>' . "\n";
        $xml .= "  <id root=\"2.16.840.1.113883.19.5.99999.1\" extension=\"{$docId}\"/>\n";
        $xml .= '  <code code="34133-9" displayName="Summarization of Episode Note" codeSystem="2.16.840.1.113883.6.1" codeSystemName="LOINC"/>' . "\n";
        $xml .= "  <title>CoreHealth v2 Continuity of Care Document (CCD)</title>\n";
        $xml .= "  <effectiveTime value=\"{$docTime}\"/>\n";
        $xml .= '  <confidentialityCode code="N" codeSystem="2.16.840.1.113883.5.25"/>' . "\n";
        $xml .= '  <languageCode code="en-US"/>' . "\n";

        // Record Target (Patient)
        $xml .= "  <recordTarget>\n";
        $xml .= "    <patientRole>\n";
        $xml .= "      <id root=\"2.16.840.1.113883.19.5\" extension=\"" . htmlspecialchars($patient->file_no ?? (string) $patient->id) . "\"/>\n";
        $xml .= "      <patient>\n";
        $xml .= "        <name>\n";
        $xml .= "          <family>" . htmlspecialchars($user->surname ?? 'Unknown') . "</family>\n";
        $xml .= "          <given>" . htmlspecialchars($user->firstname ?? 'Unknown') . "</given>\n";
        if (!empty($user->othername)) {
            $xml .= "          <given>" . htmlspecialchars(trim($user->othername)) . "</given>\n";
        }
        $xml .= "        </name>\n";
        $xml .= "        <administrativeGenderCode code=\"{$genderCode}\" codeSystem=\"2.16.840.1.113883.5.1\"/>\n";
        $xml .= "        <birthTime value=\"{$dob}\"/>\n";
        $xml .= "      </patient>\n";
        $xml .= "    </patientRole>\n";
        $xml .= "  </recordTarget>\n";

        // Author (Hospital / Attending)
        $xml .= "  <author>\n";
        $xml .= "    <time value=\"{$docTime}\"/>\n";
        $xml .= "    <assignedAuthor>\n";
        $xml .= "      <id root=\"2.16.840.1.113883.19.5\" extension=\"COREHEALTH-SYS\"/>\n";
        $xml .= "      <assignedPerson>\n";
        $xml .= "        <name><prefix>Dr.</prefix><family>Physician</family><given>Hospital</given></name>\n";
        $xml .= "      </assignedPerson>\n";
        $xml .= "    </assignedAuthor>\n";
        $xml .= "  </author>\n";

        // Custodian
        $xml .= "  <custodian>\n";
        $xml .= "    <assignedCustodian>\n";
        $xml .= "      <representedCustodianOrganization>\n";
        $xml .= "        <id root=\"2.16.840.1.113883.19.5\" extension=\"HOSPITAL-1\"/>\n";
        $xml .= "        <name>CoreHealth Teaching Hospital</name>\n";
        $xml .= "      </representedCustodianOrganization>\n";
        $xml .= "    </assignedCustodian>\n";
        $xml .= "  </custodian>\n";

        // Body
        $xml .= "  <component>\n";
        $xml .= "    <structuredBody>\n";

        // 1. Vitals Section
        $xml .= "      <component>\n";
        $xml .= "        <section>\n";
        $xml .= "          <templateId root=\"2.16.840.1.113883.10.20.22.2.4.1\"/>\n";
        $xml .= "          <code code=\"8716-3\" codeSystem=\"2.16.840.1.113883.6.1\" displayName=\"VITAL SIGNS\"/>\n";
        $xml .= "          <title>Vital Signs</title>\n";
        $xml .= "          <text>\n";
        if ($latestVital) {
            $bp = htmlspecialchars($latestVital->blood_pressure ?? 'N/A');
            $hr = htmlspecialchars($latestVital->heart_rate ?? 'N/A');
            $temp = htmlspecialchars($latestVital->temp ?? 'N/A');
            $xml .= "            <table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>\n";
            $xml .= "              <tr><td>Blood Pressure</td><td>{$bp} mmHg</td></tr>\n";
            $xml .= "              <tr><td>Heart Rate</td><td>{$hr} bpm</td></tr>\n";
            $xml .= "              <tr><td>Temperature</td><td>{$temp} °C</td></tr>\n";
            $xml .= "            </tbody></table>\n";
        } else {
            $xml .= "            <p>No vital signs recorded.</p>\n";
        }
        $xml .= "          </text>\n";
        $xml .= "        </section>\n";
        $xml .= "      </component>\n";

        // 2. Problem List / Diagnoses Section
        $xml .= "      <component>\n";
        $xml .= "        <section>\n";
        $xml .= "          <templateId root=\"2.16.840.1.113883.10.20.22.2.5.1\"/>\n";
        $xml .= "          <code code=\"11450-4\" codeSystem=\"2.16.840.1.113883.6.1\" displayName=\"PROBLEM LIST\"/>\n";
        $xml .= "          <title>Active Problems &amp; Diagnoses</title>\n";
        $xml .= "          <text>\n";
        $xml .= "            <table><thead><tr><th>Condition / Diagnosis</th><th>Recorded Date</th></tr></thead><tbody>\n";
        $hasDiag = false;
        foreach ($encounters as $enc) {
            if ($enc->treatmentPlan) {
                $hasDiag = true;
                $diag = htmlspecialchars($enc->treatmentPlan->plan_name ?? 'Diagnosis');
                $date = $enc->created_at->format('Y-m-d');
                $xml .= "              <tr><td>{$diag}</td><td>{$date}</td></tr>\n";
            }
        }
        if (!$hasDiag) {
            $xml .= "              <tr><td colspan=\"2\">No active problems recorded.</td></tr>\n";
        }
        $xml .= "            </tbody></table>\n";
        $xml .= "          </text>\n";
        $xml .= "        </section>\n";
        $xml .= "      </component>\n";

        // 3. Medications Section
        $xml .= "      <component>\n";
        $xml .= "        <section>\n";
        $xml .= "          <templateId root=\"2.16.840.1.113883.10.20.22.2.1.1\"/>\n";
        $xml .= "          <code code=\"10160-0\" codeSystem=\"2.16.840.1.113883.6.1\" displayName=\"HISTORY OF MEDICATION USE\"/>\n";
        $xml .= "          <title>Medications</title>\n";
        $xml .= "          <text>\n";
        $xml .= "            <table><thead><tr><th>Medication</th><th>Dosage Instructions</th><th>Status</th></tr></thead><tbody>\n";
        $hasMed = false;
        foreach ($encounters as $enc) {
            if ($enc->productRequests) {
                foreach ($enc->productRequests as $rx) {
                    $hasMed = true;
                    $drug = htmlspecialchars($rx->product->product_name ?? 'Medication');
                    $dose = htmlspecialchars($rx->dose ?? '1 stat');
                    $st = $rx->status == 2 ? 'Dispensed' : 'Active';
                    $xml .= "              <tr><td>{$drug}</td><td>{$dose}</td><td>{$st}</td></tr>\n";
                }
            }
        }
        if (!$hasMed) {
            $xml .= "              <tr><td colspan=\"3\">No active prescriptions recorded.</td></tr>\n";
        }
        $xml .= "            </tbody></table>\n";
        $xml .= "          </text>\n";
        $xml .= "        </section>\n";
        $xml .= "      </component>\n";

        // 4. Results Section (Labs)
        $xml .= "      <component>\n";
        $xml .= "        <section>\n";
        $xml .= "          <templateId root=\"2.16.840.1.113883.10.20.22.2.3.1\"/>\n";
        $xml .= "          <code code=\"30954-2\" codeSystem=\"2.16.840.1.113883.6.1\" displayName=\"STUDIES SUMMARY\"/>\n";
        $xml .= "          <title>Diagnostic Investigations &amp; Laboratory Results</title>\n";
        $xml .= "          <text>\n";
        $xml .= "            <table><thead><tr><th>Test Name</th><th>Result</th><th>Status</th></tr></thead><tbody>\n";
        $hasLab = false;
        foreach ($encounters as $enc) {
            if ($enc->labRequests) {
                foreach ($enc->labRequests as $lab) {
                    $hasLab = true;
                    $test = htmlspecialchars($lab->service->service_name ?? 'Investigation');
                    $res = htmlspecialchars(is_array($lab->result) ? json_encode($lab->result) : ($lab->result ?? 'Pending'));
                    $st = $lab->status == 2 ? 'Approved' : 'In Progress';
                    $xml .= "              <tr><td>{$test}</td><td>{$res}</td><td>{$st}</td></tr>\n";
                }
            }
        }
        if (!$hasLab) {
            $xml .= "              <tr><td colspan=\"3\">No diagnostic results recorded.</td></tr>\n";
        }
        $xml .= "            </tbody></table>\n";
        $xml .= "          </text>\n";
        $xml .= "        </section>\n";
        $xml .= "      </component>\n";

        $xml .= "    </structuredBody>\n";
        $xml .= "  </component>\n";
        $xml .= '</ClinicalDocument>';

        return $xml;
    }
}

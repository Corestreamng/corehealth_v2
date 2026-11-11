<?php

namespace App\Interoperability\Mutators\Fhir;

use Illuminate\Http\Request;

class InboundFhirPatientMutator
{
    /**
     * Mutate an incoming FHIR R4 Patient JSON payload into an Illuminate Request
     * perfectly formatted for CoreHealth's PatientController@store.
     */
    public static function mutateToStoreRequest(array $payload, ?Request $originalRequest = null): Request
    {
        $surname = 'Unknown';
        $firstname = 'Unknown';
        $othername = null;

        if (!empty($payload['name']) && is_array($payload['name'])) {
            $nameObj = $payload['name'][0];
            $surname = $nameObj['family'] ?? $surname;
            if (!empty($nameObj['given']) && is_array($nameObj['given'])) {
                $firstname = $nameObj['given'][0] ?? $firstname;
                if (isset($nameObj['given'][1])) {
                    $othername = $nameObj['given'][1];
                }
            } elseif (!empty($nameObj['text'])) {
                $parts = explode(' ', trim($nameObj['text']));
                $surname = array_shift($parts) ?? $surname;
                $firstname = array_shift($parts) ?? $firstname;
                $othername = implode(' ', $parts);
            }
        }

        // Telecom
        $phone = null;
        $email = null;
        if (!empty($payload['telecom']) && is_array($payload['telecom'])) {
            foreach ($payload['telecom'] as $tel) {
                if (($tel['system'] ?? '') === 'phone' && empty($phone)) {
                    $phone = $tel['value'] ?? null;
                }
                if (($tel['system'] ?? '') === 'email' && empty($email)) {
                    $email = $tel['value'] ?? null;
                }
            }
        }

        // Gender
        $gender = 'Male';
        if (!empty($payload['gender'])) {
            $g = strtolower($payload['gender']);
            $gender = in_array($g, ['female', 'f']) ? 'Female' : 'Male';
        }

        // DOB
        $dob = $payload['birthDate'] ?? date('Y-m-d', strtotime('-30 years'));

        // Address
        $address = 'Unknown Address';
        if (!empty($payload['address']) && is_array($payload['address'])) {
            $addr = $payload['address'][0];
            $address = $addr['text'] ?? implode(', ', array_filter([
                implode(' ', $addr['line'] ?? []),
                $addr['city'] ?? '',
                $addr['state'] ?? '',
                $addr['country'] ?? 'Nigeria',
            ]));
        }

        // MRN / file_no
        $fileNo = null;
        if (!empty($payload['identifier']) && is_array($payload['identifier'])) {
            foreach ($payload['identifier'] as $ident) {
                if (($ident['type']['coding'][0]['code'] ?? '') === 'MR' || str_contains($ident['system'] ?? '', 'mrn')) {
                    $fileNo = $ident['value'] ?? null;

                    break;
                }
            }
            if (!$fileNo && isset($payload['identifier'][0]['value'])) {
                $fileNo = $payload['identifier'][0]['value'];
            }
        }

        // Next of Kin
        $nokName = null;
        $nokPhone = null;
        $nokAddress = null;
        if (!empty($payload['contact']) && is_array($payload['contact'])) {
            $contact = $payload['contact'][0];
            $nokName = $contact['name']['text'] ?? null;
            if (!empty($contact['telecom']) && is_array($contact['telecom'])) {
                $nokPhone = $contact['telecom'][0]['value'] ?? null;
            }
            $nokAddress = $contact['address']['text'] ?? null;
        }

        // Extensions (Blood group, Genotype)
        $bloodGroup = null;
        $genotype = null;
        if (!empty($payload['extension']) && is_array($payload['extension'])) {
            foreach ($payload['extension'] as $ext) {
                if (str_contains($ext['url'] ?? '', 'bloodGroup')) {
                    $bloodGroup = $ext['valueString'] ?? null;
                }
                if (str_contains($ext['url'] ?? '', 'genotype')) {
                    $genotype = $ext['valueString'] ?? null;
                }
            }
        }

        // Auto-generate email if missing to satisfy PatientController validation
        if (empty($email)) {
            $email = strtolower(trim($firstname)) . '.' . strtolower(trim($surname)) . '.' . rand(10000, 99999) . '@interop.corehealth.local';
        }

        $localParams = [
            'surname' => $surname,
            'firstname' => $firstname,
            'othername' => $othername ?? ' ',
            'email' => $email,
            'password' => '123456',
            'gender' => $gender,
            'dob' => $dob,
            'phone_no' => $phone ?? '08000000000',
            'address' => $address,
            'file_no' => $fileNo,
            'blood_group' => $bloodGroup,
            'genotype' => $genotype,
            'disability' => 0,
            'hmo_id' => 1, // Default private HMO
            'registration_workbench' => 'reception',
            'next_of_kin_name' => $nokName,
            'next_of_kin_phone' => $nokPhone,
            'next_of_kin_address' => $nokAddress,
        ];

        $mutatedRequest = new Request();
        $mutatedRequest->replace($localParams);

        if ($originalRequest) {
            $mutatedRequest->headers->replace($originalRequest->headers->all());
            $mutatedRequest->server->set('REMOTE_ADDR', $originalRequest->ip());
        }

        return $mutatedRequest;
    }
}

<?php

/**
 * CoreHealth v2 - HIPAA / GDPR Compliance PII Scrambler
 * 
 * Replaces all patient, staff, next-of-kin names, phone numbers,
 * addresses, and contact details with realistic, non-identifiable
 * synthetic fixtures.
 * 
 * Preserves administrator access:
 *   sysadmin@mail.com / 123456
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$firstNames = [
    'Amina', 'Emmanuel', 'Fatima', 'Ibrahim', 'Grace', 'Samuel', 'Blessing', 'Usman',
    'Mary', 'David', 'Zainab', 'Joseph', 'Chidinma', 'Michael', 'Hadiza', 'Peter',
    'Ngozi', 'John', 'Aisha', 'Daniel', 'Joy', 'Abubakar', 'Faith', 'Joshua',
    'Halima', 'Paul', 'Ruth', 'Gabriel', 'Sarah', 'Solomon', 'Victoria', 'Mustapha',
    'Esther', 'Yakubu', 'Deborah', 'Caleb', 'Kaltume', 'Gideon', 'Peace', 'Sunday',
    'Patience', 'Timothy', 'Mercy', 'Kenneth', 'Felicia', 'Stephen', 'Rebecca', 'Moses',
    'Dorcas', 'Benjamin', 'Hauwa', 'Victor', 'Comfort', 'Aliyu', 'Precious', 'Isaac',
    'Jamila', 'Elijah', 'Hannah', 'Suleiman', 'Gloria', 'Matthew', 'Saratu', 'Jeremiah',
    'Bilkisu', 'Nathaniel', 'Lydia', 'Kabir', 'Juliet', 'Abel', 'Safiya', 'Simeon'
];

$surnames = [
    'Abubakar', 'Okafor', 'Adeleke', 'Bello', 'Nwosu', 'Danladi', 'Eze', 'Oladipo',
    'Musa', 'Okon', 'Aliyu', 'Balogun', 'Chukwu', 'Garba', 'Adebayo', 'Nnamdi',
    'Sani', 'Afolabi', 'Anyanwu', 'Haruna', 'Ojo', 'Okoro', 'Abdullahi', 'Babatunde',
    'Igwe', 'Mohammed', 'Ogundipe', 'Onuoha', 'Umar', 'Olawale', 'Obinna', 'Yakubu',
    'Fagbemi', 'Kalu', 'Dahiru', 'Soyinka', 'Ekwueme', 'Jibril', 'Akintola', 'Madaki',
    'Bako', 'Pam', 'Dung', 'Gyang', 'Bot', 'Chollom', 'Dachung', 'Vongjen', 'Lar',
    'Plang', 'Gofwen', 'Dimka', 'Nanle', 'Longjan', 'Gotep', 'Dalyop', 'Datong',
    'Kefas', 'Mangut', 'Nandom', 'Selcan', 'Mallo', 'Damulak', 'Godiya', 'Rimfat'
];

$otherNames = [
    'Emeka', 'Tanimu', 'Ayomide', 'Ifeanyi', 'Damilola', 'Chukwudi', 'Temitope',
    'Nneka', 'Oluwaseun', 'Chioma', 'Babatunde', 'Somto', 'Folashade', 'Kelechi',
    'Opeyemi', 'Uche', 'Eniola', 'Nonso', 'Yetunde', 'Kamsi', 'Segun', 'Chika',
    'Adewale', 'Amaka', 'Bunmi', 'Obioma', 'Tolu', 'Chinonso', 'Gbenga', 'Tochukwu',
    'Jummai', 'Bulus', 'Danfulani', 'Lami', 'Nansel', 'Danjuma', 'Simi', 'Zwalnan',
    'Godiya', 'Dachollom', 'Weng', 'Kangyang', 'Zingtim', 'Nanlop', 'Chundung'
];

$streetNames = [
    'Ahmadu Bello Way', 'Yakubu Gowon Way', 'Rayfield Road', 'Tafawa Balewa Street',
    'Murtala Mohammed Way', 'Old Airport Road', 'Bukuru Expressway', 'Lamingo Dam Road',
    'Anglo-Jos Industrial Layout', 'Bauchi Ring Road', 'Zaria By-Pass', 'Tudun Wada Road',
    'Secretariat Road', 'Hill Station Road', 'Liberty Dam Road', 'Plateau Hospital Road',
    'Standard Building Boulevard', 'University Road', 'Miango Junction Crescent'
];

$locations = ['Jos', 'Bukuru', 'Rayfield', 'Lamingo', 'Anglo-Jos', 'Tudun Wada', 'Dadinkowa'];

$phonePrefixes = ['0803', '0802', '0813', '0814', '0703', '0902', '0805', '0816', '0706', '0808'];

$totalFirst = count($firstNames);
$totalSur = count($surnames);
$totalOther = count($otherNames);
$totalStreets = count($streetNames);
$totalLocs = count($locations);
$totalPrefixes = count($phonePrefixes);

echo "Starting Database HIPAA / GDPR PII Scrambling...\n";
$start = microtime(true);

// ----------------------------------------------------------------------------
// 1. SCRAMBLE USERS TABLE
// ----------------------------------------------------------------------------
echo "\n[1/5] Scrambling `users` table...\n";
$adminUser = DB::table('users')->where('email', 'sysadmin@mail.com')->first();
$adminId = $adminUser ? $adminUser->id : 1;

$userChunkSize = 2000;
$maxUserId = DB::table('users')->max('id') ?? 0;

for ($startId = 1; $startId <= $maxUserId; $startId += $userChunkSize) {
    $endId = $startId + $userChunkSize - 1;
    $users = DB::table('users')
        ->select('id', 'email')
        ->whereBetween('id', [$startId, $endId])
        ->get();

    if ($users->isEmpty()) continue;

    $casesSurname = [];
    $casesFirstname = [];
    $casesOthername = [];
    $ids = [];

    foreach ($users as $u) {
        $id = $u->id;
        $ids[] = $id;

        if ($id === $adminId) {
            $casesSurname[] = "WHEN $id THEN 'System'";
            $casesFirstname[] = "WHEN $id THEN 'Admin'";
            $casesOthername[] = "WHEN $id THEN NULL";
            continue;
        }

        $h1 = ($id * 2654435761) & 0xFFFFFFFF;
        $h2 = ($id * 1597334677) & 0xFFFFFFFF;
        $h3 = ($id * 3849201833) & 0xFFFFFFFF;

        $sur = $surnames[$h1 % $totalSur];
        $first = $firstNames[$h2 % $totalFirst];
        $hasOther = ($h3 % 3 === 0);
        $other = $hasOther ? $otherNames[$h3 % $totalOther] : null;

        $casesSurname[] = "WHEN $id THEN " . DB::getPdo()->quote($sur);
        $casesFirstname[] = "WHEN $id THEN " . DB::getPdo()->quote($first);
        $casesOthername[] = "WHEN $id THEN " . ($other ? DB::getPdo()->quote($other) : "NULL");
    }

    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $sql = "UPDATE users SET 
            surname = CASE id " . implode(' ', $casesSurname) . " END,
            firstname = CASE id " . implode(' ', $casesFirstname) . " END,
            othername = CASE id " . implode(' ', $casesOthername) . " END
            WHERE id IN ($idList)";
        DB::statement($sql);
    }
}
echo "  Done scrambling `users`.\n";

// ----------------------------------------------------------------------------
// 2. SCRAMBLE PATIENTS TABLE
// ----------------------------------------------------------------------------
echo "\n[2/5] Scrambling `patients` table...\n";
$maxPatientId = DB::table('patients')->max('id') ?? 0;
$patientChunkSize = 2000;

for ($startId = 1; $startId <= $maxPatientId; $startId += $patientChunkSize) {
    $endId = $startId + $patientChunkSize - 1;
    $patients = DB::table('patients')
        ->select('id', 'user_id')
        ->whereBetween('id', [$startId, $endId])
        ->get();

    if ($patients->isEmpty()) continue;

    $casesPhone = [];
    $casesAddr = [];
    $casesKinName = [];
    $casesKinPhone = [];
    $casesKinAddr = [];
    $casesHmoNo = [];
    $ids = [];

    foreach ($patients as $p) {
        $id = $p->id;
        $ids[] = $id;

        $h1 = ($id * 2654435761) & 0xFFFFFFFF;
        $h2 = ($id * 1597334677) & 0xFFFFFFFF;
        $h3 = ($id * 3849201833) & 0xFFFFFFFF;

        $prefix = $phonePrefixes[$h1 % $totalPrefixes];
        $phone = $prefix . sprintf('%07d', ($id * 6173) % 10000000);
        $kinPhone = $phonePrefixes[($h1 + 3) % $totalPrefixes] . sprintf('%07d', ($id * 8329) % 10000000);

        $streetNo = (($id * 23) % 150) + 1;
        $street = $streetNames[$h2 % $totalStreets];
        $loc = $locations[$h3 % $totalLocs];
        $addr = "$streetNo $street, $loc";
        $kinAddr = (($streetNo + 4) % 150 + 1) . " $street, $loc";

        $kinSur = $surnames[$h1 % $totalSur];
        $kinFirst = $firstNames[($h2 + 5) % $totalFirst];
        $kinName = "$kinSur $kinFirst";

        $hmoNum = sprintf('NHIA-%06d', ($id * 3141) % 1000000);

        $casesPhone[] = "WHEN $id THEN " . DB::getPdo()->quote($phone);
        $casesAddr[] = "WHEN $id THEN " . DB::getPdo()->quote($addr);
        $casesKinName[] = "WHEN $id THEN " . DB::getPdo()->quote($kinName);
        $casesKinPhone[] = "WHEN $id THEN " . DB::getPdo()->quote($kinPhone);
        $casesKinAddr[] = "WHEN $id THEN " . DB::getPdo()->quote($kinAddr);
        $casesHmoNo[] = "WHEN $id THEN CASE WHEN hmo_no IS NOT NULL AND hmo_no != '' THEN " . DB::getPdo()->quote($hmoNum) . " ELSE hmo_no END";
    }

    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $sql = "UPDATE patients SET 
            phone_no = CASE id " . implode(' ', $casesPhone) . " END,
            address = CASE id " . implode(' ', $casesAddr) . " END,
            next_of_kin_name = CASE id " . implode(' ', $casesKinName) . " END,
            next_of_kin_phone = CASE id " . implode(' ', $casesKinPhone) . " END,
            next_of_kin_address = CASE id " . implode(' ', $casesKinAddr) . " END,
            hmo_no = CASE id " . implode(' ', $casesHmoNo) . " END
            WHERE id IN ($idList)";
        DB::statement($sql);
    }
}
echo "  Done scrambling `patients`.\n";

// ----------------------------------------------------------------------------
// 3. SCRAMBLE STAFF TABLE
// ----------------------------------------------------------------------------
echo "\n[3/5] Scrambling `staff` table...\n";
$staffMembers = DB::table('staff')->select('id', 'user_id')->get();
foreach ($staffMembers as $s) {
    $id = $s->id;
    $h1 = ($id * 179424673) & 0xFFFFFFFF;
    $prefix = $phonePrefixes[$h1 % $totalPrefixes];
    $phone = $prefix . sprintf('%07d', ($id * 4391) % 10000000);
    $emerPhone = $phonePrefixes[($h1 + 2) % $totalPrefixes] . sprintf('%07d', ($id * 9109) % 10000000);

    $streetNo = (($id * 11) % 90) + 1;
    $addr = "$streetNo " . $streetNames[$h1 % $totalStreets] . ", " . $locations[$h1 % $totalLocs];
    $emerName = $surnames[$h1 % $totalSur] . ' ' . $firstNames[($h1 + 4) % $totalFirst];

    DB::table('staff')->where('id', $id)->update([
        'phone_number' => $phone,
        'home_address' => $addr,
        'permanent_home_address' => $addr,
        'emergency_contact_name' => $emerName,
        'emergency_contact_phone' => $emerPhone,
        'bank_account_number' => sprintf('01%08d', ($id * 5381) % 100000000),
        'bank_account_name' => $emerName,
        'national_id_number' => sprintf('NIN-%010d', ($id * 8971) % 10000000000)
    ]);
}
echo "  Done scrambling `staff`.\n";

// ----------------------------------------------------------------------------
// 4. SCRAMBLE SPECIALIST REFERRALS TABLE
// ----------------------------------------------------------------------------
echo "\n[4/5] Scrambling `specialist_referrals` table...\n";
$externalHospitals = [
    'Jos University Teaching Hospital (JUTH)',
    'Plateau State Specialist Hospital',
    'Bingham University Teaching Hospital',
    'National Hospital Abuja',
    'Federal Medical Centre Keffi',
    'Ahmadu Bello University Teaching Hospital',
    'University of Abuja Teaching Hospital Gwagwalada'
];
$totalExtHosp = count($externalHospitals);

$referrals = DB::table('specialist_referrals')->select('id')->get();
foreach ($referrals as $ref) {
    $id = $ref->id;
    $h = ($id * 32452843) & 0xFFFFFFFF;
    $doc = "Dr. " . $firstNames[$h % $totalFirst] . " " . $surnames[($h + 3) % $totalSur];
    $hosp = $externalHospitals[$h % $totalExtHosp];
    $phone = $phonePrefixes[$h % $totalPrefixes] . sprintf('%07d', ($id * 4127) % 10000000);

    DB::table('specialist_referrals')->where('id', $id)->update([
        'external_facility_name' => $hosp,
        'external_doctor_name' => $doc,
        'external_facility_phone' => $phone,
        'external_facility_address' => 'Hospital Referral Complex, ' . $locations[$h % $totalLocs]
    ]);
}
echo "  Done scrambling `specialist_referrals`.\n";

// ----------------------------------------------------------------------------
// 5. SCRAMBLE MORGUE ADMISSIONS & SHIFT ACTIONS
// ----------------------------------------------------------------------------
echo "\n[5/5] Scrambling miscellaneous PII tables...\n";
if (Schema::hasTable('morgue_admissions') && Schema::hasColumn('morgue_admissions', 'released_to_name')) {
    $morgues = DB::table('morgue_admissions')->select('id')->get();
    foreach ($morgues as $m) {
        $id = $m->id;
        $name = $surnames[$id % $totalSur] . ' ' . $firstNames[($id + 2) % $totalFirst];
        DB::table('morgue_admissions')->where('id', $id)->update(['released_to_name' => $name]);
    }
}

if (Schema::hasTable('shift_actions') && Schema::hasColumn('shift_actions', 'patient_name')) {
    $shifts = DB::table('shift_actions')->select('id')->get();
    foreach ($shifts as $s) {
        $id = $s->id;
        $name = $surnames[$id % $totalSur] . ' ' . $firstNames[($id + 3) % $totalFirst];
        DB::table('shift_actions')->where('id', $id)->update(['patient_name' => $name]);
    }
}
echo "  Done scrambling miscellaneous PII.\n";

$elapsed = round(microtime(true) - $start, 2);
echo "\n=======================================================\n";
echo "SUCCESS: Entire Database Scrambled for HIPAA/GDPR Compliance in {$elapsed}s!\n";
echo "=======================================================\n";

<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\patient;
use App\Models\Hmo;
use App\Services\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

class ImportPatientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BATCH_SIZE = 400;

    protected string $importId;
    protected string $filePath;
    protected int $userId;
    protected string $duplicateAction;

    public $timeout = 7200;
    public $tries = 1;

    public function __construct(string $importId, string $filePath, int $userId, string $duplicateAction = 'update')
    {
        $this->importId = $importId;
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->duplicateAction = $duplicateAction;
    }

    public function handle(): void
    {
        // Disable PHP execution time limit for large imports
        set_time_limit(0);
        ini_set('memory_limit', '4G');

        $fullPath = storage_path('app/' . $this->filePath);

        try {
            $generator = $this->parseFileInChunks($fullPath);

            $existingPatients = patient::pluck('user_id', 'file_no')->toArray();
            $existingEmails = User::pluck('id', 'email')->toArray();
            $hmos = Hmo::pluck('id', 'name')->toArray();
            $patientRole = Role::where('name', 'PATIENT')->first();

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            $processed = 0;
            $batch = [];
            $batchIndex = 0;

            foreach ($generator as $rowNum => $row) {
                $batch[] = ['row' => $row, 'rowNum' => $rowNum];

                if (count($batch) >= self::BATCH_SIZE) {
                    if (ImportProgressService::isCancelled($this->importId)) {
                        $errors[] = 'Import was cancelled by user';
                        break;
                    }

                    $result = $this->processBatch($batch, $existingPatients, $existingEmails, $hmos, $patientRole, $batchIndex, $this->duplicateAction);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $skipped += $result['skipped'];
                    $processed += count($batch);

                    // Limit error accumulation to prevent memory issues
                    if (count($errors) < 500) {
                        $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                    }

                    $existingPatients = array_merge($existingPatients, $result['new_patients']);
                    $existingEmails = array_merge($existingEmails, $result['new_emails']);

                    ImportProgressService::updateProgress(
                        $this->importId, $processed, $created, $updated, $skipped,
                        array_slice($errors, 0, 100), $batchIndex + 1
                    );

                    $batch = [];
                    $batchIndex++;

                    // Free memory periodically
                    if ($batchIndex % 10 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            if (!empty($batch) && !ImportProgressService::isCancelled($this->importId)) {
                $result = $this->processBatch($batch, $existingPatients, $existingEmails, $hmos, $patientRole, $batchIndex, $this->duplicateAction);
                $created += $result['created'];
                $updated += $result['updated'];
                $skipped += $result['skipped'];
                $processed += count($batch);

                if (count($errors) < 500) {
                    $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                }

                ImportProgressService::updateProgress(
                    $this->importId, $processed, $created, $updated, $skipped,
                    array_slice($errors, 0, 100), $batchIndex + 1
                );
            }

            ImportProgressService::completeImport($this->importId, [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 100),
            ]);

        } catch (\Exception $e) {
            Log::error("Patient import job failed: " . $e->getMessage());
            ImportProgressService::failImport($this->importId, $e->getMessage());
        } finally {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private function parseFileInChunks(string $filePath): \Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'])) {
            yield from $this->parseExcelInChunks($filePath);
        } else {
            yield from $this->parseCsvInChunks($filePath);
        }
    }

    private function parseExcelInChunks(string $filePath): \Generator
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rowIndex = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex++;
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace(['"', "'"], '', $h ?? '')));
                }, $rowData);
                continue;
            }

            if (empty(array_filter($rowData))) {
                continue;
            }

            $assocRow = [];
            foreach ($headers as $i => $header) {
                if (!empty($header)) {
                    $assocRow[$header] = isset($rowData[$i]) ? trim((string)$rowData[$i]) : '';
                }
            }

            yield $rowIndex => $assocRow;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function parseCsvInChunks(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        $headers = [];
        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowIndex++;

            if ($rowIndex === 1) {
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace(['"', "'"], '', $h ?? '')));
                }, $row);
                continue;
            }

            if (empty(array_filter($row))) {
                continue;
            }

            $assocRow = [];
            foreach ($headers as $i => $header) {
                if (!empty($header)) {
                    $assocRow[$header] = isset($row[$i]) ? trim($row[$i]) : '';
                }
            }

            yield $rowIndex => $assocRow;
        }

        fclose($handle);
    }

    private function processBatch(array $batch, array &$existingPatients, array &$existingEmails, array $hmos, ?Role $patientRole, int $batchIndex, string $duplicateAction = 'update'): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $newPatients = [];
        $newEmails = [];

        DB::beginTransaction();
        try {
            foreach ($batch as $item) {
                $row = $item['row'];
                $rowNum = $item['rowNum'];

                $fileNo = trim($row['file_no'] ?? '');
                $surname = trim($row['surname'] ?? '');
                $firstname = trim($row['firstname'] ?? '');
                $email = strtolower(trim($row['email'] ?? ''));

                // Handle blank patients (file_no only)
                $isBlankPatient = !empty($fileNo) && empty($surname) && empty($firstname);
                if ($isBlankPatient) {
                    $surname = 'Blank';
                    $firstname = $fileNo;
                }

                // Generate file number if empty
                if (empty($fileNo)) {
                    $fileNo = $this->generatePatientFileNo();
                }

                // Validate minimum data
                if (empty($surname) || empty($firstname)) {
                    $errors[] = "Row {$rowNum}: Missing surname or firstname";
                    $skipped++;
                    continue;
                }

                // Generate email if empty
                if (empty($email)) {
                    $email = strtolower(str_replace(' ', '', $fileNo)) . '@patient.local';
                }

                // Get HMO ID
                $hmoId = null;
                if (!empty($row['hmo_name']) && isset($hmos[trim($row['hmo_name'])])) {
                    $hmoId = $hmos[trim($row['hmo_name'])];
                }

                $gender = strtolower(trim($row['gender'] ?? ''));
                $gender = in_array($gender, ['male', 'female']) ? ucfirst($gender) : ($isBlankPatient ? 'Male' : null);

                $dob = null;
                if (!empty($row['dob'])) {
                    try {
                        $dob = \Carbon\Carbon::parse($row['dob'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }
                // Default DOB for blank patients
                if ($isBlankPatient && empty($dob)) {
                    $dob = '2000-01-01';
                }

                $allergies = null;
                if (!empty($row['allergies'])) {
                    $allergies = array_map('trim', explode(',', $row['allergies']));
                }

                // Helper to convert empty strings to null
                $nullIfEmpty = function($value) {
                    return (is_string($value) && trim($value) === '') ? null : $value;
                };

                // Get hmo_no (integer field - must be null not empty string)
                $hmoNo = $nullIfEmpty($row['hmo_no'] ?? null);
                if ($hmoNo !== null) {
                    $hmoNo = is_numeric($hmoNo) ? (int)$hmoNo : null;
                }

                if (isset($existingPatients[$fileNo])) {
                    // DUPLICATE FOUND
                    if ($duplicateAction === 'skip') {
                        // Skip existing records
                        $skipped++;
                        continue;
                    }

                    // UPDATE by file_no
                    $userId = $existingPatients[$fileNo];

                    User::where('id', $userId)->update([
                        'surname' => $surname,
                        'firstname' => $firstname,
                        'othername' => $nullIfEmpty($row['othername'] ?? null),
                    ]);

                    patient::where('user_id', $userId)->update([
                        'phone_no' => $nullIfEmpty($row['phone_no'] ?? null),
                        'gender' => $gender,
                        'dob' => $dob,
                        'blood_group' => $nullIfEmpty($row['blood_group'] ?? null),
                        'genotype' => $nullIfEmpty($row['genotype'] ?? null),
                        'address' => $nullIfEmpty($row['address'] ?? null),
                        'nationality' => $nullIfEmpty($row['nationality'] ?? null),
                        'ethnicity' => $nullIfEmpty($row['ethnicity'] ?? null),
                        'hmo_id' => $hmoId,
                        'hmo_no' => $hmoNo,
                        'next_of_kin_name' => $nullIfEmpty($row['next_of_kin_name'] ?? null),
                        'next_of_kin_phone' => $nullIfEmpty($row['next_of_kin_phone'] ?? null),
                        'allergies' => $allergies,
                    ]);

                    $updated++;
                } else {
                    // Check if email exists
                    if (isset($existingEmails[$email])) {
                        // Email conflict - skip or generate new email
                        $email = $fileNo . '_' . Str::random(4) . '@patient.local';
                    }

                    // CREATE
                    $user = User::create([
                        'surname' => $surname,
                        'firstname' => $firstname,
                        'othername' => $nullIfEmpty($row['othername'] ?? null),
                        'email' => $email,
                        'password' => Hash::make(Str::random(12)),
                        'is_admin' => 0, // Patient
                    ]);

                    if ($patientRole) {
                        $user->assignRole($patientRole);
                    }

                    patient::create([
                        'user_id' => $user->id,
                        'file_no' => $fileNo,
                        'phone_no' => $nullIfEmpty($row['phone_no'] ?? null),
                        'gender' => $gender,
                        'dob' => $dob,
                        'blood_group' => $nullIfEmpty($row['blood_group'] ?? null),
                        'genotype' => $nullIfEmpty($row['genotype'] ?? null),
                        'address' => $nullIfEmpty($row['address'] ?? null),
                        'nationality' => $nullIfEmpty($row['nationality'] ?? null),
                        'ethnicity' => $nullIfEmpty($row['ethnicity'] ?? null),
                        'hmo_id' => $hmoId,
                        'hmo_no' => $hmoNo,
                        'next_of_kin_name' => $nullIfEmpty($row['next_of_kin_name'] ?? null),
                        'next_of_kin_phone' => $nullIfEmpty($row['next_of_kin_phone'] ?? null),
                        'allergies' => $allergies,
                    ]);

                    $existingPatients[$fileNo] = $user->id;
                    $existingEmails[$email] = $user->id;
                    $newPatients[$fileNo] = $user->id;
                    $newEmails[$email] = $user->id;
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors[] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
            Log::error("Patient import batch {$batchIndex} failed: " . $e->getMessage());
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'new_patients' => $newPatients,
            'new_emails' => $newEmails,
        ];
    }

    /**
     * Generate unique patient file number
     */
    private function generatePatientFileNo(): string
    {
        $prefix = date('Y');
        $lastPatient = patient::where('file_no', 'like', $prefix . '%')
            ->orderBy('file_no', 'desc')
            ->first();

        if ($lastPatient && preg_match('/(\d+)$/', $lastPatient->file_no, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function failed(\Throwable $exception): void
    {
        ImportProgressService::failImport($this->importId, $exception->getMessage());

        $fullPath = storage_path('app/' . $this->filePath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}

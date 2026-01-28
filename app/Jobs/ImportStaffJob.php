<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Staff;
use App\Models\Specialization;
use App\Models\Clinic;
use App\Models\Department;
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

class ImportStaffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BATCH_SIZE = 400;

    protected string $importId;
    protected string $filePath;
    protected string $defaultPassword;
    protected int $userId;
    protected string $duplicateAction;

    public $timeout = 7200;
    public $tries = 1;

    public function __construct(string $importId, string $filePath, string $defaultPassword, int $userId, string $duplicateAction = 'update')
    {
        $this->importId = $importId;
        $this->filePath = $filePath;
        $this->defaultPassword = $defaultPassword;
        $this->userId = $userId;
        $this->duplicateAction = $duplicateAction;
    }

    public function handle(): void
    {
        // Disable PHP execution time limit for large imports
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $fullPath = storage_path('app/' . $this->filePath);

        try {
            $generator = $this->parseFileInChunks($fullPath);

            $existingUsers = User::where('is_admin', 2)->pluck('id', 'email')->toArray();
            $specializations = Specialization::pluck('id', 'name')->toArray();
            $clinics = Clinic::pluck('id', 'name')->toArray();
            $departments = Department::pluck('id', 'name')->toArray();
            $roles = Role::pluck('id', 'name')->toArray();

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

                    $result = $this->processBatch($batch, $existingUsers, $specializations, $clinics, $departments, $roles, $batchIndex, $this->duplicateAction);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $skipped += $result['skipped'];
                    $processed += count($batch);

                    // Limit error accumulation to prevent memory issues
                    if (count($errors) < 500) {
                        $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                    }

                    $existingUsers = array_merge($existingUsers, $result['new_users']);

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
                $result = $this->processBatch($batch, $existingUsers, $specializations, $clinics, $departments, $roles, $batchIndex, $this->duplicateAction);
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
            Log::error("Staff import job failed: " . $e->getMessage());
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

    private function processBatch(array $batch, array &$existingUsers, array $specializations, array $clinics, array $departments, array $roles, int $batchIndex, string $duplicateAction = 'update'): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $newUsers = [];

        DB::beginTransaction();
        try {
            foreach ($batch as $item) {
                $row = $item['row'];
                $rowNum = $item['rowNum'];

                if (empty($row['email']) || empty($row['surname']) || empty($row['firstname'])) {
                    $errors[] = "Row {$rowNum}: Missing email, surname, or firstname";
                    $skipped++;
                    continue;
                }

                $email = strtolower(trim($row['email']));
                $surname = trim($row['surname']);
                $firstname = trim($row['firstname']);

                // Validate role
                $roleName = trim($row['role'] ?? $row['roles'] ?? '');
                if (empty($roleName) || !isset($roles[$roleName])) {
                    $errors[] = "Row {$rowNum}: Invalid or missing role '{$roleName}'";
                    $skipped++;
                    continue;
                }

                // Get specialization, clinic, department IDs
                $specializationId = null;
                if (!empty($row['specialization']) && isset($specializations[trim($row['specialization'])])) {
                    $specializationId = $specializations[trim($row['specialization'])];
                }

                $clinicId = null;
                if (!empty($row['clinic']) && isset($clinics[trim($row['clinic'])])) {
                    $clinicId = $clinics[trim($row['clinic'])];
                }

                $departmentId = null;
                if (!empty($row['department']) && isset($departments[trim($row['department'])])) {
                    $departmentId = $departments[trim($row['department'])];
                }

                $gender = strtolower(trim($row['gender'] ?? ''));
                $gender = in_array($gender, ['male', 'female']) ? ucfirst($gender) : null;

                $dob = null;
                if (!empty($row['date_of_birth'])) {
                    try {
                        $dob = \Carbon\Carbon::parse($row['date_of_birth'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                $dateHired = null;
                if (!empty($row['date_hired'])) {
                    try {
                        $dateHired = \Carbon\Carbon::parse($row['date_hired'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                if (isset($existingUsers[$email])) {
                    // DUPLICATE FOUND
                    if ($duplicateAction === 'skip') {
                        // Skip existing records
                        $skipped++;
                        continue;
                    }

                    // UPDATE existing record
                    $userId = $existingUsers[$email];

                    User::where('id', $userId)->update([
                        'surname' => $surname,
                        'firstname' => $firstname,
                        'othername' => $row['othername'] ?? null,
                    ]);

                    $user = User::find($userId);
                    $user->syncRoles([$roleName]);

                    Staff::where('user_id', $userId)->update([
                        'phone_number' => $row['phone_number'] ?? null,
                        'gender' => $gender,
                        'date_of_birth' => $dob,
                        'home_address' => $row['home_address'] ?? null,
                        'specialization_id' => $specializationId,
                        'clinic_id' => $clinicId,
                        'department_id' => $departmentId,
                        'job_title' => $row['job_title'] ?? null,
                        'date_hired' => $dateHired,
                        'consultation_fee' => floatval($row['consultation_fee'] ?? 0),
                    ]);

                    $updated++;
                } else {
                    // CREATE
                    $user = User::create([
                        'surname' => $surname,
                        'firstname' => $firstname,
                        'othername' => $row['othername'] ?? null,
                        'email' => $email,
                        'password' => Hash::make($this->defaultPassword),
                        'is_admin' => 2, // Staff
                    ]);

                    $user->assignRole($roleName);

                    Staff::create([
                        'user_id' => $user->id,
                        'employee_id' => $row['employee_id'] ?? ('EMP' . str_pad($user->id, 6, '0', STR_PAD_LEFT)),
                        'phone_number' => $row['phone_number'] ?? null,
                        'gender' => $gender,
                        'date_of_birth' => $dob,
                        'home_address' => $row['home_address'] ?? null,
                        'specialization_id' => $specializationId,
                        'clinic_id' => $clinicId,
                        'department_id' => $departmentId,
                        'job_title' => $row['job_title'] ?? null,
                        'date_hired' => $dateHired,
                        'employment_type' => $row['employment_type'] ?? 'full_time',
                        'employment_status' => $row['employment_status'] ?? 'active',
                        'consultation_fee' => floatval($row['consultation_fee'] ?? 0),
                        'is_unit_head' => ($row['is_unit_head'] ?? 0) == 1,
                        'is_dept_head' => ($row['is_dept_head'] ?? 0) == 1,
                        'bank_name' => $row['bank_name'] ?? null,
                        'bank_account_number' => $row['bank_account_number'] ?? null,
                        'bank_account_name' => $row['bank_account_name'] ?? null,
                        'emergency_contact_name' => $row['emergency_contact_name'] ?? null,
                        'emergency_contact_phone' => $row['emergency_contact_phone'] ?? null,
                        'emergency_contact_relationship' => $row['emergency_contact_relationship'] ?? null,
                        'tax_id' => $row['tax_id'] ?? null,
                        'pension_id' => $row['pension_id'] ?? null,
                    ]);

                    $existingUsers[$email] = $user->id;
                    $newUsers[$email] = $user->id;
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors[] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
            Log::error("Staff import batch {$batchIndex} failed: " . $e->getMessage());
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'new_users' => $newUsers,
        ];
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

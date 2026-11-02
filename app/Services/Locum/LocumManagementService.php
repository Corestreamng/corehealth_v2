<?php

namespace App\Services\Locum;

use App\Models\LocumPass;
use App\Models\LocumProfile;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Enterprise Locum Management Service
 *
 * Implements JIT provisioning, Spatie role assignment at creation,
 * shift-bounded ephemeral pass generation, and revocation enforcement.
 */
class LocumManagementService
{
    /**
     * Map of cadre templates to default Spatie roles and User Categories.
     */
    public const CADRE_CONFIG = [
        'DOCTOR' => [
            'label' => 'Locum Doctor / Consultant',
            'roles' => ['DOCTOR'],
            'is_admin' => 21, // Doctor
            'regulatory_body' => 'MDCN',
        ],
        'RADIOLOGIST' => [
            'label' => 'Locum Radiologist / Sonologist',
            'roles' => ['RADIOLOGIST'],
            'is_admin' => 25, // Others / Diagnostic
            'regulatory_body' => 'RRBN',
        ],
        'LAB_SCIENTIST' => [
            'label' => 'Locum Lab Scientist / Pathologist',
            'roles' => ['LAB SCIENTIST'],
            'is_admin' => 24, // Lab Tech
            'regulatory_body' => 'MLSCN',
        ],
        'NURSE' => [
            'label' => 'Locum Nurse / Midwife',
            'roles' => ['NURSE'],
            'is_admin' => 22, // Nurse
            'regulatory_body' => 'NMCN',
        ],
        'PHARMACIST' => [
            'label' => 'Locum Pharmacist',
            'roles' => ['PHARMACIST'],
            'is_admin' => 23, // Pharmacist
            'regulatory_body' => 'PCN',
        ],
        'CUSTOM' => [
            'label' => 'Custom Clinical Role',
            'roles' => [],
            'is_admin' => 25,
            'regulatory_body' => 'OTHER',
        ],
    ];

    /**
     * Create a new locum practitioner with assigned Spatie roles and shift pass.
     *
     * @param array $data
     * @param User|null $creator
     * @return array
     */
    public function createLocum(array $data, ?User $creator = null): array
    {
        return DB::transaction(function () use ($data, $creator) {
            $creatorId = $creator ? $creator->id : auth()->id();

            // 1. Resolve cadre and roles
            $cadre = $data['cadre_template'] ?? 'DOCTOR';
            $cadreConfig = self::CADRE_CONFIG[$cadre] ?? self::CADRE_CONFIG['DOCTOR'];

            $roles = !empty($data['roles']) ? (array) $data['roles'] : $cadreConfig['roles'];
            $isAdmin = $data['is_admin'] ?? $cadreConfig['is_admin'];

            // 2. Credentials
            $plainPassword = $data['password'] ?? Str::random(10);
            $email = strtolower(trim($data['email']));

            // Check if user already exists with this email
            $user = User::where('email', $email)->first();

            if (!$user) {
                $user = new User();
                $user->email = $email;
                $user->password = Hash::make($plainPassword);
                $user->filename = 'avatar.png';
            }

            $user->surname = trim($data['surname'] ?? '');
            $user->firstname = trim($data['firstname'] ?? '');
            $user->othername = trim($data['othername'] ?? '') ?: ' ';
            $user->is_admin = $isAdmin;
            $user->status = 1;
            $user->is_locum = true;
            $user->assignRole = 1;
            $user->save();

            // 3. Assign Spatie roles immediately at creation
            if (!empty($roles)) {
                $existingRoles = Role::whereIn('name', $roles)->pluck('name')->toArray();
                if (!empty($existingRoles)) {
                    $user->syncRoles($existingRoles);
                }
            }

            // 4. Create/Update Staff Profile
            $staff = Staff::firstOrNew(['user_id' => $user->id]);
            $staff->employment_type = 'locum';
            $staff->employment_status = 'active';
            $staff->status = 1;
            $staff->phone_number = $data['phone_number'] ?? $staff->phone_number;
            $staff->clinic_id = $data['clinic_id'] ?? $staff->clinic_id;
            $staff->specialization_id = $data['specialization_id'] ?? $staff->specialization_id;
            $staff->job_title = $data['job_title'] ?? ('Locum ' . ($cadreConfig['label'] ?? 'Practitioner'));

            if (isset($data['can_see_clinic_queues'])) {
                $staff->can_see_clinic_queues = array_values(array_unique(array_map('intval', array_filter((array) $data['can_see_clinic_queues']))));
            }
            $staff->save();

            // 5. Create/Update LocumProfile
            $profile = LocumProfile::firstOrNew(['user_id' => $user->id]);
            $profile->regulatory_body = $data['regulatory_body'] ?? $cadreConfig['regulatory_body'];
            $profile->license_number = trim($data['license_number'] ?? 'PENDING');
            $profile->primary_specialty = $data['primary_specialty'] ?? null;
            $profile->cadre_template = $cadre;
            $profile->created_by = $creatorId;
            $profile->save();

            // 6. Issue initial Shift Pass
            $passData = [
                'shift_type' => $data['shift_type'] ?? 'NIGHT',
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'allowed_clinic_ids' => $data['allowed_clinic_ids'] ?? ($staff->clinic_id ? [$staff->clinic_id] : null),
                'allowed_department_ids' => $data['allowed_department_ids'] ?? null,
            ];

            $pass = $this->issuePass($user, $passData, $creator);

            return [
                'user' => $user,
                'staff' => $staff,
                'profile' => $profile,
                'pass' => $pass,
                'pin' => $pass->pin_display,
                'temporary_password' => $plainPassword,
                'roles' => $user->getRoleNames()->toArray(),
            ];
        });
    }

    /**
     * Issue a new shift-bounded pass for an existing locum practitioner.
     *
     * @param User $user
     * @param array $data
     * @param User|null $issuer
     * @return LocumPass
     */
    public function issuePass(User $user, array $data, ?User $issuer = null): LocumPass
    {
        $issuerId = $issuer ? $issuer->id : auth()->id();
        $shiftType = strtoupper($data['shift_type'] ?? 'NIGHT');

        // Resolve Shift Window (starts_at & expires_at)
        [$startsAt, $expiresAt] = $this->resolveShiftWindow(
            $shiftType,
            $data['starts_at'] ?? null,
            $data['expires_at'] ?? null
        );

        // Generate 6-digit cryptographic PIN
        $pin = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Revoke any prior unexpired pass so practitioner has a single active key
        LocumPass::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>=', now())
            ->update([
                'revoked_at' => now(),
                'revoked_by' => $issuerId,
                'revocation_reason' => 'Superseded by new shift pass',
            ]);

        $pass = new LocumPass();
        $pass->user_id = $user->id;
        $pass->pin_display = $pin;
        $pass->passcode_hash = Hash::make($pin);
        $pass->shift_type = $shiftType;
        $pass->starts_at = $startsAt;
        $pass->expires_at = $expiresAt;
        $pass->allowed_clinic_ids = !empty($data['allowed_clinic_ids']) ? (array) $data['allowed_clinic_ids'] : null;
        $pass->allowed_department_ids = !empty($data['allowed_department_ids']) ? (array) $data['allowed_department_ids'] : null;
        $pass->created_by = $issuerId;
        $pass->save();

        return $pass;
    }

    /**
     * Revoke a shift pass immediately.
     */
    public function revokePass(LocumPass $pass, ?User $revoker = null, ?string $reason = null): bool
    {
        return $pass->revoke($revoker ? $revoker->id : auth()->id(), $reason ?: 'Revoked by administrator');
    }

    /**
     * Extend an active or expired pass.
     */
    public function extendPass(LocumPass $pass, int $minutes = 240): bool
    {
        return $pass->extend($minutes);
    }

    /**
     * Authenticate a locum practitioner via Mobile Phone / Email and 6-digit PIN.
     *
     * @param string $identifier Phone or Email
     * @param string $pin 6-digit PIN
     * @return array ['success' => bool, 'user' => ?User, 'pass' => ?LocumPass, 'message' => string]
     */
    public function authenticatePass(string $identifier, string $pin): array
    {
        $cleanId = trim($identifier);

        // Find user by email or staff phone number
        $user = User::where('email', $cleanId)
            ->orWhereHas('staff_profile', function ($q) use ($cleanId) {
                $q->where('phone_number', $cleanId);
            })
            ->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'No locum practitioner found matching this phone number or email.',
            ];
        }

        if (!$user->isLocum()) {
            return [
                'success' => false,
                'message' => 'This account is not designated as a locum practitioner.',
            ];
        }

        // Check if user account is deactivated
        if ((int) $user->status === 0) {
            return [
                'success' => false,
                'message' => 'This locum account has been deactivated. Please contact administration.',
            ];
        }

        // Check staff profile status and suspension
        if ($user->staff_profile) {
            $staff = $user->staff_profile;
            if ($staff->isSuspended()) {
                $reason = $staff->suspension_reason ? ': ' . $staff->suspension_reason : '.';

                return [
                    'success' => false,
                    'message' => 'This locum practitioner is currently suspended' . $reason,
                ];
            }

            if (in_array($staff->employment_status, ['terminated', 'resigned', 'retired']) || (int) $staff->status === 0) {
                return [
                    'success' => false,
                    'message' => 'This locum practitioner account is inactive (' . ucfirst($staff->employment_status) . ').',
                ];
            }
        }

        // Get latest pass
        $pass = LocumPass::where('user_id', $user->id)->latest('id')->first();

        if (!$pass) {
            return [
                'success' => false,
                'message' => 'No shift pass on record for this practitioner. Please request a pass from the HOD.',
            ];
        }

        // Check PIN match
        if (!Hash::check($pin, $pass->passcode_hash) && $pass->pin_display !== $pin) {
            return [
                'success' => false,
                'message' => 'Invalid 6-digit Shift Pass PIN. Please verify your PIN.',
            ];
        }

        // Check revocation
        if ($pass->isRevoked()) {
            return [
                'success' => false,
                'message' => 'This shift pass has been revoked: ' . ($pass->revocation_reason ?: 'Contact HOD/Admin.'),
            ];
        }

        // Check expiry
        if ($pass->isExpired()) {
            return [
                'success' => false,
                'message' => 'This shift pass expired at ' . $pass->expires_at->format('h:i A on M d, Y') . '. Please request a shift renewal.',
            ];
        }

        // Check schedule start
        if ($pass->starts_at > now()) {
            return [
                'success' => false,
                'message' => 'This shift pass is scheduled to start at ' . $pass->starts_at->format('h:i A on M d, Y') . '.',
            ];
        }

        return [
            'success' => true,
            'user' => $user,
            'pass' => $pass,
            'message' => 'Authentication successful.',
        ];
    }

    /**
     * Resolve standard shift operational windows.
     */
    protected function resolveShiftWindow(string $shiftType, ?string $startsAt = null, ?string $expiresAt = null): array
    {
        if ($shiftType === 'CUSTOM' && $startsAt && $expiresAt) {
            return [
                Carbon::parse($startsAt),
                Carbon::parse($expiresAt),
            ];
        }

        $now = now();

        switch ($shiftType) {
            case 'MORNING':
                // Active now until 14:00 today (or tomorrow if already past 14:00)
                $end = $now->copy()->setTime(14, 0, 0);
                if ($now->greaterThanOrEqualTo($end)) {
                    $start = $now->copy()->addDay()->setTime(8, 0, 0);
                    $end = $now->copy()->addDay()->setTime(14, 0, 0);
                } else {
                    $start = $now->copy()->setTime(8, 0, 0);
                    if ($start->greaterThan($now)) {
                        $start = $now->copy();
                    }
                }

                return [$start, $end];

            case 'AFTERNOON':
                // Active now until 19:00 today (or tomorrow if already past 19:00)
                $end = $now->copy()->setTime(19, 0, 0);
                if ($now->greaterThanOrEqualTo($end)) {
                    $start = $now->copy()->addDay()->setTime(13, 0, 0);
                    $end = $now->copy()->addDay()->setTime(19, 0, 0);
                } else {
                    $start = $now->copy()->setTime(13, 0, 0);
                    if ($start->greaterThan($now)) {
                        $start = $now->copy();
                    }
                }

                return [$start, $end];

            case 'NIGHT':
            default:
                // Active now until 08:00 next morning (or today 08:00 if before 08:00)
                if ($now->hour < 8) {
                    $start = $now->copy()->subDay()->setTime(18, 0, 0);
                    $end = $now->copy()->setTime(8, 0, 0);
                } else {
                    $start = $now->copy()->setTime(18, 0, 0);
                    if ($start->greaterThan($now)) {
                        $start = $now->copy();
                    }
                    $end = $now->copy()->addDay()->setTime(8, 0, 0);
                }

                return [$start, $end];
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\LocumPass;
use App\Models\Specialization;
use App\Models\User;
use App\Services\Locum\LocumManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Locum Management & Ephemeral Access Controller
 *
 * Full administration console for managing locum doctors, visiting radiologists,
 * relief nurses, and external lab scientists.
 */
class LocumManagementController extends Controller
{
    protected LocumManagementService $locumService;

    public function __construct(LocumManagementService $locumService)
    {
        $this->middleware('auth');
        $this->locumService = $locumService;
    }

    /**
     * Display the main Locum Management Console.
     */
    public function index(Request $request)
    {
        $clinics = Clinic::where('status', 1)->orderBy('name')->pluck('name', 'id');
        $specializations = Specialization::orderBy('name')->pluck('name', 'id');
        $cadres = LocumManagementService::CADRE_CONFIG;
        $roles = Role::orderBy('name')->pluck('name', 'name');

        // Executive Quick Telemetry
        $activePasses = LocumPass::active()->count();
        $expiringSoon = LocumPass::active()
            ->where('expires_at', '<=', now()->addHours(2))
            ->count();
        $totalLocums = User::where('is_locum', true)
            ->orWhereHas('locum_profile')
            ->count();
        $revokedPasses = LocumPass::whereNotNull('revoked_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return view('admin.locum.index', compact(
            'clinics',
            'specializations',
            'cadres',
            'roles',
            'activePasses',
            'expiringSoon',
            'totalLocums',
            'revokedPasses'
        ));
    }

    /**
     * AJAX feed for Locum Practitioners and Passes DataTable.
     */
    public function data(Request $request): JsonResponse
    {
        $query = User::with([
            'locum_profile',
            'staff_profile.clinic',
            'staff_profile.specialization',
            'roles',
            'latest_locum_pass',
            'active_locum_pass',
        ])
        ->where(function ($q) {
            $q->where('is_locum', true)
              ->orWhereHas('locum_profile');
        });

        // Filter by Cadre
        if ($request->filled('cadre') && $request->cadre !== 'all') {
            $query->whereHas('locum_profile', function ($q) use ($request) {
                $q->where('cadre_template', $request->cadre);
            });
        }

        // Filter by Clinic
        if ($request->filled('clinic_id') && $request->clinic_id !== 'all') {
            $query->whereHas('staff_profile', function ($q) use ($request) {
                $q->where('clinic_id', $request->clinic_id);
            });
        }

        // Filter by Status (active, expired, revoked)
        if ($request->filled('status') && $request->status !== 'all') {
            switch ($request->status) {
                case 'active':
                    $query->whereHas('active_locum_pass');

                    break;
                case 'expired':
                    $query->whereHas('latest_locum_pass', function ($q) {
                        $q->whereNull('revoked_at')->where('expires_at', '<', now());
                    });

                    break;
                case 'revoked':
                    $query->whereHas('latest_locum_pass', function ($q) {
                        $q->whereNotNull('revoked_at');
                    });

                    break;
            }
        }

        $users = $query->orderBy('id', 'desc')->get();

        $rows = [];
        foreach ($users as $user) {
            $profile = $user->locum_profile;
            $staff = $user->staff_profile;
            $pass = $user->active_locum_pass ?: $user->latest_locum_pass;

            $fullName = trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?: ''));
            $initial = mb_strtoupper(mb_substr($user->surname ?: 'L', 0, 1));
            $avatar = '<div class="locum-avatar d-inline-flex align-items-center justify-content-center me-2">' . $initial . '</div>';

            // Role badges
            $roleBadges = '';
            foreach ($user->roles as $role) {
                $roleBadges .= '<span class="badge bg-primary text-white me-1">' . e($role->name) . '</span>';
            }
            if (!$roleBadges) {
                $roleBadges = '<span class="badge bg-secondary text-white">None</span>';
            }

            // Cadre label
            $cadreKey = $profile->cadre_template ?? 'DOCTOR';
            $cadreInfo = LocumManagementService::CADRE_CONFIG[$cadreKey] ?? null;
            $cadreLabel = $cadreInfo['label'] ?? 'Locum Clinician';

            // Status Badge & Time Remaining
            $statusBadge = $pass ? $pass->status_badge : '<span class="badge bg-secondary text-white">No Pass</span>';
            $timeRemaining = $pass ? $pass->time_remaining : 'No active shift';

            $windowText = $pass
                ? '<small class="text-muted d-block">' . $pass->starts_at->format('M d, h:i A') . ' - ' . $pass->expires_at->format('M d, h:i A') . ' (' . $pass->shift_type . ')</small>'
                : '';

            $pinPreview = $pass && $pass->pin_display
                ? '<code class="locum-pin-tag" title="Shift Pass 6-Digit PIN">' . e($pass->pin_display) . '</code>'
                : ($pass ? '<span class="text-muted"><i class="mdi mdi-lock text-success"></i> Active PIN</span>' : '—');

            // Action buttons
            $actions = '<div class="btn-group btn-group-sm">';

            if ($pass && $pass->isActive()) {
                $actions .= '<button type="button" class="btn btn-outline-warning btn-locum-extend" data-id="' . $pass->id . '" title="Extend Shift (+4 hours)">'
                    . '<i class="mdi mdi-clock-fast"></i> +4h</button>';
                $actions .= '<button type="button" class="btn btn-outline-danger btn-locum-revoke" data-id="' . $pass->id . '" title="Revoke Pass Immediately">'
                    . '<i class="mdi mdi-cancel"></i> Revoke</button>';
            }

            $actions .= '<button type="button" class="btn btn-outline-primary btn-locum-issue" data-user-id="' . $user->id . '" data-user-name="' . e($fullName) . '" title="Issue New Shift Pass">'
                . '<i class="mdi mdi-key-plus"></i> New Pass</button>';

            if ($pass) {
                $passRoles = $user->roles->pluck('name')->implode(', ');
                $actions .= '<button type="button" class="btn btn-outline-secondary btn-locum-print" '
                    . 'data-pass-id="' . $pass->id . '" '
                    . 'data-name="' . e($fullName) . '" '
                    . 'data-email="' . e($user->email) . '" '
                    . 'data-roles="' . e($passRoles) . '" '
                    . 'data-pin="' . e($pass->pin_display ?: 'Active PIN') . '" '
                    . 'data-shift="' . e($pass->shift_type . ' (Expires ' . $pass->expires_at->format('M d, Y h:i A') . ')') . '" '
                    . 'title="Print / View Thermal Pass Slip">'
                    . '<i class="mdi mdi-printer"></i></button>';
            }

            $actions .= '</div>';

            $rows[] = [
                'id' => $user->id,
                'practitioner' => '<div class="d-flex align-items-center">' . $avatar . '<div><strong>' . e($fullName) . '</strong><br><small class="text-muted"><i class="mdi mdi-phone me-1"></i>' . e($staff->phone_number ?? 'No phone') . ' · ' . e($user->email) . '</small></div></div>',
                'cadre_roles' => '<div><strong>' . e($cadreLabel) . '</strong><br>' . $roleBadges . '</div>',
                'licensing' => '<div><span class="badge bg-light text-dark border">' . e($profile->regulatory_body ?? 'MDCN') . '</span> <strong>' . e($profile->license_number ?? 'N/A') . '</strong><br><small class="text-muted">' . e($profile->primary_specialty ?: ($staff->clinic->name ?? 'General Clinic')) . '</small></div>',
                'shift_status' => '<div>' . $statusBadge . ' <small class="fw-bold ms-1">' . e($timeRemaining) . '</small>' . $windowText . '</div>',
                'pin_preview' => $pinPreview,
                'actions' => $actions,
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * Store and provision a new locum practitioner with assigned roles and shift pass.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'surname' => 'required|string|max:100',
            'firstname' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone_number' => 'required|string|max:30',
            'cadre_template' => 'required|string|in:' . implode(',', array_keys(LocumManagementService::CADRE_CONFIG)),
            'regulatory_body' => 'required|string|max:50',
            'license_number' => 'required|string|max:100',
            'clinic_id' => 'nullable|integer',
            'shift_type' => 'nullable|string|in:MORNING,AFTERNOON,NIGHT,CUSTOM',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'roles' => 'nullable|array',
        ]);

        try {
            $result = $this->locumService->createLocum($request->all(), auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Locum practitioner registered successfully with assigned clinical roles and initial shift pass.',
                'data' => [
                    'user_id' => $result['user']->id,
                    'full_name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'roles' => $result['roles'],
                    'pass_id' => $result['pass']->id,
                    'pin' => $result['pin'],
                    'shift_type' => $result['pass']->shift_type,
                    'expires_at' => $result['pass']->expires_at->format('M d, Y h:i A'),
                    'temporary_password' => $result['temporary_password'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to provision locum practitioner: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Issue a new shift pass for an existing locum practitioner.
     */
    public function issuePass(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'shift_type' => 'required|string|in:MORNING,AFTERNOON,NIGHT,CUSTOM',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'allowed_clinic_ids' => 'nullable|array',
        ]);

        try {
            $pass = $this->locumService->issuePass($user, $request->all(), auth()->user());

            return response()->json([
                'success' => true,
                'message' => 'Shift pass issued successfully.',
                'data' => [
                    'pass_id' => $pass->id,
                    'full_name' => trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')),
                    'email' => $user->email,
                    'roles' => $user->roles ? $user->roles->pluck('name')->toArray() : [],
                    'pin' => $pass->pin_display,
                    'shift_type' => $pass->shift_type,
                    'starts_at' => $pass->starts_at->format('M d, Y h:i A'),
                    'expires_at' => $pass->expires_at->format('M d, Y h:i A'),
                    'temporary_password' => 'Shift Pass (Active)',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue shift pass: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Revoke an active shift pass immediately.
     */
    public function revokePass(Request $request, LocumPass $pass): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $this->locumService->revokePass($pass, auth()->user(), $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Shift pass revoked immediately. Access has been locked.',
        ]);
    }

    /**
     * Extend a shift pass duration.
     */
    public function extendPass(Request $request, LocumPass $pass): JsonResponse
    {
        $minutes = (int) ($request->minutes ?: 240); // Default +4 hours
        $this->locumService->extendPass($pass, $minutes);

        return response()->json([
            'success' => true,
            'message' => "Shift pass successfully extended by {$minutes} minutes.",
            'new_expiry' => $pass->expires_at->format('M d, Y h:i A'),
        ]);
    }

    /**
     * Printable credential badge / thermal slip for locum shift pass.
     */
    public function printPass(LocumPass $pass)
    {
        $pass->load(['user.locum_profile', 'user.staff_profile.clinic', 'user.roles']);

        return view('admin.locum.print_slip', compact('pass'));
    }

    /**
     * Get JSON details for a locum shift pass (for slip modal viewing).
     */
    public function passDetails(LocumPass $pass): JsonResponse
    {
        $pass->load(['user.locum_profile', 'user.staff_profile.clinic', 'user.roles']);
        $user = $pass->user;

        return response()->json([
            'success' => true,
            'data' => [
                'pass_id' => $pass->id,
                'full_name' => trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')),
                'email' => $user->email ?? '',
                'roles' => $user->roles ? $user->roles->pluck('name')->toArray() : [],
                'pin' => $pass->pin_display ?: 'Active PIN',
                'shift_type' => $pass->shift_type,
                'starts_at' => $pass->starts_at ? $pass->starts_at->format('M d, Y h:i A') : '',
                'expires_at' => $pass->expires_at ? $pass->expires_at->format('M d, Y h:i A') : '',
                'temporary_password' => 'Shift Pass (Active)',
            ],
        ]);
    }
}

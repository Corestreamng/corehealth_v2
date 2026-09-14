<?php

namespace App\Http\Controllers\EncounterWorkbench;

use App\Http\Controllers\EncounterWorkbench\Tabs\ClinicAnalyticsTab;
use App\Http\Controllers\EncounterWorkbench\Tabs\DoctorProductivityTab;
use App\Http\Controllers\EncounterWorkbench\Tabs\EncounterKpiTab;
use App\Http\Controllers\EncounterWorkbench\Tabs\EncounterListTab;
use App\Http\Controllers\EncounterWorkbench\Tabs\PatientInsightsTab;
use App\Http\Controllers\EncounterWorkbench\Tabs\RevenueBillingTab;
use App\Models\Clinic;
use App\Models\Encounter;
use App\Models\Hmo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * EncounterWorkbenchController
 *
 * Entry point for the Encounter Intelligence Workbench.
 * Renders the page and dispatches tab AJAX requests to the appropriate tab controller.
 */
class EncounterWorkbenchController extends EncounterWorkbenchBaseController
{
    // ─── Page Render ──────────────────────────────────────────────────────────

    public function index()
    {
        $user = Auth::user();

        $canViewAnalytics = $this->canViewAnalytics();
        $canViewRevenue = $this->canViewRevenue();
        $isDoctor = $this->userIsDoctor();

        // Filter dropdowns
        $clinics = Clinic::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $hmos = Hmo::where('status', 1)->orderBy('name')->get(['id', 'name']);

        // Only expose doctor list to admin/accounts; doctors can't filter by other doctors
        $doctors = ($canViewAnalytics || $canViewRevenue && !$isDoctor)
            ? User::whereHas('roles', fn ($q) => $q->where('name', 'DOCTOR'))
                ->orderBy('surname')
                ->get(['id', 'surname', 'firstname', 'othername'])
            : collect();

        // Build WORKBENCH_CONFIG for JS
        $config = [
            'routes' => [
                'list' => route('encounter.workbench.list'),
                'kpi' => route('encounter.workbench.kpi'),
                'kpiStrip' => route('encounter.workbench.kpi-strip'),
                'clinic' => route('encounter.workbench.clinic'),
                'doctor' => route('encounter.workbench.doctor'),
                'revenue' => route('encounter.workbench.revenue'),
                'patients' => route('encounter.workbench.patients'),
                'details' => route('encounter.workbench.details', '__ID__'),
                'export' => route('encounter.workbench.export'),
            ],
            'capabilities' => [
                'canViewAnalytics' => $canViewAnalytics,
                'canViewRevenue' => $canViewRevenue,
                'isDoctor' => $isDoctor,
                'doctorId' => $isDoctor ? Auth::id() : null,
            ],
            'csrfToken' => csrf_token(),
        ];

        return view('admin.encounters.workbench', compact(
            'clinics',
            'hmos',
            'doctors',
            'canViewAnalytics',
            'canViewRevenue',
            'isDoctor',
            'config'
        ));
    }

    // ─── Tab Endpoints ────────────────────────────────────────────────────────

    public function listData(Request $request)
    {
        return (new EncounterListTab())->getData($request);
    }

    public function kpiData(Request $request)
    {
        return (new EncounterKpiTab())->getData($request);
    }

    public function kpiStrip(Request $request)
    {
        return (new EncounterKpiTab())->getKpiStrip($request);
    }

    public function clinicAnalytics(Request $request)
    {
        return (new ClinicAnalyticsTab())->getData($request);
    }

    public function doctorProductivity(Request $request)
    {
        return (new DoctorProductivityTab())->getData($request);
    }

    public function revenue(Request $request)
    {
        return (new RevenueBillingTab())->getData($request);
    }

    public function patientInsights(Request $request)
    {
        return (new PatientInsightsTab())->getData($request);
    }

    // ─── Encounter Detail Modal ────────────────────────────────────────────────

    public function details(Request $request, int $id)
    {
        $encounter = Encounter::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'queue.clinic',
            'labRequests.service',
            'imagingRequests.service',
            'productRequests.product',
            'productOrServiceRequests.payment',
            'procedures.service',
            'referrals.targetClinic',
            'referrals.referringDoctor',
            'admissionRequests.ward',
            'admissionRequests.bed',
            'treatmentPlan',
        ])->findOrFail($id);

        // Role-scoped: doctors can only view their own encounters
        if ($this->userIsDoctor() && !$this->canViewAnalytics()) {
            abort_if($encounter->doctor_id !== Auth::id(), 403);
        }

        $html = view('admin.encounters.partials._detail_modal_body', compact('encounter'))->render();

        $patientName = $encounter->patient?->user
            ? trim($encounter->patient->user->surname . ' ' . $encounter->patient->user->firstname . ' ' . ($encounter->patient->user->othername ?? ''))
            : 'Unknown Patient';

        return response()->json([
            'html' => $html,
            'title' => '<i class="mdi mdi-stethoscope me-2 text-primary"></i> Encounter — ' . e($patientName),
        ]);
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        // Delegate to encounter list tab with action=print to use OpsAudit print view
        $request->merge(['action' => 'print', 'tab' => 'Encounter List']);

        return (new EncounterListTab())->getData($request);
    }
}

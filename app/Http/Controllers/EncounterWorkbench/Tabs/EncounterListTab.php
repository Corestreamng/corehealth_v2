<?php

namespace App\Http\Controllers\EncounterWorkbench\Tabs;

use App\Http\Controllers\EncounterWorkbench\EncounterWorkbenchBaseController;
use App\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Tab 1 — Encounter List
 * Enriched DataTable replacing the legacy AllprevEncounterList endpoint.
 * Accessible by all workbench roles; doctors are scoped to their own encounters.
 */
class EncounterListTab extends EncounterWorkbenchBaseController
{
    public function getData(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\View\View
    {
        $query = Encounter::with([
            'patient.user',
            'patient.hmo.scheme',
            'doctor',
            'queue.clinic',
            'labRequests',
            'imagingRequests',
            'productRequests',
            'procedures',
            'referrals',
            'admissionRequests',
        ]);

        $this->applyEncounterFilters($query, $request);
        $this->applyRoleScope($query);

        $kpiQuery = clone $query;

        return $this->buildDataTableResponse(
            $query,
            $request,
            fn ($q) => $q,
            function ($row) {
                $patient = $row->patient;
                $user = $patient?->user;
                $hmo = $patient?->hmo;
                $doctor = $row->doctor;
                $clinic = $row->queue?->clinic;

                $doctorName = $doctor
                    ? trim($doctor->surname . ' ' . $doctor->firstname . ' ' . ($doctor->othername ?? ''))
                    : '—';

                $duration = $row->started_at && $row->completed_at
                    ? $this->renderDuration(Carbon::parse($row->started_at), Carbon::parse($row->completed_at))
                    : '—';

                $dateHtml = '<div class="font-weight-bold" style="font-size:0.8rem;">'
                    . e(Carbon::parse($row->created_at)->format('d M Y'))
                    . '</div><small class="text-muted">'
                    . e(Carbon::parse($row->created_at)->format('h:i A'))
                    . '</small>';

                if ($row->completed && $row->completed_at) {
                    $dateHtml .= '<br><small class="text-success"><i class="mdi mdi-clock-check-outline"></i> ' . $duration . '</small>';
                }

                return [
                    'id' => $row->id,
                    'patient' => $this->renderPatient($user, $patient, $hmo),
                    'hmo' => $this->renderHmo($hmo),
                    'clinic' => $clinic ? e($clinic->name) : '<span class="text-muted">—</span>',
                    'doctor' => '<span class="font-weight-bold">' . e($doctorName) . '</span>',
                    'date' => $dateHtml,
                    'tags' => $this->renderEncounterTags($row),
                    'status' => $this->renderStatusBadge($row->completed ?? false),
                    'actions' => $this->renderActions($row),
                ];
            },
            function ($kpiQuery) {
                $total = (clone $kpiQuery)->count();
                $completed = (clone $kpiQuery)->where('completed', true)->count();
                $active = $total - $completed;

                $uniquePatients = (clone $kpiQuery)
                    ->distinct('patient_id')
                    ->count('patient_id');

                // New patients: only 1 encounter ever
                $newPatients = (clone $kpiQuery)->whereHas('patient', function ($pq) {
                    $pq->whereHas('encounters', null, '=', 1);
                })->count();

                return [
                    $this->kpiCard('Total Encounters', number_format($total), '#0d6efd', 'mdi-stethoscope'),
                    $this->kpiCard('Active', number_format($active), '#17a2b8', 'mdi-account-clock'),
                    $this->kpiCard('Completed', number_format($completed), '#198754', 'mdi-check-circle-outline'),
                    $this->kpiCard('Unique Patients', number_format($uniquePatients), '#6610f2', 'mdi-account-group'),
                    $this->kpiCard('New Patients', number_format($newPatients), '#fd7e14', 'mdi-account-plus-outline'),
                ];
            },
            $kpiQuery
        );
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function renderActions(Encounter $row): string
    {
        $viewUrl = route('encounter.workbench.details', $row->id);
        $patientId = $row->patient_id;

        $html = '<div class="d-flex gap-1">';
        $html .= '<button class="btn btn-xs btn-outline-primary ewb-view-encounter" '
            . 'data-id="' . $row->id . '" '
            . 'data-url="' . $viewUrl . '" '
            . 'title="View Encounter Details">'
            . '<i class="mdi mdi-eye"></i></button>';

        if ($patientId) {
            $html .= '<a class="btn btn-xs btn-outline-secondary" '
                . 'href="' . route('patient.show', $patientId) . '" '
                . 'title="Open Patient Record" target="_blank">'
                . '<i class="mdi mdi-account-card-details-outline"></i></a>';
        }

        $html .= '</div>';

        return $html;
    }
}

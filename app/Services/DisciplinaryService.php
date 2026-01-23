<?php

namespace App\Services;

use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HRMS Implementation Plan - Section 6.3
 * Disciplinary Service - Handles disciplinary queries, suspensions, and terminations
 */
class DisciplinaryService
{
    /**
     * Create a disciplinary query
     */
    public function createQuery(array $data, User $issuer): DisciplinaryQuery
    {
        return DisciplinaryQuery::create([
            'staff_id' => $data['staff_id'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'severity' => $data['severity'],
            'incident_date' => $data['incident_date'] ?? null,
            'expected_response' => $data['expected_response'] ?? null,
            'response_deadline' => $data['response_deadline'] ?? Carbon::now()->addDays(7),
            'status' => DisciplinaryQuery::STATUS_ISSUED,
            'issued_by' => $issuer->id,
        ]);
    }

    /**
     * Record staff response to query
     */
    public function recordResponse(DisciplinaryQuery $query, string $response): DisciplinaryQuery
    {
        if ($query->status !== DisciplinaryQuery::STATUS_ISSUED) {
            throw new \Exception('Response can only be submitted for issued queries.');
        }

        $query->update([
            'staff_response' => $response,
            'response_received_at' => now(),
            'status' => DisciplinaryQuery::STATUS_RESPONSE_RECEIVED,
        ]);

        return $query->fresh();
    }

    /**
     * Process HR decision on a query
     */
    public function processDecision(DisciplinaryQuery $query, array $data, User $decider): DisciplinaryQuery
    {
        if (!in_array($query->status, [DisciplinaryQuery::STATUS_RESPONSE_RECEIVED, DisciplinaryQuery::STATUS_UNDER_REVIEW, DisciplinaryQuery::STATUS_ISSUED])) {
            throw new \Exception('Decision can only be made on queries with responses or under review.');
        }

        return DB::transaction(function () use ($query, $data, $decider) {
            $query->update([
                'hr_decision' => $data['hr_decision'],
                'outcome' => $data['outcome'],
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'status' => DisciplinaryQuery::STATUS_CLOSED,
            ]);

            // Handle outcomes that require additional actions
            switch ($data['outcome']) {
                case DisciplinaryQuery::OUTCOME_SUSPENSION:
                    $this->createSuspension([
                        'staff_id' => $query->staff_id,
                        'disciplinary_query_id' => $query->id,
                        'type' => $data['suspension_type'] ?? StaffSuspension::TYPE_UNPAID,
                        'start_date' => $data['suspension_start'] ?? now(),
                        'end_date' => $data['suspension_end'] ?? null,
                        'reason' => "Following disciplinary query #{$query->query_number}: {$query->subject}",
                        'suspension_message' => $data['suspension_message'] ?? "Your account has been suspended pending disciplinary action. Reference: {$query->query_number}",
                    ], $decider);
                    break;

                case DisciplinaryQuery::OUTCOME_TERMINATION:
                    $this->createTermination([
                        'staff_id' => $query->staff_id,
                        'disciplinary_query_id' => $query->id,
                        'type' => StaffTermination::TYPE_INVOLUNTARY,
                        'reason_category' => StaffTermination::REASON_MISCONDUCT,
                        'reason_details' => "Termination following disciplinary query #{$query->query_number}: {$query->subject}",
                        'notice_date' => now(),
                        'effective_date' => $data['termination_date'] ?? now()->addDays(1),
                    ], $decider);
                    break;
            }

            return $query->fresh();
        });
    }

    /**
     * Create a staff suspension
     */
    public function createSuspension(array $data, User $issuer): StaffSuspension
    {
        $staff = Staff::findOrFail($data['staff_id']);

        // Check for existing active suspension
        if ($staff->isSuspended()) {
            throw new \Exception('Staff member is already suspended.');
        }

        return StaffSuspension::create([
            'staff_id' => $data['staff_id'],
            'disciplinary_query_id' => $data['disciplinary_query_id'] ?? null,
            'type' => $data['type'] ?? StaffSuspension::TYPE_UNPAID,
            'start_date' => $data['start_date'] ?? now(),
            'end_date' => $data['end_date'] ?? null,
            'reason' => $data['reason'],
            'suspension_message' => $data['suspension_message'] ?? 'Your account has been suspended. Please contact HR for more information.',
            'status' => StaffSuspension::STATUS_ACTIVE,
            'issued_by' => $issuer->id,
        ]);
    }

    /**
     * Lift a suspension
     */
    public function liftSuspension(StaffSuspension $suspension, User $liftedBy, string $reason): StaffSuspension
    {
        if (!$suspension->isActive()) {
            throw new \Exception('Only active suspensions can be lifted.');
        }

        $suspension->lift($liftedBy->id, $reason);

        return $suspension->fresh();
    }

    /**
     * Create a termination record
     */
    public function createTermination(array $data, User $processor): StaffTermination
    {
        $staff = Staff::findOrFail($data['staff_id']);

        // Check if already terminated
        if (in_array($staff->employment_status, ['terminated', 'resigned'])) {
            throw new \Exception('Staff member is already terminated or has resigned.');
        }

        // If staff is suspended, lift the suspension first
        if ($staff->isSuspended() && $staff->activeSuspension) {
            $staff->activeSuspension->update(['status' => StaffSuspension::STATUS_LIFTED]);
        }

        return StaffTermination::create([
            'staff_id' => $data['staff_id'],
            'disciplinary_query_id' => $data['disciplinary_query_id'] ?? null,
            'type' => $data['type'] ?? StaffTermination::TYPE_INVOLUNTARY,
            'reason_category' => $data['reason_category'],
            'reason_details' => $data['reason_details'] ?? null,
            'notice_date' => $data['notice_date'] ?? now(),
            'effective_date' => $data['effective_date'],
            'last_working_day' => $data['last_working_day'] ?? $data['effective_date'],
            'exit_interview_conducted' => $data['exit_interview_conducted'] ?? false,
            'exit_interview_notes' => $data['exit_interview_notes'] ?? null,
            'clearance_completed' => $data['clearance_completed'] ?? false,
            'final_payment_processed' => $data['final_payment_processed'] ?? false,
            'processed_by' => $processor->id,
        ]);
    }

    /**
     * Process voluntary resignation
     */
    public function processResignation(Staff $staff, array $data, User $processor): StaffTermination
    {
        return $this->createTermination([
            'staff_id' => $staff->id,
            'type' => StaffTermination::TYPE_VOLUNTARY,
            'reason_category' => StaffTermination::REASON_RESIGNATION,
            'reason_details' => $data['reason'] ?? 'Voluntary resignation',
            'notice_date' => $data['notice_date'] ?? now(),
            'effective_date' => $data['effective_date'],
            'last_working_day' => $data['last_working_day'] ?? $data['effective_date'],
        ], $processor);
    }

    /**
     * Get disciplinary statistics
     */
    public function getDisciplinaryStats(?int $year = null): array
    {
        $year = $year ?? now()->year;

        return [
            'total_queries' => DisciplinaryQuery::whereYear('created_at', $year)->count(),
            'open_queries' => DisciplinaryQuery::whereIn('status', [
                DisciplinaryQuery::STATUS_ISSUED,
                DisciplinaryQuery::STATUS_RESPONSE_RECEIVED,
                DisciplinaryQuery::STATUS_UNDER_REVIEW,
            ])->count(),
            'overdue_responses' => DisciplinaryQuery::where('status', DisciplinaryQuery::STATUS_ISSUED)
                ->where('response_deadline', '<', now())
                ->count(),
            'active_suspensions' => StaffSuspension::active()->count(),
            'terminations_this_year' => StaffTermination::whereYear('created_at', $year)->count(),
            'by_severity' => DisciplinaryQuery::whereYear('created_at', $year)
                ->selectRaw('severity, count(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
        ];
    }
}

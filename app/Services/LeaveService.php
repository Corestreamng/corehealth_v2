<?php

namespace App\Services;

use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use App\Models\Staff;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HRMS Implementation Plan - Section 6.1
 * Leave Service - Handles leave request workflow and balance management
 *
 * Two-Level Approval Workflow:
 * 1. First Level: Unit Head (same department) OR Dept Head (same user category)
 * 2. Second Level: HR Manager (only after first level approved)
 */
class LeaveService
{
    /**
     * Create a new leave request
     */
    public function createLeaveRequest(array $data): LeaveRequest
    {
        return DB::transaction(function () use ($data) {
            $staff = Staff::findOrFail($data['staff_id']);
            $leaveType = LeaveType::findOrFail($data['leave_type_id']);

            // Calculate total days (excluding weekends if needed)
            $totalDays = $this->calculateLeaveDays(
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date'])
            );

            // Validate against balance
            $balance = $this->getOrCreateBalance($staff->id, $leaveType->id);
            if (!$balance->hasSufficientBalance($totalDays)) {
                throw new \Exception("Insufficient leave balance. Available: {$balance->available} days, Requested: {$totalDays} days.");
            }

            // Validate against leave type constraints
            $this->validateLeaveTypeConstraints($staff, $leaveType, $totalDays, $data['start_date'], $data['end_date']);

            // Create the request
            $leaveRequest = LeaveRequest::create([
                'staff_id' => $staff->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $totalDays,
                'reason' => $data['reason'] ?? null,
                'handover_notes' => $data['handover_notes'] ?? null,
                'relief_staff_id' => $data['relief_staff_id'] ?? null,
                'status' => LeaveRequest::STATUS_PENDING,
            ]);

            // Update pending days in balance
            $balance->increment('pending_days', $totalDays);

            return $leaveRequest;
        });
    }

    /**
     * First Level Approval - Supervisor (Unit Head / Dept Head) approves
     */
    public function supervisorApproveRequest(LeaveRequest $leaveRequest, User $approver, ?string $comments = null): LeaveRequest
    {
        if (!$leaveRequest->isPending()) {
            throw new \Exception('Only pending requests can be approved by supervisor.');
        }

        if (!$leaveRequest->canBeApprovedBySupervisor($approver)) {
            throw new \Exception('You are not authorized to approve this leave request as a supervisor.');
        }

        return DB::transaction(function () use ($leaveRequest, $approver, $comments) {
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                'supervisor_approved_by' => $approver->id,
                'supervisor_approved_at' => now(),
                'supervisor_comments' => $comments,
            ]);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Second Level Approval - HR Manager approves (final approval)
     */
    public function hrApproveRequest(LeaveRequest $leaveRequest, User $approver, ?string $comments = null): LeaveRequest
    {
        if (!$leaveRequest->isSupervisorApproved()) {
            throw new \Exception('Only supervisor-approved requests can be approved by HR.');
        }

        if (!$leaveRequest->canBeApprovedByHr($approver)) {
            throw new \Exception('You are not authorized to approve this leave request as HR.');
        }

        return DB::transaction(function () use ($leaveRequest, $approver, $comments) {
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'hr_approved_by' => $approver->id,
                'hr_approved_at' => now(),
                'hr_comments' => $comments,
                // Also set legacy fields for backward compatibility
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_comments' => $comments,
            ]);

            // Move from pending to used in balance
            $balance = $this->getOrCreateBalance($leaveRequest->staff_id, $leaveRequest->leave_type_id);
            $balance->decrement('pending_days', $leaveRequest->total_days);
            $balance->increment('used_days', $leaveRequest->total_days);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Legacy approve method - determines which level to use
     */
    public function approveRequest(LeaveRequest $leaveRequest, User $approver, ?string $comments = null): LeaveRequest
    {
        // If pending, try supervisor approval first
        if ($leaveRequest->isPending()) {
            if ($leaveRequest->canBeApprovedBySupervisor($approver)) {
                return $this->supervisorApproveRequest($leaveRequest, $approver, $comments);
            }
        }

        // If supervisor_approved, try HR approval
        if ($leaveRequest->isSupervisorApproved()) {
            if ($leaveRequest->canBeApprovedByHr($approver)) {
                return $this->hrApproveRequest($leaveRequest, $approver, $comments);
            }
        }

        throw new \Exception('You are not authorized to approve this leave request at its current state.');
    }

    /**
     * Reject a leave request (can be done at any pending stage)
     */
    public function rejectRequest(LeaveRequest $leaveRequest, User $rejector, string $reason): LeaveRequest
    {
        if (!$leaveRequest->isPending() && !$leaveRequest->isSupervisorApproved()) {
            throw new \Exception('Only pending or supervisor-approved requests can be rejected.');
        }

        // Check if user has authority to reject
        $canReject = false;

        if ($leaveRequest->isPending()) {
            $canReject = $leaveRequest->canBeApprovedBySupervisor($rejector);
        } elseif ($leaveRequest->isSupervisorApproved()) {
            $canReject = $leaveRequest->canBeApprovedByHr($rejector);
        }

        if (!$canReject) {
            throw new \Exception('You are not authorized to reject this leave request.');
        }

        return DB::transaction(function () use ($leaveRequest, $rejector, $reason) {
            $updateData = [
                'status' => LeaveRequest::STATUS_REJECTED,
                'reviewed_by' => $rejector->id,
                'reviewed_at' => now(),
                'review_comments' => $reason,
            ];

            // Track which level rejected
            if ($leaveRequest->isPending()) {
                $updateData['supervisor_comments'] = "Rejected: " . $reason;
            } else {
                $updateData['hr_comments'] = "Rejected: " . $reason;
            }

            $leaveRequest->update($updateData);

            // Release pending days from balance
            $balance = $this->getOrCreateBalance($leaveRequest->staff_id, $leaveRequest->leave_type_id);
            $balance->decrement('pending_days', $leaveRequest->total_days);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Cancel a leave request (by requester, only pending or supervisor_approved)
     */
    public function cancelRequest(LeaveRequest $leaveRequest): LeaveRequest
    {
        if (!$leaveRequest->isPending() && !$leaveRequest->isSupervisorApproved()) {
            throw new \Exception('Only pending or supervisor-approved requests can be cancelled.');
        }

        return DB::transaction(function () use ($leaveRequest) {
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
            ]);

            // Release pending days from balance
            $balance = $this->getOrCreateBalance($leaveRequest->staff_id, $leaveRequest->leave_type_id);
            $balance->decrement('pending_days', $leaveRequest->total_days);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Recall an approved leave request (HR only, reverses used days)
     */
    public function recallRequest(LeaveRequest $leaveRequest, User $recaller, string $reason): LeaveRequest
    {
        if (!$leaveRequest->isApproved()) {
            throw new \Exception('Only approved requests can be recalled.');
        }

        return DB::transaction(function () use ($leaveRequest, $recaller, $reason) {
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_RECALLED,
                'review_comments' => "Recalled by {$recaller->name}: {$reason}",
            ]);

            // Restore used days to balance
            $balance = $this->getOrCreateBalance($leaveRequest->staff_id, $leaveRequest->leave_type_id);
            $balance->decrement('used_days', $leaveRequest->total_days);

            return $leaveRequest->fresh();
        });
    }

    /**
     * Get pending requests for supervisor approval
     * (requests where user is unit head/dept head for the staff)
     */
    public function getPendingForSupervisor(User $user)
    {
        return LeaveRequest::canApproveAsSupervisor($user)
            ->with(['staff.user', 'leaveType'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get pending requests for HR approval
     * (supervisor_approved requests)
     */
    public function getPendingForHr()
    {
        return LeaveRequest::pendingHrApproval()
            ->with(['staff.user', 'leaveType', 'supervisorApprovedBy'])
            ->orderBy('supervisor_approved_at', 'asc')
            ->get();
    }

    /**
     * Initialize leave balances for a new year
     */
    public function initializeYearBalances(int $year, ?array $staffIds = null): int
    {
        $query = Staff::active();
        if ($staffIds) {
            $query->whereIn('id', $staffIds);
        }
        $staffMembers = $query->get();
        $leaveTypes = LeaveType::active()->get();

        $count = 0;
        foreach ($staffMembers as $staff) {
            foreach ($leaveTypes as $leaveType) {
                // Check if applicable based on employment type
                if (!$leaveType->isApplicableFor($staff->employment_type)) {
                    continue;
                }

                // Check if balance already exists
                $exists = LeaveBalance::where('staff_id', $staff->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->exists();

                if (!$exists) {
                    // Calculate carried forward from previous year
                    $carriedForward = 0;
                    $previousBalance = LeaveBalance::where('staff_id', $staff->id)
                        ->where('leave_type_id', $leaveType->id)
                        ->where('year', $year - 1)
                        ->first();

                    if ($previousBalance) {
                        $carriedForward = max(0, $previousBalance->available);
                    }

                    LeaveBalance::create([
                        'staff_id' => $staff->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                        'entitled_days' => $leaveType->max_days_per_year,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'carried_forward' => $carriedForward,
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Adjust leave balance (admin function)
     */
    public function adjustBalance(int $staffId, int $leaveTypeId, int $year, float $adjustment, string $reason): LeaveBalance
    {
        $balance = $this->getOrCreateBalance($staffId, $leaveTypeId, $year);

        // Adjustment can be positive or negative
        $balance->entitled_days += $adjustment;
        $balance->save();

        // Log the adjustment (via audit trail)
        activity()
            ->performedOn($balance)
            ->withProperties(['adjustment' => $adjustment, 'reason' => $reason])
            ->log('Leave balance adjusted');

        return $balance;
    }

    /**
     * Get or create balance for staff/leave type/year
     */
    public function getOrCreateBalance(int $staffId, int $leaveTypeId, ?int $year = null): LeaveBalance
    {
        $year = $year ?? now()->year;

        return LeaveBalance::firstOrCreate(
            [
                'staff_id' => $staffId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'entitled_days' => LeaveType::find($leaveTypeId)?->max_days_per_year ?? 0,
                'used_days' => 0,
                'pending_days' => 0,
                'carried_forward' => 0,
            ]
        );
    }

    /**
     * Calculate leave days between two dates (excluding weekends)
     */
    public function calculateLeaveDays(Carbon $startDate, Carbon $endDate, bool $excludeWeekends = true): float
    {
        if ($excludeWeekends) {
            $days = 0;
            $current = $startDate->copy();

            while ($current <= $endDate) {
                if (!$current->isWeekend()) {
                    $days++;
                }
                $current->addDay();
            }

            return $days;
        }

        return $startDate->diffInDays($endDate) + 1;
    }

    /**
     * Validate leave type constraints
     */
    protected function validateLeaveTypeConstraints(Staff $staff, LeaveType $leaveType, float $totalDays, string $startDate, string $endDate): void
    {
        // Check max consecutive days
        if ($leaveType->max_consecutive_days && $totalDays > $leaveType->max_consecutive_days) {
            throw new \Exception("This leave type allows maximum {$leaveType->max_consecutive_days} consecutive days.");
        }

        // Check max requests per year
        if ($leaveType->max_requests_per_year) {
            $currentYearRequests = LeaveRequest::where('staff_id', $staff->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', [
                    LeaveRequest::STATUS_PENDING,
                    LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                    LeaveRequest::STATUS_APPROVED
                ])
                ->count();

            if ($currentYearRequests >= $leaveType->max_requests_per_year) {
                throw new \Exception("Maximum {$leaveType->max_requests_per_year} requests per year allowed for this leave type.");
            }
        }

        // Check minimum notice days
        if ($leaveType->min_days_notice) {
            $noticeGiven = now()->diffInDays(Carbon::parse($startDate));
            if ($noticeGiven < $leaveType->min_days_notice) {
                throw new \Exception("This leave type requires at least {$leaveType->min_days_notice} days notice.");
            }
        }

        // Check overlapping requests
        $overlapping = LeaveRequest::where('staff_id', $staff->id)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                LeaveRequest::STATUS_APPROVED
            ])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->exists();

        if ($overlapping) {
            throw new \Exception('You have an existing leave request that overlaps with these dates.');
        }
    }
}

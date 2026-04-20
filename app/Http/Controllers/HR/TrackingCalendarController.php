<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffMedicalExam;
use App\Models\HR\StaffTraining;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TrackingCalendarController extends Controller
{
    public function index()
    {
        return view('admin.hr.tracking.calendar');
    }

    public function events(Request $request)
    {
        $start = $request->get('start');
        $end = $request->get('end');
        $events = [];

        // Medical exam due dates
        $exams = StaffMedicalExam::with('staff.user')
            ->whereNotNull('next_exam_due')
            ->when($start, fn($q) => $q->where('next_exam_due', '>=', $start))
            ->when($end, fn($q) => $q->where('next_exam_due', '<=', $end))
            ->get();

        foreach ($exams as $exam) {
            $name = $exam->staff?->user?->surname . ' ' . $exam->staff?->user?->firstname . ' ' . $exam->staff?->user?->othername;
            $overdue = $exam->next_exam_due->isPast();
            $events[] = [
                'id' => 'exam-' . $exam->id,
                'title' => $name . ' — Medical Exam Due',
                'start' => $exam->next_exam_due->toDateString(),
                'color' => $overdue ? '#ef4444' : '#f59e0b',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'medical_exam',
                    'staff' => $name,
                    'exam_type' => ucfirst(str_replace('_', ' ', $exam->exam_type)),
                    'overdue' => $overdue,
                    'staff_id' => $exam->staff_id,
                ],
            ];
        }

        // Training schedules (with start_date)
        $trainings = StaffTraining::with('staff.user')
            ->whereNotNull('start_date')
            ->whereIn('status', ['planned', 'in_progress'])
            ->when($start, fn($q) => $q->where(function ($q2) use ($start, $end) {
                $q2->whereBetween('start_date', [$start, $end])
                   ->orWhereBetween('end_date', [$start, $end]);
            }))
            ->get();

        foreach ($trainings as $t) {
            $name = $t->staff?->user?->surname . ' ' . $t->staff?->user?->firstname . ' ' . $t->staff?->user?->othername;
            $events[] = [
                'id' => 'train-' . $t->id,
                'title' => $name . ' — ' . $t->title,
                'start' => $t->start_date->toDateString(),
                'end' => $t->end_date ? $t->end_date->addDay()->toDateString() : null,
                'color' => $t->status === 'in_progress' ? '#3b82f6' : '#8b5cf6',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'training',
                    'staff' => $name,
                    'training_type' => ucfirst(str_replace('_', ' ', $t->type)),
                    'status' => ucfirst(str_replace('_', ' ', $t->status)),
                    'institution' => $t->institution,
                    'staff_id' => $t->staff_id,
                ],
            ];
        }

        // Staff-level date events (promotion due, license expiry, confirmation due)
        $staffQuery = Staff::with('user')
            ->whereHas('user', fn($q) => $q->where('status', '>', 0))
            ->where('employment_status', 'active');

        // Promotion due dates
        $promoDue = (clone $staffQuery)->whereNotNull('next_promotion_due_date')
            ->when($start, fn($q) => $q->where('next_promotion_due_date', '>=', $start))
            ->when($end, fn($q) => $q->where('next_promotion_due_date', '<=', $end))
            ->get();

        foreach ($promoDue as $s) {
            $name = $s->user?->surname . ' ' . $s->user?->firstname . ' ' . $s->user?->othername;
            $overdue = Carbon::parse($s->next_promotion_due_date)->isPast();
            $events[] = [
                'id' => 'promo-' . $s->id,
                'title' => $name . ' — Promotion Due',
                'start' => Carbon::parse($s->next_promotion_due_date)->toDateString(),
                'color' => $overdue ? '#dc2626' : '#059669',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'promotion_due',
                    'staff' => $name,
                    'overdue' => $overdue,
                    'staff_id' => $s->id,
                ],
            ];
        }

        // License expiry dates
        $licExpiry = (clone $staffQuery)->whereNotNull('license_expiry_date')
            ->when($start, fn($q) => $q->where('license_expiry_date', '>=', $start))
            ->when($end, fn($q) => $q->where('license_expiry_date', '<=', $end))
            ->get();

        foreach ($licExpiry as $s) {
            $name = $s->user?->surname . ' ' . $s->user?->firstname . ' ' . $s->user?->othername;
            $expired = Carbon::parse($s->license_expiry_date)->isPast();
            $events[] = [
                'id' => 'lic-' . $s->id,
                'title' => $name . ' — License ' . ($expired ? 'Expired' : 'Expiry'),
                'start' => Carbon::parse($s->license_expiry_date)->toDateString(),
                'color' => $expired ? '#be123c' : '#0891b2',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'license_expiry',
                    'staff' => $name,
                    'license_number' => $s->license_number,
                    'overdue' => $expired,
                    'staff_id' => $s->id,
                ],
            ];
        }

        // Confirmation due dates (unconfirmed staff only)
        $confirmDue = (clone $staffQuery)->whereNotNull('confirmation_due_date')
            ->whereNull('date_confirmed')
            ->when($start, fn($q) => $q->where('confirmation_due_date', '>=', $start))
            ->when($end, fn($q) => $q->where('confirmation_due_date', '<=', $end))
            ->get();

        foreach ($confirmDue as $s) {
            $name = $s->user?->surname . ' ' . $s->user?->firstname . ' ' . $s->user?->othername;
            $overdue = Carbon::parse($s->confirmation_due_date)->isPast();
            $events[] = [
                'id' => 'conf-' . $s->id,
                'title' => $name . ' — Confirmation Due',
                'start' => Carbon::parse($s->confirmation_due_date)->toDateString(),
                'color' => $overdue ? '#9333ea' : '#7c3aed',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'confirmation_due',
                    'staff' => $name,
                    'overdue' => $overdue,
                    'staff_id' => $s->id,
                ],
            ];
        }

        // Retirement date events (staff with retirement_date within range)
        $retiring = (clone $staffQuery)->whereNotNull('retirement_date')
            ->when($start, fn($q) => $q->where('retirement_date', '>=', $start))
            ->when($end, fn($q) => $q->where('retirement_date', '<=', $end))
            ->get();

        foreach ($retiring as $s) {
            $name = $s->user?->surname . ' ' . $s->user?->firstname . ' ' . $s->user?->othername;
            $past = Carbon::parse($s->retirement_date)->isPast();
            $events[] = [
                'id' => 'retire-' . $s->id,
                'title' => $name . ' — ' . ($past ? 'Retired' : 'Retirement'),
                'start' => Carbon::parse($s->retirement_date)->toDateString(),
                'color' => $past ? '#6b7280' : '#ea580c',
                'textColor' => '#fff',
                'extendedProps' => [
                    'type' => 'retirement',
                    'staff' => $name,
                    'past' => $past,
                    'staff_id' => $s->id,
                ],
            ];
        }

        return response()->json($events);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use App\Models\ProcedureAttachment;
use App\Models\ProcedureNote;
use App\Models\ProcedureTeamMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Native client adapter for the Surgery workbench's procedure page.
 *
 * Only adapts: the page (PatientProcedureController@show) is a Blade view that passes the procedure's
 * checklist to the template. This returns that same checklist as JSON; every action goes to
 * PatientProcedureController.
 */
class MobileSurgeryController extends Controller
{
    /** GET /api/mobile/surgery/procedure/{procedure}/checklist */
    public function checklist(Procedure $procedure)
    {
        return response()->json(['success' => true, 'items' => $procedure->getChecklistItems()]);
    }

    /**
     * GET /api/mobile/surgery/procedure/{procedure}/meta
     *
     * What the Blade page knows and the app cannot read: the lists the page's selects use (team roles, note types,
     * outcomes, consent statuses), whether the procedure is surgical (changes labels), the preparation summary, the
     * attachments, and what the signed-in user may do. The abilities repeat the role lists of the page's
     * @hasanyrole blocks (patient-procedures/show.blade.php); the actions themselves are still checked by the web
     * controller methods the app calls.
     */
    public function meta(Procedure $procedure)
    {
        $procedure->loadMissing(['procedureDefinition', 'service.procedureDefinition', 'attachments.uploadedBy', 'consentMarkedBy', 'cancelledByUser']);
        $user = Auth::user();
        $has = fn (string $roles) => $user->hasAnyRole(explode('|', $roles));
        $def = $procedure->procedureDefinition ?? optional($procedure->service)->procedureDefinition;

        $pageRole = $user->hasRole('DOCTOR') ? 'doctor' : ($user->hasAnyRole(['NURSE', 'SURGERY']) ? 'nurse' : 'admin');
        $done = in_array($procedure->procedure_status, [Procedure::STATUS_COMPLETED, Procedure::STATUS_CANCELLED], true);

        return response()->json([
            'success' => true,
            'me' => $user->id,
            'is_surgical' => (bool) optional($def)->is_surgical,
            'is_free_form' => (bool) $procedure->is_free_form,
            'page_role' => $pageRole,
            'statuses' => Procedure::STATUSES,
            'priorities' => Procedure::PRIORITIES,
            'outcomes' => Procedure::OUTCOMES,
            'consent_statuses' => Procedure::CONSENT_STATUSES,
            'team_roles' => ProcedureTeamMember::ROLES,
            'note_types' => ProcedureNote::NOTE_TYPES,
            'prep_summary' => $procedure->prep_summary,
            'billed' => $procedure->product_or_service_request_id !== null,
            'consent' => [
                'status' => $procedure->consent_status,
                'notes' => $procedure->consent_notes,
                'marked_by' => optional($procedure->consentMarkedBy)->name,
                'marked_at' => optional($procedure->consent_marked_at)->format('d M Y H:i'),
            ],
            'cancelled' => $procedure->procedure_status === Procedure::STATUS_CANCELLED ? [
                'by' => optional($procedure->cancelledByUser)->name,
                'at' => optional($procedure->cancelled_at)->format('d M Y H:i'),
                'reason' => $procedure->cancellation_reason,
                'refund_amount' => $procedure->refund_amount,
            ] : null,
            'abilities' => [
                'schedule' => !$done && $has('SUPERADMIN|ADMIN|DOCTOR'),
                'consent_mark' => !$done && $has('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY'),
                'consent_change_after_obtained' => $has('SUPERADMIN|ADMIN'),
                'bill_base_fee' => $has('SUPERADMIN|ADMIN|DOCTOR|SURGERY'),
                'team_manage' => !$done && $has('SUPERADMIN|ADMIN|DOCTOR'),
                'note_add' => $has('SUPERADMIN|ADMIN|DOCTOR|NURSE'),
                'quick_nursing_note' => !$done && $has('NURSE|SURGERY'),
                'item_add' => !$done && $has('SUPERADMIN|ADMIN|DOCTOR'),
                'services_rendered' => !$done && $has('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY'),
                'attachment_upload' => $has('SUPERADMIN|ADMIN|DOCTOR|NURSE|SURGERY'),
                'outcome' => $has('SUPERADMIN|ADMIN|DOCTOR'),
                'next_action' => $has('SUPERADMIN|ADMIN|DOCTOR'),
                'reopen' => $has('SUPERADMIN|ADMIN'),
                'cancel' => !$done && $has('SUPERADMIN|ADMIN'),
                'delete_any_attachment' => $has('SUPERADMIN|ADMIN|DOCTOR'),
            ],
            'attachments' => $procedure->attachments->map(fn ($a) => $this->attachmentRow($procedure, $a))->values(),
        ]);
    }

    /** GET /api/mobile/surgery/procedure/{procedure}/attachments */
    public function attachments(Procedure $procedure)
    {
        $procedure->loadMissing('attachments.uploadedBy');

        return response()->json(['success' => true, 'attachments' => $procedure->attachments->map(fn ($a) => $this->attachmentRow($procedure, $a))->values()]);
    }

    /**
     * GET /api/mobile/surgery/procedure/{procedure}/attachments/{attachment}/download
     * The web route is session based; this streams the same file for the app's token.
     */
    public function downloadAttachment(Procedure $procedure, ProcedureAttachment $attachment)
    {
        abort_unless($attachment->procedure_id === $procedure->id, 403, 'Attachment does not belong to this procedure.');
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404, 'File not found.');

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }

    /** GET /api/mobile/surgery/staff-search?search= : the team picker's staff list (the web's api/staff-search). */
    public function staffSearch(Request $request)
    {
        $query = User::whereHas('staff_profile')->where('status', '>', 0);
        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('firstname', 'LIKE', "%{$term}%")->orWhere('surname', 'LIKE', "%{$term}%")->orWhere('othername', 'LIKE', "%{$term}%");
            });
        }

        return response()->json(['data' => $query->orderBy('surname')->orderBy('firstname')->limit(50)->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->surname . ' ' . $u->firstname . ($u->othername ? ' ' . $u->othername : ''),
        ])]);
    }

    private function attachmentRow(Procedure $procedure, ProcedureAttachment $a): array
    {
        return [
            'id' => $a->id,
            'original_name' => $a->original_name,
            'label' => $a->label,
            'formatted_size' => $a->formattedSize(),
            'mime_type' => $a->mime_type,
            'uploaded_by' => optional($a->uploadedBy)->name ?? 'Unknown',
            'uploaded_by_id' => $a->uploaded_by,
            'created_at' => optional($a->created_at)->format('d M Y H:i'),
            'download_url' => url("/api/mobile/surgery/procedure/{$procedure->id}/attachments/{$a->id}/download"),
        ];
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\LabWorkbenchController;
use Illuminate\Support\Facades\Auth;

/**
 * Native client adapter for the Laboratory workbench. Only adapts: every action goes to
 * LabWorkbenchController, the controller the web workbench uses.
 */
class MobileLabController extends Controller
{
    /** GET /api/mobile/lab/departments : the bench chips of the queue (the web builds them into the page). */
    public function departments()
    {
        return response()->json(['success' => true, 'data' => LabWorkbenchController::getLabDepartments()]);
    }

    /**
     * GET /api/mobile/lab/meta : the two flags the web page gets from LabWorkbenchController@index (isApprover,
     * requiresApproval; the same expressions), so the app shows Review and Approvals only to approvers and asks
     * for the approver's name only when results do not go through approval.
     */
    public function meta()
    {
        $user = Auth::user();
        $staff = $user->staff_profile;

        return response()->json([
            'success' => true,
            'is_approver' => ($staff && ($staff->is_unit_head || $staff->is_dept_head)) || $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']),
            'requires_approval' => (bool) appsettings('lab_results_require_approval'),
            'result_edit_minutes' => (int) (appsettings('result_edit_duration') ?? 60),
            'me' => $user->id,
        ]);
    }
}

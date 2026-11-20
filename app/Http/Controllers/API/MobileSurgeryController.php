<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Procedure;

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
}

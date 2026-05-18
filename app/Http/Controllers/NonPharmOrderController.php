<?php

namespace App\Http\Controllers;

use App\Models\NonPharmOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NonPharmOrderController extends Controller
{
    /**
     * Get non-pharmacological orders for a given patient.
     */
    public function getPatientOrders(Request $request, $patientId)
    {
        $query = NonPharmOrder::with(['requestedByUser', 'completedByUser', 'discontinuedByUser'])
            ->where('patient_id', $patientId);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('target_executor')) {
            $query->where('target_executor', $request->input('target_executor'));
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    /**
     * Store a newly created non-pharmacological order.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|exists:patients,id',
            'encounter_id' => 'nullable|exists:encounters,id',
            'maternity_enrollment_id' => 'nullable|exists:maternity_enrollments,id',
            'category' => 'required|string|max:50',
            'target_executor' => 'required|string|in:patient,nurse',
            'instructions' => 'required|string',
            'frequency' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $validator->errors()->all())
            ], 422);
        }

        $order = NonPharmOrder::create([
            'patient_id' => $request->input('patient_id'),
            'encounter_id' => $request->input('encounter_id'),
            'maternity_enrollment_id' => $request->input('maternity_enrollment_id'),
            'requested_by' => auth()->id() ?? 1,
            'category' => $request->input('category'),
            'target_executor' => $request->input('target_executor'),
            'instructions' => $request->input('instructions'),
            'frequency' => $request->input('frequency'),
            'duration' => $request->input('duration'),
            'status' => 'active',
        ]);

        $order->load(['requestedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Care order added successfully',
            'order' => $order
        ]);
    }

    /**
     * Mark a bedside care order as completed/performed by the nurse.
     */
    public function complete(Request $request, $id)
    {
        $order = NonPharmOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Care order not found'
            ], 404);
        }

        $order->update([
            'status' => 'completed',
            'completed_by' => auth()->id() ?? 1,
            'completed_at' => now(),
            'completed_notes' => $request->input('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Care order marked as completed',
            'order' => $order->load(['completedByUser'])
        ]);
    }

    /**
     * Discontinue / Delete a care order.
     */
    public function destroy(Request $request, $id)
    {
        $order = NonPharmOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Care order not found'
            ], 404);
        }

        if ($request->input('action') === 'discontinue') {
            $order->update([
                'status' => 'discontinued',
                'discontinued_by' => auth()->id() ?? 1,
                'discontinued_at' => now(),
                'discontinue_reason' => $request->input('reason', 'Discontinued by clinical request'),
            ]);
            $msg = 'Care order discontinued successfully';
        } else {
            $order->delete();
            $msg = 'Care order removed successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $msg
        ]);
    }
}

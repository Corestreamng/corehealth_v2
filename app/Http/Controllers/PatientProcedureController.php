<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\ProcedureTeamMember;
use App\Models\ProcedureNote;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\ProductRequest;
use App\Models\ProductOrServiceRequest;
use App\Models\PatientAccount;
use App\Helpers\HmoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * PatientProcedureController
 *
 * Dedicated controller for patient procedure management.
 * Handles the detailed procedure view, items management (bundled/separate billing),
 * and outcome tracking as specified in PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.4
 */
class PatientProcedureController extends Controller
{
    /**
     * Display the procedure detail page (opens in new tab)
     * Spec Reference: Part 3.4, 3.5.2
     */
    public function show(Procedure $procedure)
    {
        $procedure->load([
            'service.price',
            'procedureDefinition.procedureCategory',
            'requestedByUser',
            'billedByUser',
            'preNotesBy',
            'postNotesBy',
            'cancelledByUser',
            'patient.hmo',
            'encounter',
            'teamMembers.user',
            'notes.createdBy',
            'items.labServiceRequest.service.price',
            'items.imagingServiceRequest.service.price',
            'items.productRequest.product.price',
            'items.productOrServiceRequest',
            'productOrServiceRequest.payment',
        ]);

        return view('admin.patient-procedures.show', compact('procedure'));
    }

    /**
     * Update procedure status
     * Spec Reference: Part 3.4
     */
    public function update(Request $request, Procedure $procedure)
    {
        $request->validate([
            'procedure_status' => 'nullable|in:requested,scheduled,in_progress,completed,cancelled',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'operating_room' => 'nullable|string|max:100',
            'pre_notes' => 'nullable|string',
            'post_notes' => 'nullable|string',
        ]);

        try {
            if ($request->has('procedure_status')) {
                $procedure->procedure_status = $request->procedure_status;

                if ($request->procedure_status === Procedure::STATUS_IN_PROGRESS && !$procedure->actual_start_time) {
                    $procedure->actual_start_time = now();
                }

                if ($request->procedure_status === Procedure::STATUS_COMPLETED && !$procedure->actual_end_time) {
                    $procedure->actual_end_time = now();
                }
            }

            if ($request->has('scheduled_date')) {
                $procedure->scheduled_date = $request->scheduled_date;
            }

            if ($request->has('scheduled_time')) {
                $procedure->scheduled_time = $request->scheduled_time;
            }

            if ($request->has('operating_room')) {
                $procedure->operating_room = $request->operating_room;
            }

            if ($request->has('pre_notes')) {
                $procedure->pre_notes = $request->pre_notes;
                $procedure->pre_notes_by = Auth::id();
            }

            if ($request->has('post_notes')) {
                $procedure->post_notes = $request->post_notes;
                $procedure->post_notes_by = Auth::id();
            }

            $procedure->save();

            return response()->json([
                'success' => true,
                'message' => 'Procedure updated successfully',
                'procedure' => $procedure->fresh()->load(['service', 'productOrServiceRequest'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update procedure outcome
     * Spec Reference: Part 3.4, 3.1.3
     */
    public function updateOutcome(Request $request, Procedure $procedure)
    {
        $request->validate([
            'outcome' => 'required|in:successful,complications,aborted,converted',
            'outcome_notes' => 'nullable|string|max:5000',
        ]);

        try {
            $procedure->outcome = $request->outcome;
            $procedure->outcome_notes = $request->outcome_notes;
            $procedure->save();

            return response()->json([
                'success' => true,
                'message' => 'Outcome saved successfully',
                'outcome' => $procedure->outcome,
                'outcome_notes' => $procedure->outcome_notes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving outcome: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete procedure (shortcut to mark as completed)
     * Spec Reference: Part 3.4
     */
    public function complete(Request $request, Procedure $procedure)
    {
        try {
            if ($procedure->procedure_status === Procedure::STATUS_CANCELLED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete a cancelled procedure'
                ], 422);
            }

            $procedure->procedure_status = Procedure::STATUS_COMPLETED;
            $procedure->actual_end_time = now();

            if (!$procedure->actual_start_time) {
                $procedure->actual_start_time = now();
            }

            $procedure->save();

            return response()->json([
                'success' => true,
                'message' => 'Procedure marked as completed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error completing procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // ITEMS MANAGEMENT - BUNDLED BILLING
    // Spec Reference: Part 3.2.1, 3.2.2, 3.4
    // =========================================================================

    /**
     * Get all items for a procedure
     */
    public function getItems(Procedure $procedure)
    {
        $items = $procedure->items()->with([
            'labServiceRequest.service',
            'imagingServiceRequest.service',
            'productRequest.product',
            'productOrServiceRequest.payment'
        ])->get();

        return response()->json([
            'success' => true,
            'items' => $items->map(function ($item) {
                return $this->formatItemResponse($item);
            })
        ]);
    }

    /**
     * Add a lab request to the procedure
     * Spec Reference: Part 3.2.1, 3.2.2, 3.4
     */
    public function addLabRequest(Request $request, Procedure $procedure)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'is_bundled' => 'required|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($request, $procedure) {
                $service = \App\Models\service::with('price')->find($request->service_id);

                // Create the lab service request
                $labRequest = new LabServiceRequest();
                $labRequest->service_id = $service->id;
                $labRequest->patient_id = $procedure->patient_id;
                $labRequest->encounter_id = $procedure->encounter_id;
                $labRequest->doctor_id = Auth::id();
                $labRequest->note = $request->note;

                // Handle status based on is_bundled flag
                if ($request->is_bundled) {
                    // Bundled: No separate billing, go directly to "sample collection" (status 2)
                    $labRequest->status = 2;
                } else {
                    // Non-bundled: Let Lab Workbench handle billing, stays at "pending billing" (status 1)
                    $labRequest->status = 1;
                }

                $labRequest->save();

                // Create procedure item to track the link
                $procedureItem = new ProcedureItem();
                $procedureItem->procedure_id = $procedure->id;
                $procedureItem->lab_service_request_id = $labRequest->id;
                $procedureItem->is_bundled = $request->is_bundled;
                $procedureItem->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Lab request added successfully',
                    'item' => $this->formatItemResponse($procedureItem->fresh()->load([
                        'labServiceRequest.service.price',
                        'productOrServiceRequest'
                    ]))
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding lab request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add an imaging request to the procedure
     * Spec Reference: Part 3.2.1, 3.2.2, 3.4
     */
    public function addImagingRequest(Request $request, Procedure $procedure)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'is_bundled' => 'required|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($request, $procedure) {
                $service = \App\Models\service::with('price')->find($request->service_id);

                // Create the imaging service request
                $imagingRequest = new ImagingServiceRequest();
                $imagingRequest->service_id = $service->id;
                $imagingRequest->patient_id = $procedure->patient_id;
                $imagingRequest->encounter_id = $procedure->encounter_id;
                $imagingRequest->doctor_id = Auth::id();
                $imagingRequest->note = $request->note;

                // Handle status based on is_bundled flag
                if ($request->is_bundled) {
                    // Bundled: No separate billing, go directly to "ready" (status 2)
                    $imagingRequest->status = 2;
                } else {
                    // Non-bundled: Let Imaging Workbench handle billing, stays at "pending billing" (status 1)
                    $imagingRequest->status = 1;
                }

                $imagingRequest->save();

                // Create procedure item to track the link
                $procedureItem = new ProcedureItem();
                $procedureItem->procedure_id = $procedure->id;
                $procedureItem->imaging_service_request_id = $imagingRequest->id;
                $procedureItem->is_bundled = $request->is_bundled;
                $procedureItem->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Imaging request added successfully',
                    'item' => $this->formatItemResponse($procedureItem->fresh()->load([
                        'imagingServiceRequest.service.price',
                        'productOrServiceRequest'
                    ]))
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding imaging request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a medication/product to the procedure
     * Spec Reference: Part 3.2.1, 3.2.2, 3.4
     */
    public function addMedication(Request $request, Procedure $procedure)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'dose' => 'nullable|string|max:255',
            'is_bundled' => 'required|boolean',
        ]);

        try {
            return DB::transaction(function () use ($request, $procedure) {
                $product = \App\Models\Product::with('price')->find($request->product_id);

                // Create the product request
                $productRequest = new ProductRequest();
                $productRequest->product_id = $product->id;
                $productRequest->patient_id = $procedure->patient_id;
                $productRequest->encounter_id = $procedure->encounter_id;
                $productRequest->doctor_id = Auth::id();
                $productRequest->qty = $request->qty;
                $productRequest->dose = $request->dose;

                // Handle status based on is_bundled flag
                if ($request->is_bundled) {
                    // Bundled: No separate billing, go directly to "ready for dispense" (status 2)
                    $productRequest->status = 2;
                } else {
                    // Non-bundled: Let Pharmacy Workbench handle billing, stays at "pending billing" (status 1)
                    $productRequest->status = 1;
                }

                $productRequest->save();

                // Create procedure item to track the link
                $procedureItem = new ProcedureItem();
                $procedureItem->procedure_id = $procedure->id;
                $procedureItem->product_request_id = $productRequest->id;
                $procedureItem->is_bundled = $request->is_bundled;
                $procedureItem->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Medication added successfully',
                    'item' => $this->formatItemResponse($procedureItem->fresh()->load([
                        'productRequest.product.price',
                        'productOrServiceRequest'
                    ]))
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding medication: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove an item from the procedure
     * Spec Reference: Part 3.4
     */
    public function removeItem(Procedure $procedure, ProcedureItem $item)
    {
        try {
            // Verify item belongs to this procedure
            if ($item->procedure_id !== $procedure->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item does not belong to this procedure'
                ], 403);
            }

            DB::transaction(function () use ($item) {
                // Delete the associated request based on type
                if ($item->lab_service_request_id) {
                    LabServiceRequest::where('id', $item->lab_service_request_id)->delete();
                }

                if ($item->imaging_service_request_id) {
                    ImagingServiceRequest::where('id', $item->imaging_service_request_id)->delete();
                }

                if ($item->product_request_id) {
                    ProductRequest::where('id', $item->product_request_id)->delete();
                }

                // Delete the billing entry if non-bundled
                if (!$item->is_bundled && $item->product_or_service_request_id) {
                    ProductOrServiceRequest::where('id', $item->product_or_service_request_id)->delete();
                }

                // Delete the procedure item
                $item->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing item: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // TEAM MEMBERS
    // Spec Reference: Part 3.1.4, 3.4
    // =========================================================================

    /**
     * Get team members for a procedure
     */
    public function getTeam(Procedure $procedure)
    {
        $members = $procedure->teamMembers()->with('user')->get();

        return response()->json([
            'success' => true,
            'team' => $members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user_name' => optional($member->user)->name,
                    'role' => $member->role,
                    'custom_role' => $member->custom_role,
                    'display_role' => $member->role === 'other' ? $member->custom_role : ucfirst(str_replace('_', ' ', $member->role)),
                    'is_lead' => $member->is_lead,
                    'notes' => $member->notes,
                ];
            })
        ]);
    }

    /**
     * Add a team member to the procedure
     * Spec Reference: Part 3.1.4, 3.4
     */
    public function addTeamMember(Request $request, Procedure $procedure)
    {
        $validRoles = implode(',', array_keys(ProcedureTeamMember::ROLES));

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:' . $validRoles,
            'custom_role' => 'required_if:role,other|nullable|string|max:100',
            'is_lead' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Check for duplicate
            $existing = ProcedureTeamMember::where('procedure_id', $procedure->id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is already a team member'
                ], 422);
            }

            $member = new ProcedureTeamMember();
            $member->procedure_id = $procedure->id;
            $member->user_id = $request->user_id;
            $member->role = $request->role;
            $member->custom_role = $request->role === 'other' ? $request->custom_role : null;
            $member->is_lead = $request->is_lead ?? false;
            $member->notes = $request->notes;
            $member->save();

            return response()->json([
                'success' => true,
                'message' => 'Team member added successfully',
                'member' => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user_name' => optional($member->user)->name,
                    'role' => $member->role,
                    'custom_role' => $member->custom_role,
                    'display_role' => $member->role === 'other' ? $member->custom_role : ucfirst(str_replace('_', ' ', $member->role)),
                    'is_lead' => $member->is_lead,
                    'notes' => $member->notes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding team member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a team member
     */
    public function updateTeamMember(Request $request, Procedure $procedure, ProcedureTeamMember $member)
    {
        if ($member->procedure_id !== $procedure->id) {
            return response()->json([
                'success' => false,
                'message' => 'Member does not belong to this procedure'
            ], 403);
        }

        $request->validate([
            'role' => 'nullable|in:lead_surgeon,assistant_surgeon,anesthesiologist,scrub_nurse,circulating_nurse,other',
            'custom_role' => 'required_if:role,other|nullable|string|max:100',
            'is_lead' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            if ($request->has('role')) {
                $member->role = $request->role;
                $member->custom_role = $request->role === 'other' ? $request->custom_role : null;
            }

            if ($request->has('is_lead')) {
                $member->is_lead = $request->is_lead;
            }

            if ($request->has('notes')) {
                $member->notes = $request->notes;
            }

            $member->save();

            return response()->json([
                'success' => true,
                'message' => 'Team member updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating team member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a team member
     */
    public function removeTeamMember(Procedure $procedure, ProcedureTeamMember $member)
    {
        if ($member->procedure_id !== $procedure->id) {
            return response()->json([
                'success' => false,
                'message' => 'Member does not belong to this procedure'
            ], 403);
        }

        try {
            $member->delete();

            return response()->json([
                'success' => true,
                'message' => 'Team member removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing team member: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // NOTES (CKEditor WYSIWYG)
    // Spec Reference: Part 3.1.5, 3.4
    // =========================================================================

    /**
     * Get notes for a procedure
     */
    public function getNotes(Procedure $procedure)
    {
        $notes = $procedure->notes()->with(['createdBy', 'updatedBy'])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'notes' => $notes->map(function ($note) {
                return [
                    'id' => $note->id,
                    'note_type' => $note->note_type,
                    'title' => $note->title,
                    'content' => $note->content,
                    'created_by' => optional($note->createdBy)->name,
                    'updated_by' => optional($note->updatedBy)->name,
                    'created_at' => $note->created_at->format('d M Y H:i'),
                    'updated_at' => $note->updated_at->format('d M Y H:i'),
                ];
            })
        ]);
    }

    /**
     * Add a note to the procedure
     * Spec Reference: Part 3.1.5, 3.4
     */
    public function addNote(Request $request, Procedure $procedure)
    {
        $request->validate([
            'note_type' => 'required|in:pre_op,intra_op,post_op,anesthesia,nursing',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        try {
            $note = new ProcedureNote();
            $note->procedure_id = $procedure->id;
            $note->note_type = $request->note_type;
            $note->title = $request->title;
            $note->content = $request->content;
            $note->created_by = Auth::id();
            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'note' => [
                    'id' => $note->id,
                    'note_type' => $note->note_type,
                    'title' => $note->title,
                    'content' => $note->content,
                    'created_by' => optional($note->createdBy)->name,
                    'created_at' => $note->created_at->format('d M Y H:i'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single note for editing
     */
    public function getNote(Procedure $procedure, ProcedureNote $note)
    {
        if ($note->procedure_id !== $procedure->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this procedure'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'note' => [
                'id' => $note->id,
                'note_type' => $note->note_type,
                'title' => $note->title,
                'content' => $note->content,
            ]
        ]);
    }

    /**
     * Update a note
     */
    public function updateNote(Request $request, Procedure $procedure, ProcedureNote $note)
    {
        if ($note->procedure_id !== $procedure->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this procedure'
            ], 403);
        }

        // Only the creator can edit the note
        if ($note->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit notes you created'
            ], 403);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);

        try {
            if ($request->has('title')) {
                $note->title = $request->title;
            }

            if ($request->has('content')) {
                $note->content = $request->content;
            }

            $note->save();

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a note
     */
    public function deleteNote(Procedure $procedure, ProcedureNote $note)
    {
        if ($note->procedure_id !== $procedure->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this procedure'
            ], 403);
        }

        // Only the creator can delete the note
        if ($note->created_by !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete notes you created'
            ], 403);
        }

        try {
            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting note: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // CANCEL WITH REFUND
    // Spec Reference: Part 3.2.5, 3.4
    // =========================================================================

    /**
     * Cancel procedure with optional refund
     */
    public function cancel(Request $request, Procedure $procedure)
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
            'refund_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($request, $procedure) {
                $refundAmount = $request->refund_amount ?? 0;

                // Process refund if applicable
                if ($refundAmount > 0 && $procedure->productOrServiceRequest) {
                    $billingEntry = $procedure->productOrServiceRequest;

                    // Check if there's a payment to refund
                    if ($billingEntry->payment_id) {
                        // Get or create patient account
                        $account = PatientAccount::firstOrCreate(
                            ['patient_id' => $procedure->patient_id],
                            ['balance' => 0]
                        );

                        // Credit refund to account
                        $account->balance += $refundAmount;
                        $account->save();

                        // Create refund payment record
                        $payment = new \App\Models\Payment();
                        $payment->patient_id = $procedure->patient_id;
                        $payment->amount = $refundAmount;
                        $payment->payment_type = 'ACC_DEPOSIT';
                        $payment->payment_method = 'REFUND';
                        $payment->payment_note = "Refund for cancelled procedure: " . optional($procedure->service)->service_name;
                        $payment->created_by = Auth::id();
                        $payment->save();
                    }
                }

                // Cancel bundled items
                $bundledItems = $procedure->items()->where('is_bundled', true)->get();
                foreach ($bundledItems as $item) {
                    if ($item->lab_service_request_id) {
                        LabServiceRequest::where('id', $item->lab_service_request_id)
                            ->update(['status' => 'cancelled']);
                    }
                    if ($item->imaging_service_request_id) {
                        ImagingServiceRequest::where('id', $item->imaging_service_request_id)
                            ->update(['status' => 'cancelled']);
                    }
                    if ($item->product_request_id) {
                        ProductRequest::where('id', $item->product_request_id)
                            ->update(['status' => 'cancelled']);
                    }
                }

                // Update procedure
                $procedure->procedure_status = Procedure::STATUS_CANCELLED;
                $procedure->cancellation_reason = $request->cancellation_reason;
                $procedure->refund_amount = $refundAmount;
                $procedure->cancelled_at = now();
                $procedure->cancelled_by = Auth::id();
                $procedure->save();

                return response()->json([
                    'success' => true,
                    'message' => $refundAmount > 0
                        ? "Procedure cancelled. ₦" . number_format($refundAmount, 2) . " refunded to patient account."
                        : "Procedure cancelled successfully."
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cancelling procedure: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // PROCEDURE HISTORY LISTS - For DataTable display
    // Mirrors workbench history tabs but filtered by procedure items
    // =========================================================================

    /**
     * Get lab history for a procedure (DataTable endpoint)
     * Returns card-modern formatted HTML matching Lab Workbench history tab
     */
    public function labHistoryList(Procedure $procedure)
    {
        // Get lab service request IDs linked to this procedure
        $labRequestIds = $procedure->items()
            ->whereNotNull('lab_service_request_id')
            ->pluck('lab_service_request_id')
            ->toArray();

        $requests = LabServiceRequest::with([
            'service', 'encounter', 'patient', 'patient.user',
            'productOrServiceRequest', 'doctor', 'biller', 'results_person'
        ])
            ->whereIn('id', $labRequestIds)
            ->orderBy('created_at', 'DESC')
            ->get();

        return \Yajra\DataTables\Facades\DataTables::of($requests)
            ->addColumn('info', function ($req) use ($procedure) {
                return $this->formatLabHistoryCard($req, $procedure);
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Get imaging history for a procedure (DataTable endpoint)
     * Returns card-modern formatted HTML matching Imaging Workbench history tab
     */
    public function imagingHistoryList(Procedure $procedure)
    {
        // Get imaging service request IDs linked to this procedure
        $imagingRequestIds = $procedure->items()
            ->whereNotNull('imaging_service_request_id')
            ->pluck('imaging_service_request_id')
            ->toArray();

        $requests = ImagingServiceRequest::with([
            'service', 'encounter', 'patient', 'patient.user',
            'productOrServiceRequest', 'doctor', 'biller', 'results_person'
        ])
            ->whereIn('id', $imagingRequestIds)
            ->orderBy('created_at', 'DESC')
            ->get();

        return \Yajra\DataTables\Facades\DataTables::of($requests)
            ->addColumn('info', function ($req) use ($procedure) {
                return $this->formatImagingHistoryCard($req, $procedure);
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Get medication history for a procedure (DataTable endpoint)
     * Returns card-modern formatted HTML matching Pharmacy Workbench history tab
     */
    public function medicationHistoryList(Procedure $procedure)
    {
        // Get product request IDs linked to this procedure
        $productRequestIds = $procedure->items()
            ->whereNotNull('product_request_id')
            ->pluck('product_request_id')
            ->toArray();

        $requests = ProductRequest::with([
            'product.price', 'product.category', 'encounter', 'patient',
            'productOrServiceRequest.payment', 'doctor', 'biller', 'dispenser'
        ])
            ->whereIn('id', $productRequestIds)
            ->orderBy('created_at', 'DESC')
            ->get();

        return \Yajra\DataTables\Facades\DataTables::of($requests)
            ->addColumn('info', function ($req) use ($procedure) {
                return $this->formatMedicationHistoryCard($req, $procedure);
            })
            ->rawColumns(['info'])
            ->make(true);
    }

    /**
     * Format lab request as card-modern HTML (matching workbench style)
     */
    private function formatLabHistoryCard($req, $procedure)
    {
        $procedureItem = $procedure->items()->where('lab_service_request_id', $req->id)->first();
        $isBundled = $procedureItem ? $procedureItem->is_bundled : false;

        $str = '<div class="card-modern mb-2" style="border-left: 4px solid #0d6efd;">';
        $str .= '<div class="card-body p-3">';

        // Header with service name and status
        $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
        $str .= "<h6 class='mb-0'><span class='badge bg-primary'><i class='fa fa-flask'></i> " . (($req->service) ? $req->service->service_name : 'N/A') . '</span></h6>';

        // Status badges
        $str .= '<div>';
        if ($req->result) {
            $str .= "<span class='badge bg-info'>Result Available</span> ";
        } elseif ($req->sample_taken_by) {
            $str .= "<span class='badge bg-warning'>Sample Taken</span> ";
        } elseif ($req->billed_by) {
            $str .= "<span class='badge bg-success'>Billed</span> ";
        } else {
            $str .= "<span class='badge bg-secondary'>Pending</span> ";
        }

        // Bundled badge
        if ($isBundled) {
            $str .= "<span class='badge bg-info'>Bundled</span> ";
        }

        // HMO Coverage Badge
        if ($req->productOrServiceRequest && $req->productOrServiceRequest->coverage_mode) {
            $coverageClass = $req->productOrServiceRequest->coverage_mode === 'express' ? 'success' :
                           ($req->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
            $str .= "<span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($req->productOrServiceRequest->coverage_mode) . '</span>';
        }
        $str .= '</div></div>';

        // Timeline section
        $str .= '<div class="mb-3"><small>';
        $str .= '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
            . ((isset($req->doctor_id) && $req->doctor_id != null) ? (userfullname($req->doctor_id) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->created_at)) . ')</span>') : "<span class='badge bg-secondary'>N/A</span>") . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
            . ((isset($req->billed_by) && $req->billed_by != null) ? (userfullname($req->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->billed_date)) . ')</span>') : ($isBundled ? "<span class='badge bg-info'>Bundled with Procedure</span>" : "<span class='badge bg-secondary'>Not billed</span>")) . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-test-tube text-warning"></i> <b>Sample taken by:</b> '
            . ((isset($req->sample_taken_by) && $req->sample_taken_by != null) ? (userfullname($req->sample_taken_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->sample_date)) . ')</span>') : "<span class='badge bg-secondary'>Not taken</span>") . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> '
            . ((isset($req->result_by) && $req->result_by != null) ? (userfullname($req->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->result_date)) . ')</span>') : "<span class='badge bg-secondary'>Awaiting Results</span>") . '</div>';
        $str .= '</small></div>';

        // Results section
        if ($req->result) {
            $str .= '<div class="alert alert-light mb-2"><small><b>Result:</b><br>' . $req->result . '</small></div>';
        }

        // Request note
        if (isset($req->note) && $req->note != null) {
            $str .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . $req->note . '</small></div>';
        }

        // Attachments
        if ($req->attachments) {
            $attachments = is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments;
            if (!empty($attachments)) {
                $str .= "<div class='mb-2'><small><b><i class='mdi mdi-paperclip'></i> Attachments:</b> ";
                foreach ($attachments as $attachment) {
                    $url = asset('storage/' . $attachment['path']);
                    $str .= "<a href='{$url}' target='_blank' class='badge bg-info text-white me-1'><i class='fa fa-file'></i> {$attachment['name']}</a> ";
                }
                $str .= "</small></div>";
            }
        }

        // Delivery guard check
        if (!$isBundled && $req->productOrServiceRequest) {
            $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($req->productOrServiceRequest);
            if (!$deliveryCheck['can_deliver']) {
                $str .= "<div class='alert alert-warning py-2 mb-2 mt-2'><small>";
                $str .= "<i class='fa fa-exclamation-triangle'></i> <b>" . $deliveryCheck['reason'] . "</b><br>";
                $str .= $deliveryCheck['hint'];
                $str .= "</small></div>";
            }
        }

        // Action buttons
        $str .= '<div class="btn-group mt-2" role="group">';
        $str .= "<button type='button' class='btn btn-info btn-sm' onclick='viewLabResult({$req->id})'><i class='mdi mdi-eye'></i> View</button>";
        $str .= "<a href='" . route('service-requests.show', $req->id) . "' target='_blank' class='btn btn-primary btn-sm'><i class='mdi mdi-printer'></i> Print</a>";
        $str .= '</div>';

        $str .= '</div></div>';
        return $str;
    }

    /**
     * Format imaging request as card-modern HTML (matching workbench style)
     */
    private function formatImagingHistoryCard($req, $procedure)
    {
        $procedureItem = $procedure->items()->where('imaging_service_request_id', $req->id)->first();
        $isBundled = $procedureItem ? $procedureItem->is_bundled : false;

        $str = '<div class="card-modern mb-2" style="border-left: 4px solid #9c27b0;">';
        $str .= '<div class="card-body p-3">';

        // Header with service name and status
        $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
        $str .= "<h6 class='mb-0'><span class='badge bg-purple' style='background:#9c27b0'><i class='fa fa-x-ray'></i> " . (($req->service) ? $req->service->service_name : 'N/A') . '</span></h6>';

        // Status badges
        $str .= '<div>';
        if ($req->result) {
            $str .= "<span class='badge bg-info'>Result Available</span> ";
        } elseif ($req->billed_by) {
            $str .= "<span class='badge bg-success'>Billed</span> ";
        } else {
            $str .= "<span class='badge bg-secondary'>Pending</span> ";
        }

        // Bundled badge
        if ($isBundled) {
            $str .= "<span class='badge bg-info'>Bundled</span> ";
        }

        // HMO Coverage Badge
        if ($req->productOrServiceRequest && $req->productOrServiceRequest->coverage_mode) {
            $coverageClass = $req->productOrServiceRequest->coverage_mode === 'express' ? 'success' :
                           ($req->productOrServiceRequest->coverage_mode === 'primary' ? 'warning' : 'danger');
            $str .= "<span class='badge bg-{$coverageClass}'>HMO: " . strtoupper($req->productOrServiceRequest->coverage_mode) . '</span>';
        }
        $str .= '</div></div>';

        // Timeline section
        $str .= '<div class="mb-3"><small>';
        $str .= '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
            . ((isset($req->doctor_id) && $req->doctor_id != null) ? (userfullname($req->doctor_id) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->created_at)) . ')</span>') : "<span class='badge bg-secondary'>N/A</span>") . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
            . ((isset($req->billed_by) && $req->billed_by != null) ? (userfullname($req->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->billed_date)) . ')</span>') : ($isBundled ? "<span class='badge bg-info'>Bundled with Procedure</span>" : "<span class='badge bg-secondary'>Not billed</span>")) . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-clipboard-check text-info"></i> <b>Results by:</b> '
            . ((isset($req->result_by) && $req->result_by != null) ? (userfullname($req->result_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->result_date)) . ')</span>') : "<span class='badge bg-secondary'>Awaiting Results</span>") . '</div>';
        $str .= '</small></div>';

        // Results section
        if ($req->result) {
            $str .= '<div class="alert alert-light mb-2"><small><b>Result:</b><br>' . $req->result . '</small></div>';
        }

        // Request note
        if (isset($req->note) && $req->note != null) {
            $str .= '<div class="mb-2"><small><i class="mdi mdi-note-text"></i> <b>Note:</b> ' . $req->note . '</small></div>';
        }

        // Attachments
        if ($req->attachments) {
            $attachments = is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments;
            if (!empty($attachments)) {
                $str .= "<div class='mb-2'><small><b><i class='mdi mdi-paperclip'></i> Attachments:</b> ";
                foreach ($attachments as $attachment) {
                    $url = asset('storage/' . $attachment['path']);
                    $str .= "<a href='{$url}' target='_blank' class='badge bg-info text-white me-1'><i class='fa fa-file'></i> {$attachment['name']}</a> ";
                }
                $str .= "</small></div>";
            }
        }

        // Delivery guard check
        if (!$isBundled && $req->productOrServiceRequest) {
            $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($req->productOrServiceRequest);
            if (!$deliveryCheck['can_deliver']) {
                $str .= "<div class='alert alert-warning py-2 mb-2 mt-2'><small>";
                $str .= "<i class='fa fa-exclamation-triangle'></i> <b>" . $deliveryCheck['reason'] . "</b><br>";
                $str .= $deliveryCheck['hint'];
                $str .= "</small></div>";
            }
        }

        // Action buttons
        $str .= '<div class="btn-group mt-2" role="group">';
        $str .= "<button type='button' class='btn btn-info btn-sm' onclick='viewImagingResult({$req->id})'><i class='mdi mdi-eye'></i> View</button>";
        $str .= "<a href='" . route('imaging-requests.show', $req->id) . "' target='_blank' class='btn btn-primary btn-sm'><i class='mdi mdi-printer'></i> Print</a>";
        $str .= '</div>';

        $str .= '</div></div>';
        return $str;
    }

    /**
     * Format medication request as card-modern HTML (matching workbench style)
     */
    private function formatMedicationHistoryCard($req, $procedure)
    {
        $procedureItem = $procedure->items()->where('product_request_id', $req->id)->first();
        $isBundled = $procedureItem ? $procedureItem->is_bundled : false;

        $str = '<div class="card-modern mb-2" style="border-left: 4px solid #28a745;">';
        $str .= '<div class="card-body p-3">';

        $productName = optional($req->product)->product_name ?? 'Unknown';
        $productCode = optional($req->product)->product_code ?? '';
        $dose = $req->dose ?? 'N/A';
        $qty = $req->qty ?? 1;
        $price = optional(optional($req->product)->price)->current_sale_price ?? 0;

        // Header with product name and status
        $str .= '<div class="d-flex justify-content-between align-items-start mb-3">';
        $str .= "<h6 class='mb-0'><span class='badge bg-success'><i class='fa fa-pills'></i> {$productName}</span></h6>";

        // Status badges
        $str .= '<div>';
        $status = $req->status;
        if ($status == 0) {
            $str .= "<span class='badge bg-danger'>Dismissed</span> ";
        } elseif ($status == 1) {
            $str .= "<span class='badge bg-warning text-dark'>Unbilled</span> ";
        } elseif ($status == 2) {
            $payableAmount = optional($req->productOrServiceRequest)->payable_amount ?? 0;
            $claimsAmount = optional($req->productOrServiceRequest)->claims_amount ?? 0;
            $isPaid = optional($req->productOrServiceRequest)->payment_id !== null;
            $validationStatus = optional($req->productOrServiceRequest)->validation_status;
            $isValidated = in_array($validationStatus, ['validated', 'approved']);

            $pendingReasons = [];
            if ($payableAmount > 0 && !$isPaid) {
                $pendingReasons[] = 'Payment';
            }
            if ($claimsAmount > 0 && !$isValidated) {
                $pendingReasons[] = 'HMO Validation';
            }

            if (count($pendingReasons) > 0) {
                $str .= "<span class='badge bg-info'>Awaiting " . implode(' & ', $pendingReasons) . "</span> ";
            } else {
                $str .= "<span class='badge bg-success'>Ready to Dispense</span> ";
            }
        } elseif ($status == 3) {
            $str .= "<span class='badge bg-secondary'>Dispensed</span> ";
        }

        // Bundled badge
        if ($isBundled) {
            $str .= "<span class='badge bg-info'>Bundled</span> ";
        }
        $str .= '</div></div>';

        // Product info
        $str .= '<div class="mb-2">';
        $str .= "<small class='text-muted'>{$productCode}</small><br>";
        $str .= "<span><i class='mdi mdi-pill'></i> {$dose}</span> ";
        $str .= "<span class='ms-2'><i class='mdi mdi-numeric'></i> Qty: {$qty}</span> ";
        $str .= "<span class='ms-2'><i class='mdi mdi-cash'></i> ₦" . number_format($price * $qty, 2) . "</span>";
        $str .= '</div>';

        // Timeline section
        $str .= '<div class="mb-3"><small>';
        $str .= '<div class="mb-2"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
            . ((isset($req->doctor_id) && $req->doctor_id != null) ? (userfullname($req->doctor_id) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->created_at)) . ')</span>') : "<span class='badge bg-secondary'>N/A</span>") . '</div>';

        $str .= '<div class="mb-2"><i class="mdi mdi-cash-multiple text-success"></i> <b>Billed by:</b> '
            . ((isset($req->billed_by) && $req->billed_by != null) ? (userfullname($req->billed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->billed_date)) . ')</span>') : ($isBundled ? "<span class='badge bg-info'>Bundled with Procedure</span>" : "<span class='badge bg-secondary'>Not billed</span>")) . '</div>';

        if ($req->dispensed_by) {
            $str .= '<div class="mb-2"><i class="mdi mdi-truck-delivery text-info"></i> <b>Dispensed by:</b> '
                . userfullname($req->dispensed_by) . ' <span class="text-muted">(' . date('h:i a D M j, Y', strtotime($req->dispense_date)) . ')</span></div>';
        }
        $str .= '</small></div>';

        // Delivery guard check
        if (!$isBundled && $req->productOrServiceRequest) {
            $deliveryCheck = \App\Helpers\HmoHelper::canDeliverService($req->productOrServiceRequest);
            if (!$deliveryCheck['can_deliver']) {
                $str .= "<div class='alert alert-warning py-2 mb-2 mt-2'><small>";
                $str .= "<i class='fa fa-exclamation-triangle'></i> <b>" . $deliveryCheck['reason'] . "</b><br>";
                $str .= $deliveryCheck['hint'];
                $str .= "</small></div>";
            }
        }

        $str .= '</div></div>';
        return $str;
    }

    /**
     * Print procedure report
     * Spec Reference: Part 3.5.4
     */
    public function print(Procedure $procedure)
    {
        $procedure->load([
            'service.price',
            'procedureDefinition.procedureCategory',
            'requestedByUser',
            'billedByUser',
            'preNotesBy',
            'postNotesBy',
            'cancelledByUser',
            'patient.hmo',
            'encounter',
            'teamMembers.user',
            'notes.createdBy',
            'items.labServiceRequest.service',
            'items.imagingServiceRequest.service',
            'items.productRequest.product',
            'items.productOrServiceRequest',
            'productOrServiceRequest.payment',
        ]);

        return view('admin.patient-procedures.print', compact('procedure'));
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Create a billing entry (ProductOrServiceRequest) for non-bundled items
     */
    private function createBillingEntry(Procedure $procedure, $item, $basePrice, $type)
    {
        // Get patient's user_id from the patient record
        $patient = \App\Models\Patient::find($procedure->patient_id);

        $billingEntry = new ProductOrServiceRequest();
        $billingEntry->type = $type;

        if ($type === 'service') {
            $billingEntry->service_id = $item->id;
        } else {
            $billingEntry->product_id = $item->id;
        }

        $billingEntry->patient_id = $procedure->patient_id;
        $billingEntry->encounter_id = $procedure->encounter_id;
        $billingEntry->admission_request_id = $procedure->admission_request_id;
        $billingEntry->user_id = $patient->user_id; // Patient's user_id from patient record
        $billingEntry->staff_user_id = Auth::id(); // Staff who created
        $billingEntry->created_by = Auth::id();
        $billingEntry->order_date = now();

        // Check HMO coverage
        try {
            if ($type === 'service') {
                $coverage = HmoHelper::applyHmoTariff($procedure->patient_id, null, $item->id);
            } else {
                $coverage = HmoHelper::applyHmoTariff($procedure->patient_id, $item->id, null);
            }
        } catch (\Exception $e) {
            $coverage = null;
        }

        if ($coverage && $coverage['coverage_mode'] === 'hmo') {
            $billingEntry->amount = $coverage['payable_amount'];
            $billingEntry->claims_amount = $coverage['claims_amount'];
            $billingEntry->coverage_mode = 'hmo';
            $billingEntry->hmo_id = $coverage['hmo_id'] ?? null;
        } else {
            $billingEntry->amount = $basePrice;
            $billingEntry->claims_amount = 0;
            $billingEntry->coverage_mode = 'cash';
        }

        $billingEntry->save();

        return $billingEntry;
    }

    /**
     * Format item response for JSON
     */
    private function formatItemResponse(ProcedureItem $item)
    {
        $name = 'Unknown';
        $code = '';
        $price = 0;
        $type = $item->item_type;
        $deliveryStatus = 'pending';

        if ($item->labServiceRequest) {
            $name = optional($item->labServiceRequest->service)->service_name ?? 'Lab Test';
            $code = optional($item->labServiceRequest->service)->service_code ?? '';
            $price = optional($item->labServiceRequest->service->price)->sale_price ?? 0;
            $deliveryStatus = $item->labServiceRequest->status ?? 'pending';
        } elseif ($item->imagingServiceRequest) {
            $name = optional($item->imagingServiceRequest->service)->service_name ?? 'Imaging';
            $code = optional($item->imagingServiceRequest->service)->service_code ?? '';
            $price = optional($item->imagingServiceRequest->service->price)->sale_price ?? 0;
            $deliveryStatus = $item->imagingServiceRequest->status ?? 'pending';
        } elseif ($item->productRequest) {
            $name = optional($item->productRequest->product)->product_name ?? 'Medication';
            $code = optional($item->productRequest->product)->product_code ?? '';
            $price = (optional($item->productRequest->product->price)->sale_price ?? 0) * ($item->productRequest->qty ?? 1);
            $deliveryStatus = $item->productRequest->status ?? 'pending';
        }

        $billingStatus = null;
        if (!$item->is_bundled && $item->productOrServiceRequest) {
            $billingStatus = [
                'amount' => $item->productOrServiceRequest->amount,
                'claims_amount' => $item->productOrServiceRequest->claims_amount,
                'coverage_mode' => $item->productOrServiceRequest->coverage_mode,
                'validation_status' => $item->productOrServiceRequest->validation_status,
                'payment_status' => $item->productOrServiceRequest->payment_id ? 'paid' : 'unpaid',
            ];
        }

        return [
            'id' => $item->id,
            'type' => $type,
            'name' => $name,
            'code' => $code,
            'price' => $price,
            'is_bundled' => $item->is_bundled,
            'delivery_status' => $deliveryStatus,
            'billing_status' => $billingStatus,
        ];
    }
}

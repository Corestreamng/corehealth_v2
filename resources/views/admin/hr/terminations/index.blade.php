@extends('admin.layouts.app')

@section('title', 'Staff Terminations')

@section('style')
<style>
    /* Fix Select2 z-index issue in modals */
    .select2-container--open {
        z-index: 99999 !important;
    }
    .select2-dropdown {
        z-index: 99999 !important;
    }
    /* Fix Select2 search input focus in Bootstrap modals */
    .select2-search__field {
        z-index: 99999 !important;
    }
    .select2-container--open .select2-search--dropdown .select2-search__field {
        pointer-events: auto !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-account-off mr-2"></i>Staff Terminations
                    </h3>
                    <p class="text-muted mb-0">Manage staff terminations and exit process</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @can('termination.create')
                    <button type="button" class="btn btn-danger" id="addTerminationBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> New Termination
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Pending Exit</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-outline" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Resigned</h6>
                                    <h3 class="mb-0" id="resignedCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-arrow-right" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Dismissed</h6>
                                    <h3 class="mb-0" id="dismissedCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-remove" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Total This Year</h6>
                                    <h3 class="mb-0" id="totalCount">0</h3>
                                </div>
                                <i class="mdi mdi-history" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terminations Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Termination Records
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="terminationsTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Type</th>
                                    <th style="font-weight: 600; color: #495057;">Reason</th>
                                    <th style="font-weight: 600; color: #495057;">Last Working Day</th>
                                    <th style="font-weight: 600; color: #495057;">Exit Interview</th>
                                    <th style="font-weight: 600; color: #495057;">Clearance</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Termination Modal -->
<div class="modal fade" id="terminationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-off mr-2"></i>
                    Process Staff Termination
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="terminationForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Termination Type *</label>
                            <select class="form-control" name="termination_type" id="termination_type" required style="border-radius: 8px;">
                                <option value="voluntary">Voluntary (Resignation)</option>
                                <option value="involuntary">Involuntary (Termination/Dismissal)</option>
                                <option value="retirement">Retirement</option>
                                <option value="contract_end">Contract End</option>
                                <option value="death">Death</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Notice Date *</label>
                            <input type="date" class="form-control" name="notice_date" id="notice_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Last Working Day *</label>
                            <input type="date" class="form-control" name="last_working_day" id="last_working_day" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                            <textarea class="form-control" name="reason" id="reason" rows="3" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Detailed reason for termination"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Related Disciplinary Query (if applicable)</label>
                            <select class="form-control" name="disciplinary_query_id" id="disciplinary_query_id" style="border-radius: 8px;">
                                <option value="">None</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-cash mr-1"></i> Final Settlement
                    </h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="is_eligible_for_severance" name="is_eligible_for_severance">
                                <label class="custom-control-label" for="is_eligible_for_severance">Eligible for Severance</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="exit_interview_scheduled" name="exit_interview_scheduled">
                                <label class="custom-control-label" for="exit_interview_scheduled">Schedule Exit Interview</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="severanceAmountGroup" style="display: none;">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Severance Amount</label>
                            <input type="number" class="form-control" name="severance_amount" id="severance_amount"
                                   step="0.01" min="0" style="border-radius: 8px; padding: 0.75rem;">
                        </div>
                    </div>

                    <div class="alert alert-danger mt-3" style="border-radius: 8px;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Warning:</strong> This action will permanently terminate the staff member's employment and block their system access.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="submitTerminationBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Process Termination
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Termination Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 950px;" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-account-off mr-2" style="color: var(--primary-color);"></i>
                    Termination Details - <span id="viewTerminationNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
                <div class="row">
                    <!-- Left Column: Info & Timeline -->
                    <div class="col-lg-5">
                        <!-- Employee Summary Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-account mr-1"></i> Employee Information
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                                    <tbody id="terminationInfoTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Exit Process Timeline -->
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-timeline-text-outline mr-1"></i> Exit Process Timeline
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div id="exitTimeline" class="timeline-vertical">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Details -->
                    <div class="col-lg-7">
                        <!-- Termination Type Card -->
                        <div id="terminationTypeCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header" id="terminationTypeHeader" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-file-document-outline mr-1"></i> Termination Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="mr-2"><strong>Type:</strong></span>
                                    <span id="viewTypeBadge"></span>
                                </div>
                                <div id="viewReason" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                <small class="text-muted mt-2 d-block" id="viewTerminationMeta"></small>
                            </div>
                        </div>

                        <!-- Exit Interview Card -->
                        <div id="exitInterviewCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-info text-white" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-comment-text-outline mr-1"></i> Exit Interview Notes
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewExitNotes" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                            </div>
                        </div>

                        <!-- Severance Card -->
                        <div id="severanceCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-success text-white" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-cash mr-1"></i> Settlement Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1 text-muted">Severance Eligibility</p>
                                        <h5 id="viewSeveranceEligible" class="mb-0"></h5>
                                    </div>
                                    <div class="col-6">
                                        <p class="mb-1 text-muted">Settlement Amount</p>
                                        <h5 id="viewSeveranceAmount" class="mb-0 text-success"></h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Related Query Card -->
                        <div id="relatedQueryCard" class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-warning text-dark" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-link mr-1"></i> Related Disciplinary Query
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewRelatedQuery"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <div id="terminationActionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Complete Exit Modal -->
<div class="modal fade" id="completeExitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-check-decagram mr-2"></i>
                    Process Exit Checklist
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="completeExitForm">
                @csrf
                <input type="hidden" id="complete_termination_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <!-- Staff Info Banner -->
                    <div class="alert alert-light border mb-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary rounded-circle text-white d-flex align-items-center justify-content-center mr-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="mdi mdi-account"></i>
                            </div>
                            <div>
                                <h6 class="mb-0" id="exitStaffName">-</h6>
                                <small class="text-muted" id="exitStaffDetails">-</small>
                            </div>
                        </div>
                    </div>

                    <!-- Exit Steps -->
                    <h6 class="text-muted mb-3"><i class="mdi mdi-format-list-checks mr-1"></i> Exit Checklist</h6>

                    <!-- Step 1: Clearance -->
                    <div class="exit-step card mb-3" style="border-radius: 10px; border-left: 4px solid #6c757d;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="clearance_completed" name="clearance_completed">
                                        <label class="custom-control-label" for="clearance_completed" style="font-weight: 600; font-size: 1rem;">
                                            <i class="mdi mdi-clipboard-check-outline mr-1"></i> Clearance Completed
                                        </label>
                                    </div>
                                    <p class="text-muted mb-0 mt-1" style="font-size: 0.85rem; margin-left: 28px;">
                                        Ensure all company assets have been returned (ID cards, laptops, keys, etc.) and all pending tasks are handed over.
                                    </p>
                                </div>
                                <span class="badge badge-secondary step-badge" id="clearance_badge">Pending</span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Exit Interview -->
                    <div class="exit-step card mb-3" style="border-radius: 10px; border-left: 4px solid #6c757d;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="exit_interview_conducted" name="exit_interview_conducted">
                                        <label class="custom-control-label" for="exit_interview_conducted" style="font-weight: 600; font-size: 1rem;">
                                            <i class="mdi mdi-comment-question-outline mr-1"></i> Exit Interview Conducted
                                        </label>
                                    </div>
                                    <p class="text-muted mb-0 mt-1" style="font-size: 0.85rem; margin-left: 28px;">
                                        Conduct a final interview to gather feedback about their experience and suggestions for improvement.
                                    </p>
                                </div>
                                <span class="badge badge-secondary step-badge" id="interview_badge">Pending</span>
                            </div>
                            <div class="mt-3 ml-4 interview-notes-section" style="display: none;">
                                <label class="form-label" style="font-weight: 600; color: #495057; font-size: 0.9rem;">
                                    <i class="mdi mdi-note-text mr-1"></i> Interview Notes
                                </label>
                                <textarea class="form-control" name="exit_interview_notes" id="exit_interview_notes" rows="3"
                                          style="border-radius: 8px; padding: 0.75rem; font-size: 0.9rem;"
                                          placeholder="Document key feedback, reasons for leaving, suggestions, etc."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Final Payment -->
                    <div class="exit-step card mb-3" style="border-radius: 10px; border-left: 4px solid #6c757d;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="final_payment_processed" name="final_payment_processed">
                                        <label class="custom-control-label" for="final_payment_processed" style="font-weight: 600; font-size: 1rem;">
                                            <i class="mdi mdi-cash-multiple mr-1"></i> Final Payment Processed
                                        </label>
                                    </div>
                                    <p class="text-muted mb-0 mt-1" style="font-size: 0.85rem; margin-left: 28px;">
                                        Process final salary, leave encashment, gratuity, and any applicable settlement amounts.
                                    </p>
                                </div>
                                <span class="badge badge-secondary step-badge" id="payment_badge">Pending</span>
                            </div>
                            <div class="mt-3 ml-4 settlement-section" style="display: none;">
                                <label class="form-label" style="font-weight: 600; color: #495057; font-size: 0.9rem;">
                                    <i class="mdi mdi-currency-ngn mr-1"></i> Final Settlement Amount
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" name="final_settlement_amount" id="final_settlement_amount"
                                           step="0.01" min="0" style="border-radius: 0 8px 8px 0; padding: 0.75rem;" placeholder="0.00">
                                </div>
                                <small class="text-info mt-1 d-block">
                                    <i class="mdi mdi-information mr-1"></i>
                                    This will create a pending expense entry for finance approval.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Summary -->
                    <div class="alert alert-light border mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Exit Progress:</span>
                            <div>
                                <span id="progressCount">0</span>/3 steps completed
                                <div class="progress ml-2 d-inline-block" style="width: 100px; height: 8px; vertical-align: middle;">
                                    <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-success" id="completeExitBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Save Progress
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Timeline CSS -->
<style>
.timeline-vertical {
    position: relative;
    padding-left: 30px;
}
.timeline-vertical::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 15px;
    margin-bottom: 5px;
}
.timeline-item:last-child {
    padding-bottom: 0;
    margin-bottom: 0;
}
.timeline-item .timeline-dot {
    position: absolute;
    left: -24px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: white;
}
.timeline-item .timeline-dot.completed {
    background: #28a745;
}
.timeline-item .timeline-dot.pending {
    background: #e9ecef;
    border: 2px solid #adb5bd;
}
.timeline-item .timeline-dot.pending i {
    color: #adb5bd;
}
.timeline-item .timeline-dot.current {
    background: #007bff;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
}
.timeline-item .timeline-content {
    padding-left: 5px;
}
.timeline-item .timeline-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 2px;
}
.timeline-item .timeline-meta {
    font-size: 0.8rem;
    color: #6c757d;
}
.exit-step.completed {
    border-left-color: #28a745 !important;
}
.exit-step.completed .step-badge {
    background-color: #28a745 !important;
}
</style>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    // Fix Bootstrap modal enforceFocus to allow Select2 to work properly
    $.fn.modal.Constructor.prototype._enforceFocus = function() {};

    // Initialize Select2 on modal shown (with check for availability)
    $('#terminationModal').on('shown.bs.modal', function () {
        if (typeof $.fn.select2 !== 'undefined' && !$('#staff_id').hasClass('select2-hidden-accessible')) {
            $('#staff_id').select2({
                dropdownParent: $('#terminationModal'),
                placeholder: 'Select Staff',
                allowClear: true
            });
        }
    });

    // Initialize DataTable
    const table = $('#terminationsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.terminations.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'termination_type_badge', name: 'type' },
            { data: 'reason', name: 'reason_details',
              render: function(data, type, row) {
                  return row.reason_details ? (row.reason_details.length > 50 ? row.reason_details.substring(0, 50) + '...' : row.reason_details) : '-';
              }
            },
            { data: 'last_working_day', name: 'last_working_day' },
            { data: 'exit_interview', name: 'exit_interview_conducted', orderable: false },
            { data: 'clearance', name: 'clearance_completed', orderable: false },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: {
            emptyTable: "No termination records found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.terminations.index') }}", { stats: true }, function(data) {
            $('#pendingCount').text(data.pending || 0);
            $('#resignedCount').text(data.resigned || 0);
            $('#dismissedCount').text(data.dismissed || 0);
            $('#totalCount').text(data.total || 0);
        });
    }
    loadStats();

    // Status filter
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Severance checkbox
    $('#is_eligible_for_severance').change(function() {
        if ($(this).is(':checked')) {
            $('#severanceAmountGroup').show();
        } else {
            $('#severanceAmountGroup').hide();
        }
    });

    // Add termination button
    $('#addTerminationBtn').click(function() {
        $('#terminationForm')[0].reset();
        if ($('#staff_id').hasClass('select2-hidden-accessible')) {
            $('#staff_id').val('').trigger('change');
        }
        $('#severanceAmountGroup').hide();
        $('#terminationModal').modal('show');
    });

    // Load staff queries when staff selected
    $('#staff_id').change(function() {
        const staffId = $(this).val();
        if (staffId) {
            $.get("{{ url('/hr/disciplinary') }}", { staff_id: staffId }, function(data) {
                let options = '<option value="">None</option>';
                if (data.data) {
                    data.data.forEach(function(query) {
                        options += '<option value="' + query.id + '">' + query.query_number + ' - ' + query.subject + '</option>';
                    });
                }
                $('#disciplinary_query_id').html(options);
            });
        }
    });

    // Submit termination
    $('#terminationForm').submit(function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to process this termination? This action cannot be easily undone.')) {
            return;
        }

        $('#submitTerminationBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.terminations.store') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staff_id: $('#staff_id').val(),
                termination_type: $('#termination_type').val(),
                notice_date: $('#notice_date').val(),
                last_working_day: $('#last_working_day').val(),
                reason: $('#reason').val(),
                disciplinary_query_id: $('#disciplinary_query_id').val() || null,
                is_eligible_for_severance: $('#is_eligible_for_severance').is(':checked') ? 1 : 0,
                severance_amount: $('#severance_amount').val() || null,
                exit_interview_scheduled: $('#exit_interview_scheduled').is(':checked') ? 1 : 0
            },
            success: function(response) {
                $('#terminationModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Termination processed successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process termination');
            },
            complete: function() {
                $('#submitTerminationBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Process Termination');
            }
        });
    });

    // View termination - with timeline
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.terminations.index') }}/" + id, function(data) {
            // Set header
            $('#viewTerminationNumber').text(data.termination_number || 'TRM-' + data.id);

            // Populate info table
            let infoHtml = `
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-account text-muted mr-2"></i>Staff</td>
                    <td class="border-0 py-2 font-weight-bold">${data.staff_name}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-badge-account text-muted mr-2"></i>Employee ID</td>
                    <td class="border-0 py-2">${data.employee_id || '-'}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-calendar text-muted mr-2"></i>Notice Date</td>
                    <td class="border-0 py-2">${data.notice_date}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-calendar-end text-muted mr-2"></i>Last Working Day</td>
                    <td class="border-0 py-2">${data.last_working_day}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-flag text-muted mr-2"></i>Status</td>
                    <td class="border-0 py-2">${data.status_badge}</td></tr>
            `;
            $('#terminationInfoTable').html(infoHtml);

            // Type card header color
            const typeColors = {
                'voluntary': '#17a2b8',
                'involuntary': '#dc3545',
                'retirement': '#28a745',
                'death': '#343a40',
                'contract_end': '#6c757d'
            };
            $('#terminationTypeHeader').css('background-color', typeColors[data.termination_type] || '#f8f9fa').css('color', ['voluntary', 'involuntary', 'death'].includes(data.termination_type) ? 'white' : '#333');
            $('#viewTypeBadge').html(data.termination_type_badge);
            $('#viewReason').text(data.reason || 'No reason provided');
            $('#viewTerminationMeta').html(`<i class="mdi mdi-account mr-1"></i>Processed by ${data.processed_by} on ${data.created_at}`);

            // Build timeline
            let timelineHtml = '';

            // Step 1: Termination Initiated
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot completed"><i class="mdi mdi-check"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Termination Initiated</div>
                        <div class="timeline-meta">${data.created_at}</div>
                    </div>
                </div>
            `;

            // Step 2: Notice Period
            const noticeComplete = data.last_working_day && new Date(data.last_working_day.split(' ').slice(0, 3).join(' ')) <= new Date();
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot ${noticeComplete ? 'completed' : 'current'}">
                        <i class="mdi mdi-${noticeComplete ? 'check' : 'clock'}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Notice Period ${noticeComplete ? 'Completed' : 'In Progress'}</div>
                        <div class="timeline-meta">Until ${data.last_working_day}</div>
                    </div>
                </div>
            `;

            // Step 3: Clearance
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot ${data.clearance_completed ? 'completed' : 'pending'}">
                        <i class="mdi mdi-${data.clearance_completed ? 'check' : 'clipboard-check'}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Clearance ${data.clearance_completed ? 'Completed' : 'Pending'}</div>
                        <div class="timeline-meta">${data.clearance_completed ? 'Assets returned, handover complete' : 'Awaiting asset return & handover'}</div>
                    </div>
                </div>
            `;

            // Step 4: Exit Interview
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot ${data.exit_interview_conducted ? 'completed' : 'pending'}">
                        <i class="mdi mdi-${data.exit_interview_conducted ? 'check' : 'comment-question'}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Exit Interview ${data.exit_interview_conducted ? 'Conducted' : 'Pending'}</div>
                        <div class="timeline-meta">${data.exit_interview_conducted ? 'Feedback recorded' : 'Schedule and conduct exit interview'}</div>
                    </div>
                </div>
            `;

            // Step 5: Final Payment
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot ${data.final_payment_processed ? 'completed' : 'pending'}">
                        <i class="mdi mdi-${data.final_payment_processed ? 'check' : 'cash'}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Final Payment ${data.final_payment_processed ? 'Processed' : 'Pending'}</div>
                        <div class="timeline-meta">${data.final_payment_processed ? 'Settlement completed' : 'Process final settlement'}</div>
                    </div>
                </div>
            `;

            $('#exitTimeline').html(timelineHtml);

            // Exit Interview Card
            if (data.exit_interview_notes) {
                $('#exitInterviewCard').show();
                $('#viewExitNotes').text(data.exit_interview_notes);
            } else {
                $('#exitInterviewCard').hide();
            }

            // Severance Card
            if (data.is_eligible_for_severance || data.severance_amount > 0) {
                $('#severanceCard').show();
                $('#viewSeveranceEligible').html(data.is_eligible_for_severance
                    ? '<span class="text-success"><i class="mdi mdi-check-circle mr-1"></i>Eligible</span>'
                    : '<span class="text-muted">Not Eligible</span>');
                $('#viewSeveranceAmount').text(data.severance_amount ? '₦' + parseFloat(data.severance_amount).toLocaleString() : '-');
            } else {
                $('#severanceCard').hide();
            }

            // Related Query Card
            if (data.disciplinary_query) {
                $('#relatedQueryCard').show();
                $('#viewRelatedQuery').html(`
                    <a href="{{ url('/hr/disciplinary') }}/${data.disciplinary_query.id}" class="text-primary">
                        <i class="mdi mdi-open-in-new mr-1"></i>
                        ${data.disciplinary_query.query_number} - ${data.disciplinary_query.subject}
                    </a>
                `);
            } else {
                $('#relatedQueryCard').hide();
            }

            // Action buttons
            let actions = '';
            if (data.status === 'pending') {
                @can('termination.edit')
                actions += '<button class="btn btn-success complete-exit-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-clipboard-check mr-1"></i> Process Exit Checklist</button>';
                @endcan
            }
            $('#terminationActionButtons').html(actions);

            $('#viewModal').modal('show');
        });
    });

    // Store current termination data for exit modal
    let currentTerminationData = null;

    // Complete exit button (from table)
    $(document).on('click', '.complete-btn', function() {
        const id = $(this).data('id');
        loadExitModalData(id);
    });

    // Complete exit button (from view modal)
    $(document).on('click', '.complete-exit-btn', function() {
        const id = $(this).data('id');
        $('#viewModal').modal('hide');
        loadExitModalData(id);
    });

    // Load exit modal with current state
    function loadExitModalData(id) {
        $.get("{{ route('hr.terminations.index') }}/" + id, function(data) {
            currentTerminationData = data;
            $('#complete_termination_id').val(id);

            // Set staff info
            $('#exitStaffName').text(data.staff_name);
            $('#exitStaffDetails').text(`${data.employee_id || '-'} • ${data.termination_type_badge.replace(/<[^>]*>/g, '')} • Last Day: ${data.last_working_day}`);

            // Pre-check completed items and update UI
            $('#clearance_completed').prop('checked', data.clearance_completed);
            $('#exit_interview_conducted').prop('checked', data.exit_interview_conducted);
            $('#final_payment_processed').prop('checked', data.final_payment_processed);

            // Pre-fill notes if exists
            $('#exit_interview_notes').val(data.exit_interview_notes || '');
            $('#final_settlement_amount').val(data.severance_amount || '');

            // Update step cards visual state
            updateExitStepStyles();

            // Show/hide conditional sections
            if (data.exit_interview_conducted || $('#exit_interview_conducted').is(':checked')) {
                $('.interview-notes-section').show();
            }
            if (data.final_payment_processed || $('#final_payment_processed').is(':checked')) {
                $('.settlement-section').show();
            }

            $('#completeExitModal').modal('show');
        });
    }

    // Update step card styles based on checkbox state
    function updateExitStepStyles() {
        const clearance = $('#clearance_completed').is(':checked');
        const interview = $('#exit_interview_conducted').is(':checked');
        const payment = $('#final_payment_processed').is(':checked');

        // Update card borders and badges
        $('#clearance_completed').closest('.exit-step').toggleClass('completed', clearance);
        $('#clearance_badge').text(clearance ? 'Done' : 'Pending').toggleClass('badge-success', clearance).toggleClass('badge-secondary', !clearance);

        $('#exit_interview_conducted').closest('.exit-step').toggleClass('completed', interview);
        $('#interview_badge').text(interview ? 'Done' : 'Pending').toggleClass('badge-success', interview).toggleClass('badge-secondary', !interview);

        $('#final_payment_processed').closest('.exit-step').toggleClass('completed', payment);
        $('#payment_badge').text(payment ? 'Done' : 'Pending').toggleClass('badge-success', payment).toggleClass('badge-secondary', !payment);

        // Update progress
        const completed = [clearance, interview, payment].filter(Boolean).length;
        $('#progressCount').text(completed);
        $('#progressBar').css('width', (completed / 3 * 100) + '%');
    }

    // Checkbox change handlers
    $('#clearance_completed, #exit_interview_conducted, #final_payment_processed').change(function() {
        updateExitStepStyles();
    });

    // Show interview notes when checked
    $('#exit_interview_conducted').change(function() {
        if ($(this).is(':checked')) {
            $('.interview-notes-section').slideDown();
        } else {
            $('.interview-notes-section').slideUp();
        }
    });

    // Show settlement amount when payment checked
    $('#final_payment_processed').change(function() {
        if ($(this).is(':checked')) {
            $('.settlement-section').slideDown();
        } else {
            $('.settlement-section').slideUp();
        }
    });

    // Submit complete exit
    $('#completeExitForm').submit(function(e) {
        e.preventDefault();

        const id = $('#complete_termination_id').val();

        $('#completeExitBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('hr.terminations.index') }}/" + id + "/complete",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                clearance_completed: $('#clearance_completed').is(':checked') ? 1 : 0,
                exit_interview_conducted: $('#exit_interview_conducted').is(':checked') ? 1 : 0,
                final_payment_processed: $('#final_payment_processed').is(':checked') ? 1 : 0,
                exit_interview_notes: $('#exit_interview_notes').val(),
                final_settlement_amount: $('#final_settlement_amount').val()
            },
            success: function(response) {
                $('#completeExitModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Exit process updated successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to update exit process');
            },
            complete: function() {
                $('#completeExitBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Progress');
            }
        });
    });
});
</script>
@endsection

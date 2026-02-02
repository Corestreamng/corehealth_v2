@extends('admin.layouts.app')

@section('title', 'Disciplinary Queries')

@section('style')
<style>
    /* Fix Select2 z-index issue in modals */
    .select2-container--open {
        z-index: 9999 !important;
    }
    .select2-dropdown {
        z-index: 9999 !important;
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
                        <i class="mdi mdi-gavel mr-2"></i>Disciplinary Queries
                    </h3>
                    <p class="text-muted mb-0">Issue and manage disciplinary queries</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending Response</option>
                        <option value="responded">Responded</option>
                        <option value="reviewed">Under Review</option>
                        <option value="closed">Closed</option>
                    </select>
                    @can('disciplinary.create')
                    <button type="button" class="btn btn-primary" id="addQueryBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> Issue Query
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Pending Response</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-alert-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Under Review</h6>
                                    <h3 class="mb-0" id="reviewCount">0</h3>
                                </div>
                                <i class="mdi mdi-file-search-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Warnings Issued</h6>
                                    <h3 class="mb-0" id="warningsCount">0</h3>
                                </div>
                                <i class="mdi mdi-alert-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Dismissals</h6>
                                    <h3 class="mb-0" id="dismissalsCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-off-outline" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queries Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Disciplinary Queries
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="queriesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Query No</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Subject</th>
                                    <th style="font-weight: 600; color: #495057;">Severity</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Outcome</th>
                                    <th style="font-weight: 600; color: #495057;">Date</th>
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

<!-- Issue Query Modal -->
<div class="modal fade" id="queryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-file-alert mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">Issue Disciplinary Query</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="queryForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="query_id" id="query_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Severity Level *</label>
                            <select class="form-control" name="severity" id="severity" required style="border-radius: 8px;">
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="major">Major</option>
                                <option value="gross">Gross Misconduct</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Subject *</label>
                            <input type="text" class="form-control" name="subject" id="subject" required
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="Brief subject of the query">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Incident Date *</label>
                            <input type="date" class="form-control" name="incident_date" id="incident_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Description *</label>
                            <textarea class="form-control" name="description" id="description" rows="4" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Detailed description of the incident/misconduct"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Response Deadline *</label>
                            <input type="date" class="form-control" name="response_deadline" id="response_deadline" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Supporting Documents</label>
                            <input type="file" class="form-control" name="attachments[]" id="attachments" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="border-radius: 8px;">
                            <small class="text-muted">Evidence files (PDF, images, Word documents). Max 5MB each.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitQueryBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-send mr-1"></i> Issue Query
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Query Modal -->
<div class="modal fade" id="viewQueryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 900px;" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Query Details - <span id="viewQueryNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
                <div class="row">
                    <!-- Left Column: Query Info & Timeline -->
                    <div class="col-lg-5">
                        <!-- Query Summary Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-information-outline mr-1"></i> Query Information
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                                    <tbody id="queryInfoTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Workflow Timeline -->
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-timeline-text-outline mr-1"></i> Query Timeline
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div id="queryTimeline" class="timeline-vertical">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Details -->
                    <div class="col-lg-7">
                        <!-- Subject & Description -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-file-document-outline mr-1"></i> Query Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary mb-2" id="viewSubject"></h6>
                                <div id="viewDescription" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                            </div>
                        </div>

                        <!-- Staff Response (if any) -->
                        <div id="responseCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-info text-white" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-comment-text-outline mr-1"></i> Staff Response
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewResponse" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                <small class="text-muted mt-2 d-block" id="viewResponseMeta"></small>
                            </div>
                        </div>

                        <!-- Decision (if any) -->
                        <div id="decisionCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header" id="decisionCardHeader" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-scale-balance mr-1"></i> Decision & Outcome
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="mr-2"><strong>Outcome:</strong></span>
                                    <span id="viewOutcomeBadge"></span>
                                </div>
                                <div id="viewDecisionNotes" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                <small class="text-muted mt-2 d-block" id="viewDecisionMeta"></small>
                            </div>
                        </div>

                        <!-- Attachments (if any) -->
                        <div id="attachmentsCard" class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-paperclip mr-1"></i> Attachments
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div id="attachmentsList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <div id="queryActionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
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
.timeline-item .timeline-dot.warning {
    background: #ffc107;
}
.timeline-item .timeline-dot.danger {
    background: #dc3545;
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
</style>

<!-- Process Decision Modal -->
<div class="modal fade" id="decisionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-dark">
                    <i class="mdi mdi-scale-balance mr-2"></i>
                    Process Decision
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="decisionForm">
                @csrf
                <input type="hidden" id="decision_query_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Outcome *</label>
                                <select class="form-control" name="outcome" id="outcome" required style="border-radius: 8px;">
                                    <option value="">Select Outcome</option>
                                    <option value="no_action">No Action Required</option>
                                    <option value="verbal_warning">Verbal Warning</option>
                                    <option value="written_warning">Written Warning</option>
                                    <option value="final_warning">Final Warning</option>
                                    <option value="suspension">Suspension</option>
                                    <option value="demotion">Demotion</option>
                                    <option value="termination">Termination</option>
                                    <option value="exonerated">Exonerated / Case Dismissed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Suspension Options -->
                    <div id="suspensionOptionsGroup" style="display: none;">
                        <hr class="my-3">
                        <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                            <i class="mdi mdi-account-lock mr-1 text-danger"></i> Suspension Details
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Suspension Type *</label>
                                    <select class="form-control" name="suspension_type" id="suspension_type" style="border-radius: 8px;">
                                        <option value="unpaid">Without Pay (Unpaid)</option>
                                        <option value="paid">With Pay (Paid)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Duration (Days) *</label>
                                    <input type="number" class="form-control" name="suspension_days" id="suspension_days"
                                           min="1" max="365" style="border-radius: 8px; padding: 0.75rem;" placeholder="Number of days">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Start Date *</label>
                                    <input type="date" class="form-control" name="suspension_start_date" id="suspension_start_date"
                                           style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">End Date</label>
                                    <input type="date" class="form-control" name="suspension_end_date" id="suspension_end_date"
                                           style="border-radius: 8px; padding: 0.75rem;" readonly>
                                    <small class="text-muted">Auto-calculated from start date + days</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Login Block Message</label>
                                    <input type="text" class="form-control" name="suspension_message" id="suspension_message"
                                           style="border-radius: 8px; padding: 0.75rem;"
                                           placeholder="Message shown when staff tries to login"
                                           value="Your account has been suspended due to disciplinary action. Please contact HR.">
                                    <small class="text-muted">This message will be displayed when the staff attempts to login</small>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-danger" style="border-radius: 8px;">
                            <i class="mdi mdi-alert mr-1"></i>
                            <strong>Warning:</strong> This will immediately block the staff member from logging into the system.
                        </div>
                    </div>

                    <!-- Termination Options -->
                    <div id="terminationOptionsGroup" style="display: none;">
                        <hr class="my-3">
                        <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                            <i class="mdi mdi-account-off mr-1 text-dark"></i> Termination Details
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Termination Type *</label>
                                    <select class="form-control" name="termination_type" id="termination_type" style="border-radius: 8px;">
                                        <option value="involuntary">Involuntary (Dismissal)</option>
                                        <option value="voluntary">Voluntary (Resignation)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Reason Category *</label>
                                    <select class="form-control" name="termination_reason_category" id="termination_reason_category" style="border-radius: 8px;">
                                        <option value="misconduct">Misconduct</option>
                                        <option value="poor_performance">Poor Performance</option>
                                        <option value="resignation">Resignation</option>
                                        <option value="redundancy">Redundancy</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Notice Date *</label>
                                    <input type="date" class="form-control" name="termination_notice_date" id="termination_notice_date"
                                           style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; color: #495057;">Last Working Day *</label>
                                    <input type="date" class="form-control" name="termination_last_working_day" id="termination_last_working_day"
                                           style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="termination_schedule_exit_interview" name="termination_schedule_exit_interview">
                                    <label class="custom-control-label" for="termination_schedule_exit_interview">Schedule Exit Interview</label>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-dark" style="border-radius: 8px;">
                            <i class="mdi mdi-alert mr-1"></i>
                            <strong>Warning:</strong> This action will permanently terminate the staff member's employment and immediately block their system access.
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Decision Notes / Justification *</label>
                        <textarea class="form-control" name="decision_notes" id="decision_notes" rows="3" required
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Provide detailed justification for the decision"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="submitDecisionBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Submit Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Initialize Select2 only when modal is shown (to avoid "not a function" error)
    let select2Initialized = false;
    $('#queryModal').on('shown.bs.modal', function() {
        if (!select2Initialized && typeof $.fn.select2 !== 'undefined') {
            $('#staff_id').select2({
                dropdownParent: $('#queryModal'),
                placeholder: 'Select Staff',
                allowClear: true,
                width: '100%'
            });
            select2Initialized = true;
        }
    });

    // Initialize DataTable
    const table = $('#queriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.disciplinary.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'query_number', name: 'query_number' },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'subject', name: 'subject' },
            { data: 'severity_badge', name: 'severity' },
            { data: 'status_badge', name: 'status' },
            { data: 'outcome_badge', name: 'outcome' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[7, 'desc']],
        language: {
            emptyTable: "No disciplinary queries found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.disciplinary.index') }}", { stats: true }, function(data) {
            $('#pendingCount').text(data.pending || 0);
            $('#reviewCount').text(data.reviewed || 0);
            $('#warningsCount').text(data.warnings || 0);
            $('#dismissalsCount').text(data.dismissals || 0);
        });
    }
    loadStats();

    // Status filter change
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Add query button
    $('#addQueryBtn').click(function() {
        $('#queryForm')[0].reset();
        $('#query_id').val('');
        $('#staff_id').val('').trigger('change');
        $('#modalTitleText').text('Issue Disciplinary Query');
        $('#queryModal').modal('show');
    });

    // Submit query (create or update)
    $('#queryForm').submit(function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const id = $('#query_id').val();
        let url = "{{ route('hr.disciplinary.store') }}";
        let method = 'POST';

        if (id) {
            // Update existing - use PUT method via _method field
            url = "{{ route('hr.disciplinary.index') }}/" + id;
            formData.append('_method', 'PUT');
        }

        $('#submitQueryBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#queryModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || (id ? 'Query updated successfully' : 'Query issued successfully'));
            },
            error: function(xhr) {
                let message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
                toastr.error(message);
            },
            complete: function() {
                const btnText = $('#query_id').val() ? '<i class="mdi mdi-content-save mr-1"></i> Save Changes' : '<i class="mdi mdi-send mr-1"></i> Issue Query';
                $('#submitQueryBtn').prop('disabled', false).html(btnText);
            }
        });
    });

    // View query
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        // Show loading state
        $('#queryInfoTable').html('<tr><td colspan="2" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
        $('#queryTimeline').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
        $('#viewSubject').text('');
        $('#viewDescription').text('');
        $('#responseCard, #decisionCard, #attachmentsCard').hide();
        $('#queryActionButtons').html('');
        $('#viewQueryModal').modal('show');

        $.ajax({
            url: "{{ route('hr.disciplinary.index') }}/" + id,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const data = response.query;
                renderQueryDetails(data);
                renderQueryTimeline(data);
                renderQueryActions(data);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to load query details';
                $('#queryInfoTable').html(`<tr><td colspan="2" class="text-danger">${msg}</td></tr>`);
                toastr.error(msg);
            }
        });
    });

    function renderQueryDetails(data) {
        const staff = data.staff?.user;
        const staffName = staff ? `${staff.firstname || ''} ${staff.surname || ''}`.trim() : 'N/A';
        const employeeId = data.staff?.employee_id || 'N/A';

        $('#viewQueryNumber').text(data.query_number || 'N/A');

        // Severity badge
        const severityColors = { minor: 'info', moderate: 'warning', major: 'danger', gross: 'dark' };
        const severityBadge = `<span class="badge badge-${severityColors[data.severity] || 'secondary'}">${(data.severity || '').replace('_', ' ').toUpperCase()}</span>`;

        // Status badge
        const statusColors = { issued: 'warning', response_received: 'info', under_review: 'primary', closed: 'secondary' };
        const statusBadge = `<span class="badge badge-${statusColors[data.status] || 'secondary'}">${(data.status || '').replace(/_/g, ' ').toUpperCase()}</span>`;

        // Query info table
        let infoHtml = `
            <tr><th style="width: 40%;">Staff</th><td><strong>${staffName}</strong><br><small class="text-muted">${employeeId}</small></td></tr>
            <tr><th>Severity</th><td>${severityBadge}</td></tr>
            <tr><th>Status</th><td>${statusBadge}</td></tr>
            <tr><th>Incident Date</th><td>${data.incident_date ? formatDate(data.incident_date) : '-'}</td></tr>
            <tr><th>Response Deadline</th><td>${data.response_deadline ? formatDate(data.response_deadline) : '-'}</td></tr>
            <tr><th>Issued By</th><td>${data.issued_by?.name || '-'}</td></tr>
        `;
        $('#queryInfoTable').html(infoHtml);

        // Subject and description
        $('#viewSubject').text(data.subject || '-');
        $('#viewDescription').text(data.description || '-');

        // Staff response
        if (data.staff_response) {
            $('#responseCard').show();
            $('#viewResponse').text(data.staff_response);
            $('#viewResponseMeta').text(`Responded on: ${data.responded_at ? formatDate(data.responded_at) : '-'}`);
        }

        // Decision/Outcome
        if (data.outcome) {
            $('#decisionCard').show();
            const outcomeColors = {
                no_action: 'bg-secondary text-white',
                verbal_warning: 'bg-info text-white',
                written_warning: 'bg-warning text-dark',
                final_warning: 'bg-orange text-white',
                suspension: 'bg-danger text-white',
                demotion: 'bg-danger text-white',
                termination: 'bg-dark text-white',
                dismissed: 'bg-success text-white',
                exonerated: 'bg-success text-white'
            };
            const headerClass = outcomeColors[data.outcome] || 'bg-secondary text-white';
            $('#decisionCardHeader').removeClass().addClass('card-header ' + headerClass).css('border-radius', '10px 10px 0 0');

            const outcomeBadge = `<span class="badge badge-lg ${headerClass.replace('bg-', 'badge-')}">${(data.outcome || '').replace(/_/g, ' ').toUpperCase()}</span>`;
            $('#viewOutcomeBadge').html(outcomeBadge);
                $('#viewDecisionNotes').text(data.hr_decision || 'No notes provided');
            $('#viewDecisionMeta').text(`Decided by: ${data.decided_by?.name || '-'} on ${data.decided_at ? formatDate(data.decided_at) : '-'}`);
        }

        // Attachments
        if (data.attachments && data.attachments.length > 0) {
            $('#attachmentsCard').show();
            let attachHtml = '';
            data.attachments.forEach(att => {
                attachHtml += `
                    <div class="d-flex align-items-center py-2 border-bottom">
                        <i class="mdi mdi-file-document text-primary mr-2"></i>
                        <span class="flex-grow-1">${att.original_name || att.filename || 'Document'}</span>
                        <a href="/storage/${att.file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-download"></i>
                        </a>
                    </div>
                `;
            });
            $('#attachmentsList').html(attachHtml);
        }
    }

    function renderQueryTimeline(data) {
        let timeline = [];

        // Query Issued
        timeline.push({
            title: 'Query Issued',
            completed: true,
            user: data.issued_by?.name || 'System',
            date: data.created_at ? formatDate(data.created_at) : '-',
            icon: 'mdi-file-alert'
        });

        // Response Received
        if (data.response_received_at) {
            timeline.push({
                title: 'Response Received',
                completed: true,
                user: data.staff?.user ? `${data.staff.user.firstname || ''} ${data.staff.user.surname || ''}`.trim() : 'Staff',
                date: formatDate(data.response_received_at),
                icon: 'mdi-comment-text'
            });
        } else if (data.status === 'issued') {
            timeline.push({
                title: 'Awaiting Response',
                completed: false,
                current: true,
                icon: 'mdi-clock-outline'
            });
        }

        // Under Review
        if (data.status === 'response_received' || data.status === 'under_review' || data.status === 'closed') {
            timeline.push({
                title: 'Under Review',
                completed: data.status === 'closed',
                current: data.status === 'response_received' || data.status === 'under_review',
                icon: 'mdi-magnify'
            });
        }

        // Decision Made
        if (data.outcome) {
            const outcomeLabel = (data.outcome || '').replace(/_/g, ' ');
            timeline.push({
                title: `Decision: ${outcomeLabel.charAt(0).toUpperCase() + outcomeLabel.slice(1)}`,
                completed: true,
                user: data.decided_by?.name || '-',
                date: data.decided_at ? formatDate(data.decided_at) : '-',
                icon: 'mdi-scale-balance',
                dotClass: data.outcome === 'termination' || data.outcome === 'suspension' ? 'danger' :
                          data.outcome === 'exonerated' || data.outcome === 'dismissed' || data.outcome === 'no_action' ? 'completed' : 'warning'
            });
        }

        // Render timeline
        let html = '';
        timeline.forEach(event => {
            let dotClass = event.dotClass || (event.completed ? 'completed' : (event.current ? 'current' : 'pending'));
            let icon = event.icon || (event.completed ? 'mdi-check' : 'mdi-circle-outline');

            html += `
                <div class="timeline-item">
                    <div class="timeline-dot ${dotClass}">
                        <i class="mdi ${icon}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">${event.title}</div>
            `;

            if (event.completed && event.user) {
                html += `<div class="timeline-meta">By ${event.user} on ${event.date}</div>`;
            } else if (!event.completed) {
                html += `<div class="timeline-meta text-muted">${event.current ? 'In Progress' : 'Pending'}</div>`;
            }

            html += `
                    </div>
                </div>
            `;
        });

        $('#queryTimeline').html(html);
    }

    function renderQueryActions(data) {
        let actions = '';

        // Edit button - only for issued queries
        if (data.status === 'issued') {
            @can('disciplinary.edit')
            actions += `<button class="btn btn-primary mr-2 edit-btn" data-id="${data.id}" style="border-radius: 8px;">
                <i class="mdi mdi-pencil mr-1"></i> Edit
            </button>`;
            @endcan

            @can('disciplinary.delete')
            actions += `<button class="btn btn-danger mr-2 delete-btn" data-id="${data.id}" style="border-radius: 8px;">
                <i class="mdi mdi-delete mr-1"></i> Delete
            </button>`;
            @endcan
        }

        // Process Decision - only for responded/under_review status
        if (data.status === 'response_received' || data.status === 'under_review') {
            @can('disciplinary.decide')
            actions += `<button class="btn btn-warning mr-2 decision-btn" data-id="${data.id}" style="border-radius: 8px;">
                <i class="mdi mdi-scale-balance mr-1"></i> Process Decision
            </button>`;
            @endcan
        }

        $('#queryActionButtons').html(actions);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Edit query
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        $('#viewQueryModal').modal('hide');

        $.ajax({
            url: "{{ route('hr.disciplinary.index') }}/" + id + "/edit",
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const data = response.query;
                $('#query_id').val(data.id);
                $('#staff_id').val(data.staff_id).trigger('change');
                $('#severity').val(data.severity);
                $('#subject').val(data.subject);
                $('#incident_date').val(data.incident_date);
                $('#description').val(data.description);
                $('#response_deadline').val(data.response_deadline);

                // Disable staff selection for edit
                $('#staff_id').prop('disabled', true);

                $('#modalTitleText').text('Edit Disciplinary Query');
                $('#submitQueryBtn').html('<i class="mdi mdi-content-save mr-1"></i> Save Changes');
                $('#queryModal').modal('show');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to load query for editing');
            }
        });
    });

    // Reset form when modal is closed
    $('#queryModal').on('hidden.bs.modal', function() {
        $('#staff_id').prop('disabled', false);
        $('#modalTitleText').text('Issue Disciplinary Query');
        $('#submitQueryBtn').html('<i class="mdi mdi-send mr-1"></i> Issue Query');
    });

    // Delete query
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');

        if (!confirm('Are you sure you want to delete this disciplinary query? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: "{{ route('hr.disciplinary.index') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#viewQueryModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Query deleted successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to delete query');
            }
        });
    });

    // Decision button click
    $(document).on('click', '.decision-btn', function() {
        const id = $(this).data('id');
        $('#decision_query_id').val(id);
        $('#outcome').val('');
        $('#decision_notes').val('');
        // Reset suspension fields
        $('#suspension_days').val('');
        $('#suspension_type').val('unpaid');
        $('#suspension_start_date').val('{{ date('Y-m-d', strtotime('+1 day')) }}');
        $('#suspension_end_date').val('');
        $('#suspension_message').val('Your account has been suspended due to disciplinary action. Please contact HR.');
        // Reset termination fields
        $('#termination_type').val('involuntary');
        $('#termination_reason_category').val('misconduct');
        $('#termination_notice_date').val('{{ date('Y-m-d') }}');
        $('#termination_last_working_day').val('{{ date('Y-m-d') }}');
        $('#termination_schedule_exit_interview').prop('checked', false);
        // Hide all option groups
        $('#suspensionOptionsGroup').hide();
        $('#terminationOptionsGroup').hide();
        $('#viewQueryModal').modal('hide');
        $('#decisionModal').modal('show');
    });

    // Outcome change - show/hide relevant options
    $('#outcome').change(function() {
        const outcome = $(this).val();
        // Hide all first
        $('#suspensionOptionsGroup').hide();
        $('#terminationOptionsGroup').hide();

        if (outcome === 'suspension') {
            $('#suspensionOptionsGroup').show();
            calculateSuspensionEndDate();
        } else if (outcome === 'termination') {
            $('#terminationOptionsGroup').show();
        }
    });

    // Calculate suspension end date when start date or days change
    $('#suspension_start_date, #suspension_days').change(function() {
        calculateSuspensionEndDate();
    });

    function calculateSuspensionEndDate() {
        const startDate = $('#suspension_start_date').val();
        const days = parseInt($('#suspension_days').val()) || 0;
        if (startDate && days > 0) {
            const start = new Date(startDate);
            start.setDate(start.getDate() + days);
            const endDate = start.toISOString().split('T')[0];
            $('#suspension_end_date').val(endDate);
        } else {
            $('#suspension_end_date').val('');
        }
    }

    // Submit decision
    $('#decisionForm').submit(function(e) {
        e.preventDefault();

        const id = $('#decision_query_id').val();
        const outcome = $('#outcome').val();

        // Validate suspension fields
        if (outcome === 'suspension') {
            if (!$('#suspension_days').val() || parseInt($('#suspension_days').val()) < 1) {
                toastr.error('Please enter the number of suspension days');
                return;
            }
            if (!$('#suspension_start_date').val()) {
                toastr.error('Please select a suspension start date');
                return;
            }
        }

        // Validate termination fields
        if (outcome === 'termination') {
            if (!$('#termination_last_working_day').val()) {
                toastr.error('Please select the last working day');
                return;
            }
        }

        // Confirm for suspension/termination
        if (outcome === 'suspension' || outcome === 'termination') {
            const actionText = outcome === 'suspension' ? 'suspend' : 'terminate';
            if (!confirm(`Are you sure you want to ${actionText} this staff member? This action will take immediate effect.`)) {
                return;
            }
        }

        $('#submitDecisionBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        // Build data object
        const data = {
            _token: '{{ csrf_token() }}',
            outcome: outcome,
            hr_decision: $('#decision_notes').val()
        };

        // Add suspension data if applicable
        if (outcome === 'suspension') {
            data.suspension_type = $('#suspension_type').val();
            data.suspension_days = $('#suspension_days').val();
            data.suspension_start_date = $('#suspension_start_date').val();
            data.suspension_end_date = $('#suspension_end_date').val();
            data.suspension_message = $('#suspension_message').val();
        }

        // Add termination data if applicable
        if (outcome === 'termination') {
            data.termination_type = $('#termination_type').val();
            data.termination_reason_category = $('#termination_reason_category').val();
            data.termination_notice_date = $('#termination_notice_date').val();
            data.termination_last_working_day = $('#termination_last_working_day').val();
            data.termination_schedule_exit_interview = $('#termination_schedule_exit_interview').is(':checked') ? 1 : 0;
        }

        $.ajax({
            url: "{{ route('hr.disciplinary.decide', ':id') }}".replace(':id', id),
            method: 'POST',
            data: data,
            success: function(response) {
                $('#decisionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Decision processed successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process decision');
            },
            complete: function() {
                $('#submitDecisionBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Submit Decision');
            }
        });
    });
});
</script>
@endsection

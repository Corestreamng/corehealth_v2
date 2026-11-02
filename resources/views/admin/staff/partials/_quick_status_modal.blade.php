{{-- Quick Status Transition Modal --}}
<div class="modal fade" id="quickStatusModal" tabindex="-1" role="dialog" aria-labelledby="quickStatusModalLabel" aria-hidden="true" data-backdrop="static" data-bs-backdrop="static" data-keyboard="false" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h5 class="modal-title font-weight-bold" id="quickStatusModalLabel" style="font-size:1rem;">
                    <i class="mdi mdi-account-settings mr-1"></i> Staff Status & Deactivation
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="quickStatusForm">
                @csrf
                <input type="hidden" id="qs-user-id" name="user_id">
                <div class="modal-body py-3 px-4">
                    {{-- Staff Summary Box --}}
                    <div class="mb-3 d-flex align-items-center justify-content-between p-2 rounded" style="background:#f1f5f9;border:1px solid #e2e8f0;">
                        <div>
                            <span class="text-muted d-block" style="font-size:0.75rem;">STAFF PRACTITIONER</span>
                            <strong id="qs-staff-name" class="text-dark" style="font-size:0.95rem;">-</strong>
                        </div>
                        <div class="text-right">
                            <span class="text-muted d-block" style="font-size:0.75rem;">CURRENT STATUS</span>
                            <span id="qs-current-status-badge" class="badge badge-secondary">-</span>
                        </div>
                    </div>

                    {{-- Locum Alert (if applicable) --}}
                    <div id="qs-locum-alert" class="alert alert-warning py-2 px-3 mb-3" style="display:none;font-size:0.8rem;">
                        <i class="mdi mdi-alert-circle mr-1"></i>
                        <strong>Locum Practitioner:</strong> Suspending or deactivating this staff will automatically revoke all active shift passes and immediately block PIN logins.
                    </div>

                    <div class="form-group mb-3">
                        <label for="qs-employment-status" class="font-weight-bold text-dark" style="font-size:0.85rem;">
                            New Employment Status <span class="text-danger">*</span>
                        </label>
                        <select id="qs-employment-status" name="employment_status" class="form-control form-control-sm form-control-modern" required>
                            <option value="active">Active (Full Operational Access)</option>
                            <option value="probation">Probation (Active on Probation)</option>
                            <option value="on_leave">On Leave (Portal Only - Excluded from Clinical Queues)</option>
                            <option value="suspended">Suspended (Disciplinary - Login Blocked & Session Revoked)</option>
                            <option value="resigned">Resigned (Voluntary Exit - Login Revoked)</option>
                            <option value="terminated">Terminated (Involuntary Dismissal - Login Revoked)</option>
                            <option value="retired">Retired (Retirement Exit - Login Revoked)</option>
                        </select>
                        <small id="qs-status-help" class="form-text text-muted" style="font-size:0.75rem;min-height:18px;"></small>
                    </div>

                    <div id="qs-suspension-dates" class="form-row mb-3" style="display:none;">
                        <div class="col-6">
                            <label for="qs-effective-date" class="font-weight-bold" style="font-size:0.85rem;">Effective Date</label>
                            <input type="date" id="qs-effective-date" name="effective_date" class="form-control form-control-sm form-control-modern" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-6" id="qs-end-date-col">
                            <label for="qs-end-date" class="font-weight-bold" style="font-size:0.85rem;">Suspension End Date</label>
                            <input type="date" id="qs-end-date" name="suspension_end_date" class="form-control form-control-sm form-control-modern">
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label for="qs-reason" class="font-weight-bold text-dark" style="font-size:0.85rem;">Reason / Operational Notes</label>
                        <textarea id="qs-reason" name="reason" rows="3" class="form-control form-control-sm form-control-modern" placeholder="Provide reason or operational notes for this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="qs-submit-btn" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-check"></i> Apply Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

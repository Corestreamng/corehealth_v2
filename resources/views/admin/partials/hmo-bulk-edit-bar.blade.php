{{-- Bulk Update Bar (Unified Partial) --}}
<div class="bulk-bar-refined mt-3 p-4 bg-white border rounded-xl shadow-lg d-none" id="bulkBar" data-mode="percent">
    <div class="mb-4 pb-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="font-weight-bold mb-1 text-dark">{{ $title ?? 'Network Bulk Edit Tool' }}</h5>
            <p class="text-muted mb-0 text-sm">{{ $subtitle ?? 'Perform large-scale pricing updates across your HMO network.' }} <span class="text-primary font-weight-bold">Apply locally first to review.</span></p>
        </div>
        <div class="text-right">
            <div class="badge badge-soft-primary p-2 px-3">
                <i class="mdi mdi-bullseye-arrow mr-1"></i> Current Scope: <span id="currentScopeCount" class="font-weight-bold">0</span> {{ $entityName ?? 'Entities' }}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 border-right">
            <div class="step-badge mb-2">Step 1</div>
            <h6 class="font-weight-bold mb-2">Calculation Method</h6>
            <p class="small text-muted mb-3">Define how the system should compute new claimable amounts.</p>
            
            <div class="custom-control custom-radio mb-3 p-2 border rounded hover-bg-light transition-all">
                <input type="radio" id="modePct" name="bulkMode" class="custom-control-input mode-radio" value="percent" checked>
                <label class="custom-control-label font-weight-bold cursor-pointer ml-1" for="modePct">Percentage Based</label>
                <div class="smallest text-muted mt-1 ml-4">Sets claims as a % of reference price.</div>
            </div>
            <div class="custom-control custom-radio p-2 border rounded hover-bg-light transition-all">
                <input type="radio" id="modeFixed" name="bulkMode" class="custom-control-input mode-radio" value="fixed">
                <label class="custom-control-label font-weight-bold cursor-pointer ml-1" for="modeFixed">Fixed Naira Values</label>
                <div class="smallest text-muted mt-1 ml-4">Directly input specific amounts.</div>
            </div>
        </div>
        
        <div class="col-md-6 border-right px-4">
            <div class="step-badge mb-2">Step 2</div>
            <h6 class="font-weight-bold mb-2">Input Pricing Values</h6>
            
            <div class="row align-items-end">
                <div class="col-md-4 pct-group">
                    <label class="font-weight-bold text-dark mb-1 text-sm">HMO Claims (%)</label>
                    <div class="input-group">
                        <input type="number" id="bulkClaimsPct" class="form-control form-control-lg font-weight-bold" placeholder="100">
                        <div class="input-group-append"><span class="input-group-text font-weight-bold">%</span></div>
                    </div>
                </div>
                <div class="col-md-4 pct-group">
                    <label class="font-weight-bold text-dark mb-1 text-sm">Patient Pays (%)</label>
                    <div class="input-group">
                        <input type="number" id="bulkPayablePct" class="form-control form-control-lg font-weight-bold" placeholder="0">
                        <div class="input-group-append"><span class="input-group-text font-weight-bold">%</span></div>
                    </div>
                </div>
                <div class="col-md-4 pct-group">
                    <label class="font-weight-bold text-dark mb-1 text-sm">Reference Price (&#8358;)</label>
                    <input type="number" step="0.01" id="bulkRefPrice" class="form-control form-control-lg font-weight-bold text-primary" value="{{ $defaultRefPrice ?? '' }}" placeholder="{{ $refPlaceholder ?? 'Use Item Base' }}">
                    <div class="smallest text-muted mt-1">{{ $refHint ?? 'Leave blank to use base price.' }}</div>
                </div>

                {{-- Preview Area (only for Single Item view usually, but we can hide/show via JS or CSS) --}}
                @if(isset($showPreview) && $showPreview)
                <div class="col-md-12 mt-3 pct-group">
                    <div class="bg-light p-2 rounded border d-flex justify-content-between align-items-center">
                        <span class="small font-weight-bold text-muted">Resulting Calculation:</span>
                        <div>
                            <span class="badge badge-soft-info mr-2">HMO: <span id="previewBulkClaims">&#8358;0.00</span></span>
                            <span class="badge badge-soft-success">Patient: <span id="previewBulkPayable">&#8358;0.00</span></span>
                        </div>
                    </div>
                </div>
                @endif

                <div class="col-md-6 fixed-group">
                    <label class="font-weight-bold text-dark mb-1 text-sm">Patient Pays (&#8358;)</label>
                    <input type="number" step="0.01" id="bulkPayableAmount" class="form-control form-control-lg font-weight-bold" placeholder="0.00">
                </div>
                <div class="col-md-6 fixed-group">
                    <label class="font-weight-bold text-dark mb-1 text-sm">HMO Claims (&#8358;)</label>
                    <input type="number" step="0.01" id="bulkClaimsAmount" class="form-control form-control-lg font-weight-bold" placeholder="0.00">
                </div>

                <div class="col-md-12 mt-4">
                    <div class="d-flex align-items-center mb-1">
                        <label class="font-weight-bold text-dark mb-0 text-sm">Coverage Status</label>
                        <i class="mdi mdi-information-outline text-muted ml-2 cursor-help" data-bs-toggle="tooltip" data-bs-html="true" title="<b>Express:</b> Auto-access, no approval needed.<br><b>Primary:</b> HMO Executive must approve first.<br><b>Secondary:</b> Requires Auth Code from provider then approval."></i>
                    </div>
                    <select id="bulkCoverage" class="form-control form-control-lg">
                        <option value="">-- Keep Current Status --</option>
                        <option value="express">Express (No approval needed - e.g. GP Consult)</option>
                        <option value="primary">Primary (HMO Executive must approve first)</option>
                        <option value="secondary">Secondary (Auth code required from provider)</option>
                    </select>
                    <div class="mt-2 p-2 bg-light rounded smallest text-muted border-left border-primary" style="border-left-width: 3px !important;">
                        <div class="mb-1"><strong>Express:</strong> Automatic access (e.g. Primary Care).</div>
                        <div class="mb-1"><strong>Primary:</strong> Requires HMO Executive approval.</div>
                        <div><strong>Secondary:</strong> Auth code required before approval.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 pl-4">
            <div class="step-badge mb-2">Step 3</div>
            <h6 class="font-weight-bold mb-2">Execution Scope</h6>
            <p class="small text-muted mb-3">Which entities should receive this update?</p>
            
            <div class="form-group mb-4">
                <select id="bulkScope" class="form-control form-control-lg font-weight-bold border-primary">
                    @foreach($scopeOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div id="scopeInstruction" class="smallest text-primary font-italic mt-2">
                    <i class="mdi mdi-information-outline"></i> Targetting <span class="font-weight-bold" id="scopeDesc">selected scope</span> only.
                </div>
            </div>
            
            <button type="button" class="btn btn-primary btn-block py-3 shadow font-weight-bold" id="applyBulkBtn">
                Apply Update Locally
            </button>
            <p class="smallest text-center text-muted mt-2">Preview changes in the table before saving.</p>
        </div>
    </div>
</div>

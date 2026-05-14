{{--
    Perform Investigation Modal + JavaScript
    =========================================
    Include once per view that uses investigationHistoryList / imagingHistoryList.
    Requires:
      - invest_res_modal + invest_res_js partials already included in the same view
      - enterLabResult(id) and enterImagingResult(id) JS functions defined in the view
      - Bootstrap 5
--}}

<div class="modal fade" id="performInvestModal" tabindex="-1" aria-labelledby="performInvestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: rgba(255,193,7,.10);">
                <h5 class="modal-title" id="performInvestModalLabel">
                    <i class="mdi mdi-flask-outline me-2 text-warning"></i>
                    Perform Investigation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    You are about to bill and perform this investigation yourself.
                    A billing record will be created and you will then be prompted to enter the result.
                </p>

                <div class="card border-0 bg-light mb-3">
                    <div class="card-body py-2 px-3">
                        <strong id="pi_service_name" class="d-block mb-2 text-dark fs-6"></strong>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal ps-0" style="width:45%">Full Price</th>
                                    <td id="pi_full_price" class="fw-semibold"></td>
                                </tr>
                                <tr id="pi_hmo_row" style="display:none;">
                                    <th class="text-muted fw-normal ps-0">HMO Coverage</th>
                                    <td>
                                        <span id="pi_coverage_badge" class="badge bg-info me-1"></span>
                                        <span id="pi_claims_amount" class="text-success small"></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal ps-0">Payable Amount</th>
                                    <td id="pi_payable_amount" class="fw-bold text-danger"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert alert-info py-2 mb-0" style="font-size:.85rem;">
                    <i class="mdi mdi-information-outline me-1"></i>
                    Confirming will record the billing and immediately open the result entry form.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="confirmPerformInvestBtn">
                    <i class="mdi mdi-check me-1"></i> Confirm &amp; Enter Result
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Per-click state ──────────────────────────────────────────────────
    var _piCtx = {};

    // ── Open billing confirmation modal ──────────────────────────────────
    window.performInvestigation = function (btn) {
        var $btn = $(btn);

        _piCtx = {
            type:      $btn.data('type'),         // 'lab' | 'imaging'
            requestId: $btn.data('request-id'),
            patientId: $btn.data('patient-id')
        };

        var price   = parseFloat($btn.data('price'))   || 0;
        var payable = parseFloat($btn.data('payable'))  || price;
        var claims  = parseFloat($btn.data('claims'))   || 0;
        var covMode = (($btn.data('coverage-mode') || '') + '').trim();

        var fmt = function (n) {
            return parseFloat(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        $('#pi_service_name').text($btn.data('service-name') || '');
        $('#pi_full_price').text(fmt(price));
        $('#pi_payable_amount').text(fmt(payable));

        if (covMode) {
            $('#pi_coverage_badge').text(covMode.toUpperCase());
            $('#pi_claims_amount').text('Claim: ' + fmt(claims));
            $('#pi_hmo_row').show();
        } else {
            $('#pi_hmo_row').hide();
        }

        $('#performInvestModal').modal('show');
    };

    // ── Confirm: bill → open result entry ────────────────────────────────
    $(document).on('click', '#confirmPerformInvestBtn', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Billing...');

        var billingUrl = _piCtx.type === 'lab'
            ? '{{ route("lab.recordBilling") }}'
            : '{{ route("imaging.recordBilling") }}';

        $.ajax({
            url:    billingUrl,
            method: 'POST',
            data: {
                _token:      $('meta[name="csrf-token"]').attr('content'),
                request_ids: [_piCtx.requestId],
                patient_id:  _piCtx.patientId
            },
            success: function (response) {
                if (!response.success) {
                    toastr.error(response.message || 'Billing failed.');
                    $btn.prop('disabled', false)
                        .html('<i class="mdi mdi-check me-1"></i> Confirm &amp; Enter Result');
                    return;
                }

                // Wait for performInvestModal to FULLY close before showing the result
                // entry modal — Bootstrap 5 cannot open a new modal while another is
                // still mid-animation, which would leave investResModal in a broken state.
                var _capturedCtx = { type: _piCtx.type, id: _piCtx.requestId };
                $('#performInvestModal').one('hidden.bs.modal', function () {
                    window._investResultContext = {
                        type: _capturedCtx.type,
                        id:   _capturedCtx.id
                    };
                    if (_capturedCtx.type === 'lab') {
                        if (typeof enterLabResult === 'function') {
                            enterLabResult(_capturedCtx.id);
                        }
                    } else {
                        if (typeof enterImagingResult === 'function') {
                            enterImagingResult(_capturedCtx.id);
                        }
                    }
                });
                $('#performInvestModal').modal('hide');
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Billing error. Please try again.';
                toastr.error(msg);
                $btn.prop('disabled', false)
                    .html('<i class="mdi mdi-check me-1"></i> Confirm &amp; Enter Result');
            }
        });
    });

    // ── Reset confirm button when modal closes ───────────────────────────
    $('#performInvestModal').on('hidden.bs.modal', function () {
        $('#confirmPerformInvestBtn')
            .prop('disabled', false)
            .html('<i class="mdi mdi-check me-1"></i> Confirm &amp; Enter Result');
    });

    // ── Claim self-perform for combo/bundle items ─────────────────────────
    // Combo items are auto-billed as part of a service bundle, so the doctor
    // must explicitly claim intent to self-perform before entering a result.
    window.claimComboPerform = function (requestId, type, serviceName, btn) {
        if (!confirm('Confirm you are self-performing "' + serviceName + '"?\n\nThis item was auto-billed as part of a service bundle.')) {
            return;
        }

        var $btn = $(btn);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Claiming...');

        var claimUrl = type === 'lab'
            ? '{{ route("lab.claimSelfPerform") }}'
            : '{{ route("imaging.claimSelfPerform") }}';

        $.ajax({
            url:    claimUrl,
            method: 'POST',
            data: {
                _token:     $('meta[name="csrf-token"]').attr('content'),
                request_id: requestId
            },
            success: function (response) {
                if (!response.success) {
                    toastr.error(response.message || 'Failed to claim perform intent.');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-flask-outline"></i> Perform Investigation');
                    return;
                }
                // Immediately open result entry
                if (type === 'lab') {
                    if (typeof enterLabResult === 'function') enterLabResult(requestId);
                } else {
                    if (typeof enterImagingResult === 'function') enterImagingResult(requestId);
                }
                // Refresh the investigation history datatable
                if (typeof labHistoryTable !== 'undefined' && labHistoryTable) labHistoryTable.ajax.reload(null, false);
                if (typeof imagingHistoryTable !== 'undefined' && imagingHistoryTable) imagingHistoryTable.ajax.reload(null, false);
            },
            error: function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Error claiming perform intent. Please try again.';
                toastr.error(msg);
                $btn.prop('disabled', false).html('<i class="mdi mdi-flask-outline"></i> Perform Investigation');
            }
        });
    };
})();
</script>

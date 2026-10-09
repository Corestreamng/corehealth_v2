/**
 * procedure-outcome-modal.js
 * Interactive Procedure Outcome Documentation Modal
 * Handles NHMIS Delegated Indicator awareness, interactive pills, and synchronized notes.
 */

(function () {
    'use strict';

    var currentNhmisMapping = null;

    window.openProcedureOutcomeModal = function (procedureId, serviceName, isFreeForm, serviceId) {
        $('#outcome_procedure_id').val(procedureId);
        $('#procedure_outcome').val('');
        $('#procedure_outcome_notes').val('');
        $('#procedure_nhmis_outcome').val('');
        $('#procedure_nhmis_outcome_raw').val('');
        $('#procedure_is_nhmis_mapped').val('0');
        $('#procedure_nhmis_indicator_bar').hide();
        $('.modal-outcome-pill').removeClass('active');
        currentNhmisMapping = null;

        if (isFreeForm) {
            $('#procedureOutcomeAlert').hide();
        } else {
            $('#procedureOutcomeAlert').show();
            var showUrl = '/patient-procedures/' + procedureId;
            $('#procedureOutcomeShowLink').attr('href', showUrl);
        }

        // Query NHMIS service mapping to detect if this procedure is delegated
        var sId = serviceId || 0;
        var sName = serviceName || '';
        var mappingUrl = '/nhmis-workbench/service-mapping/' + sId;
        if (sName) {
            mappingUrl += '?service_name=' + encodeURIComponent(sName);
        }

        $.ajax({
            url: mappingUrl,
            type: 'GET',
            success: function (res) {
                if (res && res.success && res.is_mapped) {
                    currentNhmisMapping = res;
                    $('#procedure_is_nhmis_mapped').val('1');
                    $('#procedure_nhmis_indicator_name').text(res.indicator_label || res.indicator_code);
                    $('#procedure_nhmis_indicator_bar').show();
                } else {
                    $('#procedure_is_nhmis_mapped').val('0');
                    $('#procedure_nhmis_indicator_bar').hide();
                }
            },
            error: function () {
                $('#procedure_is_nhmis_mapped').val('0');
                $('#procedure_nhmis_indicator_bar').hide();
            }
        });

        $('#procedureOutcomeModal').modal('show');
    };

    $(document).ready(function () {
        // Pill click handler
        $(document).on('click', '.modal-outcome-pill', function (e) {
            e.preventDefault();
            var val = $(this).data('val');
            var isPos = $(this).data('is-pos') == '1';

            $('.modal-outcome-pill').removeClass('active');
            $(this).addClass('active');

            $('#procedure_outcome').val(val);

            var isMapped = $('#procedure_is_nhmis_mapped').val() === '1';
            if (isMapped) {
                var outcomeVal = isPos ? 'positive' : 'negative';
                var rawVal = val.charAt(0).toUpperCase() + val.slice(1);
                $('#procedure_nhmis_outcome').val(outcomeVal);
                $('#procedure_nhmis_outcome_raw').val(rawVal);
            } else {
                $('#procedure_nhmis_outcome').val('');
                $('#procedure_nhmis_outcome_raw').val('');
            }

            // Suggest clinical notes if field is currently blank
            var currentNotes = $('#procedure_outcome_notes').val();
            if (!currentNotes || currentNotes.trim() === '') {
                if (val === 'successful') {
                    if (currentNhmisMapping && currentNhmisMapping.indicator_code === 'caesarean_section') {
                        $('#procedure_outcome_notes').val('Caesarean section completed successfully. Uterus closed in layers, hemostasis achieved, sponge and instrument counts correct. Mother and neonate in stable condition.');
                    } else if (currentNhmisMapping && (currentNhmisMapping.indicator_code === 'mva_spontaneous' || currentNhmisMapping.indicator_code === 'mva_induced' || currentNhmisMapping.indicator_code === 'mva_pac')) {
                        $('#procedure_outcome_notes').val('Manual vacuum aspiration (MVA) completed successfully. Complete uterine evacuation confirmed, minimal blood loss. Vital signs stable.');
                    } else if (currentNhmisMapping && currentNhmisMapping.indicator_code === 'tubal_ligation') {
                        $('#procedure_outcome_notes').val('Bilateral tubal ligation (BTL) completed successfully. Bilateral fallopian tubes identified and ligated without complication.');
                    } else if (currentNhmisMapping && currentNhmisMapping.indicator_code === 'vasectomy') {
                        $('#procedure_outcome_notes').val('Vasectomy completed successfully under local anesthesia. Bilateral vas deferens isolated, divided, and ligated.');
                    } else if (currentNhmisMapping && currentNhmisMapping.indicator_code === 'fistula_repair') {
                        $('#procedure_outcome_notes').val('Obstetric fistula repair completed successfully. Tension-free closure achieved, dye test negative.');
                    } else {
                        $('#procedure_outcome_notes').val('Procedure completed successfully without immediate complications. Patient stable post-procedure.');
                    }
                }
            }
        });

        // Dropdown change handler
        $(document).on('change', '#procedure_outcome', function () {
            var val = $(this).val();
            $('.modal-outcome-pill').removeClass('active');
            if (val) {
                var $pill = $('.modal-outcome-pill[data-val="' + val + '"]');
                $pill.addClass('active');

                var isMapped = $('#procedure_is_nhmis_mapped').val() === '1';
                if (isMapped) {
                    var isPos = $pill.data('is-pos') == '1';
                    $('#procedure_nhmis_outcome').val(isPos ? 'positive' : 'negative');
                    $('#procedure_nhmis_outcome_raw').val(val.charAt(0).toUpperCase() + val.slice(1));
                } else {
                    $('#procedure_nhmis_outcome').val('');
                    $('#procedure_nhmis_outcome_raw').val('');
                }
            }
        });

        // Save Outcome handler
        $('#btn-save-procedure-outcome').on('click', function () {
            var procedureId = $('#outcome_procedure_id').val();
            var outcome = $('#procedure_outcome').val();
            var notes = $('#procedure_outcome_notes').val();
            var nhmisOutcome = $('#procedure_nhmis_outcome').val();
            var nhmisOutcomeRaw = $('#procedure_nhmis_outcome_raw').val();
            var isMapped = $('#procedure_is_nhmis_mapped').val() === '1';

            if (!outcome) {
                var errorMsg = isMapped ? 'Selecting an outcome is required for this mapped procedure.' : 'Please select a procedure outcome.';
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Outcome Required', errorMsg, 'warning');
                } else if (typeof toastr !== 'undefined') {
                    toastr.warning(errorMsg);
                } else {
                    alert(errorMsg);
                }
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Saving...');

            var payload = {
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                outcome: outcome,
                outcome_notes: notes
            };

            if (nhmisOutcome) {
                payload.nhmis_outcome = nhmisOutcome;
                payload.nhmis_outcome_raw = nhmisOutcomeRaw;
            }

            $.ajax({
                url: '/patient-procedures/' + procedureId + '/outcome',
                type: 'PUT',
                data: payload,
                success: function (response) {
                    $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
                    if (response.success) {
                        $('#procedureOutcomeModal').modal('hide');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Outcome documented successfully.',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.success('Outcome documented successfully.');
                        }

                        // Reload data tables if they exist
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#procedures_history_list')) {
                            $('#procedures_history_list').DataTable().ajax.reload(null, false);
                        }
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#procedure_history_list')) {
                            $('#procedure_history_list').DataTable().ajax.reload(null, false);
                        }
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#surgery-queue-table')) {
                            $('#surgery-queue-table').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message || 'Failed to save outcome.', 'error');
                        } else {
                            alert(response.message || 'Failed to save outcome.');
                        }
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('<i class="fa fa-save mr-1"></i>Save Outcome');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'An error occurred while saving. Please try again.', 'error');
                    } else {
                        alert('An error occurred while saving. Please try again.');
                    }
                }
            });
        });
    });
})();

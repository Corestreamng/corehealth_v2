{{-- Shared Clinical Orders Modal (Currently customized for Procedures in Surgery Workbench) --}}
<div class="modal fade" id="clinical_orders_modal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title mb-0 d-flex align-items-center" style="font-weight: 600;">
                    <i class="mdi mdi-medical-bag me-2"></i> Clinical Requests
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="d-flex align-items-start">
                    <div class="nav flex-column nav-pills me-3 p-3 bg-white border-end shadow-sm" style="min-width: 200px; min-height: 500px;" role="tablist" aria-orientation="vertical">
                        <button class="nav-link text-start active" id="cp-procedures-tab" data-bs-toggle="pill" data-bs-target="#cp-procedures" type="button" role="tab">
                            <i class="mdi mdi-scalpel me-2"></i> Procedures
                        </button>
                        {{-- Future tabs for Labs, Imaging, Meds can be added here --}}
                    </div>
                    <div class="tab-content flex-grow-1 p-4" id="v-pills-tabContent">
                        <div class="tab-pane fade show active" id="cp-procedures" role="tabpanel">
                            <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
                                <div class="card-header bg-white border-bottom py-3">
                                    <h5 class="mb-0"><i class="mdi mdi-plus-circle text-primary"></i> Request New Procedure</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label><i class="fa fa-search"></i> Search Procedure</label>
                                                <input type="text" class="form-control" id="sw_proc_search" placeholder="Type procedure name or code..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="sw_proc_results"></ul>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label><i class="fa fa-exclamation-triangle"></i> Priority</label>
                                                <select class="form-control" id="sw_proc_priority">
                                                    <option value="routine">Routine</option>
                                                    <option value="urgent">Urgent</option>
                                                    <option value="emergency">Emergency</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group mb-3">
                                                <label><i class="fa fa-calendar"></i> Scheduled Date</label>
                                                <input type="date" class="form-control" id="sw_proc_scheduled_date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label><i class="fa fa-sticky-note"></i> Pre-op / Clinical Notes</label>
                                        <textarea class="form-control" id="sw_proc_notes" rows="2" placeholder="Clinical indications, relevant history..."></textarea>
                                    </div>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-bordered table-striped">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Procedure</th>
                                                    <th>Price</th>
                                                    <th>Priority</th>
                                                    <th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody id="sw-selected-procedures"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<style>
.co-search-dropdown {
    position: absolute;
    z-index: 1000;
    width: 95%;
    max-height: 300px;
    overflow-y: auto;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.co-search-dropdown .list-group-item {
    cursor: pointer;
    transition: background-color 0.2s;
}
.co-search-dropdown .list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // We defer the setup until ClinicalOrdersKit is initialized for a patient
    
    // Procedure Search logic
    var swProcTimer;
    $('#sw_proc_search').on('input', function() {
        clearTimeout(swProcTimer);
        var q = $(this).val().trim();
        if (q.length < 2) {
            $('#sw_proc_results').hide();
            return;
        }
        var patientId = $('#sw-patient-banner').hasClass('visible') ? $('.sw-search-item[data-patient-id]').data('patient-id') : null;
        if (!patientId && window.ClinicalOrdersKit && ClinicalOrdersKit.currentPatientId) {
            patientId = ClinicalOrdersKit.currentPatientId;
        }
        
        swProcTimer = setTimeout(function() {
            $.get('/live-search-services', { q: q, type: 'procedure', patient_id: patientId }, function(results) {
                var $res = $('#sw_proc_results').empty();
                
                // Free form addition fallback
                if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.appendFreeFormLink === 'function') {
                    ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Procedure', 'Enter procedure name:', '#sw_proc_search', function(val) {
                        addSurgeryProcedure(val + ' [Free-form]', 'FF_' + val, 0);
                    });
                }
                
                if (!results || !results.length) {
                    if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.showSearchEmpty === 'function') {
                        ClinicalOrdersKit.showSearchEmpty('#sw_proc_results', 'procedures');
                    } else {
                        $res.append('<li class="list-group-item text-muted">No procedures found</li>');
                    }
                    $res.show();
                    return;
                }
                
                results.forEach(function(item) {
                    var name = item.service_name || 'Unknown';
                    var code = item.service_code || '';
                    var price = item.price?.sale_price ?? 0;
                    var display = name + ' [' + code + ']';
                    var payable = item.payable_amount ?? price;
                    
                    var alreadyAdded = false;
                    if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.isAlreadyAdded === 'function') {
                        alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(item.id));
                    }
                    
                    var onClickStr = alreadyAdded ? '' : 'addSurgeryProcedure(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + payable + ')';
                    
                    if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.renderSearchResultItem === 'function') {
                        $res.append(ClinicalOrdersKit.renderSearchResultItem({
                            id: item.id,
                            category: item.category?.category_name || 'Procedure',
                            name: name,
                            code: code,
                            price: price,
                            payable: payable,
                            claims: item.claims_amount ?? 0,
                            mode: item.coverage_mode || null,
                            alreadyAdded: alreadyAdded,
                            alreadyLabel: 'Already Added',
                            onClick: onClickStr
                        }));
                    } else {
                        // Fallback simple item
                        $res.append('<li class="list-group-item d-flex justify-content-between align-items-center" onclick="' + onClickStr + '">' +
                            '<div><strong>' + name + '</strong> <small class="text-muted">' + code + '</small></div>' +
                            '<div><span class="badge bg-primary">₦' + payable + '</span></div>' +
                        '</li>');
                    }
                });
                $res.show();
            });
        }, 300);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#sw_proc_search, #sw_proc_results').length) {
            $('#sw_proc_results').hide();
        }
    });
});

function addSurgeryProcedure(name, id, price) {
    if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.isAlreadyAdded === 'function') {
        if (ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(id))) {
            toastr.warning('Procedure already added');
            return;
        }
    }
    
    var priority = $('#sw_proc_priority').val() || 'routine';
    var scheduledDate = $('#sw_proc_scheduled_date').val() || '';
    var preNotes = $('#sw_proc_notes').val() || '';
    
    var patientId = null;
    if (window.ClinicalOrdersKit && ClinicalOrdersKit.currentPatientId) {
        patientId = ClinicalOrdersKit.currentPatientId;
    } else {
        // Try getting from active banner
        var bannerPatient = $('.sw-search-item[data-patient-id]');
        if (bannerPatient.length && bannerPatient.closest('#sw-search-dropdown').css('display') !== 'block') {
            patientId = bannerPatient.data('patient-id');
        }
    }
    
    if (!patientId) {
        toastr.error('No patient selected.');
        return;
    }
    
    // We use the Nursing Workbench endpoint since it allows adding to a patient without an active encounter
    var url = '/nursing-workbench/clinical-requests/add-procedure';
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    
    if (typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.addItem === 'function') {
        ClinicalOrdersKit.addItem({
            url: url,
            payload: {
                service_id: id,
                patient_id: patientId,
                priority: priority,
                scheduled_date: scheduledDate,
                pre_notes: preNotes
            },
            csrfToken: csrfToken,
            tableSelector: '#sw-selected-procedures',
            type: 'procedures',
            referenceId: parseInt(id),
            buildRowHtml: function(resp) {
                var isFreeForm = String(id).startsWith('FF_');
                var priorityClass = {
                    routine: 'bg-success',
                    urgent: 'bg-warning text-dark',
                    emergency: 'bg-danger'
                }[priority] || 'bg-secondary';
                var priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);
                
                return '<tr data-record-id="' + resp.id + '">' +
                    '<td>' + name + (isFreeForm ? ' <span class="badge bg-secondary ms-1">Free-form</span>' : '') + '</td>' +
                    '<td>' + (price || 0) + '</td>' +
                    '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="removeSurgeryProcedure(this, ' + resp.id + ', \'' + name.replace(/'/g, "\\'") + '\')"><i class="fa fa-times"></i></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                // Trigger event so Surgery Workbench can reload queue if needed
                $(document).trigger('clinicalOrders:procedureAdded', [resp]);
            }
        });
    } else {
        // Fallback standard ajax
        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: csrfToken,
                service_id: id,
                patient_id: patientId,
                priority: priority,
                scheduled_date: scheduledDate,
                pre_notes: preNotes
            },
            success: function(resp) {
                if (resp.success) {
                    var priorityClass = {
                        routine: 'bg-success',
                        urgent: 'bg-warning text-dark',
                        emergency: 'bg-danger'
                    }[priority] || 'bg-secondary';
                    var priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);
                    var html = '<tr data-record-id="' + resp.id + '">' +
                        '<td>' + name + '</td>' +
                        '<td>' + (price || 0) + '</td>' +
                        '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span></td>' +
                        '<td><button class="btn btn-sm btn-danger" onclick="removeSurgeryProcedure(this, ' + resp.id + ', \'' + name.replace(/'/g, "\\'") + '\')"><i class="fa fa-times"></i></button></td>' +
                    '</tr>';
                    $('#sw-selected-procedures').append(html);
                    $(document).trigger('clinicalOrders:procedureAdded', [resp]);
                    toastr.success('Procedure added');
                } else {
                    toastr.error(resp.message || 'Error adding procedure');
                }
            },
            error: function(xhr) {
                toastr.error('Error adding procedure');
            }
        });
    }
    
    $('#sw_proc_search').val('');
    $('#sw_proc_results').hide();
}

function removeSurgeryProcedure(btn, id, name) {
    if (typeof deleteNurseClinicalRequest === 'function') {
        // Use standard nursing delete so it uses standard confirmation
        deleteNurseClinicalRequest('procedure', id, name);
        // Remove row on success - wait, deleteNurseClinicalRequest normally reloads tables.
        // For our modal, we can manually remove the row if we listen to the success or just wait for table reload.
        // Actually deleteNurseClinicalRequest reloads DataTables, not normal table rows.
        // Let's hook into it by overriding the callback? We can just do a custom delete for Surgery Modal:
        
        ClinicalOrdersKit.showDeleteConfirmation({
            type: 'procedure',
            itemName: name,
            onConfirm: function(reason, callback) {
                $.ajax({
                    url: '/nursing-workbench/clinical-requests/procedures/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { reason: reason },
                    success: function(response) {
                        callback(true);
                        if(response.success) {
                            $(btn).closest('tr').remove();
                            if(typeof ClinicalOrdersKit !== 'undefined' && typeof ClinicalOrdersKit.untrackId === 'function') {
                                ClinicalOrdersKit.untrackId('procedures', $(btn).closest('tr').data('service-id'));
                            }
                            $(document).trigger('clinicalOrders:procedureAdded'); // Reuse to trigger reload
                            toastr.success('Deleted successfully');
                        }
                    },
                    error: function() {
                        callback(false);
                        toastr.error('Delete failed');
                    }
                });
            }
        });
    }
}
</script>

{{-- Service Combos - Request Bundle --}}
<div class="card-modern mt-2">
    <div class="card-body">
        <h5 class="mb-3"><i class="fa fa-boxes"></i> Service Combos</h5>
        <div class="alert alert-info py-2">
            <i class="fa fa-info-circle"></i> Select a combo to request multiple services and products at once with bundle pricing.
        </div>

        <div class="form-group mb-4">
            <label for="combo_search" class="fw-bold">Search Combos</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="combo_search" 
                       onkeyup="ClinicalOrdersKit.searchCombos(this.value)" 
                       placeholder="Type to search service bundles..." autocomplete="off">
            </div>
            <ul class="list-group shadow-sm mt-1" id="combo_search_res" style="display: none; position: absolute; z-index: 1000; width: calc(100% - 40px);"></ul>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped" id="active_combos_table">
                <thead class="table-light">
                    <tr>
                        <th>Combo Name</th>
                        <th>Price</th>
                        <th>Includes</th>
                        <th style="width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody id="combo_list_body">
                    <tr class="text-center">
                        <td colspan="4" class="py-3 text-muted">No combos requested in this encounter yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Integration for Combo Searching and Adding
        // window.ClinicalOrdersKit is either initialized here or by clinical-orders-shared.js
        window.ClinicalOrdersKit = window.ClinicalOrdersKit || {};

        /**
         * Search for service combos via API.
         */
        ClinicalOrdersKit.searchCombos = function(term) {
            let res_ul = $('#combo_search_res');
            if (term.length < 2) {
                res_ul.hide().html('');
                return;
            }

            $.ajax({
                url: "{{ route('api.service-combos.search') }}",
                data: { term: term },
                success: function(data) {
                    if (data.length > 0) {
                        let html = '';
                        data.forEach(combo => {
                            html += `
                                <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                                    onclick="ClinicalOrdersKit.addCombo(${combo.id})">
                                    <div>
                                        <span class="fw-bold text-primary">${combo.service_name}</span>
                                        <div class="small text-muted">${combo.items_summary}</div>
                                    </div>
                                    <span class="badge bg-success">₦${combo.price}</span>
                                </li>
                            `;
                        });
                        res_ul.html(html).show();
                    } else {
                        res_ul.html('<li class="list-group-item text-muted">No combos found</li>').show();
                    }
                }
            });
        };

        /**
         * Apply a selected combo to the current encounter.
         */
        ClinicalOrdersKit.addCombo = function(id) {
            $('#combo_search_res').hide();
            $('#combo_search').val('');
            
            let encounter_id = $('#encounter_id__').val();
            let patient_id = $('#encounter_patient_id__').val();

            toastr.info('Applying combo, please wait...', '', { timeOut: 3000 });

            $.ajax({
                type: 'POST',
                url: "{{ route('encounters.applyCombo', $encounter->id) }}",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    service_id: id,
                    encounter_id: encounter_id,
                    patient_id: patient_id
                },
                success: function(response) {
                    toastr.clear();
                    if (response.success) {
                        toastr.success('Service Combo applied successfully!');
                        // Refresh other tabs as items might have been added
                        if (typeof refreshLabHistory === 'function') refreshLabHistory();
                        if (typeof refreshImagingHistory === 'function') refreshImagingHistory();
                        if (typeof refreshPrescHistory === 'function') refreshPrescHistory();
                        if (typeof refreshProceduresList === 'function') refreshProceduresList();
                    } else {
                        toastr.error(response.message || 'Failed to apply combo');
                    }
                },
                error: function() {
                    toastr.clear();
                    toastr.error('Network error occurred.');
                }
            });
        };
    });
</script>
@endpush

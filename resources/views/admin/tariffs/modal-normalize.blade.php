<div class="modal fade" id="normalizeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header py-3 border-0 text-white" style="background: #7B1FA2;">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white-transparent p-2 mr-3">
                        <i class="mdi mdi-auto-fix mdi-24px"></i>
                    </div>
                    <h5 class="modal-title font-weight-bold mb-0">Quick Normalization Tool</h5>
                </div>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="normalizeForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-soft-purple mb-4 smallest border-left border-purple" style="border-left-width: 4px !important;">
                        <i class="mdi mdi-lightning-bolt mr-2 text-purple"></i>
                        <strong>Action:</strong> This tool will scan all items within the selected scheme and apply the specified percentage split. Use this for rapid baseline configuration of new HMOs.
                    </div>

                    <div class="form-group mb-4">
                        <label class="small font-weight-bold text-muted text-uppercase mb-2">Target Scheme</label>
                        <select name="scheme_id" class="form-control form-control-lg border-2 select2-normalize" required style="width: 100%;">
                            @foreach($schemes as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                        </select>
                        <p class="smallest text-muted mt-2">All HMO providers under this scheme will be affected.</p>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <label class="small font-weight-bold text-muted text-uppercase mb-2">Pharmacy Patient Pays (%)</label>
                            <div class="input-group">
                                <input type="number" name="drug_patient_pct" class="form-control form-control-lg font-weight-bold" value="10">
                                <div class="input-group-append"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small font-weight-bold text-muted text-uppercase mb-2">Service HMO Claims (%)</label>
                            <div class="input-group">
                                <input type="number" name="service_claims_pct" class="form-control form-control-lg font-weight-bold" value="100">
                                <div class="input-group-append"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                    </div>
                    <p class="smallest text-center text-muted mt-3 mb-0">Example: 10% co-pay for drugs, 100% coverage for consultations.</p>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4 py-2 font-weight-bold shadow-sm" style="background: #7B1FA2;" id="doNormalizeBtn">
                        <i class="mdi mdi-check-all mr-1"></i> Apply Baseline Split
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .alert-soft-purple { background: rgba(123, 31, 162, 0.05); color: #7B1FA2; }
    .text-purple { color: #7B1FA2; }
</style>

@push('modal_scripts')
<script>
    $(function() {
        $('.select2-normalize').select2({ dropdownParent: $('#normalizeModal'), theme: 'bootstrap4' });

        $('#normalizeForm').on('submit', function(e) {
            e.preventDefault();
            if (!confirm('This will globally update multiple tariffs in this scheme. Are you sure?')) return;
            
            const $btn = $('#doNormalizeBtn');
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

            $.ajax({
                url: "{{ route('hmo-tariffs.normalize') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    $('#normalizeModal').modal('hide');
                    toastr.success(res.message);
                    if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
                },
                error: function(xhr) { 
                    toastr.error(xhr.responseJSON?.message || 'Normalization failed'); 
                },
                complete: function() { 
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check-all mr-1"></i> Apply Baseline Split');
                }
            });
        });
    });
</script>
@endpush

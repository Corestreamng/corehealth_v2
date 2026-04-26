<div class="modal fade" id="modal-normalize" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: #7B1FA2;">
                <h5 class="modal-title">Quick Drug Split / Normalize</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="normalizeForm">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Target Scheme <span class="text-danger">*</span></label>
                        <select name="scheme_id" class="form-control" required>
                            @foreach($schemes as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label>Drug Patient Pays (%)</label>
                            <input type="number" name="drug_patient_pct" class="form-control" value="10">
                        </div>
                        <div class="col-6">
                            <label>Service HMO Covers (%)</label>
                            <input type="number" name="service_claims_pct" class="form-control" value="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn text-white" style="background: #7B1FA2;" id="doNormalizeBtn">Apply Normalization</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('modal_scripts')
<script>
    $('#normalizeForm').submit(function(e) {
        e.preventDefault();
        if (!confirm('This will overwrite multiple tariffs. Continue?')) return;
        const $btn = $('#doNormalizeBtn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: "{{ route('hmo-tariffs.normalize') }}",
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                $('#modal-normalize').modal('hide');
                toastr.success(res.message);
                if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Normalization failed'); },
            complete: function() { $btn.prop('disabled', false).text('Apply Normalization'); }
        });
    });
</script>
@endpush

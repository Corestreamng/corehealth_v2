<div class="modal fade" id="modal-import" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Import Tariffs</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Expected Format:</strong> Excel or CSV with columns for Item Code/Name, Type, Category, Claims, and Payable.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Target HMO (or leave blank for global match)</label>
                                <select name="hmo_id" class="form-control select2">
                                    <option value="">Global Import</option>
                                    @foreach($hmos as $h) <option value="{{ $h->id }}">{{ $h->name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>File <span class="text-danger">*</span></label>
                                <input type="file" name="file" class="form-control-file" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="doImportBtn">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('modal_scripts')
<script>
    $('#importForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const $btn = $('#doImportBtn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

        $.ajax({
            url: "{{ route('hmo-tariffs.import') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $('#modal-import').modal('hide');
                toastr.success(res.message);
                if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Import failed'); },
            complete: function() { $btn.prop('disabled', false).text('Upload & Import'); }
        });
    });
</script>
@endpush

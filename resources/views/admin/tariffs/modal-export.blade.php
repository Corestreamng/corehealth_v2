<div class="modal fade" id="modal-export" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Export Tariffs</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('hmo-tariffs.export-excel') }}" method="GET">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Scope</label>
                        <select name="scope" class="form-control" id="export-scope">
                            <option value="all">All Tariffs</option>
                            <option value="hmo">By HMO</option>
                            <option value="scheme">By Scheme</option>
                        </select>
                    </div>
                    <div class="form-group" id="export-hmo-group" style="display:none;">
                        <label>Select HMO</label>
                        <select name="hmo_id" class="form-control select2">
                            @foreach($hmos as $h) <option value="{{ $h->id }}">{{ $h->name }}</option> @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="export-scheme-group" style="display:none;">
                        <label>Select Scheme</label>
                        <select name="scheme_id" class="form-control select2">
                            @foreach($schemes as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Download Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('modal_scripts')
<script>
    $('#export-scope').change(function() {
        const val = $(this).val();
        $('#export-hmo-group').toggle(val === 'hmo');
        $('#export-scheme-group').toggle(val === 'scheme');
    });
</script>
@endpush

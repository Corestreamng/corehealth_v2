<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header bg-warning text-dark py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-dark-soft p-2 mr-3">
                        <i class="mdi mdi-file-upload mdi-24px"></i>
                    </div>
                    <h5 class="modal-title font-weight-bold mb-0">Bulk Import Tariffs</h5>
                </div>
                <button type="button" class="close text-dark" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="importForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div id="import-selection-step">
                        <div class="row mb-4">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Import Instructions</h6>
                                <p class="text-muted small">Upload an Excel (.xlsx) or CSV file. The system will match items by their <strong>Code</strong> or <strong>Full Name</strong>.</p>
                                <div class="bg-light p-3 rounded border">
                                    <ul class="mb-0 smallest text-muted pl-3">
                                        <li>Column A: HMO Provider (for Global Import)</li>
                                        <li>Column B & C: Item Code & Full Name</li>
                                        <li>Column G: Claims Amount (Naira)</li>
                                        <li>Column H: Patient Payable (Naira)</li>
                                        <li>Column I: Coverage (Express/Primary/Secondary)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-5 border-left">
                                <div class="text-center py-3">
                                    <i class="mdi mdi-download text-primary mdi-36px mb-2 d-block"></i>
                                    <a href="#" class="btn btn-soft-primary btn-sm font-weight-bold">Download Template</a>
                                    <p class="smallest text-muted mt-2 mb-0">Use our standard format for best results.</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold text-muted text-uppercase mb-2">Destination Target</label>
                                    <select name="hmo_id" class="form-control form-control-lg select2-import" id="import-hmo-id" style="width: 100%;">
                                        <option value="">Global Import (Matches Existing HMOs)</option>
                                        @php
                                            $groupedHmos = $hmos->groupBy(function($hmo) {
                                                return $hmo->scheme ? $hmo->scheme->name : 'Unassigned HMOs';
                                            });
                                            $unassigned = $groupedHmos->pull('Unassigned HMOs');
                                        @endphp
                                        
                                        @foreach($groupedHmos as $schemeName => $hmoList)
                                            <optgroup label="{{ $schemeName }}">
                                                @foreach($hmoList as $h) <option value="{{ $h->id }}">{{ $h->name }}</option> @endforeach
                                            </optgroup>
                                        @endforeach

                                        @if($unassigned)
                                            <optgroup label="Unassigned HMOs">
                                                @foreach($unassigned as $h) <option value="{{ $h->id }}">{{ $h->name }}</option> @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <div class="smallest text-muted mt-2">Choose an HMO if you want to force all rows into a single provider.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold text-muted text-uppercase mb-2">Excel/CSV File <span class="text-danger">*</span></label>
                                    <div class="custom-file custom-file-lg">
                                        <input type="file" name="file" class="custom-file-input" id="tariffFile" required>
                                        <label class="custom-file-label" for="tariffFile">Choose file...</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer bg-light border-0 py-3 px-0 mt-4">
                            <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary px-5 py-2 font-weight-bold shadow-sm" id="btnPreviewImport">
                                <i class="mdi mdi-eye mr-1"></i> Preview Changes
                            </button>
                        </div>
                    </div>

                    {{-- Preview Step --}}
                    <div id="import-preview-step" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="font-weight-bold text-dark mb-0">Import Impact Summary</h6>
                            <button type="button" class="btn btn-sm btn-link text-primary" id="btnBackToSelection">
                                <i class="mdi mdi-arrow-left"></i> Change File
                            </button>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded text-center border">
                                    <div class="text-primary font-weight-bold h4 mb-0" id="preview-updates">0</div>
                                    <div class="smallest text-muted text-uppercase">To Update</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded text-center border">
                                    <div class="text-success font-weight-bold h4 mb-0" id="preview-new">0</div>
                                    <div class="smallest text-muted text-uppercase">New Records</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 bg-light rounded text-center border">
                                    <div class="text-danger font-weight-bold h4 mb-0" id="preview-skipped">0</div>
                                    <div class="smallest text-muted text-uppercase">Skipped/Errors</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive border rounded" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0 smallest">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Claims (New)</th>
                                        <th>Payable (New)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="preview-table-body"></tbody>
                            </table>
                        </div>

                        <div class="modal-footer bg-light border-0 py-3 px-0 mt-4">
                            <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning px-5 py-2 font-weight-bold shadow-sm" id="doImportBtn">
                                <i class="mdi mdi-check-all mr-1"></i> Confirm & Import
                            </button>
                        </div>
                    </div>

                    <div id="importStatus" class="mt-3 d-none">
                        <div class="progress progress-sm mb-2" style="height: 6px;">
                            <div class="progress-bar progress-bar-animated progress-bar-striped bg-warning" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center smallest font-weight-bold text-warning" id="status-text">Processing...</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .bg-dark-soft { background: rgba(0,0,0,0.05); }
    .custom-file-lg .custom-file-label { padding: .5rem 1rem; height: calc(1.5em + 1rem + 2px); line-height: 1.5; }
    .custom-file-lg .custom-file-label::after { padding: .5rem 1rem; height: calc(1.5em + 1rem); line-height: 1.5; }
</style>

@push('modal_scripts')
<script>
    $(function() {
        $('.select2-import').select2({ dropdownParent: $('#importModal'), theme: 'bootstrap4' });

        $('#tariffFile').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });

        $('#btnPreviewImport').on('click', function() {
            const fileInput = document.getElementById('tariffFile');
            if (!fileInput.files[0]) { toastr.warning('Please select a file first.'); return; }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('hmo_id', $('#import-hmo-id').val());
            formData.append('scope', $('#import-hmo-id').val() ? 'hmo' : 'all');
            formData.append('_token', '{{ csrf_token() }}');

            const $btn = $(this);
            const $status = $('#importStatus');
            
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Analyzing...');
            $status.removeClass('d-none');

            $.ajax({
                url: "{{ route('hmo-tariffs.import-preview') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        $('#preview-updates').text(res.summary.updates);
                        $('#preview-new').text(res.summary.new);
                        $('#preview-skipped').text(res.summary.skipped);

                        let html = '';
                        res.preview.forEach(row => {
                            let statusClass = row.status === 'update' ? 'text-primary' : (row.status === 'new' ? 'text-success' : 'text-danger');
                            html += `<tr>
                                <td>${row.name} <br> <span class="smallest text-muted">${row.code || ''}</span></td>
                                <td class="text-uppercase">${row.type}</td>
                                <td>₦${row.new_claims.toLocaleString()}</td>
                                <td>₦${row.new_payable.toLocaleString()}</td>
                                <td class="font-weight-bold ${statusClass}">${row.status.toUpperCase()} ${row.reason ? '('+row.reason+')' : ''}</td>
                            </tr>`;
                        });
                        $('#preview-table-body').html(html);

                        $('#import-selection-step').addClass('d-none');
                        $('#import-preview-step').removeClass('d-none');
                    }
                },
                error: function(xhr) { 
                    toastr.error(xhr.responseJSON?.message || 'Preview failed. Check file format.'); 
                },
                complete: function() { 
                    $btn.prop('disabled', false).html('<i class="mdi mdi-eye mr-1"></i> Preview Changes');
                    $status.addClass('d-none');
                }
            });
        });

        $('#btnBackToSelection').on('click', function() {
            $('#import-preview-step').addClass('d-none');
            $('#import-selection-step').removeClass('d-none');
        });

        $('#importForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('scope', $('#import-hmo-id').val() ? 'hmo' : 'all');
            
            const $btn = $('#doImportBtn');
            const $status = $('#importStatus');
            
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Importing...');
            $status.removeClass('d-none');
            $('#status-text').text('Writing changes to database...');

            $.ajax({
                url: "{{ route('hmo-tariffs.import-excel') }}",
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $('#importModal').modal('hide');
                    toastr.success(res.message);
                    if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
                    
                    // Reset steps for next time
                    $('#import-preview-step').addClass('d-none');
                    $('#import-selection-step').removeClass('d-none');
                },
                error: function(xhr) { 
                    toastr.error(xhr.responseJSON?.message || 'Import failed.'); 
                },
                complete: function() { 
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check-all mr-1"></i> Confirm & Import');
                    $status.addClass('d-none');
                }
            });
        });
    });
</script>
@endpush

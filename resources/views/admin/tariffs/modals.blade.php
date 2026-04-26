<!-- Add/Edit Tariff Modal -->
<div class="modal fade" id="tariffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tariffModalTitle">Add New Tariff</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="tariffForm">
                @csrf
                <input type="hidden" id="tariff_id" name="tariff_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>HMO <span class="text-danger">*</span></label>
                                <select class="form-control" id="hmo_id" name="hmo_id" required>
                                    <option value="">Select HMO</option>
                                    @foreach($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Item Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="item_type" name="item_type" required>
                                    <option value="">Select Type</option>
                                    <option value="product">Product</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6" id="product_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Product <span class="text-danger">*</span></label>
                                <select class="form-control" id="product_id" name="product_id">
                                    <option value="">Select Product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->product_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="service_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Service <span class="text-danger">*</span></label>
                                <select class="form-control" id="service_id" name="service_id">
                                    <option value="">Select Service</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->service_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Coverage Mode <span class="text-danger">*</span></label>
                                <select class="form-control" id="coverage_mode" name="coverage_mode" required>
                                    <option value="express">Express (Auto-Approved)</option>
                                    <option value="primary" selected>Primary (Requires Validation)</option>
                                    <option value="secondary">Secondary (Requires Validation + Auth Code)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Claims Amount (HMO Covers)</label>
                                <input type="number" class="form-control" name="claims_amount" value="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payable Amount (Patient Pays)</label>
                                <input type="number" class="form-control" name="payable_amount" value="0" step="0.01">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Tariff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modals, Import Modals, etc. -->
@include('admin.tariffs.modal-export')
@include('admin.tariffs.modal-import')
@include('admin.tariffs.modal-normalize')

@push('modal_scripts')
<script>
    $('#item_type').change(function() {
        let type = $(this).val();
        $('#product_select_div').toggle(type === 'product');
        $('#service_select_div').toggle(type === 'service');
    });

    $('#tariffForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('hmo-tariffs.store') }}",
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                $('#tariffModal').modal('hide');
                toastr.success(res.message);
                // If a view is loaded, maybe refresh it?
                if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
            },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error saving'); }
        });
    });
</script>
@endpush

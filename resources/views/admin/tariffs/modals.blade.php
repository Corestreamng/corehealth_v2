<!-- Add/Edit Tariff Modal -->
<div class="modal fade" id="tariffModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white-transparent p-2 mr-3">
                        <i class="mdi mdi-plus-circle mdi-24px"></i>
                    </div>
                    <h5 class="modal-title font-weight-bold mb-0" id="tariffModalTitle">Create Single Tariff</h5>
                </div>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="tariffForm">
                @csrf
                <input type="hidden" id="tariff_id" name="tariff_id">
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Target HMO <span class="text-danger">*</span></label>
                                <select class="form-control form-control-lg select2-single" id="hmo_id" name="hmo_id" required style="width: 100%;">
                                    <option value="">Select Provider</option>
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
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Item Classification <span class="text-danger">*</span></label>
                                <select class="form-control form-control-lg" id="item_type" name="item_type" required>
                                    <option value="">Select Type</option>
                                    <option value="product">Pharmacy Product</option>
                                    <option value="service">Clinical Service</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6" id="product_select_div" style="display:none;">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Select Product <span class="text-danger">*</span></label>
                                <select class="form-control form-control-lg select2-single" id="product_id" name="product_id" style="width: 100%;">
                                    <option value="">Choose Drug...</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->product_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="service_select_div" style="display:none;">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Select Service <span class="text-danger">*</span></label>
                                <select class="form-control form-control-lg select2-single" id="service_id" name="service_id" style="width: 100%;">
                                    <option value="">Choose Service...</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->service_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Coverage Logic <span class="text-danger">*</span></label>
                                <select class="form-control form-control-lg" id="coverage_mode" name="coverage_mode" required>
                                    <option value="express">Express (Automatic Access)</option>
                                    <option value="primary" selected>Primary (HMO Approval Required)</option>
                                    <option value="secondary">Secondary (Auth Code Required)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-lg border">
                        <h6 class="smallest font-weight-bold text-muted text-uppercase mb-3">Price Distribution</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="smallest font-weight-bold text-muted">HMO Claims (₦)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">₦</span></div>
                                        <input type="number" class="form-control form-control-lg font-weight-bold" name="claims_amount" value="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="smallest font-weight-bold text-muted">Patient Payable (₦)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">₦</span></div>
                                        <input type="number" class="form-control form-control-lg font-weight-bold text-primary" name="payable_amount" value="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-5 py-2 font-weight-bold shadow-sm">
                        <i class="mdi mdi-content-save mr-1"></i> Save Tariff Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Sub-Modals --}}
@include('admin.tariffs.modal-export')
@include('admin.tariffs.modal-import')
@include('admin.tariffs.modal-normalize')

<style>
    .bg-white-transparent { background: rgba(255,255,255,0.2); }
    /* Force results to be scrollable in modals */
    .select2-results__options { max-height: 250px !important; overflow-y: auto !important; }
</style>

@push('modal_scripts')
<script>
    $(function() {
        $('.select2-single').select2({ dropdownParent: $('#tariffModal'), theme: 'bootstrap4' });

        $('#item_type').on('change', function() {
            let type = $(this).val();
            $('#product_select_div').toggle(type === 'product');
            $('#service_select_div').toggle(type === 'service');
        });

        $('#tariffForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $(this).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

            $.ajax({
                url: "{{ route('hmo-tariffs.store') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    $('#tariffModal').modal('hide');
                    toastr.success(res.message);
                    if ($('#axis-item-select').val()) $('#load-config-btn').trigger('click');
                },
                error: function(xhr) { 
                    toastr.error(xhr.responseJSON?.message || 'Error saving tariff'); 
                },
                complete: function() { 
                    $btn.prop('disabled', false).html('<i class="mdi mdi-content-save mr-1"></i> Save Tariff Record');
                }
            });
        });
    });
</script>
@endpush

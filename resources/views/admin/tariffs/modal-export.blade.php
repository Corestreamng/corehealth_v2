<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header bg-info text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white-transparent p-2 mr-3">
                        <i class="mdi mdi-file-export mdi-24px"></i>
                    </div>
                    <h5 class="modal-title font-weight-bold mb-0">Export Tariff Catalog</h5>
                </div>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('hmo-tariffs.export-excel') }}" method="GET">
                <div class="modal-body p-4">
                    <div class="info-callout mb-4 p-2 bg-light border rounded small text-muted">
                        <i class="mdi mdi-information-outline mr-2 text-info"></i>
                        Export your current configuration to Excel for offline auditing or bulk updates.
                    </div>

                    <div class="form-group mb-4">
                        <label class="small font-weight-bold text-muted text-uppercase mb-2">Export Scope</label>
                        <div class="row">
                            <div class="col-12">
                                <select name="scope" class="form-control form-control-lg border-2" id="export-scope">
                                    <option value="all">Full Network (All HMOs & Items)</option>
                                    <option value="hmo">Targeted HMO Focus</option>
                                    <option value="scheme">Scheme Focus</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0 animate-fade-in" id="export-hmo-group" style="display:none;">
                        <label class="small font-weight-bold text-muted text-uppercase mb-2">Select Target HMO</label>
                        <select name="hmo_id" class="form-control select2-modal" style="width: 100%;">
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
                        <p class="smallest text-muted mt-2">Only tariffs explicitly mapped to this HMO will be exported.</p>
                    </div>

                    <div class="form-group mb-0 animate-fade-in" id="export-scheme-group" style="display:none;">
                        <label class="small font-weight-bold text-muted text-uppercase mb-2">Select Target Scheme</label>
                        <select name="scheme_id" class="form-control select2-modal" style="width: 100%;">
                            @foreach($schemes as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
                        </select>
                        <p class="smallest text-muted mt-2">Exports all items for all HMOs within this scheme.</p>
                    </div>

                    <div class="form-group mt-4 mb-0">
                        <label class="small font-weight-bold text-muted text-uppercase mb-2">Data Layout</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="layout-standard" name="layout" value="standard" class="custom-control-input" checked>
                                    <label class="custom-control-label small font-weight-bold" for="layout-standard">Standard List</label>
                                </div>
                                <div class="smallest text-muted ml-4">One row per HMO-Item pair. Best for simple updates.</div>
                            </div>
                            <div class="col-md-4">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="layout-hmo" name="layout" value="consolidated_hmo" class="custom-control-input">
                                    <label class="custom-control-label small font-weight-bold" for="layout-hmo">Consolidated HMO</label>
                                </div>
                                <div class="smallest text-muted ml-4">One row per Item. HMOs as columns. Great for comparisons.</div>
                            </div>
                            <div class="col-md-4">
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="layout-scheme" name="layout" value="consolidated_scheme" class="custom-control-input">
                                    <label class="custom-control-label small font-weight-bold" for="layout-scheme">Consolidated Scheme</label>
                                </div>
                                <div class="smallest text-muted ml-4">One row per Item. Schemes as columns. Managed by network focus.</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Item Classification</label>
                                <select name="type" class="form-control border-2" id="export-type">
                                    <option value="">All Items</option>
                                    <option value="product">Products Only</option>
                                    <option value="service">Services Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4" id="product-cat-group" style="display:none;">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Product Category</label>
                                <select name="product_category_id" class="form-control select2-modal" style="width: 100%;">
                                    <option value="">All Categories</option>
                                    @foreach($productCategories as $pc) <option value="{{ $pc->id }}">{{ $pc->category_name }}</option> @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-4" id="service-cat-group" style="display:none;">
                                <label class="small font-weight-bold text-muted text-uppercase mb-2">Service Category</label>
                                <select name="service_category_id" class="form-control select2-modal" style="width: 100%;">
                                    <option value="">All Categories</option>
                                    @foreach($serviceCategories as $sc) <option value="{{ $sc->id }}">{{ $sc->category_name }}</option> @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info px-4 py-2 font-weight-bold shadow-sm">
                        <i class="mdi mdi-microsoft-excel mr-1"></i> Generate Excel Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .bg-white-transparent { background: rgba(255,255,255,0.2); }
    /* Force results to be scrollable in modals */
    .select2-results__options { max-height: 250px !important; overflow-y: auto !important; }
</style>

@push('modal_scripts')
<script>
    $(function() {
        $('#export-scope').on('change', function() {
            const val = $(this).val();
            $('#export-hmo-group').toggle(val === 'hmo');
            $('#export-scheme-group').toggle(val === 'scheme');
            
            // Re-init select2 if visible
            if (val !== 'all') {
                $('.select2-modal').select2({
                    dropdownParent: $('#exportModal'),
                    theme: 'bootstrap4'
                });
            }
        });
        $('#export-type').on('change', function() {
            const val = $(this).val();
            $('#product-cat-group').toggle(val === 'product');
            $('#service-cat-group').toggle(val === 'service');
            
            if (val) {
                $('.select2-modal').select2({ dropdownParent: $('#exportModal'), theme: 'bootstrap4' });
            }
        });
    });
</script>
@endpush

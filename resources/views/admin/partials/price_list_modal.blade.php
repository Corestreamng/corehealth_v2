<div class="modal fade" id="workbench-price-list-modal" tabindex="-1" role="dialog" aria-labelledby="priceListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="priceListModalLabel">
                    <i class="mdi mdi-currency-usd"></i> Unified Price List
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                @php
                    $productCategories = \App\Models\ProductCategory::where('status', 1)->orderBy('category_name')->get();
                    $serviceCategories = \App\Models\ServiceCategory::where('status', 1)->orderBy('category_name')->get();
                @endphp
                <ul class="nav nav-tabs" id="price-list-tabs" role="tablist" style="background: #f8f9fa; padding: 0.5rem 1rem 0 1rem;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pl-products-tab-btn" data-bs-toggle="tab" data-bs-target="#pl-products-tab" type="button" role="tab" aria-selected="true" style="font-weight: 500;">
                            <i class="mdi mdi-package-variant"></i> Products
                        </button>
                    </li>
                    @if(!isset($products_only) || !$products_only)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pl-services-tab-btn" data-bs-toggle="tab" data-bs-target="#pl-services-tab" type="button" role="tab" aria-selected="false" style="font-weight: 500;">
                            <i class="mdi mdi-medical-bag"></i> Services
                        </button>
                    </li>
                    @endif
                </ul>

                <div class="tab-content p-3" id="price-list-tab-content">
                    <!-- Products Tab -->
                    <div class="tab-pane fade show active" id="pl-products-tab" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="small text-muted mb-1">Category</label>
                                <select class="form-select form-control-sm" id="filter_product_category">
                                    <option value="">All Categories</option>
                                    @foreach($productCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small text-muted mb-1">Product Type</label>
                                <select class="form-select form-control-sm" id="filter_product_type">
                                    <option value="">All Types</option>
                                    <option value="drug">Drug</option>
                                    <option value="consumable">Consumable</option>
                                    <option value="utility">Utility</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped w-100" id="pl-products-table">
                                <thead>
                                    <tr>
                                        <th>Item Details</th>
                                        <th width="150">Pricing</th>
                                        <th width="150">HMO Tariffs</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    @if(!isset($products_only) || !$products_only)
                    <!-- Services Tab -->
                    <div class="tab-pane fade" id="pl-services-tab" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="small text-muted mb-1">Category</label>
                                <select class="form-select form-control-sm" id="filter_service_category">
                                    <option value="">All Categories</option>
                                    @foreach($serviceCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small text-muted mb-1">Special Filter</label>
                                <select class="form-select form-control-sm" id="filter_service_type">
                                    <option value="">All Services</option>
                                    <option value="lab">Investigations/Labs</option>
                                    <option value="imaging">Imaging</option>
                                    <option value="procedure">Procedures</option>
                                    <option value="combo">Combo/Bundles</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped w-100" id="pl-services-table">
                                <thead>
                                    <tr>
                                        <th>Service Details</th>
                                        <th width="150">Pricing</th>
                                        <th width="150">HMO Tariffs</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    td.details-control {
        background: url('{{ asset("images/details_open.png") }}') no-repeat center center;
        cursor: pointer;
    }
    tr.shown td.details-control {
        background: url('{{ asset("images/details_close.png") }}') no-repeat center center;
    }
    .tariff-child-row {
        background-color: #f8f9fa !important;
        padding: 1rem !important;
        border-left: 4px solid {{ appsettings('hos_color', '#007bff') }};
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let productsTable, servicesTable;

    function formatTariffDetails(data) {
        if (!data || (!data.schemeSummary.length && !data.standaloneData.length)) {
            return '<div class="alert alert-info py-2 m-0"><i class="mdi mdi-information"></i> No HMO tariffs configured for this item.</div>';
        }

        let html = '<div class="tariff-details-container p-2" style="max-height: 400px; overflow-y: auto;">';
        
        if (data.schemeSummary.length > 0) {
            data.schemeSummary.forEach(scheme => {
                html += '<h6 class="mt-2 mb-2 font-weight-bold" style="color: #495057;">' + scheme.name + '</h6>';
                html += '<div class="table-responsive"><table class="table table-sm table-bordered bg-white">';
                html += '<thead class="bg-light"><tr><th>HMO</th><th>Payable</th><th>Claims</th><th>Mode</th></tr></thead><tbody>';
                
                scheme.hmos.forEach(hmo => {
                    let payable = hmo.has_tariff ? '₦' + parseFloat(hmo.payable_amount).toFixed(2) : '<span class="text-muted">Not Set</span>';
                    let claims = hmo.has_tariff ? '₦' + parseFloat(hmo.claims_amount).toFixed(2) : '<span class="text-muted">Not Set</span>';
                    let modeBadge = hmo.coverage_mode === 'primary' ? '<span class="badge bg-primary">Primary</span>' : '<span class="badge bg-secondary">Secondary</span>';
                    
                    html += '<tr>';
                    html += '<td>' + hmo.name + '</td>';
                    html += '<td class="text-success font-weight-bold">' + payable + '</td>';
                    html += '<td class="text-info font-weight-bold">' + claims + '</td>';
                    html += '<td>' + modeBadge + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            });
        }

        if (data.standaloneData.length > 0) {
            html += '<h6 class="mt-3 mb-2 font-weight-bold" style="color: #495057;">Standalone HMOs</h6>';
            html += '<div class="table-responsive"><table class="table table-sm table-bordered bg-white">';
            html += '<thead class="bg-light"><tr><th>HMO</th><th>Payable</th><th>Claims</th><th>Mode</th></tr></thead><tbody>';
            
            data.standaloneData.forEach(hmo => {
                let payable = hmo.has_tariff ? '₦' + parseFloat(hmo.payable_amount).toFixed(2) : '<span class="text-muted">Not Set</span>';
                let claims = hmo.has_tariff ? '₦' + parseFloat(hmo.claims_amount).toFixed(2) : '<span class="text-muted">Not Set</span>';
                let modeBadge = hmo.coverage_mode === 'primary' ? '<span class="badge bg-primary">Primary</span>' : '<span class="badge bg-secondary">Secondary</span>';
                
                html += '<tr>';
                html += '<td>' + hmo.name + '</td>';
                html += '<td class="text-success font-weight-bold">' + payable + '</td>';
                html += '<td class="text-info font-weight-bold">' + claims + '</td>';
                html += '<td>' + modeBadge + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
        }

        html += '</div>';
        return html;
    }

    $('#workbench-price-list-modal').on('shown.bs.modal', function () {
        if (!productsTable) {
            productsTable = $('#pl-products-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("workbench.price-list.products") }}',
                    data: function(d) {
                        d.category_id = $('#filter_product_category').val();
                        d.product_type = $('#filter_product_type').val();
                    }
                },
                columns: [
                    { data: 'item_details', name: 'product_name' },
                    { data: 'base_pricing', name: 'base_pricing', orderable: false, searchable: false },
                    { data: 'tariff_action', name: 'tariff_action', orderable: false, searchable: false }
                ]
            });

            $('#filter_product_category, #filter_product_type').on('change', function() {
                productsTable.ajax.reload();
            });

            $('#pl-products-table tbody').on('click', 'button.view-tariffs-btn', function () {
                let tr = $(this).closest('tr');
                let row = productsTable.row(tr);
                let id = $(this).data('id');
                let type = $(this).data('type');

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    $(this).html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
                    let btn = $(this);
                    
                    $.get('{{ route("workbench.price-list.tariffs") }}', { id: id, type: type }, function(data) {
                        row.child($(formatTariffDetails(data))).show();
                        tr.addClass('shown');
                        btn.html('<i class="mdi mdi-chevron-up"></i> Hide Tariffs');
                    });
                }
            });
        }
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'pl-services-tab-btn' && !servicesTable) {
            servicesTable = $('#pl-services-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("workbench.price-list.services") }}',
                    data: function(d) {
                        d.category_id = $('#filter_service_category').val();
                        d.service_type = $('#filter_service_type').val();
                    }
                },
                columns: [
                    { data: 'item_details', name: 'service_name' },
                    { data: 'base_pricing', name: 'base_pricing', orderable: false, searchable: false },
                    { data: 'tariff_action', name: 'tariff_action', orderable: false, searchable: false }
                ]
            });

            $('#filter_service_category, #filter_service_type').on('change', function() {
                servicesTable.ajax.reload();
            });

            $('#pl-services-table tbody').on('click', 'button.view-tariffs-btn', function () {
                let tr = $(this).closest('tr');
                let row = servicesTable.row(tr);
                let id = $(this).data('id');
                let type = $(this).data('type');

                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    $(this).html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
                    let btn = $(this);
                    
                    $.get('{{ route("workbench.price-list.tariffs") }}', { id: id, type: type }, function(data) {
                        row.child($(formatTariffDetails(data))).show();
                        tr.addClass('shown');
                        btn.html('<i class="mdi mdi-chevron-up"></i> Hide Tariffs');
                    });
                }
            });
        }
    });
});
</script>

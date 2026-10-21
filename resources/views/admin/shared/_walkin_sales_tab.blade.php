@php
    $appStatus = appsettings();
    $allowLab = (bool) ($appStatus->walkin_lab_enabled ?? true);
    $allowImaging = (bool) ($appStatus->walkin_imaging_enabled ?? true);
    $allowPharmacy = (bool) ($appStatus->walkin_products_enabled ?? true);
    $allowProcedure = (bool) ($appStatus->walkin_procedures_enabled ?? true);
    $allowFreeForm = (bool) ($appStatus->walkin_free_form_enabled ?? true);
    $activeSubtab = $allowLab ? 'lab' : ($allowImaging ? 'imaging' : ($allowPharmacy ? 'product' : ($allowProcedure ? 'procedure' : ($allowFreeForm ? 'free_form' : ''))));
@endphp

<div class="walkin-tab-content p-3">
    <div class="row">
        <div class="col-lg-7 col-md-12 mb-3">
            <div class="card-modern shadow-sm border">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                    <ul class="nav nav-pills card-header-pills" id="walkin-subtabs">
                        @if($allowLab)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeSubtab === 'lab' ? 'active' : '' }}" href="#walkin-catalog-pane" data-toggle="pill" data-type="lab">
                                <i class="mdi mdi-test-tube"></i> Lab
                            </a>
                        </li>
                        @endif
                        @if($allowImaging)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeSubtab === 'imaging' ? 'active' : '' }}" href="#walkin-catalog-pane" data-toggle="pill" data-type="imaging">
                                <i class="mdi mdi-radiology-box"></i> Imaging
                            </a>
                        </li>
                        @endif
                        @if($allowPharmacy)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeSubtab === 'product' ? 'active' : '' }}" href="#walkin-catalog-pane" data-toggle="pill" data-type="product">
                                <i class="mdi mdi-pill"></i> Products
                            </a>
                        </li>
                        @endif
                        @if($allowProcedure)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeSubtab === 'procedure' ? 'active' : '' }}" href="#walkin-catalog-pane" data-toggle="pill" data-type="procedure">
                                <i class="mdi mdi-needle"></i> Procedures
                            </a>
                        </li>
                        @endif
                        @if($allowFreeForm)
                        <li class="nav-item">
                            <a class="nav-link {{ $activeSubtab === 'free_form' ? 'active' : '' }}" href="#walkin-freeform-pane" data-toggle="pill" data-type="free_form">
                                <i class="mdi mdi-playlist-edit"></i> Custom / Free-Form
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
                <div class="card-body p-3">
                    <div class="tab-content" id="walkin-subtabs-content">
                        <!-- Catalog Search Pane -->
                        <div class="tab-pane fade {{ $activeSubtab !== 'free_form' ? 'show active' : '' }}" id="walkin-catalog-pane">
                            <div class="input-group input-group-sm mb-3">
                                <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                <input type="text" class="form-control" id="walkin-search" placeholder="Type name or code to search catalog...">
                                <button class="btn btn-outline-secondary" type="button" id="btn-clear-walkin-search">Clear</button>
                            </div>
                            <div class="walkin-search-results border rounded p-2" id="walkin-search-results" style="max-height: 380px; min-height: 200px; overflow-y: auto;">
                                <p class="text-muted text-center py-4"><i class="mdi mdi-magnify"></i> Start typing to search services and products...</p>
                            </div>
                        </div>

                        <!-- Free-Form Item Pane -->
                        @if($allowFreeForm)
                        <div class="tab-pane fade {{ $activeSubtab === 'free_form' ? 'show active' : '' }}" id="walkin-freeform-pane">
                            <div class="alert alert-info py-2 px-3 mb-3 small">
                                <i class="mdi mdi-information-outline"></i>
                                Add an unlisted / ad-hoc item or service directly to the walk-in sales cart. Specify the price and whether it is billable.
                            </div>
                            <form id="walkin-freeform-form">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Item / Service Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="walkin-freeform-name" placeholder="e.g., Special Dressing Procedure or Custom Splint" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Description / Clinical Notes (Optional)</label>
                                    <textarea class="form-control form-control-sm" id="walkin-freeform-desc" rows="2" placeholder="Additional details or justification..."></textarea>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Unit Price (₦) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="walkin-freeform-price" placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" step="1" min="1" class="form-control form-control-sm" id="walkin-freeform-qty" value="1" required>
                                    </div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="walkin-freeform-billable" checked>
                                    <label class="form-check-label small fw-bold" for="walkin-freeform-billable">
                                        Is Billable (charge patient for this request)
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="mdi mdi-cart-plus"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-md-12">
            <div class="card-modern shadow-sm border">
                <div class="card-header p-0 bg-light">
                    <ul class="nav nav-tabs nav-fill" id="walkin-cart-tabs">
                        <li class="nav-item">
                            <a class="nav-link active py-2" data-toggle="tab" href="#walkin-cart-pane">
                                <i class="mdi mdi-cart"></i> Cart
                                <span class="badge bg-primary text-white ml-1" id="cart-count-badge">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2" data-toggle="tab" href="#walkin-recent-pane">
                                <i class="mdi mdi-clock-outline"></i> Recent (24h)
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="walkin-cart-pane">
                        <div class="card-body p-0" style="max-height: 280px; min-height: 180px; overflow-y: auto;">
                            <table class="table table-sm mb-0" id="walkin-cart-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end text-success">HMO</th>
                                        <th class="text-end text-primary">Payable</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="walkin-cart-body">
                                    <tr id="walkin-cart-empty">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="mdi mdi-cart-outline font-24"></i>
                                            <p class="mb-0 mt-2 font-12">No items selected</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="border-top p-2 bg-light">
                            <table class="table table-sm mb-2" id="walkin-summary-table">
                                <tbody>
                                    <tr>
                                        <td><strong>Subtotal:</strong></td>
                                        <td class="text-end"><strong id="walkin-subtotal">₦0.00</strong></td>
                                    </tr>
                                    <tr id="walkin-hmo-row" style="display: none;" class="text-success">
                                        <td>
                                            <i class="mdi mdi-shield-check"></i> <strong>HMO Coverage:</strong>
                                            <small class="d-block" id="walkin-hmo-name"></small>
                                        </td>
                                        <td class="text-end"><strong id="walkin-hmo-amount">-₦0.00</strong></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong class="font-14">Total Payable:</strong></td>
                                        <td class="text-end"><strong class="font-14" id="walkin-cart-total">₦0.00</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button class="btn btn-success btn-lg w-100" id="btn-submit-walkin" disabled>
                                <i class="mdi mdi-cart-check"></i> Checkout & Pay (Walk-in)
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="walkin-recent-pane">
                        <div class="card-body p-2" style="max-height: 380px; overflow-y: auto;">
                            <div class="alert alert-info py-1 px-2 mb-2 font-11">
                                <i class="mdi mdi-information"></i> Walk-in requests created in the last 24 hours for this patient
                            </div>
                            <div id="recent-requests-container">
                                <div class="text-center text-muted py-4">
                                    <i class="mdi mdi-clock-outline font-24"></i>
                                    <p class="mb-0 mt-2 font-12">No recent requests</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

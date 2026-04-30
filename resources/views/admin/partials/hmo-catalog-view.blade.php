<div class="tariff-canvas-inner animate-fade-in">
    {{-- Enhanced Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 bg-transparent p-0">
                    <li class="breadcrumb-item small"><a href="{{ $backUrl ?? '#' }}">Tariffs</a></li>
                    <li class="breadcrumb-item small active">Edit {{ ucfirst($targetType) }} Catalog</li>
                </ol>
            </nav>
            <h2 class="h3 font-weight-bold text-gray-900 mb-0">
                <i class="mdi mdi-building-outline text-primary mr-2"></i>{{ $targetName }}
            </h2>
            <p class="text-muted small">
                Configuring: <span class="font-weight-bold text-dark">{{ count($products) }} Products, {{ count($services) }} Services</span>
                <span class="mx-2 text-gray-300">|</span>
                Type: <span class="badge badge-soft-info text-uppercase">{{ $targetType }}</span>
            </p>
        </div>
        <div class="text-right">
            <button class="btn btn-primary btn-sm px-4 shadow-sm" id="bulkEditToggle">
                <i class="mdi mdi-layers-triple mr-1"></i> Bulk Edit Tools
            </button>
        </div>
    </div>

    {{-- Toolbar & Bulk Bar --}}
    <div class="sticky-top-toolbar" style="top: 0; z-index: 100; background: rgba(245,245,245,0.9); backdrop-filter: blur(8px); padding: 10px 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <div class="input-group mr-3 shadow-sm" style="width: 250px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0"><i class="mdi mdi-magnify text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control border-left-0" id="catalogSearch" placeholder="Filter items or categories...">
                </div>

                {{-- Smart Sync Global Toggle --}}
                <div class="sync-global-toggle bg-white border rounded-pill px-3 py-1 shadow-sm d-flex align-items-center mr-3">
                    <span class="small font-weight-bold text-muted mr-2">Smart Sync:</span>
                    <div class="custom-control custom-switch" title="When ON, changing one field balances the other against the item's base price.">
                        <input type="checkbox" class="custom-control-input" id="globalSyncToggle" checked>
                        <label class="custom-control-label cursor-pointer" for="globalSyncToggle"></label>
                    </div>
                </div>

                <div class="badge badge-soft-secondary px-3 py-2" style="font-size: 0.85rem;">
                    Total: <span id="visibleCount" class="font-weight-bold">{{ count($products) + count($services) }}</span> Items
                </div>
            </div>
            <div id="saveAllContainer" class="d-none animate-slide-in">
                <div class="d-flex align-items-center">
                    <div class="text-right mr-3 line-height-1">
                        <div class="small font-weight-bold text-success" id="impactSummary">0 items to update</div>
                        <div class="smallest text-muted">Review changes before saving</div>
                    </div>
                    <button class="btn btn-success shadow-sm px-4 btn-lg" id="saveAllBtn">
                        <i class="mdi mdi-content-save-all mr-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>

        {{-- Unified Bulk Update Bar --}}
        @include('admin.partials.hmo-bulk-edit-bar', [
            'title' => 'Catalog Bulk Edit Tool',
            'subtitle' => 'Perform network-wide pricing updates.',
            'entityName' => 'Items',
            'defaultRefPrice' => '',
            'refPlaceholder' => 'Use Item Base Price',
            'refHint' => 'Leave blank to use each item\'s own base price.',
            'scopeOptions' => [
                'visible' => 'Expanded Groups Only',
                'pharmacy' => 'All Products',
                'services' => 'All Services',
                'empty' => 'Unconfigured Items Only'
            ]
        ])
    </div>

    {{-- Catalog Sub-Tabs (Clinical vs Pharmacy) --}}
    <ul class="nav nav-tabs nav-tabs-custom mb-4 border-bottom" id="catalogTypeTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active font-weight-bold py-3 px-4 border-0" id="services-type-tab" data-bs-toggle="tab" href="#services-pane" role="tab">
                <i class="mdi mdi-stethoscope mr-2 text-primary"></i> Clinical Services
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold py-3 px-4 border-0" id="products-type-tab" data-bs-toggle="tab" href="#products-pane" role="tab">
                <i class="mdi mdi-pills mr-2 text-success"></i> Pharmacy & Products
            </a>
        </li>
    </ul>

    <div class="tab-content" id="catalogTypeTabContent">
        {{-- Clinical Services Pane --}}
        <div class="tab-pane fade show active" id="services-pane" role="tabpanel">
            <div id="servicesAccordion">
                @php
                    $serviceGroups = collect($services)->groupBy('category');
                @endphp
                @foreach ($serviceGroups as $catName => $items)
                    <div class="scheme-modern-group mb-3 {{ $loop->first ? 'open' : '' }}" data-category="{{ strtolower($catName) }}">
                        <div class="scheme-modern-header d-flex align-items-center justify-content-between p-3 bg-white border rounded-lg cursor-pointer hover-bg-light transition-all">
                            <div class="d-flex align-items-center">
                                <i class="mdi mdi-chevron-down chevron mr-3 text-primary"></i>
                                <div class="d-flex align-items-center">
                                    <h6 class="mb-0 font-weight-bold text-gray-800">{{ $catName ?: 'General Services' }}</h6>
                                    <span class="badge badge-soft-secondary ml-3 smallest">{{ count($items) }} services</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bulk-active-indicator d-none mr-3 animate-pulse">
                                    <span class="badge badge-soft-primary px-2 py-1"><i class="mdi mdi-target mr-1"></i> In Bulk Scope</span>
                                </div>
                                <div class="scheme-stats-pill d-none">
                                    <span class="badge badge-pill badge-soft-success">Modified</span>
                                </div>
                            </div>
                        </div>
                        <div class="scheme-modern-body mt-2 border rounded-lg overflow-hidden bg-white shadow-sm">
                            @include('admin.partials.hmo-catalog-table-content', ['items' => $items, 'type' => 'service'])
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pharmacy Pane --}}
        <div class="tab-pane fade" id="products-pane" role="tabpanel">
            <div id="productsAccordion">
                @php
                    $productGroups = collect($products)->groupBy('category');
                @endphp
                @foreach ($productGroups as $catName => $items)
                    <div class="scheme-modern-group mb-3 {{ $loop->first ? 'open' : '' }}" data-category="{{ strtolower($catName) }}">
                        <div class="scheme-modern-header d-flex align-items-center justify-content-between p-3 bg-white border rounded-lg cursor-pointer hover-bg-light transition-all">
                            <div class="d-flex align-items-center">
                                <i class="mdi mdi-chevron-down chevron mr-3 text-success"></i>
                                <div class="d-flex align-items-center">
                                    <h6 class="mb-0 font-weight-bold text-gray-800">{{ $catName ?: 'Pharmacy Items' }}</h6>
                                    <span class="badge badge-soft-secondary ml-3 smallest">{{ count($items) }} products</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bulk-active-indicator d-none mr-3 animate-pulse">
                                    <span class="badge badge-soft-primary px-2 py-1"><i class="mdi mdi-target mr-1"></i> In Bulk Scope</span>
                                </div>
                                <div class="scheme-stats-pill d-none">
                                    <span class="badge badge-pill badge-soft-success">Modified</span>
                                </div>
                            </div>
                        </div>
                        <div class="scheme-modern-body mt-2 border rounded-lg overflow-hidden bg-white shadow-sm">
                            @include('admin.partials.hmo-catalog-table-content', ['items' => $items, 'type' => 'product'])
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Save Confirmation Modal --}}
<div class="modal fade" id="saveSummaryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl overflow-hidden">
            <div class="modal-header bg-light border-0 py-3">
                <div class="modal-title d-flex align-items-center">
                    <div class="rounded-circle bg-success-soft p-2 mr-3">
                        <i class="mdi mdi-check-all text-success mdi-24px"></i>
                    </div>
                    <div>
                        <h5 class="font-weight-bold text-gray-900 mb-0">Commit Catalog Changes</h5>
                        <p class="text-muted small mb-0">Review the impact across your items</p>
                    </div>
                </div>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex border-bottom bg-white flex-wrap">
                    <div class="flex-grow-1 p-4 border-right">
                        <h6 class="text-uppercase small font-weight-bold text-muted mb-3">Impact Breakdown</h6>
                        <div id="impactDetailsList" class="impact-list"></div>
                    </div>
                    <div class="p-4 bg-light" style="width: 300px;">
                        <h6 class="text-uppercase small font-weight-bold text-muted mb-3">Change Summary</h6>
                        <div id="globalSummaryData">
                            <div class="summary-card mb-3">
                                <div class="text-muted smallest">Total Items Updated</div>
                                <div class="h4 font-weight-bold mb-0 text-primary" id="sumTotalHmos">0</div>
                            </div>
                            <div class="summary-card mb-3">
                                <div class="text-muted smallest">Price Divergences</div>
                                <div class="h4 font-weight-bold mb-0 text-warning" id="sumDivergentHmos">0</div>
                            </div>
                            <div class="summary-card">
                                <div class="text-muted smallest">Coverage Updates</div>
                                <div class="h4 font-weight-bold mb-0 text-info" id="sumCoverageHmos">0</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white text-center">
                    <div class="alert alert-soft-warning mb-0 small">
                        <i class="mdi mdi-alert-circle-outline mr-2"></i> These changes will be permanently applied to the HMO tariff catalog.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link text-muted font-weight-bold" data-bs-dismiss="modal">Back to Editor</button>
                <button type="button" class="btn btn-success px-5 py-2 font-weight-bold shadow-sm" id="confirmFinalSave">
                    Confirm & Apply Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .smallest { font-size: 0.75rem; }
    .impact-list { max-height: 400px; overflow-y: auto; }
    .impact-scheme-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
    .impact-scheme-header { background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .impact-hmo-list { padding: 5px 15px; }
    .impact-hmo-item { font-size: 0.75rem; padding: 4px 0; border-bottom: 1px dotted #e2e8f0; display: flex; justify-content: space-between; }
    
    .step-badge { display: inline-block; padding: 2px 10px; border-radius: 4px; background: #ebf8ff; color: #3182ce; font-size: 0.65rem; font-weight: 800; text-uppercase: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .bg-success-soft { background-color: rgba(72, 187, 120, 0.1); }
    
    .scheme-modern-group .scheme-modern-body { display: none; }
    .scheme-modern-group.open .scheme-modern-body { display: block; }
    .scheme-modern-group.open .chevron { transform: rotate(0deg); }
    .scheme-modern-group:not(.open) .chevron { transform: rotate(-90deg); }
    .chevron { transition: transform 0.2s ease; }

    .bulk-bar-refined { border-top: 5px solid #3182ce !important; transition: all 0.3s ease; }
    .bulk-bar-refined[data-mode="percent"] .fixed-group { display: none !important; }
    .bulk-bar-refined[data-mode="fixed"] .pct-group { display: none !important; }
    
    .animate-pulse { animation: pulse 2s infinite; }
    @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
    .hover-bg-light:hover { background-color: #f8fafc !important; }
    
    .nav-tabs-custom .nav-link { color: #718096; border-radius: 0; border-bottom: 3px solid transparent !important; }
    .nav-tabs-custom .nav-link.active { color: #3182ce; border-bottom: 3px solid #3182ce !important; background: transparent; }
</style>

@if(request()->ajax())
    @include('admin.partials.hmo-catalog-scripts', ['hmoIds' => $hmoIds])
@else
    @push('scripts')
        @include('admin.partials.hmo-catalog-scripts', ['hmoIds' => $hmoIds])
    @endpush
@endif

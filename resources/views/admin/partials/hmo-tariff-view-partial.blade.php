<div class="tariff-canvas-inner animate-fade-in">
    {{-- Enhanced Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 bg-transparent p-0">
                    <li class="breadcrumb-item small"><a href="{{ $backUrl }}">Tariffs</a></li>
                    <li class="breadcrumb-item small active">Edit {{ ucfirst($itemType) }}</li>
                </ol>
            </nav>
            <h2 class="h3 font-weight-bold text-gray-900 mb-0">
                <i class="mdi {{ $itemType === 'product' ? 'mdi-pills' : 'mdi-stethoscope' }} text-primary mr-2"></i>{{ $itemName }}
            </h2>
            <p class="text-muted small">
                Base Hospital Price: <span class="font-weight-bold text-dark">&#8358;{{ number_format($salePrice, 2) }}</span>
                <span class="mx-2 text-gray-300">|</span>
                Auditing: <span class="font-weight-bold text-dark">{{ $totalCount }} HMO Entities</span>
            </p>
        </div>
        <div class="text-right">
            <button class="btn btn-primary btn-sm px-4 shadow-sm" id="bulkEditToggle">
                <i class="mdi mdi-layers-triple mr-1"></i> Bulk Edit Tools
            </button>
        </div>
    </div>

    {{-- Tabs Navigation --}}
    <ul class="nav nav-pills nav-pills-custom mb-4 bg-white p-1 rounded-pill shadow-sm d-inline-flex border" id="tariffViewTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active rounded-pill px-4" id="grouped-tab" data-bs-toggle="pill" href="#grouped-view" role="tab">
                <i class="mdi mdi-format-list-bulleted-type mr-1"></i> Grouped View
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-4" id="flat-tab" data-bs-toggle="pill" href="#flat-view" role="tab">
                <i class="mdi mdi-format-list-bulleted mr-1"></i> Flat List (A-Z)
            </a>
        </li>
    </ul>

    {{-- Toolbar & Bulk Bar --}}
    <div class="sticky-top-toolbar" style="top: 0; z-index: 100; background: rgba(245,245,245,0.9); backdrop-filter: blur(8px); padding: 10px 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <div class="input-group mr-3 shadow-sm" style="width: 250px;">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0"><i class="mdi mdi-magnify text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control border-left-0" id="tariffSearch" placeholder="Filter HMOs or Schemes...">
                </div>
                
                <div class="sync-global-toggle bg-white border rounded-pill px-3 py-1 shadow-sm d-flex align-items-center mr-3">
                    <span class="small font-weight-bold text-muted mr-2">Smart Sync:</span>
                    <div class="custom-control custom-switch" title="When ON, changing one field balances the other against the base price.">
                        <input type="checkbox" class="custom-control-input" id="globalSyncToggle" checked>
                        <label class="custom-control-label cursor-pointer" for="globalSyncToggle"></label>
                    </div>
                </div>

                <div class="badge badge-soft-secondary px-3 py-2" style="font-size: 0.85rem;">
                    Total: <span class="font-weight-bold">{{ $totalCount }}</span> HMOs
                </div>
            </div>
            <div id="saveAllContainer" class="d-none animate-slide-in">
                <div class="d-flex align-items-center">
                    <div class="text-right mr-3 line-height-1">
                        <div class="small font-weight-bold text-success" id="impactSummary">0 HMOs to update</div>
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
            'title' => 'Network Bulk Edit Tool',
            'subtitle' => 'Perform large-scale pricing updates across your HMO network.',
            'entityName' => 'HMOs',
            'defaultRefPrice' => $salePrice,
            'showPreview' => true,
            'scopeOptions' => [
                'visible' => 'Expanded Groups Only',
                'empty' => 'Unconfigured Items',
                'all' => 'Global (All HMOs)'
            ]
        ])
    </div>

    <div class="tab-content" id="tariffTabContent">
        {{-- Grouped View --}}
        <div class="tab-pane fade show active" id="grouped-view" role="tabpanel">
            <div id="schemeAccordion">
                @foreach($schemeSummary as $index => $scheme)
                <div class="scheme-modern-group mb-3 {{ $index === 0 ? 'open' : '' }}" data-scheme-name="{{ strtolower($scheme['name']) }}">
                    <div class="scheme-modern-header d-flex align-items-center justify-content-between p-3 bg-white border rounded-lg cursor-pointer hover-bg-light transition-all">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-chevron-down chevron mr-3 text-primary"></i>
                            <div class="d-flex align-items-center">
                                <h6 class="mb-0 font-weight-bold text-gray-800">{{ $scheme['name'] }}</h6>
                                <span class="badge badge-soft-secondary ml-3 smallest">{{ $scheme['hmo_count'] }} HMOs</span>
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
                        @include('admin.partials.hmo-tariff-table', ['rows' => $scheme['hmos'], 'schemeName' => $scheme['name']])
                    </div>
                </div>
                @endforeach
                @if(!empty($standaloneData))
                <div class="scheme-modern-group mb-3" data-scheme-name="standalone">
                    <div class="scheme-modern-header d-flex align-items-center justify-content-between p-3 bg-white border rounded-lg cursor-pointer hover-bg-light transition-all">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-chevron-down chevron mr-3 text-primary"></i>
                            <div>
                                <h6 class="mb-0 font-weight-bold text-gray-800">Unassigned HMOs</h6>
                                <span class="badge badge-soft-secondary ml-3 smallest">{{ count($standaloneData) }} Entities</span>
                            </div>
                        </div>
                        <div class="bulk-active-indicator d-none mr-3 animate-pulse">
                            <span class="badge badge-soft-primary px-2 py-1"><i class="mdi mdi-target mr-1"></i> In Bulk Scope</span>
                        </div>
                    </div>
                    <div class="scheme-modern-body mt-2 border rounded-lg overflow-hidden bg-white shadow-sm">
                        @include('admin.partials.hmo-tariff-table', ['rows' => $standaloneData, 'schemeName' => 'Standalone'])
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Flat View --}}
        <div class="tab-pane fade" id="flat-view" role="tabpanel">
            <div class="bg-white border rounded-xl overflow-hidden shadow-sm">
                @php
                    $allRows = collect();
                    foreach($schemeSummary as $scheme) { foreach($scheme['hmos'] as $hmo) { $allRows->push(array_merge($hmo, ['scheme_name' => $scheme['name']])); } }
                    foreach($standaloneData as $hmo) { $allRows->push(array_merge($hmo, ['scheme_name' => 'Standalone'])); }
                    $sortedRows = $allRows->sortBy('name')->values()->toArray();
                @endphp
                @include('admin.partials.hmo-tariff-table', ['rows' => $sortedRows, 'schemeName' => ''])
            </div>
        </div>
    </div>
</div>

{{-- Enhanced Save Confirmation Modal --}}
<div class="modal fade" id="saveSummaryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg rounded-xl overflow-hidden">
            <div class="modal-header bg-light border-0 py-3">
                <div class="modal-title d-flex align-items-center">
                    <div class="rounded-circle bg-success-soft p-2 mr-3">
                        <i class="mdi mdi-check-all text-success mdi-24px"></i>
                    </div>
                    <div>
                        <h5 class="font-weight-bold text-gray-900 mb-0">Commit Tariff Changes</h5>
                        <p class="text-muted small mb-0">Review the impact across your HMO network</p>
                    </div>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
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
                                <div class="text-muted smallest">Total Entities</div>
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
                <div class="p-3 bg-white">
                    <div class="alert alert-soft-warning mb-0 small d-flex align-items-center">
                        <i class="mdi mdi-alert-circle-outline mr-2 mdi-18px"></i>
                        Confirming will immediately update the claimable amounts for these HMOs. This action is audited.
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
    .line-height-1 { line-height: 1.2; }
    .impact-list { max-height: 400px; overflow-y: auto; }
    .impact-scheme-card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; overflow: hidden; }
    .impact-scheme-header { background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .impact-hmo-list { padding: 5px 15px; }
    .impact-hmo-item { font-size: 0.75rem; padding: 4px 0; border-bottom: 1px dotted #e2e8f0; display: flex; justify-content: space-between; }
    
    .step-badge { display: inline-block; padding: 2px 10px; border-radius: 4px; background: #ebf8ff; color: #3182ce; font-size: 0.65rem; font-weight: 800; text-uppercase: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    
    .bg-success-soft { background-color: rgba(72, 187, 120, 0.1); }
    .nav-pills-custom .nav-link { color: #718096; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; }
    .nav-pills-custom .nav-link.active { background-color: #ebf8ff; color: #3182ce; box-shadow: 0 2px 4px rgba(66, 153, 225, 0.1); }
    
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
    .pct-group, .fixed-group { animation: fadeIn 0.3s ease; }
    .hover-bg-light:hover { background-color: #f8fafc !important; }
</style>

@if(isset($salePrice))
    @if(request()->ajax())
        @include('admin.partials.hmo-tariff-scripts', ['salePrice' => $salePrice, 'itemType' => $itemType, 'itemId' => $itemId])
    @else
        @push('scripts')
            @include('admin.partials.hmo-tariff-scripts', ['salePrice' => $salePrice, 'itemType' => $itemType, 'itemId' => $itemId])
        @endpush
    @endif
@endif

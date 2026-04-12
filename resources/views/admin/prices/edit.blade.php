@extends('admin.layouts.app')
@section('title', 'Product Price Setting ')
@section('page_name', 'Products ')
@section('subpage_name', 'Edit Product Price Setting')
@section('styles')
<style>
    .tariff-section { transition: all 0.3s ease; }
    .tariff-section .section-toggle { cursor: pointer; user-select: none; }
    .tariff-section .section-toggle:hover { background: #edf2f7 !important; }
    .tariff-section .chevron-icon { transition: transform 0.3s ease; display: inline-block; }
    .tariff-section .chevron-icon.open { transform: rotate(180deg); }
    .scope-option { cursor: pointer; border: 2px solid #dee2e6; border-radius: 10px; padding: 12px 16px; transition: all 0.2s; }
    .scope-option:hover { border-color: #80bdff; background: #f0f7ff; }
    .scope-option.active { border-color: #007bff; background: #e7f1ff; }
    .scope-option .scope-radio { margin-right: 8px; }
    .scheme-card { border: 1px solid #e2e8f0; border-radius: 10px; transition: all 0.2s; overflow: hidden; }
    .scheme-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .scheme-card.selected { border-color: #007bff; background: #f8fbff; }
    .scheme-card .scheme-header { padding: 12px 14px; cursor: pointer; user-select: none; }
    .scheme-card .scheme-hmos { display: none; border-top: 1px solid #e2e8f0; padding: 8px 14px; background: #fafbfc; max-height: 200px; overflow-y: auto; }
    .scheme-card .scheme-hmos.open { display: block; }
    .hmo-item { padding: 5px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.82rem; }
    .hmo-item:last-child { border-bottom: none; }
    .stat-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; }
    .stat-badge.payable { background: #e6f7ee; color: #0d6832; }
    .stat-badge.claims { background: #fff3e0; color: #e65100; }
    .stat-badge.manual { background: #fce4ec; color: #c62828; }
    .stat-badge.auto { background: #e3f2fd; color: #1565c0; }
    .price-hint { font-size: 0.75rem; color: #6c757d; }
    .preview-box { border: 2px dashed #007bff; border-radius: 10px; padding: 16px; background: #f8fbff; }
    .standalone-hmo-list { max-height: 250px; overflow-y: auto; }
    .tariff-range-bar { height: 4px; border-radius: 2px; background: #e9ecef; position: relative; margin: 4px 0; }
    .tariff-range-bar .range-fill { height: 100%; border-radius: 2px; background: linear-gradient(90deg, #28a745, #007bff); position: absolute; left: 0; top: 0; }
    .info-callout { border-left: 4px solid #17a2b8; background: #f0faff; border-radius: 0 8px 8px 0; padding: 10px 14px; font-size: 0.85rem; }
    .warning-callout { border-left: 4px solid #ffc107; background: #fffdf0; border-radius: 0 8px 8px 0; padding: 10px 14px; font-size: 0.85rem; }
</style>
@endsection
@section('content')
    <section class="container">
        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1: Price Edit (existing, cleaned up) --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="card-modern border-info mb-3">
            <div class="card-header bg-transparent border-info">
                <i class="mdi mdi-tag-outline mr-1"></i>
                {{ __(ucwords($data->product->product_name)) }}
                <span class="text-muted ml-2" style="font-size: 0.85rem;">— Price Adjustment</span>
            </div>
            <form id="priceEditForm" class="form-horizontal" method="POST" action="{{ route('prices.update', $data->id) }}">
                {{ csrf_field() }}
                <input name="_method" type="hidden" value="PUT">
                <input type="hidden" name="products" value="{{ $data->id }}">

                <div class="card-body">
                    {{-- Current prices (read-only) --}}
                    <h6 class="font-weight-bold text-muted mb-2">
                        <i class="mdi mdi-history mr-1"></i> Current Prices
                    </h6>
                    <table class="table table-sm table-bordered table-striped mb-3">
                        <thead class="thead-light">
                            <tr>
                                <th>Supplier Price (&#8358;)</th>
                                <th>Issue Price (&#8358;)</th>
                                <th>Max Discount (&#8358;)</th>
                                @if ($application->allow_piece_sale == 1)
                                    <th>Pieces Price (&#8358;)</th>
                                    <th>Pieces Mxd (&#8358;)</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>{{ formatMoney($data->pr_buy_price) }}</strong></td>
                                <td><strong>{{ formatMoney($data->current_sale_price) }}</strong></td>
                                <td>{{ formatMoney($data->max_discount) }}</td>
                                @if ($application->allow_piece_sale == 1)
                                    <td>{{ formatMoney($data->pieces_price) }}</td>
                                    <td>{{ formatMoney($data->pieces_max_discount) }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>

                    {{-- New price inputs --}}
                    <h6 class="font-weight-bold text-primary mb-2">
                        <i class="mdi mdi-pencil mr-1"></i> New Prices
                    </h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Supplier Price (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="new_buy_price" id="new_buy_price"
                                class="form-control" placeholder="New buy price"
                                value="{{ old('new_buy_price', $data->pr_buy_price) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Issue/Sale Price (&#8358;) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="price" id="price"
                                class="form-control" placeholder="New sale price" required
                                value="{{ old('price', $data->current_sale_price) }}">
                            <small class="text-muted">This is the price patients/HMOs are charged</small>
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Max Discount (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="max_discount" id="max_discount"
                                class="form-control" placeholder="Maximum discount"
                                value="{{ old('max_discount', $data->max_discount) }}">
                        </div>
                    </div>
                    @if ($application->allow_piece_sale == 1)
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Pieces Price (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="pieces_price" id="pieces_price"
                                class="form-control" placeholder="Pieces price"
                                value="{{ old('pieces_price', $data->pieces_price) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Pieces Max Discount (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="pieces_max_discount" id="pieces_max_discount"
                                class="form-control" placeholder="Pieces max discount"
                                value="{{ old('pieces_max_discount', $data->pieces_max_discount) }}">
                        </div>
                    </div>
                    @endif

                {{-- ══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 2: Tariff Propagation (collapsible) --}}
                {{-- ══════════════════════════════════════════════════════════ --}}
                <div class="tariff-section mt-2">
                    <div class="card mb-0" style="border-radius: 10px; border: 1px dashed #adb5bd;">
                        <div class="card-header section-toggle px-3 py-2" style="background: #f8f9fa; border-radius: 10px;" id="tariffToggle">
                            <div class="d-flex align-items-center justify-content-between">
                                <span>
                                    <i class="mdi mdi-shield-sync mr-1 text-info" style="font-size: 1.1rem;"></i>
                                    <strong>Update HMO Tariffs</strong>
                                    <span class="text-muted small ml-1">— Optionally sync the new price to HMO tariff schedules</span>
                                </span>
                                <i class="mdi mdi-chevron-down chevron-icon" id="tariffChevron"></i>
                            </div>
                        </div>

                        <div id="tariffPanel" style="display: none;">
                            <div class="card-body px-3 pt-3 pb-3" style="border-top: 1px solid #dee2e6;">

                                {{-- Explanation callout --}}
                                <div class="info-callout mb-3">
                                    <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                    <strong>How this works:</strong>
                                    When you change a product's price, the HMO tariff entries (what each insurance scheme pays for this item)
                                    can optionally be updated to match. Choose what to update and which HMOs/schemes are affected below.
                                    <br><small class="text-muted mt-1 d-block">If you skip this section, only the product price changes — existing tariffs remain as-is.</small>
                                </div>

                                {{-- ── Step 1: What to update ── --}}
                                <h6 class="font-weight-bold mb-2">
                                    <span class="badge badge-primary mr-1">1</span> What to Update
                                </h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="custom-control custom-switch mb-2">
                                            <input type="checkbox" class="custom-control-input" id="sync_payable" name="sync_payable" value="1">
                                            <label class="custom-control-label" for="sync_payable">
                                                <strong>Payable Amount</strong>
                                            </label>
                                        </div>
                                        <div class="pl-4">
                                            <small class="text-muted d-block">
                                                Sets the <em>payable_amount</em> on each tariff to the new <strong>Issue Price</strong> you entered above.
                                                This is what the facility receives from the HMO for this item.
                                            </small>
                                            <small class="text-info d-block mt-1">
                                                <i class="mdi mdi-arrow-right-bold mr-1"></i>
                                                New value: <strong id="payablePreviewValue">&#8358;{{ number_format($data->current_sale_price, 2) }}</strong>
                                                <span class="text-muted">(from Issue Price field)</span>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-control custom-switch mb-2">
                                            <input type="checkbox" class="custom-control-input" id="sync_claims" name="sync_claims" value="1">
                                            <label class="custom-control-label" for="sync_claims">
                                                <strong>Claims Amount</strong>
                                            </label>
                                        </div>
                                        <div class="pl-4" id="claimsInputWrap" style="display: none;">
                                            <label class="small text-muted mb-1">Enter the claims amount to apply:</label>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="new_claims_amount" id="new_claims_amount"
                                                placeholder="e.g. 5000.00" value="{{ old('new_claims_amount', 0) }}" style="max-width: 220px;">
                                            <small class="text-muted d-block mt-1">
                                                This is the amount the HMO <em>claims</em> for this item. Often different from the payable amount.
                                            </small>
                                        </div>
                                        <div class="pl-4" id="claimsHint">
                                            <small class="text-muted">
                                                The amount the HMO reimburses/claims for this item. Enable to set a custom value across selected tariffs.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Override manual toggle --}}
                                <div class="warning-callout mb-3" id="overrideSection" style="display: none;">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="override_manual" name="override_manual" value="1">
                                        <label class="custom-control-label" for="override_manual">
                                            <strong>Override manually-set tariffs</strong>
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="mdi mdi-alert-outline mr-1 text-warning"></i>
                                        Some HMOs have tariffs that were configured manually (payable &gt; 0). By default these are <strong>skipped</strong> to preserve negotiated rates.
                                        Check this box to overwrite them too.
                                    </small>
                                </div>

                                {{-- ── Step 2: Which HMOs ── --}}
                                <div id="scopeSection" style="display: none;">
                                    <h6 class="font-weight-bold mb-2">
                                        <span class="badge badge-primary mr-1">2</span> Which HMOs to Update
                                        <small class="text-muted font-weight-normal ml-1">({{ $totalHmoCount }} active HMOs total)</small>
                                    </h6>

                                    {{-- Scope selector --}}
                                    <div class="row mb-3">
                                        <div class="col-md-4 mb-2">
                                            <div class="scope-option" data-scope="all">
                                                <div class="d-flex align-items-center">
                                                    <input type="radio" name="tariff_scope" value="all" class="scope-radio">
                                                    <div>
                                                        <strong>All HMOs</strong>
                                                        <small class="d-block text-muted">Update tariffs for every active HMO ({{ $totalHmoCount }})</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="scope-option" data-scope="scheme">
                                                <div class="d-flex align-items-center">
                                                    <input type="radio" name="tariff_scope" value="scheme" class="scope-radio">
                                                    <div>
                                                        <strong>By Scheme</strong>
                                                        <small class="d-block text-muted">Select insurance scheme categories</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="scope-option" data-scope="manual">
                                                <div class="d-flex align-items-center">
                                                    <input type="radio" name="tariff_scope" value="manual" class="scope-radio">
                                                    <div>
                                                        <strong>Pick Individually</strong>
                                                        <small class="d-block text-muted">Hand-pick specific HMOs</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ── Scheme cards ── --}}
                                    <div id="schemePanel" style="display: none;">
                                        @if(count($schemeSummary) > 0)
                                        <div class="row">
                                            @foreach($schemeSummary as $scheme)
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="scheme-card" data-scheme-id="{{ $scheme['id'] }}">
                                                    <div class="scheme-header">
                                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                                            <div class="d-flex align-items-center">
                                                                <input type="checkbox" class="mr-2 scheme-checkbox"
                                                                    name="selected_scheme_ids[]"
                                                                    value="{{ $scheme['id'] }}">
                                                                <strong>{{ $scheme['name'] }}</strong>
                                                            </div>
                                                            <span class="badge badge-pill badge-secondary">{{ $scheme['hmo_count'] }} HMOs</span>
                                                        </div>

                                                        {{-- Payable stats --}}
                                                        <div class="mb-1">
                                                            <span class="stat-badge payable">Payable</span>
                                                            @if($scheme['payable_min'] == $scheme['payable_max'])
                                                                <span class="price-hint ml-1">&#8358;{{ number_format($scheme['payable_min'], 2) }}</span>
                                                            @else
                                                                <span class="price-hint ml-1">
                                                                    &#8358;{{ number_format($scheme['payable_min'], 2) }}
                                                                    — &#8358;{{ number_format($scheme['payable_max'], 2) }}
                                                                    <span class="text-muted">(avg: &#8358;{{ number_format($scheme['payable_avg'], 2) }})</span>
                                                                </span>
                                                            @endif
                                                        </div>

                                                        {{-- Claims stats --}}
                                                        <div class="mb-1">
                                                            <span class="stat-badge claims">Claims</span>
                                                            @if($scheme['claims_min'] == $scheme['claims_max'])
                                                                <span class="price-hint ml-1">&#8358;{{ number_format($scheme['claims_min'], 2) }}</span>
                                                            @else
                                                                <span class="price-hint ml-1">
                                                                    &#8358;{{ number_format($scheme['claims_min'], 2) }}
                                                                    — &#8358;{{ number_format($scheme['claims_max'], 2) }}
                                                                    <span class="text-muted">(avg: &#8358;{{ number_format($scheme['claims_avg'], 2) }})</span>
                                                                </span>
                                                            @endif
                                                        </div>

                                                        {{-- Manual/Auto counts --}}
                                                        <div>
                                                            @if($scheme['manual_count'] > 0)
                                                                <span class="stat-badge manual">{{ $scheme['manual_count'] }} manual</span>
                                                            @endif
                                                            @if($scheme['auto_count'] > 0)
                                                                <span class="stat-badge auto">{{ $scheme['auto_count'] }} auto</span>
                                                            @endif
                                                        </div>

                                                        {{-- Expand toggle --}}
                                                        <div class="text-center mt-1">
                                                            <small class="text-primary scheme-expand-toggle" style="cursor: pointer;">
                                                                <i class="mdi mdi-chevron-down"></i> Show HMOs
                                                            </small>
                                                        </div>
                                                    </div>

                                                    {{-- Individual HMO list --}}
                                                    <div class="scheme-hmos">
                                                        @foreach($scheme['hmos'] as $hmo)
                                                        <div class="hmo-item d-flex align-items-center justify-content-between">
                                                            <div>
                                                                <i class="mdi mdi-shield-outline text-muted mr-1"></i>
                                                                {{ $hmo['name'] }}
                                                                @if(!$hmo['has_tariff'])
                                                                    <span class="badge badge-warning badge-pill" style="font-size: 0.65rem;">No tariff</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-right">
                                                                <small>
                                                                    <span class="text-success">P: &#8358;{{ number_format($hmo['payable_amount'], 2) }}</span>
                                                                    <span class="text-muted mx-1">|</span>
                                                                    <span class="text-warning">C: &#8358;{{ number_format($hmo['claims_amount'], 2) }}</span>
                                                                </small>
                                                                @if($hmo['is_manual'])
                                                                    <i class="mdi mdi-account-edit text-danger ml-1" title="Manually configured" style="font-size: 0.8rem;"></i>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        @else
                                        <div class="alert alert-light text-muted"><i class="mdi mdi-information-outline mr-1"></i> No scheme categories found.</div>
                                        @endif
                                    </div>

                                    {{-- ── Manual HMO picker ── --}}
                                    <div id="manualPanel" style="display: none;">
                                        <div class="mb-2">
                                            <input type="text" class="form-control form-control-sm" id="hmoSearchInput"
                                                placeholder="Search HMOs by name..." style="max-width: 300px; border-radius: 8px;">
                                        </div>
                                        <div class="d-flex mb-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm mr-2" id="selectAllHmos">Select All</button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllHmos">Deselect All</button>
                                        </div>
                                        <div class="standalone-hmo-list border rounded p-2" style="border-radius: 8px !important;">
                                            @php
                                                $allHmos = collect();
                                                foreach($schemeSummary as $scheme) {
                                                    foreach($scheme['hmos'] as $h) { $allHmos->push($h); }
                                                }
                                                foreach($standaloneData as $h) { $allHmos->push($h); }
                                                $allHmos = $allHmos->sortBy('name');
                                            @endphp
                                            @foreach($allHmos as $hmo)
                                            <div class="hmo-item d-flex align-items-center justify-content-between hmo-manual-item" data-name="{{ strtolower($hmo['name']) }}">
                                                <div class="d-flex align-items-center">
                                                    <input type="checkbox" class="mr-2 manual-hmo-checkbox"
                                                        name="selected_hmo_ids[]"
                                                        value="{{ $hmo['id'] }}">
                                                    <span>{{ $hmo['name'] }}</span>
                                                    @if(!$hmo['has_tariff'])
                                                        <span class="badge badge-warning badge-pill ml-1" style="font-size: 0.65rem;">No tariff</span>
                                                    @endif
                                                    @if($hmo['is_manual'])
                                                        <i class="mdi mdi-account-edit text-danger ml-1" title="Manually configured" style="font-size: 0.8rem;"></i>
                                                    @endif
                                                </div>
                                                <div class="text-right">
                                                    <small>
                                                        <span class="text-success">P: &#8358;{{ number_format($hmo['payable_amount'], 2) }}</span>
                                                        <span class="text-muted mx-1">|</span>
                                                        <span class="text-warning">C: &#8358;{{ number_format($hmo['claims_amount'], 2) }}</span>
                                                    </small>
                                                </div>
                                            </div>
                                            @endforeach

                                            @if($allHmos->isEmpty())
                                                <p class="text-muted text-center my-3"><i class="mdi mdi-information-outline mr-1"></i> No active HMOs found.</p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ── Step 3: Preview ── --}}
                                    <div id="previewSection" style="display: none;" class="mt-3">
                                        <h6 class="font-weight-bold mb-2">
                                            <span class="badge badge-primary mr-1">3</span> Preview
                                        </h6>
                                        <div class="preview-box">
                                            <div class="d-flex align-items-start">
                                                <i class="mdi mdi-clipboard-check-outline text-primary mr-2" style="font-size: 1.3rem; margin-top: 2px;"></i>
                                                <div>
                                                    <p class="mb-1"><strong>Summary of changes on submit:</strong></p>
                                                    <ul class="mb-1" style="padding-left: 18px; font-size: 0.88rem;" id="previewList">
                                                        <li id="previewPayable" style="display: none;">
                                                            Payable amount → <strong id="previewPayableVal">&#8358;0.00</strong>
                                                        </li>
                                                        <li id="previewClaims" style="display: none;">
                                                            Claims amount → <strong id="previewClaimsVal">&#8358;0.00</strong>
                                                        </li>
                                                        <li id="previewTarget">
                                                            Target: <strong id="previewTargetText">—</strong>
                                                        </li>
                                                        <li id="previewOverride" style="display: none;">
                                                            <span class="text-warning"><i class="mdi mdi-alert mr-1"></i></span>
                                                            <strong>Will overwrite</strong> manually-configured tariffs
                                                        </li>
                                                    </ul>
                                                    <small class="text-muted" id="previewSkipNote">
                                                        <i class="mdi mdi-shield-alert-outline mr-1"></i>
                                                        Tariffs with a non-zero payable amount (manually set) will be <strong>skipped</strong> unless override is checked.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- End tariff section --}}

                </div>
                <div class="card-footer bg-transparent border-info">
                    <div class="form-group row mb-0">
                        <div class="col-md-6">
                            <a href="{{ route('products.index') }}" class="btn btn-success">
                                <i class="fa fa-close"></i> Back
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary float-right">
                                <i class="fa fa-send"></i> Save Price
                                <span id="submitTariffHint" class="ml-1" style="display: none; font-size: 0.8rem;">+ Tariffs</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // ════════════════════════════════════════════════
    // Toggle tariff panel
    // ════════════════════════════════════════════════
    $('#tariffToggle').on('click', function() {
        let $panel = $('#tariffPanel');
        let $chevron = $('#tariffChevron');
        $panel.slideToggle(250);
        $chevron.toggleClass('open');
    });

    // ════════════════════════════════════════════════
    // Scheme card expand/collapse
    // ════════════════════════════════════════════════
    $(document).on('click', '.scheme-expand-toggle', function(e) {
        e.stopPropagation();
        let $hmos = $(this).closest('.scheme-card').find('.scheme-hmos');
        $hmos.toggleClass('open');
        let $icon = $(this).find('i');
        if ($hmos.hasClass('open')) {
            $icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
            $(this).html('<i class="mdi mdi-chevron-up"></i> Hide HMOs');
        } else {
            $icon.removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
            $(this).html('<i class="mdi mdi-chevron-down"></i> Show HMOs');
        }
    });

    // Clicking scheme header toggles checkbox
    $(document).on('click', '.scheme-header', function(e) {
        if ($(e.target).is('input') || $(e.target).closest('.scheme-expand-toggle').length) return;
        let $cb = $(this).find('.scheme-checkbox');
        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
    });

    // Scheme card visual selection
    $(document).on('change', '.scheme-checkbox', function() {
        $(this).closest('.scheme-card').toggleClass('selected', $(this).is(':checked'));
        updatePreview();
    });

    // ════════════════════════════════════════════════
    // Scope selector
    // ════════════════════════════════════════════════
    $('.scope-option').on('click', function() {
        let scope = $(this).data('scope');
        $('.scope-option').removeClass('active');
        $(this).addClass('active');
        $(this).find('.scope-radio').prop('checked', true);

        // Show/hide panels
        $('#schemePanel').toggle(scope === 'scheme');
        $('#manualPanel').toggle(scope === 'manual');
        updatePreview();
    });

    // ════════════════════════════════════════════════
    // Sync toggles
    // ════════════════════════════════════════════════
    function updateSyncVisibility() {
        let payableOn = $('#sync_payable').is(':checked');
        let claimsOn = $('#sync_claims').is(':checked');
        let anyOn = payableOn || claimsOn;

        // Show/hide claims input
        $('#claimsInputWrap').toggle(claimsOn);
        $('#claimsHint').toggle(!claimsOn);

        // Show/hide override section
        $('#overrideSection').toggle(payableOn);

        // Show/hide scope section
        $('#scopeSection').toggle(anyOn);

        // Show tariff hint on submit button
        $('#submitTariffHint').toggle(anyOn);

        updatePreview();
    }

    $('#sync_payable, #sync_claims').on('change', updateSyncVisibility);

    // ════════════════════════════════════════════════
    // Update payable preview value when price changes
    // ════════════════════════════════════════════════
    $('#price').on('input', function() {
        let val = parseFloat($(this).val()) || 0;
        $('#payablePreviewValue').html('&#8358;' + val.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        updatePreview();
    });

    // ════════════════════════════════════════════════
    // HMO search filter (manual panel)
    // ════════════════════════════════════════════════
    $('#hmoSearchInput').on('input', function() {
        let term = $(this).val().toLowerCase();
        $('.hmo-manual-item').each(function() {
            $(this).toggle($(this).data('name').indexOf(term) !== -1);
        });
    });

    // Select/deselect all HMOs
    $('#selectAllHmos').on('click', function() {
        $('.hmo-manual-item:visible .manual-hmo-checkbox').prop('checked', true);
        updatePreview();
    });
    $('#deselectAllHmos').on('click', function() {
        $('.manual-hmo-checkbox').prop('checked', false);
        updatePreview();
    });

    // Manual HMO checkbox changes
    $(document).on('change', '.manual-hmo-checkbox', function() {
        updatePreview();
    });

    // Override checkbox
    $('#override_manual').on('change', updatePreview);

    // ════════════════════════════════════════════════
    // Preview updater
    // ════════════════════════════════════════════════
    function updatePreview() {
        let payableOn = $('#sync_payable').is(':checked');
        let claimsOn = $('#sync_claims').is(':checked');
        let anyOn = payableOn || claimsOn;

        if (!anyOn) {
            $('#previewSection').hide();
            return;
        }
        $('#previewSection').show();

        // Payable line
        let price = parseFloat($('#price').val()) || 0;
        $('#previewPayable').toggle(payableOn);
        $('#previewPayableVal').html('&#8358;' + price.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        // Claims line
        let claims = parseFloat($('#new_claims_amount').val()) || 0;
        $('#previewClaims').toggle(claimsOn);
        $('#previewClaimsVal').html('&#8358;' + claims.toLocaleString('en-NG', {minimumFractionDigits: 2}));

        // Target line
        let scope = $('input[name="tariff_scope"]:checked').val();
        let targetText = '—';
        if (scope === 'all') {
            targetText = 'All {{ $totalHmoCount }} active HMOs';
        } else if (scope === 'scheme') {
            let selected = $('.scheme-checkbox:checked');
            if (selected.length === 0) {
                targetText = '<span class="text-danger">No schemes selected</span>';
            } else {
                let names = [];
                let total = 0;
                selected.each(function() {
                    let $card = $(this).closest('.scheme-card');
                    names.push($card.find('strong:first').text().trim());
                    total += parseInt($card.find('.badge-secondary').text());
                });
                targetText = names.join(', ') + ' (' + total + ' HMOs)';
            }
        } else if (scope === 'manual') {
            let count = $('.manual-hmo-checkbox:checked').length;
            targetText = count > 0 ? count + ' HMO(s) selected' : '<span class="text-danger">No HMOs selected</span>';
        } else {
            targetText = '<span class="text-danger">Choose a scope above</span>';
        }
        $('#previewTargetText').html(targetText);

        // Override line
        let overrideOn = $('#override_manual').is(':checked');
        $('#previewOverride').toggle(overrideOn && payableOn);
        $('#previewSkipNote').toggle(!overrideOn && payableOn);
    }

    // Claims amount input updates preview
    $('#new_claims_amount').on('input', updatePreview);
});
</script>
@endsection

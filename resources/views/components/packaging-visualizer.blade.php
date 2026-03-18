{{--
Packaging visualizer component — shows the packaging chain for a product.

Usage:
  <x-packaging-visualizer :product="$product" :stock-qty="$totalQty" />

Or dynamically via JS: call PackagingVisualizer.render(containerEl, productData)
--}}

@props([
    'product' => null,
    'stockQty' => null,
])

@if($product && $product->packagings && $product->packagings->count())
<div class="packaging-visualizer">
    <div class="d-flex align-items-center flex-wrap mb-2">
        <span class="badge badge-success mr-1">{{ $product->base_unit_name ?? 'Piece' }}</span>
        @foreach($product->packagings->sortBy('level') as $pkg)
            <span class="text-muted mx-1">&rarr; {{ $pkg->units_in_parent }} &rarr;</span>
            <span class="badge badge-light border mr-1">
                {{ $pkg->name }}
                <small class="text-muted">(={{ number_format($pkg->base_unit_qty, $pkg->base_unit_qty == intval($pkg->base_unit_qty) ? 0 : 2) }} {{ $product->base_unit_name ?? 'pcs' }})</small>
            </span>
        @endforeach
    </div>

    @if($stockQty !== null && $stockQty > 0)
    <div class="text-muted small">
        <i class="mdi mdi-package-variant"></i>
        In Stock: <strong>{{ $product->formatQty($stockQty) }}</strong>
    </div>
    @endif
</div>
@endif

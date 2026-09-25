@php
    $tallyRoutes = [
        'inventory.store-workbench.tally-card.pending-actions' => route('inventory.store-workbench.tally-card.pending-actions'),
        'inventory.store-workbench.tally-card.data' => route('inventory.store-workbench.tally-card.data'),
        'inventory.store-workbench.store-batches' => route('inventory.store-workbench.store-batches'),
        'inventory.store-workbench.create-manual-batch' => route('inventory.store-workbench.create-manual-batch'),
        'inventory.requisitions.store' => route('inventory.requisitions.store'),
        'inventory.purchase-orders.store' => route('inventory.purchase-orders.store'),
        'products.packagings' => route('products.packagings', ['product' => ':id']),
        'inventory.store-damages.get-batches' => route('inventory.store-damages.get-batches'),
        'inventory.store-damages.get-recent-batches' => route('inventory.store-damages.get-recent-batches'),
        'inventory.store-damages.search-products' => route('inventory.store-damages.search-products'),
        'inventory.store-damages.store' => route('inventory.store-damages.store'),
        'inventory.requisition-returns.search-requisitions' => route('inventory.requisition-returns.search-requisitions'),
        'inventory.requisition-returns.req-items' => route('inventory.requisition-returns.req-items'),
        'inventory.requisition-returns.batches-for-product' => route('inventory.requisition-returns.batches-for-product'),
        'inventory.requisition-returns.store' => route('inventory.requisition-returns.store'),
        'inventory.po-returns.search-pos' => route('inventory.po-returns.search-pos'),
        'inventory.po-returns.po-items' => route('inventory.po-returns.po-items'),
        'inventory.po-returns.store' => route('inventory.po-returns.store'),
    ];
@endphp
@section('scripts')
<script>
window.WORKBENCH_CONFIG = Object.assign(window.WORKBENCH_CONFIG || {}, {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: @json($tallyRoutes)
});
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/tally-card.js') }}"></script>
@endsection


@push('scripts')
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        redirectUrl: '{{ route("inventory.purchase-orders.show", $purchaseOrder) }}'
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/po-payment.js') }}"></script>
@endpush

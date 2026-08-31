@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        queue: '{{ request("queue") }}',
        routes: {
            'inventory.requisitions.index': '{{ route("inventory.requisitions.index") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/requisitions-index.js') }}"></script>
@endsection

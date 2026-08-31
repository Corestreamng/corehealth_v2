@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        routes: {
            'organizations.data': '{{ route("organizations.data") }}',
            'organizations.store': '{{ route("organizations.store") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/organizations-index.js') }}"></script>
@endsection

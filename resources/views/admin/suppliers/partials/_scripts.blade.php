@section('scripts')
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        routes: {
            'suppliers.list': '{{ route("suppliers.list") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/suppliers-index.js') }}"></script>
@endsection

@section('scripts')
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        routes: {
            'listReturningPatients': '{{ route("listReturningPatients") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/add-to-queue.js') }}"></script>
@endsection

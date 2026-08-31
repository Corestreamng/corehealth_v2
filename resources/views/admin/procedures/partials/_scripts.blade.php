@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        routes: {
            'encounterList': '{{ route("encounterList") }}',
            'my-admission-requests-list': '{{ route("my-admission-requests-list") }}',
            'admission-requests-list': '{{ route("admission-requests-list") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/procedures-index.js') }}"></script>
@endsection

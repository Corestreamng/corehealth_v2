@section('scripts')
<script>
window.WORKBENCH_CONFIG = window.WORKBENCH_CONFIG || {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {}
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/tally-card.js') }}"></script>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        morgue_admit: '{{ route("morgue.admit") }}',
        morgue_queue: '{{ route("morgue.queue") }}',
        morgue_services: '{{ route("morgue.services") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/morgue-workbench.js') }}?v={{ filemtime(public_path('js/morgue-workbench.js')) }}"></script>
@endsection

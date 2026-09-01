@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker/moment.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}"></script>
<script>
window.WORKBENCH_CONFIG = {
    csrf: '{{ csrf_token() }}',
    baseUrl: '{{ url("/") }}',
    routes: {
        'appointments.doctor.unified-list': '{{ route("appointments.doctor.unified-list") }}',
        'appointments.doctor.unified-events': '{{ route("appointments.doctor.unified-events") }}',
        'appointments.doctor.queue-counts': '{{ route("appointments.doctor.queue-counts") }}',
        'appointments.check-in': '{{ route("appointments.check-in", ["appointment" => "__AID__"]) }}',
        'appointments.cancel': '{{ route("appointments.cancel", ["appointment" => "__AID__"]) }}',
        'appointments.no-show': '{{ route("appointments.no-show", ["appointment" => "__AID__"]) }}',
        'appointments.reassign': '{{ route("appointments.reassign", ["appointment" => "__AID__"]) }}',
        'my-admission-requests-list': '{{ route("my-admission-requests-list") }}',
        'admission-requests-list': '{{ route("admission-requests-list") }}',
        'referrals.doctor-list': '{{ route("referrals.doctor-list") }}',
        'referrals.all-list': '{{ route("referrals.all-list") }}'
    }
};
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/doctor-queue.js') }}?v={{ filemtime(public_path('js/doctor-queue.js')) }}"></script>
@endsection

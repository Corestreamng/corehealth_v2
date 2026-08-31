@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        hmos: @json(\App\Models\Hmo::with('scheme')->orderBy('name')->get()->map(fn($h) => ['id' => $h->id, 'name' => $h->name, 'scheme_name' => $h->scheme->name ?? 'Other'])),
        requestsUrl: '{{ route("hmo.requests") }}',
        batchApproveUrl: '{{ route("hmo.batch-approve") }}',
        batchRejectUrl: '{{ route("hmo.batch-reject") }}',
        queueCountsUrl: '{{ route("hmo.queue-counts") }}',
        financialSummaryUrl: '{{ route("hmo.financial-summary") }}',
        exportClaimsUrl: '{{ route("hmo.export-claims") }}',
        groupApproveUrl: '{{ route("hmo.group-approve") }}',
        groupRejectUrl: '{{ route("hmo.group-reject") }}',
        batchSubmitAuthCodeUrl: '{{ route("hmo.batch-submit-auth-code") }}',
        routes: {
            'reception.patient.next-file-number': '{{ route("reception.patient.next-file-number") }}',
            'reception.patient.check-file-number': '{{ route("reception.patient.check-file-number") }}',
            'reception.patient.quick-register': '{{ route("reception.patient.quick-register") }}',
            'hmo.requests': '{{ route("hmo.requests") }}',
            'hmo.batch-approve': '{{ route("hmo.batch-approve") }}',
            'hmo.batch-reject': '{{ route("hmo.batch-reject") }}',
            'hmo.queue-counts': '{{ route("hmo.queue-counts") }}',
            'hmo.financial-summary': '{{ route("hmo.financial-summary") }}',
            'hmo.export-claims': '{{ route("hmo.export-claims") }}',
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/hmo-workbench.js') }}"></script>
@endsection

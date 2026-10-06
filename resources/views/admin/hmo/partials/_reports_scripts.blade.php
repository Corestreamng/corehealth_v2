@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<link href="{{ asset('assets/css/select2.min.css') }}" rel="stylesheet" />
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        bootstrapCss: '{{ asset("plugins/bootstrap/css/bootstrap.min.css") }}',
        appSettings: {
            siteName: @json(appsettings()->site_name ?? config('app.name')),
            logo: @json(appsettings()->logo ?? ''),
            address: @json(appsettings()->contact_address ?? ''),
            phones: @json(appsettings()->contact_phones ?? ''),
            emails: @json(appsettings()->contact_emails ?? ''),
            hosColor: @json(appsettings()->hos_color ?? '#0066cc')
        },
        routes: {
            'hmo.reports': '{{ route("hmo.reports") }}',
            'hmo.reports.claims': '{{ route("hmo.reports.claims") }}',
            'hmo.reports.outstanding': '{{ route("hmo.reports.outstanding") }}',
            'hmo.reports.monthly': '{{ route("hmo.reports.monthly") }}',
            'hmo.reports.utilization': '{{ route("hmo.reports.utilization") }}',
            'hmo.reports.auth-codes': '{{ route("hmo.reports.auth-codes") }}',
            'hmo.reports.remittances': '{{ route("hmo.reports.remittances") }}',
            'hmo.reports.remittances.store': '{{ route("hmo.reports.remittances.store") }}',
            'hmo.reports.mark-submitted': '{{ route("hmo.reports.mark-submitted") }}',
            'hmo.reports.link-claims': '{{ route("hmo.reports.link-claims") }}',
            'hmo.reports.print-data': '{{ route("hmo.reports.print-data") }}',
            'hmo.reports.export-excel': '{{ route("hmo.reports.export-excel") }}',
            'hmo.reports.export-pdf': '{{ route("hmo.reports.export-pdf") }}',
            'hmo.reports.search-patients': '{{ route("hmo.reports.search-patients") }}',
            'patient-search': '{{ route("patient-search") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/hmo-reports.js') }}"></script>
@endsection

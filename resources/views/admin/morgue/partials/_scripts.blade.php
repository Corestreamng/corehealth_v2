@section('scripts')
@php
    $sett = appsettings();
    $hosColor = '#8b5cf6';
@endphp
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        hospital: {
            name: '{{ $sett->site_name ?? config("app.name") }}',
            color: '{{ $hosColor }}',
            logo: '{{ $sett->logo ? "data:image/jpeg;base64," . $sett->logo : "" }}',
            address: '{{ $sett->contact_address ?? "" }}',
            phone: '{{ $sett->contact_phones ?? "" }}',
            email: '{{ $sett->contact_emails ?? "" }}',
            tagline: '{{ $sett->hos_tagline ?? "" }}',
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/morgue-workbench.js') }}"></script>
@endsection

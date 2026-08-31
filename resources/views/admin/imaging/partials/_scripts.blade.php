@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        imagingSearchPatientsUrl: '{{ route("imaging-workbench.search-patients") }}',
        requireApproval: {{ (bool) (appsettings('require_imaging_result_approval', 0)) ? 'true' : 'false' }},
        drSelfImg: {{ (bool) (appsettings('enable_doctor_self_imaging', 0)) ? 'true' : 'false' }},
        nrSelfImg: {{ (bool) (appsettings('enable_nurse_self_imaging', 0)) ? 'true' : 'false' }},
        imagingCategoryId: {{ appsettings('imaging_category_id', 6) }},
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}?v={{ filemtime(public_path('js/clinical-orders-shared.js')) }}"></script>
<script src="{{ asset('js/imaging-workbench.js') }}"></script>
@endsection

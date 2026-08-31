@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        patientId: '{{ request()->get("patient_id", "") }}',
        queueId: '{{ request()->get("queue_id", "") }}',
        patientWeight: {{ json_encode($patientWeight ?? null) }},
        enableStructuredDose: {{ (bool) (appsettings('enable_structured_dose') ?? 1) ? 'true' : 'false' }},
        defaultDoseMode: '{{ (bool) (appsettings('enable_structured_dose') ?? 1) ? (appsettings('default_dose_mode') ?? 'structured') : 'simple' }}',
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
<script src="{{ asset('js/encounter-page.js') }}"></script>
@endsection

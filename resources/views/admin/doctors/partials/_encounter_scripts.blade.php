@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
@php
    $pId = $patient->id ?? request()->get('patient_id', 0);
    $encId = $encounter->id ?? request()->get('encounter_id', '');
@endphp
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        patientId: '{{ $pId }}',
        queueId: '{{ request()->get("queue_id", "") }}',
        encounterId: '{{ $encId }}',
        patientWeight: {!! json_encode($patientWeight ?? null) !!},
        enableStructuredDose: {{ (bool) (appsettings('enable_structured_dose') ?? 1) ? 'true' : 'false' }},
        defaultDoseMode: '{{ (bool) (appsettings('enable_structured_dose') ?? 1) ? (appsettings('default_dose_mode') ?? 'structured') : 'simple' }}',
        allowDoctorSetProcedurePrice: {{ (bool) (appsettings('allow_doctor_set_procedure_price') ?? 0) ? 'true' : 'false' }},
        procedureCategoryId: {{ (int) (appsettings('procedure_category_id', 0)) }},
        routes: {
            'patient-form-list': '{{ url("/patient-form-list") }}/' + ('{{ $pId }}' || '0'),
            'EncounterHistoryList': '{{ url("/EncounterHistoryList") }}/' + ('{{ $pId }}' || '0'),
            'investigationHistoryList': '{{ url("/investigationHistoryList") }}/' + ('{{ $pId }}' || '0'),
            'imagingHistoryList': '{{ url("/imagingHistoryList") }}/' + ('{{ $pId }}' || '0'),
            'prescHistoryList': '{{ url("/prescHistoryList") }}/' + ('{{ $pId }}' || '0'),
            'procedureHistoryList': '{{ url("/procedureHistoryList") }}/' + ('{{ $pId }}' || '0'),
            'patientAdmissionRequestsList': '{{ url("/patient-admission-requests-list") }}/' + ('{{ $pId }}' || '0'),
            'patient-admission-requests-list': '{{ url("/patient-admission-requests-list") }}/' + ('{{ $pId }}' || '0')
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
@include('admin.partials.invest_res_js')
@include('admin.partials.perform_investigation_modal')
@include('admin.partials.combo_confirm_modal')
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
@include('admin.doctors.partials.modals')
@include('admin.partials.treatment-plan-viewer-modal')
@include('admin.doctors.partials.report_builder')
@include('admin.partials.admit_discharge_modal')
@include('admin.partials.clinical_alerts_modal')
@include('admin.patients.partials.nurse_chart_scripts_enhanced')
<script src="{{ asset('js/speech-dictation.js') }}"></script>
<script src="{{ asset('js/workbench-notes-shared.js') }}"></script>
<script src="{{ asset('js/encounter-procedures.js') }}"></script>
<script src="{{ asset('js/encounter-page.js') }}"></script>
@endsection

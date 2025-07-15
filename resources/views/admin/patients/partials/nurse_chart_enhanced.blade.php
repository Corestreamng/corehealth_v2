@php
    // $patient is available in parent view
@endphp
<div class="nurse-chart-tabs">
    <ul class="nav nav-tabs" id="nurseChartTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="medication-tab" data-bs-toggle="tab" data-bs-target="#medicationChart"
                type="button" role="tab">Medication Chart</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="intake-output-tab" data-bs-toggle="tab" data-bs-target="#intakeOutputChart"
                type="button" role="tab">Intake & Output Chart</button>
        </li>
    </ul>
    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="medicationChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_medication_enhanced')
        </div>
        <div class="tab-pane fade" id="intakeOutputChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_intake_output')
        </div>
    </div>
</div>

@section('scripts')
    @parent
    @include('admin.patients.partials.nurse_chart_scripts_enhanced')
@endsection

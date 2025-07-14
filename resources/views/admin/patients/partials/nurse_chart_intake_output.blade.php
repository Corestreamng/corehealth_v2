<div class="intake-output-chart-section">
    <h5>Intake & Output Chart</h5>
    <ul class="nav nav-tabs" id="intakeOutputTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="fluid-tab" data-bs-toggle="tab" data-bs-target="#fluidChart" type="button" role="tab">Fluid</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="solid-tab" data-bs-toggle="tab" data-bs-target="#solidChart" type="button" role="tab">Solid</button>
        </li>
    </ul>
    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="fluidChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_intake_output_fluid')
        </div>
        <div class="tab-pane fade" id="solidChart" role="tabpanel">
            @include('admin.patients.partials.nurse_chart_intake_output_solid')
        </div>
    </div>
</div>

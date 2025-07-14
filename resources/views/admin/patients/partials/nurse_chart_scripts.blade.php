<script>
    // CSRF token for AJAX requests
    var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var PATIENT_ID = {{ isset($patient) ? (is_object($patient) ? $patient->id : $patient) : (isset($patient_id) ? $patient_id : 'null') }};
    var medicationChartIndexRoute = "{{ route('nurse.medication.index', [':patient']) }}";
    var medicationChartScheduleRoute = "{{ route('nurse.medication.schedule') }}";
    var medicationChartAdministerRoute = "{{ route('nurse.medication.administer') }}";
    var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', [':patient']) }}";
    var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
    var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
    var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
</script>
<script>
    // Nurse Chart JS (AJAX + Toaster)
    $(function() {
        // Medication Chart
        function loadMedicationChart() {
            $('#medication-loading').show();
            $.get(medicationChartIndexRoute.replace(':patient', PATIENT_ID), function(data) {
                $('#medication-loading').hide();
                // Render table from data.prescriptions and data.administrations
                $('#medication-chart-list').html(renderMedicationTable(data));
            }).fail(function() {
                $('#medication-loading').hide();
                $('#medication-chart-list').html('<div class="text-danger">Failed to load drugs.</div>');
            });
        }

        function renderMedicationTable(data) {
            let html = `<table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Drug</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Timings</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>`;
            data.prescriptions.forEach(function(p) {
                let prod = p.product || {};
                html += `<tr>
                        <td>${prod.product_name || ''}</td>
                        <td>${prod.product_code || ''}</td>
                        <td>${prod.category ? prod.category.category_name : ''}</td>
                        <td>${p.qty || ''}</td>
                        <td>${prod.price ? prod.price.initial_sale_price : ''}</td>
                        <td>${prod.stock ? prod.stock.current_quantity : ''}</td>
                        <td>`;
                let scheduled = data.administrations.filter(a => a.product_or_service_request_id === p.id);
                if (scheduled.length === 0) {
                    html += `<button class='btn btn-sm btn-outline-primary set-timing-btn' data-id='${p.id}'>Set Timing</button>`;
                } else {
                    scheduled.forEach(function(s) {
                        html += `<span class='badge bg-info m-1 timing-slot' data-id='${s.id}' data-bs-toggle='modal' data-bs-target='#administerModal'>${s.scheduled_time ? s.scheduled_time : 'Not set'}</span>`;
                    });
                }
                html += `</td><td></td></tr>`;
            });
            html += `</tbody></table>`;
            return html;
        }
        $(document).on('click', '.set-timing-btn', function() {
            let pid = $(this).data('id');
            // Open modal for set timing
            $('#set_timing_request_id').val(pid);
            $('#scheduled_time').val('');
            $('#setTimingModal').modal('show');
        });

        // Set Timing Modal Submission
        $('#setTimingForm').submit(function(e) {
            e.preventDefault();
            $('#setTimingLoading').removeClass('d-none');
            $('#setTimingSubmitBtn').attr('disabled', true);
            $.post(medicationChartScheduleRoute, $(this).serialize() + '&patient_id=' + PATIENT_ID + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Timing set!');
                    $('#setTimingModal').modal('hide');
                    loadMedicationChart();
                })
                .fail(function(xhr) {
                    toastr.error('Failed to set timing.');
                })
                .always(function() {
                    $('#setTimingLoading').addClass('d-none');
                    $('#setTimingSubmitBtn').attr('disabled', false);
                });
        });
        $(document).on('click', '.timing-slot', function() {
            let sid = $(this).data('id');
            $('#administer_schedule_id').val(sid);
        });
        $('#administerForm').submit(function(e) {
            e.preventDefault();
            $('#administerLoading').removeClass('d-none');
            $('#administerSubmitBtn').attr('disabled', true);
            $.post(medicationChartAdministerRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Administration saved!');
                    $('#administerModal').modal('hide');
                    loadMedicationChart();
                })
                .fail(function(xhr) {
                    toastr.error('Failed to save administration.');
                })
                .always(function() {
                    $('#administerLoading').addClass('d-none');
                    $('#administerSubmitBtn').attr('disabled', false);
                });
        });
        // Intake/Output Chart
        function loadIntakeOutput(type) {
            $.get(intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID), function(data) {
                let periods = type === 'fluid' ? data.fluidPeriods : data.solidPeriods;
                let html = '';
                periods.forEach(function(p) {
                    html += `<div class='card mb-2'>
                        <div class='card-header'>Period: ${p.started_at} ${(p.ended_at ? ' - ' + p.ended_at : '')}`;
                    if (!p.ended_at) html += ` <button class='btn btn-sm btn-danger float-end end-period-btn' data-id='${p.id}'
                                data-type='${type}'>End Period</button>`;
                    html += `</div>
                        <div class='card-body'>
                            <table class='table table-sm'>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    p.records.forEach(function(r) {
                        html += `<tr>
                                <td>${r.type}</td>
                                <td>${r.amount}</td>
                                <td>${r.description || ''}</td>
                                <td>${r.recorded_at}</td>
                            </tr>`;
                    });
                    html += `</tbody>
                    </table>`;
                    if (!p.ended_at) {
                        html +=
                            `<button class='btn btn-primary btn-sm add-record-btn' data-id='${p.id}' data-type='${type}'>Add Record</button>`;
                    }
                    html += `</div>
                    </div>`;
                });
                $('#' + type + '-periods-list').html(html);
            });
        }
        $('#startFluidPeriodBtn').click(function() {
            $.post(intakeOutputChartStartRoute, {
                patient_id: PATIENT_ID,
                type: 'fluid',
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Fluid period started!');
                loadIntakeOutput('fluid');
            });
        });
        $('#startSolidPeriodBtn').click(function() {
            $.post(intakeOutputChartStartRoute, {
                patient_id: PATIENT_ID,
                type: 'solid',
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Solid period started!');
                loadIntakeOutput('solid');
            });
        });
        $(document).on('click', '.end-period-btn', function() {
            let pid = $(this).data('id');
            let type = $(this).data('type');
            $.post(intakeOutputChartEndRoute, {
                period_id: pid,
                _token: CSRF_TOKEN
            }, function(resp) {
                toastr.success('Period ended! Balance: ' + resp.balance);
                loadIntakeOutput(type);
            });
        });
        $(document).on('click', '.add-record-btn', function() {
            let pid = $(this).data('id');
            let type = $(this).data('type');
            if (type === 'fluid') {
                $('#fluid_period_id').val(pid);
                $('#fluidRecordModal').modal('show');
            } else {
                $('#solid_period_id').val(pid);
                $('#solidRecordModal').modal('show');
            }
        });
        $('#fluidRecordForm').submit(function(e) {
            e.preventDefault();
            $.post(intakeOutputChartRecordRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Record added!');
                    $('#fluidRecordModal').modal('hide');
                    loadIntakeOutput('fluid');
                });
        });
        $('#solidRecordForm').submit(function(e) {
            e.preventDefault();
            $.post(intakeOutputChartRecordRoute, $(this).serialize() + '&_token=' + CSRF_TOKEN,
                function(resp) {
                    toastr.success('Record added!');
                    $('#solidRecordModal').modal('hide');
                    loadIntakeOutput('solid');
                });
        });
        // On tab show
        $('#nurseChart-tab').on('shown.bs.tab', function() {
            loadMedicationChart();
            loadIntakeOutput('fluid');
            loadIntakeOutput('solid');
        });
        // Initial load if already active
        if ($('#nurseChartCardBody').hasClass('show')) {
            loadMedicationChart();
            loadIntakeOutput('fluid');
            loadIntakeOutput('solid');
        }
    });
</script>


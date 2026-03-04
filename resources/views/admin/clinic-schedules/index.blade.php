@extends('admin.layouts.app')
@section('title', 'Clinic Schedules & Doctor Availability')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Clinic Schedules')

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-header">
                <h3 class="card-title"><i class="mdi mdi-calendar-clock"></i> Clinic Schedules & Doctor Availability</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="mdi mdi-information"></i>
                    <strong>Schedule Management:</strong>
                    Configure weekly clinic operating hours, doctor availability windows, and date-specific overrides (leave / extra sessions).
                    These settings control when appointment slots are available for booking.
                </div>

                {{-- Tabs --}}
                <ul class="nav nav-tabs" id="scheduleTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="clinic-hours-tab" data-bs-toggle="tab" data-bs-target="#clinic-hours-pane" type="button" role="tab">
                            <i class="mdi mdi-hospital-building me-1"></i>Clinic Hours
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="doctor-avail-tab" data-bs-toggle="tab" data-bs-target="#doctor-avail-pane" type="button" role="tab">
                            <i class="mdi mdi-doctor me-1"></i>Doctor Availability
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="overrides-tab" data-bs-toggle="tab" data-bs-target="#overrides-pane" type="button" role="tab">
                            <i class="mdi mdi-calendar-remove me-1"></i>Overrides
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="scheduleTabContent">

                    {{-- ═══ Tab 1: Clinic Weekly Hours ═══ --}}
                    <div class="tab-pane fade show active" id="clinic-hours-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <select id="filterClinicScheduleClinic" class="form-select form-select-sm" style="width:200px;">
                                    <option value="">All Clinics</option>
                                    @foreach($clinics as $clinic)
                                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm" onclick="openClinicScheduleModal()">
                                <i class="fa fa-plus"></i> Add Schedule
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped" id="clinic-schedule-table" style="width:100%">
                                <thead>
                                    <th>#</th>
                                    <th>Clinic</th>
                                    <th>Day</th>
                                    <th>Hours</th>
                                    <th>Slot Duration</th>
                                    <th>Max Concurrent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </thead>
                            </table>
                        </div>
                    </div>

                    {{-- ═══ Tab 2: Doctor Weekly Availability ═══ --}}
                    <div class="tab-pane fade" id="doctor-avail-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <select id="filterAvailDoctor" class="form-select form-select-sm" style="width:200px;">
                                    <option value="">All Doctors</option>
                                    @foreach($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->user->name ?? 'Doctor #'.$doc->id }}</option>
                                    @endforeach
                                </select>
                                <select id="filterAvailClinic" class="form-select form-select-sm" style="width:200px;">
                                    <option value="">All Clinics</option>
                                    @foreach($clinics as $clinic)
                                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm" onclick="openAvailabilityModal()">
                                <i class="fa fa-plus"></i> Add Availability
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped" id="doctor-avail-table" style="width:100%">
                                <thead>
                                    <th>#</th>
                                    <th>Doctor</th>
                                    <th>Clinic</th>
                                    <th>Day</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </thead>
                            </table>
                        </div>
                    </div>

                    {{-- ═══ Tab 3: Doctor Availability Overrides ═══ --}}
                    <div class="tab-pane fade" id="overrides-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <select id="filterOverrideDoctor" class="form-select form-select-sm" style="width:200px;">
                                    <option value="">All Doctors</option>
                                    @foreach($doctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->user->name ?? 'Doctor #'.$doc->id }}</option>
                                    @endforeach
                                </select>
                                <select id="filterOverrideClinic" class="form-select form-select-sm" style="width:200px;">
                                    <option value="">All Clinics</option>
                                    @foreach($clinics as $clinic)
                                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm" onclick="openOverrideModal()">
                                <i class="fa fa-plus"></i> Add Override
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped" id="override-table" style="width:100%">
                                <thead>
                                    <th>#</th>
                                    <th>Doctor</th>
                                    <th>Clinic</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Time Range</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </thead>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{--  MODALS                                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}

{{-- Clinic Schedule Modal --}}
<div class="modal fade" id="clinicScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clinicScheduleModalTitle">Add Clinic Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="clinicScheduleForm">
                <div class="modal-body">
                    <input type="hidden" id="cs_id" name="id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Clinic <span class="text-danger">*</span></label>
                        <select name="clinic_id" id="cs_clinic_id" class="form-select" required>
                            <option value="">Select Clinic</option>
                            @foreach($clinics as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Day of Week <span class="text-danger">*</span></label>
                        <select name="day_of_week" id="cs_day_of_week" class="form-select" required>
                            <option value="0">Sunday</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Open Time <span class="text-danger">*</span></label>
                            <input type="time" name="open_time" id="cs_open_time" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Close Time <span class="text-danger">*</span></label>
                            <input type="time" name="close_time" id="cs_close_time" class="form-control" value="17:00" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Slot Duration (min)</label>
                            <input type="number" name="slot_duration_minutes" id="cs_slot_duration" class="form-control" value="15" min="5" max="120">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Max Concurrent Slots</label>
                            <input type="number" name="max_concurrent_slots" id="cs_max_concurrent" class="form-control" value="1" min="1" max="20">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" id="cs_is_active" value="1" checked>
                        <label class="form-check-label" for="cs_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="cs_submit_btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Doctor Availability Modal --}}
<div class="modal fade" id="doctorAvailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="doctorAvailModalTitle">Add Doctor Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="doctorAvailForm">
                <div class="modal-body">
                    <input type="hidden" id="da_id" name="id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Doctor <span class="text-danger">*</span></label>
                        <select name="staff_id" id="da_staff_id" class="form-select" required>
                            <option value="">Select Doctor</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->user->name ?? 'Doctor #'.$doc->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Clinic <span class="text-danger">*</span></label>
                        <select name="clinic_id" id="da_clinic_id" class="form-select" required>
                            <option value="">Select Clinic</option>
                            @foreach($clinics as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Day of Week <span class="text-danger">*</span></label>
                        <select name="day_of_week" id="da_day_of_week" class="form-select" required>
                            <option value="0">Sunday</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="da_start_time" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="da_end_time" class="form-control" value="17:00" required>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="is_active" id="da_is_active" value="1" checked>
                        <label class="form-check-label" for="da_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="da_submit_btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Override Modal --}}
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="overrideModalTitle">Add Availability Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="overrideForm">
                <div class="modal-body">
                    <input type="hidden" id="ov_id" name="id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Doctor <span class="text-danger">*</span></label>
                        <select name="staff_id" id="ov_staff_id" class="form-select" required>
                            <option value="">Select Doctor</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->user->name ?? 'Doctor #'.$doc->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Clinic</label>
                        <select name="clinic_id" id="ov_clinic_id" class="form-select">
                            <option value="">All Clinics</option>
                            @foreach($clinics as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                        <input type="date" name="override_date" id="ov_override_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Type <span class="text-danger">*</span></label>
                        <select name="is_available" id="ov_is_available" class="form-select" required>
                            <option value="0">Block / Leave (Doctor Unavailable)</option>
                            <option value="1">Extra Availability (Additional Session)</option>
                        </select>
                    </div>
                    <div class="row mb-3" id="ov_time_row">
                        <div class="col-6">
                            <label class="form-label fw-bold">Start Time</label>
                            <input type="time" name="start_time" id="ov_start_time" class="form-control">
                            <small class="text-muted">Leave empty for full-day block</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">End Time</label>
                            <input type="time" name="end_time" id="ov_end_time" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason</label>
                        <input type="text" name="reason" id="ov_reason" class="form-control" placeholder="e.g. Annual leave, Conference, etc." maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="ov_submit_btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
    // ═══════════════════════════════════════════════════════════════════
    //  DataTable Initialization
    // ═══════════════════════════════════════════════════════════════════

    var clinicScheduleTable, doctorAvailTable, overrideTable;

    $(function() {
        initClinicScheduleTable();
        initDoctorAvailTable();
        initOverrideTable();
    });

    // ─── Clinic Schedules DataTable ────────────────────────────────────
    function initClinicScheduleTable() {
        clinicScheduleTable = $('#clinic-schedule-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 50,
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print'],
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('clinic-schedules.data') }}",
                type: 'GET',
                data: function(d) {
                    d.clinic_id = $('#filterClinicScheduleClinic').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'clinic_name', name: 'clinic_name' },
                { data: 'day_name', name: 'day_name' },
                { data: 'hours', name: 'hours' },
                { data: 'slot_duration_minutes', name: 'slot_duration_minutes' },
                { data: 'max_concurrent_slots', name: 'max_concurrent_slots' },
                { data: 'status', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#filterClinicScheduleClinic').on('change', function() {
            clinicScheduleTable.ajax.reload();
        });
    }

    // ─── Doctor Availability DataTable ─────────────────────────────────
    function initDoctorAvailTable() {
        doctorAvailTable = $('#doctor-avail-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 50,
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print'],
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('doctor-availability.data') }}",
                type: 'GET',
                data: function(d) {
                    d.staff_id = $('#filterAvailDoctor').val();
                    d.clinic_id = $('#filterAvailClinic').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'doctor_name', name: 'doctor_name' },
                { data: 'clinic_name', name: 'clinic_name' },
                { data: 'day_name', name: 'day_name' },
                { data: 'hours', name: 'hours' },
                { data: 'status', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#filterAvailDoctor, #filterAvailClinic').on('change', function() {
            doctorAvailTable.ajax.reload();
        });
    }

    // ─── Overrides DataTable ───────────────────────────────────────────
    function initOverrideTable() {
        overrideTable = $('#override-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 50,
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print'],
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('doctor-availability-overrides.data') }}",
                type: 'GET',
                data: function(d) {
                    d.staff_id = $('#filterOverrideDoctor').val();
                    d.clinic_id = $('#filterOverrideClinic').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'doctor_name', name: 'doctor_name' },
                { data: 'clinic_name', name: 'clinic_name' },
                { data: 'override_date', name: 'override_date' },
                { data: 'type_badge', name: 'type_badge' },
                { data: 'time_range', name: 'time_range' },
                { data: 'reason', name: 'reason' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ]
        });

        $('#filterOverrideDoctor, #filterOverrideClinic').on('change', function() {
            overrideTable.ajax.reload();
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Clinic Schedule CRUD
    // ═══════════════════════════════════════════════════════════════════

    function openClinicScheduleModal(id) {
        resetForm('#clinicScheduleForm');
        if (id) {
            $('#clinicScheduleModalTitle').text('Edit Clinic Schedule');
            $.get("{{ url('clinic-schedules') }}/" + id, function(data) {
                $('#cs_id').val(data.id);
                $('#cs_clinic_id').val(data.clinic_id);
                $('#cs_day_of_week').val(data.day_of_week);
                $('#cs_open_time').val(data.open_time ? data.open_time.substring(0, 5) : '08:00');
                $('#cs_close_time').val(data.close_time ? data.close_time.substring(0, 5) : '17:00');
                $('#cs_slot_duration').val(data.slot_duration_minutes);
                $('#cs_max_concurrent').val(data.max_concurrent_slots);
                $('#cs_is_active').prop('checked', data.is_active);
            });
        } else {
            $('#clinicScheduleModalTitle').text('Add Clinic Schedule');
        }
        new bootstrap.Modal('#clinicScheduleModal').show();
    }

    function editClinicSchedule(id) { openClinicScheduleModal(id); }

    $('#clinicScheduleForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#cs_id').val();
        var url = id ? "{{ url('clinic-schedules') }}/" + id : "{{ route('clinic-schedules.store') }}";
        var method = id ? 'PUT' : 'POST';
        var data = {
            clinic_id: $('#cs_clinic_id').val(),
            day_of_week: $('#cs_day_of_week').val(),
            open_time: $('#cs_open_time').val(),
            close_time: $('#cs_close_time').val(),
            slot_duration_minutes: $('#cs_slot_duration').val(),
            max_concurrent_slots: $('#cs_max_concurrent').val(),
            is_active: $('#cs_is_active').is(':checked') ? 1 : 0,
            _token: '{{ csrf_token() }}'
        };
        $.ajax({
            url: url, type: method, data: data,
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    bootstrap.Modal.getInstance(document.getElementById('clinicScheduleModal')).hide();
                    clinicScheduleTable.ajax.reload();
                } else {
                    toastr.error(res.message || 'Operation failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Validation error') : 'Server error';
                toastr.error(msg);
            }
        });
    });

    function toggleClinicSchedule(id) {
        $.post("{{ url('clinic-schedules') }}/" + id + "/toggle", { _token: '{{ csrf_token() }}' }, function(res) {
            if (res.success) { toastr.success(res.message); clinicScheduleTable.ajax.reload(); }
            else { toastr.error(res.message); }
        });
    }

    function deleteClinicSchedule(id) {
        if (!confirm('Delete this clinic schedule?')) return;
        $.ajax({
            url: "{{ url('clinic-schedules') }}/" + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) { toastr.success(res.message); clinicScheduleTable.ajax.reload(); }
                else { toastr.error(res.message); }
            },
            error: function() { toastr.error('Failed to delete.'); }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Doctor Availability CRUD
    // ═══════════════════════════════════════════════════════════════════

    function openAvailabilityModal(id) {
        resetForm('#doctorAvailForm');
        if (id) {
            $('#doctorAvailModalTitle').text('Edit Doctor Availability');
            $.get("{{ url('doctor-availability') }}/" + id, function(data) {
                $('#da_id').val(data.id);
                $('#da_staff_id').val(data.staff_id);
                $('#da_clinic_id').val(data.clinic_id);
                $('#da_day_of_week').val(data.day_of_week);
                $('#da_start_time').val(data.start_time ? data.start_time.substring(0, 5) : '08:00');
                $('#da_end_time').val(data.end_time ? data.end_time.substring(0, 5) : '17:00');
                $('#da_is_active').prop('checked', data.is_active);
            });
        } else {
            $('#doctorAvailModalTitle').text('Add Doctor Availability');
        }
        new bootstrap.Modal('#doctorAvailModal').show();
    }

    function editAvailability(id) { openAvailabilityModal(id); }

    $('#doctorAvailForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#da_id').val();
        var url = id ? "{{ url('doctor-availability') }}/" + id : "{{ route('doctor-availability.store') }}";
        var method = id ? 'PUT' : 'POST';
        var data = {
            staff_id: $('#da_staff_id').val(),
            clinic_id: $('#da_clinic_id').val(),
            day_of_week: $('#da_day_of_week').val(),
            start_time: $('#da_start_time').val(),
            end_time: $('#da_end_time').val(),
            is_active: $('#da_is_active').is(':checked') ? 1 : 0,
            _token: '{{ csrf_token() }}'
        };
        $.ajax({
            url: url, type: method, data: data,
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    bootstrap.Modal.getInstance(document.getElementById('doctorAvailModal')).hide();
                    doctorAvailTable.ajax.reload();
                } else {
                    toastr.error(res.message || 'Operation failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Validation error') : 'Server error';
                toastr.error(msg);
            }
        });
    });

    function toggleAvailability(id) {
        $.post("{{ url('doctor-availability') }}/" + id + "/toggle", { _token: '{{ csrf_token() }}' }, function(res) {
            if (res.success) { toastr.success(res.message); doctorAvailTable.ajax.reload(); }
            else { toastr.error(res.message); }
        });
    }

    function deleteAvailability(id) {
        if (!confirm('Delete this availability entry?')) return;
        $.ajax({
            url: "{{ url('doctor-availability') }}/" + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) { toastr.success(res.message); doctorAvailTable.ajax.reload(); }
                else { toastr.error(res.message); }
            },
            error: function() { toastr.error('Failed to delete.'); }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Override CRUD
    // ═══════════════════════════════════════════════════════════════════

    function openOverrideModal(id) {
        resetForm('#overrideForm');
        if (id) {
            $('#overrideModalTitle').text('Edit Availability Override');
            $.get("{{ url('doctor-availability-overrides') }}/" + id, function(data) {
                $('#ov_id').val(data.id);
                $('#ov_staff_id').val(data.staff_id);
                $('#ov_clinic_id').val(data.clinic_id || '');
                $('#ov_override_date').val(data.override_date);
                $('#ov_is_available').val(data.is_available ? '1' : '0');
                $('#ov_start_time').val(data.start_time ? data.start_time.substring(0, 5) : '');
                $('#ov_end_time').val(data.end_time ? data.end_time.substring(0, 5) : '');
                $('#ov_reason').val(data.reason || '');
            });
        } else {
            $('#overrideModalTitle').text('Add Availability Override');
        }
        new bootstrap.Modal('#overrideModal').show();
    }

    function editOverride(id) { openOverrideModal(id); }

    $('#overrideForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#ov_id').val();
        var url = id ? "{{ url('doctor-availability-overrides') }}/" + id : "{{ route('doctor-availability-overrides.store') }}";
        var method = id ? 'PUT' : 'POST';
        var data = {
            staff_id: $('#ov_staff_id').val(),
            clinic_id: $('#ov_clinic_id').val() || null,
            override_date: $('#ov_override_date').val(),
            is_available: $('#ov_is_available').val(),
            start_time: $('#ov_start_time').val() || null,
            end_time: $('#ov_end_time').val() || null,
            reason: $('#ov_reason').val(),
            _token: '{{ csrf_token() }}'
        };
        $.ajax({
            url: url, type: method, data: data,
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    bootstrap.Modal.getInstance(document.getElementById('overrideModal')).hide();
                    overrideTable.ajax.reload();
                } else {
                    toastr.error(res.message || 'Operation failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? (xhr.responseJSON.message || 'Validation error') : 'Server error';
                toastr.error(msg);
            }
        });
    });

    function deleteOverride(id) {
        if (!confirm('Delete this override?')) return;
        $.ajax({
            url: "{{ url('doctor-availability-overrides') }}/" + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                if (res.success) { toastr.success(res.message); overrideTable.ajax.reload(); }
                else { toastr.error(res.message); }
            },
            error: function() { toastr.error('Failed to delete.'); }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Utilities
    // ═══════════════════════════════════════════════════════════════════

    function resetForm(selector) {
        $(selector)[0].reset();
        $(selector).find('input[type=hidden]').val('');
        $(selector).find('input[type=checkbox]').prop('checked', true);
    }
</script>
@endsection

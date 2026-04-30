@extends('admin.layouts.app')
@section('title', 'Staff Registry')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Staff Registry')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .filter-bar { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .filter-bar select, .filter-bar input { max-width: 170px; display: inline-block; font-size: 0.82rem; }
        .registry-table th {
            font-weight: 600; font-size: 0.78rem; text-transform: uppercase;
            letter-spacing: 0.5px; color: #6b7280; background: #f9fafb;
        }
        .registry-table td { vertical-align: middle !important; font-size: 0.84rem; }
        .stat-card { border-radius: 10px; border: none; transition: transform 0.15s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .stat-card.active-filter { box-shadow: 0 0 0 3px rgba(255,255,255,0.8), 0 4px 12px rgba(0,0,0,0.2); transform: translateY(-3px); }
        .insight-card { border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; }
        .insight-card h6 { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 0.5rem; }
        .gender-bar { height: 8px; border-radius: 4px; background: #e5e7eb; overflow: hidden; display: flex; }
        .gender-bar-fill { height: 100%; transition: width 0.6s ease; }
        .dept-bar { height: 6px; border-radius: 3px; background: #e5e7eb; margin-bottom: 4px; }
        .dept-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--primary-color), #667eea); transition: width 0.6s ease; }
        .detail-panel { padding: 1rem 1.5rem; background: #f8fafc; }
        .detail-panel .detail-label { font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.3px; color: #6b7280; margin-bottom: 2px; }
        .detail-panel .detail-value { font-size: 0.85rem; color: #1f2937; }
        .detail-panel .date-chip { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 500; margin: 2px; }
        .detail-panel .date-chip.overdue { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .detail-panel .date-chip.ok { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .detail-panel .date-chip.info { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        td.details-control { cursor: pointer; text-align: center; color: #6b7280; }
        td.details-control::before { content: '\f0142'; font-family: 'Material Design Icons'; font-size: 1.2rem; transition: transform 0.2s; }
        tr.shown td.details-control::before { content: '\f0140'; color: var(--primary-color); }
        .registry-table td.details-control:hover { color: var(--primary-color); }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">

    @include('admin.hr.partials.hr-subnav')

    {{-- Page Header --}}
    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-clipboard-list-outline text-primary"></i> Staff Registry
                </h2>
                <p class="text-muted mb-0">Comprehensive HR registry &mdash; click <i class="mdi mdi-chevron-right"></i> on any row to expand full details</p>
            </div>
            <div class="d-flex" style="gap: 0.5rem;">
                <a href="{{ route('hr.master-import.index') }}" class="btn btn-outline-info btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-file-import mr-1"></i> Import
                </a>
                <a href="{{ route('hr.staff-registry.export') }}" class="btn btn-outline-success btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-download mr-1"></i> Export CSV
                </a>
                <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-plus mr-1"></i> Add Staff
                </a>
            </div>
        </div>

        <div class="card-body">

            {{-- ROW 1: Alert Stat Cards (clickable to filter) --}}
            <div class="row mb-3">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Total Active</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-total">&mdash;</div>
                                </div>
                                <i class="mdi mdi-account-group" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="promotion_due" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Promotion Due</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-promotion">&mdash;</div>
                                </div>
                                <i class="mdi mdi-arrow-up-bold-circle" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="confirmation_due" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Confirmation Due</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-confirmation">&mdash;</div>
                                </div>
                                <i class="mdi mdi-account-check" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="license_expiring" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">License Expiring</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-license">&mdash;</div>
                                </div>
                                <i class="mdi mdi-card-account-details" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="medical_exam_due" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Medical Exam Due</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-medical">&mdash;</div>
                                </div>
                                <i class="mdi mdi-stethoscope" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <div class="card-modern stat-card" data-alert="retiring_soon" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);">
                        <div class="card-body text-white py-3 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div style="font-size: 0.7rem; opacity: 0.8;">Retiring Soon</div>
                                    <div class="font-weight-bold" style="font-size: 1.5rem;" id="cnt-retiring">&mdash;</div>
                                </div>
                                <i class="mdi mdi-account-clock" style="font-size: 1.8rem; opacity: 0.6;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW 2: Workforce Insights --}}
            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 mb-2">
                    <div class="insight-card p-3">
                        <h6><i class="mdi mdi-gender-male-female mr-1"></i> Gender Distribution</h6>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size:0.8rem;"><i class="mdi mdi-gender-male text-primary"></i> Male: <strong id="cnt-male">&mdash;</strong></span>
                            <span style="font-size:0.8rem;"><i class="mdi mdi-gender-female text-danger"></i> Female: <strong id="cnt-female">&mdash;</strong></span>
                        </div>
                        <div class="gender-bar">
                            <div class="gender-bar-fill" id="gender-bar-male" style="width:50%; background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div>
                            <div class="gender-bar-fill" id="gender-bar-female" style="width:50%; background: linear-gradient(90deg, #f472b6, #ec4899);"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-2">
                    <div class="insight-card p-3">
                        <h6><i class="mdi mdi-clock-outline mr-1"></i> Service & Payroll</h6>
                        <div class="d-flex justify-content-between mb-1">
                            <div>
                                <div style="font-size:0.7rem;color:#6b7280;">Avg Service</div>
                                <div class="font-weight-bold" style="font-size:1.1rem;" id="cnt-avg-service">&mdash;</div>
                            </div>
                            <div class="text-right">
                                <div style="font-size:0.7rem;color:#6b7280;">Monthly Payroll</div>
                                <div class="font-weight-bold" style="font-size:1.1rem;" id="cnt-payroll">&mdash;</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 mb-2">
                    <div class="insight-card p-3">
                        <h6><i class="mdi mdi-domain mr-1"></i> Top Departments</h6>
                        <div id="dept-bars" style="max-height:60px;overflow:hidden;"></div>
                    </div>
                </div>
            </div>

            {{-- Filter Bar --}}
            <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 mr-2 font-weight-bold" style="font-size:0.82rem;"><i class="mdi mdi-filter-outline"></i> Filters:</label>
                <select id="filter-department" class="form-control form-control-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select id="filter-unit" class="form-control form-control-sm">
                    <option value="">All Units</option>
                </select>
                <select id="filter-cadre" class="form-control form-control-sm">
                    <option value="">All Cadres</option>
                    @foreach($cadres as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select id="filter-grade" class="form-control form-control-sm">
                    <option value="">All Grades</option>
                    @foreach($gradeLevels as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <select id="filter-status" class="form-control form-control-sm">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="resigned">Resigned</option>
                    <option value="terminated">Terminated</option>
                    <option value="retired">Retired</option>
                </select>
                <select id="filter-gender" class="form-control form-control-sm">
                    <option value="">All Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                <select id="filter-type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    <option value="full_time">Full Time</option>
                    <option value="part_time">Part Time</option>
                    <option value="contract">Contract</option>
                    <option value="intern">Intern</option>
                </select>
                <input type="hidden" id="filter-alert" value="">
                <button id="clearAllFilters" class="btn btn-outline-secondary btn-sm" style="border-radius:6px;font-size:0.8rem;">
                    <i class="mdi mdi-close-circle mr-1"></i> Clear
                </button>
            </div>

            {{-- DataTable --}}
            <div class="table-responsive">
                <table id="registryTable" class="table table-sm table-hover registry-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            <th>Staff</th>
                            <th>Department / Unit</th>
                            <th>Grade / Cadre</th>
                            <th>Hired</th>
                            <th>Yrs</th>
                            <th>Salary</th>
                            <th>Status</th>
                            <th style="width: 60px;">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    var allUnits = @json($units);

    // Load stats + insights
    $.getJSON('{{ route("hr.staff-registry.alerts") }}', function(d) {
        $('#cnt-total').text(d.total_active);
        $('#cnt-promotion').text(d.promotion_due);
        $('#cnt-confirmation').text(d.confirmation_due);
        $('#cnt-license').text(d.license_expiring);
        $('#cnt-medical').text(d.medical_exam_due);
        $('#cnt-retiring').text(d.retiring_soon);
        $('#cnt-male').text(d.male);
        $('#cnt-female').text(d.female);
        var total = d.male + d.female;
        if (total> 0) {
            var malePct = Math.round(d.male / total * 100);
            $('#gender-bar-male').css('width', malePct + '%');
            $('#gender-bar-female').css('width', (100 - malePct) + '%');
        }
        $('#cnt-avg-service').text(d.avg_service_years + ' yrs');
        $('#cnt-payroll').html('&#8358;' + Number(d.total_monthly_payroll).toLocaleString());
        if (d.dept_distribution && d.dept_distribution.length) {
            var maxCnt = d.dept_distribution[0].count;
            var html = '';
            d.dept_distribution.forEach(function(dept) {
                var pct = Math.round(dept.count / maxCnt * 100);
                html += '<div class="d-flex align-items-center mb-1" style="font-size:0.75rem;">' +
                    '<span style="width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + dept.name + '">' + dept.name + '</span>' +
                    '<div class="dept-bar flex-fill mx-2"><div class="dept-bar-fill" style="width:' + pct + '%;"></div></div>' +
                    '<span class="font-weight-bold" style="min-width:25px;text-align:right;">' + dept.count + '</span></div>';
            });
            $('#dept-bars').html(html);
        }
    });

    // DataTable
    var table = $('#registryTable').DataTable({
        "dom": '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
        "iDisplayLength": 50,
        "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
        "buttons": [
            { extend: 'pageLength', className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-success', text: '<i class="mdi mdi-file-excel"></i> Excel', exportOptions: { columns: [1,2,3,4,5,6,7] } },
            { extend: 'pdf', className: 'btn btn-sm btn-outline-danger', text: '<i class="mdi mdi-file-pdf"></i> PDF', exportOptions: { columns: [1,2,3,4,5,6,7] } },
            { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="mdi mdi-printer"></i> Print', exportOptions: { columns: [1,2,3,4,5,6,7] } }
        ],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('hr.staff-registry.data') }}",
            "type": "GET",
            "data": function(d) {
                d.department_id = $('#filter-department').val();
                d.unit_id = $('#filter-unit').val();
                d.cadre_id = $('#filter-cadre').val();
                d.grade_level_id = $('#filter-grade').val();
                d.employment_status = $('#filter-status').val();
                d.gender = $('#filter-gender').val();
                d.employment_type = $('#filter-type').val();
                d.alert_type = $('#filter-alert').val();
            }
        },
        "columns": [
            { data: null, className: 'details-control', orderable: false, searchable: false, defaultContent: '' },
            { data: 'full_name' },
            { data: 'department_name', render: function(data, type, row) {
                return '<span class="font-weight-medium">' + (data || '') + '</span>' +
                    (row.unit_name ? '<br><small class="text-muted">' + row.unit_name + '</small>' : '');
            }},
            { data: 'grade_level_name', render: function(data, type, row) {
                return (data || '&mdash;') + (row.cadre_name ? '<br><small class="text-muted">' + row.cadre_name + '</small>' : '');
            }},
            { data: 'date_hired', render: function(data) {
                return data ? '<span style="font-size:0.8rem;">' + data + '</span>' : '&mdash;';
            }},
            { data: 'years_of_service', render: function(data) {
                if (!data) return '&mdash;';
                var yrs = parseFloat(data);
                var color = yrs>= 30 ? '#dc2626' : yrs>= 20 ? '#d97706' : yrs>= 10 ? '#059669' : '#6b7280';
                return '<span style="color:' + color + ';font-weight:600;">' + data + '</span>';
            }},
            { data: 'salary', render: function(data) {
                return data ? '<span style="font-size:0.8rem;">' + data + '</span>' : '<span class="text-muted">&mdash;</span>';
            }},
            { data: 'employment_status', render: function(data, type, row) {
                var colors = {active:'success',suspended:'warning',resigned:'secondary',terminated:'danger',retired:'dark'};
                var badge = '<span class="badge badge-' + (colors[data] || 'light') + '">' + (data ? data.charAt(0).toUpperCase() + data.slice(1) : '') + '</span>';
                return badge + (row.alerts ? '<br>' + row.alerts : '');
            }},
            { data: 'action', orderable: false, searchable: false }
        ],
        "order": [[1, 'asc']],
        "language": {
            "processing": '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Loading...',
            "emptyTable": "No staff members found",
            "zeroRecords": "No matching staff found"
        }
    });

    // Expandable child row
    $('#registryTable tbody').on('click', 'td.details-control', function() {
        var tr = $(this).closest('tr');
        var row = table.row(tr);
        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
        } else {
            row.child('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>').show();
            tr.addClass('shown');
            var staffId = row.data().staff_id;
            $.getJSON("{{ url('hr/staff-registry/staff') }}/" + staffId + "/detail", function(d) {
                row.child(buildDetailPanel(d)).show();
            }).fail(function() {
                row.child('<div class="text-center py-3 text-danger">Failed to load details</div>').show();
            });
        }
    });

    function buildDetailPanel(d) {
        var h = '<div class="detail-panel"><div class="row">';
        // Col 1: Personal + Contact
        h += '<div class="col-md-3">';
        h += '<div><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-account mr-1"></i>Personal</strong>';
        h += fld('Employee ID', d.employee_id);
        h += fld('Gender', d.gender);
        h += fld('DOB / Age', d.date_of_birth ? d.date_of_birth + ' (Age ' + d.age + ')' : null);
        h += fld('Marital Status', d.marital_status);
        h += fld('NIN', d.national_id);
        h += fld('License #', d.license_number);
        h += '</div>';
        h += '<div class="mt-2"><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-phone mr-1"></i>Contact</strong>';
        h += fld('Phone', d.phone);
        h += fld('Email', d.email);
        h += fld('Address', d.home_address);
        if (d.next_of_kin) h += fld('Next of Kin', d.next_of_kin.name + ' (' + d.next_of_kin.relationship + ')' + (d.next_of_kin.phone ? ' &middot; ' + d.next_of_kin.phone : ''));
        h += '</div></div>';
        // Col 2: Employment + Salary
        h += '<div class="col-md-3">';
        h += '<div><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-briefcase mr-1"></i>Employment</strong>';
        h += fld('Type', d.employment_type);
        h += fld('Location', d.job_location);
        h += fld('Responsibility', d.responsibility);
        h += fld('Entry Level', d.entry_level);
        h += fld('Current Level', d.current_level);
        h += '</div>';
        h += '<div class="mt-2"><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-cash mr-1"></i>Salary</strong>';
        h += fld('Monthly', d.salary_monthly ? '&#8358;' + d.salary_monthly : null);
        h += fld('Annual', d.salary_annual ? '&#8358;' + d.salary_annual : null);
        h += '</div></div>';
        // Col 3: Key Dates
        h += '<div class="col-md-3">';
        h += '<div><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-calendar-clock mr-1"></i>Key Dates</strong>';
        if (d.key_dates) {
            $.each(d.key_dates, function(label, val) {
                var cls = val.indexOf('\u26A0') !== -1 ? 'overdue' : (label.indexOf('Confirmed') !== -1 ? 'ok' : 'info');
                h += '<div class="mb-1"><span class="detail-label">' + label + '</span><br><span class="date-chip ' + cls + '">' + val.replace(' \u26A0 ', ' <i class="mdi mdi-alert"></i> ') + '</span></div>';
            });
        }
        h += '</div></div>';
        // Col 4: Records Summary
        h += '<div class="col-md-3">';
        h += '<div><strong style="color:var(--primary-color);font-size:0.8rem;"><i class="mdi mdi-chart-box mr-1"></i>Records</strong>';
        if (d.entry_qualification) {
            h += '<div class="mb-1"><span class="detail-label">Entry Qualification</span><div class="detail-value">' + d.entry_qualification.name;
            if (d.entry_qualification.field) h += ' (' + d.entry_qualification.field + ')';
            if (d.entry_qualification.verified) h += ' <i class="mdi mdi-check-circle text-success"></i>';
            h += '</div></div>';
        }
        h += fld('Additional Quals', d.additional_qualifications> 0 ? d.additional_qualifications + ' recorded' : null);
        h += fld('Promotions', d.total_promotions> 0 ? d.total_promotions + ' recorded' : null);
        h += fld('Trainings', d.total_trainings> 0 ? d.completed_trainings + '/' + d.total_trainings + ' completed' : null);
        if (d.latest_medical_exam) h += fld('Last Medical', d.latest_medical_exam.date + ' &mdash; ' + d.latest_medical_exam.result.charAt(0).toUpperCase() + d.latest_medical_exam.result.slice(1));
        h += fld('Open Follow-ups', d.open_follow_ups> 0 ? '<span class="text-danger font-weight-bold">' + d.open_follow_ups + ' open</span>' : '0');
        h += '<div class="mt-2"><a href="' + d.profile_url + '" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:0.78rem;"><i class="mdi mdi-account-search mr-1"></i> Full Tracking Profile</a></div>';
        h += '</div></div>';
        h += '</div></div>';
        return h;
    }

    function fld(label, value) {
        if (!value) return '';
        return '<div class="mb-1"><span class="detail-label">' + label + '</span><div class="detail-value">' + value + '</div></div>';
    }

    // Filter changes
    $('#filter-department, #filter-unit, #filter-cadre, #filter-grade, #filter-status, #filter-gender, #filter-type').on('change', function() {
        table.ajax.reload();
    });

    // Cascading unit filter
    $('#filter-department').on('change', function() {
        var deptId = $(this).val();
        var $u = $('#filter-unit');
        $u.html('<option value="">All Units</option>');
        if (deptId) {
            allUnits.filter(function(u) { return u.department_id == deptId; }).forEach(function(u) {
                $u.append('<option value="' + u.id + '">' + u.name + '</option>');
            });
        }
    });

    // Clickable stat cards filter table
    $('.stat-card[data-alert]').on('click', function() {
        var alertType = $(this).data('alert');
        var currentAlert = $('#filter-alert').val();
        if (currentAlert === alertType) {
            $('#filter-alert').val('');
            $('.stat-card').removeClass('active-filter');
        } else {
            $('#filter-alert').val(alertType);
            $('.stat-card').removeClass('active-filter');
            if (alertType) $(this).addClass('active-filter');
        }
        table.ajax.reload();
    });

    // Clear all filters
    $('#clearAllFilters').on('click', function() {
        $('#filter-department, #filter-unit, #filter-cadre, #filter-grade, #filter-status, #filter-gender, #filter-type').val('');
        $('#filter-alert').val('');
        $('.stat-card').removeClass('active-filter');
        table.ajax.reload();
    });
});
</script>
@endsection

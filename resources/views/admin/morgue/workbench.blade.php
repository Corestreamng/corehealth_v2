@extends('admin.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-md-12 grid-margin">
            <div class="d-flex justify-content-between flex-wrap">
                <div class="d-flex align-items-end flex-wrap">
                    <div class="mr-md-3 mr-xl-5">
                        <h2>Morgue Workbench</h2>
                        <p class="mb-md-0">Manage deceased patients and mortuary services.</p>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                    <button class="btn btn-dark mt-2 mt-xl-0" onclick="showMorgueAdmissionModal()">
                        <i class="mdi mdi-emoticon-dead"></i> Direct Morgue Admission (BID)
                    </button>
                    <button class="btn btn-primary mt-2 mt-xl-0" id="refresh-btn">
                        <i class="mdi mdi-refresh"></i> Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="row">
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-danger card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Pending Admissions <i class="mdi mdi-alert-circle-outline mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-pending">0</h2>
                    <p class="card-text">Candidates awaiting morgue intake</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-info card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Currently in Morgue <i class="mdi mdi-emoticon-dead mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-active">0</h2>
                    <p class="card-text">Bodies currently under care</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 stretch-card grid-margin">
            <div class="card bg-gradient-success card-img-holder text-white">
                <div class="card-body">
                    <h4 class="font-weight-normal mb-3">Released Today <i class="mdi mdi-check-circle-outline mdi-24px float-right"></i></h4>
                    <h2 class="mb-5" id="stat-released">0</h2>
                    <p class="card-text">Final discharges completed today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pending Admissions -->
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-danger"><i class="mdi mdi-clock-alert"></i> Pending Admissions</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="pending-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>File No.</th>
                                    <th>Death Type</th>
                                    <th>Date/Time of Death</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-body">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Admissions -->
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card border-top border-info border-3">
                <div class="card-body">
                    <h4 class="card-title text-info"><i class="mdi mdi-account-multiple"></i> Active Morgue Residents</h4>
                    <div class="table-responsive">
                        <table class="table table-hover" id="active-table">
                            <thead>
                                <tr>
                                    <th>Patient Name</th>
                                    <th>File No.</th>
                                    <th>Fridge/Tray</th>
                                    <th>Admitted At</th>
                                    <th>Days Spent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="active-body">
                                <!-- Loaded via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admit Modal -->
<div class="modal fade" id="admitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Admit to Morgue</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="admit-form">
                    <input type="hidden" id="admit-death-record-id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient Name</label>
                        <div id="admit-patient-name" class="form-control-plaintext text-primary fw-bold"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fridge No.</label>
                            <input type="text" class="form-control" id="admit-fridge" placeholder="e.g. F-102">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tray No.</label>
                            <input type="text" class="form-control" id="admit-tray" placeholder="e.g. T-05">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daily Service Fee (Billing) <span class="text-danger">*</span></label>
                        <select class="form-select select2-morgue" id="admit-daily-service" required>
                            <option value="">-- Select Daily Rate --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admissions Notes</label>
                        <textarea class="form-control" id="admit-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-save-admission">Admit Body</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Add Morgue Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="service-form">
                    <input type="hidden" id="service-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Select Service</label>
                        <select class="form-select select2-morgue" id="morgue-service-id" required>
                            <!-- Loaded via JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="morgue-service-qty" value="1" min="1">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="btn-save-service">Add to Bill</button>
            </div>
        </div>
    </div>
</div>

<!-- Release Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Release Body</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="release-form">
                    <input type="hidden" id="release-admission-id">
                    <div class="mb-3">
                        <label class="form-label">Released To (Name) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="release-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="release-phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Release Notes</label>
                        <textarea class="form-control" id="release-notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-confirm-release">Confirm Release</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.patient-form-modal')
@endsection

@push('styles')
<style>
    .card-modern {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: none;
        transition: transform 0.2s;
    }
    .bg-gradient-danger { background: linear-gradient(45deg, #ff5252, #f44336) !important; }
    .bg-gradient-info { background: linear-gradient(45deg, #40c4ff, #2196f3) !important; }
    .bg-gradient-success { background: linear-gradient(45deg, #66bb6a, #43a047) !important; }

    .table th { font-weight: 700; color: #333; }
    .btn-action { padding: 0.4rem 0.8rem; border-radius: 6px; }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Merge (do not replace) — modal partial already set registerUrl, emergencyIntakeUrl, etc.
        $.extend(window.patientFormConfig, {
            submitUrl: '{{ route("morgue.admit") }}',
            onSuccess: function(patientId, mode) {
                toastr.success("Patient record created/admitted successfully");
                $("#patientFormModal").modal("hide");
                loadData();
            }
        });
        loadData();

        $('#refresh-btn').click(function() {
            loadData();
        });

        function loadData() {
            $.get('{{ route("morgue.queue") }}', function(response) {
                renderPending(response.pending);
                renderActive(response.active);
                $('#stat-pending').text(response.pending.length);
                $('#stat-active').text(response.active.length);
            });
        }

        function loadServices(patientId, targetSelect) {
            $(targetSelect).html('<option value="">Loading services...</option>');
            $.get('{{ route("morgue.services") }}', { patient_id: patientId }, function(services) {
                let html = '<option value="">-- Select Service --</option>';
                services.forEach(s => {
                    const basePrice = s.price ? parseFloat(s.price.sale_price) : 0;
                    const payable = parseFloat(s.payable_amount);
                    const claims = parseFloat(s.claims_amount);

                    let priceText = `Base: ₦${basePrice.toLocaleString()}`;
                    if (s.coverage_mode !== 'cash') {
                        priceText = `Payable: ₦${payable.toLocaleString()} | HMO: ₦${claims.toLocaleString()}`;
                    }

                    html += `<option value="${s.id}">${s.service_name} (${priceText})</option>`;
                });
                $(targetSelect).html(html);
            });
        }

        function renderPending(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">No pending admissions</td></tr>';
            } else {
                data.forEach(r => {
                    html += `
                        <tr>
                            <td class="fw-bold">${r.name}</td>
                            <td>${r.file_no}</td>
                            <td><span class="badge bg-danger">${r.death_type}</span></td>
                            <td>${r.date_of_death} ${r.time_of_death}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="openAdmitModal(${r.id}, '${r.name.replace(/'/g, "\\'")}', ${r.patient_id})">
                                    <i class="mdi mdi-login"></i> Admit
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#pending-body').html(html);
        }

        function renderActive(data) {
            let html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-4 text-muted">Morgue is currently empty</td></tr>';
            } else {
                data.forEach(a => {
                    html += `
                        <tr>
                            <td class="fw-bold">${a.name}</td>
                            <td>${a.file_no}</td>
                            <td>F: ${a.fridge_no || '-'} / T: ${a.tray_no || '-'}</td>
                            <td>${a.admitted_at}</td>
                            <td><span class="badge bg-info">${a.days_spent} days</span></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-info" onclick="openServiceModal(${a.id}, ${a.patient_id})">
                                        <i class="mdi mdi-plus-circle"></i> Service
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="openReleaseModal(${a.id})">
                                        <i class="mdi mdi-logout"></i> Release
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#active-body').html(html);
        }

        window.openAdmitModal = function(id, name, patientId) {
            $('#admit-death-record-id').val(id);
            $('#admit-patient-name').text(name);
            loadServices(patientId, '#admit-daily-service');
            $('#admitModal').modal('show');
        };

        window.openServiceModal = function(id, patientId) {
            $('#service-admission-id').val(id);
            loadServices(patientId, '#morgue-service-id');
            $('#serviceModal').modal('show');
        };

        window.openReleaseModal = function(id) {
            $('#release-admission-id').val(id);
            $('#releaseModal').modal('show');
        };

        $('#btn-save-admission').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                death_record_id: $('#admit-death-record-id').val(),
                fridge_no: $('#admit-fridge').val(),
                tray_no: $('#admit-tray').val(),
                daily_service_id: $('#admit-daily-service').val(),
                notes: $('#admit-notes').val()
            };

            if (!data.daily_service_id) {
                toastr.warning('Please select a daily rate.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

            $.post('{{ route("morgue.admit") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#admitModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error processing admission.');
            }).always(() => {
                $(this).prop('disabled', false).text('Admit Body');
            });
        });

        $('#btn-save-service').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#service-admission-id').val(),
                service_id: $('#morgue-service-id').val(),
                qty: $('#morgue-service-qty').val()
            };

            if (!data.service_id) {
                toastr.warning('Please select a service.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

            $.post('{{ route("morgue.add-service") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#serviceModal').modal('hide');
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Add to Bill');
            });
        });

        $('#btn-confirm-release').click(function() {
            const data = {
                _token: '{{ csrf_token() }}',
                morgue_admission_id: $('#release-admission-id').val(),
                released_to_name: $('#release-name').val(),
                released_to_phone: $('#release-phone').val(),
                release_notes: $('#release-notes').val()
            };

            if (!data.released_to_name) {
                toastr.warning('Please enter releasee name.');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Releasing...');

            $.post('{{ route("morgue.release") }}', data, function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#releaseModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(res.message);
                }
            }).always(() => {
                $(this).prop('disabled', false).text('Confirm Release');
            });
        });
    });
</script>
@endpush

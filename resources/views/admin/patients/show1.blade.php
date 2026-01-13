@extends('admin.layouts.app')
@section('title', 'Patients Profile')
@section('page_name', 'Patients')
@section('subpage_name', 'Show Patient')
@section('content')
@php
    $section = request()->get('section');
@endphp

<div class="col-12">
    <div class="card-modern">
        <div class="card-header">
            <h3 class="card-title">Patient Data</h3>
        </div>
    </div>

    {{-- Nav tabs --}}
    <ul class="nav nav-tabs" id="patientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ !$section || $section == 'patientInfo' ? 'active' : '' }}" id="patientInfo-tab"
                data-bs-toggle="tab" data-bs-target="#patientInfo" type="button" role="tab">
                <i class="fa fa-user me-1"></i> Patient Info
            </button>
        </li>
        @can('see-vitals')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'vitalsCardBody' ? 'active' : '' }}" id="vitals-tab"
                    data-bs-toggle="tab" data-bs-target="#vitalsCardBody" type="button" role="tab">
                    <i class="fa fa-heartbeat me-1"></i> Vitals
                </button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseChartCardBody' ? 'active' : '' }}" id="nurseChart-tab"
                    data-bs-toggle="tab" data-bs-target="#nurseChartCardBody" type="button" role="tab">
                    <i class="fa fa-notes-medical me-1"></i> Nurse Chart
                </button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'injImmHistoryCardBody' ? 'active' : '' }}" id="injImmHistory-tab"
                    data-bs-toggle="tab" data-bs-target="#injImmHistoryCardBody" type="button" role="tab">
                    <i class="fa fa-syringe me-1"></i> Inj/Imm History
                </button>
            </li>
        @endcan
        @can('see-accounts')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'accountsCardBody' ? 'active' : '' }}" id="accounts-tab"
                    data-bs-toggle="tab" data-bs-target="#accountsCardBody" type="button" role="tab">
                    <i class="fa fa-money-bill-wave me-1"></i> Accounts
                </button>
            </li>
        @endcan
        @can('see-admissions')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'addmissionsCardBody' ? 'active' : '' }}" id="admissions-tab"
                    data-bs-toggle="tab" data-bs-target="#addmissionsCardBody" type="button" role="tab">
                    <i class="fa fa-bed me-1"></i> Admission History
                </button>
            </li>
        @endcan
        @can('see-procedures')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'wardNotesCardBody' ? 'active' : '' }}" id="procedures-tab"
                    data-bs-toggle="tab" data-bs-target="#wardNotesCardBody" type="button" role="tab">
                    <i class="fa fa-procedures me-1"></i> Procedure Notes
                </button>
            </li>
        @endcan
        @can('see-nursing-notes')
        <!-- replaced by new nurse notes -->
            <!-- <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseingNotesCardBody' ? 'active' : '' }}" id="nursing-tab"
                    data-bs-toggle="tab" data-bs-target="#nurseingNotesCardBody" type="button" role="tab">
                    <i class="fa fa-user-nurse me-1"></i> Nursing Notes
                </button>
            </li> -->
        @endcan
        @can('see-doctor-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'doctorNotesCardBody' ? 'active' : '' }}" id="doctor-tab"
                    data-bs-toggle="tab" data-bs-target="#doctorNotesCardBody" type="button" role="tab">
                    <i class="fa fa-stethoscope me-1"></i> Doctor Notes
                </button>
            </li>
        @endcan
        @can('see-prescriptions')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'prescriptionsNotesCardBody' ? 'active' : '' }}" id="prescriptions-tab"
                    data-bs-toggle="tab" data-bs-target="#prescriptionsNotesCardBody" type="button" role="tab">
                    <i class="fa fa-pills me-1"></i> Prescriptions
                </button>
            </li>
        @endcan
        @can('see-investigations')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'investigationsCardBody' ? 'active' : '' }}" id="investigations-tab"
                    data-bs-toggle="tab" data-bs-target="#investigationsCardBody" type="button" role="tab">
                    <i class="fa fa-flask me-1"></i> Investigations
                </button>
            </li>
        @endcan
        @can('see-investigations')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'imagingCardBody' ? 'active' : '' }}" id="imaging-tab"
                    data-bs-toggle="tab" data-bs-target="#imagingCardBody" type="button" role="tab">
                    <i class="fa fa-x-ray me-1"></i> Imaging
                </button>
            </li>
        @endcan
    </ul>

    {{-- Tab content --}}
    <div class="tab-content pt-3">
        <div class="tab-pane fade {{ !$section || $section == 'patientInfo' ? 'show active' : '' }}" id="patientInfo" role="tabpanel">
            <div class="card-modern">
                <div class="card-body">@include('admin.patients.partials.patient_info')</div>
            </div>
        </div>
        @can('see-vitals')
            <div class="tab-pane fade {{ $section == 'vitalsCardBody' ? 'show active' : '' }}" id="vitalsCardBody" role="tabpanel">
                <div class="mt-2">
                    @include('admin.partials.unified_vitals', ['patient' => $patient])
                </div>
            </div>
            @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var vitalsTab = document.getElementById('vitals-tab');
                    if(vitalsTab){
                         vitalsTab.addEventListener('shown.bs.tab', function (event) {
                            if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }});
                            }
                        });
                         // Handle initial load if tab is active
                        if (vitalsTab.classList.contains('active')) {
                            if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }});
                            }
                        }
                    }
                });
             </script>
             @endpush
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'nurseChartCardBody' ? 'show active' : '' }}" id="nurseChartCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.nurse_chart')</div>
                </div>
            </div>
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'injImmHistoryCardBody' ? 'show active' : '' }}" id="injImmHistoryCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.injection_immunization_history', ['patient' => $patient])</div>
                </div>
            </div>
        @endcan
        @can('see-accounts')
            <div class="tab-pane fade {{ $section == 'accountsCardBody' ? 'show active' : '' }}" id="accountsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.accounts')</div>
                </div>
            </div>
        @endcan
        @can('see-admissions')
            <div class="tab-pane fade {{ $section == 'addmissionsCardBody' ? 'show active' : '' }}" id="addmissionsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.admissions')</div>
                </div>
            </div>
        @endcan
        @can('see-procedures')
            <div class="tab-pane fade {{ $section == 'wardNotesCardBody' ? 'show active' : '' }}" id="wardNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.procedure_notes')</div>
                </div>
            </div>
        @endcan
        <!-- replaced by new enhbced nurse  notes -->
        <!-- @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'nurseingNotesCardBody' ? 'show active' : '' }}" id="nurseingNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.nurse_notes')</div>
                </div>
            </div>
        @endcan -->
        @can('see-doctor-notes')
            <div class="tab-pane fade {{ $section == 'doctorNotesCardBody' ? 'show active' : '' }}" id="doctorNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.doctor_notes')</div>
                </div>
            </div>
        @endcan
        @can('see-prescriptions')
            <div class="tab-pane fade {{ $section == 'prescriptionsNotesCardBody' ? 'show active' : '' }}" id="prescriptionsNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.presc')</div>
                </div>
            </div>
        @endcan
        @can('see-investigations')
            <div class="tab-pane fade {{ $section == 'investigationsCardBody' ? 'show active' : '' }}" id="investigationsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.invest')</div>
                </div>
            </div>
        @endcan
        @can('see-investigations')
            <div class="tab-pane fade {{ $section == 'imagingCardBody' ? 'show active' : '' }}" id="imagingCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.imaging')</div>
                </div>
            </div>
        @endcan
    </div>

    {{-- Back button --}}
    <div class="row mt-4">
        <div class="col-12">
            <a href="{{ route('patient.index') }}" class="btn btn-danger"><i class="fa fa-close"></i> Back</a>
        </div>
    </div>
</div>

@include('admin.patients.partials.modals')
@endsection



@section('scripts')
    <!-- jQuery (needed for product search & DataTables) -->
    <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
    <script>
        ClassicEditor
            .create(document.querySelector('.classic-editor'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                },
                cloudServices: {
                    // All predefined builds include the Easy Image feature.
                    // Provide correct configuration values to use it.
                    // tokenUrl: 'https://example.com/cs-token-endpoint',
                    // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
                    // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
                    // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
                }
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(err => {
                console.error(err);
            });
    </script>

    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
        $(function() {
            $('#encounter_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('EncounterHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#payment_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patientPaymentHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "user_id",
                        name: "user_id"
                    },
                    {
                        data: "total",
                        name: "total"
                    },
                    {
                        data: "payment_type",
                        name: "payment_type"
                    },
                    {
                        data: "product_or_service_request",
                        name: "product_or_service_request"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#investigation_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investigationHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#admission-request-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patient-admission-requests-list', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                    data: "info",
                    name: "info",
                    orderable: false
                }],
                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#misc_bill_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('miscBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#misc_bill_bills_hist').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('miscBillHistList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_dispense').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescDispenseList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function add_to_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) + parseFloat(v);
            $('#presc_bill_tot').val(new_tot);
            $('#presc_billed_tot').val(new_tot);
        }

        function subtract_from_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) - parseFloat(v);
            //in order to prevent negative values
            if (new_tot > 0) {
                $('#presc_bill_tot').val(new_tot);
                $('#presc_billed_tot').val(new_tot);
            } else {
                $('#presc_bill_tot').val(0);
                $('#presc_billed_tot').val(0);
            }
        }

        function checkPrescBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_presc_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_presc_bill(row_val);
            }
        }
    </script>
    <script>
        function add_to_total_misc_bill(v) {
            let new_tot = parseFloat($('#misc_bill_tot').val()) + parseFloat(v);
            $('#misc_bill_tot').val(new_tot);
            $('#misc_billed_tot').val(new_tot);
        }

        function subtract_from_total_misc_bill(v) {
            let new_tot = parseFloat($('#misc_bill_tot').val()) - parseFloat(v);
            //in order to prevent negative values
            if (new_tot > 0) {
                $('#misc_bill_tot').val(new_tot);
                $('#misc_billed_tot').val(new_tot);
            } else {
                $('#misc_bill_tot').val(0);
                $('#misc_billed_tot').val(0);
            }
        }

        function checkMiscBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_misc_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_misc_bill(row_val);
            }
        }
    </script>
    <script>
        function removeProdRow(obj, price) {
            subtract_from_total_presc_bill(price);
            subtract_from_total_invest_bill(price);
            $(obj).closest('tr').remove();
        }

        function setSearchValProd(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price = '${payable ?? price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_presc_dose[] required>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${payable ?? price}')">x</button></td>
                </tr>
            `;

            $('#selected-products').append(mk);
            $('#consult_presc_res').html('');

        }

        function searchProducts(q) {
            if (q !== "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-products') }}",
                    method: "GET",
                    dataType: 'json',
                    data: { term: q, patient_id: '{{ $patient->id }}' },
                    success: function(data) {
                        $('#consult_presc_res').html('');

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.product_name || 'Unknown';
                            const code = item.product_code || '';
                            const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                            const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}](${qty} avail.)`;

                            const mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValProd('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> (${qty} avail.) NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_presc_res').append(mk);
                            $('#consult_presc_res').show();
                        }
                    },
                    error: function(xhr) {
                        console.error('Product search failed', xhr);
                        $('#consult_presc_res').html('');
                    }
                });
            } else {
                $('#consult_presc_res').html('');
            }
        }
    </script>
    <script>
        $(function() {
            $('#invest_history_sample').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investSampleList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#invest_history_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>

    <script>
        $(function() {
            $('#invest_history_res').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investResList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function add_to_total_invest_bill(v) {
            let new_tot = parseFloat($('#invest_bill_tot').val()) + parseFloat(v);
            $('#invest_bill_tot').val(new_tot);
            $('#invest_billed_tot').val(new_tot);
        }

        function subtract_from_total_invest_bill(v) {
            let new_tot = parseFloat($('#invest_bill_tot').val()) - parseFloat(v);
            //in order to avoid having negative values
            if (new_tot > 0) {
                $('#invest_bill_tot').val(new_tot);
                $('#invest_billed_tot').val(new_tot);
            } else {
                $('#invest_bill_tot').val(0);
                $('#invest_billed_tot').val(0);
            }
        }

        function checkInvestBillRow(obj) {
            // console.log($(obj).val());
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                // console.log('ch')
                add_to_total_invest_bill(row_val);
            } else {
                // console.log('unch')
                subtract_from_total_invest_bill(row_val);
            }
        }

        function setSearchValSer(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedInvestBillRows[]' onclick='checkInvestBillRow(this)' data-price = '${payable ?? price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_invest_note[]>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${payable ?? price}')">x</button></td>
                </tr>
            `;

            $('#selected-services').append(mk);
            $('#consult_invest_res').html('');

        }

        function searchServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-services') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        patient_id: '{{ $patient->id }}'
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_invest_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}]`;

                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValSer('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_invest_res').append(mk);
                            $('#consult_invest_res').show();
                        }
                    }
                });
            } else {
                $('#consult_invest_res').html('');
            }
        }
    </script>
    <script>
        // CKEditor instances
        let investResEditor = null;
        let imagingResEditor = null;
        let deletedAttachments = [];
        let imagingDeletedAttachments = [];

        function setResTempInModal(obj) {
            $('#invest_res_service_name').text($(obj).attr('data-service-name'));
            $('#invest_res_entry_id').val($(obj).attr('data-id'));
            $('#invest_res_is_edit').val('0'); // Set to create mode

            // Clear existing attachments and reset
            deletedAttachments = [];
            $('#deleted_attachments').val('[]');
            $('#existing_attachments_container').hide();
            $('#existing_attachments_list').html('');

            // Check if V2 template exists
            let templateV2Str = $(obj).attr('data-template-v2');
            let templateV1 = $(obj).attr('data-template');

            if (templateV2Str && templateV2Str !== '') {
                // Use V2 Template (structured form)
                try {
                    let templateV2 = JSON.parse(templateV2Str);
                    loadV2Template(templateV2, null); // null means new entry, not editing
                } catch (e) {
                    console.error('Error parsing V2 template:', e);
                    // Fallback to V1
                    loadV1Template(templateV1);
                }
            } else {
                // Use V1 Template (WYSIWYG editor)
                loadV1Template(templateV1);
            }

            $('#investResModal').modal('show');
        }

        function loadV1Template(template) {
            $('#invest_res_template_version').val('1');
            $('#v1_template_container').show();
            $('#v2_template_container').hide();

            // Initialize CKEditor if not already initialized
            if (!investResEditor) {
                ClassicEditor
                    .create(document.querySelector('#invest_res_template_editor'), {
                        toolbar: {
                            items: [
                                'undo', 'redo',
                                '|', 'heading',
                                '|', 'bold', 'italic',
                                '|', 'link', 'insertTable',
                                '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                            ]
                        }
                    })
                    .then(editor => {
                        investResEditor = editor;
                        editor.setData(template);
                    })
                    .catch(err => {
                        console.error(err);
                    });
            } else {
                investResEditor.setData(template);
            }
        }

        function loadV2Template(template, existingData) {
            $('#invest_res_template_version').val('2');
            $('#v1_template_container').hide();
            $('#v2_template_container').show();

            let formHtml = '<div class="v2-result-form">';
            formHtml += '<h6 class="mb-3">' + template.template_name + '</h6>';

            // Sort parameters by order
            let parameters = template.parameters.sort((a, b) => a.order - b.order);

            parameters.forEach(param => {
                if (param.show_in_report === false) {
                    return; // Skip hidden parameters
                }

                formHtml += '<div class="form-group row">';
                formHtml += '<label class="col-md-4 col-form-label">';
                formHtml += param.name;
                if (param.unit) {
                    formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
                }
                if (param.required) {
                    formHtml += ' <span class="text-danger">*</span>';
                }
                formHtml += '</label>';
                formHtml += '<div class="col-md-8">';

                let fieldId = 'param_' + param.id;
                let value = existingData && existingData[param.id] ? existingData[param.id] : '';

                // Generate form field based on type
                if (param.type === 'string') {
                    formHtml += '<input type="text" class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    formHtml += 'id="' + fieldId + '" ';
                    formHtml += 'value="' + value + '" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += 'placeholder="Enter ' + param.name + '">';

                } else if (param.type === 'integer') {
                    formHtml += '<input type="number" step="1" class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    if (param.reference_range) {
                        formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                        formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                    }
                    formHtml += 'id="' + fieldId + '" ';
                    formHtml += 'value="' + value + '" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += 'placeholder="Enter ' + param.name + '">';

                } else if (param.type === 'float') {
                    formHtml += '<input type="number" step="0.01" class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    if (param.reference_range) {
                        formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                        formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                    }
                    formHtml += 'id="' + fieldId + '" ';
                    formHtml += 'value="' + value + '" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += 'placeholder="Enter ' + param.name + '">';

                } else if (param.type === 'boolean') {
                    formHtml += '<select class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    if (param.reference_range && param.reference_range.reference_value !== undefined) {
                        formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                    }
                    formHtml += 'id="' + fieldId + '" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += '>';
                    formHtml += '<option value="">Select</option>';
                    formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
                    formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
                    formHtml += '</select>';

                } else if (param.type === 'enum') {
                    formHtml += '<select class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    if (param.reference_range && param.reference_range.reference_value) {
                        formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                    }
                    formHtml += 'id="' + fieldId + '" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += '>';
                    formHtml += '<option value="">Select</option>';
                    if (param.options) {
                        param.options.forEach(opt => {
                            formHtml += '<option value="' + opt.value + '" ' + (value === opt.value ? 'selected' : '') + '>' + opt.label + '</option>';
                        });
                    }
                    formHtml += '</select>';

                } else if (param.type === 'long_text') {
                    formHtml += '<textarea class="form-control v2-param-field" ';
                    formHtml += 'data-param-id="' + param.id + '" ';
                    formHtml += 'data-param-type="' + param.type + '" ';
                    formHtml += 'id="' + fieldId + '" ';
                    formHtml += 'rows="3" ';
                    if (param.required) formHtml += 'required ';
                    formHtml += 'placeholder="Enter ' + param.name + '">' + value + '</textarea>';
                }

                // Add reference range info if available
                if (param.reference_range) {
                    formHtml += '<small class="form-text text-muted">';
                    if (param.type === 'integer' || param.type === 'float') {
                        if (param.reference_range.min !== null && param.reference_range.max !== null) {
                            formHtml += 'Normal range: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                        }
                    } else if (param.type === 'boolean' && param.reference_range.reference_value !== undefined) {
                        formHtml += 'Normal: ' + (param.reference_range.reference_value ? 'Yes/Positive' : 'No/Negative');
                    } else if (param.type === 'enum' && param.reference_range.reference_value) {
                        formHtml += 'Normal: ' + param.reference_range.reference_value;
                    } else if (param.reference_range.text) {
                        formHtml += param.reference_range.text;
                    }
                    formHtml += '</small>';
                }

                // Status indicator (will be updated on blur)
                formHtml += '<div class="mt-1"><span class="param-status" id="status_' + param.id + '"></span></div>';

                formHtml += '</div>';
                formHtml += '</div>';
            });

            formHtml += '</div>';

            $('#v2_form_fields').html(formHtml);

            // Add event listeners for value changes to show status
            $('.v2-param-field').on('blur change', function() {
                updateParameterStatus($(this));
            });
        }

        function updateParameterStatus($field) {
            let paramId = $field.data('param-id');
            let paramType = $field.data('param-type');
            let value = $field.val();
            let $statusSpan = $('#status_' + paramId);

            if (!value || value === '') {
                $statusSpan.html('');
                return;
            }

            let status = '';
            let statusClass = '';

            if (paramType === 'integer' || paramType === 'float') {
                let numValue = parseFloat(value);
                let min = $field.data('ref-min');
                let max = $field.data('ref-max');

                if (min !== '' && max !== '') {
                    if (numValue < min) {
                        status = 'Low';
                        statusClass = 'badge-warning';
                    } else if (numValue > max) {
                        status = 'High';
                        statusClass = 'badge-danger';
                    } else {
                        status = 'Normal';
                        statusClass = 'badge-success';
                    }
                }
            } else if (paramType === 'boolean') {
                let refValue = $field.data('ref-value');
                if (refValue !== undefined) {
                    let boolValue = value === 'true';
                    let refBool = refValue === true || refValue === 'true';

                    if (boolValue === refBool) {
                        status = 'Normal';
                        statusClass = 'badge-success';
                    } else {
                        status = 'Abnormal';
                        statusClass = 'badge-warning';
                    }
                }
            } else if (paramType === 'enum') {
                let refValue = $field.data('ref-value');
                if (refValue) {
                    if (value === refValue) {
                        status = 'Normal';
                        statusClass = 'badge-success';
                    } else {
                        status = 'Abnormal';
                        statusClass = 'badge-warning';
                    }
                }
            }

            if (status) {
                $statusSpan.html('<span class="badge ' + statusClass + '">' + status + '</span>');
            } else {
                $statusSpan.html('');
            }
        }

        function copyResTemplateToField() {
            let version = $('#invest_res_template_version').val();

            if (version === '2') {
                // Collect V2 structured data
                let data = {};
                $('.v2-param-field').each(function() {
                    let paramId = $(this).data('param-id');
                    let paramType = $(this).data('param-type');
                    let value = $(this).val();

                    // Convert values to appropriate types
                    if (paramType === 'integer') {
                        data[paramId] = value ? parseInt(value) : null;
                    } else if (paramType === 'float') {
                        data[paramId] = value ? parseFloat(value) : null;
                    } else if (paramType === 'boolean') {
                        data[paramId] = value === 'true' ? true : (value === 'false' ? false : null);
                    } else {
                        data[paramId] = value || null;
                    }
                });

                $('#invest_res_template_data').val(JSON.stringify(data));
                // For V2, we still save a simple HTML representation to result column for backward compat
                $('#invest_res_template_submited').val('<p>Structured result data (V2 template)</p>');
            } else {
                // V1: Copy from CKEditor
                if (investResEditor) {
                    $('#invest_res_template_submited').val(investResEditor.getData());
                }
            }
            return true;
        }

        // Handle form submission
        $('#investResModal form').on('submit', function(e) {
            copyResTemplateToField();

            let isEdit = $('#invest_res_is_edit').val() === '1';
            let message = isEdit
                ? 'Are you sure you want to update this result?'
                : 'Are you sure you wish to save this result entry? It can not be edited after!';

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // Function to edit lab result
        function editLabResult(obj) {
            $('#invest_res_service_name').text($(obj).attr('data-service-name'));
            $('#invest_res_entry_id').val($(obj).attr('data-id'));
            $('#invest_res_is_edit').val('1'); // Set to edit mode

            // Reset deleted attachments
            deletedAttachments = [];
            $('#deleted_attachments').val('[]');

            // Load existing result into CKEditor
            let result = $(obj).attr('data-result');

            // Load existing attachments
            let attachments = $(obj).attr('data-attachments');
            if (attachments) {
                try {
                    attachments = JSON.parse(attachments);
                    displayExistingAttachments(attachments);
                } catch(e) {
                    console.error('Error parsing attachments:', e);
                }
            } else {
                $('#existing_attachments_container').hide();
            }

            // Initialize CKEditor if not already initialized
            if (!investResEditor) {
                ClassicEditor
                    .create(document.querySelector('#invest_res_template_editor'), {
                        toolbar: {
                            items: [
                                'undo', 'redo',
                                '|', 'heading',
                                '|', 'bold', 'italic',
                                '|', 'link', 'insertTable',
                                '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                            ]
                        }
                    })
                    .then(editor => {
                        investResEditor = editor;
                        editor.setData(result);
                    })
                    .catch(err => {
                        console.error(err);
                    });
            } else {
                investResEditor.setData(result);
            }

            // Update modal title and button text
            $('#investResModal .modal-title').text('Edit Investigation Result');
            $('#invest_res_submit_btn').html('<i class="mdi mdi-content-save"></i> Update Result');

            $('#investResModal').modal('show');

            // Reset modal on close
            $('#investResModal').on('hidden.bs.modal', function() {
                $('#investResModal .modal-title').text('Investigation Result Entry');
                $('#invest_res_submit_btn').html('Save changes');
                $('#invest_res_is_edit').val('0');
                deletedAttachments = [];
                $('#deleted_attachments').val('[]');
                $('#existing_attachments_container').hide();
            });
        }

        // Function to display existing attachments for lab results
        function displayExistingAttachments(attachments) {
            if (!attachments || attachments.length === 0) {
                $('#existing_attachments_container').hide();
                return;
            }

            let html = '';
            attachments.forEach((attachment, index) => {
                if (!deletedAttachments.includes(index)) {
                    let icon = getFileIcon(attachment.type);
                    html += `
                        <div class="badge badge-secondary mr-2 mb-2 p-2" id="attachment_${index}">
                            ${icon} ${attachment.name}
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ml-2"
                                onclick="removeAttachment(${index})" title="Remove">
                                <i class="mdi mdi-close-circle"></i>
                            </button>
                        </div>
                    `;
                }
            });

            $('#existing_attachments_list').html(html);
            $('#existing_attachments_container').show();
        }

        // Function to remove an attachment
        function removeAttachment(index) {
            if (!deletedAttachments.includes(index)) {
                deletedAttachments.push(index);
                $('#deleted_attachments').val(JSON.stringify(deletedAttachments));
                $(`#attachment_${index}`).fadeOut(300, function() {
                    $(this).remove();
                    // Hide container if no attachments left
                    if ($('#existing_attachments_list').children(':visible').length === 0) {
                        $('#existing_attachments_container').fadeOut();
                    }
                });
            }
        }
    </script>
    <script>
        function setResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));

            // Basic service info
            $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));

            // Patient information
            let patientName = res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname;
            $('#res_patient_name').html(patientName);
            $('#res_patient_id').html(res_obj.patient.file_no);

            // Calculate age from date of birth
            let age = 'N/A';
            if (res_obj.patient.date_of_birth) {
                let dob = new Date(res_obj.patient.date_of_birth);
                let today = new Date();
                let ageYears = today.getFullYear() - dob.getFullYear();
                let monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    ageYears--;
                }
                age = ageYears + ' years';
            }
            $('#res_patient_age').html(age);

            // Gender
            let gender = res_obj.patient.gender ? res_obj.patient.gender.toUpperCase() : 'N/A';
            $('#res_patient_gender').html(gender);

            // Test information
            $('#res_test_id').html(res_obj.id);
            $('#res_sample_date').html(res_obj.sample_date || 'N/A');
            $('#res_result_date').html(res_obj.result_date || 'N/A');
            $('#res_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);

            // Signature date (use result date)
            $('#res_signature_date').html(res_obj.result_date || '');

            // Generated date (current date)
            let now = new Date();
            let generatedDate = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            $('#res_generated_date').html(generatedDate);

            // Handle V2 results (structured data)
            if (res_obj.result_data && typeof res_obj.result_data === 'object') {
                let resultsHtml = '<table class="result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Test Parameter</th>';
                resultsHtml += '<th style="width: 25%;">Results</th>';
                resultsHtml += '<th style="width: 25%;">Reference Range</th>';
                resultsHtml += '<th style="width: 10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                res_obj.result_data.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) {
                        resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
                    }
                    resultsHtml += '</td>';

                    // Value with unit
                    let valueDisplay = param.value;
                    if (param.unit) {
                        valueDisplay += ' ' + param.unit;
                    }
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    // Reference range
                    let refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.type === 'integer' || param.type === 'float') {
                            if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                                refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                                if (param.unit) refRange += ' ' + param.unit;
                            }
                        } else if (param.type === 'boolean' || param.type === 'enum') {
                            refRange = param.reference_range.reference_value || 'N/A';
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    // Status badge
                    let statusHtml = '';
                    if (param.status) {
                        let statusClass = 'status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#invest_res').html(resultsHtml);
            } else {
                // V1 results (HTML content)
                $('#invest_res').html(res_obj.result);
            }

            // Handle attachments
            $('#invest_attachments').html('');
            if (res_obj.attachments) {
                let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
                if (attachments && attachments.length > 0) {
                    let attachHtml = '<div class="result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
                    attachments.forEach(function(attachment) {
                        let url = '{{ asset("storage") }}/' + attachment.path;
                        let icon = getFileIcon(attachment.type);
                        attachHtml += `<div class="col-md-4 mb-2">
                            <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                                ${icon} ${attachment.name}
                            </a>
                        </div>`;
                    });
                    attachHtml += '</div></div>';
                    $('#invest_attachments').html(attachHtml);
                }
            }

            $('#investResViewModal').modal('show');
        }

        function getFileIcon(extension) {
            const icons = {
                'pdf': '<i class="mdi mdi-file-pdf"></i>',
                'doc': '<i class="mdi mdi-file-word"></i>',
                'docx': '<i class="mdi mdi-file-word"></i>',
                'jpg': '<i class="mdi mdi-file-image"></i>',
                'jpeg': '<i class="mdi mdi-file-image"></i>',
                'png': '<i class="mdi mdi-file-image"></i>'
            };
            return icons[extension] || '<i class="mdi mdi-file"></i>';
        }
    </script>

    {{-- Imaging JavaScript Functions --}}
    <script>
        // Imaging DataTables
        $(function() {
            $('#imaging_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('imagingHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],
                "paging": true
            });

            $('#imaging_history_bills').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('imagingBillList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],
                "paging": true
            });

            $('#imaging_history_res').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('imagingResList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "result",
                        name: "result"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],
                "paging": true
            });
        });

        // Imaging billing functions
        function add_to_total_imaging_bill(v) {
            let new_tot = parseFloat($('#imaging_bill_tot').val()) + parseFloat(v);
            $('#imaging_bill_tot').val(new_tot);
        }

        function subtract_from_total_imaging_bill(v) {
            let new_tot = parseFloat($('#imaging_bill_tot').val()) - parseFloat(v);
            if (new_tot > 0) {
                $('#imaging_bill_tot').val(new_tot);
            } else {
                $('#imaging_bill_tot').val(0);
            }
        }

        function checkImagingBillRow(obj) {
            var row_val = $(obj).attr('data-price');
            if ($(obj).is(':checked')) {
                add_to_total_imaging_bill(row_val);
            } else {
                subtract_from_total_imaging_bill(row_val);
            }
        }

        function setSearchValImagingSer(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedImagingBillRows[]' onclick='checkImagingBillRow(this)' data-price = '${payable ?? price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    <td>
                        <input type = 'text' class='form-control' name='consult_imaging_note[]'>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeImagingProdRow(this,'${payable ?? price}')">x</button></td>
                </tr>
            `;
            $('#selected-imaging-services').append(mk);
            $('#consult_imaging_search').val('');
            $('#consult_imaging_res').html('');
        }

        function removeImagingProdRow(obj, price) {
            subtract_from_total_imaging_bill(price);
            $(obj).closest('tr').remove();
        }

        function searchImagingServices(q) {
            if (q != "") {
                $.ajax({
                    url: "{{ url('live-search-services') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        category_id: 6, // Imaging category ID
                        patient_id: '{{ $patient->id }}'
                    },
                    success: function(data) {
                        $('#consult_imaging_res').html('');
                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}]`;

                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValImagingSer('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_imaging_res').append(mk);
                            $('#consult_imaging_res').show();
                        }
                    }
                });
            } else {
                $('#consult_imaging_res').html('');
            }
        }

        function setImagingResTempInModal(obj) {
            $('#imaging_res_service_name').text($(obj).attr('data-service-name'));
            $('#imaging_res_entry_id').val($(obj).attr('data-id'));
            $('#imaging_res_is_edit').val('0'); // Set to create mode

            // Clear existing attachments and reset
            imagingDeletedAttachments = [];
            $('#imaging_deleted_attachments').val('[]');
            $('#imaging_existing_attachments_container').hide();
            $('#imaging_existing_attachments_list').html('');

            // Load template into CKEditor
            let template = $(obj).attr('data-template');

            // Initialize CKEditor if not already initialized
            if (!imagingResEditor) {
                ClassicEditor
                    .create(document.querySelector('#imaging_res_template_editor'), {
                        toolbar: {
                            items: [
                                'undo', 'redo',
                                '|', 'heading',
                                '|', 'bold', 'italic',
                                '|', 'link', 'insertTable',
                                '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                            ]
                        }
                    })
                    .then(editor => {
                        imagingResEditor = editor;
                        editor.setData(template);
                    })
                    .catch(err => {
                        console.error(err);
                    });
            } else {
                imagingResEditor.setData(template);
            }

            $('#imagingResModal').modal('show');
        }

        function copyImagingResTemplateToField() {
            if (imagingResEditor) {
                $('#imaging_res_template_submited').val(imagingResEditor.getData());
            }
            return true;
        }

        // Handle imaging form submission
        $('#imagingResModal form').on('submit', function(e) {
            copyImagingResTemplateToField();

            let isEdit = $('#imaging_res_is_edit').val() === '1';
            let message = isEdit
                ? 'Are you sure you want to update this result?'
                : 'Are you sure you wish to save this result entry? It can not be edited after!';

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // Function to edit imaging result
        function editImagingResult(obj) {
            $('#imaging_res_service_name').text($(obj).attr('data-service-name'));
            $('#imaging_res_entry_id').val($(obj).attr('data-id'));
            $('#imaging_res_is_edit').val('1'); // Set to edit mode

            // Reset deleted attachments
            imagingDeletedAttachments = [];
            $('#imaging_deleted_attachments').val('[]');

            // Load existing result into CKEditor
            let result = $(obj).attr('data-result');

            // Load existing attachments
            let attachments = $(obj).attr('data-attachments');
            if (attachments) {
                try {
                    attachments = JSON.parse(attachments);
                    displayImagingExistingAttachments(attachments);
                } catch(e) {
                    console.error('Error parsing attachments:', e);
                }
            } else {
                $('#imaging_existing_attachments_container').hide();
            }

            // Initialize CKEditor if not already initialized
            if (!imagingResEditor) {
                ClassicEditor
                    .create(document.querySelector('#imaging_res_template_editor'), {
                        toolbar: {
                            items: [
                                'undo', 'redo',
                                '|', 'heading',
                                '|', 'bold', 'italic',
                                '|', 'link', 'insertTable',
                                '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                            ]
                        }
                    })
                    .then(editor => {
                        imagingResEditor = editor;
                        editor.setData(result);
                    })
                    .catch(err => {
                        console.error(err);
                    });
            } else {
                imagingResEditor.setData(result);
            }

            // Update modal title and button text
            $('#imagingResModal .modal-title').text('Edit Imaging Result');
            $('#imaging_res_submit_btn').html('<i class="mdi mdi-content-save"></i> Update Result');

            $('#imagingResModal').modal('show');

            // Reset modal on close
            $('#imagingResModal').on('hidden.bs.modal', function() {
                $('#imagingResModal .modal-title').text('Imaging Result Entry');
                $('#imaging_res_submit_btn').html('Save changes');
                $('#imaging_res_is_edit').val('0');
                imagingDeletedAttachments = [];
                $('#imaging_deleted_attachments').val('[]');
                $('#imaging_existing_attachments_container').hide();
            });
        }

        // Function to display existing attachments for imaging results
        function displayImagingExistingAttachments(attachments) {
            if (!attachments || attachments.length === 0) {
                $('#imaging_existing_attachments_container').hide();
                return;
            }

            let html = '';
            attachments.forEach((attachment, index) => {
                if (!imagingDeletedAttachments.includes(index)) {
                    let icon = getFileIcon(attachment.type);
                    html += `
                        <div class="badge badge-secondary mr-2 mb-2 p-2" id="imaging_attachment_${index}">
                            ${icon} ${attachment.name}
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ml-2"
                                onclick="removeImagingAttachment(${index})" title="Remove">
                                <i class="mdi mdi-close-circle"></i>
                            </button>
                        </div>
                    `;
                }
            });

            $('#imaging_existing_attachments_list').html(html);
            $('#imaging_existing_attachments_container').show();
        }

        // Function to remove an imaging attachment
        function removeImagingAttachment(index) {
            if (!imagingDeletedAttachments.includes(index)) {
                imagingDeletedAttachments.push(index);
                $('#imaging_deleted_attachments').val(JSON.stringify(imagingDeletedAttachments));
                $(`#imaging_attachment_${index}`).fadeOut(300, function() {
                    $(this).remove();
                    // Hide container if no attachments left
                    if ($('#imaging_existing_attachments_list').children(':visible').length === 0) {
                        $('#imaging_existing_attachments_container').fadeOut();
                    }
                });
            }
        }

        function setImagingResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));

            // Basic service info
            $('.imaging_res_service_name_view').text($(obj).attr('data-service-name'));

            // Patient information
            let patientName = res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname;
            $('#imaging_patient_name').html(patientName);
            $('#imaging_patient_id').html(res_obj.patient.file_no);

            // Calculate age from date of birth
            let age = 'N/A';
            if (res_obj.patient.date_of_birth) {
                let dob = new Date(res_obj.patient.date_of_birth);
                let today = new Date();
                let ageYears = today.getFullYear() - dob.getFullYear();
                let monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    ageYears--;
                }
                age = ageYears + ' years';
            }
            $('#imaging_patient_age').html(age);

            // Gender
            let gender = res_obj.patient.gender ? res_obj.patient.gender.toUpperCase() : 'N/A';
            $('#imaging_patient_gender').html(gender);

            // Test information
            $('#imaging_test_id').html(res_obj.id);
            $('#imaging_result_date').html(res_obj.result_date || 'N/A');
            $('#imaging_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);

            // Status
            let statusBadge = '';
            if (res_obj.status) {
                let statusClass = 'badge-';
                let statusText = String(res_obj.status); // Convert to string
                switch(statusText.toLowerCase()) {
                    case 'completed':
                    case '3':
                    case '4':
                        statusClass += 'success';
                        statusText = 'Completed';
                        break;
                    case 'pending':
                    case '1':
                        statusClass += 'warning';
                        statusText = 'Pending';
                        break;
                    case 'in progress':
                    case '2':
                        statusClass += 'info';
                        statusText = 'In Progress';
                        break;
                    default: statusClass += 'secondary';
                }
                statusBadge = '<span class="badge ' + statusClass + '">' + statusText + '</span>';
            }
            $('#imaging_status').html(statusBadge || 'N/A');

            // Signature date (use result date)
            $('#imaging_signature_date').html(res_obj.result_date || '');

            // Generated date (current date)
            let now = new Date();
            let generatedDate = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            $('#imaging_generated_date').html(generatedDate);

            // Handle V2 results (structured data)
            if (res_obj.result_data && typeof res_obj.result_data === 'object') {
                let resultsHtml = '<table class="imaging-result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Parameter</th>';
                resultsHtml += '<th style="width: 25%;">Results</th>';
                resultsHtml += '<th style="width: 25%;">Reference Range</th>';
                resultsHtml += '<th style="width: 10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                res_obj.result_data.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) {
                        resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
                    }
                    resultsHtml += '</td>';

                    // Value with unit
                    let valueDisplay = param.value;
                    if (param.unit) {
                        valueDisplay += ' ' + param.unit;
                    }
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    // Reference range
                    let refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.type === 'integer' || param.type === 'float') {
                            if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                                refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                                if (param.unit) refRange += ' ' + param.unit;
                            }
                        } else if (param.type === 'boolean' || param.type === 'enum') {
                            refRange = param.reference_range.reference_value || 'N/A';
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    // Status badge
                    let statusHtml = '';
                    if (param.status) {
                        let statusClass = 'imaging-status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="imaging-result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#imaging_res').html(resultsHtml);
            } else {
                // V1 results (HTML content)
                $('#imaging_res').html(res_obj.result);
            }

            // Handle attachments
            $('#imaging_attachments').html('');
            if (res_obj.attachments) {
                let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
                if (attachments && attachments.length > 0) {
                    let attachHtml = '<div class="imaging-result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
                    attachments.forEach(function(attachment) {
                        let url = '{{ asset("storage") }}/' + attachment.path;
                        let icon = getFileIcon(attachment.type);
                        attachHtml += `<div class="col-md-4 mb-2">
                            <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                                ${icon} ${attachment.name}
                            </a>
                        </div>`;
                    });
                    attachHtml += '</div></div>';
                    $('#imaging_attachments').html(attachHtml);
                }
            }

            $('#imagingResViewModal').modal('show');
        }
    </script>

    <script>
        function PrintElem(elem) {
            @php
                $hosColor = appsettings()->hos_color ?? '#0066cc';
            @endphp

            var mywindow = window.open('', 'PRINT', 'height=800,width=1000');

            // Get all style tags from the current modal
            var styleContent = '';
            var modalParent = document.getElementById(elem).closest('.modal-content');
            if (modalParent) {
                var styleTags = modalParent.querySelectorAll('style');
                styleTags.forEach(function(styleTag) {
                    styleContent += styleTag.innerHTML;
                });
            }

            mywindow.document.write('<html><head><title>' + document.title + '</title>');
            mywindow.document.write('<meta charset="UTF-8">');

            // Add custom styles for printing
            mywindow.document.write(`<style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: white;
                    width: 100%;
                    max-width: 100%;
                }
                #resultViewTable, #imagingResultViewTable {
                    width: 100% !important;
                    max-width: 100% !important;
                }

                /* Lab/Imaging Result Styles */
                .result-header, .imaging-result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 3px solid {{ $hosColor }};
                    page-break-inside: avoid;
                }
                .result-header-left, .imaging-result-header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .result-logo, .imaging-result-logo {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                }
                .result-hospital-name, .imaging-result-hospital-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    text-transform: uppercase;
                }
                .result-header-right, .imaging-result-header-right {
                    text-align: right;
                    font-size: 13px;
                    color: #666;
                    line-height: 1.6;
                }
                .result-title-section, .imaging-result-title-section {
                    background: {{ $hosColor }};
                    color: white;
                    text-align: center;
                    padding: 15px;
                    font-size: 28px;
                    font-weight: bold;
                    letter-spacing: 6px;
                    page-break-inside: avoid;
                }
                .result-patient-info, .imaging-result-patient-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    padding: 20px;
                    background: #f8f9fa;
                    page-break-inside: avoid;
                }
                .result-info-box, .imaging-result-info-box {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                .result-info-row, .imaging-result-info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .result-info-row:last-child, .imaging-result-info-row:last-child {
                    border-bottom: none;
                }
                .result-info-label, .imaging-result-info-label {
                    font-weight: 600;
                    color: #333;
                    min-width: 120px;
                }
                .result-info-value, .imaging-result-info-value {
                    color: #666;
                    flex: 1;
                }
                .result-section, .imaging-result-section {
                    padding: 20px;
                }
                .result-section-title, .imaging-result-section-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid {{ $hosColor }};
                    page-break-after: avoid;
                }
                .result-table, .imaging-result-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse;
                    margin-top: 15px;
                    table-layout: fixed;
                }
                .result-table thead, .imaging-result-table thead {
                    background: {{ $hosColor }};
                    color: white;
                }
                .result-table th, .imaging-result-table th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #dee2e6;
                }
                .result-table td, .imaging-result-table td {
                    padding: 10px 12px;
                    border: 1px solid #ddd;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                .result-table tbody tr:nth-child(even),
                .imaging-result-table tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                .result-status-badge, .imaging-result-status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status-normal, .imaging-status-normal {
                    background: #d4edda;
                    color: #155724;
                }
                .status-high, .imaging-status-high {
                    background: #f8d7da;
                    color: #721c24;
                }
                .status-low, .imaging-status-low {
                    background: #fff3cd;
                    color: #856404;
                }
                .status-abnormal, .imaging-status-abnormal {
                    background: #f8d7da;
                    color: #721c24;
                }
                .result-attachments, .imaging-result-attachments {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    page-break-inside: avoid;
                }
                .result-footer, .imaging-result-footer {
                    padding: 20px;
                    border-top: 2px solid #eee;
                    font-size: 12px;
                    color: #999;
                    text-align: center;
                    page-break-inside: avoid;
                }

                /* Badge styles */
                .badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .badge-success {
                    background: #28a745;
                    color: white;
                }
                .badge-warning {
                    background: #ffc107;
                    color: #000;
                }
                .badge-info {
                    background: #17a2b8;
                    color: white;
                }
                .badge-secondary {
                    background: #6c757d;
                    color: white;
                }

                /* Button styles for attachments */
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    margin: 5px;
                    border: 1px solid transparent;
                    border-radius: 4px;
                    text-decoration: none;
                    font-size: 14px;
                }
                .btn-outline-primary {
                    color: {{ $hosColor }};
                    border-color: {{ $hosColor }};
                }
                .row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: -5px;
                }
                .col-md-4 {
                    flex: 0 0 33.333333%;
                    max-width: 33.333333%;
                    padding: 5px;
                }

                /* Icon styles */
                .mdi:before {
                    font-family: "Material Design Icons";
                }

                /* Print specific */
                @media print {
                    body {
                        padding: 0;
                        width: 100%;
                        max-width: 100%;
                    }
                    #resultViewTable, #imagingResultViewTable {
                        width: 100% !important;
                        max-width: 100% !important;
                    }
                    .result-table, .imaging-result-table {
                        width: 100% !important;
                        max-width: 100% !important;
                    }
                    .btn-outline-primary {
                        display: none;
                    }
                }

                @page {
                    margin: 0.5cm;
                    size: A4;
                }
            </style>`);

            mywindow.document.write('</head><body>');
            mywindow.document.write(document.getElementById(elem).innerHTML);
            mywindow.document.write('</body></html>');

            mywindow.document.close(); // IE >= 10

            // Wait for the window and its contents to load
            mywindow.onload = function() {
                mywindow.focus(); // Set focus for IE
                setTimeout(function() {
                    mywindow.print();
                    // Optional: Uncomment the line below to close the window after printing
                    // mywindow.close();
                }, 250);
            };

            return true;
        }
    </script>
    <script>
        function setBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_patient_id').val($(obj).attr('data-patient-id') || '{{ $patient->id ?? "" }}');
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#bed_coverage_info').hide(); // Reset coverage display
            $('#assignBedModal').modal('show');
        }
    </script>

    <script>
        function newBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_patient_id').val($(obj).attr('data-patient-id') || '{{ $patient->id ?? "" }}');
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#bed_coverage_info').hide(); // Reset coverage display
            $('#assignBedModal').modal('show');
        }
    </script>
    <script>
        function setBillModal(obj) {
            $('#assign_bed_req_id_').val($(obj).attr('data-id'));
            $('#admit_days').val($(obj).attr('data-days'));
            $('#admit_price').val($(obj).attr('data-price'));
            $('#admit_bed_details').html($(obj).attr('data-bed'));
            $('#admit_total').val(parseFloat($(obj).attr('data-price')) * parseFloat($(obj).attr('data-days')));
            $('#assignBillModal').modal('show');
        }
    </script>
    @include('admin.partials.nursing-note-save-scripts')
    <script>
        $(function() {
            $('#nurse_note_hist_1').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 1]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_2').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 2]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_3').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 3]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_4').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 4]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_5').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 5]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function setNoteInModal(obj) {
            $('#note_type_name_').text($(obj).attr('data-service-name'));
            $('#nursing_note_template_').html($(obj).attr('data-template'));
            $('#nursingNoteModal').modal('show');
        }
    </script>
    <script>
        function toggle_group(class_) {
            var x = document.getElementsByClassName(class_);
            for (i = 0; i < x.length; i++) {
                if (x[i].style.display === "none") {
                    x[i].style.display = "block";
                } else {
                    x[i].style.display = "none";
                }
            }

        }
    </script>

    <script>
        function addMiscBillRow() {
            let mrkup = `
            <tr>
                <td>
                    <input type="text" class="form-control" name="names[]" placeholder="Describe service rendered...">
                </td>
                <td>
                    <input type="number" class="form-control" name="prices[]" min="1">
                </td>
                <td><button type="button" onclick="removeMiscBillRow(this)" class="btn btn-danger btn-sm"><i
                            class="fa fa-times"></i></button></td>
            </tr>
            `;

            $('#misc-bill-row').append(mrkup);
        }

        function removeMiscBillRow(obj) {
            $(obj).closest('tr').remove();
        }
    </script>

    <script>
        $(function() {
            $('#pending-paymnet-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('product-services-requesters-list', $patient->user_id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "service_id",
                        name: "service_id"
                    },
                    {
                        data: "product_id",
                        name: "product_id"
                    },
                    {
                        data: "show",
                        name: "show"
                    },
                ],
                "paging": true
            });
        });
    </script>

    @include('admin.partials.vitals-scripts')
    @include('admin.patients.partials.nurse_chart_scripts_enhanced')
@endsection

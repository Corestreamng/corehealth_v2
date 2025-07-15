@extends('admin.layouts.app')
@section('title', 'Patients Profile')
@section('page_name', 'Patients')
@section('subpage_name', 'Show Patient')
@section('content')
@php
    $section = request()->get('section');
@endphp

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Patient Data</h3>
        </div>
    </div>

    {{-- Nav tabs --}}
    <ul class="nav nav-tabs" id="patientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ !$section || $section == 'patientInfo' ? 'active' : '' }}" id="patientInfo-tab"
                data-bs-toggle="tab" data-bs-target="#patientInfo" type="button" role="tab">Patient Info</button>
        </li>
        @can('see-vitals')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'vitalsCardBody' ? 'active' : '' }}" id="vitals-tab"
                    data-bs-toggle="tab" data-bs-target="#vitalsCardBody" type="button" role="tab">Vitals</button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseChartCardBody' ? 'active' : '' }}" id="nurseChart-tab"
                    data-bs-toggle="tab" data-bs-target="#nurseChartCardBody" type="button" role="tab">New Nurse Chart</button>
            </li>
        @endcan
        @can('see-accounts')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'accountsCardBody' ? 'active' : '' }}" id="accounts-tab"
                    data-bs-toggle="tab" data-bs-target="#accountsCardBody" type="button" role="tab">Accounts</button>
            </li>
        @endcan
        @can('see-admissions')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'addmissionsCardBody' ? 'active' : '' }}" id="admissions-tab"
                    data-bs-toggle="tab" data-bs-target="#addmissionsCardBody" type="button" role="tab">Admission History</button>
            </li>
        @endcan
        @can('see-procedures')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'wardNotesCardBody' ? 'active' : '' }}" id="procedures-tab"
                    data-bs-toggle="tab" data-bs-target="#wardNotesCardBody" type="button" role="tab">Procedure Notes</button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseingNotesCardBody' ? 'active' : '' }}" id="nursing-tab"
                    data-bs-toggle="tab" data-bs-target="#nurseingNotesCardBody" type="button" role="tab">Nursing Notes</button>
            </li>
        @endcan
        @can('see-doctor-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'doctorNotesCardBody' ? 'active' : '' }}" id="doctor-tab"
                    data-bs-toggle="tab" data-bs-target="#doctorNotesCardBody" type="button" role="tab">Doctor Notes</button>
            </li>
        @endcan
        @can('see-prescriptions')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'prescriptionsNotesCardBody' ? 'active' : '' }}" id="prescriptions-tab"
                    data-bs-toggle="tab" data-bs-target="#prescriptionsNotesCardBody" type="button" role="tab">Prescriptions</button>
            </li>
        @endcan
        @can('see-investigations')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'investigationsCardBody' ? 'active' : '' }}" id="investigations-tab"
                    data-bs-toggle="tab" data-bs-target="#investigationsCardBody" type="button" role="tab">Investigations</button>
            </li>
        @endcan
    </ul>

    {{-- Tab content --}}
    <div class="tab-content pt-3">
        <div class="tab-pane fade {{ !$section || $section == 'patientInfo' ? 'show active' : '' }}" id="patientInfo" role="tabpanel">
            <div class="card">
                <div class="card-body">@include('admin.patients.partials.patient_info')</div>
            </div>
        </div>
        @can('see-vitals')
            <div class="tab-pane fade {{ $section == 'vitalsCardBody' ? 'show active' : '' }}" id="vitalsCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.vitals')</div>
                </div>
            </div>
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'nurseChartCardBody' ? 'show active' : '' }}" id="nurseChartCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.nurse_chart')</div>
                </div>
            </div>
        @endcan
        @can('see-accounts')
            <div class="tab-pane fade {{ $section == 'accountsCardBody' ? 'show active' : '' }}" id="accountsCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.accounts')</div>
                </div>
            </div>
        @endcan
        @can('see-admissions')
            <div class="tab-pane fade {{ $section == 'addmissionsCardBody' ? 'show active' : '' }}" id="addmissionsCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.admissions')</div>
                </div>
            </div>
        @endcan
        @can('see-procedures')
            <div class="tab-pane fade {{ $section == 'wardNotesCardBody' ? 'show active' : '' }}" id="wardNotesCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.procedure_notes')</div>
                </div>
            </div>
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'nurseingNotesCardBody' ? 'show active' : '' }}" id="nurseingNotesCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.nurse_notes')</div>
                </div>
            </div>
        @endcan
        @can('see-doctor-notes')
            <div class="tab-pane fade {{ $section == 'doctorNotesCardBody' ? 'show active' : '' }}" id="doctorNotesCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.doctor_notes')</div>
                </div>
            </div>
        @endcan
        @can('see-prescriptions')
            <div class="tab-pane fade {{ $section == 'prescriptionsNotesCardBody' ? 'show active' : '' }}" id="prescriptionsNotesCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.presc')</div>
                </div>
            </div>
        @endcan
        @can('see-investigations')
            <div class="tab-pane fade {{ $section == 'investigationsCardBody' ? 'show active' : '' }}" id="investigationsCardBody" role="tabpanel">
                <div class="card">
                    <div class="card-body">@include('admin.patients.partials.invest')</div>
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
    <!-- jQuery -->
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
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
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    //{
                    //    data: "doctor_id",
                    //    name: "doctor_id"
                    //},
                    {
                        data: "notes",
                        name: "notes"
                    },
                    //{
                    //    data: "created_at",
                    //    name: "created_at"
                    //},
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
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "doctor_id",
                        name: "doctor_id"
                    },
                    {
                        data: "billed_by",
                        name: "billed_by"
                    },
                    {
                        data: "bed_id",
                        name: "bed_id"
                    },
                    {
                        data: "show",
                        name: "show"
                    },
                ],
                // initComplete: function () {
                //     this.api().columns().every(function () {
                //         var column = this;
                //         var input = document.createElement("input");
                //         $(input).appendTo($(column.footer()).empty())
                //         .on('change', function () {
                //             column.search($(this).val(), false, false, true).draw();
                //         });
                //     });
                // },
                "paging": true
                // "lengthChange": false,
                // "searching": true,
                // "ordering": true,
                // "info": true,
                // "autoWidth": false
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

        function setSearchValProd(name, id, price) {
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedPrescBillRows[]' onclick='checkPrescBillRow(this)' data-price = '${price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_presc_dose[] required>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${price}')">x</button></td>
                </tr>
            `;

            $('#selected-products').append(mk);
            $('#consult_presc_res').html('');

        }

        function searchProducts(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-products') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_presc_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            // Append the new options to the list field
                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValProd('${data[i].product_name}[${data[i].product_code}](${data[i].stock.current_quantity} avail.)', '${data[i].id}', '${data[i].price.initial_sale_price}')">
                                   [${data[i].category.category_name}]<b>${data[i].product_name}[${data[i].product_code}]</b> (${data[i].stock.current_quantity} avail.) NGN ${data[i].price.initial_sale_price}</li>`;
                            $('#consult_presc_res').append(mk);
                            $('#consult_presc_res').show();
                        }
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

        function setSearchValSer(name, id, price) {
            var mk = `
                <tr>
                    <td><input type='checkbox' name='addedInvestBillRows[]' onclick='checkInvestBillRow(this)' data-price = '${price}' value='${id}' class='form-control addedRows'></td>
                    <td>${name}</td>
                    <td>${price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_invest_note[]>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this,'${price}')">x</button></td>
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
                        term: q
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_invest_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            // Append the new options to the list field
                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValSer('${data[i].service_name}[${data[i].service_code}]', '${data[i].id}', '${data[i].price.sale_price}')">
                                   [${data[i].category.category_name}]<b>${data[i].service_name}[${data[i].service_code}]</b> NGN ${data[i].price.sale_price}</li>`;
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
        function setResTempInModal(obj) {
            $('#invest_res_service_name').text($(obj).attr('data-service-name'));
            $('#invest_res_template').html($(obj).attr('data-template'));
            $('#invest_res_entry_id').val($(obj).attr('data-id'));
            $('#investResModal').modal('show');
        }

        function copyResTemplateToField() {
            $('#invest_res_template_submited').val($('#invest_res_template').html());
            return true;
        }
    </script>
    <script>
        function setResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));
            $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));
            $('#invest_res').html(res_obj.result);
            $('#res_sample_date').html(res_obj.sample_date);
            $('#res_result_date').html(res_obj.result_date);
            $('#res_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);
            $('#invest_name').text(res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname + '(' + res_obj
                .patient.file_no + ')');
            $('#investResViewModal').modal('show');
        }
    </script>
    <script>
        function PrintElem(elem) {
            var mywindow = window.open('', 'PRINT', 'height=600,width=800');

            mywindow.document.write('<html><head><title>' + document.title + '</title>');
            mywindow.document.write(
                `<link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}" />`);

            mywindow.document.write(
                `<link rel="stylesheet" href=" {{ asset('admin/assets/css/demo_1/style.css') }}" />`);
            mywindow.document.write('</head><body>');
            mywindow.document.write(document.getElementById(elem).innerHTML);
            mywindow.document.write('</body></html>');

            mywindow.document.close(); // IE >= 10

            // Wait for the window and its contents to load
            mywindow.onload = function() {
                mywindow.focus(); // Set focus for IE
                mywindow.print();
                // Optional: Uncomment the line below to close the window after printing
                // mywindow.close();
            };

            return true;
        }
    </script>
    <script>
        function setBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#assignBedModal').modal('show');
        }
    </script>

    <script>
        function newBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
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

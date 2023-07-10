@extends('admin.layouts.app')
@section('title', 'Patients Profile')
@section('page_name', 'Patients')
@section('subpage_name', 'Show Patient')
@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Patient Data</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-9">
                        <h1>{{ userfullname($user->id) }}</h1>
                        <h3>File No: {{ $patient->file_no }}</h3>
                        @if ($user->old_records)
                            <div class="form-group">
                                <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old Records</a>
                                <br>
                            </div>
                        @else
                            <div class="form-group">
                                <a href="#"><i class="fa fa-file"></i> No Old Records Attached</a>
                                <br>
                            </div>
                        @endif
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                            <br>
                        </div>
                    </div>
                </div>
                <br>
                <hr>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>Gender: </th>
                                <td>{{ $patient->gender ?? 'N/A' }}</td>
                                <th>D.O.B:</th>
                                <td>{{ $patient->dob ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Blood Group:</th>
                                <td>{{ $patient->blood_group ?? 'N/A' }}</td>
                                <th>Genotype :</th>
                                <td>{{ $patient->genotype ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Nationality: </th>
                                <td>{{ $patient->nationality ?? 'N/A' }}</td>
                                <th>Ethnicity:</th>
                                <td>{{ $patient->ethnicity ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Address: </th>
                                <td>{{ $patient->address ?? 'N/A' }}</td>
                                <th>Other info:</th>
                                <td>{{ $patient->misc ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Insurance/HMO: </th>
                                <td>{{ $patient->hmo->name ?? 'N/A' }}</td>
                                <th>HMO No:</th>
                                <td>{{ $patient->hmo_no ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Disability status:</th>
                                <td>{{ $patient->disability == 1 ? 'Disabled' : 'None' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @if ($user->assignRole == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Roles Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getRoleNames()))
                                @foreach ($user->getRoleNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif

                @if ($user->assignPermission == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Permission Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getPermissionNames()))
                                @foreach ($user->getPermissionNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Vitals
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#vitalsCardBody" aria-expanded="false" aria-controls="vitalsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="vitalsCardBody">
                vitals
            </div>
        </div>
        {{-- <div class="card mt-3">
            <div class="card-header">
                Billing
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#billingCardBody" aria-expanded="false" aria-controls="billingCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="billingCardBody">
                <form action="" method="post">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="presc_history_bills">
                        <thead>
                            <th>#</th>
                            <th>Select</th>
                            <th>Product</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <div class="form-group">
                        <label for="">Total cost of selected items</label>
                        <input type="number" value="0" class="form-control" id="presc_bill_tot" name="presc_bill_tot"
                            readonly required>

                    </div>
                    <div class="form-group">
                        <label for="">Total amount to bill</label>
                        <input type="number" value="0" id="presc_billed_tot" name="presc_billed_tot"
                            class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Are you sure you wish to bill the selected items')">Bill</button>
                    <button type="submit" value="dismiss_presc_bill" class="btn btn-danger"
                        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                        style="float: right">Dismiss</button>
                </form>
            </div>
        </div> --}}
        <div class="card mt-3">
            <div class="card-header">
                Accounts
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#accountsCardBody" aria-expanded="false" aria-controls="accountsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="accountsCardBody">
                accounts
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Admission History
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#addmissionsCardBody" aria-expanded="false"
                    aria-controls="addmissionsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="addmissionsCardBody">
                history
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                ward notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#wardNotesCardBody" aria-expanded="false" aria-controls="wardNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="wardNotesCardBody">
                ward notes
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                nursing notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#nurseingNotesCardBody" aria-expanded="false"
                    aria-controls="nurseingNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="nurseingNotesCardBody">
                nursing notes
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Doctor notes
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#doctorNotesCardBody" aria-expanded="false"
                    aria-controls="doctorNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="doctorNotesCardBody">
                <table class="table table-sm table-bordered table-striped" style="width: 100%" id="encounter_history_list">
                    <thead>
                        <th>#</th>
                        <th>Doctor</th>
                        <th>Notes</th>
                        <th>Time</th>
                    </thead>
                </table>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Prescriptions
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#prescriptionsNotesCardBody" aria-expanded="false"
                    aria-controls="prescriptionsNotesCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="prescriptionsNotesCardBody">
                <h4>Requested Prescription</h4>
                <form action="{{ route('product-bill-patient') }}" method="post">
                    @csrf
                    <h6>Requested Items</h6>
                    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="presc_history_bills">
                        <thead>
                            <th>#</th>
                            <th>Select</th>
                            <th>Product</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <hr>
                    <h6>Other Items</h6>
                    <label for="">Search products</label>
                    <input type="text" class="form-control" id="consult_presc_search"
                        onkeyup="searchProducts(this.value)" placeholder="search products...">
                    <ul class="list-group" id="consult_presc_res" style="display: none;">

                    </ul>
                    <br>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped">
                            <thead>
                                <th>*</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Dose/Freq.</th>
                                <th>*</th>
                            </thead>
                            <tbody id="selected-products">

                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="">Total cost of selected items</label>
                        <input type="number" value="0" class="form-control" id="presc_bill_tot"
                            name="presc_bill_tot" readonly required>

                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Are you sure you wish to bill the selected items')">Dispense/Bill</button>
                    <button type="submit" value="dismiss_presc_bill" name="dismiss_presc_bill" class="btn btn-danger"
                        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                        style="float: right">Dismiss</button>
                </form>
                <hr>
                <h4>Precription History</h4>
                <table class="table table-sm table-bordered table-striped" style="width: 100%" id="presc_history_list">
                    <thead>
                        <th>#</th>
                        <th>Product</th>
                        <th>Details</th>
                    </thead>
                </table>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">
                Investigations
                <button class="btn btn-primary pull-right" type="button" data-toggle="collapse"
                    data-target="#investigationsCardBody" aria-expanded="false"
                    aria-controls="investigationsCardBody">Toggle</button>
            </div>
            <div class="collapse card-body" id="investigationsCardBody">
                <h4>Requested Investigations</h4>
                <form action="{{ route('service-bill-patient') }}" method="post">
                    @csrf
                    <h6>Requested Items</h6>
                    <input type="hidden" name="patient_user_id" id="" value="{{ $patient->user->id }}">
                    <input type="hidden" name="patient_id" id="" value="{{ $patient->id }}">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="invest_history_bills">
                        <thead>
                            <th>#</th>
                            <th>Select</th>
                            <th>Service</th>
                            <th>Details</th>
                        </thead>
                    </table>
                    <hr>
                    <h6>Other Items</h6>
                    <label for="consult_invest_search">Search services</label>
                    <input type="text" class="form-control" id="consult_invest_search"
                        onkeyup="searchServices(this.value)" placeholder="search services...">
                    <ul class="list-group" id="consult_invest_res" style="display: none;">

                    </ul>
                    <br>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped">
                            <thead>
                                <th>*</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Notes/Specimen</th>
                                <th>*</th>
                            </thead>
                            <tbody id="selected-services">

                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="form-group">
                        <label for="">Total cost of selected items</label>
                        <input type="number" value="0" class="form-control" id="invest_bill_tot"
                            name="invest_bill_tot" readonly required>

                    </div>
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Are you sure you wish to bill the selected items')">Take
                        sample/Bill</button>
                    <button type="submit" value="dismiss_invest_bill" name="dismiss_invest_bill" class="btn btn-danger"
                        onclick="return confirm('Are you sure you wish to dissmiss the selected items')"
                        style="float: right">Dismiss</button>
                </form>
                <hr>
                <h4>Investigation Result Entry</h4>
                <table class="table table-sm table-bordered table-striped" style="width: 100%" id="invest_history_res">
                    <thead>
                        <th>#</th>
                        <th>Service</th>
                        <th>Details</th>
                        <th>Entry</th>
                    </thead>
                </table>
                <hr>
                <h4>Investigation History</h4>
                <table class="table table-sm table-bordered table-striped" style="width: 100%"
                    id="investigation_history_list">
                    <thead>
                        <th>#</th>
                        <th>Results</th>
                        <th>Details</th>
                    </thead>
                </table>

            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <div class="form-group">
                    <div class="col-sm-6">
                        <a href="{{ route('staff.index') }}" class="btn btn-danger"><i class="fa fa-close"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="{{route('service-save-result')}}" method="post" onsubmit="copyResTemplateToField()" >
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                                id="invest_res_service_name"></span>)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="invest_res_template" style="border: 1px solid black;">

                        </div>
                        <input type="text" id="invest_res_entry_id" name="invest_res_entry_id">
                        <input type="text" name="invest_res_template_submited" id="invest_res_template_submited">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" onclick="return confirm('Are you sure you wish to save this result entry? It can not be edited after!')" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endsection

@section('scripts')
    <!-- jQuery -->
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    {{-- <!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> --> --}}
    {{-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script> --}}
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>

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
                    {
                        data: "doctor_id",
                        name: "doctor_id"
                    },
                    {
                        data: "notes",
                        name: "notes"
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
        function add_to_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) + parseFloat(v);
            $('#presc_bill_tot').val(new_tot);
            $('#presc_billed_tot').val(new_tot);
        }

        function subtract_from_total_presc_bill(v) {
            let new_tot = parseFloat($('#presc_bill_tot').val()) - parseFloat(v);
            $('#presc_bill_tot').val(new_tot);
            $('#presc_billed_tot').val(new_tot);
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
            }
        }
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
            $('#invest_bill_tot').val(new_tot);
            $('#invest_billed_tot').val(new_tot);
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

        function copyResTemplateToField(){
            $('#invest_res_template_submited').val($('#invest_res_template').html());
            return true;
        }
    </script>

@endsection

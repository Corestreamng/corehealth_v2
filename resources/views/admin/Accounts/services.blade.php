@extends('admin.layouts.app')
@section('title', 'Services and Products ')
@section('page_name', 'Services')
@section('subpage_name', 'Services Request List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card-modern mt-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <a href="{{ route('paid-services', $id) }}" class="btn btn-light btn-sm">View Settled</a>
                        </div>
                        <div class="col-md-6 text-right">
                            {{-- Future: Add new request button here --}}
                        </div>
                    </div>
                </div>
                <form action="{{ route('service-payment') }}" method="POST" id="serviceProductForm">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="mb-0">Services</h3>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="printInvoiceBtn">
                                <i class="fa fa-print"></i> Print Invoice
                            </button>
                        </div>
                        <div class="table-responsive mb-4">
                            <table id="service-list" class="table table-hover table-bordered table-striped table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;">#</th>
                                        <th style="width:40px;">
                                            <input type="checkbox" id="selectAllServices" title="Select/Deselect All" />
                                        </th>
                                        <th style="min-width:120px;">Service Name</th>
                                        <th style="min-width:90px;">Category</th>
                                        <th style="width:70px;">Price</th>
                                        <th style="width:60px;">Quantity</th>
                                        <th style="width:90px;">Date</th>
                                        <th style="min-width:80px;">Staff Name</th>
                                        <th style="min-width:80px;">Patient Name</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <h4 class="mt-4">Products</h4>
                        <div class="table-responsive mb-4">
                            <table id="products-list" class="table table-hover table-bordered table-striped table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:32px;">#</th>
                                        <th style="width:40px;">
                                            <input type="checkbox" id="selectAllProducts" title="Select/Deselect All" />
                                        </th>
                                        <th style="min-width:120px;">Product Name</th>
                                        <th style="min-width:90px;">Category</th>
                                        <th style="width:70px;">Price</th>
                                        <th style="width:60px;">Quantity</th>
                                        <th style="width:90px;">Date</th>
                                        <th style="min-width:80px;">Staff Name</th>
                                        <th style="min-width:80px;">Patient Name</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <input type="hidden" name="id" id="myInput" value="{{ $id }}"><br>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-arrow-right"></i> Proceed
                            </button>
                        </div>
                    </div>
                    <br>
                </form>
            </div>
        </div>
    </div>

    {{-- Invoice Print Modal --}}
    <div class="modal fade" id="invoicePrintModal" tabindex="-1" role="dialog" aria-labelledby="invoicePrintModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="invoicePrintModalLabel">Invoice</h5>
                    <button type="button" class="close text-white"  data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>ESC</small></span>
                    </button>
                </div>
                <div class="modal-body" id="invoiceContent">
                    <div id="invoicePrintableArea">
                        <div class="text-center mb-2">
                            <img src="data:image/jpeg;base64,{{ appsettings()->logo ?? '' }}" alt="Logo"
                                style="width: 100px; display: block; margin: 0 auto 10px auto;" />
                            <h2 class="font-weight-bold mb-0">{{ appsettings()->site_name }}</h2>
                            <div>{{ appsettings()->contact_address }}</div>
                            <div>Phone: {{ appsettings()->contact_phones }} | Email: {{ appsettings()->contact_emails }}
                            </div>
                            <hr>
                            <h3 class="text-uppercase text-primary" style="letter-spacing:2px;">Hospital Invoice</h3>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <strong>Patient Name:</strong> <span id="invoicePatientName"></span><br>
                                <strong>File No:</strong> <span id="invoicePatientFile"></span>
                            </div>
                            <div class="col-md-6 text-right">
                                <strong>Date:</strong> <span id="invoiceDate"></span><br>
                                <strong>Staff:</strong> <span id="invoiceStaffName"></span>
                            </div>
                        </div>
                        <h5 class="mt-3">Services</h5>
                        <table class="table table-sm table-bordered" id="invoiceServicesTable">
                            <thead>
                                <tr>
                                    <th style="width:32px;">#</th>
                                    <th style="min-width:120px;">Service Name</th>
                                    <th style="min-width:90px;">Category</th>
                                    <th style="width:70px;">Price</th>
                                    <th style="width:60px;">Qty</th>
                                    <th style="width:90px;">Date</th>
                                    <th style="min-width:80px;">Staff</th>
                                    <th style="width:80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- JS will populate --}}
                            </tbody>
                        </table>
                        <h5 class="mt-4">Products</h5>
                        <table class="table table-sm table-bordered" id="invoiceProductsTable">
                            <thead>
                                <tr>
                                    <th style="width:32px;">#</th>
                                    <th style="min-width:120px;">Product Name</th>
                                    <th style="min-width:90px;">Category</th>
                                    <th style="width:70px;">Price</th>
                                    <th style="width:60px;">Qty</th>
                                    <th style="width:90px;">Date</th>
                                    <th style="min-width:80px;">Staff</th>
                                    <th style="width:80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- JS will populate --}}
                            </tbody>
                        </table>
                        <div class="text-right mt-3">
                            <h4>Total: <span id="invoiceGrandTotal"></span></h4>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    <button type="button" onclick="printInvoiceArea('invoicePrintableArea')" class="btn btn-primary">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        const dar = document.getElementById('myInput').value;
        $(function() {
            $('#service-list').DataTable({
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
                    "url": "{{route('service-list', ['id' => $id])}}",
                    "type": "GET"
                },
                "columns": [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "checkBox", name: "checkBox", orderable: false, searchable: false },
                    { data: "service.service_name", name: "service" },
                    { data: "service.category.category_name", name: "category" },
                    { data: "service.price.sale_price", name: "service" },
                    { data: "qty" },
                    { data: "created_at", name: "created_at" },
                    { data: "staff_name", name: "staff_name" },
                    { data: "patient_name", name: "patient_name" }
                ],
                "paging": true
            });

            $('#products-list').DataTable({
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
                    "url": "{{ route('accounts.product-list', ['id' => $id]) }}",
                    "type": "GET"
                },
                "columns": [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "checkBox", name: "checkBox", orderable: false, searchable: false },
                    { data: "product.product_name", name: "product" },
                    { data: "product.category.category_name", name: "category" },
                    { data: "product.price.current_sale_price", name: "price" },
                    { data: "qty" },
                    { data: "created_at", name: "created_at" },
                    { data: "staff_name", name: "staff_name" },
                    { data: "patient_name", name: "patient_name" }
                ],
                "paging": true
            });

            // Select All / Deselect All logic for services
            $(document).on('change', '#selectAllServices', function() {
                let checked = $(this).is(':checked');
                $('#service-list tbody input[type=checkbox][name="someCheckbox[]"]').prop('checked', checked);
            });
            $(document).on('change', '#service-list tbody input[type=checkbox][name="someCheckbox[]"]', function() {
                let all = $('#service-list tbody input[type=checkbox][name="someCheckbox[]"]').length;
                let checked = $('#service-list tbody input[type=checkbox][name="someCheckbox[]"]:checked').length;
                $('#selectAllServices').prop('checked', all > 0 && all === checked);
            });

            // Select All / Deselect All logic for products
            $(document).on('change', '#selectAllProducts', function() {
                let checked = $(this).is(':checked');
                $('#products-list tbody input[type=checkbox][name="productChecked[]"]').prop('checked', checked);
            });
            $(document).on('change', '#products-list tbody input[type=checkbox][name="productChecked[]"]', function() {
                let all = $('#products-list tbody input[type=checkbox][name="productChecked[]"]').length;
                let checked = $('#products-list tbody input[type=checkbox][name="productChecked[]"]:checked').length;
                $('#selectAllProducts').prop('checked', all > 0 && all === checked);
            });

            // Form submission validation
            $('#serviceProductForm').on('submit', function(e) {
                let servicesChecked = $('#service-list tbody input[type=checkbox][name="someCheckbox[]"]:checked').length;
                let productsChecked = $('#products-list tbody input[type=checkbox][name="productChecked[]"]:checked').length;

                if (servicesChecked === 0 && productsChecked === 0) {
                    e.preventDefault();
                    alert('Please select at least one service or product to proceed with payment.');
                    return false;
                }

                return true;
            });
        });

        // Print Invoice Modal Logic
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('printInvoiceBtn').addEventListener('click', function() {
                populateInvoiceModal();
                $('#invoicePrintModal').modal('show');
            });
        });

        function populateInvoiceModal() {
            document.getElementById('invoiceDate').textContent = (new Date()).toLocaleString();

            // Fetch patient info from the first checked row (if any)
            let patientName = '';
            let fileNo = '';
            let staffName = '';

            let firstServiceRow = $('#service-list').find('input[type=checkbox][name="someCheckbox[]"]:checked').first().closest('tr');
            if (firstServiceRow.length) {
                patientName = firstServiceRow.find('td').eq(8).text();
                staffName = firstServiceRow.find('td').eq(7).text();
            }
            let firstProductRow = $('#products-list').find('input[type=checkbox][name="productChecked[]"]:checked').first().closest('tr');
            if (!patientName && firstProductRow.length) {
                patientName = firstProductRow.find('td').eq(8).text();
                staffName = firstProductRow.find('td').eq(7).text();
            }

            // Fallback to blade
            @php
                $patient = \App\Models\patient::where('user_id', $id)->first();
            @endphp
            if (!patientName) {
                patientName = "{{ $patient ? $patient->fullname : 'N/A' }}";
            }
            fileNo = "{{ $patient ? $patient->file_no : 'N/A' }}";

            document.getElementById('invoicePatientName').textContent = patientName;
            document.getElementById('invoicePatientFile').textContent = fileNo;
            document.getElementById('invoiceStaffName').textContent = staffName;

            // Gather selected services
            let services = [];
            $('#service-list').find('input[type=checkbox][name="someCheckbox[]"]:checked').each(function(idx, el) {
                let row = $(el).closest('tr');
                let name = row.find('td').eq(2).text();
                let category = row.find('td').eq(3).text();
                let price = row.find('td').eq(4).text();
                let qty = row.find('input[name="serviceQty[]"]').val() || 1;
                let date = row.find('td').eq(6).text();
                let staff = row.find('td').eq(7).text();
                let total = parseFloat(price) * parseInt(qty);
                services.push({name, category, price, qty, date, staff, total});
            });

            // Gather selected products
            let products = [];
            $('#products-list').find('input[type=checkbox][name="productChecked[]"]:checked').each(function(idx, el) {
                let row = $(el).closest('tr');
                let name = row.find('td').eq(2).text();
                let category = row.find('td').eq(3).text();
                let price = row.find('td').eq(4).text();
                let qty = row.find('input[name="productQty[]"]').val() || 1;
                let date = row.find('td').eq(6).text();
                let staff = row.find('td').eq(7).text();
                let total = parseFloat(price) * parseInt(qty);
                products.push({name, category, price, qty, date, staff, total});
            });

            // Populate tables
            let sbody = $('#invoiceServicesTable tbody');
            let pbody = $('#invoiceProductsTable tbody');
            sbody.empty();
            pbody.empty();
            let grandTotal = 0;
            services.forEach(function(s, i) {
                grandTotal += s.total;
                sbody.append(`<tr>
                    <td>${i+1}</td>
                    <td>${s.name}</td>
                    <td>${s.category}</td>
                    <td>${s.price}</td>
                    <td>${s.qty}</td>
                    <td>${s.date}</td>
                    <td>${s.staff}</td>
                    <td>${s.total.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                </tr>`);
            });
            products.forEach(function(p, i) {
                grandTotal += p.total;
                pbody.append(`<tr>
                    <td>${i+1}</td>
                    <td>${p.name}</td>
                    <td>${p.category}</td>
                    <td>${p.price}</td>
                    <td>${p.qty}</td>
                    <td>${p.date}</td>
                    <td>${p.staff}</td>
                    <td>${p.total.toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                </tr>`);
            });
            document.getElementById('invoiceGrandTotal').textContent = '₦' + grandTotal.toLocaleString(undefined, {
                minimumFractionDigits: 2
            });
        }

        function printInvoiceArea(areaId) {
            var printContents = document.getElementById(areaId).innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload(); // reload to restore event handlers
        }
    </script>
@endsection

@section('styles')
    <style>
        /* Space conservation for table cells */
        #service-list th,
        #service-list td,
        #products-list th,
        #products-list td,
        #invoiceServicesTable th,
        #invoiceServicesTable td,
        #invoiceProductsTable th,
        #invoiceProductsTable td {
            padding: 0.25rem 0.5rem;
            font-size: 0.92rem;
            vertical-align: middle;
            white-space: nowrap;
        }

        #service-list input[type="number"],
        #products-list input[type="number"],
        #invoiceServicesTable input[type="number"],
        #invoiceProductsTable input[type="number"] {
            min-width: 48px;
            max-width: 70px;
            padding: 2px 4px;
            font-size: 0.95em;
            text-align: center;
        }

        #service-list .badge,
        #products-list .badge,
        #invoiceServicesTable .badge,
        #invoiceProductsTable .badge {
            font-size: 85%;
            padding: 0.25em 0.5em;
        }

        /* Hide overflow text in long columns, show ellipsis */
        #service-list td,
        #products-list td,
        #invoiceServicesTable td,
        #invoiceProductsTable td {
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }
    </style>
@endsection

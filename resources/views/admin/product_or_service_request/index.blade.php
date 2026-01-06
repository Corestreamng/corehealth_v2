@extends('admin.layouts.app')
@section('title', 'Services and Products')
@section('page_name', 'Services and Products')
@section('subpage_name', 'Services and Products Request List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <!-- Date Filter Card -->
            <div class="card mb-2">
                <div class="card-body">
                    <form id="dateRangeForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="button" id="fetchData" class="btn btn-primary btn-sm d-block">
                                        Fetch Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Services and Products Table -->
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            {{ __('Services') }}
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('add-to-queue') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                New Request
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Patient</th>
                                    <th>File No</th>
                                    <th>HMO/Insurance</th>
                                    <th>HMO No</th>
                                    <th>View</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payModalLabel">Settle Bills</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2" id="patient-meta"></div>
                    <div id="modal-alert" class="alert d-none" role="alert"></div>
                    <div class="table-responsive position-relative">
                        <div id="bill-loading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                            <div class="mt-2">Loading unpaid items…</div>
                        </div>
                        <table class="table table-sm table-bordered" id="bill-items">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="check-all"></th>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th>Coverage</th>
                                    <th>Price (Payable)</th>
                                    <th>Claims</th>
                                    <th>Qty</th>
                                    <th>Discount %</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="bill-items-body"></tbody>
                        </table>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Payment Type</label>
                            <select class="form-control" id="payment_type">
                                <option value="POS">POS</option>
                                <option value="CASH">Cash</option>
                                <option value="TRANSFER">Transfer</option>
                                <option value="TELLER">Teller</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="ACC_WITHDRAW">Credit Account</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" id="reference_no" value="{{ generate_invoice_no() }}">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div>
                                <div class="fw-bold">Total: <span id="grand-total">0.00</span></div>
                                <div class="text-muted">Discount: <span id="grand-discount">0.00</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3" id="receipt-tabs" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#a4-tab" role="tab">A4 Receipt</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#thermal-tab" role="tab">Thermal Receipt</a></li>
                            </ul>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Print receipts">
                                <button type="button" class="btn btn-outline-primary" id="btn-print-a4">Print A4</button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-print-thermal">Print Thermal</button>
                            </div>
                        </div>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="a4-tab" role="tabpanel">
                                <div id="a4-receipt" class="mt-2"></div>
                            </div>
                            <div class="tab-pane fade" id="thermal-tab" role="tabpanel">
                                <div id="thermal-receipt" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btn-pay-now">Pay Selected</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- DataTables -->
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>

    <script>
        $(function() {
            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
            });

            let currentUserId = null;
            let itemsCache = [];
            let currentPatientModelId = null;
            let isLoadingUnpaid = false;
            let isPaying = false;

            // Initialize DataTable
            const table = $('#products-list').DataTable({
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
                    "url": "{{ route('product-services-requesters-list') }}",
                    "type": "GET",
                    "data": function(d) {
                        // Add date range to AJAX request
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": [
                    {
                        data: "DT_RowIndex",
                        name: "DT_RowIndex",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "patient",
                        name: "patient"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo",
                        name: "hmo"
                    },
                    {
                        data: "hmo_no",
                        name: "hmo_no"
                    },
                    {
                        data: "show",
                        name: "show",
                        orderable: false,
                        searchable: false
                    },
                ],
                "paging": true
            });

            // Filter Button Event
            $('#fetchData').click(function() {
                table.ajax.reload();
            });

            // Open modal and fetch unpaid items
            $('#products-list').on('click', '.btn-pay', function(e) {
                e.preventDefault();
                if (isLoadingUnpaid || isPaying) return;
                currentUserId = $(this).data('user');
                $('#payModal').modal('show');
                loadUnpaid(currentUserId);
            });

            $('#check-all').on('change', function() {
                const checked = $(this).is(':checked');
                $('#bill-items-body').find('.item-check').prop('checked', checked);
                recompute();
            });

            $('#bill-items-body').on('input change', '.item-qty, .item-discount, .item-check', function() {
                recompute();
            });

            $('#btn-pay-now').on('click', function() {
                submitPayment();
            });

            $('#btn-print-a4').on('click', function() {
                printReceipt('#a4-receipt');
            });

            $('#btn-print-thermal').on('click', function() {
                printReceipt('#thermal-receipt');
            });

            // Refresh whole page when modal closes to ensure list stays in sync
            $('#payModal').on('hidden.bs.modal', function() {
                window.location.reload();
            });

            function loadUnpaid(userId, opts = {}) {
                const { keepReceipts = false, message = null } = opts;
                isLoadingUnpaid = true;
                setModalAlert('info', message || 'Loading unpaid items…');
                setTableLoading(true);
                if (!keepReceipts) {
                    setPayLoading(true, 'Loading…');
                    $('#receipt-tabs').hide();
                }
                $.get("{{ url('ajax/unpaid-items') }}/" + userId)
                    .done(function(res) {
                        itemsCache = res.items || [];
                        currentPatientModelId = res.patient?.id || null;
                        renderItems(res.patient, itemsCache);
                        setModalAlert('success', 'Unpaid items loaded. Select items to pay.');
                    })
                    .fail(function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Failed to load items.';
                        setModalAlert('danger', msg);
                        $('#bill-items-body').html('<tr><td colspan="9" class="text-center">Unable to load items</td></tr>');
                    })
                    .always(function() {
                        isLoadingUnpaid = false;
                        setTableLoading(false);
                        if (!keepReceipts) setPayLoading(false);
                    });
            }

            function renderItems(patient, items) {
                $('#patient-meta').html(`<strong>Patient:</strong> ${patient?.file_no || ''} - ${patient?.hmo_name || 'Self'} | HMO No: ${patient?.hmo_no || ''}`);
                const body = $('#bill-items-body');
                body.empty();
                if (!items.length) {
                    body.append('<tr><td colspan="9" class="text-center">No unpaid items found</td></tr>');
                    $('#check-all').prop('checked', false).prop('disabled', true);
                    setPayLoading(false);
                    return;
                }
                $('#check-all').prop('disabled', false);
                items.forEach((item, idx) => {
                    const coverageBadge = item.coverage_mode
                        ? `<span class="badge bg-info">${item.coverage_mode.toUpperCase()}</span>`
                        : '<span class="badge bg-secondary">Cash</span>';
                    const claims = item.claims_amount ? Number(item.claims_amount).toFixed(2) : '0.00';
                    const price = item.payable_amount !== null && item.payable_amount !== undefined
                        ? item.payable_amount
                        : item.price || 0;
                    const row = `
                        <tr data-id="${item.id}" data-price="${price}">
                            <td><input type="checkbox" class="item-check" checked></td>
                            <td>${item.type === 'service' ? 'Service' : 'Product'}</td>
                            <td>${item.name || ''}<br><small class="text-muted">${item.code || ''}</small></td>
                            <td>${coverageBadge}</td>
                            <td>${price}</td>
                            <td>${claims}</td>
                            <td><input type="number" min="1" value="${item.qty || 1}" class="form-control form-control-sm item-qty" style="width:80px"></td>
                            <td><input type="number" min="0" max="100" value="0" class="form-control form-control-sm item-discount" style="width:80px"></td>
                            <td class="row-total">0.00</td>
                        </tr>`;
                    body.append(row);
                });
                recompute();
            }

            function recompute() {
                let total = 0;
                let discountTotal = 0;
                $('#bill-items-body tr').each(function() {
                    const checked = $(this).find('.item-check').is(':checked');
                    const price = parseFloat($(this).data('price')) || 0;
                    const qty = parseFloat($(this).find('.item-qty').val()) || 1;
                    const disc = parseFloat($(this).find('.item-discount').val()) || 0;
                    const rowBase = price * qty;
                    const rowDisc = rowBase * (disc / 100);
                    const rowTotal = rowBase - rowDisc;
                    $(this).find('.row-total').text(rowTotal.toFixed(2));
                    if (checked) {
                        total += rowTotal;
                        discountTotal += rowDisc;
                    }
                });
                $('#grand-total').text(total.toFixed(2));
                $('#grand-discount').text(discountTotal.toFixed(2));
            }

            function submitPayment() {
                if (!currentUserId) return;
                const items = [];
                $('#bill-items-body tr').each(function() {
                    if (!$(this).find('.item-check').is(':checked')) return;
                    items.push({
                        id: $(this).data('id'),
                        qty: parseFloat($(this).find('.item-qty').val()) || 1,
                        discount: parseFloat($(this).find('.item-discount').val()) || 0,
                    });
                });

                if (!items.length) {
                    alert('Please select at least one item to pay.');
                    return;
                }

                const payload = {
                    patient_id: currentPatientModelId,
                    payment_type: $('#payment_type').val(),
                    reference_no: $('#reference_no').val(),
                    items: items
                };

                if (!payload.patient_id) {
                    alert('Unable to resolve patient id for payment.');
                    return;
                }
                setModalAlert('info', 'Processing payment…');
                setPayLoading(true, 'Processing…');
                finalizePay(payload);
            }

            function finalizePay(payload) {
                $.ajax({
                    url: "{{ route('ajax-pay') }}",
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: function(res) {
                        setModalAlert('success', 'Payment successful. Receipts are ready below.');
                        $('#receipt-tabs').show();
                        $('#a4-receipt').html(res.receipt_a4 || '');
                        $('#thermal-receipt').html(res.receipt_thermal || '');
                        table.ajax.reload();
                        // Refresh unpaid items but keep receipts visible
                        loadUnpaid(currentUserId, { keepReceipts: true, message: 'Refreshing unpaid items…' });
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Payment failed. Please try again.';
                        setModalAlert('danger', msg);
                    },
                    complete: function() {
                        setPayLoading(false);
                        isPaying = false;
                    }
                });
            }

            function printReceipt(selector) {
                const html = $(selector).html();
                if (!html || !html.trim()) {
                    setModalAlert('warning', 'No receipt content to print yet.');
                    return;
                }
                const printWindow = window.open('', '_blank', 'width=800,height=900');
                if (!printWindow) {
                    setModalAlert('warning', 'Please allow pop-ups to print the receipt.');
                    return;
                }
                const styles = document.querySelectorAll('link[rel="stylesheet"], style');
                let headContent = '';
                styles.forEach((el) => {
                    headContent += el.outerHTML;
                });
                printWindow.document.write(`<!doctype html><html><head>${headContent}</head><body>${html}</body></html>`);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
                // Close shortly after to avoid stray blank windows if user cancels
                setTimeout(() => { printWindow.close(); }, 500);
            }

            function setModalAlert(type, message) {
                const el = $('#modal-alert');
                if (!message) {
                    el.addClass('d-none').text('');
                    return;
                }
                el.removeClass('d-none alert-success alert-danger alert-warning alert-info');
                el.addClass('alert-' + type).text(message);
            }

            function setTableLoading(active) {
                if (active) {
                    $('#bill-loading').removeClass('d-none');
                    $('#bill-items').addClass('opacity-50');
                    $('#bill-items-body').addClass('d-none');
                    $('#check-all').prop('checked', false).prop('disabled', true);
                } else {
                    $('#bill-loading').addClass('d-none');
                    $('#bill-items').removeClass('opacity-50');
                    $('#bill-items-body').removeClass('d-none');
                }
            }

            function setPayLoading(active, text) {
                const btn = $('#btn-pay-now');
                if (active) {
                    if (!btn.data('orig-text')) btn.data('orig-text', btn.text());
                    btn.prop('disabled', true).text(text || 'Processing…');
                    isPaying = true;
                } else {
                    btn.prop('disabled', false).text(btn.data('orig-text') || 'Pay Selected');
                    isPaying = false;
                }
            }
        });
    </script>
@endsection

$(function() {
    let claimsTable;
    let remittancesTable;
    let authCodesTable;
    let selectedClaimIds = [];
    let currentRemittanceId = null;
    let selectedPatientId = null;

    // App settings for branding
    const appSettings = {
        siteName: "{{ appsettings()->site_name ?? config('app.name') }}",
        logo: "{{ appsettings()->logo ?? '' }}",
        address: "{{ appsettings()->contact_address ?? '' }}",
        phones: "{{ appsettings()->contact_phones ?? '' }}",
        emails: "{{ appsettings()->contact_emails ?? '' }}",
        hosColor: "{{ appsettings()->hos_color ?? '#0066cc' }}"
    };

    // Initialize Claims DataTable
    function initClaimsTable() {
        if (claimsTable) {
            claimsTable.destroy();
        }

        claimsTable = $('#claimsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.claims') }}",
                data: function(d) {
                    d.hmo_id = $('#filter_hmo').val();
                    d.status = $('#filter_status').val();
                    d.date_from = $('#filter_date_from').val();
                    d.date_to = $('#filter_date_to').val();
                    d.submission_status = $('#filter_submission').val();
                    d.payment_status = $('#filter_payment').val();
                    d.service_category_id = $('#filter_service_category').val();
                    d.product_category_id = $('#filter_product_category').val();
                    d.service_type = $('#filter_type').val();
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        return '<input type="checkbox" class="claim-checkbox" data-id="' + data.id + '">';
                    }
                },
                { data: 'DT_RowIndex', orderable: false },
                { data: 'patient_name' },
                { data: 'file_no' },
                { data: 'hmo_no' },
                { data: 'hmo_name' },
                { data: 'service_date' },
                { data: 'item_name' },
                { data: 'auth_code_display' },
                { data: 'qty_display' },
                { data: 'claim_amount' },
                { data: 'status_badge' },
                { data: 'submission_badge' },
                { data: 'payment_badge' }
            ],
            order: [[6, 'desc']],
            dom: 'Bfrtip',
            buttons: ['pageLength', 'copy', 'excel', 'pdf'],
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]]
        });
    }

    // Initialize Remittances DataTable
    function initRemittancesTable() {
        if (remittancesTable) {
            remittancesTable.destroy();
        }

        remittancesTable = $('#remittancesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.remittances') }}",
                data: function(d) {
                    d.hmo_id = $('#remittance_filter_hmo').val();
                    d.date_from = $('#remittance_filter_from').val();
                    d.date_to = $('#remittance_filter_to').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false },
                { data: 'hmo_name' },
                { data: 'amount_formatted' },
                { data: 'payment_date_formatted' },
                { data: 'reference_number', defaultContent: '-' },
                { data: 'period' },
                { data: 'created_by_name' },
                { data: 'actions', orderable: false }
            ],
            order: [[3, 'desc']]
        });
    }

    // Load Outstanding Report
    function loadOutstandingReport() {
        $.get("{{ route('hmo.reports.outstanding') }}", function(response) {
            // Update summary
            $('#summaryTotalClaims').text('₦' + formatNumber(response.summary.total_claims));
            $('#summaryTotalPaid').text('₦' + formatNumber(response.summary.total_paid));
            $('#summaryOutstanding').text('₦' + formatNumber(response.summary.total_outstanding));

            // Calculate overdue (90+ days)
            let overdue = response.data.reduce((sum, item) => sum + parseFloat(item.aging_over_90 || 0), 0);
            $('#summaryOverdue').text('₦' + formatNumber(overdue));

            // Build table
            let html = '';
            response.data.forEach(function(item) {
                html += `<tr>
                    <td><strong>${item.hmo_name}</strong></td>
                    <td>₦${formatNumber(item.total_claims)}</td>
                    <td class="text-success">₦${formatNumber(item.paid)}</td>
                    <td class="text-danger font-weight-bold">₦${formatNumber(item.outstanding)}</td>
                    <td class="aging-cell-current">₦${formatNumber(item.aging_current)}</td>
                    <td class="aging-cell-warning">₦${formatNumber(item.aging_31_60)}</td>
                    <td class="aging-cell-danger">₦${formatNumber(item.aging_61_90)}</td>
                    <td class="${item.aging_over_90> 0 ? 'aging-cell-critical' : ''}">₦${formatNumber(item.aging_over_90)}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-hmo-claims" data-hmo-id="${item.hmo_id}">
                            <i class="fa fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });

            if (html === '') {
                html = '<tr><td colspan="9" class="text-center">No outstanding claims</td></tr>';
            }

            $('#outstandingTableBody').html(html);
        });
    }

    // Load Monthly Summary
    function loadMonthlySummary() {
        let month = $('#monthlyMonth').val();
        let year = $('#monthlyYear').val();

        $.get("{{ route('hmo.reports.monthly') }}", { month: month, year: year }, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card-modern bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Claims</h6>
                                <h3>₦${response.summary.total_claims}</h3>
                                <small>${response.summary.claims_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Approved</h6>
                                <h3>₦${response.summary.approved_total}</h3>
                                <small>${response.summary.approved_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-danger text-white">
                            <div class="card-body text-center">
                                <h6>Rejected</h6>
                                <h3>₦${response.summary.rejected_total}</h3>
                                <small>${response.summary.rejected_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Remittances</h6>
                                <h3>₦${response.summary.total_remittances}</h3>
                                <small>Received</small>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="mb-3">Claims by HMO</h6>
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>HMO</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Pending</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>`;

            response.by_hmo.forEach(function(hmo) {
                html += `<tr>
                    <td>${hmo.hmo_name}</td>
                    <td>₦${formatNumber(hmo.total_claims)}</td>
                    <td class="text-success">₦${formatNumber(hmo.approved)}</td>
                    <td class="text-danger">₦${formatNumber(hmo.rejected)}</td>
                    <td class="text-warning">₦${formatNumber(hmo.pending)}</td>
                    <td>${hmo.count}</td>
                </tr>`;
            });

            html += `</tbody></table>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6>By Service Type</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Products</span>
                                <strong>₦${formatNumber(response.by_type.products)}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Services</span>
                                <strong>₦${formatNumber(response.by_type.services)}</strong>
                            </li>
                        </ul>
                    </div>
                </div>`;

            $('#monthlySummaryContent').html(html);
        });
    }

    // Format number helper
    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Report card click handler - using event delegation
    $(document).on('click', '.report-card-modern', function(e) {
        e.preventDefault();
        e.stopPropagation();

        let $card = $(this);
        let reportType = $card.data('report');

        console.log('Card clicked:', reportType);

        // Remove active from all and add to clicked
        $('.report-card-modern').removeClass('active');
        $card.addClass('active');

        // Hide all sections then show the selected one
        $('.report-section').hide();

        switch(reportType) {
            case 'claims':
                $('#claimsReportSection').show();
                if (!claimsTable) initClaimsTable();
                break;
            case 'outstanding':
                $('#outstandingReportSection').show();
                loadOutstandingReport();
                break;
            case 'remittances':
                $('#remittancesSection').show();
                if (!remittancesTable) initRemittancesTable();
                loadRemittanceSummary();
                break;
            case 'monthly':
                $('#monthlySummarySection').show();
                break;
            case 'patient':
                $('#patientHistorySection').show();
                initPatientSearch();
                break;
            case 'utilization':
                $('#utilizationSection').show();
                break;
            case 'authcodes':
                $('#authCodesSection').show();
                if (!authCodesTable) initAuthCodesTable();
                break;
        }
    });

    // Initialize default report
    initClaimsTable();

    // Filter handlers
    $('#applyFilters').on('click', function() {
        claimsTable.ajax.reload();
    });

    $('#clearFilters').on('click', function() {
        $('#filter_hmo, #filter_status, #filter_submission, #filter_payment, #filter_service_category, #filter_product_category, #filter_type').val('');
        $('#filter_date_from, #filter_date_to').val('');
        claimsTable.ajax.reload();
    });

    // Claim checkbox handlers
    $('#selectAllClaims').on('change', function() {
        let checked = $(this).prop('checked');
        $('.claim-checkbox').prop('checked', checked);
        updateSelectedClaims();
    });

    $(document).on('change', '.claim-checkbox', function() {
        updateSelectedClaims();
    });

    function updateSelectedClaims() {
        selectedClaimIds = [];
        $('.claim-checkbox:checked').each(function() {
            selectedClaimIds.push($(this).data('id'));
        });
        $('#selectedClaimsInfo').text(selectedClaimIds.length + ' claims selected');
        $('#markSubmittedBtn').prop('disabled', selectedClaimIds.length === 0);
    }

    // Mark as submitted
    $('#markSubmittedBtn').on('click', function() {
        if (selectedClaimIds.length === 0) return;

        if (!confirm('Mark ' + selectedClaimIds.length + ' claims as submitted to HMO?')) return;

        $.post("{{ route('hmo.reports.mark-submitted') }}", {
            _token: '{{ csrf_token() }}',
            claim_ids: selectedClaimIds
        }, function(response) {
            if (response.success) {
                toastr.success(response.message);
                claimsTable.ajax.reload();
                selectedClaimIds = [];
                updateSelectedClaims();
            }
        });
    });

    // Print Report
    $('#printReportBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });

        $.get("{{ route('hmo.reports.print-data') }}?" + params, function(response) {
            let html = generatePrintHTML(response);
            $('#printPreviewContent').html(html);
            $('#printPreviewModal').modal('show');
        });
    });

    // Generate Print HTML
    function generatePrintHTML(data) {
        let logoHtml = data.hospital.logo
            ? `<img src="data:image/png;base64,${data.hospital.logo}" style="max-height: 80px;" alt="Logo">`
            : `<h2>${data.hospital.name}</h2>`;

        let html = `
            <div style="font-family: Arial, sans-serif;">
                <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid ${appSettings.hosColor}; padding-bottom: 15px;">
                    ${logoHtml}
                    <h3 style="margin: 10px 0 5px 0;">${data.hospital.name}</h3>
                    <p style="margin: 0; font-size: 12px;">${data.hospital.address}</p>
                    <p style="margin: 0; font-size: 12px;">Tel: ${data.hospital.phones} | Email: ${data.hospital.emails}</p>
                </div>

                <div style="text-align: center; margin-bottom: 20px;">
                    <h4 style="margin: 0; color: ${appSettings.hosColor};">${data.report.title}</h4>
                    <p style="margin: 5px 0;"><strong>HMO:</strong> ${data.report.hmo_name}</p>
                    <p style="margin: 5px 0;"><strong>Period:</strong> ${data.report.period}</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: ${appSettings.hosColor}; color: white;">
                            <th style="border: 1px solid #ddd; padding: 8px;">S/N</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Patient</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">File No</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">HMO No</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Date</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Item</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Auth Code</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Qty</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Amount (₦)</th>
                        </tr>
                    </thead>
                    <tbody>`;

        data.claims.forEach(function(claim) {
            html += `<tr>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.sn}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.patient_name}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.file_no}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.hmo_no}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.service_date}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.item}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.auth_code}</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">${claim.qty}</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: right;">${claim.claim_amount}</td>
            </tr>`;
        });

        html += `</tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="8" style="border: 1px solid #ddd; padding: 8px; text-align: right;">TOTAL CLAIMS:</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">₦${data.summary.total_claims}</td>
                        </tr>
                    </tfoot>
                </table>

                <div style="margin-top: 30px; font-size: 11px;">
                    <p><strong>Summary:</strong> ${data.summary.total_count} claims totaling ₦${data.summary.total_claims}</p>
                    <p><strong>Approved:</strong> ${data.summary.approved_count} claims (₦${data.summary.total_approved})</p>
                </div>

                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Prepared By
                        </div>
                        <small>${data.report.generated_by}</small>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Verified By
                        </div>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Authorized Signature
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
                    <p>Generated on ${data.report.generated_at}</p>
                </div>
            </div>`;

        return html;
    }

    // Do Print
    $('#doPrintBtn').on('click', function() {
        let content = $('#printPreviewContent').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>HMO Claims Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    @media print {
                        body { padding: 20px; }
                        @page { margin: 1cm; }
                    }
                </style>
            </head>
            <body>${content}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });

    // Export Excel
    $('#exportExcelBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
        window.location.href = "{{ route('hmo.reports.export-excel') }}?" + params;
    });

    // Remittance handlers
    $('#addRemittanceBtn').on('click', function() {
        $('#remittanceModalTitle').text('Record HMO Remittance');
        $('#remittanceForm')[0].reset();
        $('#remittance_id').val('');
        $('#remittance_bank_id').val('');
        $('#remittanceModal').modal('show');
    });

    $('#remittanceForm').on('submit', function(e) {
        e.preventDefault();

        let id = $('#remittance_id').val();
        let url = id ? "{{ url('hmo/reports/remittances') }}/" + id : "{{ route('hmo.reports.remittances.store') }}";
        let method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            method: method,
            data: {
                _token: '{{ csrf_token() }}',
                hmo_id: $('#remittance_hmo_id').val(),
                bank_id: $('#remittance_bank_id').val(),
                amount: $('#remittance_amount').val(),
                payment_date: $('#remittance_payment_date').val(),
                reference_number: $('#remittance_reference').val(),
                payment_method: $('#remittance_method').val(),
                bank_name: $('#remittance_bank').val(),
                period_from: $('#remittance_period_from').val(),
                period_to: $('#remittance_period_to').val(),
                notes: $('#remittance_notes').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#remittanceModal').modal('hide');
                    remittancesTable.ajax.reload();
                    loadRemittanceSummary();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // View remittance
    $(document).on('click', '.view-remittance-btn', function() {
        let id = $(this).data('id');
        currentRemittanceId = id;

        $.get("{{ url('hmo/reports/remittances') }}/" + id, function(response) {
            let rem = response.remittance;
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>HMO:</th><td>${rem.hmo_name}</td></tr>
                            <tr><th>Amount:</th><td><strong>₦${rem.amount}</strong></td></tr>
                            <tr><th>Payment Date:</th><td>${rem.payment_date}</td></tr>
                            <tr><th>Reference:</th><td>${rem.reference_number || '-'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Method:</th><td>${rem.payment_method || '-'}</td></tr>
                            <tr><th>Receiving Account:</th><td>${rem.bank_account || '-'}</td></tr>
                            <tr><th>HMO's Bank:</th><td>${rem.bank_name || '-'}</td></tr>
                            <tr><th>Period:</th><td>${rem.period_from || '-'} to ${rem.period_to || '-'}</td></tr>
                            <tr><th>Recorded By:</th><td>${rem.created_by}</td></tr>
                        </table>
                    </div>
                </div>
                ${rem.notes ? `<div class="alert alert-info"><strong>Notes:</strong> ${rem.notes}</div>` : ''}
                <h6 class="mt-3">Linked Claims (${response.claims.length})</h6>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Patient</th><th>Item</th><th>Amount</th></tr></thead>
                    <tbody>`;

            response.claims.forEach(function(claim) {
                html += `<tr><td>${claim.patient}</td><td>${claim.item}</td><td>₦${claim.amount}</td></tr>`;
            });

            html += `</tbody>
                <tfoot><tr><th colspan="2">Total:</th><th>₦${response.claims_total}</th></tr></tfoot>
                </table>`;

            $('#viewRemittanceContent').html(html);
            $('#viewRemittanceModal').modal('show');
        });
    });

    // Edit remittance
    $(document).on('click', '.edit-remittance-btn', function() {
        let id = $(this).data('id');

        $.get("{{ url('hmo/reports/remittances') }}/" + id, function(response) {
            let rem = response.remittance;
            $('#remittanceModalTitle').text('Edit Remittance');
            $('#remittance_id').val(rem.id);
            $('#remittance_hmo_id').val(rem.hmo_id);
            $('#remittance_bank_id').val(rem.bank_id);
            $('#remittance_amount').val(parseFloat(rem.amount.replace(/,/g, '')));
            $('#remittance_payment_date').val(rem.payment_date);
            $('#remittance_reference').val(rem.reference_number);
            $('#remittance_method').val(rem.payment_method);
            $('#remittance_bank').val(rem.bank_name);
            $('#remittance_period_from').val(rem.period_from);
            $('#remittance_period_to').val(rem.period_to);
            $('#remittance_notes').val(rem.notes);
            $('#remittanceModal').modal('show');
        });
    });

    // Delete remittance
    $(document).on('click', '.delete-remittance-btn', function() {
        let id = $(this).data('id');

        if (!confirm('Are you sure you want to delete this remittance? Claims will be unlinked.')) return;

        $.ajax({
            url: "{{ url('hmo/reports/remittances') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    remittancesTable.ajax.reload();
                    loadRemittanceSummary();
                }
            }
        });
    });

    // Filter remittances
    $('#filterRemittances').on('click', function() {
        remittancesTable.ajax.reload();
    });

    // Load remittance summary
    function loadRemittanceSummary() {
        $.get("{{ route('hmo.reports.outstanding') }}", function(response) {
            let html = `
                <div class="text-center mb-3">
                    <h3 class="text-danger">₦${formatNumber(response.summary.total_outstanding)}</h3>
                    <small class="text-muted">Total Outstanding</small>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Claims:</span>
                    <strong>₦${formatNumber(response.summary.total_claims)}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Paid:</span>
                    <strong class="text-success">₦${formatNumber(response.summary.total_paid)}</strong>
                </div>`;

            $('#remittanceSummary').html(html);
        });
    }

    // Load monthly summary
    $('#loadMonthlySummary').on('click', function() {
        loadMonthlySummary();
    });

    // Print outstanding report
    $('#printOutstandingBtn').on('click', function() {
        let content = $('#outstandingReportSection .card-body').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Outstanding Claims Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    .aging-cell-current { background-color: #d4edda !important; }
                    .aging-cell-warning { background-color: #fff3cd !important; }
                    .aging-cell-danger { background-color: #f8d7da !important; }
                    .aging-cell-critical { background-color: #dc3545 !important; color: white; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Outstanding HMO Claims Report</h4>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px;">
                    <div style="display: flex; justify-content: space-between;">
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div>
                        </div>
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div>
                        </div>
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });

    // View HMO specific claims from outstanding table
    $(document).on('click', '.view-hmo-claims', function() {
        let hmoId = $(this).data('hmo-id');
        $('#filter_hmo').val(hmoId);
        $('#filter_status').val('approved');
        $('.report-card-modern[data-report="claims"]').click();
        setTimeout(() => claimsTable.ajax.reload(), 100);
    });

    // PDF Export
    $('#exportPdfBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
        window.location.href = "{{ route('hmo.reports.export-pdf') }}?" + params;
    });

    // =============================================
    // PATIENT HISTORY SECTION
    // =============================================
    let patientSearchInitialized = false;

    function initPatientSearch() {
        if (patientSearchInitialized) return;

        $('#patientSearchSelect').select2({
            placeholder: 'Type patient name, file no, or HMO no...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: "{{ route('hmo.reports.search-patients') }}",
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    return { results: data };
                }
            }
        });

        patientSearchInitialized = true;
    }

    $('#patientSearchSelect').on('select2:select', function(e) {
        let data = e.params.data;
        selectedPatientId = data.id;
        $('#patientInfoText').html(`<strong>${data.text}</strong> | HMO: ${data.hmo_name}`);
        $('#selectedPatientInfo').show();
        loadPatientClaims(data.id);
    });

    function loadPatientClaims(patientId) {
        $('#patientClaimsContent').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><p>Loading patient claims...</p></div>');

        $.get("{{ url('hmo/reports/patient') }}/" + patientId, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card-modern border-left-primary">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Patient Details</h6>
                                <p class="mb-1"><strong>Name:</strong> ${response.patient.name}</p>
                                <p class="mb-1"><strong>File No:</strong> ${response.patient.file_no}</p>
                                <p class="mb-1"><strong>HMO No:</strong> ${response.patient.hmo_no}</p>
                                <p class="mb-0"><strong>HMO:</strong> ${response.patient.hmo_name}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card-modern bg-success text-white">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0">₦${response.summary.total_claims}</h4>
                                        <small>Total Claims (Approved)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern bg-info text-white">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0">₦${response.summary.total_patient_paid}</h4>
                                        <small>Patient Paid</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="alert alert-success mb-0 py-2 text-center">
                                    <strong>${response.summary.approved_count}</strong><br><small>Approved</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-danger mb-0 py-2 text-center">
                                    <strong>${response.summary.rejected_count}</strong><br><small>Rejected</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning mb-0 py-2 text-center">
                                    <strong>${response.summary.pending_count}</strong><br><small>Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <h6><i class="fa fa-list"></i> Claims History</h6>
                    <button class="btn btn-success btn-sm" onclick="printPatientReport(${patientId})">
                        <i class="fa fa-print"></i> Print Report
                    </button>
                </div>

                <table class="table table-sm table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Auth Code</th>
                            <th>Claim (₦)</th>
                            <th>Patient Pays (₦)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

            response.claims.forEach(function(claim) {
                let statusClass = claim.status === 'Approved' ? 'success' : (claim.status === 'Rejected' ? 'danger' : 'warning');
                html += `<tr>
                    <td>${claim.date}</td>
                    <td>${claim.type}</td>
                    <td>${claim.item}</td>
                    <td>${claim.qty}</td>
                    <td>${claim.auth_code}</td>
                    <td class="text-right">${claim.claim_amount}</td>
                    <td class="text-right">${claim.patient_pays}</td>
                    <td><span class="badge badge-${statusClass}">${claim.status}</span></td>
                </tr>`;
            });

            html += '</tbody></table>';
            $('#patientClaimsContent').html(html);
        });
    }

    // Print patient report
    window.printPatientReport = function(patientId) {
        $.get("{{ url('hmo/reports/patient') }}/" + patientId + "/print", function(data) {
            let html = generatePatientPrintHTML(data);
            let printWindow = window.open('', '', 'height=800,width=1000');
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 500);
        });
    }

    function generatePatientPrintHTML(data) {
        let html = `
            <html>
            <head>
                <title>Patient Claims Report</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; font-size: 12px; }
                    .header { text-align: center; border-bottom: 2px solid ${data.hospital.color}; padding-bottom: 15px; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    th { background: ${data.hospital.color}; color: white; padding: 8px; text-align: left; }
                    td { border: 1px solid #ddd; padding: 6px; }
                    .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
                    .sig-box { width: 30%; text-align: center; }
                    .sig-line { border-top: 1px solid #000; margin-top: 50px; padding-top: 5px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>${data.hospital.name}</h2>
                    <p>${data.hospital.address}</p>
                    <p>Tel: ${data.hospital.phones} | Email: ${data.hospital.emails}</p>
                </div>

                <h3 style="text-align: center; color: ${data.hospital.color};">Patient Claims Report</h3>

                <table style="width: 60%; margin: 0 auto 20px; border: none;">
                    <tr><td style="border:none;"><strong>Patient:</strong></td><td style="border:none;">${data.patient.name}</td></tr>
                    <tr><td style="border:none;"><strong>File No:</strong></td><td style="border:none;">${data.patient.file_no}</td></tr>
                    <tr><td style="border:none;"><strong>HMO No:</strong></td><td style="border:none;">${data.patient.hmo_no}</td></tr>
                    <tr><td style="border:none;"><strong>HMO:</strong></td><td style="border:none;">${data.patient.hmo_name}</td></tr>
                </table>

                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Auth Code</th>
                            <th>Claim (₦)</th>
                            <th>Patient Pays (₦)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

        data.claims.forEach(function(claim) {
            html += `<tr>
                <td>${claim.sn}</td>
                <td>${claim.date}</td>
                <td>${claim.type}</td>
                <td>${claim.item}</td>
                <td>${claim.auth_code}</td>
                <td style="text-align:right;">${claim.claim_amount}</td>
                <td style="text-align:right;">${claim.patient_pays}</td>
                <td>${claim.status}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr style="background:#f0f0f0; font-weight:bold;">
                        <td colspan="5" style="text-align:right;">TOTALS:</td>
                        <td style="text-align:right;">₦${data.summary.total_claims}</td>
                        <td style="text-align:right;">₦${data.summary.total_patient_paid}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="signatures">
                <div class="sig-box"><div class="sig-line">Prepared By</div></div>
                <div class="sig-box"><div class="sig-line">Verified By</div></div>
                <div class="sig-box"><div class="sig-line">Authorized Signature</div></div>
            </div>

            <p style="text-align:center; margin-top:30px; font-size:10px; color:#999;">
                Generated on ${data.generated_at} by ${data.generated_by}
            </p>
            </body></html>`;

        return html;
    }

    // =============================================
    // SERVICE UTILIZATION SECTION
    // =============================================
    $('#loadUtilization').on('click', function() {
        loadUtilizationReport();
    });

    function loadUtilizationReport() {
        let dateFrom = $('#util_date_from').val();
        let dateTo = $('#util_date_to').val();

        $('#utilizationContent').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><p>Loading utilization data...</p></div>');

        $.get("{{ route('hmo.reports.utilization') }}", { date_from: dateFrom, date_to: dateTo }, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card-modern bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_claims)}</h4>
                                <small>Total HMO Claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_services)}</h4>
                                <small>Services Revenue</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_products)}</h4>
                                <small>Products Revenue</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card-modern bg-secondary text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">${response.summary.total_count}</h4>
                                <small>Total Transactions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fa fa-star"></i> Top 10 Services</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Service</th><th>Category</th><th>Count</th><th class="text-right">Revenue</th></tr></thead>
                                    <tbody>`;

            response.top_services.forEach(function(item) {
                html += `<tr>
                    <td>${item.name}</td>
                    <td><small class="text-muted">${item.category}</small></td>
                    <td>${item.count}</td>
                    <td class="text-right">₦${formatNumber(item.revenue)}</td>
                </tr>`;
            });

            html += `</tbody></table></div></div></div>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fa fa-star"></i> Top 10 Products</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Product</th><th>Category</th><th>Count</th><th class="text-right">Revenue</th></tr></thead>
                                    <tbody>`;

            response.top_products.forEach(function(item) {
                html += `<tr>
                    <td>${item.name}</td>
                    <td><small class="text-muted">${item.category}</small></td>
                    <td>${item.count}</td>
                    <td class="text-right">₦${formatNumber(item.revenue)}</td>
                </tr>`;
            });

            html += `</tbody></table></div></div></div></div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header"><h6 class="mb-0">Service Categories Breakdown</h6></div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Category</th><th>Count</th><th class="text-right">Total</th></tr></thead>
                                    <tbody>`;

            response.service_categories.forEach(function(cat) {
                html += `<tr><td>${cat.category_name}</td><td>${cat.count}</td><td class="text-right">₦${formatNumber(cat.total)}</td></tr>`;
            });

            html += `</tbody></table></div></div></div>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-header"><h6 class="mb-0">Product Categories Breakdown</h6></div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Category</th><th>Count</th><th class="text-right">Total</th></tr></thead>
                                    <tbody>`;

            response.product_categories.forEach(function(cat) {
                html += `<tr><td>${cat.category_name}</td><td>${cat.count}</td><td class="text-right">₦${formatNumber(cat.total)}</td></tr>`;
            });

            html += `</tbody></table></div></div></div></div>

                <div class="text-right mt-3">
                    <button class="btn btn-success" onclick="printUtilizationReport()">
                        <i class="fa fa-print"></i> Print Utilization Report
                    </button>
                </div>`;

            $('#utilizationContent').html(html);
        });
    }

    window.printUtilizationReport = function() {
        let content = $('#utilizationContent').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Service Utilization Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Service Utilization Report</h4>
                    <p>Period: ${$('#util_date_from').val()} to ${$('#util_date_to').val()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div></div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    }

    // =============================================
    // AUTH CODE TRACKER SECTION
    // =============================================
    function initAuthCodesTable() {
        if (authCodesTable) {
            authCodesTable.destroy();
        }

        authCodesTable = $('#authCodesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.auth-codes') }}",
                data: function(d) {
                    d.hmo_id = $('#auth_filter_hmo').val();
                    d.auth_status = $('#auth_filter_status').val();
                    d.date_from = $('#auth_filter_from').val();
                    d.date_to = $('#auth_filter_to').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false },
                { data: 'patient_name' },
                { data: 'hmo_no' },
                { data: 'hmo_name' },
                { data: 'service_date' },
                { data: 'item_name' },
                { data: 'auth_code_display' },
                { data: 'claim_amount' },
                { data: 'status_badge' }
            ],
            order: [[4, 'desc']],
            drawCallback: function() {
                updateAuthStats();
            }
        });
    }

    function updateAuthStats() {
        // Get stats via info from table or separate AJAX call
        let withCode = 0, withoutCode = 0, withCodeAmount = 0, withoutCodeAmount = 0;
        // This is simplified - in production you might want a separate endpoint
    }

    $('#applyAuthFilters').on('click', function() {
        authCodesTable.ajax.reload();
    });

    $('#printAuthReport').on('click', function() {
        let content = $('#authCodesSection .card-body').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Auth Code Tracker Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Authorization Code Tracker Report</h4>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div></div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });
});
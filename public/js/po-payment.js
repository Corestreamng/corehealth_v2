$(document).ready(function() {
    // Toggle bank/cheque fields based on payment method
    $('#payment_method').on('change', function() {
        const method = $(this).val();

        // Show/hide bank fields
        if (method === 'bank_transfer' || method === 'card') {
            $('.bank-fields').slideDown();
        } else {
            $('.bank-fields').slideUp();
        }

        // Show/hide cheque fields
        if (method === 'cheque') {
            $('.cheque-fields').slideDown();
            $('.bank-fields').slideDown(); // Cheques also need bank
        } else {
            $('.cheque-fields').slideUp();
        }
    }).trigger('change');

    // AJAX form submission
    $('#payment-form').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btn-submit');
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() {
                        window.location.href = window.WORKBENCH_CONFIG?.redirectUrl || wbUrl('/inventory/purchase-orders');
                    }, 1500);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Record Payment');

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    toastr.error(xhr.responseJSON.message);
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    Object.values(xhr.responseJSON.errors).forEach(function(errors) {
                        errors.forEach(function(error) {
                            toastr.error(error);
                        });
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });
});

jQuery(document).ready(function($) {
    $('#email-checker-form').on('submit', function(e) {
        e.preventDefault();
        var email = $(this).find('input[name="email"]').val();
        $('#email-checker-result').html('');
        $('#aev-toast').hide();

        $.ajax({
            type: 'POST',
            url: emailCheckerAjax.ajax_url,
            data: {
                action: 'handle_ajax_requests',
                load_data: 'email_validate',
                email: email
            },
            beforeSend: function() {
                $('#aev-loader').show();
            },
            success: function(response) {
                let data = response.data || {};
                let resultHtml = '';
                if (data.status == 'Passed') {
                    resultHtml = `
                    <div style="color:green;">
                        ✅ ${data.email} is a valid email address<br>
                        <strong>${data.message}</strong>
                    </div>`;
                } else if (data.status == 'valid_major_provider') {
                    resultHtml = `
                    <div style="color:green;">
                        ✅ ${data.email} is a valid email address<br>
                        <strong>${data.message}</strong>
                    </div>`;
                } else if (data.status === 'limit_reached') {
                    resultHtml = `
                        <div style="color:orange;">
                            <strong>${data.email}</strong><br>
                            <small>${data.message}</small>
                        </div>`;
                } else {
                    resultHtml = `<div style="color:red;"><strong>Reason: ${data.message}</strong></div>`;
                }
                $('#email-checker-result').html(resultHtml);
                $('#aev-loader').hide();
            },
            error: function(xhr, status, error) {
                $('#aev-loader').hide();
                $('#email-checker-result').html(`<div style="color:red;">❌ AJAX failed: ${error}</div>`);
            },
        });
    });

    $('#bulkimportvalidationfrm').on('submit', function(e) {
        e.preventDefault();

        const form = $('#bulkimportvalidationfrm')[0];
        const formData = new FormData(form);

        formData.append('action', 'handle_ajax_requests');
        formData.append('load_data', 'bluk_email_validate');
        formData.append('nonce', emailCheckerAjax.nonce);

        $.ajax({
            type: 'POST',
            url: emailCheckerAjax.ajax_url,
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                $('#aev-loader').show();
            },
            success: function(response) {
                $('#aev-loader').hide();
                let resultHtml = '';
                if(response.success) {
                    resultHtml = `<div style="color:green;"><strong>Reason: ${response.data.message}</strong></div>`;
                    $('#email-checker-result').html(resultHtml);
                } else {
                    resultHtml = `<div style="color:red;"><strong>Reason: ${response.data.message}</strong></div>`;
                    $('#email-checker-result').html(resultHtml);
                }
            },
        });
    });

    function showToast(message) {
        $('#aev-toast').html(message).fadeIn();
        setTimeout(() => $('#aev-toast').fadeOut(), 4000);
    }
});

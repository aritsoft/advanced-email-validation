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
                    resultHtml = `<div class="aev-alert aev-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-fill-check" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 63 63 0 0 0-2.887-.87C9.843.266 8.69 0 8 0m2.146 5.146a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793z"/>
                        </svg> <span><strong>${data.email}</strong> is valid.</span>
                    </div>`;
                } else if (data.status === 'limit_reached') {
                    resultHtml = `
                    <div class="aev-alert aev-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-fill-exclamation" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 63 63 0 0 0-2.887-.87C9.843.266 8.69 0 8 0m-.55 8.502L7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0M8.002 12a1 1 0 1 1 0-2 1 1 0 0 1 0 2"/>
                        </svg> <strong>${data.message}</strong>
                    </div>`;
                } else {
                    resultHtml = `<div class="aev-alert aev-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-fill-x" viewBox="0 0 16 16">
                            <path d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 63 63 0 0 0-2.887-.87C9.843.266 8.69 0 8 0M6.854 5.146 8 6.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 7l1.147 1.146a.5.5 0 0 1-.708.708L8 7.707 6.854 8.854a.5.5 0 1 1-.708-.708L7.293 7 6.146 5.854a.5.5 0 1 1 .708-.708"/>
                        </svg> <strong>${data.message}</strong>
                    </div>`;
                }
                var remaining_credits = parseInt(data.credits);
                $("#remaining_credits").html(remaining_credits);
                var aevhistoryrows = $(".aevhistoryrows").html();
                if(data.status != 'limit_reached') {
                    if(data.status == 'Passed') {
                        var newrows = '<tr><td>'+data.email+'</td><td><span class="aev-tag aev-green">'+data.status+'</span><td>'+data.event+'</td><td>'+data.message+'</td></tr>';
                    } else {
                        var newrows = '<tr><td>'+data.email+'</td><td><span class="aev-tag aev-red">'+data.status+'</span><td>'+data.event+'</td><td>'+data.message+'</td></tr>';
                    }
                }
                $('#email-checker-result').html(resultHtml);
                $(".aevhistoryrows").html(newrows+aevhistoryrows);
                $('#aev-loader').hide();
            },
            error: function(xhr, status, error) {
                $('#aev-loader').hide();
                $('#email-checker-result').html(`<div class="aev-alert aev-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shield-fill-x" viewBox="0 0 16 16">
                            <path d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 63 63 0 0 0-2.887-.87C9.843.266 8.69 0 8 0M6.854 5.146 8 6.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 7l1.147 1.146a.5.5 0 0 1-.708.708L8 7.707 6.854 8.854a.5.5 0 1 1-.708-.708L7.293 7 6.146 5.854a.5.5 0 1 1 .708-.708"/>
                        </svg> <span>AJAX failed: <strong>${error}</strong></span>
                    </div>`);
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
                let data = response.data || {};
                $('#aev-loader').hide();
                let resultHtml = '';
                var remaining_credits = parseInt(response.data.credits);
                $("#remaining_credits").html(remaining_credits);

                if(response.success) {
                    resultHtml = `<div style="color:green;"><strong>Reason: ${response.data.message}</strong></div>`;
                    $('#email-checker-result').html(resultHtml);
                    $(".bulk-passed_varification").html(response.data.bulk_passed);
                    $(".bulk-failed-varification").html(response.data.bulk_failed);
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

document.documentElement.classList.add('js-enabled');

window.addEventListener("DOMContentLoaded", function () {
    const nav = document.querySelector(".pms-account-navigation");
    if (nav) {
        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = 'dash-logo';

        const img = document.createElement('img');
        img.src = `https://emailcleaner.plentyflow.com/wp-content/uploads/2025/06/logo.svg`;
        img.alt = 'Plenty Flow';

        wrapperDiv.appendChild(img);
        nav.insertBefore(wrapperDiv, nav.firstChild);

        function insertHeader(selector, text, isId = true) {
            const el = isId ? document.getElementById(selector) : document.querySelector(selector);
            if (el) {
              const div = document.createElement('div');
              div.className = 'dash-header';
              div.innerHTML = `<h1>${text}</h1>`;
              el.parentNode.insertBefore(div, el);
            }
        }
          
        if (document.querySelector('.aev-dashboard')) {
            insertHeader('pms_edit-profile-form', 'Edit Profile', true);
            insertHeader('pms-payment-history', 'Payments', true);
            insertHeader('.pms-account-subscription-details-table', 'Subscriptions', false);
        }

        const wrapper = document.createElement("div");
        wrapper.className = "aev-container";

        let next = nav.nextSibling;
        const elementsToWrap = [];

        while (next) {
            const current = next;
            next = next.nextSibling;

            if (
            current.nodeType === 1 ||
            (current.nodeType === 3 && current.textContent.trim() !== "")
            ) {
            elementsToWrap.push(current);
            }
        }

        elementsToWrap.forEach(el => wrapper.appendChild(el));
        nav.parentNode.insertBefore(wrapper, nav.nextSibling);
    }

    document.body.classList.remove("page-id-365");
});
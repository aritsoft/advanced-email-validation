<?php
function aev_render_form() {
    ob_start(); 
    $remaining = get_remaining_email_credits(); 
    ?>
    <div class="dash-header">
        <h1>Quick Verify</h1>
    </div>
    <div class="aev-form-wrapper">
        <div class="aev-usage">
            <small>You have <strong id="remaining_credits"><?php echo $remaining; ?></strong> email verifications remaining.</small>            
        </div>
        <div class="aev-form-outer">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-check" viewBox="0 0 16 16"><path d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-.966-.741l5.64-3.471L8 9.583l7-4.2V8.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2zm3.708 6.208L1 11.105V5.383zM1 4.217V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v.217l-7 4.2z"></path><path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0m-1.993-1.679a.5.5 0 0 0-.686.172l-1.17 1.95-.547-.547a.5.5 0 0 0-.708.708l.774.773a.75.75 0 0 0 1.174-.144l1.335-2.226a.5.5 0 0 0-.172-.686"></path></svg>
            <form id="email-checker-form" method="post">
                <input type="email" name="email" required placeholder="Enter email to verify">
                <button type="submit">Verify Email</button>
            </form>
            <div id="aev-loader" style="display:none;">
                <div class="loader"></div>
            </div>
        </div>
        <div id="aev-toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:10px 15px;background:#dc3545;color:#fff;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);font-size:14px;"></div>
        <div id="email-checker-result"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('email_validation_form', 'aev_render_form');

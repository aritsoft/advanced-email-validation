<?php
function aev_render_form() {
    $user_id = aev_get_user_identifier();
    $used  = function_exists('get_aev_user_daily_check_usage') ? get_aev_user_daily_check_usage($user_id) : 0;
    $limit = function_exists('get_aev_email_check_limit') ? get_aev_email_check_limit() : 10;
    $remaining = max(0, $limit - $used);

    ob_start(); ?>
    <div class="aev-form-wrapper">
        <div class="aev-usage">
            <small>You have <?php echo $remaining; ?> email verifications remaining.</small>            
        </div>
        <h3>Verify an Email Address</h3>
        <form id="email-checker-form" method="post">
            <input type="email" name="email" required placeholder="Enter email to verify">
            <button type="submit">Verify Email</button>
        </form>
        <div id="aev-loader" style="display:none;text-align:center;margin-top:15px;">
            <span class="spinner is-active" style="width:20px;height:20px;border:3px solid #ccc;border-top:3px solid #0073aa;border-radius:50%;display:inline-block;animation: spin 1s linear infinite;"></span>
        </div>
        <div id="aev-toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:10px 15px;background:#dc3545;color:#fff;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1);font-size:14px;"></div>
        <div id="email-checker-result" style="margin-top:20px;"></div>
        <hr class="aev-divider">
        <div id="user-email-checker-result"></div>
        <div id="email-checker-history" style="margin-top:20px;"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('email_validation_form', 'aev_render_form');

<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="bulk_import_validation_section">
    <div class="dash-header">
        <h1>Bulk Verify</h1>
    </div>
    <div class="bulk-email-import-box">
        <h3><img draggable="false" role="img" class="emoji" alt="📥" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4e5.svg"> Bulk Import Emails</h3>
        <p>Remaining Credits: <strong id="remaining_credits"><?php echo get_remaining_email_credits(); ?></strong></p>
        <form id="bulkimportvalidationfrm" enctype="multipart/form-data" method="post">
            <input type="file" id="bulk_email_file" name="bulk_email_file" accept=".txt" required>

            <button type="submit" class="button button-primary">Import</button>

            <div class="notice">
                Files must be plain text and no larger than <strong>10 MB</strong>.
            </div>
        </form>

        <div id="aev-loader" style="display:none;">Loading...</div>
        <div id="email-checker-result"></div>
    </div>

    <div class="bulk_validation_right">
        <div class="bulk-passed_varification">
            <?php echo bulk_passed_varification(10); ?>
        </div>

        <div class="bulk-failed-varification">
            <?php echo bulk_failed_varification(10); ?>
        </div>
    </div>
</div>
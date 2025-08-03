<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<style>
    .bulk_import_validation_section {
        display: flex;
        flex-wrap: wrap;
        gap: 2rem;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background-color: #fff;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .bulk-email-import-box, .bulk_validation_right {
        flex: 1 1 400px;
        padding: 20px;
        border-radius: 10px;
        background: #f9f9f9;
    }

    h3 {
        margin-top: 0;
        color: #333;
        font-size: 20px;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 5px;
    }

    ol {
        margin-bottom: 15px;
        padding-left: 20px;
    }

    label {
        display: block;
        margin-top: 15px;
        margin-bottom: 5px;
        font-weight: 600;
    }

    input[type="text"],
    input[type="file"] {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
    }

    .button.button-primary {
        margin-top: 15px;
        padding: 10px 20px;
        background-color: #0073aa;
        border: none;
        color: #fff;
        border-radius: 5px;
        cursor: pointer;
    }

    .button.button-primary:hover {
        background-color: #005177;
    }

    .notice {
        margin-top: 15px;
        font-size: 14px;
        color: #666;
        background: #f1f1f1;
        padding: 10px;
        border-left: 4px solid #0073aa;
        border-radius: 5px;
    }

    .bulk_validation_right {
        background: #fdfdfd;
    }

    .bulk_validation_right h3 {
        margin-top: 30px;
    }

    @media screen and (max-width: 768px) {
        .bulk_import_validation_section {
            flex-direction: column;
        }
    }
</style>

<div class="bulk_import_validation_section">
    <div class="bulk-email-import-box">
        <h3><img draggable="false" role="img" class="emoji" alt="📥" src="https://s.w.org/images/core/emoji/16.0.1/svg/1f4e5.svg"> Bulk Import Emails</h3>
        <p>Remaining Credits: <strong><?php echo get_remaining_email_credits(); ?></strong></p>
        <form id="bulkimportvalidationfrm" enctype="multipart/form-data" method="post">
            <label for="bulk_email_file">Choose File</label>
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
            <?php bulk_passed_varification(10); ?>
        </div>

        <div class="bulk-failed-varification">
            <?php bulk_failed_varification(10); ?>
        </div>
    </div>
</div>
<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class PMS_Meta_Box_Subscription_Email_validation {

    public function init() {
        add_action( 'add_meta_boxes', array( $this, 'email_validation_add_subscription_plan_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_subscription_email_validation_limit_field' ) );
    }

    public function email_validation_add_subscription_plan_meta_box() {
        add_meta_box(
            'subscription_plan_email_validation_limit',
            'Email Validation',
            array( $this, 'email_validation_limit_subscription_plan_meta_box_callback' ),
            'pms-subscription', // Post type
            'side',
            'default'
        );
    }

    public function email_validation_limit_subscription_plan_meta_box_callback( $post ) {
        $email_validation_limit = get_post_meta( $post->ID, 'email_validation_limit', true );
        wp_nonce_field( 'save_email_validation_limit_field', 'email_validation_limit_field_nonce' );
        ?>
        <p>
            <label for="email_validation_limit_field"><strong>Email Check Limit:</strong></label><br>
            <input type="text" name="email_validation_limit_field" id="email_validation_limit_field"
                    value="<?php echo esc_attr( $email_validation_limit ); ?>" class="widefat" />
        </p>
        <?php
    }

    public function save_subscription_email_validation_limit_field( $post_id ) {
        if (
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
            ! isset( $_POST['email_validation_limit_field_nonce'] ) ||
            ! wp_verify_nonce( $_POST['email_validation_limit_field_nonce'], 'save_email_validation_limit_field' )
        ) {
            return;
        }

        if ( isset( $_POST['email_validation_limit_field'] ) ) {
            update_post_meta( $post_id, 'email_validation_limit', sanitize_text_field( $_POST['email_validation_limit_field'] ) );
        }
    }
}

function pms_init_subscription_plan_email_validation_meta_box() {
    $instance = new PMS_Meta_Box_Subscription_Email_validation();
    $instance->init();
}

add_action( 'init', 'pms_init_subscription_plan_email_validation_meta_box', 2 );

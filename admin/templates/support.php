<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sas_frontend  = untrailingslashit( SAS_FRONTEND_URL );
$current_user  = wp_get_current_user();
?>
<div class="sas-wrap" data-page="support">
    <div class="sas-page-header">
        <h1><?php esc_html_e( 'Contact Support', 'social-auto-scheduler' ); ?></h1>
    </div>

    <div class="sas-notice sas-notice--info">
        <?php esc_html_e( 'Send us a message and our team will get back to you at the email address below. Your request is tied to this website so we can look up your account and license automatically.', 'social-auto-scheduler' ); ?>
    </div>

    <div class="sas-card" style="max-width:640px;">
        <div class="sas-card__header">
            <h2><?php esc_html_e( 'Send a Message', 'social-auto-scheduler' ); ?></h2>
        </div>
        <div class="sas-card__body">
            <form id="sas-support-form">
                <div class="sas-settings-grid">
                    <div class="sas-field">
                        <label for="sas-support-name"><?php esc_html_e( 'Name', 'social-auto-scheduler' ); ?></label>
                        <input type="text" id="sas-support-name" name="name" class="sas-input" required
                               value="<?php echo esc_attr( $current_user->display_name ); ?>" />
                    </div>
                    <div class="sas-field">
                        <label for="sas-support-email"><?php esc_html_e( 'Email', 'social-auto-scheduler' ); ?></label>
                        <input type="email" id="sas-support-email" name="email" class="sas-input" required
                               value="<?php echo esc_attr( $current_user->user_email ); ?>" />
                    </div>
                </div>

                <div class="sas-field">
                    <label for="sas-support-topic"><?php esc_html_e( 'Topic', 'social-auto-scheduler' ); ?></label>
                    <select id="sas-support-topic" name="topic" class="sas-select">
                        <option value="Technical support"><?php esc_html_e( 'Technical support', 'social-auto-scheduler' ); ?></option>
                        <option value="Billing"><?php esc_html_e( 'Billing', 'social-auto-scheduler' ); ?></option>
                        <option value="Bug report"><?php esc_html_e( 'Bug report', 'social-auto-scheduler' ); ?></option>
                        <option value="Feature request"><?php esc_html_e( 'Feature request', 'social-auto-scheduler' ); ?></option>
                        <option value="Other"><?php esc_html_e( 'Other', 'social-auto-scheduler' ); ?></option>
                    </select>
                </div>

                <div class="sas-field">
                    <label for="sas-support-message"><?php esc_html_e( 'Message', 'social-auto-scheduler' ); ?></label>
                    <textarea id="sas-support-message" name="message" class="sas-textarea" rows="5" required
                        placeholder="<?php esc_attr_e( 'Tell us how we can help…', 'social-auto-scheduler' ); ?>"></textarea>
                </div>

                <div class="sas-form-actions">
                    <button type="submit" class="sas-btn sas-btn--primary sas-btn--lg" id="sas-send-support-btn">
                        <?php esc_html_e( 'Send Message', 'social-auto-scheduler' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <p class="sas-field__help" style="margin-top:16px;">
        <?php
        printf(
            /* translators: %s: link to the frontend contact page */
            esc_html__( 'Prefer email? Reach us directly at support@soulitam.com or visit %s.', 'social-auto-scheduler' ),
            '<a href="' . esc_url( $sas_frontend . '/contact' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'our Contact page', 'social-auto-scheduler' ) . '</a>'
        );
        ?>
    </p>
</div>

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_active = SAS_License_Manager::is_active();
$status    = SAS_License_Manager::get_status();
$plan      = SAS_License_Manager::get_plan();
$token     = SAS_License_Manager::get_license_token();

$sas_frontend = untrailingslashit( SAS_FRONTEND_URL );

$error   = get_transient( 'sas_license_error' );   delete_transient( 'sas_license_error' );
$success = get_transient( 'sas_license_success' ); delete_transient( 'sas_license_success' );

// Mask the stored token — show only the last 4 characters.
$masked_token = $token ? str_repeat( '•', max( 0, strlen( $token ) - 4 ) ) . substr( $token, -4 ) : '';
?>
<div class="sas-wrap" data-page="license">
    <div class="sas-page-header">
        <h1><?php esc_html_e( 'License', 'social-auto-scheduler' ); ?></h1>
    </div>

    <?php if ( $error ) : ?>
        <div class="sas-notice sas-notice--error"><?php echo esc_html( $error ); ?></div>
    <?php endif; ?>
    <?php if ( $success ) : ?>
        <div class="sas-notice sas-notice--success"><?php echo esc_html( $success ); ?></div>
    <?php endif; ?>

    <!-- Status card -->
    <div class="sas-card" style="max-width:640px;">
        <div class="sas-card__header">
            <h2><?php esc_html_e( 'License Status', 'social-auto-scheduler' ); ?></h2>
        </div>
        <div class="sas-card__body">
            <div class="sas-settings-grid">
                <div class="sas-field sas-mt-0">
                    <label><?php esc_html_e( 'Status', 'social-auto-scheduler' ); ?></label>
                    <?php if ( $is_active ) : ?>
                        <span class="sas-badge sas-badge--published"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active', 'social-auto-scheduler' ); ?></span>
                    <?php else : ?>
                        <span class="sas-badge sas-badge--failed"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( ucfirst( $status ) ); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ( $is_active && $plan ) : ?>
                <div class="sas-field sas-mt-0">
                    <label><?php esc_html_e( 'Plan', 'social-auto-scheduler' ); ?></label>
                    <span><?php echo esc_html( strtoupper( $plan ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php if ( $is_active && $masked_token ) : ?>
                <div class="sas-field sas-mt-0">
                    <label><?php esc_html_e( 'License Key', 'social-auto-scheduler' ); ?></label>
                    <code><?php echo esc_html( $masked_token ); ?></code>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ( ! $is_active ) : ?>
    <!-- Activation form -->
    <div class="sas-card" style="max-width:640px;">
        <div class="sas-card__header">
            <h2><?php esc_html_e( 'Activate License', 'social-auto-scheduler' ); ?></h2>
        </div>
        <div class="sas-card__body">
            <p class="sas-field__help" style="margin:0 0 16px;">
                <?php
                printf(
                    /* translators: %s: link to the licenses page on the frontend website */
                    esc_html__( 'Enter your license key. You can generate a free key from your %s.', 'social-auto-scheduler' ),
                    '<a href="' . esc_url( $sas_frontend . '/licenses' ) . '" target="_blank" rel="noopener noreferrer" class="sas-link">' . esc_html__( 'Soulitam dashboard', 'social-auto-scheduler' ) . '</a>'
                );
                ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sas_activate_license' ); ?>
                <input type="hidden" name="action" value="sas_activate_license">
                <div class="sas-field">
                    <label for="sas_license_token"><?php esc_html_e( 'License Key', 'social-auto-scheduler' ); ?></label>
                    <input type="text" id="sas_license_token" name="sas_license_token" class="sas-input"
                           value="" placeholder="XXXX-XXXX-XXXX-XXXX" required autocomplete="off">
                </div>
                <div class="sas-form-actions">
                    <button type="submit" class="sas-btn sas-btn--primary sas-btn--lg">
                        <?php esc_html_e( 'Activate License', 'social-auto-scheduler' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else : ?>
    <!-- Deactivation form -->
    <div class="sas-card" style="max-width:640px;">
        <div class="sas-card__header">
            <h2><?php esc_html_e( 'Deactivate License', 'social-auto-scheduler' ); ?></h2>
        </div>
        <div class="sas-card__body">
            <p class="sas-field__help" style="margin:0 0 16px;"><?php esc_html_e( 'This will disconnect the plugin from your account. Video publishing will stop.', 'social-auto-scheduler' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sas_deactivate_license' ); ?>
                <input type="hidden" name="action" value="sas_deactivate_license">
                <button type="submit" class="sas-btn sas-btn--danger">
                    <?php esc_html_e( 'Deactivate License', 'social-auto-scheduler' ); ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

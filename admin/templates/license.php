<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_active    = SAS_License_Manager::is_active();
$status       = SAS_License_Manager::get_status();
$plan         = SAS_License_Manager::get_plan();
$token        = SAS_License_Manager::get_license_token();
$backend_url  = SAS_Backend_Client::get_backend_url();
$website_id   = SAS_Backend_Client::get_website_id();

$error   = get_transient( 'sas_license_error' );   delete_transient( 'sas_license_error' );
$success = get_transient( 'sas_license_success' ); delete_transient( 'sas_license_success' );
?>
<div class="wrap sas-wrap">
    <h1><?php esc_html_e( 'License Activation', 'social-auto-scheduler' ); ?></h1>

    <?php if ( $error ) : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>
    <?php if ( $success ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success ); ?></p></div>
    <?php endif; ?>

    <!-- Status card -->
    <div class="sas-card" style="max-width:640px;margin-top:16px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:6px;">
        <h2 style="margin-top:0"><?php esc_html_e( 'Connection Status', 'social-auto-scheduler' ); ?></h2>
        <table class="form-table" style="margin:0">
            <tr>
                <th><?php esc_html_e( 'Status', 'social-auto-scheduler' ); ?></th>
                <td>
                    <?php if ( $is_active ) : ?>
                        <span style="color:#2ea44f;font-weight:600">&#10003; <?php esc_html_e( 'Active', 'social-auto-scheduler' ); ?></span>
                    <?php else : ?>
                        <span style="color:#d73a49;font-weight:600">&#10007; <?php echo esc_html( ucfirst( $status ) ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ( $plan ) : ?>
            <tr>
                <th><?php esc_html_e( 'Plan', 'social-auto-scheduler' ); ?></th>
                <td><?php echo esc_html( strtoupper( $plan ) ); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ( $website_id ) : ?>
            <tr>
                <th><?php esc_html_e( 'Website ID', 'social-auto-scheduler' ); ?></th>
                <td><?php echo esc_html( $website_id ); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ( $backend_url ) : ?>
            <tr>
                <th><?php esc_html_e( 'Backend URL', 'social-auto-scheduler' ); ?></th>
                <td><?php echo esc_html( $backend_url ); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if ( ! $is_active ) : ?>
    <!-- Activation form -->
    <div class="sas-card" style="max-width:640px;margin-top:24px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:6px;">
        <h2 style="margin-top:0"><?php esc_html_e( 'Activate License', 'social-auto-scheduler' ); ?></h2>
        <p><?php esc_html_e( 'Enter the license token from your Soulitam account dashboard.', 'social-auto-scheduler' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sas_activate_license' ); ?>
            <input type="hidden" name="action" value="sas_activate_license">
            <table class="form-table">
                <tr>
                    <th><label for="sas_backend_url"><?php esc_html_e( 'Backend URL', 'social-auto-scheduler' ); ?></label></th>
                    <td>
                        <input type="url" id="sas_backend_url" name="sas_backend_url"
                               value="<?php echo esc_attr( $backend_url ?: 'https://api.soulitam.com' ); ?>"
                               class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'URL of the ss_backend API server.', 'social-auto-scheduler' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="sas_license_token"><?php esc_html_e( 'License Token', 'social-auto-scheduler' ); ?></label></th>
                    <td>
                        <input type="text" id="sas_license_token" name="sas_license_token"
                               value="" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" required>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Activate License', 'social-auto-scheduler' ), 'primary' ); ?>
        </form>
    </div>
    <?php else : ?>
    <!-- Deactivation form -->
    <div class="sas-card" style="max-width:640px;margin-top:24px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:6px;">
        <h2 style="margin-top:0"><?php esc_html_e( 'Deactivate License', 'social-auto-scheduler' ); ?></h2>
        <p><?php esc_html_e( 'This will disconnect the plugin from the backend. Video publishing will stop.', 'social-auto-scheduler' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'sas_deactivate_license' ); ?>
            <input type="hidden" name="action" value="sas_deactivate_license">
            <?php submit_button( __( 'Deactivate License', 'social-auto-scheduler' ), 'delete' ); ?>
        </form>
    </div>
    <?php endif; ?>
</div>

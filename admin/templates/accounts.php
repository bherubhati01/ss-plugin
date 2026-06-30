<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oauth_success = get_transient( 'sas_oauth_success' );
$oauth_error   = get_transient( 'sas_oauth_error' );
if ( $oauth_success ) {
	delete_transient( 'sas_oauth_success' );
}
if ( $oauth_error ) {
	delete_transient( 'sas_oauth_error' );
}

// Diagnostic: show & clear the OAuth debug log written by handle_oauth_callback()
$sas_debug = get_option( 'sas_oauth_debug' );
if ( $sas_debug ) {
	delete_option( 'sas_oauth_debug' );
}
?>
<div class="sas-wrap" data-page="accounts">
	<div class="sas-page-header">
		<h1><?php esc_html_e( 'Connected Accounts', 'social-auto-scheduler' ); ?></h1>
	</div>

	<?php if ( $sas_debug ) : ?>
	<div style="background:#1e1e1e;color:#d4d4d4;padding:14px 16px;border-radius:4px;font-family:monospace;font-size:12px;margin-bottom:16px;white-space:pre-wrap;word-break:break-all;">
<strong style="color:#4ec9b0;">OAuth Debug (v1.0.2):</strong>
<?php echo esc_html( json_encode( $sas_debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?>
	</div>
	<?php endif; ?>

	<?php if ( $oauth_success ) : ?>
	<div class="sas-notice sas-notice--success">
		<?php printf( esc_html__( '%s account connected successfully!', 'social-auto-scheduler' ), esc_html( ucfirst( $oauth_success ) ) ); ?>
	</div>
	<?php endif; ?>

	<?php if ( $oauth_error ) : ?>
	<div class="sas-notice sas-notice--error">
		<?php echo esc_html( $oauth_error ); ?>
	</div>
	<?php endif; ?>

	<div class="sas-notice sas-notice--info">
		<strong><?php esc_html_e( 'Setup required:', 'social-auto-scheduler' ); ?></strong>
		<?php esc_html_e( 'Enter your API credentials in Settings before connecting accounts.', 'social-auto-scheduler' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=sas-settings' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'social-auto-scheduler' ); ?></a>
	</div>

	<div class="sas-accounts-grid">
		<!-- YouTube -->
		<div class="sas-account-card" id="sas-youtube-card">
			<div class="sas-account-card__logo sas-account-card__logo--youtube">
				<svg viewBox="0 0 24 24" width="48" height="48"><path fill="#FF0000" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
			</div>
			<h3>YouTube</h3>
			<p class="sas-account-card__desc"><?php esc_html_e( 'Upload videos to YouTube channels using the YouTube Data API v3.', 'social-auto-scheduler' ); ?></p>
			<div id="sas-youtube-status" class="sas-account-card__status">
				<div class="sas-loading-skeleton"></div>
			</div>
			<div class="sas-account-card__actions">
				<button id="sas-connect-youtube" class="sas-btn sas-btn--primary">
					<?php esc_html_e( 'Connect YouTube', 'social-auto-scheduler' ); ?>
				</button>
			</div>
		</div>

		<!-- Instagram -->
		<div class="sas-account-card" id="sas-instagram-card">
			<div class="sas-account-card__logo sas-account-card__logo--instagram">
				<svg viewBox="0 0 24 24" width="48" height="48"><defs><linearGradient id="ig-grad" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#f09433"/><stop offset="25%" style="stop-color:#e6683c"/><stop offset="50%" style="stop-color:#dc2743"/><stop offset="75%" style="stop-color:#cc2366"/><stop offset="100%" style="stop-color:#bc1888"/></linearGradient></defs><path fill="url(#ig-grad)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
			</div>
			<h3>Instagram</h3>
			<p class="sas-account-card__desc"><?php esc_html_e( 'Publish Reels to Instagram Business accounts using the Meta Graph API.', 'social-auto-scheduler' ); ?></p>
			<div id="sas-instagram-status" class="sas-account-card__status">
				<div class="sas-loading-skeleton"></div>
			</div>
			<div class="sas-account-card__actions">
				<button id="sas-connect-instagram" class="sas-btn sas-btn--primary">
					<?php esc_html_e( 'Connect Instagram', 'social-auto-scheduler' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Connected accounts list -->
	<div class="sas-card" id="sas-accounts-list-card" style="display:none;">
		<div class="sas-card__header">
			<h2><?php esc_html_e( 'Connected Accounts', 'social-auto-scheduler' ); ?></h2>
		</div>
		<div class="sas-card__body">
			<div id="sas-connected-accounts"></div>
		</div>
	</div>

	<!-- API Credentials Info -->
	<div class="sas-card">
		<div class="sas-card__header">
			<h2><?php esc_html_e( 'How to get API Credentials', 'social-auto-scheduler' ); ?></h2>
		</div>
		<div class="sas-card__body sas-credentials-guide">
			<div class="sas-guide-section">
				<h3>YouTube (Google)</h3>
				<ol>
					<li><?php esc_html_e( 'Go to Google Cloud Console → Create a project', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Enable the YouTube Data API v3', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Create OAuth 2.0 credentials (Web Application type)', 'social-auto-scheduler' ); ?></li>
					<li><?php printf( esc_html__( 'Add redirect URI: %s', 'social-auto-scheduler' ), '<code>' . esc_html( admin_url( 'admin.php?page=sas-accounts&sas_oauth=youtube' ) ) . '</code>' ); ?></li>
					<li><?php esc_html_e( 'Copy Client ID and Client Secret to Settings', 'social-auto-scheduler' ); ?></li>
				</ol>
			</div>

			<div class="sas-guide-section">
				<h3>Instagram (Meta — Instagram Login)</h3>

				<div class="sas-notice sas-notice--info" style="margin-bottom:16px;">
					<strong><?php esc_html_e( 'Redirect URI — paste this exactly into Meta Business Login Configuration:', 'social-auto-scheduler' ); ?></strong><br />
					<code style="display:block;margin-top:6px;word-break:break-all;user-select:all;cursor:text;"><?php echo esc_html( admin_url( 'admin.php?page=sas-accounts&sas_oauth=instagram' ) ); ?></code>
					<small style="display:block;margin-top:4px;color:#444;"><?php esc_html_e( 'Copy this full URL including the ?page= and &sas_oauth= parts — Meta supports query parameters in redirect URIs.', 'social-auto-scheduler' ); ?></small>
				</div>

				<ol>
					<li><?php esc_html_e( 'Go to developers.facebook.com → My Apps → Create App.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Select use case: "Manage messaging and content on Instagram" → Next.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Choose "Instagram Login Setup" (NOT Facebook Login Setup) → Create App.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Left sidebar → Instagram → "API setup with Instagram login".', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Copy the Instagram App ID and App Secret shown on that page into plugin Settings.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Scroll to "Business login settings" → Set up → paste the redirect URI above → Save.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Add permission instagram_business_content_publish if not already listed.', 'social-auto-scheduler' ); ?></li>
					<li><?php esc_html_e( 'Development mode: App Roles → Roles → Instagram Testers → add your account → accept the invite in the Instagram app.', 'social-auto-scheduler' ); ?></li>
				</ol>
			</div>
		</div>
	</div>
</div>

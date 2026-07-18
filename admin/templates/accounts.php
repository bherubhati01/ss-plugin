<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sas_frontend = untrailingslashit( SAS_FRONTEND_URL );
?>
<div class="sas-wrap" data-page="accounts">
	<div class="sas-page-header">
		<h1><?php esc_html_e( 'Connected Accounts', 'social-auto-scheduler' ); ?></h1>
		<a href="<?php echo esc_url( $sas_frontend . '/social-accounts' ); ?>" target="_blank" rel="noopener noreferrer"
		   class="sas-btn sas-btn--primary">
			<?php esc_html_e( 'Manage Accounts in Dashboard', 'social-auto-scheduler' ); ?> ↗
		</a>
	</div>

	<div class="sas-notice sas-notice--info">
		<strong><?php esc_html_e( 'Connecting accounts happens in your Soulitam Social dashboard.', 'social-auto-scheduler' ); ?></strong>
		<?php esc_html_e( 'Log in to your dashboard, open Social Accounts, and connect YouTube or Instagram to this website. Connected accounts appear here automatically.', 'social-auto-scheduler' ); ?>
	</div>

	<div class="sas-accounts-grid">
		<!-- YouTube -->
		<div class="sas-account-card" id="sas-youtube-card">
			<div class="sas-account-card__logo sas-account-card__logo--youtube">
				<img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/youtube.svg' ); ?>" width="48" height="48" alt="YouTube" style="object-fit:contain;">
			</div>
			<h3>YouTube</h3>
			<p class="sas-account-card__desc"><?php esc_html_e( 'Automatically upload scheduled videos to your YouTube channel.', 'social-auto-scheduler' ); ?></p>
			<div id="sas-youtube-status" class="sas-account-card__status">
				<div class="sas-loading-skeleton"></div>
			</div>
		</div>

		<!-- Instagram -->
		<div class="sas-account-card" id="sas-instagram-card">
			<div class="sas-account-card__logo sas-account-card__logo--instagram">
				<img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/instagram.svg' ); ?>" width="48" height="48" alt="Instagram" style="object-fit:contain;">
			</div>
			<h3>Instagram</h3>
			<p class="sas-account-card__desc"><?php esc_html_e( 'Automatically publish scheduled Reels to your Instagram Business account.', 'social-auto-scheduler' ); ?></p>
			<div id="sas-instagram-status" class="sas-account-card__status">
				<div class="sas-loading-skeleton"></div>
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

	<!-- How it works -->
	<div class="sas-card">
		<div class="sas-card__header">
			<h2><?php esc_html_e( 'How to connect an account', 'social-auto-scheduler' ); ?></h2>
		</div>
		<div class="sas-card__body sas-credentials-guide">
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the frontend dashboard */
						esc_html__( 'Open your %s and log in.', 'social-auto-scheduler' ),
						'<a href="' . esc_url( $sas_frontend . '/social-accounts' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Soulitam Social dashboard', 'social-auto-scheduler' ) . '</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Go to Social Accounts and select this website.', 'social-auto-scheduler' ); ?></li>
				<li><?php esc_html_e( 'Click Connect on YouTube or Instagram and approve access.', 'social-auto-scheduler' ); ?></li>
				<li><?php esc_html_e( 'Done — the account appears here and scheduled videos publish automatically.', 'social-auto-scheduler' ); ?></li>
			</ol>
		</div>
	</div>

	<!-- Legal & data -->
	<div class="sas-card">
		<div class="sas-card__header">
			<h2><?php esc_html_e( 'Legal & Data', 'social-auto-scheduler' ); ?></h2>
		</div>
		<div class="sas-card__body">
			<p class="sas-field__help">
				<?php esc_html_e( 'Connecting a YouTube or Instagram account shares limited profile and publishing data with Soulitam Social. See how it&#8217;s used, or remove it at any time.', 'social-auto-scheduler' ); ?>
			</p>
			<p style="margin-top:8px;">
				<a href="<?php echo esc_url( $sas_frontend . '/privacy' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'social-auto-scheduler' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $sas_frontend . '/terms' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms of Service', 'social-auto-scheduler' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $sas_frontend . '/data-deletion' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Data Deletion Instructions', 'social-auto-scheduler' ); ?></a>
			</p>
		</div>
	</div>
</div>

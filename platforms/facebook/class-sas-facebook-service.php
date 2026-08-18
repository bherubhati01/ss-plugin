<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facebook Page integration — thin wrapper, same shape as
 * SAS_Instagram_Service / SAS_Youtube_Service.
 *
 * All actual OAuth code exchange AND publishing (video posts + Stories)
 * happens in the Django backend (apps/social_accounts + apps/scheduler on
 * ss_backend) — this class only asks the backend for the Facebook
 * authorization URL to redirect the admin to. The backend redirects back
 * here with ?sas_connected=facebook on success; handle_callback() below is
 * never actually invoked (the backend handles the code exchange itself),
 * kept only for interface parity with the other platform service classes.
 */
class SAS_Facebook_Service {

	private SAS_Token_Service $token_service;
	private SAS_Log_Service   $log_service;

	public function __construct() {
		$this->token_service = new SAS_Token_Service();
		$this->log_service   = new SAS_Log_Service();
	}

	// ── OAuth: Step 1 — get the authorize URL from backend ───────────────────

	public function get_auth_url(): string {
		$redirect_back = admin_url( 'admin.php?page=sas-accounts' );
		$result = SAS_Backend_Client::get(
			'/api/v1/social-accounts/plugin/oauth/facebook/',
			[ 'redirect_back' => $redirect_back ]
		);
		if ( is_wp_error( $result ) || empty( $result['auth_url'] ) ) {
			throw new RuntimeException(
				__( 'Could not retrieve Facebook authorization URL from backend. Check that Facebook is configured and enabled in the backend admin.', 'social-auto-scheduler' )
			);
		}
		return $result['auth_url'];
	}

	// ── OAuth: callback is handled entirely by the backend ────────────────────

	public function handle_callback( string $code, string $state ): bool {
		$this->log_service->warning( 'facebook_callback_called', 'handle_callback() called but backend handles the OAuth exchange now.' );
		return false;
	}
}

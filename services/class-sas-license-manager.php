<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages license activation, storage, and periodic verification.
 *
 * License flow:
 *   1. Admin enters license token in plugin settings.
 *   2. Plugin calls POST /api/v1/license/verify/ → gets JWT + plan info.
 *   3. Plugin calls POST /api/v1/websites/register/ → gets website-scoped JWT.
 *   4. All tokens stored in WordPress options (never hardcoded).
 *   5. Plugin verifies every 24 h in the background.
 */
class SAS_License_Manager {

	private const OPTION_LICENSE_TOKEN  = 'sas_license_token';
	private const OPTION_LICENSE_STATUS = 'sas_license_status';
	private const OPTION_LICENSE_PLAN   = 'sas_license_plan';
	private const OPTION_LAST_VERIFIED  = 'sas_license_last_verified';
	private const OPTION_PERMISSIONS    = 'sas_license_permissions';

	// ── Getters ───────────────────────────────────────────────────────────────

	public static function get_license_token(): string {
		return (string) get_option( self::OPTION_LICENSE_TOKEN, '' );
	}

	public static function get_status(): string {
		return (string) get_option( self::OPTION_LICENSE_STATUS, 'inactive' );
	}

	public static function get_plan(): string {
		return (string) get_option( self::OPTION_LICENSE_PLAN, '' );
	}

	public static function get_permissions(): array {
		$perms = get_option( self::OPTION_PERMISSIONS, [] );
		return is_array( $perms ) ? $perms : [];
	}

	public static function is_active(): bool {
		return self::get_status() === 'active' && SAS_Backend_Client::is_connected();
	}

	// ── Activation flow ───────────────────────────────────────────────────────

	/**
	 * Activate license and register the website with the backend.
	 * Called when admin submits the license token in Settings.
	 *
	 * @param string $license_token The license key entered by the admin.
	 * @return true|WP_Error
	 */
	public static function activate( string $license_token ) {
		$license_token = sanitize_text_field( $license_token );

		if ( empty( $license_token ) ) {
			return new WP_Error( 'invalid_token', __( 'License token is required.', 'social-auto-scheduler' ) );
		}

		// Step 1: Verify license with backend
		$verify_result = SAS_Backend_Client::post_public( '/api/v1/license/verify/', [
			'token'           => $license_token,
			'website_url'     => home_url(),
			'plugin_version'  => SAS_VERSION,
		] );

		if ( is_wp_error( $verify_result ) ) {
			return $verify_result;
		}

		// Step 2: Register website to get website-scoped JWT
		$register_result = SAS_Backend_Client::post_public( '/api/v1/websites/register/', [
			'license_token'    => $license_token,
			'name'             => get_bloginfo( 'name' ),
			'url'              => home_url(),
			'plugin_version'   => SAS_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
		] );

		if ( is_wp_error( $register_result ) ) {
			return $register_result;
		}

		// Step 3: Store everything
		update_option( self::OPTION_LICENSE_TOKEN, $license_token );
		update_option( self::OPTION_LICENSE_STATUS, 'active' );
		update_option( self::OPTION_LICENSE_PLAN, $verify_result['plan'] ?? '' );
		update_option( self::OPTION_PERMISSIONS, $verify_result['permissions'] ?? [] );
		update_option( self::OPTION_LAST_VERIFIED, time() );

		// Store website-scoped JWT (used for all plugin API calls)
		SAS_Backend_Client::store_tokens( $register_result );

		return true;
	}

	/**
	 * Deactivate the license and clear all stored tokens.
	 */
	public static function deactivate(): void {
		update_option( self::OPTION_LICENSE_STATUS, 'inactive' );
		SAS_Backend_Client::clear_tokens();
	}

	/**
	 * Periodic re-verification (called by cron every 24 h).
	 * Silently updates status; does not throw on failure so the site keeps working.
	 */
	public static function verify_periodically(): void {
		$last = (int) get_option( self::OPTION_LAST_VERIFIED, 0 );
		if ( ( time() - $last ) < DAY_IN_SECONDS ) {
			return;
		}

		$token = self::get_license_token();
		if ( ! $token ) {
			return;
		}

		$result = SAS_Backend_Client::post_public( '/api/v1/license/verify/', [
			'token'       => $token,
			'website_url' => home_url(),
		] );

		if ( is_wp_error( $result ) ) {
			update_option( self::OPTION_LICENSE_STATUS, 'error' );
			return;
		}

		update_option( self::OPTION_LICENSE_STATUS, 'active' );
		update_option( self::OPTION_LICENSE_PLAN, $result['plan'] ?? '' );
		update_option( self::OPTION_PERMISSIONS, $result['permissions'] ?? [] );
		update_option( self::OPTION_LAST_VERIFIED, time() );
	}
}

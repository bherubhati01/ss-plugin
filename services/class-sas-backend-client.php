<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin HTTP client for the ss_backend REST API.
 *
 * All communication with the backend goes through this class.
 * Credentials (JWT) are read from WordPress options — never hardcoded.
 */
class SAS_Backend_Client {

	private const OPTION_JWT_ACCESS  = 'sas_backend_access_token';
	private const OPTION_JWT_REFRESH = 'sas_backend_refresh_token';
	private const OPTION_WEBSITE_ID  = 'sas_backend_website_id';
	private const OPTION_BACKEND_URL = 'sas_backend_url';

	// ── Configuration ─────────────────────────────────────────────────────────

	public static function get_backend_url(): string {
		$url = get_option( self::OPTION_BACKEND_URL, '' );
		if ( ! $url && defined( 'SAS_BACKEND_URL' ) ) {
			$url = SAS_BACKEND_URL;
		}
		return untrailingslashit( (string) $url );
	}

	public static function set_backend_url( string $url ): void {
		update_option( self::OPTION_BACKEND_URL, untrailingslashit( $url ) );
	}

	// ── Token storage ─────────────────────────────────────────────────────────

	public static function get_access_token(): string {
		return (string) get_option( self::OPTION_JWT_ACCESS, '' );
	}

	public static function get_refresh_token(): string {
		return (string) get_option( self::OPTION_JWT_REFRESH, '' );
	}

	public static function get_website_id(): int {
		return (int) get_option( self::OPTION_WEBSITE_ID, 0 );
	}

	public static function store_tokens( array $tokens ): void {
		if ( ! empty( $tokens['access'] ) ) {
			update_option( self::OPTION_JWT_ACCESS, $tokens['access'] );
		}
		if ( ! empty( $tokens['refresh'] ) ) {
			update_option( self::OPTION_JWT_REFRESH, $tokens['refresh'] );
		}
		if ( ! empty( $tokens['website_id'] ) ) {
			update_option( self::OPTION_WEBSITE_ID, (int) $tokens['website_id'] );
		}
	}

	public static function clear_tokens(): void {
		delete_option( self::OPTION_JWT_ACCESS );
		delete_option( self::OPTION_JWT_REFRESH );
		delete_option( self::OPTION_WEBSITE_ID );
	}

	public static function is_connected(): bool {
		return self::get_backend_url() !== '' && self::get_access_token() !== '';
	}

	// ── HTTP requests ─────────────────────────────────────────────────────────

	/**
	 * Make an authenticated GET request to the backend.
	 *
	 * @param string $path   API path (e.g. '/api/v1/videos/').
	 * @param array  $params Query params.
	 * @return array|WP_Error Decoded JSON body or WP_Error.
	 */
	public static function get( string $path, array $params = [] ) {
		$url = self::get_backend_url() . $path;
		if ( $params ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get( $url, [
			'timeout' => 30,
			'headers' => self::auth_headers(),
		] );

		return self::parse_response( $response, 'GET', $path );
	}

	/**
	 * Make an authenticated POST request to the backend.
	 *
	 * @param string $path API path.
	 * @param array  $body Request body (will be JSON-encoded).
	 * @return array|WP_Error Decoded JSON body or WP_Error.
	 */
	public static function post( string $path, array $body = [] ) {
		$url      = self::get_backend_url() . $path;
		$response = wp_remote_post( $url, [
			'timeout' => 30,
			'headers' => array_merge( self::auth_headers(), [ 'Content-Type' => 'application/json' ] ),
			'body'    => wp_json_encode( $body ),
		] );

		return self::parse_response( $response, 'POST', $path );
	}

	/**
	 * POST without authentication (used for license verify / website register).
	 */
	public static function post_public( string $path, array $body = [] ) {
		$url      = self::get_backend_url() . $path;
		$response = wp_remote_post( $url, [
			'timeout' => 30,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body ),
		] );

		return self::parse_response( $response, 'POST', $path );
	}

	/**
	 * DELETE request.
	 */
	public static function delete( string $path ) {
		$url      = self::get_backend_url() . $path;
		$response = wp_remote_request( $url, [
			'method'  => 'DELETE',
			'timeout' => 30,
			'headers' => self::auth_headers(),
		] );

		return self::parse_response( $response, 'DELETE', $path );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private static function auth_headers(): array {
		$token = self::get_access_token();
		return $token ? [ 'Authorization' => 'Bearer ' . $token ] : [];
	}

	/**
	 * Parse response body; return decoded array or WP_Error.
	 */
	private static function parse_response( $response, string $method, string $path ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = $body['error']['message'] ?? "HTTP {$code} error from backend {$method} {$path}";
			return new WP_Error( 'backend_error', $message, [ 'status' => $code, 'body' => $body ] );
		}

		return $body ?: [];
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instagram Business Login integration for Reels publishing.
 *
 * Uses the Instagram Business Login product (NOT Facebook Login for Business).
 * This is the correct flow for all Meta apps after January 27, 2025.
 *
 * OAuth flow:
 *   1. Redirect user → https://www.instagram.com/oauth/authorize  (instagram.com, NOT facebook.com)
 *   2. POST https://api.instagram.com/oauth/access_token          → short-lived token (~1 hour)
 *   3. GET  https://graph.instagram.com/access_token              → long-lived token (60 days)
 *   4. GET  https://graph.instagram.com/v21.0/me                  → ig_user_id + username
 *
 * Publishing flow:
 *   1. POST /{ig_user_id}/media         → create Reels container
 *   2. GET  /{container_id}             → poll status_code until FINISHED
 *   3. POST /{ig_user_id}/media_publish → publish
 *
 * Required Meta App Dashboard configuration:
 *   - Product: Instagram (under "Add a product")
 *   - Instagram → API setup with Instagram login → Business Login → enable it
 *   - Add permissions: instagram_business_basic, instagram_business_content_publish
 *   - Register redirect URI in Business Login settings (NOT in Facebook Login settings)
 *   - Redirect URI: {site}/wp-admin/admin.php?page=sas-accounts&sas_oauth=instagram
 */
class SAS_Instagram_Service {

	// ── Endpoints (Instagram Business Login, 2025+) ───────────────────────────

	// Step 1: OAuth dialog — on instagram.com, NOT facebook.com/dialog/oauth
	private const AUTHORIZE_URL = 'https://www.instagram.com/oauth/authorize';

	// Step 2: Short-lived token exchange — POST, form-encoded, api.instagram.com
	private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';

	// Step 3: Long-lived token — GET, graph.instagram.com (NOT graph.facebook.com)
	private const LONG_TOKEN_URL = 'https://graph.instagram.com/access_token';

	// Token refresh — GET, same host, different path
	private const REFRESH_TOKEN_URL = 'https://graph.instagram.com/refresh_access_token';

	// All Graph API calls: graph.instagram.com (NOT graph.facebook.com)
	private const GRAPH_URL = 'https://graph.instagram.com/v21.0';

	// ── Scopes (Instagram Business Login, valid after Jan 27 2025) ───────────
	// These replace the old Facebook Login scopes that caused "Invalid Scopes":
	//   ✗ instagram_basic            → ✓ instagram_business_basic
	//   ✗ instagram_content_publish  → ✓ instagram_business_content_publish
	//   ✗ pages_show_list            → not needed (no Facebook Pages in this flow)
	//   ✗ business_management        → not needed (requires special App Review)
	private const SCOPE = 'instagram_business_basic,instagram_business_content_publish';

	private const MAX_POLLS  = 20;
	private const POLL_SLEEP = 10; // seconds between container status polls

	private SAS_Token_Service $token_service;
	private SAS_Log_Service   $log_service;

	public function __construct() {
		$this->token_service = new SAS_Token_Service();
		$this->log_service   = new SAS_Log_Service();
	}

	// ── Credentials ───────────────────────────────────────────────────────────

	private function get_app_id(): string {
		return trim( (string) ( new SAS_Settings_Service() )->get( 'instagram_app_id', null, '' ) );
	}

	private function get_app_secret(): string {
		$enc = (string) ( new SAS_Settings_Service() )->get( 'instagram_app_secret_enc', null, '' );
		return $enc ? SAS_Token_Service::decrypt( $enc ) : '';
	}

	private function get_config_id(): string {
		return trim( (string) ( new SAS_Settings_Service() )->get( 'instagram_config_id', null, '' ) );
	}

	private function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=sas-accounts&sas_oauth=instagram' );
	}

	// ── OAuth: Step 1 — build the authorize URL ───────────────────────────────

	public function get_auth_url(): string {
		$config_id = $this->get_config_id();

		$params = [
			'client_id'     => $this->get_app_id(),
			'redirect_uri'  => $this->get_redirect_uri(),
			'response_type' => 'code',
			'state'         => wp_create_nonce( 'sas_instagram_oauth' ),
		];

		if ( $config_id ) {
			// New Meta Developer Console flow (2024+): use a Business Login
			// Configuration ID. The config bundles permissions + redirect URIs,
			// so scope / enable_fb_login are NOT sent.
			$params['config_id'] = $config_id;
		} else {
			// Legacy scope-based flow (still works when config_id is not used).
			$params['scope']           = self::SCOPE;
			$params['enable_fb_login'] = '0';
		}

		return self::AUTHORIZE_URL . '?' . http_build_query( $params );
	}

	// ── OAuth: Steps 2-4 — handle the callback ───────────────────────────────

	public function handle_callback( string $code, string $state ): bool {
		if ( ! wp_verify_nonce( $state, 'sas_instagram_oauth' ) ) {
			throw new RuntimeException( __( 'Invalid OAuth state.', 'social-auto-scheduler' ) );
		}

		// Step 2: Exchange authorization code → short-lived token.
		// MUST be POST (not GET) with Content-Type: application/x-www-form-urlencoded.
		$short_data    = $this->exchange_code_for_token( $code );
		$short_token   = $short_data['access_token'];
		$token_user_id = isset( $short_data['user_id'] ) ? (string) $short_data['user_id'] : null;

		// Step 3: Exchange short-lived → long-lived token (60 days).
		// Uses graph.instagram.com with grant_type=ig_exchange_token (NOT fb_exchange_token).
		$long_data  = $this->get_long_lived_token( $short_token );
		$long_token = $long_data['access_token'];
		$expires_in = isset( $long_data['expires_in'] ) ? (int) $long_data['expires_in'] : 5184000;

		// Step 4: Fetch user profile from graph.instagram.com/me.
		// No Facebook Pages traversal — Business Login provides the IG user ID directly.
		$user_info = $this->get_user_info( $long_token, $token_user_id );

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $expires_in );

		$this->token_service->save_account(
			[
				'platform'         => 'instagram',
				'account_name'     => $user_info['username'] ?? 'Instagram Account',
				'access_token'     => $long_token,
				'refresh_token'    => '',
				'token_expires_at' => $expires_at,
				'metadata'         => $user_info,
			]
		);

		$this->log_service->info(
			'instagram_connected',
			'Instagram account connected via Business Login',
			[ 'account' => $user_info ]
		);

		return true;
	}

	// ── Token management ──────────────────────────────────────────────────────

	/**
	 * POST https://api.instagram.com/oauth/access_token
	 *
	 * Exchange the one-time authorization code for a short-lived Instagram
	 * User access token (~1 hour). Returns the full response array which
	 * includes `access_token` and `user_id`.
	 */
	private function exchange_code_for_token( string $code ): array {
		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 30,
				'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'    => [
					'client_id'     => $this->get_app_id(),
					'client_secret' => $this->get_app_secret(),
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $this->get_redirect_uri(),
					'code'          => $code,
				],
			]
		);

		$this->assert_response( $response, 'instagram_code_exchange' );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			$err = $data['error_message'] ?? $data['error']['message'] ?? 'No access_token in response';
			throw new RuntimeException( 'Instagram token exchange failed: ' . $err );
		}

		return $data;
	}

	/**
	 * GET https://graph.instagram.com/access_token
	 *     ?grant_type=ig_exchange_token
	 *     &client_secret={secret}
	 *     &access_token={short_lived_token}
	 *
	 * Returns array with `access_token` (long-lived) and `expires_in` (seconds).
	 * Note: NO client_id parameter. Grant type is ig_exchange_token, NOT fb_exchange_token.
	 */
	private function get_long_lived_token( string $short_token ): array {
		$response = wp_remote_get(
			self::LONG_TOKEN_URL . '?' . http_build_query(
				[
					'grant_type'    => 'ig_exchange_token',
					'client_secret' => $this->get_app_secret(),
					'access_token'  => $short_token,
				]
			),
			[ 'timeout' => 30 ]
		);

		$this->assert_response( $response, 'instagram_long_token' );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			// Non-fatal fallback — continue with the short-lived token.
			$this->log_service->warning(
				'instagram_long_token',
				'Long-lived token exchange returned no token; falling back to short-lived (~1 hour).'
			);
			return [ 'access_token' => $short_token, 'expires_in' => 3600 ];
		}

		return $data;
	}

	/**
	 * Refresh an existing long-lived token before it expires (must be done
	 * while the token is still valid — within 60 days of issue).
	 *
	 * GET https://graph.instagram.com/refresh_access_token
	 *     ?grant_type=ig_refresh_token
	 *     &access_token={existing_long_lived_token}
	 *
	 * No client_secret required for refresh. Returns a new 60-day token.
	 */
	public function refresh_token( array $account ): array {
		$response = wp_remote_get(
			self::REFRESH_TOKEN_URL . '?' . http_build_query(
				[
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $account['access_token'],
				]
			),
			[ 'timeout' => 30 ]
		);

		if ( ! is_wp_error( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $data['access_token'] ) ) {
				$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 5184000;
				$expires_at = gmdate( 'Y-m-d H:i:s', time() + $expires_in );

				$this->token_service->update_tokens(
					(int) $account['id'],
					$data['access_token'],
					null,
					$expires_at
				);

				$account['access_token']     = $data['access_token'];
				$account['token_expires_at'] = $expires_at;
			} else {
				$this->log_service->warning(
					'instagram_refresh_token',
					'Token refresh response contained no access_token'
				);
			}
		}

		return $account;
	}

	// ── User info ─────────────────────────────────────────────────────────────

	/**
	 * GET https://graph.instagram.com/v21.0/me
	 *     ?fields=id,username,name,profile_picture_url
	 *     &access_token={long_lived_token}
	 *
	 * Returns [ ig_user_id, username, name, profile_pic ].
	 * With Instagram Business Login there is NO need to call /me/accounts or
	 * traverse Facebook Pages — the IG User ID is directly accessible.
	 *
	 * @param string|null $fallback_user_id User ID from the token exchange
	 *                                       response, used if /me fails.
	 */
	private function get_user_info( string $token, ?string $fallback_user_id = null ): array {
		$response = wp_remote_get(
			self::GRAPH_URL . '/me?' . http_build_query(
				[
					'fields'       => 'id,username,name,profile_picture_url,followers_count',
					'access_token' => $token,
				]
			),
			[ 'timeout' => 15 ]
		);

		if ( is_wp_error( $response ) ) {
			if ( $fallback_user_id ) {
				$this->log_service->warning(
					'instagram_user_info',
					'Could not reach /me, using user_id from token response: ' . $response->get_error_message()
				);
				return [ 'ig_user_id' => $fallback_user_id, 'username' => 'instagram_user', 'name' => '', 'profile_pic' => '' ];
			}
			throw new RuntimeException( 'Could not fetch Instagram user info: ' . $response->get_error_message() );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['id'] ) ) {
			if ( $fallback_user_id ) {
				$this->log_service->warning(
					'instagram_user_info',
					'Instagram /me returned no id, using fallback: ' . ( $data['error']['message'] ?? wp_remote_retrieve_body( $response ) )
				);
				return [ 'ig_user_id' => $fallback_user_id, 'username' => 'instagram_user', 'name' => '', 'profile_pic' => '' ];
			}
			$err = $data['error']['message'] ?? 'Unknown error from /me';
			throw new RuntimeException( 'Instagram /me error: ' . $err );
		}

		return [
			'ig_user_id'  => (string) $data['id'],
			'username'    => $data['username']            ?? '',
			'name'        => $data['name']                ?? '',
			'profile_pic' => $data['profile_picture_url'] ?? '',
		];
	}

	// ── Publishing ────────────────────────────────────────────────────────────

	public function publish_video( array $video ): string {
		$account = $this->token_service->get_account( 'instagram', (int) $video['user_id'] );
		if ( ! $account ) {
			throw new RuntimeException( __( 'No Instagram account connected.', 'social-auto-scheduler' ) );
		}

		if ( $this->token_service->is_token_expired( $account ) ) {
			$account = $this->refresh_token( $account );
		}

		$meta = is_string( $video['metadata'] )
			? json_decode( $video['metadata'], true )
			: ( $video['metadata'] ?? [] );
		$meta = is_array( $meta ) ? $meta : [];

		$ig_user_id = $meta['ig_user_id'] ?? $account['metadata']['ig_user_id'] ?? '';

		// With Instagram Business Login the access_token IS the Instagram User token.
		// There is no page_token in this flow — do not look for one.
		$token = $account['access_token'];

		if ( empty( $ig_user_id ) ) {
			throw new RuntimeException(
				__( 'Instagram User ID not found. Disconnect and reconnect your Instagram account.', 'social-auto-scheduler' )
			);
		}

		if ( empty( $video['file_url'] ) ) {
			throw new RuntimeException(
				__( 'Video must have a publicly accessible URL for Instagram publishing.', 'social-auto-scheduler' )
			);
		}

		$caption = $video['description'] ?? '';
		if ( ! empty( $video['tags'] ) ) {
			$tags_raw = is_string( $video['tags'] ) ? json_decode( $video['tags'], true ) : $video['tags'];
			if ( is_array( $tags_raw ) ) {
				$hashtags = implode( ' ', array_map( fn( $t ) => '#' . ltrim( trim( $t ), '#' ), $tags_raw ) );
				$caption  = trim( $caption . "\n\n" . $hashtags );
			}
		}

		$container_id = $this->create_container( $ig_user_id, $token, $video['file_url'], $caption );
		$this->wait_for_container( $container_id, $token );
		$media_id = $this->publish_container( $ig_user_id, $container_id, $token );

		$this->log_service->info(
			'instagram_published',
			'Video published to Instagram as Reel',
			[ 'media_id' => $media_id ],
			(int) $video['id'],
			(int) $account['id']
		);

		$meta['instagram_media_id'] = $media_id;
		( new SAS_Video_Service() )->update(
			(int) $video['id'],
			[ 'metadata' => json_encode( $meta ) ],
			(int) $video['user_id']
		);

		return $media_id;
	}

	/**
	 * POST https://graph.instagram.com/v21.0/{ig_user_id}/media
	 *
	 * Creates a Reels media container. Returns the container ID.
	 * The video at video_url must be publicly accessible (Instagram fetches it).
	 */
	private function create_container(
		string $ig_user_id,
		string $token,
		string $video_url,
		string $caption
	): string {
		$response = wp_remote_post(
			self::GRAPH_URL . '/' . $ig_user_id . '/media',
			[
				'timeout' => 60,
				'body'    => [
					'media_type'    => 'REELS',
					'video_url'     => $video_url,
					'caption'       => $caption,
					'share_to_feed' => 'true',
					'access_token'  => $token,
				],
			]
		);

		$this->assert_response( $response, 'instagram_create_container' );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['id'] ) ) {
			throw new RuntimeException( __( 'Instagram did not return a container ID.', 'social-auto-scheduler' ) );
		}

		return (string) $data['id'];
	}

	/**
	 * Poll GET /{container_id}?fields=status_code,status until status_code = FINISHED.
	 * Max wait: MAX_POLLS × POLL_SLEEP = 200 seconds.
	 */
	private function wait_for_container( string $container_id, string $token ): void {
		for ( $i = 0; $i < self::MAX_POLLS; $i++ ) {
			$response = wp_remote_get(
				self::GRAPH_URL . '/' . $container_id . '?' . http_build_query(
					[
						'fields'       => 'status_code,status',
						'access_token' => $token,
					]
				),
				[ 'timeout' => 15 ]
			);

			if ( ! is_wp_error( $response ) ) {
				$data   = json_decode( wp_remote_retrieve_body( $response ), true );
				$status = $data['status_code'] ?? '';

				if ( 'FINISHED' === $status ) {
					return;
				}

				if ( 'ERROR' === $status ) {
					throw new RuntimeException(
						__( 'Instagram container processing failed: ', 'social-auto-scheduler' )
						. ( $data['status'] ?? 'unknown error' )
					);
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_sleep
			sleep( self::POLL_SLEEP );
		}

		throw new RuntimeException( __( 'Instagram container timed out waiting to be FINISHED.', 'social-auto-scheduler' ) );
	}

	/**
	 * POST https://graph.instagram.com/v21.0/{ig_user_id}/media_publish
	 *
	 * Publishes the ready container. Returns the published media ID.
	 */
	private function publish_container(
		string $ig_user_id,
		string $container_id,
		string $token
	): string {
		$response = wp_remote_post(
			self::GRAPH_URL . '/' . $ig_user_id . '/media_publish',
			[
				'timeout' => 30,
				'body'    => [
					'creation_id'  => $container_id,
					'access_token' => $token,
				],
			]
		);

		$this->assert_response( $response, 'instagram_publish_container' );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['id'] ) ) {
			throw new RuntimeException( __( 'Instagram did not return a media ID after publish.', 'social-auto-scheduler' ) );
		}

		return (string) $data['id'];
	}

	// ── Error handling ────────────────────────────────────────────────────────

	/**
	 * Assert that a WP HTTP response is not a WP_Error and is a 2xx status.
	 * Decodes Meta's JSON error body for a meaningful exception message.
	 */
	private function assert_response( $response, string $context ): void {
		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			$this->log_service->error( $context, $msg );
			throw new RuntimeException( $msg );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			$body    = wp_remote_retrieve_body( $response );
			$decoded = json_decode( $body, true );
			// Meta errors: {"error":{"message":"...","code":190,"type":"OAuthException"}}
			// api.instagram.com errors: {"error_message":"...","code":400}
			$msg  = $decoded['error']['message'] ?? $decoded['error_message'] ?? $body;
			$full = "Instagram API [{$code}] {$context}: {$msg}";
			$this->log_service->error( $context, $full );
			throw new RuntimeException( $full );
		}
	}
}

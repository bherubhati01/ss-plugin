<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Video upload service — thin client version.
 *
 * Workflow:
 *   1. Upload video to WordPress Media Library (standard WP API).
 *   2. Get the publicly accessible attachment URL.
 *   3. POST video URL + metadata to the backend.
 *   4. Return the backend video ID.
 *
 * The plugin no longer stores video files in a custom directory or manages
 * a local video database. All scheduling and publishing happens in the backend.
 */
class SAS_Upload_Service {

	private const ALLOWED_MIMES = [
		'mp4'  => 'video/mp4',
		'mov'  => 'video/quicktime',
		'avi'  => 'video/x-msvideo',
		'webm' => 'video/webm',
	];

	private const MAX_FILE_SIZE = 5368709120; // 5 GB

	// ── Platform helpers ──────────────────────────────────────────────────────

	/**
	 * Sanitize a platforms input (array or string) down to supported slugs.
	 */
	public static function sanitize_platforms( $raw ): array {
		if ( ! is_array( $raw ) ) {
			$raw = [ $raw ];
		}
		$allowed = [ 'youtube', 'instagram' ];
		$out     = [];
		foreach ( $raw as $p ) {
			$p = sanitize_key( (string) $p );
			if ( in_array( $p, $allowed, true ) && ! in_array( $p, $out, true ) ) {
				$out[] = $p;
			}
		}
		return $out ?: [ 'youtube' ];
	}

	// ── Chunked upload (large files) ──────────────────────────────────────────

	private static function tmp_dir(): string {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'sas-tmp';
		wp_mkdir_p( $dir );
		return $dir;
	}

	private static function part_path( string $upload_id ): string {
		return self::tmp_dir() . '/' . sanitize_file_name( $upload_id ) . '.part';
	}

	/**
	 * Start a chunked upload session. Returns ['upload_id' => …].
	 */
	public function init_upload( array $args ): array {
		$file_size = (int) ( $args['file_size'] ?? 0 );
		if ( $file_size <= 0 || $file_size > self::MAX_FILE_SIZE ) {
			throw new RuntimeException( __( 'Invalid file size (max 5 GB).', 'social-auto-scheduler' ) );
		}
		$file_name = sanitize_file_name( $args['file_name'] ?? 'video.mp4' );
		$info      = wp_check_filetype( $file_name, self::ALLOWED_MIMES );
		if ( empty( $info['type'] ) ) {
			throw new RuntimeException( __( 'Only MP4, MOV, AVI, and WEBM files are allowed.', 'social-auto-scheduler' ) );
		}

		$upload_id = wp_generate_uuid4();
		set_transient( 'sas_upload_' . $upload_id, [
			'file_name' => $file_name,
			'file_size' => $file_size,
			'platforms' => self::sanitize_platforms( $args['platforms'] ?? [] ),
		], DAY_IN_SECONDS );

		// Start with an empty part file.
		file_put_contents( self::part_path( $upload_id ), '' );

		return [ 'upload_id' => $upload_id ];
	}

	/**
	 * Append one chunk to the session's part file (chunks arrive in order).
	 */
	public function receive_chunk( string $upload_id, int $chunk_index, string $data ): array {
		if ( false === get_transient( 'sas_upload_' . $upload_id ) ) {
			throw new RuntimeException( __( 'Upload session expired — please retry the upload.', 'social-auto-scheduler' ) );
		}
		$part = self::part_path( $upload_id );
		file_put_contents( $part, $data, FILE_APPEND );
		return [ 'received' => $chunk_index, 'bytes' => (int) filesize( $part ) ];
	}

	/**
	 * Assemble the uploaded file into the Media Library, then register it
	 * with the backend (scheduled to the next available slot).
	 */
	public function finalize_upload( string $upload_id ): array {
		$session = get_transient( 'sas_upload_' . $upload_id );
		if ( false === $session ) {
			throw new RuntimeException( __( 'Upload session expired — please retry the upload.', 'social-auto-scheduler' ) );
		}
		$part = self::part_path( $upload_id );
		delete_transient( 'sas_upload_' . $upload_id );

		if ( ! file_exists( $part ) || (int) filesize( $part ) !== (int) $session['file_size'] ) {
			@unlink( $part );
			throw new RuntimeException( __( 'Upload incomplete — file size mismatch. Please retry.', 'social-auto-scheduler' ) );
		}

		// Move the assembled file into the uploads directory.
		$upload_dir = wp_upload_dir();
		$filename   = wp_unique_filename( $upload_dir['path'], $session['file_name'] );
		$dest       = trailingslashit( $upload_dir['path'] ) . $filename;
		if ( ! @rename( $part, $dest ) ) {
			@unlink( $part );
			throw new RuntimeException( __( 'Could not move the uploaded file into the Media Library.', 'social-auto-scheduler' ) );
		}

		$filetype      = wp_check_filetype( $filename, self::ALLOWED_MIMES );
		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => $filetype['type'],
			'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $dest );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			throw new RuntimeException( __( 'Could not create the Media Library attachment.', 'social-auto-scheduler' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		wp_update_attachment_metadata( (int) $attachment_id, wp_generate_attachment_metadata( (int) $attachment_id, $dest ) );

		return $this->schedule_and_register( (int) $attachment_id, $session['platforms'] );
	}

	/**
	 * Compute the next publish slot, register the attachment with the backend
	 * (one video, one target per platform), and return the API response shape.
	 */
	public function schedule_and_register( int $attachment_id, array $platforms ): array {
		$scheduler = new SAS_Scheduler_Service();
		$when      = $scheduler->get_next_available_date( null, $platforms[0] ?? 'youtube' );

		$video = $this->register_attachment( $attachment_id, [
			'platforms'    => $platforms,
			'caption'      => get_the_title( $attachment_id ),
			'scheduled_at' => $when->format( 'c' ),
		] );

		return [
			'videos' => [
				[
					'id'           => $video['id'] ?? 0,
					'publish_date' => $when->format( 'Y-m-d H:i:s' ),
				],
			],
		];
	}

	// ── Single-file upload → backend ─────────────────────────────────────────

	/**
	 * Upload a video to WordPress Media Library, then register it with the backend.
	 *
	 * @param array  $file       $_FILES entry.
	 * @param array  $meta       {
	 *   caption, description, tags (array), platform, social_account_id,
	 *   scheduled_at (ISO 8601 string or null)
	 * }
	 * @return array Backend video object.
	 * @throws RuntimeException On upload or backend error.
	 */
	public function upload_and_register( array $file, array $meta ): array {
		$this->validate_file( $file );

		$attachment_id = $this->upload_to_media_library( $file );
		$file_url      = wp_get_attachment_url( $attachment_id );
		$thumbnail_url = $this->get_thumbnail_url( $attachment_id );

		if ( ! $file_url ) {
			throw new RuntimeException( __( 'Could not retrieve URL for uploaded video.', 'social-auto-scheduler' ) );
		}

		return $this->send_to_backend( $file_url, $thumbnail_url, $meta );
	}

	// ── Register a video URL that's already in the Media Library ─────────────

	/**
	 * Register an existing WP Media Library attachment with the backend.
	 * Called when the user selects an already-uploaded video.
	 *
	 * @param int   $attachment_id WP attachment post ID.
	 * @param array $meta          Same as upload_and_register().
	 * @return array Backend video object.
	 */
	public function register_attachment( int $attachment_id, array $meta ): array {
		$file_url      = wp_get_attachment_url( $attachment_id );
		$thumbnail_url = $this->get_thumbnail_url( $attachment_id );

		if ( ! $file_url ) {
			throw new RuntimeException( __( 'Invalid attachment ID.', 'social-auto-scheduler' ) );
		}

		return $this->send_to_backend( $file_url, $thumbnail_url, $meta );
	}

	// ── Internal helpers ──────────────────────────────────────────────────────

	private function validate_file( array $file ): void {
		$info = wp_check_filetype( $file['name'], self::ALLOWED_MIMES );
		if ( empty( $info['type'] ) ) {
			throw new RuntimeException( __( 'Only MP4, MOV, AVI, and WEBM files are allowed.', 'social-auto-scheduler' ) );
		}
		if ( (int) $file['size'] > self::MAX_FILE_SIZE ) {
			throw new RuntimeException( __( 'File exceeds the 5 GB size limit.', 'social-auto-scheduler' ) );
		}
	}

	private function upload_to_media_library( array $file ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload_array( $file );

		if ( is_wp_error( $attachment_id ) ) {
			throw new RuntimeException(
				sprintf( __( 'Media Library upload failed: %s', 'social-auto-scheduler' ), $attachment_id->get_error_message() )
			);
		}

		return (int) $attachment_id;
	}

	private function get_thumbnail_url( int $attachment_id ): string {
		$thumb = wp_get_attachment_image_url( $attachment_id, 'large' );
		return $thumb ?: '';
	}

	private function send_to_backend( string $file_url, string $thumbnail_url, array $meta ): array {
		if ( ! SAS_License_Manager::is_active() ) {
			throw new RuntimeException( __( 'Plugin is not connected to the backend. Please activate your license.', 'social-auto-scheduler' ) );
		}

		// One backend video with a target per selected platform — the backend
		// resolves each platform to this website's connected account.
		$platforms = $meta['platforms'] ?? [];
		if ( ! is_array( $platforms ) ) {
			$platforms = [ $platforms ];
		}
		if ( empty( $platforms ) && ! empty( $meta['platform'] ) ) {
			$platforms = [ $meta['platform'] ];
		}
		$platforms = array_values( array_filter( array_map( 'sanitize_key', $platforms ) ) );

		$body = [
			'file_url'      => $file_url,
			'thumbnail_url' => $thumbnail_url,
			'caption'       => sanitize_text_field( $meta['caption'] ?? '' ),
			'description'   => sanitize_textarea_field( $meta['description'] ?? '' ),
			'tags'          => SAS_Helpers::sanitize_tags( $meta['tags'] ?? [] ),
			'platforms'     => $platforms ?: [ 'instagram' ],
			'scheduled_at'  => $meta['scheduled_at'] ?? null,
		];

		$result = SAS_Backend_Client::post( '/api/v1/videos/', $body );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}

		return $result;
	}
}

/**
 * Compatibility shim: wraps $_FILES format into WP's media_handle_upload.
 * WP's media_handle_upload() only takes a file key from $_FILES; this helper
 * moves a file into the $_FILES superglobal temporarily so WP can process it.
 */
function media_handle_upload_array( array $file ): int|WP_Error {
	$key = '_sas_tmp_upload_' . uniqid();
	$_FILES[ $key ] = $file;
	$result = media_handle_upload( $key, 0, [], [ 'test_form' => false ] );
	unset( $_FILES[ $key ] );
	return $result;
}

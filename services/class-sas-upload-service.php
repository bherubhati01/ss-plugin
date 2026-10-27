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

	private const ALLOWED_IMAGE_MIMES = [
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'webp' => 'image/webp',
	];

	private const MAX_FILE_SIZE = 5368709120; // 5 GB
	private const MAX_IMAGE_FILE_SIZE = 26214400; // 25 MB — Instagram/Facebook's own photo-post ceiling
	// Instagram's own carousel limit — same ceiling the backend's
	// MAX_PHOTO_POST_IMAGES enforces (apps/videos/serializers.py), kept in
	// sync by convention since the plugin pre-validates before ever
	// reaching that backend check.
	private const MAX_PHOTO_IMAGES = 10;

	// ── Platform helpers ──────────────────────────────────────────────────────

	/**
	 * Sanitize a platforms input (array or string) down to supported slugs.
	 */
	public static function sanitize_platforms( $raw ): array {
		if ( ! is_array( $raw ) ) {
			$raw = [ $raw ];
		}
		$allowed = [ 'youtube', 'instagram', 'facebook' ];
		$out     = [];
		foreach ( $raw as $p ) {
			$p = sanitize_key( (string) $p );
			if ( in_array( $p, $allowed, true ) && ! in_array( $p, $out, true ) ) {
				$out[] = $p;
			}
		}
		return $out ?: [ 'youtube' ];
	}

	/**
	 * Sanitize a content_type input down to 'reel' (default), 'story', or
	 * 'photo'. Stories and photos both publish only to Instagram/Facebook
	 * — see SAS_Upload_Service::send_to_backend() and the backend's
	 * VideoService, which both enforce this independently.
	 */
	public static function sanitize_content_type( $raw ): string {
		$val = sanitize_key( (string) $raw );
		if ( 'story' === $val || 'photo' === $val ) {
			return $val;
		}
		return 'reel';
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
			'file_name'    => $file_name,
			'file_size'    => $file_size,
			'platforms'    => self::sanitize_platforms( $args['platforms'] ?? [] ),
			'content_type' => self::sanitize_content_type( $args['content_type'] ?? 'reel' ),
			// Details-step fields (see admin.js's upload wizard) — carried
			// through the chunked session to finalize_upload() since that's
			// the only place the backend registration actually happens.
			'caption'      => sanitize_text_field( $args['caption'] ?? '' ),
			'description'  => sanitize_textarea_field( $args['description'] ?? '' ),
			'tags'         => SAS_Helpers::sanitize_tags( $args['tags'] ?? [] ),
			'scheduled_at' => $args['scheduled_at'] ?? null,
			'publish_now'  => ! empty( $args['publish_now'] ),
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
	 * Progress of a chunked upload session.
	 */
	public function get_status( string $upload_id ): array {
		$session = get_transient( 'sas_upload_' . $upload_id );
		if ( false === $session ) {
			return [ 'exists' => false ];
		}
		$part  = self::part_path( $upload_id );
		$bytes = file_exists( $part ) ? (int) filesize( $part ) : 0;
		return [
			'exists'         => true,
			'file_name'      => $session['file_name'],
			'file_size'      => (int) $session['file_size'],
			'bytes_received' => $bytes,
			'percent'        => $session['file_size'] > 0
				? (int) floor( $bytes / (int) $session['file_size'] * 100 )
				: 0,
		];
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

		return $this->schedule_and_register( (int) $attachment_id, $session );
	}

	/**
	 * Register the attachment with the backend (one video, one target per
	 * platform) using the caller-supplied caption/description/tags/schedule
	 * from the upload wizard's Details step, and return the API response
	 * shape. Falls back to the next available scheduler slot when neither
	 * an explicit scheduled_at nor publish_now was given, preserving the
	 * old auto-schedule behavior for any caller that still omits them.
	 *
	 * @param array $meta { platforms, content_type, caption, description,
	 *                      tags, scheduled_at, publish_now }
	 */
	public function schedule_and_register( int $attachment_id, array $meta ): array {
		$platforms    = $meta['platforms'] ?? [ 'youtube' ];
		$content_type = $meta['content_type'] ?? 'reel';
		$publish_now  = ! empty( $meta['publish_now'] );

		if ( $publish_now ) {
			$scheduled_at = null;
		} elseif ( ! empty( $meta['scheduled_at'] ) ) {
			$scheduled_at = $meta['scheduled_at'];
		} else {
			$scheduler    = new SAS_Scheduler_Service();
			$when         = $scheduler->get_next_available_date( null, $platforms[0] ?? 'youtube' );
			$scheduled_at = $when->format( 'c' );
		}

		$video = $this->register_attachment( $attachment_id, [
			'platforms'    => $platforms,
			'content_type' => $content_type,
			// Stories don't support captions — see send_to_backend().
			'caption'      => 'story' === $content_type ? '' : ( ( $meta['caption'] ?? '' ) ?: get_the_title( $attachment_id ) ),
			'description'  => $meta['description'] ?? '',
			'tags'         => $meta['tags'] ?? [],
			'scheduled_at' => $scheduled_at,
			'publish_now'  => $publish_now,
		] );

		$publish_date = '';
		if ( $scheduled_at ) {
			try {
				$publish_date = ( new DateTime( $scheduled_at ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) {
				$publish_date = '';
			}
		}

		return [
			'videos' => [
				[
					'id'           => $video['id'] ?? 0,
					'publish_date' => $publish_date,
					'publish_now'  => $publish_now,
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

		if ( ! $file_url ) {
			throw new RuntimeException( __( 'Could not retrieve URL for uploaded video.', 'social-auto-scheduler' ) );
		}

		$meta['wp_attachment_id'] = $attachment_id;
		return $this->send_to_backend( $file_url, $meta );
	}

	// ── Photo post (content_type=photo) — multiple images, no chunking ───────

	/**
	 * Upload 1-10 images to the Media Library and register them with the
	 * backend as a single photo/carousel post. Images are small enough
	 * (25 MB ceiling each) that the chunked-upload machinery above — built
	 * for multi-GB video files — isn't needed; one multipart request
	 * carries every file.
	 *
	 * @param array $files Each a $_FILES-shaped entry, in carousel order.
	 * @param array $meta  Same shape as upload_and_register(), minus
	 *                      content_type (always forced to 'photo' here).
	 * @return array Backend video object.
	 * @throws RuntimeException On validation, upload, or backend error.
	 */
	public function upload_photos_and_register( array $files, array $meta ): array {
		if ( empty( $files ) ) {
			throw new RuntimeException( __( 'Select at least one image.', 'social-auto-scheduler' ) );
		}
		if ( count( $files ) > self::MAX_PHOTO_IMAGES ) {
			throw new RuntimeException(
				/* translators: %d: maximum number of images allowed in a photo post */
				sprintf( __( 'A photo post supports at most %d images.', 'social-auto-scheduler' ), self::MAX_PHOTO_IMAGES )
			);
		}
		foreach ( $files as $file ) {
			$this->validate_image_file( $file );
		}

		$images         = [];
		$attachment_ids = [];
		foreach ( $files as $file ) {
			$attachment_id = $this->upload_to_media_library( $file );
			$url           = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				throw new RuntimeException( __( 'Could not retrieve URL for an uploaded image.', 'social-auto-scheduler' ) );
			}
			$images[]         = [ 'image_url' => $url ];
			$attachment_ids[] = $attachment_id;
		}

		$meta['content_type'] = 'photo';
		// KNOWN LIMITATION: the backend's Video.wp_attachment_id is a single
		// field (one id per Video — see MediaCleanupView), not a list, so
		// only this FIRST image's Media Library attachment is ever tracked
		// for the 48h-after-publish cleanup sweep. Images 2+ in a multi-
		// image photo post are never auto-deleted from the Media Library.
		// Fixing this needs a backend change (tracking wp_attachment_id per
		// VideoImage, not just per Video) — out of scope here; flagging so
		// it isn't silently assumed to work.
		$meta['wp_attachment_id'] = $attachment_ids[0];
		return $this->send_to_backend( '', $meta, $images );
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
		$file_url = wp_get_attachment_url( $attachment_id );

		if ( ! $file_url ) {
			throw new RuntimeException( __( 'Invalid attachment ID.', 'social-auto-scheduler' ) );
		}

		$meta['wp_attachment_id'] = $attachment_id;
		return $this->send_to_backend( $file_url, $meta );
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

	private function validate_image_file( array $file ): void {
		$info = wp_check_filetype( $file['name'], self::ALLOWED_IMAGE_MIMES );
		if ( empty( $info['type'] ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: the uploaded file's name */
					__( '%s: only JPG, PNG, and WEBP images are allowed.', 'social-auto-scheduler' ),
					$file['name'] ?? ''
				)
			);
		}
		if ( (int) $file['size'] > self::MAX_IMAGE_FILE_SIZE ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: the uploaded file's name */
					__( '%s exceeds the 25 MB image size limit.', 'social-auto-scheduler' ),
					$file['name'] ?? ''
				)
			);
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

	private function send_to_backend( string $file_url, array $meta, array $images = [] ): array {
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
		$platforms    = array_values( array_filter( array_map( 'sanitize_key', $platforms ) ) );
		$content_type = self::sanitize_content_type( $meta['content_type'] ?? 'reel' );

		// Stories can only publish to Instagram or Facebook — the backend
		// enforces this too, but filtering here avoids silently dropping the
		// whole request if a non-Story-capable platform slipped through the UI.
		if ( 'story' === $content_type ) {
			$platforms = array_values( array_intersect( $platforms, [ 'instagram', 'facebook' ] ) );
		}
		// Photo posts have no YouTube equivalent at all (YouTube's API has no
		// photo-posting capability) — same restriction VideoService._resolve_accounts
		// enforces on the backend.
		if ( 'photo' === $content_type ) {
			$platforms = array_values( array_intersect( $platforms, [ 'instagram', 'facebook' ] ) );
		}

		// "Default Description"/"Default Tags" (Settings → Default Video
		// Settings) fill in only if the upload wizard's Details step was
		// left blank for these — lets the saved defaults still do something
		// useful without overriding what the user actually typed.
		$settings    = new SAS_Settings_Service();
		$description = sanitize_textarea_field( $meta['description'] ?? '' );
		if ( '' === $description ) {
			$description = sanitize_textarea_field( (string) $settings->get( 'default_description', null, '' ) );
		}
		$tags = SAS_Helpers::sanitize_tags( $meta['tags'] ?? [] );
		if ( empty( $tags ) ) {
			$tags = SAS_Helpers::sanitize_tags( (string) $settings->get( 'default_tags', null, '' ) );
		}

		// "YouTube Upload Defaults" (category/privacy) likewise only ever
		// applied if passed through in metadata — YouTubePublisher on the
		// backend already reads video.metadata['category_id'/'privacy'],
		// but nothing here ever populated it, so those two settings were
		// also saved and silently ignored. Harmless to always include: the
		// other platforms' publishers never read this metadata.
		$metadata = [
			'category_id' => (string) $settings->get( 'youtube_category', null, '22' ),
			'privacy'     => (string) $settings->get( 'youtube_privacy', null, 'public' ),
		];

		$body = [
			// 'photo' posts use `images` instead — an empty string here is
			// what the backend's VideoCreateSerializer expects when images
			// is populated (see apps/videos/serializers.py's validate()).
			'file_url'         => $file_url,
			'images'           => $images,
			'caption'          => 'story' === $content_type ? '' : sanitize_text_field( $meta['caption'] ?? '' ),
			'description'      => 'story' === $content_type ? '' : $description,
			'tags'             => 'story' === $content_type ? [] : $tags,
			'content_type'     => $content_type,
			'platforms'        => $platforms ?: [ 'reel' === $content_type ? 'youtube' : 'instagram' ],
			'scheduled_at'     => $meta['scheduled_at'] ?? null,
			'publish_now'      => ! empty( $meta['publish_now'] ),
			'metadata'         => $metadata,
			// So the backend knows to offer this video for the 48h-after-publish
			// Media Library cleanup sweep — see cron/class-sas-cron.php.
			'wp_attachment_id' => isset( $meta['wp_attachment_id'] ) ? (int) $meta['wp_attachment_id'] : null,
		];

		$result = SAS_Backend_Client::post( '/api/v1/videos/plugin/', $body );

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

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin-side cron: syncs video statuses from the backend.
 *
 * Publishing is now handled entirely by the backend's Celery scheduler.
 * This cron only:
 *   1. Verifies the license periodically.
 *   2. Syncs video statuses (published/failed) from the backend to local cache.
 *   3. Syncs plugin metadata to the backend (heartbeat).
 *   4. Deletes Media Library files for videos 48h past publish (WordPress
 *      storage's equivalent of the backend's Google Drive cleanup — Drive is
 *      frontend-only, this plugin always uploads to and cleans up its own
 *      Media Library).
 */
class SAS_Cron {

	private SAS_Log_Service $log_service;

	public function __construct() {
		$this->log_service = new SAS_Log_Service();
	}

	public function run(): void {
		$lock_key = 'sas_cron_running';
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 300 );

		try {
			SAS_License_Manager::verify_periodically();
			$this->sync_from_backend();
			$this->heartbeat();
			$this->cleanup_wp_media();
		} catch ( Throwable $e ) {
			$this->log_service->error( 'cron_error', $e->getMessage() );
		} finally {
			delete_transient( $lock_key );
		}
	}

	// ── Sync video statuses from backend ─────────────────────────────────────

	private function sync_from_backend(): void {
		if ( ! SAS_License_Manager::is_active() ) {
			return;
		}

		$result = SAS_Backend_Client::get( '/api/v1/videos/plugin/', [
			'status'    => 'published',
			'page_size' => 50,
		] );

		if ( is_wp_error( $result ) ) {
			$this->log_service->warning( 'cron_sync_failed', $result->get_error_message() );
			return;
		}

		// Cache the synced video list so admin templates can display it without
		// making a live API call on every page load.
		set_transient( 'sas_synced_videos', $result, 5 * MINUTE_IN_SECONDS );
		$this->log_service->info( 'cron_sync', 'Video sync complete' );
	}

	// ── Heartbeat: update plugin metadata on backend ─────────────────────────

	private function heartbeat(): void {
		if ( ! SAS_License_Manager::is_active() ) {
			return;
		}

		SAS_Backend_Client::post( '/api/v1/websites/sync/', [
			'plugin_version'    => SAS_VERSION,
			'wordpress_version' => get_bloginfo( 'version' ),
		] );
	}

	// ── Delete Media Library files 48h after they publish ────────────────────

	/**
	 * The backend tracks which of this website's published videos are past
	 * their 48h retention window (source=plugin, wp_attachment_id set,
	 * media_deleted_at still null) but can't delete the file itself — it has
	 * no filesystem/DB access to this WordPress install. So: ask what's due,
	 * delete each attachment locally, then confirm back so the backend marks
	 * media_deleted_at and never offers those videos again. Videos scheduled
	 * from a pasted URL never get a wp_attachment_id, so they're never
	 * touched here.
	 */
	private function cleanup_wp_media(): void {
		if ( ! SAS_License_Manager::is_active() ) {
			return;
		}

		$result = SAS_Backend_Client::get( '/api/v1/videos/plugin/media-cleanup/' );
		if ( is_wp_error( $result ) ) {
			$this->log_service->warning( 'media_cleanup_check_failed', $result->get_error_message() );
			return;
		}

		$due = $result['videos'] ?? [];
		if ( ! $due ) {
			return;
		}

		$deleted_ids = [];
		foreach ( $due as $video ) {
			$attachment_id = (int) ( $video['wp_attachment_id'] ?? 0 );
			$video_id      = (int) ( $video['id'] ?? 0 );
			if ( ! $attachment_id || ! $video_id ) {
				continue;
			}
			// true = force-delete, skip Trash (bypasses MEDIA_TRASH so the
			// file is actually removed, not just hidden).
			if ( wp_delete_attachment( $attachment_id, true ) ) {
				$deleted_ids[] = $video_id;
			} else {
				// Already gone (e.g. deleted manually from the Media Library) —
				// still confirm it so the backend stops offering it.
				$deleted_ids[] = $video_id;
			}
		}

		if ( $deleted_ids ) {
			SAS_Backend_Client::post( '/api/v1/videos/plugin/media-cleanup/', [ 'ids' => $deleted_ids ] );
			$this->log_service->info(
				'media_cleanup',
				sprintf( '%d video file(s) deleted from the Media Library (48h post-publish).', count( $deleted_ids ) )
			);
		}
	}
}

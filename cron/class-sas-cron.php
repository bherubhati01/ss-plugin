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
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAS_Plugin {

	public function __construct() {}

	public function run(): void {
		// Initialise the backend client URL from the constant (if not already in DB).
		if ( ! get_option( 'sas_backend_url' ) && defined( 'SAS_BACKEND_URL' ) ) {
			SAS_Backend_Client::set_backend_url( SAS_BACKEND_URL );
		}

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_cron_hooks();
		$this->define_api_hooks();
	}

	private function load_dependencies(): void {
		if ( is_admin() ) {
			add_action( 'admin_init', [ new SAS_Admin(), 'handle_oauth_callback' ] );
		}
	}

	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin = new SAS_Admin();
		add_action( 'admin_menu', [ $admin, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $admin, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_sas_activate_license', [ $admin, 'handle_license_activation' ] );
		add_action( 'admin_post_sas_deactivate_license', [ $admin, 'handle_license_deactivation' ] );
		add_filter( 'admin_footer_text', [ $admin, 'admin_footer_text_links' ] );
	}

	private function define_cron_hooks(): void {
		$cron = new SAS_Cron();
		add_action( 'sas_cron_hook', [ $cron, 'run' ] );

		if ( ! wp_next_scheduled( 'sas_cron_hook' ) ) {
			wp_schedule_event( time(), 'five_minutes', 'sas_cron_hook' );
		}
	}

	private function define_api_hooks(): void {
		$api = new SAS_API();
		add_action( 'rest_api_init', [ $api, 'register_routes' ] );
	}
}

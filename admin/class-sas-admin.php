<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Admin {

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function add_admin_menu(): void {
        add_menu_page(
            __('Social Auto Scheduler', 'social-auto-scheduler'),
            __('Auto Scheduler', 'social-auto-scheduler'),
            'manage_options',
            'social-auto-scheduler',
            [$this, 'render_dashboard'],
            'dashicons-schedule',
            30
        );

        $pages = [
            ['sas-dashboard', __('Dashboard', 'social-auto-scheduler'), [$this, 'render_dashboard']],
            ['sas-videos',    __('Videos',    'social-auto-scheduler'), [$this, 'render_videos']],
            ['sas-calendar',  __('Calendar',  'social-auto-scheduler'), [$this, 'render_calendar']],
            ['sas-accounts',  __('Accounts',  'social-auto-scheduler'), [$this, 'render_accounts']],
            ['sas-settings',  __('Settings',  'social-auto-scheduler'), [$this, 'render_settings']],
            ['sas-logs',      __('Logs',      'social-auto-scheduler'), [$this, 'render_logs']],
            ['sas-license',   __('License',   'social-auto-scheduler'), [$this, 'render_license']],
        ];

        foreach ($pages as [$slug, $label, $cb]) {
            add_submenu_page('social-auto-scheduler', $label, $label, 'manage_options', $slug, $cb);
        }

        // Remove auto-added duplicate main menu item and replace with Dashboard
        remove_submenu_page('social-auto-scheduler', 'social-auto-scheduler');
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_admin_assets(string $hook): void {
        $sas_pages = [
            'toplevel_page_social-auto-scheduler',
            'auto-scheduler_page_sas-dashboard',
            'auto-scheduler_page_sas-videos',
            'auto-scheduler_page_sas-calendar',
            'auto-scheduler_page_sas-accounts',
            'auto-scheduler_page_sas-settings',
            'auto-scheduler_page_sas-logs',
        ];

        if (!in_array($hook, $sas_pages, true)) {
            return;
        }

        wp_enqueue_style('sas-admin-css', SAS_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.2');
        wp_enqueue_script('sas-admin-js', SAS_PLUGIN_URL . 'assets/js/admin.js', [], '1.0.2', true);

        wp_localize_script('sas-admin-js', 'sasData', [
            'apiUrl'    => rest_url('sas/v1'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'pluginUrl' => SAS_PLUGIN_URL,
            'adminUrl'  => admin_url('admin.php'),
            'strings'   => [
                'confirm_delete'    => __('Are you sure you want to delete this video?', 'social-auto-scheduler'),
                'confirm_bulk_del'  => __('Delete selected videos?', 'social-auto-scheduler'),
                'saving'            => __('Saving…', 'social-auto-scheduler'),
                'saved'             => __('Saved!', 'social-auto-scheduler'),
                'error'             => __('An error occurred.', 'social-auto-scheduler'),
                'upload_success'    => __('Video uploaded and scheduled!', 'social-auto-scheduler'),
                'no_selection'      => __('Please select at least one video.', 'social-auto-scheduler'),
                'schedule_success'  => __('Video scheduled!', 'social-auto-scheduler'),
                'connecting'        => __('Connecting…', 'social-auto-scheduler'),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // OAuth result handler (fires on admin_init)
    // The backend now handles the OAuth code exchange and redirects back here
    // with ?sas_connected=<platform> on success or ?sas_error=<code> on failure.
    // -------------------------------------------------------------------------

    public function handle_oauth_callback(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $connected = sanitize_key( $_GET['sas_connected'] ?? '' );
        $error     = sanitize_text_field( $_GET['sas_error'] ?? '' );

        if ( $connected ) {
            $label = $connected === 'youtube' ? 'YouTube' : 'Instagram';
            set_transient( 'sas_oauth_success', $connected, 60 );
            update_option( 'sas_oauth_debug', [
                'v'        => '1.0.3',
                'time'     => gmdate( 'Y-m-d H:i:s' ),
                'platform' => $connected,
                'step'     => "SUCCESS:{$label}",
            ] );
            return;
        }

        if ( $error ) {
            set_transient( 'sas_oauth_error', $error, 60 );
            update_option( 'sas_oauth_debug', [
                'v'    => '1.0.3',
                'time' => gmdate( 'Y-m-d H:i:s' ),
                'step' => "ERROR:{$error}",
            ] );
        }
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function render_dashboard(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/dashboard.php';
    }

    public function render_videos(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/videos.php';
    }

    public function render_calendar(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/calendar.php';
    }

    public function render_accounts(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/accounts.php';
    }

    public function render_settings(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/settings.php';
    }

    public function render_logs(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/logs.php';
    }

    public function render_license(): void {
        require_once SAS_PLUGIN_DIR . 'admin/templates/license.php';
    }

    // -------------------------------------------------------------------------
    // License activation / deactivation (admin-post.php handlers)
    // -------------------------------------------------------------------------

    public function handle_license_activation(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'social-auto-scheduler' ) );
        }

        check_admin_referer( 'sas_activate_license' );

        $token      = sanitize_text_field( $_POST['sas_license_token'] ?? '' );
        $backend_url = esc_url_raw( $_POST['sas_backend_url'] ?? '' );

        if ( $backend_url ) {
            SAS_Backend_Client::set_backend_url( $backend_url );
        }

        $result = SAS_License_Manager::activate( $token );

        if ( is_wp_error( $result ) ) {
            set_transient( 'sas_license_error', $result->get_error_message(), 30 );
        } else {
            set_transient( 'sas_license_success', __( 'License activated successfully.', 'social-auto-scheduler' ), 30 );
        }

        wp_redirect( admin_url( 'admin.php?page=sas-license' ) );
        exit;
    }

    public function handle_license_deactivation(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'social-auto-scheduler' ) );
        }

        check_admin_referer( 'sas_deactivate_license' );
        SAS_License_Manager::deactivate();
        set_transient( 'sas_license_success', __( 'License deactivated.', 'social-auto-scheduler' ), 30 );
        wp_redirect( admin_url( 'admin.php?page=sas-license' ) );
        exit;
    }
}

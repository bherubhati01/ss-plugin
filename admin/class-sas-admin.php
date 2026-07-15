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
            __('Soulitam Social', 'social-auto-scheduler'),
            __('Soulitam Social', 'social-auto-scheduler'),
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
            ['sas-support',   __('Support',   'social-auto-scheduler'), [$this, 'render_support']],
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

    /**
     * True on any Soulitam Social admin page. Slug-based (not hook-suffix
     * based) so it keeps working regardless of the top-level menu title —
     * see the note in enqueue_admin_assets().
     */
    private static function is_sas_admin_page(): bool {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $sas_slugs = [
            'social-auto-scheduler',
            'sas-dashboard',
            'sas-videos',
            'sas-calendar',
            'sas-accounts',
            'sas-settings',
            'sas-logs',
            'sas-support',
            'sas-license',
        ];
        return in_array($page, $sas_slugs, true);
    }

    public function enqueue_admin_assets(string $hook): void {
        // Matching on $_GET['page'] instead of the $hook suffix — WordPress
        // derives submenu hook suffixes from sanitize_title(menu_title), so
        // renaming the top-level menu title (e.g. the "Social Auto
        // Scheduler" → "Soulitam Social" rebrand) silently changes every
        // submenu's hook suffix too. A hardcoded 'auto-scheduler_page_*'
        // list here went stale the moment the title changed, and assets
        // stopped loading on every page except the raw toplevel one. The
        // page slugs below are never touched by branding changes.
        if (!self::is_sas_admin_page()) {
            return;
        }

        wp_enqueue_style('sas-admin-css', SAS_PLUGIN_URL . 'assets/css/admin.css', [], SAS_VERSION);
        wp_enqueue_script('sas-admin-js', SAS_PLUGIN_URL . 'assets/js/admin.js', [], SAS_VERSION, true);

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
    // Footer legal links (shown at the bottom of every plugin admin page)
    // -------------------------------------------------------------------------

    public function admin_footer_text_links( string $text ): string {
        if ( ! self::is_sas_admin_page() ) {
            return $text;
        }

        $frontend = untrailingslashit( SAS_FRONTEND_URL );
        $links    = [
            [ __( 'Privacy Policy', 'social-auto-scheduler' ), $frontend . '/privacy' ],
            [ __( 'Terms of Service', 'social-auto-scheduler' ), $frontend . '/terms' ],
            [ __( 'Data Deletion', 'social-auto-scheduler' ), $frontend . '/data-deletion' ],
        ];

        $rendered = [];
        foreach ( $links as [ $label, $url ] ) {
            $rendered[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
        }

        return 'Soulitam Social &nbsp;&middot;&nbsp; ' . implode( ' &nbsp;|&nbsp; ', $rendered );
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

    /**
     * When no active license, every plugin page shows the license gate popup
     * instead of its content. Returns true when the gate was rendered.
     */
    private function render_gate_if_unlicensed(): bool {
        if ( SAS_License_Manager::is_active() ) {
            return false;
        }
        require SAS_PLUGIN_DIR . 'admin/templates/license-gate.php';
        return true;
    }

    public function render_dashboard(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/dashboard.php';
    }

    public function render_videos(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/videos.php';
    }

    public function render_calendar(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/calendar.php';
    }

    public function render_accounts(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/accounts.php';
    }

    public function render_settings(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/settings.php';
    }

    public function render_logs(): void {
        if ( $this->render_gate_if_unlicensed() ) {
            return;
        }
        require_once SAS_PLUGIN_DIR . 'admin/templates/logs.php';
    }

    public function render_license(): void {
        // Always accessible — holds the advanced activation form and deactivation.
        require_once SAS_PLUGIN_DIR . 'admin/templates/license.php';
    }

    public function render_support(): void {
        // Always accessible — users need Support most when their license has
        // lapsed or something else is broken, so this never shows the gate.
        require_once SAS_PLUGIN_DIR . 'admin/templates/support.php';
    }

    // -------------------------------------------------------------------------
    // License activation / deactivation (admin-post.php handlers)
    // -------------------------------------------------------------------------

    public function handle_license_activation(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'social-auto-scheduler' ) );
        }

        check_admin_referer( 'sas_activate_license' );

        $token = sanitize_text_field( $_POST['sas_license_token'] ?? '' );

        $result = SAS_License_Manager::activate( $token );

        if ( is_wp_error( $result ) ) {
            set_transient( 'sas_license_error', $result->get_error_message(), 30 );
        } else {
            set_transient( 'sas_license_success', __( 'License activated successfully! Welcome aboard.', 'social-auto-scheduler' ), 30 );
        }

        // Send the user back to the page they activated from (the gate shows
        // on every plugin page); fall back to the license page.
        $referer = wp_get_referer();
        if ( $referer && strpos( $referer, 'page=sas-' ) !== false ) {
            wp_redirect( $referer );
        } else {
            wp_redirect( admin_url( 'admin.php?page=sas-license' ) );
        }
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

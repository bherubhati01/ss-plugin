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
    // OAuth callback (fires on admin_init)
    // -------------------------------------------------------------------------

    public function handle_oauth_callback(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page   = sanitize_key($_GET['page']      ?? '');
        $oauth  = sanitize_key($_GET['sas_oauth'] ?? '');
        $code   = sanitize_text_field($_GET['code']  ?? '');
        $state  = sanitize_text_field($_GET['state'] ?? '');
        $error  = sanitize_text_field($_GET['error'] ?? '');

        if ($page !== 'sas-accounts' || empty($oauth)) {
            return;
        }

        if ($error) {
            set_transient('sas_oauth_error', $error, 30);
            wp_redirect(admin_url('admin.php?page=sas-accounts'));
            exit;
        }

        if (empty($code)) {
            return;
        }

        try {
            if ($oauth === 'youtube') {
                $service = new SAS_Youtube_Service();
                $service->handle_callback($code, $state);
                set_transient('sas_oauth_success', 'youtube', 30);
            } elseif ($oauth === 'instagram') {
                $service = new SAS_Instagram_Service();
                $service->handle_callback($code, $state);
                set_transient('sas_oauth_success', 'instagram', 30);
            }
        } catch (Exception $e) {
            set_transient('sas_oauth_error', $e->getMessage(), 30);
        }

        wp_redirect(admin_url('admin.php?page=sas-accounts'));
        exit;
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
}

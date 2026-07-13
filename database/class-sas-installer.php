<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Installer {
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = [
            self::get_accounts_table_sql($wpdb, $charset_collate),
            self::get_videos_table_sql($wpdb, $charset_collate),
            self::get_logs_table_sql($wpdb, $charset_collate),
            self::get_queue_table_sql($wpdb, $charset_collate),
            self::get_settings_table_sql($wpdb, $charset_collate),
            self::get_platforms_table_sql($wpdb, $charset_collate),
        ];

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        update_option('sas_version', SAS_VERSION);
    }

    private static function get_accounts_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_accounts';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            account_name varchar(255) NOT NULL,
            access_token text,
            refresh_token text,
            token_expires_at datetime,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_platform (platform),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
    }

    private static function get_videos_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_videos';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            file_path varchar(500) NOT NULL,
            file_url varchar(500) NOT NULL,
            thumbnail_url varchar(500),
            title varchar(255) NOT NULL,
            description text,
            tags text,
            duration int(11) UNSIGNED,
            file_size bigint(20) UNSIGNED,
            platform varchar(50) NOT NULL,
            account_id bigint(20) UNSIGNED,
            status varchar(50) NOT NULL DEFAULT 'draft',
            publish_date datetime,
            published_at datetime,
            error_message text,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_publish_date (publish_date),
            KEY idx_platform (platform)
        ) $charset_collate;";
    }

    private static function get_logs_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_logs';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id bigint(20) UNSIGNED,
            account_id bigint(20) UNSIGNED,
            action varchar(255) NOT NULL,
            level varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_video_id (video_id),
            KEY idx_account_id (account_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
    }

    private static function get_queue_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_queue';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            video_id bigint(20) UNSIGNED NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'queued',
            attempts int(11) UNSIGNED NOT NULL DEFAULT 0,
            last_attempt_at datetime,
            next_attempt_at datetime,
            lock_key varchar(100),
            lock_expires_at datetime,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_video_id (video_id),
            KEY idx_status (status),
            KEY idx_next_attempt_at (next_attempt_at)
        ) $charset_collate;";
    }

    private static function get_settings_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_settings';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_user_key (user_id, setting_key)
        ) $charset_collate;";
    }

    private static function get_platforms_table_sql($wpdb, $charset_collate) {
        $table_name = $wpdb->prefix . 'sas_platforms';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(50) NOT NULL,
            label varchar(255) NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            config longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_name (name)
        ) $charset_collate;";
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('sas_cron_hook');
    }

    public static function uninstall(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'sas_accounts',
            $wpdb->prefix . 'sas_videos',
            $wpdb->prefix . 'sas_logs',
            $wpdb->prefix . 'sas_queue',
            $wpdb->prefix . 'sas_settings',
            $wpdb->prefix . 'sas_platforms',
        ];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Clear license token/status and backend JWTs — uninstalling must not
        // leave live credentials behind in wp_options.
        if (class_exists('SAS_License_Manager')) {
            SAS_License_Manager::clear_all();
        }
        if (class_exists('SAS_Backend_Client')) {
            SAS_Backend_Client::clear_tokens();
        }

        delete_option('sas_version');
        delete_option('sas_backend_url');
        delete_option('sas_oauth_debug');

        // Clean up temp/video files
        $upload_dir = wp_upload_dir();
        $dirs = ['sas-videos', 'sas-temp'];
        foreach ($dirs as $dir) {
            $path = $upload_dir['basedir'] . '/' . $dir;
            if (is_dir($path)) {
                array_map('unlink', glob($path . '/*') ?: []);
                rmdir($path);
            }
        }
    }
}

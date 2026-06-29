<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Autoloader {

    private static string $prefix = 'SAS_';

    private static array $base_dirs = [];

    public static function register(): void {
        self::$base_dirs = [
            SAS_PLUGIN_DIR . 'includes/',
            SAS_PLUGIN_DIR . 'admin/',
            SAS_PLUGIN_DIR . 'database/',
            SAS_PLUGIN_DIR . 'cron/',
            SAS_PLUGIN_DIR . 'api/',
            SAS_PLUGIN_DIR . 'services/',
            SAS_PLUGIN_DIR . 'platforms/',
            SAS_PLUGIN_DIR . 'helpers/',
        ];

        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void {
        if (strpos($class, self::$prefix) !== 0) {
            return;
        }

        // SAS_Youtube_Service → youtube-service
        $relative = strtolower(substr($class, strlen(self::$prefix)));
        $relative = str_replace('_', '-', $relative);

        foreach (self::$base_dirs as $base_dir) {
            // Pattern 1: /services/class-sas-youtube-service.php
            $file = $base_dir . 'class-sas-' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }

            // Pattern 2: /platforms/youtube/class-sas-youtube-service.php
            // Derive subdir from first segment (youtube-service → youtube)
            $parts  = explode('-', $relative);
            $subdir = $parts[0];
            $file   = $base_dir . $subdir . '/class-sas-' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }

            // Pattern 3: /platforms/youtube-service/class-sas-youtube-service.php (legacy)
            $file = $base_dir . $relative . '/class-sas-' . $relative . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}

SAS_Autoloader::register();

<?php
/**
 * Plugin Name: Social Auto Scheduler
 * Plugin URI: https://example.com/
 * Description: Automatically schedule and publish videos to YouTube and Instagram
 * Version: 1.0.2
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-auto-scheduler
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SAS_VERSION', '1.0.2');
define('SAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAS_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once SAS_PLUGIN_DIR . 'includes/class-sas-autoloader.php';
require_once SAS_PLUGIN_DIR . 'database/class-sas-installer.php';
require_once SAS_PLUGIN_DIR . 'includes/class-sas-plugin.php';
require_once SAS_PLUGIN_DIR . 'includes/updater.php';

// Register activation/deactivation/uninstall hooks
register_activation_hook(SAS_PLUGIN_BASENAME,   ['SAS_Installer', 'install']);
register_deactivation_hook(SAS_PLUGIN_BASENAME, ['SAS_Installer', 'deactivate']);
register_uninstall_hook(SAS_PLUGIN_BASENAME,    ['SAS_Installer', 'uninstall']);

function sas_cron_schedules($schedules) {
    $schedules['five_minutes'] = [
        'interval' => 300,
        'display' => __('Every 5 Minutes', 'social-auto-scheduler'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'sas_cron_schedules');

function sas_init_plugin() {
    $plugin = new SAS_Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'sas_init_plugin');

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Log_Service {

    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_DEBUG   = 'debug';

    public function log(string $action, string $message, string $level = self::LEVEL_INFO, array $context = [], ?int $video_id = null, ?int $account_id = null): void {
        global $wpdb;

        $row = [
            'action'  => substr($action, 0, 255),
            'level'   => $level,
            'message' => $message,
            'context' => !empty($context) ? json_encode($context) : null,
        ];

        if ($video_id)   $row['video_id']   = $video_id;
        if ($account_id) $row['account_id'] = $account_id;

        $wpdb->insert($wpdb->prefix . 'sas_logs', $row);
    }

    public function info(string $action, string $message, array $context = [], ?int $video_id = null, ?int $account_id = null): void {
        $this->log($action, $message, self::LEVEL_INFO, $context, $video_id, $account_id);
    }

    public function warning(string $action, string $message, array $context = [], ?int $video_id = null, ?int $account_id = null): void {
        $this->log($action, $message, self::LEVEL_WARNING, $context, $video_id, $account_id);
    }

    public function error(string $action, string $message, array $context = [], ?int $video_id = null, ?int $account_id = null): void {
        $this->log($action, $message, self::LEVEL_ERROR, $context, $video_id, $account_id);
    }

    public function debug(string $action, string $message, array $context = [], ?int $video_id = null, ?int $account_id = null): void {
        $this->log($action, $message, self::LEVEL_DEBUG, $context, $video_id, $account_id);
    }

    public function get_logs(?int $video_id = null, ?int $account_id = null, int $limit = 100, string $level = ''): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'sas_logs';
        $where  = '1=1';
        $params = [];

        if ($video_id) {
            $where   .= ' AND video_id = %d';
            $params[] = $video_id;
        }

        if ($account_id) {
            $where   .= ' AND account_id = %d';
            $params[] = $account_id;
        }

        if ($level) {
            $where   .= ' AND level = %s';
            $params[] = $level;
        }

        $params[] = max(1, min(1000, $limit));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d", ...$params),
            ARRAY_A
        ) ?: [];
    }
}

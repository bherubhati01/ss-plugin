<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Queue_Service {
    public function add($video_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        $wpdb->insert(
            $table_name,
            [
                'video_id' => $video_id,
                'status' => 'queued',
                'next_attempt_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    public function get_next($limit = 5) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        $now = current_time('mysql');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT q.* FROM $table_name q 
             INNER JOIN {$wpdb->prefix}sas_videos v ON q.video_id = v.id
             WHERE q.status = 'queued' 
             AND q.next_attempt_at <= %s 
             AND (q.lock_key IS NULL OR q.lock_expires_at < %s)
             ORDER BY q.next_attempt_at ASC 
             LIMIT %d",
            $now,
            $now,
            $limit
        ), ARRAY_A);
    }

    public function lock($queue_id, $lock_key) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        $lock_expires = date('Y-m-d H:i:s', time() + 300);

        return $wpdb->update(
            $table_name,
            [
                'status' => 'processing',
                'lock_key' => $lock_key,
                'lock_expires_at' => $lock_expires,
            ],
            ['id' => $queue_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function complete($queue_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        return $wpdb->update(
            $table_name,
            ['status' => 'completed'],
            ['id' => $queue_id],
            ['%s'],
            ['%d']
        );
    }

    public function fail($queue_id, $retry = true) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        $queue_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $queue_id), ARRAY_A);

        if (!$queue_item) {
            return false;
        }

        $attempts = $queue_item['attempts'] + 1;

        if ($retry && $attempts < 5) {
            $next_attempt = date('Y-m-d H:i:s', time() + (60 * $attempts * 5));
            return $wpdb->update(
                $table_name,
                [
                    'status' => 'queued',
                    'attempts' => $attempts,
                    'last_attempt_at' => current_time('mysql'),
                    'next_attempt_at' => $next_attempt,
                    'lock_key' => null,
                    'lock_expires_at' => null,
                ],
                ['id' => $queue_id],
                ['%s', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            return $wpdb->update(
                $table_name,
                ['status' => 'failed'],
                ['id' => $queue_id],
                ['%s'],
                ['%d']
            );
        }
    }

    public function release($queue_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_queue';
        return $wpdb->update(
            $table_name,
            [
                'lock_key' => null,
                'lock_expires_at' => null,
                'status' => 'queued',
            ],
            ['id' => $queue_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
}

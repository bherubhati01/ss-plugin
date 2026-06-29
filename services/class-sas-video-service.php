<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Video_Service {

    private const ALLOWED_ORDERBY = ['id', 'title', 'status', 'publish_date', 'created_at', 'file_size', 'duration'];
    private const ALLOWED_ORDER   = ['ASC', 'DESC'];

    public function create(array $data, ?int $user_id = null): int {
        global $wpdb;

        $user_id = $user_id ?? get_current_user_id();
        $table   = $wpdb->prefix . 'sas_videos';

        $wpdb->insert($table, [
            'user_id'       => $user_id,
            'file_path'     => $data['file_path'],
            'file_url'      => $data['file_url'],
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'tags'          => isset($data['tags']) ? (is_array($data['tags']) ? json_encode($data['tags']) : $data['tags']) : null,
            'duration'      => $data['duration'] ?? null,
            'file_size'     => $data['file_size'] ?? null,
            'platform'      => $data['platform'],
            'account_id'    => $data['account_id'] ?? null,
            'status'        => 'draft',
            'metadata'      => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function get(int $video_id, ?int $user_id = null): ?array {
        global $wpdb;

        $table = $wpdb->prefix . 'sas_videos';

        if ($user_id !== null) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND user_id = %d",
                $video_id,
                $user_id
            ), ARRAY_A) ?: null;
        }

        // Cron context: no user restriction
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $video_id
        ), ARRAY_A) ?: null;
    }

    public function get_all(?int $user_id = null, array $args = []): array {
        global $wpdb;

        $user_id = $user_id ?? get_current_user_id();
        $table   = $wpdb->prefix . 'sas_videos';

        $where  = 'WHERE user_id = %d';
        $params = [$user_id];

        if (!empty($args['status'])) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['platform'])) {
            $where   .= ' AND platform = %s';
            $params[] = $args['platform'];
        }

        if (!empty($args['search'])) {
            $where   .= ' AND (title LIKE %s OR description LIKE %s)';
            $like     = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Whitelist ORDER BY to prevent SQL injection
        $orderby = in_array($args['orderby'] ?? '', self::ALLOWED_ORDERBY, true)
            ? $args['orderby']
            : 'created_at';
        $order = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $limit  = max(1, min(200, (int) ($args['limit'] ?? 20)));
        $offset = max(0, (int) ($args['offset'] ?? 0));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            ...[...$params, $limit, $offset]
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function get_stats(?int $user_id = null): array {
        global $wpdb;

        $user_id = $user_id ?? get_current_user_id();
        $table   = $wpdb->prefix . 'sas_videos';
        $stats   = [];

        $statuses = ['draft', 'queued', 'scheduled', 'publishing', 'published', 'failed', 'cancelled'];
        foreach ($statuses as $status) {
            $stats[$status] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE user_id = %d AND status = %s",
                $user_id,
                $status
            ));
        }

        $stats['total'] = array_sum($stats);

        // Next scheduled video
        $next = $wpdb->get_row($wpdb->prepare(
            "SELECT publish_date, title FROM $table WHERE user_id = %d AND status = 'scheduled' AND publish_date >= NOW() ORDER BY publish_date ASC LIMIT 1",
            $user_id
        ), ARRAY_A);
        $stats['next_scheduled'] = $next;

        // Storage used
        $storage = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(file_size) FROM $table WHERE user_id = %d",
            $user_id
        ));
        $stats['storage_bytes'] = (int) $storage;
        $stats['storage_human'] = SAS_Helpers::format_bytes((int) $storage);

        return $stats;
    }

    public function update(int $video_id, array $data, ?int $user_id = null): bool {
        global $wpdb;

        $table        = $wpdb->prefix . 'sas_videos';
        $update_data  = [];

        $allowed = ['title', 'description', 'tags', 'thumbnail_url', 'status', 'publish_date', 'published_at', 'error_message', 'metadata', 'account_id'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update_data[$field] = $data[$field];
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $where = ['id' => $video_id];
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return (bool) $wpdb->update($table, $update_data, $where);
    }

    public function delete(int $video_id, ?int $user_id = null): bool {
        global $wpdb;

        $user_id = $user_id ?? get_current_user_id();
        $table   = $wpdb->prefix . 'sas_videos';

        $video = $this->get($video_id, $user_id);
        if ($video && !empty($video['file_path']) && file_exists($video['file_path'])) {
            unlink($video['file_path']);
        }

        return (bool) $wpdb->delete($table, ['id' => $video_id, 'user_id' => $user_id]);
    }

    public function bulk_delete(array $ids, int $user_id): int {
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->delete((int) $id, $user_id)) {
                $deleted++;
            }
        }
        return $deleted;
    }

    public function bulk_reschedule(array $ids, int $user_id): int {
        $scheduler = new SAS_Scheduler_Service();
        $scheduled = 0;
        foreach ($ids as $id) {
            try {
                $scheduler->schedule_video((int) $id, $user_id);
                $scheduled++;
            } catch (Exception $e) {
                // Continue with next video
            }
        }
        return $scheduled;
    }

    public function get_calendar_events(int $user_id, string $start, string $end): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'sas_videos';
        $rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, platform, status, publish_date, thumbnail_url FROM $table
             WHERE user_id = %d AND publish_date BETWEEN %s AND %s
             ORDER BY publish_date ASC",
            $user_id,
            $start,
            $end
        ), ARRAY_A);

        return array_map(function ($row) {
            return [
                'id'       => (int) $row['id'],
                'title'    => $row['title'],
                'platform' => $row['platform'],
                'status'   => $row['status'],
                'date'     => $row['publish_date'],
                'thumb'    => $row['thumbnail_url'],
            ];
        }, $rows ?: []);
    }
}

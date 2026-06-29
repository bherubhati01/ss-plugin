<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Cron {

    private SAS_Log_Service   $log_service;
    private SAS_Video_Service $video_service;
    private SAS_Queue_Service $queue_service;

    public function __construct() {
        $this->log_service   = new SAS_Log_Service();
        $this->video_service = new SAS_Video_Service();
        $this->queue_service = new SAS_Queue_Service();
    }

    public function run(): void {
        // Global cron lock – prevents concurrent execution
        $lock_key = 'sas_cron_running';
        if (get_transient($lock_key)) {
            return;
        }
        set_transient($lock_key, 1, 300);

        try {
            $this->move_due_videos_to_queue();
            $this->process_queue();
        } finally {
            delete_transient($lock_key);
        }
    }

    // -------------------------------------------------------------------------
    // Move scheduled videos whose publish_date has passed into the queue
    // -------------------------------------------------------------------------

    private function move_due_videos_to_queue(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sas_videos';
        $now   = current_time('mysql');

        $videos = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'scheduled' AND publish_date <= %s",
            $now
        ), ARRAY_A) ?: [];

        foreach ($videos as $video) {
            $wpdb->update($table, ['status' => 'queued'], ['id' => $video['id']]);
            $this->queue_service->add((int) $video['id']);
            $this->log_service->info('cron_queued', 'Video moved to queue', [], (int) $video['id']);
        }
    }

    // -------------------------------------------------------------------------
    // Process the queue
    // -------------------------------------------------------------------------

    private function process_queue(): void {
        $items    = $this->queue_service->get_next(3);
        $lock_key = uniqid('sas_item_', true);

        foreach ($items as $item) {
            // Per-item lock
            if (!$this->queue_service->lock((int) $item['id'], $lock_key)) {
                continue;
            }

            // Fetch video WITHOUT user restriction (cron has no current user)
            $video = $this->video_service->get((int) $item['video_id']);

            if (!$video) {
                $this->queue_service->release((int) $item['id']);
                continue;
            }

            try {
                $this->publish_video($video, $item);
                $this->queue_service->complete((int) $item['id']);
                $this->video_service->update((int) $video['id'], [
                    'status'       => 'published',
                    'published_at' => current_time('mysql'),
                    'error_message' => null,
                ]);
                $this->log_service->info('cron_published', 'Video published', [], (int) $video['id']);
            } catch (Throwable $e) {
                $this->log_service->error('cron_publish_failed', $e->getMessage(), [], (int) $video['id']);
                $this->queue_service->fail((int) $item['id']);
                $this->video_service->update((int) $video['id'], [
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function publish_video(array $video, array $queue_item): void {
        $this->video_service->update((int) $video['id'], ['status' => 'publishing']);

        $platform = strtolower($video['platform']);
        $service  = $this->get_platform_service($platform);

        if (!$service) {
            throw new RuntimeException(sprintf(__('No service found for platform: %s', 'social-auto-scheduler'), $platform));
        }

        $service->publish_video($video);
    }

    private function get_platform_service(string $platform): ?object {
        $map = [
            'youtube'   => SAS_Youtube_Service::class,
            'instagram' => SAS_Instagram_Service::class,
        ];

        $class = $map[$platform] ?? null;
        if ($class && class_exists($class)) {
            return new $class();
        }

        return null;
    }
}

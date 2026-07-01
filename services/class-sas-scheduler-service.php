<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Scheduler_Service {
    private $settings_service;

    public function __construct() {
        $this->settings_service = new SAS_Settings_Service();
    }

    public function get_next_available_date($user_id = null) {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $upload_time     = $this->settings_service->get('upload_time', $user_id, '19:00');
        $uploads_per_day = (int) $this->settings_service->get('uploads_per_day', $user_id, 1);
        $weekdays        = $this->settings_service->get('weekdays', $user_id, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);

        $timezone_obj = wp_timezone();
        $now          = new DateTime('now', $timezone_obj);

        $last_scheduled = $this->get_last_scheduled_date($user_id, $timezone_obj);

        // If a future-scheduled video exists, start searching from the day after it
        // so new videos land on their own day.
        // If no future-scheduled video exists (none at all, or all are in the past),
        // start from today — the $candidate > $now check below skips today if the
        // upload time has already passed.
        if ($last_scheduled && $last_scheduled > $now) {
            $search_from = (clone $last_scheduled)->modify('+1 day')->setTime(0, 0, 0);
        } else {
            $search_from = (clone $now)->setTime(0, 0, 0);
        }

        while (true) {
            if ($this->is_valid_weekday($search_from, $weekdays)) {
                $candidate   = $this->apply_time(clone $search_from, $upload_time);
                $slots_taken = (int) $this->get_uploads_for_date($search_from, $user_id);

                if ($candidate > $now && $slots_taken < $uploads_per_day) {
                    return $candidate;
                }
            }
            $search_from->modify('+1 day');
        }
    }

    private function get_last_scheduled_date($user_id, $timezone_obj) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_videos';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(publish_date) FROM $table_name WHERE user_id = %d AND status IN ('queued', 'scheduled', 'published')",
            $user_id
        ));

        if ($result) {
            return new DateTime($result, $timezone_obj);
        }

        return null;
    }

    private function get_uploads_for_date($date, $user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'sas_videos';
        $date_start = $date->format('Y-m-d 00:00:00');
        $date_end = $date->format('Y-m-d 23:59:59');

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND publish_date BETWEEN %s AND %s AND status IN ('queued', 'scheduled', 'published')",
            $user_id,
            $date_start,
            $date_end
        ));
    }

    private function is_valid_weekday($date, $weekdays) {
        // $date->format('D') returns 'Mon','Tue',... – lowercase to match stored values
        $day = strtolower($date->format('D')); // e.g. 'mon'

        if (!is_array($weekdays)) {
            $weekdays = ['mon','tue','wed','thu','fri','sat','sun'];
        }

        return in_array($day, array_map('strtolower', $weekdays), true);
    }

    private function apply_time($date, $time) {
        list($hours, $minutes) = explode(':', $time);
        $date->setTime((int)$hours, (int)$minutes, 0);
        return $date;
    }

    public function schedule_video($video_id, $user_id = null) {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $next_date = $this->get_next_available_date($user_id);

        $table_name = $wpdb->prefix . 'sas_videos';
        $wpdb->update(
            $table_name,
            [
                'status' => 'scheduled',
                'publish_date' => $next_date->format('Y-m-d H:i:s'),
            ],
            ['id' => $video_id, 'user_id' => $user_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        return $next_date;
    }
}

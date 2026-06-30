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

        $upload_time = $this->settings_service->get('upload_time', $user_id, '19:00');
        $uploads_per_day = $this->settings_service->get('uploads_per_day', $user_id, 1);
        $weekdays = $this->settings_service->get('weekdays', $user_id, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);

        $timezone_obj = wp_timezone();
        $now = new DateTime('now', $timezone_obj);
        $current_date = (clone $now)->setTime(0, 0, 0);

        $last_scheduled = $this->get_last_scheduled_date($user_id, $timezone_obj);
        if ($last_scheduled) {
            $current_date = (clone $last_scheduled)->setTime(0, 0, 0);
        }

        $todays_uploads = $this->get_uploads_for_date($current_date, $user_id);

        if ($todays_uploads < $uploads_per_day && $last_scheduled && $last_scheduled >= $now) {
            $next_date = clone $last_scheduled;
            $next_date->modify('+1 second');
            if ($this->is_valid_weekday($next_date, $weekdays)) {
                return $this->apply_time($next_date, $upload_time);
            }
        }

        $start_date = $last_scheduled ? (clone $last_scheduled)->modify('+1 day') : $current_date;

        while (true) {
            if ($this->is_valid_weekday($start_date, $weekdays)) {
                $date_uploads = $this->get_uploads_for_date($start_date, $user_id);
                if ($date_uploads < $uploads_per_day) {
                    return $this->apply_time($start_date, $upload_time);
                }
            }
            $start_date->modify('+1 day');
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

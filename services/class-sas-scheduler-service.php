<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Scheduler_Service {
    private $settings_service;

    public function __construct() {
        $this->settings_service = new SAS_Settings_Service();
    }

    /**
     * Find the next available publish slot for the given user and platform.
     *
     * Rules:
     *  - Slots per day are defined by upload_times[] (one time per slot).
     *  - uploads_per_day caps how many slots per platform per day are used.
     *  - Each platform is scheduled independently (YouTube and Instagram fill
     *    their own slots and don't share counts with each other).
     *  - A slot is available when it is in the future AND not already taken by
     *    another video on the same platform on the same day.
     *  - If today's slots are all past or full, search forward day by day.
     */
    /**
     * Hard bound on how many days forward we'll search. Without this, a
     * misconfigured account (all weekdays unchecked, or uploads_per_day
     * saved as 0 via a direct REST call that bypasses the HTML min="1")
     * makes get_next_available_date() loop forever and hang the PHP worker.
     */
    private const MAX_SEARCH_DAYS = 365;

    public function get_next_available_date($user_id = null, string $platform = ''): DateTime {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $upload_times    = $this->get_upload_times($user_id);
        $uploads_per_day = min(
            max(1, (int) $this->settings_service->get('uploads_per_day', $user_id, 1)),
            count($upload_times)
        );
        $weekdays = $this->settings_service->get(
            'weekdays', $user_id, ['mon','tue','wed','thu','fri','sat','sun']
        );
        if (!is_array($weekdays) || empty($weekdays)) {
            // No active days configured — fall back to every day rather than
            // hanging forever with zero valid days to schedule into.
            $weekdays = ['mon','tue','wed','thu','fri','sat','sun'];
        }

        $timezone_obj = wp_timezone();
        $now          = new DateTime('now', $timezone_obj);
        $search_from  = (clone $now)->setTime(0, 0, 0);

        for ($day = 0; $day < self::MAX_SEARCH_DAYS; $day++) {
            if ($this->is_valid_weekday($search_from, $weekdays)) {
                $taken_times = $this->get_scheduled_times_for_day($search_from, $user_id, $platform);

                if (count($taken_times) < $uploads_per_day) {
                    foreach ($upload_times as $slot_time) {
                        if (in_array($slot_time, $taken_times, true)) {
                            continue; // Slot already used on this day
                        }
                        $candidate = $this->apply_time(clone $search_from, $slot_time);
                        if ($candidate > $now) {
                            return $candidate;
                        }
                    }
                }
            }
            $search_from->modify('+1 day');
        }

        // Should be unreachable now that $weekdays and $uploads_per_day are
        // always sane, but never hang the request even if some future change
        // reintroduces an all-slots-full scenario.
        throw new RuntimeException(
            __('Could not find an available publish slot within the next year. Check your Schedule Settings.', 'social-auto-scheduler')
        );
    }

    /**
     * Schedule a video: looks up its platform from the DB, finds the next
     * available slot for that platform, and writes the publish_date.
     */
    public function schedule_video($video_id, $user_id = null): DateTime {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $table    = $wpdb->prefix . 'sas_videos';
        $platform = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT platform FROM $table WHERE id = %d AND user_id = %d",
            $video_id, $user_id
        ));

        $next_date = $this->get_next_available_date($user_id, $platform);

        $wpdb->update(
            $table,
            [
                'status'       => 'scheduled',
                'publish_date' => $next_date->format('Y-m-d H:i:s'),
            ],
            ['id' => $video_id, 'user_id' => $user_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        return $next_date;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the ordered list of upload times for this user.
     * Falls back to the legacy single upload_time setting if upload_times is not set.
     */
    private function get_upload_times(int $user_id): array {
        $times = $this->settings_service->get('upload_times', $user_id, null);

        if (is_array($times) && !empty($times)) {
            return array_values(array_filter($times));
        }

        // Legacy single-time fallback
        $single = (string) $this->settings_service->get('upload_time', $user_id, '19:00');
        return [$single ?: '19:00'];
    }

    /**
     * Return the HH:MM times already scheduled for a given platform on a given
     * day. Uses DATE_FORMAT so the result always matches the HH:MM format that
     * upload_times stores.
     */
    private function get_scheduled_times_for_day(DateTime $date, int $user_id, string $platform): array {
        global $wpdb;

        $table      = $wpdb->prefix . 'sas_videos';
        $date_start = $date->format('Y-m-d 00:00:00');
        $date_end   = $date->format('Y-m-d 23:59:59');

        if ($platform) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DATE_FORMAT(publish_date, '%%H:%%i') FROM $table
                 WHERE user_id = %d AND platform = %s
                   AND publish_date BETWEEN %s AND %s
                   AND status IN ('queued','scheduled','published')",
                $user_id, $platform, $date_start, $date_end
            ));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DATE_FORMAT(publish_date, '%%H:%%i') FROM $table
                 WHERE user_id = %d
                   AND publish_date BETWEEN %s AND %s
                   AND status IN ('queued','scheduled','published')",
                $user_id, $date_start, $date_end
            ));
        }

        return $rows ?: [];
    }

    private function is_valid_weekday(DateTime $date, $weekdays): bool {
        if (!is_array($weekdays)) {
            $weekdays = ['mon','tue','wed','thu','fri','sat','sun'];
        }
        return in_array(strtolower($date->format('D')), array_map('strtolower', $weekdays), true);
    }

    private function apply_time(DateTime $date, string $time): DateTime {
        [$hours, $minutes] = explode(':', $time);
        $date->setTime((int) $hours, (int) $minutes, 0);
        return $date;
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_API {

    public function register_routes(): void {
        $ns = 'sas/v1';

        // --- Videos ---
        register_rest_route($ns, '/videos', [
            ['methods' => 'GET',  'callback' => [$this, 'get_videos'],  'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'create_video'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/videos/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [$this, 'get_video'],    'permission_callback' => [$this, 'auth']],
            ['methods' => 'PUT',    'callback' => [$this, 'update_video'], 'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_video'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/videos/(?P<id>\d+)/schedule', [
            ['methods' => 'POST', 'callback' => [$this, 'schedule_video'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/videos/(?P<id>\d+)/publish-now', [
            ['methods' => 'POST', 'callback' => [$this, 'publish_now'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/videos/bulk', [
            ['methods' => 'POST', 'callback' => [$this, 'bulk_action'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Upload ---
        register_rest_route($ns, '/upload', [
            ['methods' => 'POST', 'callback' => [$this, 'upload_video'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/upload/init', [
            ['methods' => 'POST', 'callback' => [$this, 'upload_init'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/upload/chunk', [
            ['methods' => 'POST', 'callback' => [$this, 'upload_chunk'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/upload/finalize/(?P<upload_id>[a-zA-Z0-9_.\-]+)', [
            ['methods' => 'POST', 'callback' => [$this, 'upload_finalize'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/upload/status/(?P<upload_id>[a-zA-Z0-9_.\-]+)', [
            ['methods' => 'GET', 'callback' => [$this, 'upload_status'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Stats & Calendar ---
        register_rest_route($ns, '/stats', [
            ['methods' => 'GET', 'callback' => [$this, 'get_stats'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/calendar', [
            ['methods' => 'GET', 'callback' => [$this, 'get_calendar'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Settings ---
        register_rest_route($ns, '/settings', [
            ['methods' => 'GET',  'callback' => [$this, 'get_settings'],  'permission_callback' => [$this, 'auth']],
            ['methods' => 'POST', 'callback' => [$this, 'save_settings'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Notifications ---
        register_rest_route($ns, '/notifications', [
            ['methods' => 'GET', 'callback' => [$this, 'get_notifications'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Accounts ---
        register_rest_route($ns, '/accounts', [
            ['methods' => 'GET', 'callback' => [$this, 'get_accounts'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/accounts/(?P<id>\d+)', [
            ['methods' => 'DELETE', 'callback' => [$this, 'delete_account'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- OAuth ---
        register_rest_route($ns, '/oauth/youtube/url', [
            ['methods' => 'GET', 'callback' => [$this, 'youtube_oauth_url'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/oauth/instagram/url', [
            ['methods' => 'GET', 'callback' => [$this, 'instagram_oauth_url'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Logs ---
        register_rest_route($ns, '/logs', [
            ['methods' => 'GET',    'callback' => [$this, 'get_logs'],   'permission_callback' => [$this, 'auth']],
            ['methods' => 'DELETE', 'callback' => [$this, 'clear_logs'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Queue ---
        register_rest_route($ns, '/queue', [
            ['methods' => 'GET', 'callback' => [$this, 'get_queue'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Next available date ---
        register_rest_route($ns, '/scheduler/next-date', [
            ['methods' => 'GET', 'callback' => [$this, 'get_next_date'], 'permission_callback' => [$this, 'auth']],
        ]);

        // --- Contact / Support ---
        register_rest_route($ns, '/contact', [
            ['methods' => 'POST', 'callback' => [$this, 'submit_contact'], 'permission_callback' => [$this, 'auth']],
        ]);
    }

    // =========================================================================
    // Permission
    // =========================================================================

    public function auth(): bool {
        return current_user_can('manage_options');
    }

    // =========================================================================
    // Videos
    // =========================================================================
    //
    // Videos live entirely on the backend now (the plugin never stores a
    // local copy — see SAS_Upload_Service). Every handler below proxies the
    // backend's plugin-scoped video endpoints and adapts field names to the
    // shape assets/js/admin.js expects (title/publish_date/thumbnail_url/...).

    /**
     * Convert a backend VideoSerializer object into the local admin.js shape.
     */
    private static function map_backend_video(array $v): array {
        $publish_date = '';
        if (!empty($v['scheduled_at'])) {
            try {
                $dt = new DateTime($v['scheduled_at']);
                $dt->setTimezone(wp_timezone());
                $publish_date = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $publish_date = '';
            }
        }
        $platforms    = $v['platforms'] ?? ($v['platform'] ? [$v['platform']] : []);
        $content_type = $v['content_type'] ?? 'reel';

        return [
            'id'            => $v['id'],
            'title'         => $v['caption'] ?: (
                'story' === $content_type
                    ? __('Instagram Story', 'social-auto-scheduler')
                    : __('Untitled video', 'social-auto-scheduler')
            ),
            'description'   => $v['description'] ?? '',
            'tags'          => wp_json_encode($v['tags'] ?? []),
            'platform'      => $platforms[0] ?? 'youtube',
            'platforms'     => $platforms,
            'content_type'  => $content_type,
            'status'        => $v['status'] ?? 'draft',
            'publish_date'  => $publish_date,
            'thumbnail_url' => $v['thumbnail_url'] ?? '',
            'duration'      => 0,   // not tracked backend-side (no local file)
            'file_size'     => 0,   // not tracked backend-side (no local file)
            'error_message' => $v['error_message'] ?? '',
        ];
    }

    public function get_videos(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $limit  = max(1, min(200, absint($request->get_param('limit') ?? 20)));
        $offset = max(0, absint($request->get_param('offset') ?? 0));

        $params = [
            'status'    => sanitize_text_field($request->get_param('status') ?? ''),
            'platform'  => sanitize_text_field($request->get_param('platform') ?? ''),
            'search'    => sanitize_text_field($request->get_param('search') ?? ''),
            'page'      => (int) floor($offset / $limit) + 1,
            'page_size' => $limit,
        ];
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null);

        $result = SAS_Backend_Client::get('/api/v1/videos/plugin/', $params);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = array_map([self::class, 'map_backend_video'], $result['results'] ?? []);
        return new WP_REST_Response($items, 200);
    }

    public function get_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id     = absint($request->get_param('id'));
        $result = SAS_Backend_Client::get("/api/v1/videos/plugin/{$id}/");
        if (is_wp_error($result)) {
            return new WP_Error('not_found', __('Video not found.', 'social-auto-scheduler'), ['status' => 404]);
        }
        return new WP_REST_Response(self::map_backend_video($result), 200);
    }

    public function create_video(WP_REST_Request $request): WP_REST_Response {
        // Dead route — videos are created exclusively through the /upload*
        // endpoints (SAS_Upload_Service), never via a raw POST /videos.
        return new WP_REST_Response(
            ['success' => false, 'message' => __('Use the upload endpoints to add a video.', 'social-auto-scheduler')],
            400
        );
    }

    public function update_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id   = absint($request->get_param('id'));
        $data = $request->get_json_params() ?? [];

        $body = [];
        if (isset($data['title']))       $body['caption']     = sanitize_text_field($data['title']);
        if (isset($data['description'])) $body['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['tags']))        $body['tags']         = SAS_Helpers::sanitize_tags($data['tags']);
        if (!empty($data['publish_date'])) {
            // publish_date arrives as WP-local 'Y-m-d H:i:s' — convert to ISO 8601.
            try {
                $dt = new DateTime(sanitize_text_field($data['publish_date']), wp_timezone());
                $body['scheduled_at'] = $dt->format('c');
            } catch (Exception $e) {
                return new WP_Error('invalid_date', __('Invalid publish date.', 'social-auto-scheduler'), ['status' => 400]);
            }
        }
        // Note: platform is intentionally not editable post-creation (the
        // edit form's platform field is disabled) — targets are fixed at
        // upload time.

        $result = SAS_Backend_Client::patch("/api/v1/videos/plugin/{$id}/", $body);
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(['success' => true], 200);
    }

    public function delete_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id     = absint($request->get_param('id'));
        $result = SAS_Backend_Client::delete("/api/v1/videos/plugin/{$id}/");
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Schedule a single draft/failed video into the next available slot.
     * Uses the bulk/schedule endpoint with one ID — it's the only backend
     * route that both sets scheduled_at AND flips status to 'scheduled'
     * (a plain PATCH of scheduled_at would leave status untouched and the
     * Celery beat would never pick the video up).
     */
    public function schedule_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = absint($request->get_param('id'));
        try {
            $scheduler = new SAS_Scheduler_Service();
            $date      = $scheduler->get_next_available_date();
        } catch (Exception $e) {
            return new WP_Error('schedule_error', $e->getMessage(), ['status' => 500]);
        }

        $result = SAS_Backend_Client::post('/api/v1/videos/plugin/bulk/schedule/', [
            'ids'          => [$id],
            'scheduled_at' => $date->format('c'),
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true, 'publish_date' => $date->format('Y-m-d H:i:s')], 200);
    }

    public function bulk_action(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params() ?? [];
        $action = sanitize_key($data['action'] ?? '');
        $ids    = array_values(array_filter(array_map('absint', $data['ids'] ?? [])));

        if (empty($ids)) {
            return new WP_Error('no_ids', __('No video IDs provided.', 'social-auto-scheduler'), ['status' => 400]);
        }

        switch ($action) {
            case 'delete':
                $result = SAS_Backend_Client::post('/api/v1/videos/plugin/bulk/delete/', ['ids' => $ids]);
                if (is_wp_error($result)) {
                    return $result;
                }
                return new WP_REST_Response(['success' => true, 'affected' => $result['deleted'] ?? count($ids)], 200);

            case 'reschedule':
            case 'schedule':
                // Give each video its own staggered slot rather than piling
                // them all onto one timestamp.
                $scheduler = new SAS_Scheduler_Service();
                $count     = 0;
                foreach ($ids as $id) {
                    try {
                        $date = $scheduler->get_next_available_date();
                    } catch (Exception $e) {
                        break; // no more slots available — stop, keep what succeeded
                    }
                    $result = SAS_Backend_Client::post('/api/v1/videos/plugin/bulk/schedule/', [
                        'ids'          => [$id],
                        'scheduled_at' => $date->format('c'),
                    ]);
                    if (!is_wp_error($result)) {
                        $count++;
                    }
                }
                return new WP_REST_Response(['success' => true, 'affected' => $count], 200);

            case 'cancel':
                $count = 0;
                foreach ($ids as $id) {
                    $result = SAS_Backend_Client::delete("/api/v1/videos/plugin/{$id}/");
                    if (!is_wp_error($result)) {
                        $count++;
                    }
                }
                return new WP_REST_Response(['success' => true, 'affected' => $count], 200);

            default:
                return new WP_Error('unknown_action', __('Unknown bulk action.', 'social-auto-scheduler'), ['status' => 400]);
        }
    }

    // =========================================================================
    // Upload
    // =========================================================================

    public function upload_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $files = $request->get_file_params();
        if (empty($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('No file uploaded or upload error.', 'social-auto-scheduler'), ['status' => 400]);
        }

        // Accept 'platforms[]' array or legacy 'platform' string
        $raw_platforms = $request->get_param('platforms');
        if (!$raw_platforms) {
            $raw_platforms = $request->get_param('platform') ?? 'youtube';
        }

        try {
            $platforms    = SAS_Upload_Service::sanitize_platforms($raw_platforms);
            $content_type = SAS_Upload_Service::sanitize_content_type($request->get_param('content_type') ?? 'reel');
            $service      = new SAS_Upload_Service();

            $scheduler = new SAS_Scheduler_Service();
            $when      = $scheduler->get_next_available_date(null, $platforms[0] ?? 'youtube');

            // One backend video with a target per selected platform.
            $video = $service->upload_and_register($files['file'], [
                'platforms'    => $platforms,
                'content_type' => $content_type,
                // Stories don't support captions — see SAS_Upload_Service::send_to_backend().
                'caption'      => 'story' === $content_type ? '' : pathinfo($files['file']['name'], PATHINFO_FILENAME),
                'scheduled_at' => $when->format('c'),
            ]);

            return new WP_REST_Response([
                'videos' => [
                    ['id' => $video['id'] ?? 0, 'publish_date' => $when->format('Y-m-d H:i:s')],
                ],
            ], 201);
        } catch (\Throwable $e) {
            return new WP_Error('upload_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function upload_init(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params() ?? [];
        try {
            $service = new SAS_Upload_Service();
            $result  = $service->init_upload([
                'file_name'    => sanitize_file_name($data['file_name'] ?? 'video.mp4'),
                'file_size'    => absint($data['file_size'] ?? 0),
                'platforms'    => $data['platforms'] ?? $data['platform'] ?? 'youtube',
                'content_type' => $data['content_type'] ?? 'reel',
            ]);
            return new WP_REST_Response($result, 200);
        } catch (\Throwable $e) {
            return new WP_Error('upload_init_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function upload_chunk(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $upload_id   = sanitize_text_field($request->get_param('upload_id') ?? '');
        $chunk_index = absint($request->get_param('chunk_index') ?? 0);

        $files = $request->get_file_params();
        if (empty($files['chunk']) || $files['chunk']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('chunk_error', __('Chunk upload error.', 'social-auto-scheduler'), ['status' => 400]);
        }

        try {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $chunk_data = file_get_contents($files['chunk']['tmp_name']);
            $service    = new SAS_Upload_Service();
            $result     = $service->receive_chunk($upload_id, $chunk_index, $chunk_data);
            return new WP_REST_Response($result, 200);
        } catch (\Throwable $e) {
            return new WP_Error('chunk_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function upload_finalize(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $upload_id = sanitize_text_field($request->get_param('upload_id') ?? '');

        try {
            $service = new SAS_Upload_Service();
            // Assembles the file into the Media Library, schedules it for the
            // next available slot, and registers it with the backend.
            $result = $service->finalize_upload($upload_id);
            return new WP_REST_Response($result, 201);
        } catch (\Throwable $e) {
            return new WP_Error('finalize_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function publish_now(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = absint($request->get_param('id'));

        // Publishing is driven by the backend's Celery worker now — dispatch
        // through its publish endpoint instead of the local wp_sas_queue
        // table (nothing reads that table anymore since the cron rewrite).
        $result = SAS_Backend_Client::post("/api/v1/videos/plugin/{$id}/publish/");
        if (is_wp_error($result)) {
            return $result;
        }

        (new SAS_Log_Service())->info('publish_now', 'Video dispatched for immediate publishing', [], $id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Video queued — it will publish within a few minutes.', 'social-auto-scheduler'),
        ], 200);
    }

    public function upload_status(WP_REST_Request $request): WP_REST_Response {
        $upload_id = sanitize_text_field($request->get_param('upload_id') ?? '');
        $service   = new SAS_Upload_Service();
        return new WP_REST_Response($service->get_status($upload_id), 200);
    }

    // =========================================================================
    // Stats & Calendar
    // =========================================================================

    public function get_stats(): WP_REST_Response|WP_Error {
        $result = SAS_Backend_Client::get('/api/v1/videos/plugin/stats/');
        if (is_wp_error($result)) {
            return $result;
        }

        $next = null;
        if (!empty($result['next_scheduled'])) {
            $n = $result['next_scheduled'];
            $publish_date = '';
            try {
                $dt = new DateTime($n['scheduled_at']);
                $dt->setTimezone(wp_timezone());
                $publish_date = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // leave blank
            }
            $next = ['title' => $n['caption'] ?: __('Untitled video', 'social-auto-scheduler'), 'publish_date' => $publish_date];
        }

        return new WP_REST_Response([
            'total'          => $result['total'] ?? 0,
            'draft'          => $result['draft'] ?? 0,
            'scheduled'      => $result['scheduled'] ?? 0,
            'queued'         => $result['queued'] ?? 0,
            'publishing'     => $result['publishing'] ?? 0,
            'published'      => $result['published'] ?? 0,
            'failed'         => $result['failed'] ?? 0,
            'next_scheduled' => $next,
            'storage_bytes'  => 0,
            'storage_human'  => __('N/A (hosted on your server)', 'social-auto-scheduler'),
        ], 200);
    }

    // =========================================================================
    // Notifications
    // =========================================================================

    /**
     * Announcements the backend admin has broadcast to this website's
     * owning user, flagged show_on_plugin — same underlying Notification
     * rows the dashboard sees, filtered to a different flag.
     */
    public function get_notifications(): WP_REST_Response|WP_Error {
        $result = SAS_Backend_Client::get('/api/v1/notifications/plugin/', ['page_size' => 10]);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = array_map(static function ($n) {
            return [
                'id'         => $n['id'] ?? 0,
                'type'       => $n['type'] ?? 'info',
                'title'      => $n['title'] ?? '',
                'message'    => $n['message'] ?? '',
                'image_url'  => $n['image_url'] ?? null,
                'created_at' => $n['created_at'] ?? '',
            ];
        }, $result['results'] ?? []);

        return new WP_REST_Response($items, 200);
    }

    public function get_calendar(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $start = sanitize_text_field($request->get_param('start') ?? gmdate('Y-m-01'));
        $end   = sanitize_text_field($request->get_param('end')   ?? gmdate('Y-m-t'));

        // Backend expects full ISO datetimes for the range filter.
        $result = SAS_Backend_Client::get('/api/v1/videos/plugin/calendar/', [
            'start' => $start . 'T00:00:00Z',
            'end'   => $end . 'T23:59:59Z',
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        $events = array_map(function ($ev) {
            $date = '';
            try {
                $dt = new DateTime($ev['scheduled_at']);
                $dt->setTimezone(wp_timezone());
                $date = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // leave blank
            }
            return [
                'id'       => $ev['id'],
                'title'    => $ev['caption'] ?: __('Untitled video', 'social-auto-scheduler'),
                'platform' => $ev['platform'] ?? 'youtube',
                'status'   => $ev['status'] ?? 'scheduled',
                'date'     => $date,
                'thumb'    => $ev['thumbnail_url'] ?? '',
            ];
        }, is_array($result) ? $result : []);

        return new WP_REST_Response($events, 200);
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function get_settings(): WP_REST_Response {
        $service  = new SAS_Settings_Service();
        $settings = $service->get_all();
        // Never expose encrypted secrets
        unset($settings['youtube_client_secret_enc'], $settings['instagram_app_secret_enc']);
        return new WP_REST_Response($settings, 200);
    }

    public function save_settings(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $service  = new SAS_Settings_Service();
        $data     = $request->get_json_params() ?? [];

        $safe_keys = [
            'upload_time', 'upload_times', 'uploads_per_day', 'weekdays',
            'default_description', 'default_tags', 'youtube_client_id',
            'youtube_category', 'youtube_privacy', 'instagram_app_id',
            'instagram_config_id',
        ];

        foreach ($safe_keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = is_array($data[$key])
                ? array_map('sanitize_text_field', $data[$key])
                : sanitize_text_field($data[$key]);

            // Guard the scheduler's search bounds: uploads_per_day must be at
            // least 1 and at least one weekday must remain active, otherwise
            // SAS_Scheduler_Service::get_next_available_date() has no valid
            // slot to find. (The Settings page HTML also enforces this, but
            // this REST endpoint is reachable directly.)
            if ($key === 'uploads_per_day') {
                $val = (string) max(1, min(15, (int) $val));
            }
            if ($key === 'weekdays' && (!is_array($val) || empty($val))) {
                return new WP_Error(
                    'invalid_weekdays',
                    __('At least one active day is required.', 'social-auto-scheduler'),
                    ['status' => 400]
                );
            }

            $service->set($key, $val);
        }

        // Secrets encrypted before storage
        if (!empty($data['youtube_client_secret'])) {
            $service->set('youtube_client_secret_enc', SAS_Token_Service::encrypt(sanitize_text_field($data['youtube_client_secret'])));
        }
        if (!empty($data['instagram_app_secret'])) {
            $service->set('instagram_app_secret_enc', SAS_Token_Service::encrypt(sanitize_text_field($data['instagram_app_secret'])));
        }

        return new WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // Accounts
    // =========================================================================

    public function get_accounts(): WP_REST_Response {
        // Accounts are connected from the frontend dashboard and stored in the
        // backend — the backend is the source of truth for this website.
        $result = SAS_Backend_Client::get('/api/v1/social-accounts/plugin/');
        if (!is_wp_error($result) && is_array($result)) {
            return new WP_REST_Response(array_values($result), 200);
        }
        // Offline fallback: any legacy locally-stored accounts.
        $service  = new SAS_Token_Service();
        $accounts = $service->get_all_accounts();
        return new WP_REST_Response($accounts, 200);
    }

    public function delete_account(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id = absint($request->get_param('id'));
        // Accounts live on the backend exclusively now (user-scoped, linked
        // to websites via M2M) — there is no valid local fallback: the local
        // wp_sas_accounts table uses a different ID space than the backend's
        // account IDs, so reusing $id there would silently no-op or delete
        // an unrelated row. Report the real outcome instead of a fake success.
        $result = SAS_Backend_Client::delete('/api/v1/social-accounts/plugin/' . $id . '/');
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // OAuth URLs
    // =========================================================================

    public function youtube_oauth_url(): WP_REST_Response|WP_Error {
        try {
            $service = new SAS_Youtube_Service();
            return new WP_REST_Response( [ 'url' => $service->get_auth_url() ], 200 );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'oauth_error', $e->getMessage(), [ 'status' => 400 ] );
        }
    }

    public function instagram_oauth_url(): WP_REST_Response|WP_Error {
        try {
            $service = new SAS_Instagram_Service();
            return new WP_REST_Response( [ 'url' => $service->get_auth_url() ], 200 );
        } catch ( \Throwable $e ) {
            return new WP_Error( 'oauth_error', $e->getMessage(), [ 'status' => 400 ] );
        }
    }

    // =========================================================================
    // Logs
    // =========================================================================

    public function get_logs(WP_REST_Request $request): WP_REST_Response {
        $service  = new SAS_Log_Service();
        $video_id = $request->get_param('video_id') ? absint($request->get_param('video_id')) : null;
        $level    = sanitize_key($request->get_param('level') ?? '');
        $limit    = min(500, absint($request->get_param('limit') ?? 100));
        $logs     = $service->get_logs($video_id, null, $limit, $level);
        return new WP_REST_Response($logs, 200);
    }

    public function clear_logs(): WP_REST_Response {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}sas_logs");
        return new WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // Queue
    // =========================================================================

    public function get_queue(): WP_REST_Response {
        global $wpdb;

        $q = $wpdb->prefix . 'sas_queue';
        $v = $wpdb->prefix . 'sas_videos';

        $items = $wpdb->get_results(
            "SELECT q.*, v.title, v.platform FROM $q q LEFT JOIN $v v ON q.video_id = v.id ORDER BY q.updated_at DESC LIMIT 50",
            ARRAY_A
        );

        return new WP_REST_Response($items ?: [], 200);
    }

    // =========================================================================
    // Scheduler – next available date
    // =========================================================================

    public function get_next_date(): WP_REST_Response {
        $scheduler = new SAS_Scheduler_Service();
        $date      = $scheduler->get_next_available_date();
        return new WP_REST_Response(['next_date' => $date->format('Y-m-d H:i:s')], 200);
    }

    // =========================================================================
    // Contact / Support
    // =========================================================================

    public function submit_contact(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params() ?? [];

        $name    = sanitize_text_field($data['name'] ?? '');
        $email   = sanitize_email($data['email'] ?? '');
        $topic   = sanitize_text_field($data['topic'] ?? '');
        $message = sanitize_textarea_field($data['message'] ?? '');

        if (!$name || !$email || !is_email($email) || strlen(trim($message)) < 10) {
            return new WP_Error(
                'invalid_contact',
                __('Please provide your name, a valid email, and a message (at least 10 characters).', 'social-auto-scheduler'),
                ['status' => 400]
            );
        }

        $result = SAS_Backend_Client::post('/api/v1/contact/plugin/', [
            'name'    => $name,
            'email'   => $email,
            'topic'   => $topic,
            'message' => $message,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}

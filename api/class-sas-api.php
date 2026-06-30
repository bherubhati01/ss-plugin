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
        register_rest_route($ns, '/upload/finalize/(?P<upload_id>[a-zA-Z0-9_.]+)', [
            ['methods' => 'POST', 'callback' => [$this, 'upload_finalize'], 'permission_callback' => [$this, 'auth']],
        ]);
        register_rest_route($ns, '/upload/status/(?P<upload_id>[a-zA-Z0-9_.]+)', [
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

    public function get_videos(WP_REST_Request $request): WP_REST_Response {
        $service = new SAS_Video_Service();
        $args    = [
            'status'   => sanitize_text_field($request->get_param('status') ?? ''),
            'platform' => sanitize_text_field($request->get_param('platform') ?? ''),
            'search'   => sanitize_text_field($request->get_param('search') ?? ''),
            'orderby'  => sanitize_key($request->get_param('orderby') ?? 'created_at'),
            'order'    => strtoupper(sanitize_text_field($request->get_param('order') ?? 'DESC')),
            'limit'    => absint($request->get_param('limit') ?? 20),
            'offset'   => absint($request->get_param('offset') ?? 0),
        ];
        return new WP_REST_Response($service->get_all(null, $args), 200);
    }

    public function get_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $service = new SAS_Video_Service();
        $video   = $service->get(absint($request->get_param('id')), get_current_user_id());
        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'social-auto-scheduler'), ['status' => 404]);
        }
        return new WP_REST_Response($video, 200);
    }

    public function create_video(WP_REST_Request $request): WP_REST_Response {
        $service = new SAS_Video_Service();
        $data    = $request->get_json_params();

        $video_id = $service->create([
            'file_path'   => sanitize_text_field($data['file_path'] ?? ''),
            'file_url'    => esc_url_raw($data['file_url'] ?? ''),
            'thumbnail_url'=> esc_url_raw($data['thumbnail_url'] ?? ''),
            'title'       => sanitize_text_field($data['title'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'tags'        => SAS_Helpers::sanitize_tags($data['tags'] ?? []),
            'duration'    => absint($data['duration'] ?? 0),
            'file_size'   => absint($data['file_size'] ?? 0),
            'platform'    => sanitize_key($data['platform'] ?? 'youtube'),
            'account_id'  => absint($data['account_id'] ?? 0),
        ]);

        return new WP_REST_Response(['id' => $video_id], 201);
    }

    public function update_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $service  = new SAS_Video_Service();
        $video_id = absint($request->get_param('id'));
        $data     = $request->get_json_params() ?? [];

        $sanitized = [];
        if (isset($data['title']))       $sanitized['title']       = sanitize_text_field($data['title']);
        if (isset($data['description'])) $sanitized['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['tags']))        $sanitized['tags']        = json_encode(SAS_Helpers::sanitize_tags($data['tags']));
        if (isset($data['status']))      $sanitized['status']      = sanitize_key($data['status']);
        if (isset($data['publish_date'])) $sanitized['publish_date'] = sanitize_text_field($data['publish_date']);
        if (isset($data['account_id'])) $sanitized['account_id']  = absint($data['account_id']);
        if (isset($data['platform']))   $sanitized['platform']    = sanitize_key($data['platform']);

        $service->update($video_id, $sanitized, get_current_user_id());
        return new WP_REST_Response(['success' => true], 200);
    }

    public function delete_video(WP_REST_Request $request): WP_REST_Response {
        $service = new SAS_Video_Service();
        $service->delete(absint($request->get_param('id')), get_current_user_id());
        return new WP_REST_Response(['success' => true], 200);
    }

    public function schedule_video(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $video_id = absint($request->get_param('id'));
        try {
            $scheduler = new SAS_Scheduler_Service();
            $date      = $scheduler->schedule_video($video_id, get_current_user_id());

            $queue = new SAS_Queue_Service();
            $queue->add($video_id);

            return new WP_REST_Response(['success' => true, 'publish_date' => $date->format('Y-m-d H:i:s')], 200);
        } catch (Exception $e) {
            return new WP_Error('schedule_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function bulk_action(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data    = $request->get_json_params() ?? [];
        $action  = sanitize_key($data['action'] ?? '');
        $ids     = array_map('absint', $data['ids'] ?? []);
        $user_id = get_current_user_id();

        if (empty($ids)) {
            return new WP_Error('no_ids', __('No video IDs provided.', 'social-auto-scheduler'), ['status' => 400]);
        }

        $service = new SAS_Video_Service();

        switch ($action) {
            case 'delete':
                $count = $service->bulk_delete($ids, $user_id);
                return new WP_REST_Response(['success' => true, 'affected' => $count], 200);

            case 'reschedule':
                $count = $service->bulk_reschedule($ids, $user_id);
                return new WP_REST_Response(['success' => true, 'affected' => $count], 200);

            case 'schedule':
                $scheduler = new SAS_Scheduler_Service();
                $queue     = new SAS_Queue_Service();
                $count     = 0;
                foreach ($ids as $id) {
                    try {
                        $scheduler->schedule_video($id, $user_id);
                        $queue->add($id);
                        $count++;
                    } catch (Exception $e) {
                        // continue
                    }
                }
                return new WP_REST_Response(['success' => true, 'affected' => $count], 200);

            case 'cancel':
                foreach ($ids as $id) {
                    $service->update($id, ['status' => 'cancelled'], $user_id);
                }
                return new WP_REST_Response(['success' => true, 'affected' => count($ids)], 200);

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
            $platforms = SAS_Upload_Service::sanitize_platforms($raw_platforms);
            $service   = new SAS_Upload_Service();
            $video_ids = $service->handle_single_upload(
                $files['file'],
                $platforms,
                absint($request->get_param('account_id') ?? 0)
            );

            $scheduler = new SAS_Scheduler_Service();
            $queue     = new SAS_Queue_Service();
            $results   = [];

            foreach ($video_ids as $vid_id) {
                $date      = $scheduler->schedule_video($vid_id);
                $queue->add($vid_id);
                $results[] = ['id' => $vid_id, 'publish_date' => $date->format('Y-m-d H:i:s')];
            }

            return new WP_REST_Response(['videos' => $results], 201);
        } catch (RuntimeException $e) {
            return new WP_Error('upload_failed', $e->getMessage(), ['status' => 400]);
        }
    }

    public function upload_init(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $data = $request->get_json_params() ?? [];
        try {
            $service = new SAS_Upload_Service();
            $result  = $service->init_upload([
                'file_name'  => sanitize_file_name($data['file_name'] ?? 'video.mp4'),
                'file_size'  => absint($data['file_size'] ?? 0),
                'chunk_size' => absint($data['chunk_size'] ?? 5242880),
                'platforms'  => $data['platforms'] ?? $data['platform'] ?? 'youtube',
                'account_id' => absint($data['account_id'] ?? 0),
            ]);
            return new WP_REST_Response($result, 200);
        } catch (RuntimeException $e) {
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
        } catch (RuntimeException $e) {
            return new WP_Error('chunk_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function upload_finalize(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $upload_id = sanitize_text_field($request->get_param('upload_id') ?? '');

        try {
            $service   = new SAS_Upload_Service();
            $video_ids = $service->finalize_upload($upload_id);

            $scheduler = new SAS_Scheduler_Service();
            $queue     = new SAS_Queue_Service();
            $results   = [];

            foreach ($video_ids as $vid_id) {
                $date      = $scheduler->schedule_video($vid_id);
                $queue->add($vid_id);
                $results[] = ['id' => $vid_id, 'publish_date' => $date->format('Y-m-d H:i:s')];
            }

            return new WP_REST_Response(['videos' => $results], 201);
        } catch (RuntimeException $e) {
            return new WP_Error('finalize_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function publish_now(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $video_id = absint($request->get_param('id'));
        $user_id  = get_current_user_id();

        $video_service = new SAS_Video_Service();
        $video         = $video_service->get($video_id, $user_id);

        if (!$video) {
            return new WP_Error('not_found', __('Video not found.', 'social-auto-scheduler'), ['status' => 404]);
        }

        // Block videos already being processed or published
        if (in_array($video['status'], ['publishing', 'published'], true)) {
            return new WP_Error(
                'invalid_status',
                sprintf(__('Cannot publish: video is already %s.', 'social-auto-scheduler'), $video['status']),
                ['status' => 409]
            );
        }

        // Set publish time to now so cron processes it on the next tick
        $video_service->update($video_id, [
            'status'        => 'queued',
            'publish_date'  => current_time('mysql'),
            'error_message' => null,
        ], $user_id);

        $queue = new SAS_Queue_Service();
        $queue->add($video_id);

        (new SAS_Log_Service())->info('publish_now', 'Video queued for immediate publishing', [], $video_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Video queued — it will publish within 5 minutes.', 'social-auto-scheduler'),
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

    public function get_stats(): WP_REST_Response {
        $service = new SAS_Video_Service();
        return new WP_REST_Response($service->get_stats(), 200);
    }

    public function get_calendar(WP_REST_Request $request): WP_REST_Response {
        $start   = sanitize_text_field($request->get_param('start') ?? date('Y-m-01'));
        $end     = sanitize_text_field($request->get_param('end')   ?? date('Y-m-t'));
        $service = new SAS_Video_Service();
        $events  = $service->get_calendar_events(get_current_user_id(), $start, $end);
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

    public function save_settings(WP_REST_Request $request): WP_REST_Response {
        $service  = new SAS_Settings_Service();
        $data     = $request->get_json_params() ?? [];

        $safe_keys = [
            'timezone', 'upload_time', 'uploads_per_day', 'weekdays',
            'default_description', 'default_tags', 'youtube_client_id',
            'youtube_category', 'youtube_privacy', 'instagram_app_id',
            'instagram_config_id',
        ];

        foreach ($safe_keys as $key) {
            if (array_key_exists($key, $data)) {
                $val = is_array($data[$key])
                    ? array_map('sanitize_text_field', $data[$key])
                    : sanitize_text_field($data[$key]);
                $service->set($key, $val);
            }
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
        $service  = new SAS_Token_Service();
        $accounts = $service->get_all_accounts();
        return new WP_REST_Response($accounts, 200);
    }

    public function delete_account(WP_REST_Request $request): WP_REST_Response {
        $service = new SAS_Token_Service();
        $service->delete_account(absint($request->get_param('id')));
        return new WP_REST_Response(['success' => true], 200);
    }

    // =========================================================================
    // OAuth URLs
    // =========================================================================

    public function youtube_oauth_url(): WP_REST_Response|WP_Error {
        $settings = new SAS_Settings_Service();
        if (!$settings->get('youtube_client_id')) {
            return new WP_Error('no_credentials', __('YouTube Client ID not configured.', 'social-auto-scheduler'), ['status' => 400]);
        }
        $service = new SAS_Youtube_Service();
        return new WP_REST_Response(['url' => $service->get_auth_url()], 200);
    }

    public function instagram_oauth_url(): WP_REST_Response|WP_Error {
        $settings  = new SAS_Settings_Service();
        $app_id    = trim( (string) $settings->get( 'instagram_app_id', null, '' ) );
        $config_id = trim( (string) $settings->get( 'instagram_config_id', null, '' ) );

        if ( empty( $app_id ) ) {
            return new WP_Error(
                'no_credentials',
                __( 'Instagram App ID not configured. Go to Settings → Instagram / Meta API and enter your App ID.', 'social-auto-scheduler' ),
                [ 'status' => 400 ]
            );
        }

        if ( ! ctype_digit( $app_id ) ) {
            return new WP_Error(
                'invalid_app_id',
                __( 'Instagram App ID must be a numeric value (e.g. 1234567890). It is the number shown at the top of your Meta Developer app dashboard — NOT a name or URL.', 'social-auto-scheduler' ),
                [ 'status' => 400 ]
            );
        }

        // App Secret is required for the token exchange step (Step 2) regardless
        // of whether config_id or scope is used, so always validate it here.
        if ( ! $settings->get( 'instagram_app_secret_enc' ) ) {
            return new WP_Error(
                'no_secret',
                __( 'Instagram App Secret not configured. Go to Settings → Instagram / Meta API and enter your App Secret.', 'social-auto-scheduler' ),
                [ 'status' => 400 ]
            );
        }

        $service = new SAS_Instagram_Service();
        return new WP_REST_Response( [ 'url' => $service->get_auth_url(), 'using_config_id' => ! empty( $config_id ) ], 200 );
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
}

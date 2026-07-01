<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * YouTube Data API v3 integration.
 *
 * OAuth 2.0 → Authorization Code flow.
 * Video upload → Resumable upload session.
 */
class SAS_Youtube_Service {

    private const API_BASE       = 'https://www.googleapis.com/youtube/v3';
    private const UPLOAD_BASE    = 'https://www.googleapis.com/upload/youtube/v3';
    private const AUTH_URL       = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL      = 'https://oauth2.googleapis.com/token';
    private const SCOPE          = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly';
    private const CHUNK_SIZE     = 5242880; // 5 MB

    private SAS_Token_Service $token_service;
    private SAS_Log_Service   $log_service;

    public function __construct() {
        $this->token_service = new SAS_Token_Service();
        $this->log_service   = new SAS_Log_Service();
    }

    // -------------------------------------------------------------------------
    // Credentials (stored in plugin settings)
    // -------------------------------------------------------------------------

    private function get_client_id(int $user_id = 0): string {
        $uid = $user_id ?: get_current_user_id();
        return (new SAS_Settings_Service())->get('youtube_client_id', $uid, '');
    }

    private function get_client_secret(int $user_id = 0): string {
        $uid = $user_id ?: get_current_user_id();
        $enc = (new SAS_Settings_Service())->get('youtube_client_secret_enc', $uid, '');
        return $enc ? SAS_Token_Service::decrypt($enc) : '';
    }

    private function get_redirect_uri(): string {
        return admin_url('admin.php?page=sas-accounts&sas_oauth=youtube');
    }

    // -------------------------------------------------------------------------
    // OAuth flow
    // -------------------------------------------------------------------------

    public function get_auth_url(): string {
        $params = [
            'response_type'   => 'code',
            'client_id'       => $this->get_client_id(),
            'redirect_uri'    => $this->get_redirect_uri(),
            'scope'           => self::SCOPE,
            'access_type'     => 'offline',
            'prompt'          => 'consent',
            'state'           => wp_create_nonce('sas_youtube_oauth'),
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public function handle_callback(string $code, string $state): bool {
        if (!wp_verify_nonce($state, 'sas_youtube_oauth')) {
            throw new RuntimeException(__('Invalid OAuth state.', 'social-auto-scheduler'));
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body'    => [
                'code'          => $code,
                'client_id'     => $this->get_client_id(),
                'client_secret' => $this->get_client_secret(),
                'redirect_uri'  => $this->get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $this->assert_response($response, 'youtube_oauth_callback');

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            throw new RuntimeException(__('No access token in YouTube response.', 'social-auto-scheduler'));
        }

        // Fetch channel info for the account name
        $channel = $this->fetch_channel_info($data['access_token']);
        $expires_at = date('Y-m-d H:i:s', time() + (int) ($data['expires_in'] ?? 3600));

        $this->token_service->save_account([
            'platform'         => 'youtube',
            'account_name'     => $channel['name'] ?? 'YouTube Channel',
            'access_token'     => $data['access_token'],
            'refresh_token'    => $data['refresh_token'] ?? '',
            'token_expires_at' => $expires_at,
            'metadata'         => $channel,
        ]);

        $this->log_service->info('youtube_connected', 'YouTube account connected', ['channel' => $channel]);
        return true;
    }

    // -------------------------------------------------------------------------
    // Token refresh
    // -------------------------------------------------------------------------

    public function refresh_token(array $account): array {
        $user_id  = (int) ($account['user_id'] ?? 0);
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body'    => [
                'refresh_token' => $account['refresh_token'],
                'client_id'     => $this->get_client_id($user_id),
                'client_secret' => $this->get_client_secret($user_id),
                'grant_type'    => 'refresh_token',
            ],
        ]);

        $this->assert_response($response, 'youtube_token_refresh');
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            throw new RuntimeException(__('Token refresh failed.', 'social-auto-scheduler'));
        }

        $expires_at = date('Y-m-d H:i:s', time() + (int) ($data['expires_in'] ?? 3600));
        $this->token_service->update_tokens(
            (int) $account['id'],
            $data['access_token'],
            $data['refresh_token'] ?? null,
            $expires_at
        );

        $this->log_service->info('youtube_token_refreshed', 'YouTube access token refreshed', [], null, (int) $account['id']);

        return array_merge($account, ['access_token' => $data['access_token'], 'token_expires_at' => $expires_at]);
    }

    // -------------------------------------------------------------------------
    // Get valid access token (refreshing if needed)
    // -------------------------------------------------------------------------

    private function get_valid_account(int $user_id): array {
        $account = $this->token_service->get_account('youtube', $user_id);
        if (!$account) {
            throw new RuntimeException(__('No YouTube account connected.', 'social-auto-scheduler'));
        }

        if ($this->token_service->is_token_expired($account)) {
            $account = $this->refresh_token($account);
        }

        return $account;
    }

    // -------------------------------------------------------------------------
    // Video publishing
    // -------------------------------------------------------------------------

    public function publish_video(array $video): string {
        $account = $this->get_valid_account((int) $video['user_id']);
        $token   = $account['access_token'];

        $tags = [];
        if (!empty($video['tags'])) {
            $tags_raw = is_string($video['tags']) ? json_decode($video['tags'], true) : $video['tags'];
            $tags     = is_array($tags_raw) ? $tags_raw : explode(',', $video['tags']);
        }

        $metadata_raw = !empty($video['metadata']) ? (is_string($video['metadata']) ? json_decode($video['metadata'], true) : $video['metadata']) : [];
        $metadata_raw = is_array($metadata_raw) ? $metadata_raw : [];

        $snippet = [
            'title'       => $video['title'] ?? 'Untitled',
            'description' => $video['description'] ?? '',
            'tags'        => array_map('trim', $tags),
            'categoryId'  => (string) ($metadata_raw['youtube_category'] ?? '22'),
        ];

        $status = [
            'privacyStatus' => $metadata_raw['youtube_privacy'] ?? 'public',
        ];

        $session_uri = $this->initiate_resumable_upload($token, $snippet, $status, $video['file_path']);
        $youtube_id  = $this->upload_file_resumable($token, $session_uri, $video['file_path']);

        $this->log_service->info(
            'youtube_published',
            'Video published to YouTube',
            ['youtube_id' => $youtube_id],
            (int) $video['id'],
            (int) $account['id']
        );

        // Persist the YouTube video ID
        $meta              = $metadata_raw;
        $meta['youtube_id'] = $youtube_id;
        (new SAS_Video_Service())->update((int) $video['id'], [
            'metadata' => json_encode($meta),
        ], (int) $video['user_id']);

        return $youtube_id;
    }

    // -------------------------------------------------------------------------
    // Resumable upload
    // -------------------------------------------------------------------------

    private function initiate_resumable_upload(string $token, array $snippet, array $status, string $file_path): string {
        $file_size = filesize($file_path);

        $response = wp_remote_post(self::UPLOAD_BASE . '/videos?uploadType=resumable&part=snippet,status', [
            'timeout' => 60,
            'headers' => [
                'Authorization'           => 'Bearer ' . $token,
                'Content-Type'            => 'application/json',
                'X-Upload-Content-Length' => (string) $file_size,
                'X-Upload-Content-Type'   => 'video/mp4',
            ],
            'body'    => json_encode(['snippet' => $snippet, 'status' => $status]),
        ]);

        $this->assert_response($response, 'youtube_upload_init');

        $headers = wp_remote_retrieve_headers($response);
        $location = is_object($headers) ? $headers->offsetGet('location') : ($headers['location'] ?? '');

        if (empty($location)) {
            throw new RuntimeException(__('YouTube did not return an upload session URI.', 'social-auto-scheduler'));
        }

        return $location;
    }

    private function upload_file_resumable(string $token, string $session_uri, string $file_path): string {
        $file_size = filesize($file_path);
        $offset    = 0;
        $youtube_id = '';

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $fp = fopen($file_path, 'rb');
        if (!$fp) {
            throw new RuntimeException(__('Cannot open video file for reading.', 'social-auto-scheduler'));
        }

        while (!feof($fp)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
            $chunk = fread($fp, self::CHUNK_SIZE);
            if ($chunk === false) {
                break;
            }

            $chunk_length = strlen($chunk);
            $range_end    = $offset + $chunk_length - 1;

            $response = wp_remote_request($session_uri, [
                'method'  => 'PUT',
                'timeout' => 120,
                'headers' => [
                    'Authorization'  => 'Bearer ' . $token,
                    'Content-Type'   => 'video/mp4',
                    'Content-Length' => (string) $chunk_length,
                    'Content-Range'  => "bytes {$offset}-{$range_end}/{$file_size}",
                ],
                'body'    => $chunk,
            ]);

            $code = wp_remote_retrieve_response_code($response);

            if ($code === 308) {
                // Incomplete – continue with next chunk
                $range_header = wp_remote_retrieve_header($response, 'range');
                if ($range_header) {
                    preg_match('/bytes=0-(\d+)/', $range_header, $m);
                    $offset = isset($m[1]) ? (int) $m[1] + 1 : $offset + $chunk_length;
                } else {
                    $offset += $chunk_length;
                }
                continue;
            }

            if (in_array($code, [200, 201], true)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $youtube_id = $body['id'] ?? '';
                break;
            }

            fclose($fp);
            $this->assert_response($response, 'youtube_upload_chunk');
        }

        fclose($fp);

        if (empty($youtube_id)) {
            throw new RuntimeException(__('YouTube upload completed but no video ID returned.', 'social-auto-scheduler'));
        }

        return $youtube_id;
    }

    // -------------------------------------------------------------------------
    // Channel info
    // -------------------------------------------------------------------------

    private function fetch_channel_info(string $access_token): array {
        $response = wp_remote_get(self::API_BASE . '/channels?part=snippet&mine=true', [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ]);

        if (is_wp_error($response)) {
            return ['name' => 'YouTube Channel'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $item = $body['items'][0] ?? [];

        return [
            'channel_id'   => $item['id'] ?? '',
            'name'         => $item['snippet']['title'] ?? 'YouTube Channel',
            'thumbnail'    => $item['snippet']['thumbnails']['default']['url'] ?? '',
            'custom_url'   => $item['snippet']['customUrl'] ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Error handling
    // -------------------------------------------------------------------------

    private function assert_response($response, string $context): void {
        if (is_wp_error($response)) {
            $this->log_service->error($context, $response->get_error_message());
            throw new RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $body = wp_remote_retrieve_body($response);
            $msg  = "YouTube API error [{$code}]: {$body}";
            $this->log_service->error($context, $msg);
            throw new RuntimeException($msg);
        }
    }
}

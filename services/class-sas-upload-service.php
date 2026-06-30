<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles chunked and single-shot video uploads.
 *
 * Supports uploading to one or multiple platforms in a single operation.
 * One file is stored on disk; one SAS video record is created per platform.
 *
 * Chunked workflow:
 *   1. init_upload()    → upload_id
 *   2. receive_chunk()  × N
 *   3. finalize_upload() → array of video IDs (one per platform)
 */
class SAS_Upload_Service {

    private const CHUNK_TRANSIENT_PREFIX = 'sas_upload_';
    private const MAX_FILE_SIZE          = 5368709120; // 5 GB
    private const ALLOWED_PLATFORMS      = ['youtube', 'instagram'];

    // -------------------------------------------------------------------------
    // Init chunked upload
    // -------------------------------------------------------------------------

    public function init_upload(array $meta): array {
        $file_size = (int) ($meta['file_size'] ?? 0);
        if ($file_size > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                sprintf(__('File size %s exceeds 5 GB limit.', 'social-auto-scheduler'), SAS_Helpers::format_bytes($file_size))
            );
        }

        $upload_id    = SAS_Helpers::generate_unique_id();
        $chunk_size   = (int) ($meta['chunk_size'] ?? 5242880);
        $total_chunks = (int) ceil($file_size / max(1, $chunk_size));

        // Accept 'platforms' array OR legacy 'platform' string
        $platforms = self::sanitize_platforms($meta['platforms'] ?? $meta['platform'] ?? 'youtube');

        $state = [
            'upload_id'    => $upload_id,
            'file_name'    => sanitize_file_name($meta['file_name'] ?? 'video.mp4'),
            'file_size'    => $file_size,
            'chunk_size'   => $chunk_size,
            'total_chunks' => $total_chunks,
            'received'     => [],
            'platforms'    => $platforms,
            'account_id'   => (int) ($meta['account_id'] ?? 0),
            'user_id'      => get_current_user_id(),
            'status'       => 'uploading',
            'created_at'   => time(),
        ];

        set_transient(self::CHUNK_TRANSIENT_PREFIX . $upload_id, $state, 3600);
        return ['upload_id' => $upload_id, 'total_chunks' => $total_chunks];
    }

    // -------------------------------------------------------------------------
    // Receive chunk
    // -------------------------------------------------------------------------

    public function receive_chunk(string $upload_id, int $chunk_index, string $chunk_data): array {
        $state     = $this->get_state($upload_id);
        $chunk_dir = SAS_Helpers::get_temp_dir() . '/' . $upload_id;

        if (!file_exists($chunk_dir)) {
            wp_mkdir_p($chunk_dir);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if (file_put_contents($chunk_dir . '/chunk_' . $chunk_index, $chunk_data) === false) {
            throw new RuntimeException(__('Failed to save chunk to disk.', 'social-auto-scheduler'));
        }

        $state['received'][$chunk_index] = true;
        set_transient(self::CHUNK_TRANSIENT_PREFIX . $upload_id, $state, 3600);

        $received = count($state['received']);
        $total    = $state['total_chunks'];

        return [
            'received' => $received,
            'total'    => $total,
            'percent'  => $total > 0 ? round(($received / $total) * 100) : 0,
            'complete' => $received >= $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Finalize chunked upload → returns array of created video IDs
    // -------------------------------------------------------------------------

    public function finalize_upload(string $upload_id): array {
        $state = $this->get_state($upload_id);

        if (count($state['received']) < $state['total_chunks']) {
            throw new RuntimeException(__('Not all chunks received.', 'social-auto-scheduler'));
        }

        $chunk_dir = SAS_Helpers::get_temp_dir() . '/' . $upload_id;
        $dest_dir  = SAS_Helpers::get_upload_dir();
        $dest_name = $upload_id . '_' . $state['file_name'];
        $dest_file = $dest_dir . '/' . $dest_name;
        $dest_url  = SAS_Helpers::get_upload_url() . '/' . $dest_name;

        // Assemble chunks
        $fp = fopen($dest_file, 'wb');
        if (!$fp) {
            throw new RuntimeException(__('Cannot create destination file.', 'social-auto-scheduler'));
        }

        for ($i = 0; $i < $state['total_chunks']; $i++) {
            $chunk_file = $chunk_dir . '/chunk_' . $i;
            if (!file_exists($chunk_file)) {
                fclose($fp);
                throw new RuntimeException(sprintf(__('Chunk %d missing.', 'social-auto-scheduler'), $i));
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            fwrite($fp, file_get_contents($chunk_file));
        }
        fclose($fp);

        $this->cleanup_chunks($upload_id);

        if (!SAS_Helpers::is_valid_video_mime($dest_file)) {
            unlink($dest_file);
            throw new RuntimeException(__('Invalid video file type.', 'social-auto-scheduler'));
        }

        $duration = SAS_Helpers::get_video_duration($dest_file);
        $title    = pathinfo($state['file_name'], PATHINFO_FILENAME);

        $video_ids = $this->create_video_records(
            $dest_file,
            $dest_url,
            $title,
            $state['platforms'],
            $state['account_id'],
            (int) filesize($dest_file),
            $duration,
            $state['user_id']
        );

        delete_transient(self::CHUNK_TRANSIENT_PREFIX . $upload_id);

        return $video_ids;
    }

    // -------------------------------------------------------------------------
    // Single-shot upload (small files) → returns array of created video IDs
    // -------------------------------------------------------------------------

    public function handle_single_upload(array $file, array $platforms, int $account_id = 0): array {
        $allowed_mimes = ['mp4' => 'video/mp4', 'mov' => 'video/quicktime'];
        $file_info     = wp_check_filetype($file['name'], $allowed_mimes);

        if (empty($file_info['type'])) {
            throw new RuntimeException(__('Only MP4 and MOV files are allowed.', 'social-auto-scheduler'));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException(__('File exceeds 5 GB size limit.', 'social-auto-scheduler'));
        }

        $platforms = self::sanitize_platforms($platforms);

        $dest_dir  = SAS_Helpers::get_upload_dir();
        $dest_name = SAS_Helpers::generate_unique_id() . '_' . sanitize_file_name($file['name']);
        $dest_file = $dest_dir . '/' . $dest_name;
        $dest_url  = SAS_Helpers::get_upload_url() . '/' . $dest_name;

        if (!move_uploaded_file($file['tmp_name'], $dest_file)) {
            throw new RuntimeException(__('Failed to move uploaded file.', 'social-auto-scheduler'));
        }

        if (!SAS_Helpers::is_valid_video_mime($dest_file)) {
            unlink($dest_file);
            throw new RuntimeException(__('Invalid video file.', 'social-auto-scheduler'));
        }

        $duration = SAS_Helpers::get_video_duration($dest_file);
        $title    = pathinfo($file['name'], PATHINFO_FILENAME);

        return $this->create_video_records(
            $dest_file,
            $dest_url,
            $title,
            $platforms,
            $account_id,
            (int) filesize($dest_file),
            $duration,
            get_current_user_id()
        );
    }

    // -------------------------------------------------------------------------
    // Shared: create one video record per platform from the same file
    // -------------------------------------------------------------------------

    private function create_video_records(
        string $file_path,
        string $file_url,
        string $title,
        array  $platforms,
        int    $account_id,
        int    $file_size,
        int    $duration,
        int    $user_id
    ): array {
        $video_service = new SAS_Video_Service();
        $settings      = new SAS_Settings_Service();
        $default_desc  = (string) $settings->get('default_description', $user_id, '');
        $default_tags  = SAS_Helpers::sanitize_tags((string) $settings->get('default_tags', $user_id, ''));
        $ids           = [];

        foreach ($platforms as $platform) {
            $ids[] = $video_service->create([
                'file_path'   => $file_path,
                'file_url'    => $file_url,
                'title'       => $title,
                'description' => $default_desc,
                'tags'        => $default_tags,
                'platform'    => $platform,
                'account_id'  => $account_id,
                'file_size'   => $file_size,
                'duration'    => $duration,
            ], $user_id);
        }

        return $ids;
    }

    // -------------------------------------------------------------------------
    // Status check (chunked uploads)
    // -------------------------------------------------------------------------

    public function get_status(string $upload_id): array {
        $state = get_transient(self::CHUNK_TRANSIENT_PREFIX . $upload_id);
        if (!$state) {
            return ['status' => 'not_found'];
        }
        $total    = $state['total_chunks'];
        $received = count($state['received']);
        return [
            'status'    => $state['status'],
            'percent'   => $total > 0 ? round(($received / $total) * 100) : 0,
            'received'  => $received,
            'total'     => $total,
            'platforms' => $state['platforms'],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function get_state(string $upload_id): array {
        $state = get_transient(self::CHUNK_TRANSIENT_PREFIX . $upload_id);
        if (!$state) {
            throw new RuntimeException(__('Upload session expired or not found.', 'social-auto-scheduler'));
        }
        return $state;
    }

    private function cleanup_chunks(string $upload_id): void {
        $chunk_dir = SAS_Helpers::get_temp_dir() . '/' . $upload_id;
        if (!is_dir($chunk_dir)) {
            return;
        }
        foreach (glob($chunk_dir . '/chunk_*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($chunk_dir);
    }

    /**
     * Normalise platform input → validated array of platform slugs.
     * Accepts string, comma-separated string, or array.
     */
    public static function sanitize_platforms(mixed $input): array {
        if (is_string($input)) {
            $input = array_map('trim', explode(',', $input));
        }
        if (!is_array($input)) {
            $input = ['youtube'];
        }
        $platforms = array_values(array_filter(array_map(
            fn($p) => in_array(sanitize_key($p), self::ALLOWED_PLATFORMS, true) ? sanitize_key($p) : null,
            $input
        )));
        return $platforms ?: ['youtube'];
    }
}

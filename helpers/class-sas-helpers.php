<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Helpers {

    public static function format_bytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    public static function format_duration(int $seconds): string {
        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%d:%02d', $m, $s);
    }

    public static function sanitize_tags($tags): array {
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }
        if (!is_array($tags)) {
            return [];
        }
        return array_values(array_filter(array_map('sanitize_text_field', $tags)));
    }

    public static function tags_to_string(array $tags): string {
        return implode(', ', $tags);
    }

    public static function get_upload_dir(): string {
        $upload_dir = wp_upload_dir();
        $sas_dir    = $upload_dir['basedir'] . '/sas-videos';
        if (!file_exists($sas_dir)) {
            wp_mkdir_p($sas_dir);
            file_put_contents($sas_dir . '/.htaccess', "Options -Indexes\n");
            file_put_contents($sas_dir . '/index.php', "<?php // Silence is golden\n");
        }
        return $sas_dir;
    }

    public static function get_upload_url(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/sas-videos';
    }

    public static function get_temp_dir(): string {
        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/sas-temp';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            file_put_contents($temp_dir . '/.htaccess', "deny from all\n");
            file_put_contents($temp_dir . '/index.php', "<?php // Silence is golden\n");
        }
        return $temp_dir;
    }

    public static function generate_unique_id(): string {
        return uniqid('sas_', true);
    }

    public static function is_valid_video_mime(string $file_path): bool {
        $allowed = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        return in_array($mime, $allowed, true);
    }

    public static function get_video_duration(string $file_path): int {
        // Use ffprobe if available, otherwise return 0
        if (function_exists('exec')) {
            $output = [];
            exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file_path) . " 2>/dev/null", $output);
            if (!empty($output[0]) && is_numeric($output[0])) {
                return (int) round((float) $output[0]);
            }
        }
        return 0;
    }

    public static function time_ago(string $datetime): string {
        $now  = new DateTime('now');
        $ago  = new DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->y > 0) {
            return sprintf(_n('%d year ago', '%d years ago', $diff->y, 'social-auto-scheduler'), $diff->y);
        }
        if ($diff->m > 0) {
            return sprintf(_n('%d month ago', '%d months ago', $diff->m, 'social-auto-scheduler'), $diff->m);
        }
        if ($diff->d > 0) {
            return sprintf(_n('%d day ago', '%d days ago', $diff->d, 'social-auto-scheduler'), $diff->d);
        }
        if ($diff->h > 0) {
            return sprintf(_n('%d hour ago', '%d hours ago', $diff->h, 'social-auto-scheduler'), $diff->h);
        }
        if ($diff->i > 0) {
            return sprintf(_n('%d minute ago', '%d minutes ago', $diff->i, 'social-auto-scheduler'), $diff->i);
        }
        return __('just now', 'social-auto-scheduler');
    }

    public static function status_badge(string $status): string {
        $labels = [
            'draft'      => __('Draft', 'social-auto-scheduler'),
            'queued'     => __('Queued', 'social-auto-scheduler'),
            'scheduled'  => __('Scheduled', 'social-auto-scheduler'),
            'publishing' => __('Publishing', 'social-auto-scheduler'),
            'published'  => __('Published', 'social-auto-scheduler'),
            'failed'     => __('Failed', 'social-auto-scheduler'),
            'cancelled'  => __('Cancelled', 'social-auto-scheduler'),
        ];
        $label = $labels[$status] ?? ucfirst($status);
        return '<span class="sas-badge sas-badge--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }

    public static function platform_icon(string $platform): string {
        $icons = [
            'youtube'   => '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="#FF0000" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
            'instagram' => '<svg viewBox="0 0 24 24" width="16" height="16"><path fill="#E4405F" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
        ];
        return $icons[$platform] ?? '<span>' . esc_html(ucfirst($platform)) . '</span>';
    }
}

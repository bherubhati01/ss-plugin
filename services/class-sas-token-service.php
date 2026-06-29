<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles encrypted storage and retrieval of OAuth tokens.
 *
 * Tokens are encrypted with AES-256-CBC using a key derived from WordPress
 * secret keys so they are safe at rest in the database.
 */
class SAS_Token_Service {

    private const CIPHER = 'AES-256-CBC';

    // -------------------------------------------------------------------------
    // Encryption helpers
    // -------------------------------------------------------------------------

    private static function encryption_key(): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : '';
        $salt .= defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '';
        return hash('sha256', $salt . 'sas_token_v1');
    }

    public static function encrypt(string $plain): string {
        $key = self::encryption_key();
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $cipher): string {
        $key     = self::encryption_key();
        $decoded = base64_decode($cipher);
        if (strlen($decoded) < 17) {
            return '';
        }
        $iv  = substr($decoded, 0, 16);
        $enc = substr($decoded, 16);
        $dec = openssl_decrypt($enc, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        return $dec === false ? '' : $dec;
    }

    // -------------------------------------------------------------------------
    // Account / token storage
    // -------------------------------------------------------------------------

    public function save_account(array $data): int {
        global $wpdb;

        $user_id  = $data['user_id'] ?? get_current_user_id();
        $platform = sanitize_key($data['platform']);
        $table    = $wpdb->prefix . 'sas_accounts';

        $row = [
            'platform'        => $platform,
            'user_id'         => (int) $user_id,
            'account_name'    => sanitize_text_field($data['account_name'] ?? ''),
            'access_token'    => self::encrypt($data['access_token'] ?? ''),
            'refresh_token'   => self::encrypt($data['refresh_token'] ?? ''),
            'token_expires_at'=> $data['token_expires_at'] ?? null,
            'metadata'        => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE platform = %s AND user_id = %d",
            $platform,
            (int) $user_id
        ));

        if ($existing) {
            $wpdb->update($table, $row, ['id' => (int) $existing]);
            return (int) $existing;
        }

        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public function get_account(string $platform, ?int $user_id = null): ?array {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table   = $wpdb->prefix . 'sas_accounts';
        $account = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE platform = %s AND user_id = %d LIMIT 1",
            $platform,
            $user_id
        ), ARRAY_A);

        if (!$account) {
            return null;
        }

        $account['access_token']  = self::decrypt($account['access_token'] ?? '');
        $account['refresh_token'] = self::decrypt($account['refresh_token'] ?? '');
        $account['metadata']      = !empty($account['metadata']) ? json_decode($account['metadata'], true) : [];

        return $account;
    }

    public function get_all_accounts(?int $user_id = null): array {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table    = $wpdb->prefix . 'sas_accounts';
        $accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY platform ASC",
            $user_id
        ), ARRAY_A);

        return array_map(function ($acc) {
            $acc['access_token']  = '***';
            $acc['refresh_token'] = '***';
            $meta = !empty($acc['metadata']) ? json_decode($acc['metadata'], true) : [];
            $acc['metadata'] = $meta;
            return $acc;
        }, $accounts);
    }

    public function update_tokens(int $account_id, string $access_token, ?string $refresh_token = null, ?string $expires_at = null): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sas_accounts';
        $data  = [
            'access_token'     => self::encrypt($access_token),
            'token_expires_at' => $expires_at,
        ];

        if ($refresh_token !== null) {
            $data['refresh_token'] = self::encrypt($refresh_token);
        }

        return (bool) $wpdb->update($table, $data, ['id' => $account_id]);
    }

    public function is_token_expired(array $account): bool {
        if (empty($account['token_expires_at'])) {
            return false;
        }
        $expires = new DateTime($account['token_expires_at']);
        $now     = new DateTime('now');
        // Treat as expired 5 minutes early to avoid edge cases
        $now->modify('+5 minutes');
        return $now >= $expires;
    }

    public function delete_account(int $account_id, ?int $user_id = null): bool {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table = $wpdb->prefix . 'sas_accounts';
        return (bool) $wpdb->delete($table, ['id' => $account_id, 'user_id' => $user_id]);
    }
}

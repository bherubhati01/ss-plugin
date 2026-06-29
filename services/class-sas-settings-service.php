<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAS_Settings_Service {
    public function get($key, $user_id = null, $default = null) {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'sas_settings';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE user_id = %d AND setting_key = %s",
            $user_id,
            $key
        ));

        if ($result === null) {
            return $default;
        }

        $decoded = json_decode($result, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $result;
    }

    public function set($key, $value, $user_id = null) {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'sas_settings';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND setting_key = %s",
            $user_id,
            $key
        ));

        $value_to_store = is_array($value) || is_object($value) ? json_encode($value) : $value;

        if ($existing) {
            return $wpdb->update(
                $table_name,
                ['setting_value' => $value_to_store],
                ['user_id' => $user_id, 'setting_key' => $key],
                ['%s'],
                ['%d', '%s']
            );
        } else {
            return $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'setting_key' => $key,
                    'setting_value' => $value_to_store,
                ],
                ['%d', '%s', '%s']
            );
        }
    }

    public function get_all($user_id = null) {
        global $wpdb;

        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }

        $table_name = $wpdb->prefix . 'sas_settings';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT setting_key, setting_value FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $settings = [];
        foreach ($results as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['setting_value'];
        }

        return $settings;
    }
}

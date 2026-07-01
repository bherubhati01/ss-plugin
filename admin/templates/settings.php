<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="settings">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Settings', 'social-auto-scheduler'); ?></h1>
    </div>

    <form id="sas-settings-form">
        <!-- Schedule Settings -->
        <div class="sas-card">
            <div class="sas-card__header">
                <h2><?php esc_html_e('Schedule Settings', 'social-auto-scheduler'); ?></h2>
            </div>
            <div class="sas-card__body">
                <div class="sas-settings-grid">
                    <div class="sas-field">
                        <label><?php esc_html_e('Timezone', 'social-auto-scheduler'); ?></label>
                        <input type="text" class="sas-input" readonly
                            value="<?php echo esc_attr( wp_timezone_string() ); ?>" />
                        <p class="sas-field__help">
                            <?php
                            printf(
                                /* translators: %s: link to WordPress general settings */
                                esc_html__('Uses your WordPress site timezone. Change it under %s.', 'social-auto-scheduler'),
                                '<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__('Settings → General', 'social-auto-scheduler') . '</a>'
                            );
                            ?>
                        </p>
                    </div>

                    <div class="sas-field">
                        <label for="sas-uploads-per-day"><?php esc_html_e('Uploads Per Day (Per Platform)', 'social-auto-scheduler'); ?></label>
                        <input type="number" id="sas-uploads-per-day" name="uploads_per_day" class="sas-input" min="1" max="15" value="1" />
                        <p class="sas-field__help"><?php esc_html_e('e.g. 2 = 2 YouTube + 2 Instagram per day. Max 15.', 'social-auto-scheduler'); ?></p>
                    </div>
                </div>

                <div id="sas-upload-times-container">
                    <!-- Upload time slots rendered by JS based on Uploads Per Day -->
                </div>

                <div class="sas-field">
                    <label><?php esc_html_e('Active Days', 'social-auto-scheduler'); ?></label>
                    <div class="sas-weekday-picker">
                        <?php
                        $days = [
                            'mon' => __('Mon', 'social-auto-scheduler'),
                            'tue' => __('Tue', 'social-auto-scheduler'),
                            'wed' => __('Wed', 'social-auto-scheduler'),
                            'thu' => __('Thu', 'social-auto-scheduler'),
                            'fri' => __('Fri', 'social-auto-scheduler'),
                            'sat' => __('Sat', 'social-auto-scheduler'),
                            'sun' => __('Sun', 'social-auto-scheduler'),
                        ];
                        foreach ($days as $val => $label) :
                        ?>
                            <label class="sas-weekday-btn">
                                <input type="checkbox" name="weekdays[]" value="<?php echo esc_attr($val); ?>" checked />
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Default Video Settings -->
        <div class="sas-card">
            <div class="sas-card__header">
                <h2><?php esc_html_e('Default Video Settings', 'social-auto-scheduler'); ?></h2>
            </div>
            <div class="sas-card__body">
                <div class="sas-field">
                    <label for="sas-default-description"><?php esc_html_e('Default Description', 'social-auto-scheduler'); ?></label>
                    <textarea id="sas-default-description" name="default_description" class="sas-textarea" rows="4"
                        placeholder="<?php esc_attr_e('Default description applied to all new videos…', 'social-auto-scheduler'); ?>"></textarea>
                </div>
                <div class="sas-field">
                    <label for="sas-default-tags"><?php esc_html_e('Default Tags', 'social-auto-scheduler'); ?></label>
                    <input type="text" id="sas-default-tags" name="default_tags" class="sas-input"
                        placeholder="<?php esc_attr_e('tag1, tag2, tag3', 'social-auto-scheduler'); ?>" />
                    <p class="sas-field__help"><?php esc_html_e('Comma-separated tags applied to all new videos.', 'social-auto-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <!-- YouTube Settings -->
        <div class="sas-card">
            <div class="sas-card__header">
                <h2>YouTube API</h2>
            </div>
            <div class="sas-card__body">
                <div class="sas-settings-grid">
                    <div class="sas-field">
                        <label for="sas-yt-client-id"><?php esc_html_e('Client ID', 'social-auto-scheduler'); ?></label>
                        <input type="text" id="sas-yt-client-id" name="youtube_client_id" class="sas-input" autocomplete="off" />
                    </div>
                    <div class="sas-field">
                        <label for="sas-yt-client-secret"><?php esc_html_e('Client Secret', 'social-auto-scheduler'); ?></label>
                        <input type="password" id="sas-yt-client-secret" name="youtube_client_secret" class="sas-input" autocomplete="off"
                            placeholder="<?php esc_attr_e('Leave blank to keep existing', 'social-auto-scheduler'); ?>" />
                        <p class="sas-field__help"><?php esc_html_e('Stored encrypted. Leave blank to keep the current value.', 'social-auto-scheduler'); ?></p>
                    </div>
                    <div class="sas-field">
                        <label for="sas-yt-category"><?php esc_html_e('Default Category ID', 'social-auto-scheduler'); ?></label>
                        <input type="text" id="sas-yt-category" name="youtube_category" class="sas-input" value="22" />
                        <p class="sas-field__help"><?php esc_html_e('22 = People & Blogs. See YouTube API docs for full list.', 'social-auto-scheduler'); ?></p>
                    </div>
                    <div class="sas-field">
                        <label for="sas-yt-privacy"><?php esc_html_e('Default Privacy', 'social-auto-scheduler'); ?></label>
                        <select id="sas-yt-privacy" name="youtube_privacy" class="sas-select">
                            <option value="public"><?php esc_html_e('Public', 'social-auto-scheduler'); ?></option>
                            <option value="unlisted"><?php esc_html_e('Unlisted', 'social-auto-scheduler'); ?></option>
                            <option value="private"><?php esc_html_e('Private', 'social-auto-scheduler'); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instagram Settings -->
        <div class="sas-card">
            <div class="sas-card__header">
                <h2>Instagram / Meta API</h2>
            </div>
            <div class="sas-card__body">
                <div class="sas-settings-grid">
                    <div class="sas-field">
                        <label for="sas-ig-app-id"><?php esc_html_e('Instagram App ID', 'social-auto-scheduler'); ?></label>
                        <input type="text" id="sas-ig-app-id" name="instagram_app_id" class="sas-input" autocomplete="off"
                            placeholder="e.g. 1234567890123456" />
                        <p class="sas-field__help">
                            <?php esc_html_e('Found on the "API setup with Instagram login" page inside your Meta app (Instagram product section). Must be digits only. This may differ from the App ID shown at the top of the Meta dashboard.', 'social-auto-scheduler'); ?>
                        </p>
                    </div>
                    <div class="sas-field">
                        <label for="sas-ig-app-secret"><?php esc_html_e('Instagram App Secret', 'social-auto-scheduler'); ?></label>
                        <input type="password" id="sas-ig-app-secret" name="instagram_app_secret" class="sas-input" autocomplete="off"
                            placeholder="<?php esc_attr_e('Leave blank to keep existing', 'social-auto-scheduler'); ?>" />
                        <p class="sas-field__help"><?php esc_html_e('Stored encrypted. Found on the same "API setup with Instagram login" page alongside the Instagram App ID.', 'social-auto-scheduler'); ?></p>
                    </div>
                </div>
                <div class="sas-field" style="margin-top:12px;">
                    <label for="sas-ig-config-id">
                        <?php esc_html_e('Business Login Configuration ID', 'social-auto-scheduler'); ?>
                        <span style="font-weight:400;color:#888;">(<?php esc_html_e('new Meta UI — recommended', 'social-auto-scheduler'); ?>)</span>
                    </label>
                    <input type="text" id="sas-ig-config-id" name="instagram_config_id" class="sas-input" autocomplete="off"
                        placeholder="e.g. 1234567890123456" />
                    <p class="sas-field__help">
                        <?php esc_html_e('In the new Meta Developer Console: Instagram → API setup with Instagram login → Business Login → "Create new configuration". Copy the Configuration ID here. When set, this replaces the scope parameter and is the recommended approach for 2024+ apps. Leave blank to use the legacy scope approach.', 'social-auto-scheduler'); ?>
                    </p>
                </div>
                <div class="sas-field" style="margin-top:12px;">
                    <label><?php esc_html_e('Redirect URI (register this in Meta — no extra parameters)', 'social-auto-scheduler'); ?></label>
                    <input type="text" class="sas-input" readonly
                        value="<?php echo esc_attr( admin_url( 'admin.php' ) ); ?>"
                        onclick="this.select()" style="cursor:pointer;" />
                    <p class="sas-field__help"><?php esc_html_e('Click to select and copy. Register exactly this URL in your Business Login Configuration. Meta always redirects to its registered URI regardless of what is requested, so this must match exactly.', 'social-auto-scheduler'); ?></p>
                </div>
            </div>
        </div>

        <div class="sas-form-actions">
            <button type="submit" class="sas-btn sas-btn--primary sas-btn--lg" id="sas-save-settings-btn">
                <?php esc_html_e('Save Settings', 'social-auto-scheduler'); ?>
            </button>
        </div>
    </form>
</div>

<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Two-step upload wizard — Content (post type, destinations, file) then
 * Details (caption/description/tags, schedule, Publish Now/Schedule) —
 * mirroring the dashboard's own New Video dialog (ScheduleVideoDialog)
 * step-for-step. Included once per admin page that offers an upload
 * trigger (Dashboard's Quick Upload, Videos page's Upload Videos button);
 * each page only includes it once, so the plain (unsuffixed) ids below
 * never collide within a page.
 */
?>
<div id="sas-upload-modal" class="sas-modal" hidden>
    <div class="sas-modal__backdrop"></div>
    <div class="sas-modal__content sas-modal__content--wide">
        <div class="sas-modal__header">
            <h3><?php esc_html_e('Upload Video', 'social-auto-scheduler'); ?></h3>
            <button type="button" class="sas-modal__close sas-upload-modal__close" id="sas-upload-modal-close">&times;</button>
        </div>

        <div class="sas-modal__body">
            <!-- Step indicator -->
            <div class="sas-wizard-steps">
                <div class="sas-wizard-step is-active" data-step="content">
                    <span class="sas-wizard-step__dot">1</span>
                    <span class="sas-wizard-step__label"><?php esc_html_e('Content', 'social-auto-scheduler'); ?></span>
                </div>
                <div class="sas-wizard-step__divider"></div>
                <div class="sas-wizard-step" data-step="details">
                    <span class="sas-wizard-step__dot">2</span>
                    <span class="sas-wizard-step__label"><?php esc_html_e('Details', 'social-auto-scheduler'); ?></span>
                </div>
            </div>

            <!-- Step 1: Content -->
            <div class="sas-wizard-panel" data-panel="content">
                <div class="sas-platform-selector" id="sas-upload-content-type-selector">
                    <span class="sas-platform-selector__label"><?php esc_html_e('Post type:', 'social-auto-scheduler'); ?></span>
                    <label class="sas-platform-toggle">
                        <input type="radio" class="sas-upload-content-type" name="sas_upload_content_type" value="reel" checked />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--reel">
                            <?php esc_html_e('Reel / Video', 'social-auto-scheduler'); ?>
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="radio" class="sas-upload-content-type" name="sas_upload_content_type" value="story" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--story">
                            <?php esc_html_e('Story', 'social-auto-scheduler'); ?>
                        </span>
                    </label>
                    <span class="sas-field__help" id="sas-content-type-help-upload" style="display:none;flex-basis:100%;">
                        <?php esc_html_e('Stories can only publish to Instagram, and publish without a caption.', 'social-auto-scheduler'); ?>
                    </span>
                </div>

                <div class="sas-platform-selector" id="sas-upload-platform-selector">
                    <span class="sas-platform-selector__label"><?php esc_html_e('Publish to:', 'social-auto-scheduler'); ?></span>
                    <label class="sas-platform-toggle" id="sas-upload-platform-toggle-youtube">
                        <input type="checkbox" class="sas-upload-platform" name="sas_upload_platforms[]" value="youtube" checked />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--youtube">
                            <img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/youtube.svg' ); ?>" width="16" height="16" alt="" style="vertical-align:middle;object-fit:contain;">
                            YouTube
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="checkbox" class="sas-upload-platform" name="sas_upload_platforms[]" value="instagram" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--instagram">
                            <img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/instagram.svg' ); ?>" width="16" height="16" alt="" style="vertical-align:middle;object-fit:contain;">
                            Instagram
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="checkbox" class="sas-upload-platform" name="sas_upload_platforms[]" value="facebook" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--facebook">
                            <img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/facebook.svg' ); ?>" width="16" height="16" alt="" style="vertical-align:middle;object-fit:contain;">
                            Facebook
                        </span>
                    </label>
                </div>
                <p class="sas-field__help" id="sas-upload-accounts-error" style="display:none;color:var(--sas-danger);"></p>

                <div id="sas-upload-area" class="sas-upload-area">
                    <div class="sas-upload-area__icon"><span class="dashicons dashicons-cloud-upload"></span></div>
                    <p class="sas-upload-area__title"><?php esc_html_e('Drop a video here or click to browse', 'social-auto-scheduler'); ?></p>
                    <p class="sas-upload-area__hint"><?php esc_html_e('MP4, MOV, AVI, WEBM — max 5 GB', 'social-auto-scheduler'); ?></p>
                    <input type="file" id="sas-file-input" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,.mp4,.mov,.avi,.webm" hidden />
                </div>
                <div id="sas-upload-file-preview" class="sas-upload-item" style="display:none;"></div>
            </div>

            <!-- Step 2: Details -->
            <div class="sas-wizard-panel" data-panel="details" hidden>
                <div class="sas-field" id="sas-upload-caption-field">
                    <label for="sas-upload-caption"><?php esc_html_e('Caption / Title', 'social-auto-scheduler'); ?></label>
                    <textarea id="sas-upload-caption" class="sas-textarea" rows="3" placeholder="<?php esc_attr_e('Used as the YouTube title and Instagram caption…', 'social-auto-scheduler'); ?>"></textarea>
                </div>
                <div class="sas-field" id="sas-upload-description-field">
                    <label for="sas-upload-description"><?php esc_html_e('Description', 'social-auto-scheduler'); ?></label>
                    <textarea id="sas-upload-description" class="sas-textarea" rows="2" placeholder="<?php esc_attr_e('Optional extended description', 'social-auto-scheduler'); ?>"></textarea>
                </div>
                <div class="sas-field" id="sas-upload-tags-field">
                    <label for="sas-upload-tags"><?php esc_html_e('Tags (comma separated)', 'social-auto-scheduler'); ?></label>
                    <input type="text" id="sas-upload-tags" class="sas-input" placeholder="travel, lifestyle, vlog" />
                </div>
                <p class="sas-field__help" id="sas-upload-story-note" style="display:none;">
                    <?php esc_html_e('Stories don’t support captions, descriptions, or tags.', 'social-auto-scheduler'); ?>
                </p>
                <div class="sas-field">
                    <label for="sas-upload-schedule"><?php esc_html_e('Schedule Date & Time', 'social-auto-scheduler'); ?></label>
                    <input type="datetime-local" id="sas-upload-schedule" class="sas-input" />
                    <p class="sas-field__help"><?php esc_html_e('Required for Schedule — ignored when you Publish Now.', 'social-auto-scheduler'); ?></p>
                </div>
            </div>

            <!-- Upload progress — step-independent: the real upload only
                 starts once Publish Now/Schedule is clicked from the Details
                 step, so this must render regardless of which panel is shown. -->
            <div id="sas-upload-progress-wrap" class="sas-upload-list" style="display:none;"></div>
        </div>

        <div class="sas-modal__footer">
            <button type="button" class="sas-btn sas-btn--secondary" id="sas-upload-cancel"><?php esc_html_e('Cancel', 'social-auto-scheduler'); ?></button>
            <button type="button" class="sas-btn sas-btn--secondary" id="sas-upload-back" style="display:none;"><?php esc_html_e('Back', 'social-auto-scheduler'); ?></button>
            <button type="button" class="sas-btn sas-btn--primary" id="sas-upload-next"><?php esc_html_e('Next', 'social-auto-scheduler'); ?></button>
            <button type="button" class="sas-btn sas-btn--publish-now" id="sas-upload-publish-now" style="display:none;">
                <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e('Publish Now', 'social-auto-scheduler'); ?>
            </button>
            <button type="button" class="sas-btn sas-btn--primary" id="sas-upload-schedule-btn" style="display:none;">
                <?php esc_html_e('Schedule', 'social-auto-scheduler'); ?>
            </button>
        </div>
    </div>
</div>

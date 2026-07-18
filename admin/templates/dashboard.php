<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="dashboard">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Soulitam Social', 'social-auto-scheduler'); ?></h1>
        <button class="sas-btn sas-btn--primary" id="sas-quick-upload-btn">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Upload Videos', 'social-auto-scheduler'); ?>
        </button>
    </div>

    <!-- Stats Grid -->
    <div class="sas-stats-grid" id="sas-stats-grid">
        <div class="sas-stat-card" data-stat="total">
            <div class="sas-stat-icon"><span class="dashicons dashicons-format-video"></span></div>
            <div class="sas-stat-number" id="sas-total">—</div>
            <div class="sas-stat-label"><?php esc_html_e('Total Videos', 'social-auto-scheduler'); ?></div>
        </div>
        <div class="sas-stat-card sas-stat-card--scheduled" data-stat="scheduled">
            <div class="sas-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
            <div class="sas-stat-number" id="sas-scheduled">—</div>
            <div class="sas-stat-label"><?php esc_html_e('Scheduled', 'social-auto-scheduler'); ?></div>
        </div>
        <div class="sas-stat-card sas-stat-card--queued" data-stat="queued">
            <div class="sas-stat-icon"><span class="dashicons dashicons-clock"></span></div>
            <div class="sas-stat-number" id="sas-queued">—</div>
            <div class="sas-stat-label"><?php esc_html_e('In Queue', 'social-auto-scheduler'); ?></div>
        </div>
        <div class="sas-stat-card sas-stat-card--published" data-stat="published">
            <div class="sas-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
            <div class="sas-stat-number" id="sas-published">—</div>
            <div class="sas-stat-label"><?php esc_html_e('Published', 'social-auto-scheduler'); ?></div>
        </div>
        <div class="sas-stat-card sas-stat-card--failed" data-stat="failed">
            <div class="sas-stat-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="sas-stat-number" id="sas-failed">—</div>
            <div class="sas-stat-label"><?php esc_html_e('Failed', 'social-auto-scheduler'); ?></div>
        </div>
        <div class="sas-stat-card" data-stat="storage">
            <div class="sas-stat-icon"><span class="dashicons dashicons-cloud"></span></div>
            <div class="sas-stat-number sas-stat-number--sm" id="sas-storage">—</div>
            <div class="sas-stat-label"><?php esc_html_e('Storage Used', 'social-auto-scheduler'); ?></div>
        </div>
    </div>

    <!-- Next Upload Countdown -->
    <div class="sas-row">
        <div class="sas-card sas-card--half">
            <div class="sas-card__header">
                <h2><?php esc_html_e('Next Upload', 'social-auto-scheduler'); ?></h2>
            </div>
            <div class="sas-card__body" id="sas-next-upload">
                <div class="sas-loading-skeleton"></div>
            </div>
        </div>

        <div class="sas-card sas-card--half">
            <div class="sas-card__header">
                <h2><?php esc_html_e('Quick Upload', 'social-auto-scheduler'); ?></h2>
            </div>
            <div class="sas-card__body">
                <!-- Post type selector -->
                <div class="sas-platform-selector" id="sas-content-type-selector-dash">
                    <span class="sas-platform-selector__label"><?php esc_html_e('Post type:', 'social-auto-scheduler'); ?></span>
                    <label class="sas-platform-toggle">
                        <input type="radio" class="sas-upload-content-type" name="content_type_dash" value="reel" checked />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--reel">
                            <?php esc_html_e('Reel / Video', 'social-auto-scheduler'); ?>
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="radio" class="sas-upload-content-type" name="content_type_dash" value="story" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--story">
                            <?php esc_html_e('Story', 'social-auto-scheduler'); ?>
                        </span>
                    </label>
                    <span class="sas-field__help" id="sas-content-type-help-dash" style="display:none;flex-basis:100%;">
                        <?php esc_html_e('Stories are Instagram-only and publish without a caption.', 'social-auto-scheduler'); ?>
                    </span>
                </div>

                <!-- Platform selector -->
                <div class="sas-platform-selector" id="sas-platform-selector-dash">
                    <span class="sas-platform-selector__label"><?php esc_html_e('Publish to:', 'social-auto-scheduler'); ?></span>
                    <label class="sas-platform-toggle">
                        <input type="checkbox" class="sas-upload-platform" name="platforms[]" value="youtube" checked />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--youtube">
                            <img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/youtube.svg' ); ?>" width="16" height="16" alt="" style="vertical-align:middle;object-fit:contain;">
                            YouTube
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="checkbox" class="sas-upload-platform" name="platforms[]" value="instagram" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--instagram">
                            <img src="<?php echo esc_url( SAS_PLUGIN_URL . 'assets/images/instagram.svg' ); ?>" width="16" height="16" alt="" style="vertical-align:middle;object-fit:contain;">
                            Instagram
                        </span>
                    </label>
                </div>

                <div id="sas-upload-area" class="sas-upload-area">
                    <div class="sas-upload-area__icon"><span class="dashicons dashicons-cloud-upload"></span></div>
                    <p class="sas-upload-area__title"><?php esc_html_e('Drop videos here or click to browse', 'social-auto-scheduler'); ?></p>
                    <p class="sas-upload-area__hint"><?php esc_html_e('MP4, MOV — max 5 GB each', 'social-auto-scheduler'); ?></p>
                    <input type="file" id="sas-file-input" accept="video/mp4,video/quicktime,.mp4,.mov" multiple hidden />
                </div>
                <div id="sas-upload-list" class="sas-upload-list"></div>
            </div>
        </div>
    </div>

    <!-- Recent Videos -->
    <div class="sas-card">
        <div class="sas-card__header">
            <h2><?php esc_html_e('Recent Videos', 'social-auto-scheduler'); ?></h2>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sas-videos')); ?>" class="sas-link">
                <?php esc_html_e('View all', 'social-auto-scheduler'); ?> &rarr;
            </a>
        </div>
        <div class="sas-card__body">
            <div id="sas-recent-videos">
                <div class="sas-loading-skeleton"></div>
            </div>
        </div>
    </div>
</div>

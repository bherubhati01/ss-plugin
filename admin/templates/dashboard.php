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
                            <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle"><path fill="currentColor" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            YouTube
                        </span>
                    </label>
                    <label class="sas-platform-toggle">
                        <input type="checkbox" class="sas-upload-platform" name="platforms[]" value="instagram" />
                        <span class="sas-platform-toggle__inner sas-platform-toggle__inner--instagram">
                            <svg viewBox="0 0 24 24" width="16" height="16" style="vertical-align:middle"><path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
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

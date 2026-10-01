<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="dashboard">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Meavr', 'social-auto-scheduler'); ?></h1>
        <button type="button" class="sas-btn sas-btn--primary sas-upload-trigger">
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

    <!-- Announcements -->
    <div class="sas-card">
        <div class="sas-card__header">
            <h2><?php esc_html_e('Announcements', 'social-auto-scheduler'); ?></h2>
        </div>
        <div class="sas-card__body" id="sas-notifications">
            <div class="sas-loading-skeleton"></div>
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
                <p class="sas-text-muted"><?php esc_html_e('Upload a video, choose where it publishes, and set its details — same flow as the dashboard.', 'social-auto-scheduler'); ?></p>
                <button type="button" class="sas-btn sas-btn--primary sas-upload-trigger">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload Video', 'social-auto-scheduler'); ?>
                </button>
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

<?php require SAS_PLUGIN_DIR . 'admin/templates/partials/upload-wizard-modal.php'; ?>

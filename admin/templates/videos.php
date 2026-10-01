<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="videos">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Videos', 'social-auto-scheduler'); ?></h1>
        <button type="button" class="sas-btn sas-btn--primary sas-upload-trigger">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Upload Videos', 'social-auto-scheduler'); ?>
        </button>
    </div>

    <!-- Filters & Bulk Actions -->
    <div class="sas-card">
        <div class="sas-card__body">
            <div class="sas-toolbar">
                <div class="sas-toolbar__left">
                    <input type="text" id="sas-search" class="sas-input" placeholder="<?php esc_attr_e('Search videos…', 'social-auto-scheduler'); ?>" />
                    <select id="sas-status-filter" class="sas-select">
                        <option value=""><?php esc_html_e('All Statuses', 'social-auto-scheduler'); ?></option>
                        <option value="draft"><?php esc_html_e('Draft', 'social-auto-scheduler'); ?></option>
                        <option value="queued"><?php esc_html_e('Queued', 'social-auto-scheduler'); ?></option>
                        <option value="scheduled"><?php esc_html_e('Scheduled', 'social-auto-scheduler'); ?></option>
                        <option value="publishing"><?php esc_html_e('Publishing', 'social-auto-scheduler'); ?></option>
                        <option value="published"><?php esc_html_e('Published', 'social-auto-scheduler'); ?></option>
                        <option value="partially_published"><?php esc_html_e('Partially Published', 'social-auto-scheduler'); ?></option>
                        <option value="failed"><?php esc_html_e('Failed', 'social-auto-scheduler'); ?></option>
                    </select>
                    <select id="sas-platform-filter" class="sas-select">
                        <option value=""><?php esc_html_e('All Platforms', 'social-auto-scheduler'); ?></option>
                        <option value="youtube">YouTube</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                    </select>
                </div>
                <div class="sas-toolbar__right">
                    <select id="sas-bulk-action" class="sas-select">
                        <option value=""><?php esc_html_e('Bulk Action', 'social-auto-scheduler'); ?></option>
                        <option value="schedule"><?php esc_html_e('Schedule', 'social-auto-scheduler'); ?></option>
                        <option value="reschedule"><?php esc_html_e('Reschedule', 'social-auto-scheduler'); ?></option>
                        <option value="cancel"><?php esc_html_e('Cancel', 'social-auto-scheduler'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', 'social-auto-scheduler'); ?></option>
                    </select>
                    <button id="sas-bulk-apply" class="sas-btn sas-btn--secondary"><?php esc_html_e('Apply', 'social-auto-scheduler'); ?></button>
                </div>
            </div>

            <div class="sas-table-wrap">
                <table class="sas-table" id="sas-videos-table">
                    <thead>
                        <tr>
                            <th class="sas-col-check"><input type="checkbox" id="sas-select-all" /></th>
                            <th class="sas-col-title"><?php esc_html_e('Video', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Destination', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Scheduled', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Created', 'social-auto-scheduler'); ?></th>
                            <th style="min-width:200px"><?php esc_html_e('Actions', 'social-auto-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sas-videos-table-body">
                        <tr><td colspan="7" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="sas-pagination" id="sas-pagination"></div>
        </div>
    </div>
</div>

<!-- Edit Video Modal -->
<div id="sas-video-modal" class="sas-modal" hidden>
    <div class="sas-modal__backdrop"></div>
    <div class="sas-modal__content">
        <div class="sas-modal__header">
            <h3><?php esc_html_e('Edit Video', 'social-auto-scheduler'); ?></h3>
            <button class="sas-modal__close">&times;</button>
        </div>
        <div class="sas-modal__body">
            <input type="hidden" id="sas-edit-id" />
            <div class="sas-field">
                <label><?php esc_html_e('Title', 'social-auto-scheduler'); ?></label>
                <input type="text" id="sas-edit-title" class="sas-input" />
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Description', 'social-auto-scheduler'); ?></label>
                <textarea id="sas-edit-description" class="sas-textarea" rows="4"></textarea>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Tags (comma separated)', 'social-auto-scheduler'); ?></label>
                <input type="text" id="sas-edit-tags" class="sas-input" />
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Scheduled Date', 'social-auto-scheduler'); ?></label>
                <input type="datetime-local" id="sas-edit-date" class="sas-input" />
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Platform', 'social-auto-scheduler'); ?></label>
                <select id="sas-edit-platform" class="sas-select" disabled>
                    <option value="youtube">YouTube</option>
                    <option value="instagram">Instagram</option>
                    <option value="facebook">Facebook</option>
                </select>
                <p class="sas-field__help"><?php esc_html_e('Platform cannot be changed after a video has been added — delete and re-upload to use a different platform.', 'social-auto-scheduler'); ?></p>
            </div>
        </div>
        <div class="sas-modal__footer">
            <button class="sas-btn sas-btn--secondary sas-modal__close"><?php esc_html_e('Cancel', 'social-auto-scheduler'); ?></button>
            <button class="sas-btn sas-btn--primary" id="sas-save-video"><?php esc_html_e('Save Changes', 'social-auto-scheduler'); ?></button>
        </div>
    </div>
</div>

<?php require SAS_PLUGIN_DIR . 'admin/templates/partials/upload-wizard-modal.php'; ?>

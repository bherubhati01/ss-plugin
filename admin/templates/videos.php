<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="videos">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Videos', 'social-auto-scheduler'); ?></h1>
        <button class="sas-btn sas-btn--primary" id="sas-upload-btn-videos">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Upload Videos', 'social-auto-scheduler'); ?>
        </button>
    </div>

    <!-- Upload area (hidden by default) -->
    <div class="sas-card" id="sas-upload-panel" style="display:none;">
        <div class="sas-card__body">
            <!-- Platform selector -->
            <div class="sas-platform-selector" id="sas-platform-selector-videos">
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

            <div id="sas-upload-area-videos" class="sas-upload-area">
                <div class="sas-upload-area__icon">&#128247;</div>
                <p class="sas-upload-area__title"><?php esc_html_e('Drop videos here or click to browse', 'social-auto-scheduler'); ?></p>
                <p class="sas-upload-area__hint"><?php esc_html_e('MP4, MOV — max 5 GB each', 'social-auto-scheduler'); ?></p>
                <input type="file" id="sas-file-input-videos" accept="video/mp4,video/quicktime,.mp4,.mov" multiple hidden />
            </div>
            <div id="sas-upload-list-videos" class="sas-upload-list"></div>
        </div>
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
                        <option value="failed"><?php esc_html_e('Failed', 'social-auto-scheduler'); ?></option>
                    </select>
                    <select id="sas-platform-filter" class="sas-select">
                        <option value=""><?php esc_html_e('All Platforms', 'social-auto-scheduler'); ?></option>
                        <option value="youtube">YouTube</option>
                        <option value="instagram">Instagram</option>
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
                            <th class="sas-col-thumb"><?php esc_html_e('Thumbnail', 'social-auto-scheduler'); ?></th>
                            <th class="sas-col-title sas-sortable" data-sort="title"><?php esc_html_e('Title', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Platform', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Status', 'social-auto-scheduler'); ?></th>
                            <th class="sas-sortable" data-sort="publish_date"><?php esc_html_e('Publish Date', 'social-auto-scheduler'); ?></th>
                            <th class="sas-sortable" data-sort="duration"><?php esc_html_e('Duration', 'social-auto-scheduler'); ?></th>
                            <th class="sas-sortable" data-sort="file_size"><?php esc_html_e('Size', 'social-auto-scheduler'); ?></th>
                            <th style="min-width:200px"><?php esc_html_e('Actions', 'social-auto-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sas-videos-table-body">
                        <tr><td colspan="9" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>
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

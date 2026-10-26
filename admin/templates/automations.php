<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Automations — two tabs, same shape as the dashboard's Comment Automation /
 * DM Reply tabs: Comment Automation (comment -> public reply + DM) and
 * DM Reply (inbound DM -> auto-reply, no post/comment involved). Keywords
 * and reply variants use a plain comma/newline-separated text field rather
 * than a chip-input widget — same convention this plugin's other forms
 * already use (see the Edit Video modal's Tags field), not a new UI
 * pattern just for this page.
 */
?>
<div class="sas-wrap" data-page="automations">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Automations', 'social-auto-scheduler'); ?></h1>
    </div>

    <div class="sas-tabs">
        <button type="button" class="sas-tab is-active" data-tab="comment"><?php esc_html_e('Comment Automation', 'social-auto-scheduler'); ?></button>
        <button type="button" class="sas-tab" data-tab="dm"><?php esc_html_e('DM Reply', 'social-auto-scheduler'); ?></button>
    </div>

    <!-- Comment Automation -->
    <div class="sas-tab-panel is-active" data-tab-panel="comment">
        <div class="sas-card">
            <div class="sas-card__body">
                <div class="sas-toolbar">
                    <div class="sas-toolbar__left">
                        <select id="sas-automation-account-filter" class="sas-select">
                            <option value=""><?php esc_html_e('All Accounts', 'social-auto-scheduler'); ?></option>
                        </select>
                    </div>
                    <div class="sas-toolbar__right">
                        <button type="button" class="sas-btn sas-btn--primary" id="sas-new-automation-rule">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('New Automation', 'social-auto-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="sas-table-wrap">
                    <table class="sas-table" id="sas-automation-rules-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Account', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Trigger', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Posts', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Sends', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Active', 'social-auto-scheduler'); ?></th>
                                <th style="min-width:180px"><?php esc_html_e('Actions', 'social-auto-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="sas-automation-rules-table-body">
                            <tr><td colspan="6" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- DM Reply -->
    <div class="sas-tab-panel" data-tab-panel="dm" hidden>
        <div class="sas-card">
            <div class="sas-card__body">
                <div class="sas-toolbar">
                    <div class="sas-toolbar__left">
                        <select id="sas-dm-automation-account-filter" class="sas-select">
                            <option value=""><?php esc_html_e('All Accounts', 'social-auto-scheduler'); ?></option>
                        </select>
                    </div>
                    <div class="sas-toolbar__right">
                        <button type="button" class="sas-btn sas-btn--primary" id="sas-new-dm-automation-rule">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('New DM Automation', 'social-auto-scheduler'); ?>
                        </button>
                    </div>
                </div>

                <div class="sas-table-wrap">
                    <table class="sas-table" id="sas-dm-automation-rules-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Account', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Trigger', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Replies', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Sends', 'social-auto-scheduler'); ?></th>
                                <th><?php esc_html_e('Active', 'social-auto-scheduler'); ?></th>
                                <th style="min-width:180px"><?php esc_html_e('Actions', 'social-auto-scheduler'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="sas-dm-automation-rules-table-body">
                            <tr><td colspan="6" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comment Automation Rule modal -->
<div id="sas-automation-modal" class="sas-modal" hidden>
    <div class="sas-modal__backdrop"></div>
    <div class="sas-modal__content sas-modal__content--wide">
        <div class="sas-modal__header">
            <h3 id="sas-automation-modal-title"><?php esc_html_e('New Automation', 'social-auto-scheduler'); ?></h3>
            <button class="sas-modal__close">&times;</button>
        </div>
        <div class="sas-modal__body">
            <input type="hidden" id="sas-automation-id" />
            <div class="sas-field">
                <label><?php esc_html_e('Account', 'social-auto-scheduler'); ?></label>
                <select id="sas-automation-account" class="sas-select"></select>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Posts (one URL per line)', 'social-auto-scheduler'); ?></label>
                <textarea id="sas-automation-posts" class="sas-textarea" rows="3" placeholder="https://www.instagram.com/p/…"></textarea>
                <p class="sas-field__help" id="sas-automation-posts-hint"><?php esc_html_e('Paste the post/Reel link(s) this rule should watch.', 'social-auto-scheduler'); ?></p>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Trigger', 'social-auto-scheduler'); ?></label>
                <select id="sas-automation-trigger" class="sas-select">
                    <option value="keyword"><?php esc_html_e('Specific keyword(s)', 'social-auto-scheduler'); ?></option>
                    <option value="any_comment"><?php esc_html_e('Any comment', 'social-auto-scheduler'); ?></option>
                </select>
            </div>
            <div class="sas-field" id="sas-automation-keywords-field">
                <label><?php esc_html_e('Keywords (comma separated)', 'social-auto-scheduler'); ?></label>
                <input type="text" id="sas-automation-keywords" class="sas-input" placeholder="price, info, link" />
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Public replies (one per line, up to 5)', 'social-auto-scheduler'); ?></label>
                <textarea id="sas-automation-public-replies" class="sas-textarea" rows="3" placeholder="Check your DMs! 👋"></textarea>
                <p class="sas-field__help"><?php esc_html_e('A random line is picked per matched comment, so replies don’t look identically bot-written.', 'social-auto-scheduler'); ?></p>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('DM message', 'social-auto-scheduler'); ?></label>
                <textarea id="sas-automation-dm-message" class="sas-textarea" rows="3" placeholder="Thanks for your comment! Here are the details…"></textarea>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('DM button (optional)', 'social-auto-scheduler'); ?></label>
                <div class="sas-field-row">
                    <input type="text" id="sas-automation-dm-button-label" class="sas-input" placeholder="<?php esc_attr_e('Shop Now', 'social-auto-scheduler'); ?>" maxlength="20" />
                    <input type="url" id="sas-automation-dm-button-url" class="sas-input" placeholder="https://your-link.com" />
                </div>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Stop after N sends (optional)', 'social-auto-scheduler'); ?></label>
                <input type="number" id="sas-automation-max-sends" class="sas-input" min="1" max="100000" style="max-width:140px" />
            </div>
            <div class="sas-field" id="sas-automation-active-field" style="display:none;">
                <label class="sas-checkbox-label">
                    <input type="checkbox" id="sas-automation-active" />
                    <?php esc_html_e('Active', 'social-auto-scheduler'); ?>
                </label>
            </div>
        </div>
        <div class="sas-modal__footer">
            <button type="button" class="sas-btn sas-btn--ghost" id="sas-automation-delete" style="display:none;margin-right:auto;">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'social-auto-scheduler'); ?>
            </button>
            <button class="sas-btn sas-btn--secondary sas-modal__close"><?php esc_html_e('Cancel', 'social-auto-scheduler'); ?></button>
            <button class="sas-btn sas-btn--primary" id="sas-save-automation"><?php esc_html_e('Save', 'social-auto-scheduler'); ?></button>
        </div>
    </div>
</div>

<!-- DM Reply Rule modal -->
<div id="sas-dm-automation-modal" class="sas-modal" hidden>
    <div class="sas-modal__backdrop"></div>
    <div class="sas-modal__content">
        <div class="sas-modal__header">
            <h3 id="sas-dm-automation-modal-title"><?php esc_html_e('New DM Automation', 'social-auto-scheduler'); ?></h3>
            <button class="sas-modal__close">&times;</button>
        </div>
        <div class="sas-modal__body">
            <input type="hidden" id="sas-dm-automation-id" />
            <div class="sas-field">
                <label><?php esc_html_e('Account', 'social-auto-scheduler'); ?></label>
                <select id="sas-dm-automation-account" class="sas-select"></select>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Trigger', 'social-auto-scheduler'); ?></label>
                <select id="sas-dm-automation-trigger" class="sas-select">
                    <option value="keyword"><?php esc_html_e('Specific keyword(s)', 'social-auto-scheduler'); ?></option>
                    <option value="any_message"><?php esc_html_e('Any message', 'social-auto-scheduler'); ?></option>
                </select>
            </div>
            <div class="sas-field" id="sas-dm-automation-keywords-field">
                <label><?php esc_html_e('Keywords (comma separated)', 'social-auto-scheduler'); ?></label>
                <input type="text" id="sas-dm-automation-keywords" class="sas-input" placeholder="price, info, order" />
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Auto-replies (one per line, up to 5)', 'social-auto-scheduler'); ?></label>
                <textarea id="sas-dm-automation-replies" class="sas-textarea" rows="3" placeholder="Thanks for reaching out! We'll get back to you shortly 😊"></textarea>
                <p class="sas-field__help"><?php esc_html_e('A random line is picked per matched DM, so replies don’t look identically bot-written.', 'social-auto-scheduler'); ?></p>
            </div>
            <div class="sas-field">
                <label><?php esc_html_e('Stop after N sends (optional)', 'social-auto-scheduler'); ?></label>
                <input type="number" id="sas-dm-automation-max-sends" class="sas-input" min="1" max="100000" style="max-width:140px" />
            </div>
            <div class="sas-field" id="sas-dm-automation-active-field" style="display:none;">
                <label class="sas-checkbox-label">
                    <input type="checkbox" id="sas-dm-automation-active" />
                    <?php esc_html_e('Active', 'social-auto-scheduler'); ?>
                </label>
            </div>
        </div>
        <div class="sas-modal__footer">
            <button type="button" class="sas-btn sas-btn--ghost" id="sas-dm-automation-delete" style="display:none;margin-right:auto;">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'social-auto-scheduler'); ?>
            </button>
            <button class="sas-btn sas-btn--secondary sas-modal__close"><?php esc_html_e('Cancel', 'social-auto-scheduler'); ?></button>
            <button class="sas-btn sas-btn--primary" id="sas-save-dm-automation"><?php esc_html_e('Save', 'social-auto-scheduler'); ?></button>
        </div>
    </div>
</div>

<!-- Activity modal — shared between both rule types -->
<div id="sas-automation-activity-modal" class="sas-modal" hidden>
    <div class="sas-modal__backdrop"></div>
    <div class="sas-modal__content sas-modal__content--wide">
        <div class="sas-modal__header">
            <h3><?php esc_html_e('Activity', 'social-auto-scheduler'); ?></h3>
            <button class="sas-modal__close">&times;</button>
        </div>
        <div class="sas-modal__body">
            <div class="sas-table-wrap">
                <table class="sas-table" id="sas-automation-activity-table">
                    <thead><tr id="sas-automation-activity-head"></tr></thead>
                    <tbody id="sas-automation-activity-body"></tbody>
                </table>
            </div>
        </div>
        <div class="sas-modal__footer">
            <button class="sas-btn sas-btn--secondary sas-modal__close"><?php esc_html_e('Close', 'social-auto-scheduler'); ?></button>
        </div>
    </div>
</div>

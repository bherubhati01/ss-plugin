<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="logs">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Logs', 'social-auto-scheduler'); ?></h1>
        <div class="sas-page-header__actions">
            <select id="sas-log-level-filter" class="sas-select">
                <option value=""><?php esc_html_e('All Levels', 'social-auto-scheduler'); ?></option>
                <option value="info"><?php esc_html_e('Info', 'social-auto-scheduler'); ?></option>
                <option value="warning"><?php esc_html_e('Warning', 'social-auto-scheduler'); ?></option>
                <option value="error"><?php esc_html_e('Error', 'social-auto-scheduler'); ?></option>
                <option value="debug"><?php esc_html_e('Debug', 'social-auto-scheduler'); ?></option>
            </select>
            <button id="sas-clear-logs" class="sas-btn sas-btn--danger">
                <?php esc_html_e('Clear Logs', 'social-auto-scheduler'); ?>
            </button>
        </div>
    </div>

    <div class="sas-card">
        <div class="sas-card__body">
            <div class="sas-table-wrap">
                <table class="sas-table" id="sas-logs-table">
                    <thead>
                        <tr>
                            <th style="width:160px"><?php esc_html_e('Date', 'social-auto-scheduler'); ?></th>
                            <th style="width:90px"><?php esc_html_e('Level', 'social-auto-scheduler'); ?></th>
                            <th style="width:180px"><?php esc_html_e('Action', 'social-auto-scheduler'); ?></th>
                            <th><?php esc_html_e('Message', 'social-auto-scheduler'); ?></th>
                            <th style="width:80px"><?php esc_html_e('Video', 'social-auto-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sas-logs-table-body">
                        <tr><td colspan="5" class="sas-table__loading"><div class="sas-loading-skeleton"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

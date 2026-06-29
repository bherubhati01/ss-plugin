<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="sas-wrap" data-page="calendar">
    <div class="sas-page-header">
        <h1><?php esc_html_e('Calendar', 'social-auto-scheduler'); ?></h1>
        <div class="sas-calendar-legend">
            <span class="sas-legend-item sas-legend-item--scheduled"><?php esc_html_e('Scheduled', 'social-auto-scheduler'); ?></span>
            <span class="sas-legend-item sas-legend-item--published"><?php esc_html_e('Published', 'social-auto-scheduler'); ?></span>
            <span class="sas-legend-item sas-legend-item--failed"><?php esc_html_e('Failed', 'social-auto-scheduler'); ?></span>
        </div>
    </div>

    <div class="sas-card">
        <div class="sas-card__body">
            <div class="sas-calendar-nav">
                <button id="sas-cal-prev" class="sas-btn sas-btn--secondary">&lsaquo; <?php esc_html_e('Prev', 'social-auto-scheduler'); ?></button>
                <h2 id="sas-cal-title" class="sas-calendar-title"></h2>
                <button id="sas-cal-next" class="sas-btn sas-btn--secondary"><?php esc_html_e('Next', 'social-auto-scheduler'); ?> &rsaquo;</button>
            </div>

            <div class="sas-calendar-grid">
                <div class="sas-calendar-header">
                    <?php
                    $days = [
                        __('Sun', 'social-auto-scheduler'),
                        __('Mon', 'social-auto-scheduler'),
                        __('Tue', 'social-auto-scheduler'),
                        __('Wed', 'social-auto-scheduler'),
                        __('Thu', 'social-auto-scheduler'),
                        __('Fri', 'social-auto-scheduler'),
                        __('Sat', 'social-auto-scheduler'),
                    ];
                    foreach ($days as $day) {
                        echo '<div class="sas-calendar-dow">' . esc_html($day) . '</div>';
                    }
                    ?>
                </div>
                <div id="sas-calendar-body" class="sas-calendar-body"></div>
            </div>
        </div>
    </div>

    <!-- Video detail popover -->
    <div id="sas-cal-popover" class="sas-cal-popover" hidden></div>
</div>

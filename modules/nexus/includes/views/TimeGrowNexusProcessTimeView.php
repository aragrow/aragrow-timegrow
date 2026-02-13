<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusProcessTimeView {

    public function display($user, $clients) {
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Process Time Entries', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Select criteria to filter and process time entries for billing', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-paperclip"></span>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="timegrow-card">
                <div class="timegrow-card-header">
                    <div class="timegrow-icon timegrow-icon-primary">
                        <span class="dashicons dashicons-filter"></span>
                    </div>
                    <div class="timegrow-card-title">
                        <h2><?php esc_html_e('Filter Time Entries', 'timegrow'); ?></h2>
                    </div>
                </div>

                <div class="timegrow-card-body">
                    <form method="post" action="" class="timegrow-filter-form">
                        <?php wp_nonce_field('process_time_nonce', 'process_time_nonce_field'); ?>

                        <div class="timegrow-form-row">
                            <div class="timegrow-form-field">
                                <label for="client_id">
                                    <?php esc_html_e('Client', 'timegrow'); ?>
                                    <span class="timegrow-field-hint"><?php esc_html_e('(Optional - Leave empty for all clients)', 'timegrow'); ?></span>
                                </label>
                                <select name="client_id" id="client_id" class="timegrow-select">
                                    <option value=""><?php esc_html_e('-- All Clients --', 'timegrow'); ?></option>
                                    <?php if (!empty($clients)): ?>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo esc_attr($client->ID); ?>">
                                                <?php echo esc_html($client->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="timegrow-form-row timegrow-date-range-row">
                            <div class="timegrow-form-field">
                                <label for="start_date">
                                    <?php esc_html_e('Start Date', 'timegrow'); ?>
                                    <span class="timegrow-field-hint"><?php esc_html_e('(Optional)', 'timegrow'); ?></span>
                                </label>
                                <input type="date" name="start_date" id="start_date" class="timegrow-input" />
                            </div>

                            <div class="timegrow-form-field">
                                <label for="end_date">
                                    <?php esc_html_e('End Date', 'timegrow'); ?>
                                    <span class="timegrow-field-hint"><?php esc_html_e('(Optional)', 'timegrow'); ?></span>
                                </label>
                                <input type="date" name="end_date" id="end_date" class="timegrow-input" />
                            </div>
                        </div>

                        <div class="timegrow-info-box" style="margin: 20px 0;">
                            <span class="dashicons dashicons-info"></span>
                            <div>
                                <strong><?php esc_html_e('How it works:', 'timegrow'); ?></strong>
                                <ul style="margin: 10px 0 0 20px; list-style-type: disc;">
                                    <li><?php esc_html_e('Only unbilled and billable time entries will be processed', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('Leave filters empty to process all unbilled entries', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('WooCommerce orders will be created for each client', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('Time entries will be marked as billed after processing', 'timegrow'); ?></li>
                                </ul>
                            </div>
                        </div>

                        <div class="timegrow-form-actions">
                            <button type="submit" name="process_time_submit" class="button button-primary button-large">
                                <span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Process Time Entries', 'timegrow'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus')); ?>" class="button button-secondary button-large">
                                <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 3px;"></span>
                                <?php esc_html_e('Cancel', 'timegrow'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <style>
            .timegrow-filter-form {
                max-width: 800px;
            }

            .timegrow-form-row {
                margin-bottom: 20px;
            }

            .timegrow-date-range-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            .timegrow-form-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
            }

            .timegrow-field-hint {
                font-weight: 400;
                font-size: 0.9em;
                color: #646970;
                font-style: italic;
            }

            .timegrow-select,
            .timegrow-input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                font-size: 14px;
            }

            .timegrow-select:focus,
            .timegrow-input:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }

            .timegrow-form-actions {
                display: flex;
                gap: 10px;
                padding-top: 10px;
                border-top: 1px solid #dcdcde;
            }

            .timegrow-form-actions .button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }

            .timegrow-info-box {
                background: #f0f6fc;
                border: 1px solid #0c5d9d;
                border-left-width: 4px;
                padding: 15px;
                border-radius: 4px;
                display: flex;
                gap: 12px;
            }

            .timegrow-info-box .dashicons {
                color: #0c5d9d;
                flex-shrink: 0;
                margin-top: 2px;
            }

            .timegrow-info-box ul {
                color: #1d2327;
            }

            @media (max-width: 768px) {
                .timegrow-date-range-row {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
}

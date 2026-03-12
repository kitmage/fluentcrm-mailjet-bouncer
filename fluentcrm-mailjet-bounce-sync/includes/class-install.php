<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

class Install
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $events_table = $wpdb->prefix . 'fcrm_mailjet_events';
        $campaign_map_table = $wpdb->prefix . 'fcrm_mailjet_campaign_map';
        $contact_meta_table = $wpdb->prefix . 'fcrm_mailjet_contact_meta';

        $sql_events = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mailjet_message_id VARCHAR(191) NULL,
            event_type VARCHAR(50) NOT NULL,
            event_time DATETIME NOT NULL,
            email VARCHAR(191) NULL,
            mj_campaign_id VARCHAR(191) NULL,
            mj_contact_id VARCHAR(191) NULL,
            customcampaign VARCHAR(191) NULL,
            custom_id VARCHAR(191) NULL,
            payload_json LONGTEXT NOT NULL,
            processed_at DATETIME NULL,
            processing_result VARCHAR(191) NULL,
            unique_hash CHAR(40) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_hash (unique_hash),
            KEY event_time (event_time),
            KEY email (email)
        ) {$charset_collate};";

        $sql_campaign_map = "CREATE TABLE {$campaign_map_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fluentcrm_campaign_id BIGINT UNSIGNED NULL,
            mailjet_customcampaign VARCHAR(191) NULL,
            mailjet_campaign_id VARCHAR(191) NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY fluentcrm_campaign_id (fluentcrm_campaign_id),
            KEY mailjet_campaign_id (mailjet_campaign_id)
        ) {$charset_collate};";

        $sql_contact_meta = "CREATE TABLE {$contact_meta_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fluentcrm_contact_id BIGINT UNSIGNED NOT NULL,
            last_soft_bounce_at DATETIME NULL,
            soft_bounce_count_30d INT UNSIGNED NOT NULL DEFAULT 0,
            last_hard_bounce_at DATETIME NULL,
            last_blocked_at DATETIME NULL,
            last_spam_at DATETIME NULL,
            last_unsub_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY fluentcrm_contact_id (fluentcrm_contact_id)
        ) {$charset_collate};";

        dbDelta($sql_events);
        dbDelta($sql_campaign_map);
        dbDelta($sql_contact_meta);

        add_option(Settings::OPTION_KEY, Settings::defaults());

        if (! wp_next_scheduled(Cron::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', Cron::HOOK);
        }
    }
}

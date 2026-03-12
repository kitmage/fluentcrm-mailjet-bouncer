<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

class Event_Processor
{
    public static function ingest(array $payload): void
    {
        global $wpdb;

        $events_table = $wpdb->prefix . 'fcrm_mailjet_events';
        $now = current_time('mysql', true);

        $event = self::normalize($payload);
        $dedupe_hash = sha1(
            implode('|', [
                (string) $event['mailjet_message_id'],
                (string) $event['event_type'],
                (string) $event['event_time'],
            ])
        );

        $inserted = $wpdb->insert($events_table, [
            'mailjet_message_id' => $event['mailjet_message_id'],
            'event_type' => $event['event_type'],
            'event_time' => $event['event_time'],
            'email' => $event['email'],
            'mj_campaign_id' => $event['mj_campaign_id'],
            'mj_contact_id' => $event['mj_contact_id'],
            'customcampaign' => $event['customcampaign'],
            'custom_id' => $event['custom_id'],
            'payload_json' => wp_json_encode($payload),
            'processing_result' => 'queued',
            'unique_hash' => $dedupe_hash,
            'created_at' => $now,
        ]);

        if (! $inserted) {
            return;
        }

        $result = self::apply_rules($event);

        $wpdb->update(
            $events_table,
            [
                'processed_at' => current_time('mysql', true),
                'processing_result' => $result,
            ],
            ['unique_hash' => $dedupe_hash]
        );
    }

    private static function normalize(array $payload): array
    {
        $time = isset($payload['time']) ? gmdate('Y-m-d H:i:s', (int) $payload['time']) : current_time('mysql', true);

        return [
            'mailjet_message_id' => sanitize_text_field((string) ($payload['MessageID'] ?? $payload['message_id'] ?? '')),
            'event_type' => sanitize_key((string) ($payload['event'] ?? 'unknown')),
            'event_time' => $time,
            'email' => sanitize_email((string) ($payload['email'] ?? '')),
            'mj_campaign_id' => sanitize_text_field((string) ($payload['mj_campaign_id'] ?? '')),
            'mj_contact_id' => sanitize_text_field((string) ($payload['mj_contact_id'] ?? '')),
            'customcampaign' => sanitize_text_field((string) ($payload['customcampaign'] ?? '')),
            'custom_id' => sanitize_text_field((string) ($payload['CustomID'] ?? $payload['custom_id'] ?? '')),
            'hard_bounce' => ! empty($payload['hard_bounce']),
            'error' => sanitize_text_field((string) ($payload['error'] ?? '')),
        ];
    }

    private static function apply_rules(array $event): string
    {
        $status = null;
        $settings = Settings::get_all();

        switch ($event['event_type']) {
            case 'bounce':
                if (! empty($event['hard_bounce'])) {
                    $status = 'bounced';
                } else {
                    return self::handle_soft_bounce($event);
                }
                break;
            case 'blocked':
                if ((int) $settings['blocked_auto_suppress'] === 1 && self::is_durable_block($event['error'])) {
                    $status = 'bounced';
                }
                break;
            case 'spam':
                $status = 'complained';
                break;
            case 'unsub':
                $status = 'unsubscribed';
                break;
        }

        if (! $status) {
            return 'logged_only';
        }

        $updated = self::update_fluentcrm_status($event['email'], $status);

        return $updated ? 'status_updated:' . $status : 'contact_not_found';
    }

    private static function handle_soft_bounce(array $event): string
    {
        global $wpdb;

        $contact_id = self::find_contact_id_by_email($event['email']);
        if (! $contact_id) {
            return 'soft_bounce_contact_missing';
        }

        $meta_table = $wpdb->prefix . 'fcrm_mailjet_contact_meta';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$meta_table} WHERE fluentcrm_contact_id = %d",
            $contact_id
        ), ARRAY_A);

        $count = isset($row['soft_bounce_count_30d']) ? (int) $row['soft_bounce_count_30d'] + 1 : 1;

        if ($row) {
            $wpdb->update($meta_table, [
                'last_soft_bounce_at' => current_time('mysql', true),
                'soft_bounce_count_30d' => $count,
            ], ['fluentcrm_contact_id' => $contact_id]);
        } else {
            $wpdb->insert($meta_table, [
                'fluentcrm_contact_id' => $contact_id,
                'last_soft_bounce_at' => current_time('mysql', true),
                'soft_bounce_count_30d' => $count,
            ]);
        }

        $settings = Settings::get_all();
        if ($count >= (int) $settings['soft_bounce_threshold']) {
            $updated = self::update_fluentcrm_status($event['email'], 'bounced');

            return $updated ? 'status_updated:bounced_from_soft' : 'soft_bounce_threshold_contact_missing';
        }

        return 'soft_bounce_logged';
    }

    private static function is_durable_block(string $error): bool
    {
        $error = strtolower($error);
        $durable_terms = ['preblocked', 'sender blocked', 'blacklist', 'repeated bounce'];

        foreach ($durable_terms as $term) {
            if (strpos($error, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function update_fluentcrm_status(string $email, string $status): bool
    {
        if (! function_exists('FluentCrmApi')) {
            return false;
        }

        $contact = FluentCrmApi('contacts')->getContact($email);
        if (! $contact || empty($contact->id)) {
            return false;
        }

        FluentCrmApi('contacts')->updateContact([
            'id' => $contact->id,
            'status' => $status,
        ]);

        return true;
    }

    private static function find_contact_id_by_email(string $email): int
    {
        if (! function_exists('FluentCrmApi')) {
            return 0;
        }

        $contact = FluentCrmApi('contacts')->getContact($email);

        return (! $contact || empty($contact->id)) ? 0 : (int) $contact->id;
    }
}

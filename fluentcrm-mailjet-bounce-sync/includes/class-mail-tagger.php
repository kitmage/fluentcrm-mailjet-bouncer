<?php

namespace FCRM_MJ_Sync;

use PHPMailer\PHPMailer\PHPMailer;

if (! defined('ABSPATH')) {
    exit;
}

class Mail_Tagger
{
    public static function register(): void
    {
        add_action('phpmailer_init', [self::class, 'inject_headers']);
    }

    public static function inject_headers(PHPMailer $phpmailer): void
    {
        $campaign_id = 0;
        $contact_id = 0;

        foreach ((array) $phpmailer->getCustomHeaders() as $header) {
            if (! is_array($header) || count($header) < 2) {
                continue;
            }

            if (strtolower((string) $header[0]) === 'x-fcrm-campaign-id') {
                $campaign_id = absint($header[1]);
            }

            if (strtolower((string) $header[0]) === 'x-fcrm-contact-id') {
                $contact_id = absint($header[1]);
            }
        }

        if (! $campaign_id && ! $contact_id && ! self::is_fluentcrm_context()) {
            return;
        }

        $campaign_header = 'fcrm_campaign_' . ($campaign_id ?: 0);
        $custom_id = 'fcrm_contact_' . ($contact_id ?: 0);
        $event_payload = wp_json_encode([
            'fcrm_campaign_id' => $campaign_id,
            'fcrm_contact_id' => $contact_id,
            'source' => 'fluentcrm',
        ]);

        $phpmailer->addCustomHeader('X-Mailjet-Campaign', $campaign_header);
        $phpmailer->addCustomHeader('X-MJ-CustomID', $custom_id);
        $phpmailer->addCustomHeader('X-MJ-EventPayload', $event_payload ?: '{}');
    }

    private static function is_fluentcrm_context(): bool
    {
        return did_action('fluentcrm_loaded') > 0 || doing_action('fluentcrm_sending_emails');
    }
}

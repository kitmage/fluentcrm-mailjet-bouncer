<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

class Cron
{
    public const HOOK = 'fcrm_mj_sync_reconcile';

    public static function register(): void
    {
        add_action(self::HOOK, [self::class, 'run_reconciliation']);

        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function clear_schedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    public static function run_reconciliation(): void
    {
        // Placeholder: Mailjet polling fallback should fetch last 48h events and replay through processor.
        do_action('fcrm_mj_sync_reconciliation_ran', current_time('mysql', true));
    }
}

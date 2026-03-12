<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

class Settings
{
    public const OPTION_KEY = 'fcrm_mj_sync_settings';

    public static function register(): void
    {
        register_setting('fcrm_mj_sync', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public static function defaults(): array
    {
        return [
            'webhook_secret' => wp_generate_password(32, false, false),
            'soft_bounce_threshold' => 3,
            'soft_bounce_window_days' => 30,
            'blocked_auto_suppress' => 1,
            'update_mode' => 'internal',
            'logging_level' => 'info',
        ];
    }

    public static function get_all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);

        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function sanitize($value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'webhook_secret' => sanitize_text_field((string) ($value['webhook_secret'] ?? '')),
            'soft_bounce_threshold' => max(1, absint($value['soft_bounce_threshold'] ?? 3)),
            'soft_bounce_window_days' => max(1, absint($value['soft_bounce_window_days'] ?? 30)),
            'blocked_auto_suppress' => empty($value['blocked_auto_suppress']) ? 0 : 1,
            'update_mode' => in_array(($value['update_mode'] ?? 'internal'), ['internal', 'rest'], true) ? $value['update_mode'] : 'internal',
            'logging_level' => in_array(($value['logging_level'] ?? 'info'), ['error', 'warning', 'info', 'debug'], true) ? $value['logging_level'] : 'info',
        ];
    }
}

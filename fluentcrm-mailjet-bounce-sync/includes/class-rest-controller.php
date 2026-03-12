<?php

namespace FCRM_MJ_Sync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class Rest_Controller
{
    public static function register_routes(): void
    {
        register_rest_route('fcrm-mailjet/v1', '/events', [
            [
                'methods' => 'POST',
                'callback' => [self::class, 'handle_events'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public static function handle_events(WP_REST_Request $request)
    {
        $secret = (string) $request->get_header('x-fcrm-mj-secret');
        $settings = Settings::get_all();

        if (! hash_equals((string) $settings['webhook_secret'], $secret)) {
            return new WP_Error('forbidden', 'Invalid secret', ['status' => 403]);
        }

        $raw = $request->get_body();
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return new WP_Error('invalid_json', 'Invalid JSON payload', ['status' => 400]);
        }

        Event_Processor::ingest($decoded);

        return new WP_REST_Response(['ok' => true], 200);
    }
}

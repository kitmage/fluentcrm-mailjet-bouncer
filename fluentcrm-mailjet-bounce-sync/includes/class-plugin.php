<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

require_once FCRM_MJ_SYNC_DIR . 'includes/class-install.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-settings.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-rest-controller.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-event-processor.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-admin-page.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-cron.php';
require_once FCRM_MJ_SYNC_DIR . 'includes/class-mail-tagger.php';

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        Install::activate();
    }

    public static function deactivate(): void
    {
        Cron::clear_schedule();
    }

    public function boot(): void
    {
        Settings::register();
        Cron::register();
        Mail_Tagger::register();

        add_action('rest_api_init', [Rest_Controller::class, 'register_routes']);
        add_action('admin_menu', [Admin_Page::class, 'register_menu']);
    }
}

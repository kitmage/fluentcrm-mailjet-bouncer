<?php
/**
 * Plugin Name: FluentCRM Mailjet Bounce Sync
 * Description: Syncs Mailjet bounce/suppression events into FluentCRM contact statuses via webhook with polling fallback.
 * Version: 0.1.0
 * Author: Codex
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: fluentcrm-mailjet-bounce-sync
 */

if (! defined('ABSPATH')) {
    exit;
}

define('FCRM_MJ_SYNC_VERSION', '0.1.0');
define('FCRM_MJ_SYNC_FILE', __FILE__);
define('FCRM_MJ_SYNC_DIR', plugin_dir_path(__FILE__));
define('FCRM_MJ_SYNC_URL', plugin_dir_url(__FILE__));

require_once FCRM_MJ_SYNC_DIR . 'includes/class-plugin.php';

register_activation_hook(FCRM_MJ_SYNC_FILE, ['FCRM_MJ_Sync\\Plugin', 'activate']);
register_deactivation_hook(FCRM_MJ_SYNC_FILE, ['FCRM_MJ_Sync\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    FCRM_MJ_Sync\Plugin::instance()->boot();
});

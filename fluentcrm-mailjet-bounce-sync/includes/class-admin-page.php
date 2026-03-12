<?php

namespace FCRM_MJ_Sync;

if (! defined('ABSPATH')) {
    exit;
}

class Admin_Page
{
    public static function register_menu(): void
    {
        add_options_page(
            __('FluentCRM Mailjet Sync', 'fluentcrm-mailjet-bounce-sync'),
            __('FluentCRM Mailjet Sync', 'fluentcrm-mailjet-bounce-sync'),
            'manage_options',
            'fcrm-mailjet-sync',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Settings::get_all();
        $endpoint = rest_url('fcrm-mailjet/v1/events');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FluentCRM Mailjet Bounce Sync', 'fluentcrm-mailjet-bounce-sync'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('fcrm_mj_sync'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Webhook Secret', 'fluentcrm-mailjet-bounce-sync'); ?></th>
                        <td><input type="text" class="regular-text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[webhook_secret]" value="<?php echo esc_attr($settings['webhook_secret']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Soft Bounce Threshold', 'fluentcrm-mailjet-bounce-sync'); ?></th>
                        <td><input type="number" min="1" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[soft_bounce_threshold]" value="<?php echo esc_attr((string) $settings['soft_bounce_threshold']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Soft Bounce Window (days)', 'fluentcrm-mailjet-bounce-sync'); ?></th>
                        <td><input type="number" min="1" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[soft_bounce_window_days]" value="<?php echo esc_attr((string) $settings['soft_bounce_window_days']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-suppress durable blocked events', 'fluentcrm-mailjet-bounce-sync'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[blocked_auto_suppress]" value="1" <?php checked(1, (int) $settings['blocked_auto_suppress']); ?> /> <?php esc_html_e('Enabled', 'fluentcrm-mailjet-bounce-sync'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Webhook Endpoint', 'fluentcrm-mailjet-bounce-sync'); ?></h2>
            <p><code><?php echo esc_html($endpoint); ?></code></p>
        </div>
        <?php
    }
}

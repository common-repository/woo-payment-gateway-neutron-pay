<?php

defined( 'ABSPATH' ) || exit;

class WC_NeutronPay_Admin_Page
{
    public function boot()
    {
        add_action('admin_menu', [$this, 'register']);

		add_action('current_screen', [$this, 'handle']);
    }

    public function register()
    {
        add_submenu_page('options-general.php', 'NeutronPay', 'NeutronPay', 'read', 'neutronpay', '__return_false');
    }

    public function handle()
    {
        $current_screen = get_current_screen();

        if ($current_screen->id === 'settings_page_neutronpay') {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page' => 'wc-settings',
                        'tab' => 'checkout',
                        'section' => 'neutronpay',
                    ],
                    admin_url('admin.php')
                )
            );
            exit();
        }
    }
}

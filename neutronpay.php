<?php
/**
 * Plugin Name: WooCommerce Payment Gateway - Neutronpay
 * Plugin URI: https://neutronpay.com
 * Description: Accept Bitcoin Onchain and Lightning Instantly via Neutronpay
 * Version: 1.3.3
 * Author: Neutronpay
 * Author URI: https://neutronpay.com/
 * Requires at least: 4.0
 * Tested up to: 6.3
 *
 * Text Domain: Neutronpay
 */

defined( 'ABSPATH' ) || exit;

/**
 * NeutronPay's WooCommerce Version
 * 
 * @var string
 */
define('NEUTRONPAY_WOOCOMMERCE_VERSION', '1.3.2');

/**
 * NeutronPay's Environment
 * 
 * @var string live|sandbox
 */
define('NEUTRONPAY_ENVIRONMENT', 'live');

/**
 * NeutronPay's Checkout Path
 * 
 * @var string
 */
define('NEUTRONPAY_CHECKOUT_PATH', NEUTRONPAY_ENVIRONMENT === 'live' ? 'https://client.neutronpay.com/checkout/' : 'https://sandbox.neutronpay.com/checkout/');

function neutronpay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

    require_once(__DIR__ . '/lib/neutronpay/init.php');

    require_once(__DIR__ . '/classes/WC_NeutronPay_Utility.php');
    require_once(__DIR__ . '/classes/WC_NeutronPay_Order.php');
    require_once(__DIR__ . '/classes/WC_Gateway_NeutronPay.php');

    if (is_admin()) {
        require_once(__DIR__ . '/classes/WC_NeutronPay_Admin_Page.php');

        $services = [
            \WC_NeutronPay_Admin_Page::class,
        ];

        foreach ($services as $service) {
            $service = new $service();
            $service->boot();
        }
    }

    function add_neutronpay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_NeutronPay';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_neutronpay_gateway');
}
add_action('plugins_loaded', 'neutronpay_init');

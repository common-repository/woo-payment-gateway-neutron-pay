<?php

defined( 'ABSPATH' ) || exit;

class WC_NeutronPay_Order
{
	/**
	 * WooCommerce Order
	 * 
	 * @var WP_Order
	 */
	protected $order = null;

	/**
	 * Constructor
	 * 
	 * @param  WC_Order $order
	 */
	public function __construct(WC_Order $order)
	{
		$this->order = $order;
	}

	/**
	 *  Get the order's amount.
	 * 
	 * @return float $paid_amount
	 */
	public function get_amount()
	{
		$amount = get_post_meta($this->order->get_id(), 'neutronpay_order_amount', true);

		if ($amount === false) {
			$amount = 0;
		}

		return apply_filters('wc_neutronpay_order_amount', $amount, $this->order);
	}

	/**
	 *  Get the order's paid amount.
	 * 
	 * @return float $paid_amount
	 */
	public function get_paid_amount()
	{
		$paid_amount = get_post_meta($this->order->get_id(), 'neutronpay_order_paid_amount', true);

		if ($paid_amount === false) {
			$paid_amount = 0;
		}

		return apply_filters('wc_neutronpay_order_paid_amount', $paid_amount, $this->order);
	}

	/**
	 * Setup order's underpayment slack settings.
	 * 
	 * @param  bool $override If true, update the values for order's underpayment slack settings.
	 * @return void
	 */
	public function setup_underpayment_slack($override = false)
	{
		$payment_gateways = WC()
			->payment_gateways()
			->payment_gateways(); // Not a bug ;)

		if (empty($payment_gateways) || !isset($payment_gateways['neutronpay'])) {
			return;
		}

		$payment_gateway = $payment_gateways['neutronpay'];

		if ($payment_gateway->has_underpayment_slack_setup() === false) {
			return;
		}

		if ($this->has_underpayment_slack_setup() && $override === false) {
			return;
		}

		$order_id = $this->order->get_id();

		update_post_meta($order_id, 'neutronpay_order_underpayment_slack_percentage', $payment_gateway->get_underpayment_slack_percentage());

		do_action('wc_neutronpay_order_underpayment_slacked', $this->order);
	}

	/**
	 * Check if this order has underpayment slack already setup.
	 *
	 * @return bool
	 */
	public function has_underpayment_slack_setup()
	{
		$underpayment_slack_percentage = get_post_meta($this->order->get_id(), 'neutronpay_order_underpayment_slack_percentage', true);

		if ($underpayment_slack_percentage === false || $underpayment_slack_percentage <= 0) {
			return false;
		}

		return true;
	}

	/**
	 * Check if this order has underpayment slack after being paid. 
	 *
	 * @return bool $has_underpayment_slack
	 */
	public function has_underpayment_slack()
	{
		$has_underpayment_slack = false;

		if ($this->order->get_total() > 0) {
			$amount = $this->get_amount();
			$paid_amount = $this->get_paid_amount();
			$underpayment_slack_percentage = $this->get_underpayment_slack_percentage();

			if ($amount > 0 && $amount > $paid_amount && $underpayment_slack_percentage > 0) {
				$has_underpayment_slack = ($amount - $paid_amount) <= ($amount * $underpayment_slack_percentage / 100);
			}
		}

		return apply_filters('wc_neutronpay_order_has_underpayment_slack', $has_underpayment_slack, $this->order);
	}

	/**
	 * Calculate the order's underpayment slack difference.
	 *
	 * @return float $underpayment_slack_difference
	 */
	public function get_underpayment_slack_difference()
	{
		$underpayment_slack_difference = 0;

		if ($this->order->get_total() > 0) {
			$underpayment_slack_difference = $this->get_amount() - $this->get_paid_amount();
		}

		return apply_filters('wc_neutronpay_order_underpayment_slack_difference', $underpayment_slack_difference, $this->order);
	}

	/**
	 * Get the set order's underpayment slack percentage.
	 * 
	 * @return double $underpayment_slack_percentage
	 */
	public function get_underpayment_slack_percentage()
	{
		$underpayment_slack_percentage = get_post_meta($this->order->get_id(), 'neutronpay_order_underpayment_slack_percentage', true);
		$underpayment_slack_percentage = floatval($underpayment_slack_percentage);

		return apply_filters('wc_neutronpay_order_underpayment_slack_percentage', $underpayment_slack_percentage, $this->order);
	}
}

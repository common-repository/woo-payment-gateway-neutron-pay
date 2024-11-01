<?php

defined( 'ABSPATH' ) || exit;

class WC_NeutronPay_Utility
{
	public function to_satoshi($amount)
	{
		return $amount * 100000000;
	}

	public function to_decimal($amount)
	{
		return $amount / 100000000;
	}
}

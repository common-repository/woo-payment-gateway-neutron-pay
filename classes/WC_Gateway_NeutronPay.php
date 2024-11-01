<?php

defined( 'ABSPATH' ) || exit;

class WC_Gateway_NeutronPay extends WC_Payment_Gateway
{
	private $neutronpay_utility = null;

    public function __construct()
    {
        global $woocommerce;

        $this->neutronpay_utility = new WC_NeutronPay_Utility();

        $this->id = 'neutronpay';
        $this->has_fields = false;
        $this->method_title = 'Neutronpay';
        $this->icon = apply_filters('woocommerce_neutronpay_icon', PLUGIN_DIR . 'assets/images/bitcoin.png');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'Bitcoin Lightning';
        $this->description = $this->get_option('description');
        $this->api_secret = $this->get_option('api_secret');
        $this->api_auth_token = (empty($this->get_option('api_auth_token')) ? $this->get_option('api_secret') : $this->get_option('api_auth_token'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_neutronpay', array($this, 'thankyou'));
        add_action('woocommerce_api_wc_gateway_neutronpay', array($this, 'payment_callback'));
    }

    public function admin_options()
    {
        ?>
        <h3><?php _e('Neutronpay', 'Neutronpay'); ?></h3>
        <p><?php _e('Accept Bitcoin instantly through neutronpay.com.', 'Neutronpay'); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php

    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable Neutronpay', 'Neutronpay'),
                'label' => __('Enable Bitcoin payments via Neutronpay', 'Neutronpay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'description' => array(
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('The payment method description which a customer sees at the checkout of your store.', 'Neutronpay'),
                'default' => __('Powered by Neutronpay'),
            ),
            'api_auth_token' => array(
                'title' => __('API Auth Token', 'Neutronpay'),
                'type' => 'text',
                'description' => __('Your personal API Key. Generate one <a href="https://client.neutronpay.com" target="_blank">here</a>.  ', 'Neutronpay'),
                'default' => (empty($this->get_option('api_secret')) ? '' : $this->get_option('api_secret')),
            ),
            'underpayment_slack_enabled' => array(
                'title' => __('Enable Underpayment Slack', 'Neutronpay'),
                'label' => __('Enable Underpayment Slack feature', 'Neutronpay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'underpayment_slack_percentage' => array(
                'title' => __('Underpayment Slack Percentage (%)', 'Neutronpay'),
                'type' => 'number',
                'description' => __('Allow some payments that are off by a small threshold percentage.', 'Neutronpay'),
                'default' => 0,
                'desc_tip'  => true,
                'custom_attributes' => [
                    'step' => 0.1,
                    'min'  => 0,
                    'max'  => 20,
                ],
            ),
            'underpayment_slack_order_status' => array(
                'title' => __('Underpayment Slack Order Status', 'Neutronpay'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
                'description' => __('Change the order status if an order undergoes an underpayment slack.', 'Neutronpay'),
                'default' => 'wc-pending',
                'options' => wc_get_order_statuses(),
                'desc_tip'  => true,
                'custom_attributes' => [
                    'data-placeholder' => __('Select order status', 'Neutronpay'),
                ],
            ),
        );
    }

    public function thankyou()
    {
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $this->init_neutronpay();

        $description = array();
        foreach ($order->get_items('line_item') as $item) {
            $description[] = $item['qty'] . ' × ' . $item['name'];
        }


        $neutronpay_order_id = get_post_meta($order->get_id(), 'neutronpay_order_id', true);
		if (!empty($neutronpay_order_id)) {
			$existsOrderStatus = \Neutronpay\Merchant\Order::find($neutronpay_order_id);
			if ($existsOrderStatus && $existsOrderStatus->id && $existsOrderStatus->id === $neutronpay_order_id && ($existsOrderStatus->status === 'unpaid' ||  $existsOrderStatus->status === 'expired')) {
                $deleteParams = array(
                    'transactionId' => $neutronpay_order_id
                );
				$deleteStatus = \Neutronpay\Merchant\Order::delete($deleteParams);
				if ($deleteStatus) {
					$neutronpay_order_id = null;
					update_post_meta($order_id, 'neutronpay_order_id', null);
				}
			}
		}
        if (empty($neutronpay_order_id)) {
            $params = array(
                'order_id'          => $order->get_id(),
                'price'             => (strtoupper(get_woocommerce_currency()) === 'BTC') ? number_format($this->neutronpay_utility->to_satoshi($order->get_total()), 8, '.', '') : number_format($order->get_total(), 8, '.', ''),
                'fiat'              => get_woocommerce_currency(),
                'callback_url'      => trailingslashit(get_bloginfo('wpurl')) . '?wc-api=wc_gateway_neutronpay',
                'success_url'       => add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), $this->get_return_url($order))),
                'cancelled_url'     => home_url(),
                'description'       => implode(', ', $description),
                'name'              => $order->get_formatted_billing_full_name(),
                'email'             => $order->get_billing_email(),
                'plugin'            => 'woocommerce',
                'website_name'       => get_bloginfo('name')
            );
            $neutronpay_order = \Neutronpay\Merchant\Order::create($params);
            $neutronpay_order_id = $neutronpay_order->id;

            update_post_meta($order_id, 'neutronpay_environment', NEUTRONPAY_ENVIRONMENT);
            update_post_meta($order_id, 'neutronpay_order_id', $neutronpay_order_id);
            update_post_meta($order_id, 'neutronpay_order_amount', $neutronpay_order->price_sats);
            update_post_meta($order_id, 'neutronpay_order_lightning_invoice_payment_request', $neutronpay_order->lightning_invoice['payReq']);
            update_post_meta($order_id, 'neutronpay_order_chain_invoice_address', $neutronpay_order->chain_invoice['address']);

            return array(
                'result' => 'success',
                'redirect' => NEUTRONPAY_CHECKOUT_PATH . $neutronpay_order_id,
            );
        }
        else {
            return array(
                'result' => 'success',
                'redirect' => NEUTRONPAY_CHECKOUT_PATH . $neutronpay_order_id,
            );
        }
    }

    public function is_underpayment_slack_enabled()
    {
        return $this->get_option('underpayment_slack_enabled', 'no') === 'yes';
    }

    public function get_underpayment_slack_percentage($default = 0)
    {
        return $this->get_option('underpayment_slack_percentage', $default);
    }

    public function has_underpayment_slack_setup()
    {
        if ($this->is_underpayment_slack_enabled() === false) {
            return false;
        }

        if ($this->get_underpayment_slack_percentage() <= 0) {
            return false;
        }
        
        return true;
    }

    public function payment_fields()
    {
        $description = $this->get_description();

        if ($description) {
            $description = str_replace('Neutronpay', '', $description);
            $description .= '<img src="' . PLUGIN_DIR . 'assets/images/neutronpay.png' . '" alt="' . esc_attr($this->get_title()) . '" />';

            echo wpautop(wptexturize($description));
        }
    }

    public function payment_callback()
    {
        $request = $_REQUEST;
        $order = wc_get_order($request['order_id']);

        try {
            if (!$order || !$order->get_id()) {
                throw new Exception('Order #' . $request['order_id'] . ' does not exists');
            }

            $token = get_post_meta($order->get_id(), 'neutronpay_order_id', true);

            if (empty($token) ) {
                throw new Exception('Order has Neutronpay ID associated');
            }


            if (strcmp(hash_hmac('sha256', $token, $this->api_auth_token), $request['hashed_order']) != 0) {
                throw new Exception('Request is not signed with the same API Key, ignoring.');
            }

            $this->init_neutronpay();
            $cgOrder = \Neutronpay\Merchant\Order::find($request['id']);

            if (!$cgOrder) {
                throw new Exception('Neutronpay Order #' . $order->get_id() . ' does not exists');
            }

            update_post_meta($order->get_id(), 'neutronpay_order_paid_amount', $cgOrder->paidAmount);

            if ($cgOrder->method ?? null) {
                update_post_meta($order->get_id(), 'neutronpay_order_method', $cgOrder->method);

                switch ($cgOrder->method) {
                    case 'lightning':
                        delete_post_meta($order->get_id(), 'neutronpay_order_chain_invoice_address');
                        break;

                    case 'on-chain':
                        delete_post_meta($order->get_id(), 'neutronpay_order_lightning_invoice_payment_request');
                        break;
                }
            }

            switch ($cgOrder->status) {
                case 'expired-paid':
                case 'paid':
                    $statusWas = $order->get_status();
                    $order->add_order_note(__('Payment is settled and has been credited to your Neutronpay account. Purchased goods/services can be securely delivered to the customer.', 'Neutronpay'));
                    $order->payment_complete();

                    if ($order->get_status() === 'processing' && ($statusWas === 'expired' || $statusWas === 'cancelled')) {
                        WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id());
                    }
                    if (($order->get_status() === 'processing' || $order->get_status() == 'completed') && ($statusWas === 'expired' || $statusWas === 'cancelled')) {
                        WC()->mailer()->emails['WC_Email_New_Order']->trigger($order->get_id());
                    }
                    break;
                case 'expired-partially-paid':
                case 'partially-paid':
                    $paidAmount = $cgOrder->paidAmount;
                    $method = $cgOrder->method;
                    $coin = $method;
                    if ($method === 'on-chain' || $method === 'lightning') {
                        $paidAmount = number_format($this->neutronpay_utility->to_decimal($paidAmount), 8, '.', '');
                        $coin = 'BTC';
                    }

                    $wc_neutronpay_order = new WC_NeutronPay_Order($order);
                    $wc_neutronpay_order->setup_underpayment_slack();

                    if ($wc_neutronpay_order->has_underpayment_slack() === true) {
                        $underpayment_slack_order_status = $this->get_option('underpayment_slack_order_status', 'pending');
                        $underpayment_slack_order_status = 'wc-' === substr($underpayment_slack_order_status, 0, 3) ? substr($underpayment_slack_order_status, 3) : $underpayment_slack_order_status;

                        $underpayment_slack_percentage = $wc_neutronpay_order->get_underpayment_slack_percentage();
                        $actual_underpayment_slack_percentage = (100 - ($wc_neutronpay_order->get_paid_amount() / $wc_neutronpay_order->get_amount() * 100));

                        $order->update_status($underpayment_slack_order_status);
                        $order->add_order_note(
                            sprintf(
                                __('Customer has partially-paid with amount of %1$s %2$s. (Underpayment Slack Percentage: %3$.2f%% of %4$.2f%%)', 'Neutronpay'),
                                $paidAmount,
                                $coin,
                                $actual_underpayment_slack_percentage,
                                $underpayment_slack_percentage
                            )
                        );
                    } else {
                        $underpayment_slack_percentage = $this->get_underpayment_slack_percentage();
                        $actual_underpayment_slack_percentage = (100 - ($wc_neutronpay_order->get_paid_amount() / $wc_neutronpay_order->get_amount() * 100));

                        $order->update_status('pending');
                        $order->add_order_note(
                            sprintf(
                                __('Customer has partially-paid with amount of %1$s %2$s. Waiting on user to send the remainder before marking as PAID. (Underpayment Slack Percentage: %3$.2f%% over %4$.2f%%)', 'Neutronpay'),
                                $paidAmount,
                                $coin,
                                $actual_underpayment_slack_percentage,
                                $underpayment_slack_percentage
                            )
                        );
                    }
                    break;
                case 'processing':
                case 'pending':
                    $order->add_order_note(__('Customer has paid via standard on-chain. Payment is awaiting 2 confirmations on the Bitcoin network, DO NOT SEND purchased goods/services UNTIL payment has been marked as PAID.', 'Neutronpay'));
                    break;
                case 'pending-partial':
                    $paidAmount = $cgOrder->paidAmount;
                    $method = $cgOrder->method;
                    $coin = $method;

                    if ($method === 'on-chain' || $method === 'lightning') {
                        $paidAmount = number_format($this->neutronpay_utility->to_decimal($paidAmount), 8, '.', '');
                        $coin = 'BTC';
                    }

                    $wc_neutronpay_order = new WC_NeutronPay_Order($order);
                    $wc_neutronpay_order->setup_underpayment_slack();

                    if ($wc_neutronpay_order->has_underpayment_slack() === true) {
                        $underpayment_slack_percentage = $wc_neutronpay_order->get_underpayment_slack_percentage();
                        $actual_underpayment_slack_percentage = (100 - ($wc_neutronpay_order->get_paid_amount() / $wc_neutronpay_order->get_amount() * 100));

                        $order->add_order_note(
                            sprintf(
                                __('Customer has partially-paid via standard on-chain with amount of %1$s %2$s.  Payment is awaiting 2 confirmations on the Bitcoin network, DO NOT SEND purchased goods/services UNTIL payment has been marked as PAID. (Underpayment Slack Percentage: %3$.2f%% of %4$.2f%%)', 'Neutronpay'),
                                $paidAmount,
                                $coin,
                                $actual_underpayment_slack_percentage,
                                $underpayment_slack_percentage
                            )
                        );
                    } else {
                        $underpayment_slack_percentage = $this->get_underpayment_slack_percentage();
                        $actual_underpayment_slack_percentage = (100 - ($wc_neutronpay_order->get_paid_amount() / $wc_neutronpay_order->get_amount() * 100));

                        $order->update_status('pending');
                        $order->add_order_note(
                            sprintf(
                                __('Customer has partially-paid via standard on-chain with amount of %1$s %2$s. Payment is awaiting 2 confirmations on the Bitcoin network. Waiting on user to send the remainder before marking as PAID. (Underpayment Slack Percentage: %3$.2f%% over %4$.2f%%)', 'Neutronpay'),
                                $paidAmount,
                                $coin,
                                $actual_underpayment_slack_percentage,
                                $underpayment_slack_percentage
                            )
                        );
                    }
                    break;
                case 'expired':
                    if ($order->get_status() === 'pending') {
                        $order->add_order_note(__('Customer has not completed full payment', 'Neutronpay'));
                        $order->update_status('cancelled');
                    }
                    break;
            }
        } catch (Exception $e) {
            die(get_class($e) . ': ' . $e->getMessage());
        }
    }

    private function init_neutronpay()
    {
        \Neutronpay\Neutronpay::config(
            array(
                'auth_token'    => (empty($this->api_auth_token) ? $this->api_secret : $this->api_auth_token),
                'environment'   => NEUTRONPAY_ENVIRONMENT,
                'user_agent'    => ('Neutronpay - WooCommerce v' . WOOCOMMERCE_VERSION . ' Plugin v' . NEUTRONPAY_WOOCOMMERCE_VERSION)
            )
        );
    }
}

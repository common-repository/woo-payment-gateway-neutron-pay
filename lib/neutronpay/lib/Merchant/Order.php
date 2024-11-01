<?php
namespace Neutronpay\Merchant;

use Neutronpay\Neutronpay;
use Neutronpay\Merchant;
use Neutronpay\OrderIsNotValid;
use Neutronpay\OrderNotFound;

class Order extends Merchant
{
    private $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function toHash()
    {
        return $this->order;
    }

    public function __get($name)
    {
        return $this->order[$name];
    }

    public static function find($orderId, $options = array(), $authentication = array())
    {
        try {
            return self::findOrFail($orderId, $options, $authentication);
        } catch (OrderNotFound $e) {
            return false;
        }
    }

    public static function delete($params, $options = array(), $authentication = array())
    {
  		try {
	  		Neutronpay::request('/1/deleteOrder', 'POST', $params, $authentication);
		  	return true;
  		} catch(Exception $e) {
	  		return false;
		  }
    }

    public static function findOrFail($orderId, $options = array(), $authentication = array())
    {
        $order = Neutronpay::request('/1/status/' . $orderId, 'GET', array(), $authentication);

        return new self($order);
    }

    public static function create($params, $options = array(), $authentication = array())
    {
        try {
            return self::createOrFail($params, $options, $authentication);
        } catch (OrderIsNotValid $e) {
            return false;
        }
    }

    public static function createOrFail($params, $options = array(), $authentication = array())
    {
        $order = Neutronpay::request('/1/orders', 'POST', $params, $authentication);

        return new self($order);
    }
}
